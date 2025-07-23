<?php
// wallet_functions.php
// هذا الملف يحتوي على دوال مساعدة لإدارة المحافظ الإلكترونية ومعاملاتها.

/**
 * دالة لتوليد رقم حساب فريد مكون من 6 أرقام.
 * تتحقق من عدم تكرار الرقم في جدول 'wallets'.
 *
 * @param mysqli $con كائن اتصال قاعدة البيانات.
 * @return string|bool رقم حساب فريد كسلسلة نصية عند النجاح، أو false عند الفشل.
 */
function generateUniqueAccountNumber($con) {
    if (!$con) {
        error_log("generateUniqueAccountNumber: Database connection is not available.");
        return false;
    }
    do {
        // توليد رقم عشوائي بين 100000 و 999999 (6 أرقام)
        $number = mt_rand(100000, 999999);
        $stmt = $con->prepare("SELECT account_number FROM wallets WHERE account_number = ?");
        if ($stmt === false) {
            error_log("generateUniqueAccountNumber: Failed to prepare statement: " . $con->error);
            return false;
        }
        $stmt->bind_param("s", $number);
        $stmt->execute();
        $stmt->store_result();
        $is_unique = ($stmt->num_rows == 0); // تحقق مما إذا كان الرقم فريدًا
        $stmt->close();
    } while (!$is_unique); // كرر إذا لم يكن فريدًا
    return (string)$number; // إرجاع الرقم كسلسلة نصية
}

/**
 * دالة لإنشاء محفظة جديدة لمستخدم.
 * تقوم بتوليد رقم حساب فريد وتشفير كلمة المرور وتعيين رصيد أولي 0.
 *
 * @param int $user_id معرف المستخدم الذي سيتم إنشاء المحفظة له.
 * @param mysqli $con كائن اتصال قاعدة البيانات.
 * @param string $wallet_password كلمة المرور التي سيتم تعيينها للمحفظة (نص عادي).
 * @return array ترجع مصفوفة تحتوي على 'success' و 'account_number' عند النجاح، أو 'error' عند الفشل.
 */
function createWallet($user_id, $con, $wallet_password) {
    if (!$con) {
        return ['success' => false, 'error' => 'Database connection is not available.'];
    }

    $account_number = generateUniqueAccountNumber($con);
    if ($account_number === false) {
        return ['success' => false, 'error' => 'Failed to generate unique account number.'];
    }

    $wallet_password_hash = password_hash($wallet_password, PASSWORD_DEFAULT);
    $initial_balance = 0.00;
    $currency = 'YER'; // عملة افتراضية
    $status = 'active'; // حالة المحفظة الافتراضية

    // استعلام الإدراج مع جميع الأعمدة المطلوبة
    $stmt = $con->prepare("INSERT INTO wallets (user_id, balance, account_number, password_hash, currency, status) VALUES (?, ?, ?, ?, ?, ?)");

    if ($stmt) {
        // 'idssss' تعني: i=integer (user_id), d=double (balance), s=string (account_number), s=string (password_hash), s=string (currency), s=string (status)
        $stmt->bind_param("idssss", $user_id, $initial_balance, $account_number, $wallet_password_hash, $currency, $status);

        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true, 'account_number' => $account_number];
        } else {
            error_log("createWallet: Error executing statement: " . $stmt->error);
            $stmt->close();
            return ['success' => false, 'error' => $stmt->error];
        }
    } else {
        error_log("createWallet: Error preparing statement: " . $con->error);
        return ['success' => false, 'error' => $con->error];
    }
}


function getWalletBalance($userId, $con) {
    if (!$con) {
        error_log("getWalletBalance: Database connection is not available.");
        return 0.00;
    }
    $stmt = $con->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    if ($stmt === false) {
        error_log("getWalletBalance: Failed to prepare statement: " . $con->error);
        return 0.00;
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return (float)$row['balance']; // التأكد من إرجاع float
    }
    $stmt->close();
    return 0.00;
}

/**
 * معالجة معاملة محفظة (إيداع، سحب، إلخ).
 * تستخدم نظام المعاملات (transactions) لضمان اتساق البيانات.
 *
 * @param int $userId معرف المستخدم.
 * @param float $amount المبلغ المراد معالجته.
 * @param string $type نوع المعاملة ('deposit', 'withdraw', 'order_payment', 'refund').
 * @param string $description وصف المعاملة.
 * @param int|null $orderId معرف الطلب المرتبط (اختياري، ويمكن أن يكون NULL).
 * @param mysqli $con كائن اتصال قاعدة البيانات.
 * @return bool True عند النجاح، False عند الفشل.
 */
