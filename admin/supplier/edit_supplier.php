<?php
session_start();
include('../connect_DB.php'); // تأكد من المسار الصحيح لملف اتصال قاعدة البيانات

// التحقق من صلاحيات المستخدم
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

$supplier_id = $_GET['id'] ?? null;
$supplier_data = null;

// جلب بيانات المورد الحالي
if (!$supplier_id || !is_numeric($supplier_id)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'معرف المورد غير صالح.'];
    header("Location: list_supplier.php");
    exit();
}

// تم تعديل الاستعلام لجلب contact_name بدلاً من contact_person، وإضافة address و notes
$stmt_get = $con->prepare("SELECT id, name, contact_name, contact_phone, contact_email, address, notes, status FROM suppliers WHERE id = ?");
$stmt_get->bind_param("i", $supplier_id);
$stmt_get->execute();
$result_get = $stmt_get->get_result();
$supplier_data = $result_get->fetch_assoc();
$stmt_get->close();

if (!$supplier_data) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'المورد غير موجود.'];
    header("Location: list_supplier.php");
    exit();
}

// معالجة إرسال النموذج للتعديل
if (isset($_POST['update_supplier'])) {
    $name = trim($_POST['name']);
    $contact_name = trim($_POST['contact_name']); // تم تعديل contact_person إلى contact_name
    $contact_phone = trim($_POST['contact_phone']);
    $contact_email = trim($_POST['contact_email']);
    $address = trim($_POST['address']);
    $notes = trim($_POST['notes']);
    $status = $_POST['status'];

    // التحقق من الحقول المطلوبة
    if (empty($name) || empty($contact_phone) || empty($contact_email)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'الرجاء ملء جميع الحقول المطلوبة (الاسم، الهاتف، البريد الإلكتروني).'];
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'صيغة البريد الإلكتروني غير صالحة.'];
    } else {
        // التحقق من عدم تكرار اسم المورد أو البريد الإلكتروني (باستثناء المورد الحالي)
        $stmt_check_duplicate = $con->prepare("SELECT id FROM suppliers WHERE (name = ? OR contact_email = ?) AND id != ?");
        $stmt_check_duplicate->bind_param("ssi", $name, $contact_email, $supplier_id);
        $stmt_check_duplicate->execute();
        $stmt_check_duplicate->store_result();
        if ($stmt_check_duplicate->num_rows > 0) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'المورد بالاسم أو البريد الإلكتروني هذا موجود بالفعل لمورد آخر.'];
        } else {
            // تحديث بيانات المورد - تم تعديل contact_person إلى contact_name
            $stmt_update = $con->prepare("UPDATE suppliers SET name = ?, contact_name = ?, contact_phone = ?, contact_email = ?, address = ?, notes = ?, status = ? WHERE id = ?");
            if ($stmt_update === false) {
                 $_SESSION['message'] = ['type' => 'error', 'text' => 'خطأ في إعداد استعلام التحديث: ' . $con->error];
            } else {
                // ربط المتغيرات - تم تعديل contact_person إلى contact_name
                $stmt_update->bind_param("sssssssi", $name, $contact_name, $contact_phone, $contact_email, $address, $notes, $status, $supplier_id);
                if ($stmt_update->execute()) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'تم تحديث بيانات المورد بنجاح.'];
                    // تحديث بيانات المورد المعروضة في النموذج بعد التحديث الناجح
                    $supplier_data['name'] = $name;
                    $supplier_data['contact_name'] = $contact_name; // تم تعديل contact_person إلى contact_name
                    $supplier_data['contact_phone'] = $contact_phone;
                    $supplier_data['contact_email'] = $contact_email;
                    $supplier_data['address'] = $address;
                    $supplier_data['notes'] = $notes;
                    $supplier_data['status'] = $status;
                } else {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'فشل تحديث بيانات المورد: ' . $stmt_update->error];
                }
                $stmt_update->close();
            }
        }
        $stmt_check_duplicate->close();
    }
    // إعادة التوجيه إلى نفس الصفحة لعرض الرسائل بعد POST
    header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $supplier_id);
    header("Location: list_supplier.php"); // إعادة التوجيه إلى قائمة الموردين");
    
    exit();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل مورد: <?php echo htmlspecialchars($supplier_data['name']); ?></title>
    <link rel="stylesheet" href="./style.css"> 
</head>
<body>
    <div class="container">
        <h1>تعديل مورد: <?php echo htmlspecialchars($supplier_data['name']); ?></h1>

        <?php echo $message; ?>

        <div class="form-container">
            <form method="POST" action="">
                <input type="hidden" name="supplier_id" value="<?php echo htmlspecialchars($supplier_data['id']); ?>">
                <div class="form-group">
                    <label for="name">اسم المورد: <span style="color: red;">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($supplier_data['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="contact_name">الشخص المسؤول:</label> <input type="text" id="contact_name" name="contact_name" value="<?php echo htmlspecialchars($supplier_data['contact_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="contact_phone">رقم الهاتف: <span style="color: red;">*</span></label>
                    <input type="tel" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($supplier_data['contact_phone']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="contact_email">البريد الإلكتروني: <span style="color: red;">*</span></label>
                    <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($supplier_data['contact_email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="address">العنوان:</label>
                    <textarea id="address" name="address"><?php echo htmlspecialchars($supplier_data['address'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="notes">ملاحظات:</label>
                    <textarea id="notes" name="notes"><?php echo htmlspecialchars($supplier_data['notes'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="status">الحالة:</label>
                    <select id="status" name="status">
                        <option value="active" <?php echo ($supplier_data['status'] == 'active') ? 'selected' : ''; ?>>نشط</option>
                        <option value="inactive" <?php echo ($supplier_data['status'] == 'inactive') ? 'selected' : ''; ?>>غير نشط</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" name="update_supplier" class="submit-button">تحديث المورد</button>
                    <button type="button" class="cancel-button" onclick="window.location.href='list_supplier.php';">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>