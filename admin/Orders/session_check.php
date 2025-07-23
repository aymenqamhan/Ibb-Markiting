<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'الرجاء تسجيل الدخول للمتابعة.'];
    header("Location: /NEW_IBB/login.php");
    exit();
}
// يمكنك إضافة المزيد من التحقق من الدور هنا إذا لم تكن تقوم بذلك في كل صفحة بشكل منفصل
?>