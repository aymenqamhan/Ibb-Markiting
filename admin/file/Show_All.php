<?php
session_start();
include('../connect_DB.php'); // تأكد من أن هذا المسار صحيح لاتصال قاعدة البيانات

// التحقق من صلاحيات المستخدم
$role_id = isset($_SESSION['role_id']) ? intval($_SESSION['role_id']) : 0;

// تنفيذ الحذف عند التأكيد
if (isset($_GET['id']) && isset($_GET['del']) && $_GET['del'] === 'delete') {
    $id = intval($_GET['id']);
    // استخدام Prepared Statement للحذف الآمن
    $stmt = $con->prepare("DELETE FROM user_tb WHERE id = ?");
    if ($stmt === false) {
        // يمكنك إضافة تسجيل للخطأ هنا
        echo "<script>alert('خطأ في تهيئة استعلام الحذف: " . $con->error . "');</script>";
    } else {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo "<script>alert('تم الحذف بنجاح'); window.location='./Show_All.php';</script>"; // العودة لنفس الصفحة لتحديث الجدول
        } else {
            echo "<script>alert('فشل في الحذف: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    }
    // يجب الخروج بعد إعادة التوجيه أو رسالة التنبيه
    exit;
}

// استعلام جلب البيانات
// *** ملاحظة: كلمة المرور لا يفضل عرضها بشكل مباشر لأسباب أمنية ***
// *** إذا كانت مشفرة، يمكنك عرض رسالة "مشفرة" بدلاً من القيمة الخام ***
// *** وإذا لم تكن مشفرة، يجب تشفيرها في قاعدة البيانات ***
$sql = "SELECT id, name, email, password, role_id FROM user_tb ORDER BY id";
$sp = $con->prepare($sql);

if ($sp === false) {
    die("خطأ في تهيئة الاستعلام: " . $con->error); // رسالة خطأ في حال فشل التحضير
}

$sp->execute();
$result = $sp->get_result();

// خاص بالبحث وإدارة الصلاحيات - منطق إعادة التوجيه
// هذه الأجزاء قد تكون مرتبطة بأزرار أو روابط في لوحة التحكم الرئيسية وليس هنا.
// إذا كانت هذه الصفحة هي "عرض الكل"، فالروابط الخاصة بالبحث وإدارة الصلاحيات
// يجب أن تكون في صفحة لوحة التحكم (dashboard) أو شريط التنقل.
// أبقيتها كما هي في الكود المقدم لكن مع ملاحظة.
if (isset($_GET['search_user']) && $_GET['search_user'] === 'true') {
    header('Location:./Search_User.php');
    exit();
}
if (isset($_GET['role_users']) && $_GET['role_users'] === 'true' && $role_id == 1) {
    header('Location:./users_role.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جدول المستخدمين</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background-color: #f8f9fa; /* Light background from Bootstrap */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex; /* Make body a flex container */
            flex-direction: column; /* Stack items vertically */
            justify-content: center; /* Center items vertically */
            align-items: center; /* Center items horizontally */
            min-height: 100vh; /* Ensure body takes full viewport height */
            padding: 20px; /* Add some padding around the content */
            margin: 0; /* Remove default body margin */
        }
        .full-center-container {
            width: 100%;
            max-width: 900px; /* زيادة عرض أقصى للحاوية لاستيعاب الأعمدة الإضافية */
            padding: 2rem; /* Internal padding for the container */
            display: flex;
            flex-direction: column;
            align-items: center; /* Center contents (title, table, button) horizontally within this container */
            background-color: #fff; /* Give it a background color */
            border-radius: 0.75rem;
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.1);
            border: none;
        }

        .table-responsive.table-container { /* Combine these classes for specific styling */
            width: 100%; /* Table takes full width of its parent (.full-center-container) */
            margin-top: 20px; /* Space from the title */
        }

        .table-custom-header th {
            background-color: #007bff; /* Primary blue for header */
            color: white;
            border-color: #007bff; /* Match border color with background */
            font-weight: 600;
        }
        /* Style for Delete Button */
        .btn-delete-custom {
            background-color: #dc3545; /* Bootstrap danger red */
            border-color: #dc3545;
            color: white;
            padding: 0.4rem 0.8rem; /* Smaller padding for table button */
            font-size: 0.9rem; /* Smaller font size */
            border-radius: 0.3rem; /* Slightly less rounded */
            text-decoration: none;
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
        }
        .btn-delete-custom:hover {
            background-color: #c82333;
            border-color: #bd2130;
            transform: translateY(-1px);
            color: white;
        }
        .btn-delete-custom:active {
            transform: translateY(0);
        }

        /* Style for Edit Button */
        .btn-edit-custom {
            background-color: #ffc107; /* Bootstrap warning yellow */
            border-color: #ffc107;
            color: #212529; /* Dark text for yellow background */
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
            border-radius: 0.3rem;
            text-decoration: none;
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
        }
        .btn-edit-custom:hover {
            background-color: #e0a800;
            border-color: #d39e00;
            transform: translateY(-1px);
            color: #212529;
        }
        .btn-edit-custom:active {
            transform: translateY(0);
        }

        /* Style for Back Button */
        .btn-back-custom {
            background-color: #6c757d; /* Bootstrap secondary gray */
            border-color: #6c757d;
            color: white;
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            margin-top: 20px; /* Space from the table */
        }
        .btn-back-custom:hover {
            background-color: #5a6268;
            border-color: #545b62;
            transform: translateY(-2px);
            color: white;
        }
        .btn-back-custom:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="full-center-container">
        <h2 class="text-center text-primary mb-4 fw-bold">جدول المستخدمين</h2>

        <div class="table-responsive table-container">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-custom-header">
                    <tr>
                        <th>الرقم</th>
                        <th>اسم المستخدم</th>
                        <th>البريد الإلكتروني</th>
                        <th>كلمة المرور</th> <th>الدور</th>
                        <th>حذف</th>
                        <th>تعديل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        $count = 1;
                        while ($row = $result->fetch_assoc()) { ?>
                            <tr>
                                <td><?= $count++; ?></td>
                                <td><?= htmlspecialchars($row["name"]); ?></td>
                                <td><?= htmlspecialchars($row["email"]); ?></td>
                                <td>
                                    <?php
                                    // هذا الجزء يجب أن يتعامل مع كلمات المرور المشفرة بشكل أفضل
                                    // حالياً، يعرض القيمة الخام أو رسالة
                                    echo htmlspecialchars($row["password"]);
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($row["role_id"]); ?></td>
                                <td>
                                    <a class="btn btn-danger btn-delete-custom"
                                       href="./Show_All.php?id=<?= $row['id']; ?>&del=delete"
                                       onClick="return confirm('هل أنت متأكد من الحذف؟');">
                                       <i class="fas fa-trash-alt"></i> حذف
                                    </a>
                                </td>
                                <td>
                                    <a class="btn btn-warning btn-edit-custom"
                                       href="./edit_users.php?id=<?= $row['id']; ?>">
                                       <i class="fas fa-edit"></i> تعديل
                                    </a>
                                </td>
                            </tr>
                        <?php }
                    } else { ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">لا يوجد مستخدمون لعرضهم.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <a href="../dashbord.php" class="btn btn-back-custom">
            <i class="fas fa-arrow-right me-2"></i> العودة للصفحة الرئيسية
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php
$sp->close();
$con->close(); // يفضل إغلاق الاتصال بقاعدة البيانات عند الانتهاء
?>