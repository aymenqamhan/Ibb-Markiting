<?php
// deposit_to_wallet.php
session_start();
include('../../include/connect_DB.php'); // مسار الاتصال بقاعدة البيانات
include('./wallet_functions.php');   // مسار دوال المحفظة

// التحقق من صلاحيات المستخدم: يجب أن يكون مسؤولاً (role_id 1) أو موظفًا مخولًا (role_id 5)
if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 5)) {
    $_SESSION['error_message'] = "ليس لديك صلاحية الوصول إلى هذه الصفحة.";
    header("Location: /NEW_IBB/login.php");
    exit();
}

// معالجة رسائل النظام (نجاح/خطأ)
$message = '';
if (isset($_SESSION['message'])) {
    $msg_type = $_SESSION['message']['type'];
    $msg_text = $_SESSION['message']['text'];
    $bootstrap_alert_type = '';
    if ($msg_type == 'success') {
        $bootstrap_alert_type = 'alert-success';
    } elseif ($msg_type == 'error') {
        $bootstrap_alert_type = 'alert-danger';
    } else {
        $bootstrap_alert_type = 'alert-info';
    }
    $message = "<div class='alert $bootstrap_alert_type alert-dismissible fade show' role='alert'>
                    $msg_text
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
    unset($_SESSION['message']);
}

// جلب قائمة المستخدمين الذين لديهم محافظ
$users_with_wallets = [];
if ($con) {
    // استعلام لجلب المستخدمين (عملاء وموظفين) الذين لديهم محفظة
    $stmt_users = $con->prepare("
        SELECT ut.id, ut.name, w.account_number
        FROM user_tb ut
        JOIN wallets w ON ut.id = w.user_id
        WHERE ut.role_id IN (2, 1, 5) -- افترض أن 2 للعملاء، 1 و 5 للموظفين
        ORDER BY ut.name
    ");
    if ($stmt_users) {
        $stmt_users->execute();
        $result_users = $stmt_users->get_result();
        while ($row = $result_users->fetch_assoc()) {
            $users_with_wallets[] = $row;
        }
        $stmt_users->close();
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => "خطأ في جلب قائمة المستخدمين أصحاب المحافظ: " . $con->error];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
} else {
    $_SESSION['message'] = ['type' => 'error', 'text' => "خطأ في الاتصال بقاعدة البيانات."];
}


// معالجة طلب الإيداع
if (isset($_POST['deposit_funds']) && $con) {
    $user_id_to_deposit = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    $amount_to_deposit = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    $deposit_description = trim($_POST['description']); // وصف عملية الإيداع

    // التحقق من صحة المدخلات
    if (!$user_id_to_deposit) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'الرجاء اختيار مستخدم صالح.'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    if ($amount_to_deposit <= 0 || $amount_to_deposit === false) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'الرجاء إدخال مبلغ إيداع صالح وموجب.'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    if (empty($deposit_description)) {
        $deposit_description = 'إيداع رصيد'; // وصف افتراضي إذا لم يتم إدخال شيء
    }

    // استدعاء دالة معالجة المعاملة للإيداع
    $transaction_successful = processWalletTransaction(
        $user_id_to_deposit,
        $amount_to_deposit,
        'deposit', // نوع المعاملة
        $deposit_description,
        null, // لا يوجد معرف طلب مرتبط بهذا الإيداع المباشر
        $con
    );

    if ($transaction_successful) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'تم إيداع المبلغ بنجاح في المحفظة.'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'فشل عملية الإيداع. يرجى مراجعة سجلات الخطأ.'];
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيداع إلى محفظة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../Style.css">
    <style>
        body {
            background-color: #f8f9fa; /* لون خلفية فاتح */
        }
        .card {
            border-radius: 1rem; /* حواف دائرية للبطاقة */
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); /* ظل أنيق */
        }
        .btn-primary {
            background-color: #28a745; /* أخضر للإيداع */
            border-color: #28a745;
        }
        .btn-primary:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card shadow-lg p-4 mx-auto" style="max-width: 600px;">
            <h1 class="card-title text-center mb-4 text-success">
                <i class="bi bi-cash-stack me-2"></i> إيداع إلى محفظة
            </h1>
            <p class="text-center text-muted mb-4">
                قم بإيداع مبلغ إلى محفظة أحد العملاء أو الموظفين.
            </p>

            <?php echo $message; ?>

            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="user_id" class="form-label fw-bold">اختر المستخدم:</label>
                        <select id="user_id" name="user_id" class="form-select" required>
                            <option value="">-- اختر مستخدمًا لديه محفظة --</option>
                            <?php if (empty($users_with_wallets)): ?>
                                <option value="" disabled>لا يوجد مستخدمون لديهم محافظ حالياً</option>
                            <?php else: ?>
                                <?php foreach ($users_with_wallets as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['id']); ?>"
                                            data-account-number="<?php echo htmlspecialchars($user['account_number']); ?>">
                                        <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['account_number']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="form-text text-muted">اختر المستخدم الذي ترغب في الإيداع إلى محفظته.</div>
                    </div>

                    <div class="mb-3">
                        <label for="account_number_display" class="form-label fw-bold">رقم الحساب:</label>
                        <input type="text" id="account_number_display" class="form-control" readonly placeholder="يظهر هنا رقم حساب المحفظة">
                    </div>

                    <div class="mb-3">
                        <label for="amount" class="form-label fw-bold">المبلغ المراد إيداعه:</label>
                        <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0.01" required placeholder="مثال: 50.00">
                        <div class="form-text text-muted">أدخل المبلغ النقدي الذي سيتم إيداعه.</div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold">الوصف/الملاحظات (اختياري):</label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="مثال: إيداع شهري، مكافأة أداء..."></textarea>
                        <div class="form-text text-muted">أضف وصفاً مختصراً لهذه المعاملة.</div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" name="deposit_funds" class="btn btn-primary btn-lg">
                            <i class="bi bi-wallet-fill me-2"></i> إيداع الآن
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-4 text-center">
                <button class="btn btn-secondary" onclick="window.location.href='../dashbord.php';">
                    <i class="bi bi-arrow-right-circle-fill me-2"></i> العودة للوحة التحكم
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // سكريبت JavaScript لتحديث رقم الحساب عند اختيار المستخدم
        document.getElementById('user_id').addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var accountNumber = selectedOption.getAttribute('data-account-number');
            document.getElementById('account_number_display').value = accountNumber ? accountNumber : '';
        });
    </script>
</body>
</html>
<?php
// إغلاق اتصال قاعدة البيانات
if (isset($con) && $con) {
    $con->close();
}
?>