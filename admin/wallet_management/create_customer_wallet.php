<?php
// بدء الجلسة والاتصال بقاعدة البيانات
session_start();
include('../../include/connect_DB.php');
include('./wallet_functions.php'); // تضمين دوال المحفظة ليتم التعرف عليها مبكرًا

// التحقق من صلاحيات المستخدم: يجب أن يكون مسؤولاً (role_id 1) أو موظفًا مخولًا (role_id 5)
if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 5)) {
    $_SESSION['error_message'] = "ليس لديك صلاحية الوصول إلى هذه الصفحة.";
    header("Location: /NEW_IBB/login.php");
    exit();
}

// عرض رسائل النظام (نجاح/خطأ)
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

// جلب العملاء الذين لا يملكون محافظ حالياً
$users_without_wallets = [];
if ($con) {
    // استعلام لجلب المستخدمين (دور 2 للعملاء) الذين لا يملكون محفظة بعد
    $stmt_users = $con->prepare("
        SELECT ut.id, ut.name
        FROM user_tb ut
        LEFT JOIN wallets w ON ut.id = w.user_id
        WHERE w.id IS NULL ORDER BY ut.name
    ");
    if ($stmt_users) {
        $stmt_users->execute();
        $result_users = $stmt_users->get_result();
        while ($row = $result_users->fetch_assoc()) {
            $users_without_wallets[] = $row;
        }
        $stmt_users->close();
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => "خطأ في جلب قائمة العملاء: " . $con->error];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
} else {
    $_SESSION['message'] = ['type' => 'error', 'text' => "خطأ في الاتصال بقاعدة البيانات."];
}


// معالجة طلب إنشاء المحفظة عند إرسال النموذج
if (isset($_POST['create_wallet']) && $con) {
    $customer_user_id = filter_var($_POST['customer_user_id'], FILTER_VALIDATE_INT);
    $wallet_password = $_POST['wallet_password']; // كلمة مرور المحفظة المدخلة

    // التحقق من صحة المدخلات
    if (!$customer_user_id) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'الرجاء اختيار عميل صالح.'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    if (empty($wallet_password)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'الرجاء إدخال كلمة مرور للمحفظة.'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // التحقق لمنع إنشاء محفظة مكررة لنفس العميل
    $stmt_check = $con->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $stmt_check->bind_param("i", $customer_user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows > 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'هذا العميل لديه محفظة بالفعل.'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $stmt_check->close();

    // استدعاء دالة إنشاء المحفظة
    $wallet_creation_result = createWallet($customer_user_id, $con, $wallet_password);

    // عرض رسالة النجاح أو الخطأ
    if ($wallet_creation_result['success']) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'تم إنشاء المحفظة للعميل بنجاح. رقم الحساب هو: <strong>' . htmlspecialchars($wallet_creation_result['account_number']) . '</strong>'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'فشل إنشاء المحفظة للعميل: ' . htmlspecialchars($wallet_creation_result['error'])];
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// توليد رقم حساب للعرض الأولي في الحقل (للقراءة فقط)
$display_account_number = '';
if ($con) {
    try {
        $display_account_number = generateUniqueAccountNumber($con);
    } catch (Exception $e) {
        $display_account_number = 'خطأ'; // في حال حدوث مشكلة في الاتصال
    }
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء محفظة لعميل</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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
            background-color: #007bff; /* أزرق برايمري */
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .btn-secondary {
            background-color: #6c757d; /* رمادي ثانوي */
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #5a6268;
        }
        /* لتنسيق رسالة النجاح والخطأ بشكل أفضل */
        .alert-success strong {
            color: #0f5132; /* لون أخضر داكن للنص الهام */
        }
        .alert-danger strong {
            color: #842029; /* لون أحمر داكن للنص الهام */
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card shadow-lg p-4 mx-auto" style="max-width: 600px;">
            <h1 class="card-title text-center mb-4 text-primary">
                <i class="bi bi-wallet-fill me-2"></i> إنشاء محفظة جديدة
            </h1>
            <p class="text-center text-muted mb-4">
                يرجى ملء المعلومات المطلوبة لإنشاء محفظة إلكترونية لعميل.
            </p>

            <?php echo $message; ?>

            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="customer_user_id" class="form-label fw-bold">العميل:</label>
                        <select id="customer_user_id" name="customer_user_id" class="form-select" required>
                            <option value="">-- اختر عميلًا --</option>
                            <?php if (empty($users_without_wallets)): ?>
                                <option value="" disabled>لا يوجد عملاء بدون محافظ حالياً</option>
                            <?php else: ?>
                                <?php foreach ($users_without_wallets as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="form-text text-muted">اختر العميل الذي ترغب في إنشاء محفظة له.</div>
                    </div>

                    <div class="mb-3">
                        <label for="account_number" class="form-label fw-bold">رقم الحساب:</label>
                        <input type="text" id="account_number" name="account_number_display" class="form-control"
                               value="<?php echo htmlspecialchars($display_account_number); ?>" readonly>
                        <div class="form-text text-muted">سيتم توليد رقم حساب فريد تلقائياً.</div>
                    </div>

                    <div class="mb-3">
                        <label for="wallet_password" class="form-label fw-bold">كلمة مرور المحفظة:</label>
                        <input type="password" id="wallet_password" name="wallet_password" class="form-control" required minlength="6">
                        <div class="form-text text-muted">الرجاء إدخال كلمة مرور قوية للمحفظة (على الأقل 6 أحرف).</div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" name="create_wallet" class="btn btn-primary btn-lg">
                            <i class="bi bi-plus-circle-fill me-2"></i> إنشاء المحفظة
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</body>
</html>
<?php
// إغلاق اتصال قاعدة البيانات
if (isset($con) && $con) {
    $con->close();
}
?>