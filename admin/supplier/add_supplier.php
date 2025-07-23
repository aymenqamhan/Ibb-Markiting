<?php
session_start();
include('../connect_DB.php'); // تأكد من المسار الصحيح لملف اتصال قاعدة البيانات

// التحقق من صلاحيات المستخدم (مثال بسيط: السماح للمدير أو مدير المشتريات)
if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 5)) {
    header("Location: /login.php");
    exit();
}

$message = '';
if (isset($_SESSION['message'])) {
    $msg_type = $_SESSION['message']['type'];
    $msg_text = $_SESSION['message']['text'];
    $message = "<div class='message $msg_type'>$msg_text</div>";
    unset($_SESSION['message']);
}

if (isset($_POST['add_supplier'])) {
    $name = trim($_POST['name']);
    $contact_name = trim($_POST['contact_name']);
    $contact_phone = trim($_POST['contact_phone']);
    $contact_email = trim($_POST['contact_email']);
    $address = trim($_POST['address']);
    $notes = trim($_POST['notes']);
    $status = $_POST['status']; // 'active' or 'inactive'

    // التحقق من الحقول المطلوبة
    if (empty($name) || empty($contact_phone) || empty($contact_email)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'الرجاء ملء جميع الحقول المطلوبة (الاسم، الهاتف، البريد الإلكتروني).'];
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'صيغة البريد الإلكتروني غير صالحة.'];
    } else {
        // التحقق من عدم تكرار اسم المورد أو البريد الإلكتروني (اختياري لكن موصى به)
        $stmt_check = $con->prepare("SELECT id FROM suppliers WHERE name = ? OR contact_email = ?");
        $stmt_check->bind_param("ss", $name, $contact_email);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'المورد بالاسم أو البريد الإلكتروني هذا موجود بالفعل.'];
        } else {
            // إدراج المورد الجديد في قاعدة البيانات
            $stmt = $con->prepare("INSERT INTO suppliers (name, contact_name, contact_phone, contact_email, address, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt === false) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'خطأ في إعداد الاستعلام: ' . $con->error];
            } else {
                $stmt->bind_param("sssssss", $name, $contact_name, $contact_phone, $contact_email, $address, $notes, $status);
                if ($stmt->execute()) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'تم إضافة المورد بنجاح.'];
                    // إعادة توجيه لمنع إعادة إرسال النموذج عند التحديث
                    header("Location: list_supplier.php"); // يمكن التوجيه لصفحة العرض أو القائمة
                    exit();
                } else {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'فشل إضافة المورد: ' . $stmt->error];
                }
                $stmt->close();
            }
        }
        $stmt_check->close();
    }
    // إعادة التوجيه إلى نفس الصفحة لعرض الرسائل بعد POST
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة مورد جديد</title>
    <link rel="stylesheet" href="./style.css">
</head>
<body>
    <div class="container">
        <h1>إضافة مورد جديد</h1>

        <?php echo $message; ?>

        <div class="form-container">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">اسم المورد: <span style="color: red;">*</span></label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="contact_name">الشخص المسؤول:</label>
                    <input type="text" id="contact_name" name="contact_name">
                </div>
                <div class="form-group">
                    <label for="contact_phone">رقم الهاتف: <span style="color: red;">*</span></label>
                    <input type="tel" id="contact_phone" name="contact_phone" required>
                </div>
                <div class="form-group">
                    <label for="contact_email">البريد الإلكتروني: <span style="color: red;">*</span></label>
                    <input type="email" id="contact_email" name="contact_email" required>
                </div>
                <div class="form-group">
                    <label for="address">العنوان:</label>
                    <textarea id="address" name="address"></textarea>
                </div>
                <div class="form-group">
                    <label for="notes">ملاحظات:</label>
                    <textarea id="notes" name="notes"></textarea>
                </div>
                <div class="form-group">
                    <label for="status">الحالة:</label>
                    <select id="status" name="status">
                        <option value="active">نشط</option>
                        <option value="inactive">غير نشط</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" name="add_supplier" class="submit-button">إضافة مورد</button>
                    <button type="button" class="cancel-button" onclick="window.location.href='list_supplier.php';">إلغاء</button>
                </div>
            </form>
            <button class="back" onclick="window.location.href='../dashbord.php';">العودة لقائمة </button>
        </div>
    </div>
</body>
</html>