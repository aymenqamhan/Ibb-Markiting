<?php
// تضمين ملف الاتصال بقاعدة البيانات
include('../connect_DB.php'); // المسار الصحيح حسب هيكلة مجلداتك

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $inventory_id = $_GET['id'];

    // استخدام Prepared Statement للحذف الآمن
    $stmt = $con->prepare("DELETE FROM inventory WHERE id = ?");
    $stmt->bind_param("i", $inventory_id);

    if ($stmt->execute()) {
        // تم الحذف بنجاح
        header("Location: list_inventory.php?delete_success=1");
        exit();
    } else {
        // حدث خطأ أثناء الحذف
        // يمكنك توجيه المستخدم إلى صفحة خطأ أو عرض رسالة
        header("Location: list_inventory.php?delete_error=1&msg=" . urlencode($stmt->error));
        exit();
    }
    $stmt->close();
} else {
    // إذا لم يتم تمرير ID صحيح
    header("Location: list_inventory.php?delete_error=1&msg=" . urlencode("ID غير صالح أو مفقود."));
    exit();
}

$con->close();
?>