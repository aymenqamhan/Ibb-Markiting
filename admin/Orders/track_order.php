<?php
session_start();
error_reporting(E_ALL); // عرض جميع الأخطاء لأغراض التطوير
ini_set('display_errors', 1); // عرض الأخطاء مباشرة على الصفحة

// المسارات التالية تعتمد على مكان مجلد "include"
// إذا كان مجلد "include" موجوداً في "C:\xampp\htdocs\new_ibb\include\"
// فإن المسار النسبي من "admin/Orders/" سيكون:
include('../../include/connect_DB.php'); //
include('./session_check.php'); // // session_check.php موجود في نفس المجلد
include('./functions.php'); // // functions.php موجود في نفس المجلد

// التحقق من تسجيل الدخول والصلاحية
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 5])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'ليس لديك الصلاحية للوصول إلى هذه الصفحة.'];
    header("Location: /NEW_IBB/login.php");
    exit();
}

$order_id_to_track = isset($_GET['order_id']) ? intval($_GET['order_id']) : (isset($_POST['order_id']) ? intval($_POST['order_id']) : null);
$message = '';

// عرض رسائل النظام (نجاح/خطأ)
if (isset($_SESSION['message'])) {
    $msg_type = $_SESSION['message']['type'];
    $msg_text = $_SESSION['message']['text'];
    $bootstrap_alert_type = ($msg_type == 'success') ? 'alert-success' : 'alert-danger';
    $message = "<div class='alert $bootstrap_alert_type alert-dismissible fade show' role='alert'>
                    $msg_text
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
    unset($_SESSION['message']);
}

// جلب معلومات التتبع من جدول الطلبات
$order_tracking = null;
if ($order_id_to_track) {
    // تم إضافة عمود tracking_number و carrier إلى جدول orders
    // بناءً على مشاكل سابقة
    $query = "SELECT id, status, tracking_number, carrier FROM orders WHERE id = ?";
    $stmt = $con->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $order_id_to_track);
        $stmt->execute();
        $result = $stmt->get_result();
        $order_tracking = $result->fetch_assoc();
        $stmt->close();
    } else {
        // رسالة خطأ إذا فشل التحضير (غالباً بسبب خطأ في الاستعلام أو الاتصال)
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                        خطأ في تهيئة الاستعلام: " . $con->error . "
                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                    </div>";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الطلبات - تتبع الشحن</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/NEW_IBB/Style/admin_styles.css"> 
    <style>
        .container-fluid {
            padding: 30px;
        }
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4 text-primary"><i class="bi bi-box-seam me-2"></i> تتبع الشحن</h2>
            <?php echo $message; ?>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    البحث عن طلب لتتبعه
                </div>
                <div class="card-body">
                    <form action="track_order.php" method="GET" class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <label for="order_id_input" class="form-label">أدخل رقم الطلب:</label>
                            <input type="number" class="form-control" id="order_id_input" name="order_id" placeholder="مثال: 123" value="<?php echo htmlspecialchars($order_id_to_track ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-auto"><i class="bi bi-search me-2"></i> بحث عن الطلب</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($order_id_to_track && $order_tracking): ?>
                <div class="card mt-4">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-truck me-2"></i> معلومات تتبع الطلب #<?php echo htmlspecialchars($order_id_to_track); ?>
                    </div>
                    <div class="card-body">
                        <p><strong>الحالة الحالية:</strong> <span class="badge bg-info"><?php echo htmlspecialchars(getStatusArabic($order_tracking['status'])); ?></span></p>
                        <p><strong>رقم التتبع:</strong> <?php echo htmlspecialchars($order_tracking['tracking_number'] ?? 'غير متوفر'); ?></p>
                        <p><strong>شركة الشحن:</strong> <?php echo htmlspecialchars($order_tracking['carrier'] ?? 'غير متوفر'); ?></p>
                        <?php if ($order_tracking['tracking_number']): ?>
                            <p class="mt-3">
                                <a href="https://www.google.com/search?q=track+<?php echo urlencode($order_tracking['tracking_number']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> البحث عن التتبع في Google
                                </a>
                                <?php if ($order_tracking['carrier'] == 'Aramex'): // مثال لشركة شحن معينة، يمكنك إضافة المزيد من الروابط هنا ?>
                                    <a href="https://www.aramex.com/track/shipments?ShipmentNumber=<?php echo urlencode($order_tracking['tracking_number']); ?>" target="_blank" class="btn btn-outline-dark btn-sm ms-2">
                                        <i class="bi bi-link-45deg me-1"></i> تتبع مع أرامكس
                                    </a>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($order_id_to_track && !$order_tracking): ?>
                <div class="alert alert-warning text-center mt-4">
                    <i class="bi bi-exclamation-triangle me-2"></i> لم يتم العثور على طلب بهذا الرقم.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php
// تأكد من إغلاق الاتصال بقاعدة البيانات في نهاية الصفحة
if (isset($con) && $con instanceof mysqli) {
    $con->close();
}
?>