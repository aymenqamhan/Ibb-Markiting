<?php
include('../connect_DB.php'); // الاتصال بقاعدة البيانات
session_start();

// التحقق من صلاحيات المستخدم
if (!isset($_SESSION['user_name']) || $_SESSION['role_id'] != 1) {
    echo "<script>alert('لا توجد صلاحيات لإجراء هذه العملية.'); window.location.href = '../dashbord.php';</script>";
    exit();
}

$logged_in_role = $_SESSION['role_id']; // دور المستخدم المسجل

// التحقق من استقبال معرف المستخدم
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("معرّف المستخدم غير موجود.");
}

$user_id = intval($_GET['id']);

// جلب بيانات المستخدم
$stmt = $con->prepare("SELECT id, name, email, role_id, password FROM user_tb WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("المستخدم غير موجود.");
}

$user = $result->fetch_assoc();

// عند إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    // التحقق من صحة كلمة المرور إذا أراد المستخدم تغييرها
    if (!empty($new_password) || !empty($old_password)) {
        if (empty($old_password) || empty($new_password)) {
            echo "<script>alert('يجب إدخال كل من كلمة المرور القديمة والجديدة لتحديثها.');</script>";
        }
        elseif ($old_password !== $user['password']) {
            echo "<script>alert('كلمة المرور القديمة غير صحيحة.');</script>";
        }
        else {
            // تحديث البيانات مع كلمة المرور
            $stmt = $con->prepare("UPDATE user_tb SET name = ?, email = ?, password = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $email, $new_password, $user_id);
            if ($stmt->execute()) {
                echo "<script>alert('تم تحديث البيانات بنجاح.'); window.location.href='../dashbord.php';</script>";
                exit();
            } else {
                echo "حدث خطأ أثناء تحديث البيانات.";
            }
        }
    } 
    else {
        // تحديث البيانات بدون تغيير كلمة المرور
        $stmt = $con->prepare("UPDATE user_tb SET name = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $email, $user_id);
        if ($stmt->execute()) {
            echo "<script>alert('تم تحديث البيانات بنجاح.'); window.location.href='../dashbord.php';</script>";
            exit();
        } else {
            echo "حدث خطأ أثناء تحديث البيانات.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل المستخدم</title>
    <link rel="stylesheet" href="../style/editStyle.css">
    <link rel="stylesheet" href="../style/btn_back.css">
</head>
<body>

<div class="container">
    <h2>تعديل بيانات المستخدم</h2>
    <form method="POST">
        <label>الاسم:</label>
        <input type="text" name="name" value="<?= htmlspecialchars($user['name']); ?>" required>

        <label>البريد الإلكتروني:</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" required>

        <?php if ($logged_in_role == 1) { ?>
            <label>كلمة المرور السابقة:</label>
            <input type="password" name="old_password">

            <label>كلمة المرور الجديدة (اختياري):</label>
            <input type="password" name="new_password">
        <?php } ?>

        <button type="submit">حفظ التعديلات</button>
    </form>

    <button class="back" onclick="window.location.href='../dashbord.php';">العودة للصفحة الرئيسية</button>
</div>
</body>
</html>