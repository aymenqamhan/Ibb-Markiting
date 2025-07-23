<?php
session_start();
// Corrected relative path to connect_DB.php
include('../../include/connect_DB.php'); 

// التحقق من صلاحيات المستخدم مرة أخرى (لمزيد من الأمان)
if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] ?? 0) !== 1) {
    $_SESSION['error_message'] = "ليس لديك صلاحية لتعديل الأسعار.";
    header("Location: /NEW_IBB/login.php"); 
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $new_price = filter_input(INPUT_POST, 'new_price', FILTER_VALIDATE_FLOAT);

    // التحقق من صلاحية البيانات
    if ($product_id === false || $product_id <= 0) {
        $_SESSION['error_message'] = "معرف المنتج غير صالح.";
        header("Location: edit_product_prices.php");
        exit();
    }
    if ($new_price === false || $new_price < 0) {
        $_SESSION['error_message'] = "السعر الجديد غير صالح.";
        header("Location: edit_product_prices.php");
        exit();
    }

    // التحقق مما إذا كان الاتصال بقاعدة البيانات تم بنجاح
    if (isset($con) && $con) {
        // تحديث السعر في قاعدة البيانات
        $sql_update_price = "UPDATE products SET price = ? WHERE id = ?";
        $stmt_update_price = $con->prepare($sql_update_price);

        if ($stmt_update_price) {
            $stmt_update_price->bind_param("di", $new_price, $product_id); // 'd' لـ float, 'i' لـ int
            if ($stmt_update_price->execute()) {
                $_SESSION['success_message'] = "تم تحديث سعر المنتج بنجاح.";
            } else {
                $_SESSION['error_message'] = "خطأ في تحديث السعر: " . $stmt_update_price->error;
            }
            $stmt_update_price->close();
        } else {
            $_SESSION['error_message'] = "خطأ في تحضير استعلام تحديث السعر: " . $con->error;
        }
    } else {
        $_SESSION['error_message'] = "خطأ في الاتصال بقاعدة البيانات. الرجاء المحاولة لاحقًا.";
    }

} else {
    $_SESSION['error_message'] = "طلب غير صالح.";
}

// إعادة توجيه المستخدم إلى صفحة تعديل الأسعار
header("Location: edit_product_prices.php");
exit();

// Only close connection if it was successfully opened
if (isset($con) && $con) { 
    $con->close();
}
?>