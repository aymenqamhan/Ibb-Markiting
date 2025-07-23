<?php
session_start(); // يجب أن تكون موجودة للوصول إلى $_SESSION
// ****** تأكد من المسار الصحيح لملف اتصال قاعدة البيانات ******
// إذا كان connect_DB.php في new_ibb/connect_DB.php
include('../connect_DB.php');

// التحقق من صلاحيات المستخدم (مهم جداً لملفات معالجة البيانات)
if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 5)) {
    // إعادة توجيه إلى صفحة تسجيل الدخول أو صفحة خطأ، أو ببساطة عدم السماح بالوصول
    header("Location: /new_ibb/login.php"); // تأكد من مسار صفحة تسجيل الدخول لديك
    exit();
}

// معالجة طلب الحذف
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $supplier_id = $_GET['delete_id'];

    // 1. التحقق مما إذا كان المورد مرتبطًا بأي أوامر شراء موجودة
    $stmt_check_po = $con->prepare("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = ?");
    if ($stmt_check_po === false) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'خطأ في إعداد استعلام التحقق من أوامر الشراء: ' . $con->error];
    } else {
        $stmt_check_po->bind_param("i", $supplier_id);
        $stmt_check_po->execute();
        $stmt_check_po->bind_result($count_po);
        $stmt_check_po->fetch();
        $stmt_check_po->close();

        // 2. التحقق مما إذا كان المورد مرتبطًا بأي منتجات موجودة (الجديد)
        $stmt_check_products = $con->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ?"); // تأكد من اسم جدول المنتجات وعمود الـ foreign key
        if ($stmt_check_products === false) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'خطأ في إعداد استعلام التحقق من المنتجات: ' . $con->error];
        } else {
            $stmt_check_products->bind_param("i", $supplier_id);
            $stmt_check_products->execute();
            $stmt_check_products->bind_result($count_products);
            $stmt_check_products->fetch();
            $stmt_check_products->close();

            // اتخاذ القرار بناءً على الارتباطات
            if ($count_po > 0) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'لا يمكن حذف هذا المورد لأنه مرتبط بأوامر شراء موجودة. يرجى إلغاء ربط أوامر الشراء أولاً أو تعيين حالته إلى "غير نشط".'];
            } elseif ($count_products > 0) { // الشرط الجديد للمنتجات
                $_SESSION['message'] = ['type' => 'error', 'text' => 'لا يمكن حذف هذا المورد لأنه مرتبط بمنتجات موجودة. يرجى حذف المنتجات المرتبطة أولاً أو تعيين حالة المورد إلى "غير نشط".'];
            } else {
                // إذا لم يكن هناك ارتباطات (لا أوامر شراء ولا منتجات)، قم بالحذف
                $stmt_delete = $con->prepare("DELETE FROM suppliers WHERE id = ?");
                if ($stmt_delete === false) {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'خطأ في إعداد استعلام الحذف: ' . $con->error];
                } else {
                    $stmt_delete->bind_param("i", $supplier_id);
                    if ($stmt_delete->execute()) {
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'تم حذف المورد بنجاح.'];
                    } else {
                        $_SESSION['message'] = ['type' => 'error', 'text' => 'فشل حذف المورد: ' . $stmt_delete->error];
                    }
                    $stmt_delete->close();
                }
            }
        }
    }
    
    // إعادة توجيه لتحديث القائمة بعد المعالجة، سواء نجح الحذف أو فشل
    // ****** تأكد من المسار الصحيح لـ list_supplier.php واسم المجلد NEW_IBB ******
    header("Location: /new_ibb/admin/supplier/list_supplier.php");
    exit();

} else {
    // إذا لم يتم توفير delete_id أو كان غير صالح
    $_SESSION['message'] = ['type' => 'error', 'text' => 'معرف المورد غير صالح لعملية الحذف.'];
    header("Location: /new_ibb/admin/supplier/list_supplier.php");
    exit();
}
?>