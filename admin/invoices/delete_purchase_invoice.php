<?php
// delete_purchase_invoice.php
session_start();
include('../connect_DB.php'); // تأكد من المسار الصحيح لملف اتصال قاعدة البيانات

// تحقق من أن الطلب من نوع POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "طلب غير صالح للحذف.";
    header("Location: list_purchase_invoices.php");
    exit();
}

$invoice_id = $_POST['invoice_id'] ?? 0;
// استبدل هذا بمعرف المستخدم الفعلي المسجل دخول حاليا (من $_SESSION)
// يجب أن يكون هذا معرف مستخدم صحيح لتسجيل من قام بالحذف
$current_user_id = $_SESSION['user_id'] ?? null; // استخدم null إذا لم يكن معرف المستخدم موجوداً

if ($invoice_id <= 0) {
    $_SESSION['error_message'] = "لم يتم تحديد فاتورة صالحة للحذف.";
    header("Location: list_purchase_invoices.php");
    exit();
}

// ابدأ المعاملة (Transaction) لضمان إما نجاح العمليتين معاً أو الفشل معاً
$con->begin_transaction();

try {
    // تحديث الفاتورة لجعلها محذوفة ناعماً
    $sql_soft_delete = "UPDATE purchase_invoices SET
                        is_deleted = 1,
                        deleted_at = NOW(),
                        deleted_by_user_id = ?
                        WHERE id = ?";

    $stmt_soft_delete = $con->prepare($sql_soft_delete);
    if (!$stmt_soft_delete) {
        throw new Exception("خطأ في تحضير استعلام الحذف الناعم: " . $con->error);
    }
    // تأكد من أن current_user_id صحيح وأن invoice_id صحيح
    // لاحظ: إذا كان current_user_id يمكن أن يكون NULL في قاعدة البيانات، يجب أن يكون نوعه 'i' ويتم تمرير NULL
    $stmt_soft_delete->bind_param("ii", $current_user_id, $invoice_id);
    $stmt_soft_delete->execute();

    // تحقق مما إذا تم تحديث أي صف
    if ($stmt_soft_delete->affected_rows > 0) {
        $con->commit(); // الالتزام بالتغييرات
        $_SESSION['success_message'] = "تم نقل الفاتورة إلى سلة المحذوفات بنجاح.";
    } else {
        $con->rollback(); // التراجع إذا لم يتم العثور على الفاتورة للتحديث (لم تكن موجودة مثلاً)
        $_SESSION['error_message'] = "لم يتم العثور على الفاتورة لنقلها إلى سلة المحذوفات، أو حدث خطأ.";
    }
    $stmt_soft_delete->close();

} catch (Exception $e) {
    $con->rollback(); // التراجع عن جميع التغييرات في حالة حدوث خطأ
    $_SESSION['error_message'] = "خطأ في عملية نقل الفاتورة إلى سلة المحذوفات: " . $e->getMessage();
}

$con->close();
header("Location: list_purchase_invoices.php");
exit();
?>