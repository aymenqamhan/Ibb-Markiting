<?php
session_start();
include('../../include/connect_DB.php'); // تأكد من المسار الصحيح
include('./wallet_functions.php');     // تأكد من المسار الصحيح

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
    $bootstrap_alert_type = ''; // تهيئة المتغير
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


$users = [];
$transactions = [];
$selected_user_id = $_POST['customer_user_id'] ?? '';

// يجب أن تكون جميع عمليات قاعدة البيانات داخل هذا الشرط
if ($con) {
    // جلب قائمة جميع المستخدمين
    $stmt_users = $con->prepare("SELECT id, name FROM user_tb ORDER BY name");
    if ($stmt_users === false) { // إضافة التحقق من فشل التحضير
        $_SESSION['message'] = ['type' => 'error', 'text' => "خطأ في تحضير جلب قائمة المستخدمين: " . $con->error];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $stmt_users->execute();
    $result_users = $stmt_users->get_result();
    while ($row = $result_users->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt_users->close();

    // إذا تم تحديد عميل، جلب عملياته
    if (!empty($selected_user_id)) {
        // تأكد أن getWalletTransactions موجودة في wallet_functions.php
        $transactions = getWalletTransactions($selected_user_id, $con);
        
        // رسالة "لا توجد معاملات" يجب أن تظهر فقط بعد أن يقوم المستخدم بإرسال النموذج
        // ويتم فحص ذلك بشكل منفصل لكي لا تظهر الرسالة عند تحميل الصفحة لأول مرة.
        if (empty($transactions) && isset($_POST['customer_user_id'])) { // تحقق من أن المستخدم قام بتقديم النموذج
            $_SESSION['message'] = ['type' => 'info', 'text' => 'لا توجد معاملات لهذا المستخدم.'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
} else {
    $_SESSION['message'] = ['type' => 'error', 'text' => "خطأ في الاتصال بقاعدة البيانات."];
    header("Location: " . $_SERVER['PHP_SELF']); // أعد التوجيه حتى لو كان الخطأ في الاتصال
    exit();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض عمليات المحفظة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../Style.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 30px;
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            padding: 30px;
            margin-bottom: 30px;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .report-table th, .report-table td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: right;
        }
        .report-table th {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .no-transactions-message {
            margin-top: 20px;
            padding: 15px;
            background-color: #fff3cd;
            border-color: #ffc107;
            color: #856404;
            border-radius: 0.25rem;
            text-align: center;
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
    <div class="container">
        <div class="card mx-auto" style="max-width: 900px;">
            <h1 class="card-title text-center mb-4 text-primary">
                <i class="bi bi-wallet2 me-2"></i> عرض عمليات المحفظة
            </h1>
            <p class="text-center text-muted mb-4">
                اختر مستخدمًا لعرض سجل معاملاته المالية.
            </p>

            <?php echo $message; ?>

            <div class="mb-4">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="customer_user_id" class="form-label fw-bold">اختر المستخدم:</label>
                        <select id="customer_user_id" name="customer_user_id" class="form-select" required>
                            <option value="">-- اختر مستخدمًا --</option>
                            <?php if (empty($users)): ?>
                                <option value="" disabled>لا يوجد مستخدمون حالياً</option>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['id']); ?>" 
                                            <?php echo ($user['id'] == $selected_user_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-eye-fill me-2"></i> عرض العمليات
                        </button>
                    </div>
                </form>
            </div>

            <?php if (!empty($transactions)): ?>
                <div class="transactions-table">
                    <h2 class="text-center mb-3">سجل العمليات للمستخدم المحدد</h2>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover report-table">
                            <thead>
                                <tr>
                                    <th>معرف المعاملة</th>
                                    <th>معرف المحفظة</th>
                                    <th>معرف المستخدم</th>
                                    <th>معرف الطلب</th>
                                    <th>المبلغ</th>
                                    <th>النوع</th>
                                    <th>الوصف</th>
                                    <th>تاريخ الإنشاء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($transaction['id']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['wallet_id']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['user_id']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['order_id'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(number_format($transaction['amount'], 2)); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['type']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif (isset($_POST['customer_user_id'])): ?>
                <div class="no-transactions-message">
                    <p>لا توجد معاملات مسجلة لهذا المستخدم.</p>
                </div>
            <?php endif; ?>

            <div class="mt-4 text-center">
                <button class="btn btn-secondary" onclick="window.location.href='../dashbord.php';">
                    <i class="bi bi-arrow-right-circle-fill me-2"></i> العودة للوحة التحكم
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php
// إغلاق اتصال قاعدة البيانات
if (isset($con) && $con) {
    $con->close();
}
?>