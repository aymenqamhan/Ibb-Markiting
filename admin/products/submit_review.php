<?php
session_start();

// تمكين عرض الأخطاء لغرض التصحيح (يمكن إزالتها بعد الانتهاء)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// **التصحيح الأول: تعديل مسار ملف الاتصال بقاعدة البيانات**
// المسار الصحيح للوصول إلى connect_DB.php من داخل admin/products/
include('../connect_DB.php');

// التحقق من أن الطلب هو POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['message'] = 'طريقة طلب غير صالحة.';
    $_SESSION['message_type'] = 'danger';
    // استخدام ?? لتوفير قيمة افتراضية آمنة إذا كان HTTP_REFERER غير موجود
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/NEW_IBB/products.php'));
    exit();
}

// التحقق من تسجيل دخول المستخدم
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = 'يجب تسجيل الدخول لإرسال تقييم.';
    $_SESSION['message_type'] = 'danger';
    // تم تصحيح product_view.php هنا
    header('Location: /NEW_IBB/login.php?redirect=' . urlencode($_SERVER['HTTP_REFERER'] ?? '/NEW_IBB/admin/products/product_view.php'));
    exit();
}

// **التصحيح الثاني: التحقق من اتصال قاعدة البيانات قبل استخدام $con**
// هذا يمنع خطأ "Call to a member function prepare() on null"
if (!isset($con) || $con === null) {
    $_SESSION['message'] = 'خطأ حاسم: لم يتم الاتصال بقاعدة البيانات. يرجى مراجعة ملف connect_DB.php.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/NEW_IBB/admin/products/product_view.php'));
    exit();
}

// استقبال البيانات
$product_id = $_POST['product_id'] ?? null;
$user_id = $_SESSION['user_id']; // جلب user_id من الجلسة
$rating = $_POST['rating'] ?? null;
$review_title = trim($_POST['review_title'] ?? '');
$review_text = trim($_POST['review_text'] ?? '');

// التحقق من صحة البيانات
if (empty($product_id) || !filter_var($product_id, FILTER_VALIDATE_INT)) {
    $_SESSION['message'] = 'معرف المنتج غير صالح.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/NEW_IBB/admin/products/product_view.php'));
    exit();
}

if (empty($rating) || !filter_var($rating, FILTER_VALIDATE_INT) || $rating < 1 || $rating > 5) {
    $_SESSION['message'] = 'يرجى تقديم تقييم صالح (بين 1 و 5).';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/NEW_IBB/admin/products/product_view.php'));
    exit();
}

if (empty($review_text)) {
    $_SESSION['message'] = 'نص المراجعة مطلوب.';
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/NEW_IBB/admin/products/product_view.php'));
    exit();
}

// التحقق مما إذا كان المستخدم قد قام بالفعل بتقييم هذا المنتج (اختياري، لمنع التقييمات المتعددة)
$stmt_check = $con->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ?");
if ($stmt_check === false) {
    $_SESSION['message'] = 'خطأ في تجهيز استعلام التحقق من التقييمات: ' . $con->error;
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/NEW_IBB/admin/products/product_view.php'));
    exit();
}
$stmt_check->bind_param("ii", $product_id, $user_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
if ($result_check->num_rows > 0) {
    $_SESSION['message'] = 'لقد قمت بالفعل بتقييم هذا المنتج.';
    $_SESSION['message_type'] = 'warning';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/NEW_IBB/admin/products/product_view.php'));
    exit();
}
$stmt_check->close();


// إعداد الاستعلام لإدراج التقييم
$stmt = $con->prepare("INSERT INTO product_reviews (product_id, user_id, rating, review_title, review_text, status) VALUES (?, ?, ?, ?, ?, 'approved')");
if ($stmt === false) {
    $_SESSION['message'] = 'خطأ في تجهيز استعلام التقييم: ' . $con->error;
    $_SESSION['message_type'] = 'danger';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/NEW_IBB/admin/products/product_view.php'));
    exit();
}

$stmt->bind_param("iisss", $product_id, $user_id, $rating, $review_title, $review_text);

if ($stmt->execute()) {
    $_SESSION['message'] = 'تم إرسال تقييمك بنجاح! سيظهر بعد مراجعته.';
    $_SESSION['message_type'] = 'success';

    // **تحديث متوسط التقييم وعدد التقييمات في جدول المنتجات**
    // جلب كل التقييمات المعتمدة للمنتج لحساب المتوسط الجديد
    $update_stmt = $con->prepare("
        UPDATE products
        SET 
            average_rating = (SELECT AVG(rating) FROM product_reviews WHERE product_id = ? AND status = 'approved'),
            total_reviews_count = (SELECT COUNT(id) FROM product_reviews WHERE product_id = ? AND status = 'approved')
        WHERE id = ?
    ");
    if ($update_stmt === false) {
        error_log("Error preparing product rating update: " . $con->error);
    } else {
        $update_stmt->bind_param("iii", $product_id, $product_id, $product_id);
        $update_stmt->execute();
        $update_stmt->close();
    }

} else {
    $_SESSION['message'] = 'حدث خطأ أثناء حفظ التقييم: ' . $stmt->error;
    $_SESSION['message_type'] = 'danger';
}

$stmt->close();
$con->close();

// إعادة التوجيه إلى صفحة المنتج
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/NEW_IBB/admin/products/product_view.php'));
exit();

?>