function processWalletTransaction($userId, $amount, $type, $description, $orderId = null, $con) {
    if (!$con) {
        error_log("processWalletTransaction: Database connection is not available.");
        return false;
    }

    // 1. جلب بيانات المحفظة
    $stmt_wallet = $con->prepare("SELECT id, balance FROM wallets WHERE user_id = ?");
    if ($stmt_wallet === false) {
        error_log("processWalletTransaction: Failed to prepare wallet select statement: " . $con->error);
        return false;
    }
    $stmt_wallet->bind_param("i", $userId);
    $stmt_wallet->execute();
    $result_wallet = $stmt_wallet->get_result();
    $walletData = $result_wallet->fetch_assoc();
    $stmt_wallet->close();

    if (!$walletData) {
        error_log("processWalletTransaction: Wallet not found for user ID: " . $userId);
        return false;
    }

    $walletId = $walletData['id'];
    $currentBalance = (float)$walletData['balance'];
    $newBalance = $currentBalance;

    // 2. حساب الرصيد الجديد والتحقق من الشروط
    if ($type == 'deposit' || $type == 'refund') {
        $newBalance += $amount;
    } elseif ($type == 'withdraw' || $type == 'order_payment') {
        if ($currentBalance < $amount) {
            error_log("processWalletTransaction: Insufficient funds for user ID: " . $userId . " (Current: " . $currentBalance . ", Attempted: " . $amount . ") - Type: " . $type);
            return false;
        }
        $newBalance -= $amount;
    } else {
        error_log("processWalletTransaction: Invalid transaction type provided: " . $type);
        return false;
    }

    // 3. بدء معاملة قاعدة البيانات (Transaction) لضمان اتساق البيانات
    $con->begin_transaction();

    try {
        // أ. تحديث رصيد المحفظة
        $stmt_update = $con->prepare("UPDATE wallets SET balance = ? WHERE id = ?");
        if ($stmt_update === false) {
            throw new Exception("processWalletTransaction: Failed to prepare update statement: " . $con->error);
        }
        $stmt_update->bind_param("di", $newBalance, $walletId);
        if (!$stmt_update->execute()) {
            throw new Exception("processWalletTransaction: Failed to update wallet balance: " . $stmt_update->error);
        }
        $stmt_update->close();

        // ب. إدراج سجل المعاملة
        $stmt_transaction = null; // تهيئة المتغير
        if ($orderId === null) {
            $stmt_transaction_sql = "INSERT INTO wallet_transactions (wallet_id, user_id, amount, type, description) VALUES (?, ?, ?, ?, ?)";
            $stmt_transaction = $con->prepare($stmt_transaction_sql);
            if ($stmt_transaction === false) {
                throw new Exception("processWalletTransaction: Failed to prepare transaction insert statement (NULL orderId): " . $con->error);
            }
            $stmt_transaction->bind_param("iidss", $walletId, $userId, $amount, $type, $description); // d for amount, s for description (5 params: i, i, d, s, s)
        } else {
            $stmt_transaction_sql = "INSERT INTO wallet_transactions (wallet_id, user_id, order_id, amount, type, description) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_transaction = $con->prepare($stmt_transaction_sql);
            if ($stmt_transaction === false) {
                throw new Exception("processWalletTransaction: Failed to prepare transaction insert statement (with orderId): " . $con->error);
            }
            $stmt_transaction->bind_param("iiidss", $walletId, $userId, $orderId, $amount, $type, $description); // (6 params: i, i, i, d, s, s)
        }


        if (!$stmt_transaction->execute()) {
            throw new Exception("processWalletTransaction: Failed to insert wallet transaction: " . $stmt_transaction->error);
        }
        $stmt_transaction->close();

        // 4. تأكيد المعاملة إذا نجحت كل الخطوات
        $con->commit();
        return true;

    } catch (Exception $e) {
        // 5. التراجع عن المعاملة في حالة حدوث أي خطأ
        $con->rollback();
        error_log("processWalletTransaction: Wallet transaction failed for user ID: " . $userId . " - " . $e->getMessage());
        return false;
    }
}

/**
 * جلب سجل المعاملات لمستخدم معين.
 *
 * @param int $userId معرف المستخدم.
 * @param mysqli $con كائن اتصال قاعدة البيانات.
 * @return array مصفوفة بسجل المعاملات، أو مصفوفة فارغة في حالة عدم وجود معاملات أو خطأ.
 */
function getWalletTransactions($userId, $con) {
    if (!$con) {
        error_log("getWalletTransactions: Database connection is not available.");
        return [];
    }

    // 1. جلب معرف المحفظة بناءً على معرف المستخدم
    $stmt_wallet = $con->prepare("SELECT id FROM wallets WHERE user_id = ?");
    if ($stmt_wallet === false) {
        error_log("getWalletTransactions: Failed to prepare wallet ID statement (getWalletTransactions): " . $con->error);
        return [];
    }
    $stmt_wallet->bind_param("i", $userId);
    $stmt_wallet->execute();
    $result_wallet = $stmt_wallet->get_result();
    $walletData = $result_wallet->fetch_assoc();
    $stmt_wallet->close();

    if (!$walletData) {
        return []; // لا توجد محفظة لهذا المستخدم
    }
    $walletId = $walletData['id']; // استخدم 'id'

    // 2. جلب جميع المعاملات المرتبطة بهذه المحفظة
    $stmt_transactions = $con->prepare("SELECT * FROM wallet_transactions WHERE wallet_id = ? ORDER BY created_at DESC");
    if ($stmt_transactions === false) {
        error_log("getWalletTransactions: Failed to prepare transactions statement: " . $con->error);
        return [];
    }
    $stmt_transactions->bind_param("i", $walletId);
    $stmt_transactions->execute();
    $result = $stmt_transactions->get_result();
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $stmt_transactions->close();
    return $transactions;
}

?>