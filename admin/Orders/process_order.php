<?php
session_start();
include('../connect_DB.php'); // تأكد من المسار الصحيح لقاعدة البيانات
include('./session_check.php'); // للتحقق من تسجيل الدخول والصلاحية

// التحقق من صلاحية المستخدم
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 5])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'ليس لديك الصلاحية لتنفيذ هذا الإجراء.'];
    header("Location: /NEW_IBB/login.php");
    exit();
}

if (!isset($_GET['order_id']) || !isset($_GET['action'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'طلب غير صالح لتحديث الحالة.'];
    header("Location: list_orders.php");
    exit();
}

$order_id = intval($_GET['order_id']);
$action = $_GET['action'];
$new_status = '';
$redirect_to = 'list_orders.php'; // الافتراضي هو العودة إلى قائمة كل الطلبات

switch ($action) {
    case 'approve':
        $new_status = 'processing';
        $redirect_to = 'pending_orders.php';
        break;
    case 'shipped':
        $new_status = 'shipped';
        $redirect_to = 'approved_orders.php';
        break;
    case 'delivered':
        $new_status = 'delivered';
        $redirect_to = 'approved_orders.php';
        break;
    case 'cancel':
        $new_status = 'cancelled';
        // إضافة منطق لإعادة المنتجات للمخزون إذا ألغي الطلب ولم يتم شحنه بعد
        // يمكن معالجة هذا بشكل أكثر تفصيلاً هنا
        $redirect_to = 'canceled_orders.php';
        break;
    default:
        $_SESSION['message'] = ['type' => 'error', 'text' => 'إجراء غير معروف لتحديث الطلب.'];
        header("Location: list_orders.php");
        exit();
}

$update_query = "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?";
$stmt = $con->prepare($update_query);

if ($stmt === false) {
    error_log("Failed to prepare order status update in process_order.php: " . $con->error);
    $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ في قاعدة البيانات أثناء تحديث حالة الطلب.'];
} else {
    $stmt->bind_param("si", $new_status, $order_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'تم تحديث حالة الطلب بنجاح إلى "' . getStatusArabic($new_status) . '".'];
        // إذا كان الإجراء إلغاء، قد تحتاج إلى منطق إضافي هنا
        if ($action == 'cancel') {
             // يمكنك هنا استعادة كميات المخزون للعناصر الملغاة إذا لم تكن قد شحنت
             // مثال (يتطلب جلب عناصر الطلب وتحديث المخزون):
             // $order_items_query = "SELECT product_id, quantity FROM order_items WHERE order_id = ?";
             // ... تنفيذ الاستعلام وتحديث جدول inventory ...
        }

    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'فشل تحديث حالة الطلب: ' . $stmt->error];
    }
    $stmt->close();
}

$con->close();
header("Location: $redirect_to");
exit();

// دوال مساعدة لترجمة الحالات (يمكن وضعها في ملف include مشترك)
function getStatusArabic($status) {
    $map = [
        'pending' => 'قيد الانتظار',
        'processing' => 'قيد المعالجة',
        'shipped' => 'تم الشحن',
        'delivered' => 'تم التوصيل',
        'cancelled' => 'ملغى'
    ];
    return $map[$status] ?? $status;
}
?>