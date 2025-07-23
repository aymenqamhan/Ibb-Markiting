<?php
include('../connect_DB.php'); // الاتصال بقاعدة البيانات

// التحقق من صلاحيات المستخدم
session_start();
if (!isset($_SESSION['user_name']) || $_SESSION['role_id'] != 1) {
    echo "<script>alert('لا توجد صلاحيات لإجراء هذه العملية.'); window.location.href = '/NEW_IBB/admin/login/login_user.php?logout=out';</script>";
    exit();
}

// التحقق من وجود ID القسم للحذف
if (isset($_GET['id'])) {
    $category_id = $_GET['id'];

    // استعلام للتأكد من نوع القسم (رئيسي أو فرعي)
    $stmt = $con->prepare("SELECT parent_id FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $category = $result->fetch_assoc();
    $stmt->close();

    // إذا تم تأكيد الحذف بعد أن تم النقر على الرابط
    if (isset($_GET['confirm']) && $_GET['confirm'] == 'true') {
        // إذا كان القسم فرعيًا
        if ($category['parent_id'] != NULL) {
            // حذف القسم الفرعي
            $stmt = $con->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param("i", $category_id);
            if ($stmt->execute()) {
                echo "<script>alert('تم حذف القسم الفرعي بنجاح'); window.location.href = 'delete_category.php';</script>";
            } else {
                echo "<script>alert('حدث خطأ أثناء حذف القسم الفرعي'); window.location.href = 'delete_category.php';</script>";
            }
            $stmt->close();
        } else {
            // التحقق من الأقسام الفرعية لقسم رئيسي
            $stmt = $con->prepare("SELECT COUNT(*) AS count FROM categories WHERE parent_id = ?");
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            if ($row['count'] > 0) {
                echo "<script>alert('لا يمكنك حذف قسم رئيسي يحتوي على أقسام فرعية. قم بحذف الأقسام الفرعية أولاً.'); window.location.href = 'delete_category.php';</script>";
            } else {
                // حذف القسم الرئيسي بعد التأكد أنه لا يحتوي على أقسام فرعية
                $stmt = $con->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->bind_param("i", $category_id);
                if ($stmt->execute()) {
                    echo "<script>alert('تم حذف القسم الرئيسي بنجاح'); window.location.href = 'delete_category.php';</script>";
                } else {
                    echo "<script>alert('حدث خطأ أثناء حذف القسم الرئيسي'); window.location.href = 'delete_category.php';</script>";
                }
                $stmt->close();
            }
        }
    } else {
        // إذا لم يتم تأكيد الحذف بعد، عرض رسالة التحذير
        echo "<script>
            if (confirm('هل أنت متأكد أنك تريد حذف هذا القسم؟')) {
                // إذا كان القسم فرعيًا
                if (" . ($category['parent_id'] != NULL ? 'true' : 'false') . ") {
                    if (confirm('هل أنت متأكد أنك تريد حذف القسم الفرعي؟')) {
                        window.location.href = 'delete_category.php?id=" . $category_id . "&confirm=true';
                    }
                } else {
                    window.location.href = 'delete_category.php?id=" . $category_id . "&confirm=true';
                }
            } else {
                window.location.href = 'delete_category.php';
            }
        </script>";
    }
    exit();
}

// جلب الأقسام من قاعدة البيانات لعرضها
$stmt = $con->prepare("SELECT c1.id, c1.category_name, c2.category_name AS parent_name 
FROM categories c1 LEFT JOIN categories c2 ON c1.parent_id = c2.id");
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأقسام - حذف قسم</title>
    <link rel="stylesheet" href="./categories_styles.css">
</head>
<body>

<h2>إدارة الأقسام</h2>
<table border="1">
    <tr>
        <th>الرقم</th>
        <th>اسم القسم</th>
        <th>القسم الرئيسي</th>
        <th>العمليات</th>
    </tr>

    <?php
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>" . $row['id'] . "</td>
            <td>" . $row['category_name'] . "</td>
            <td>" . ($row['parent_name'] ?? 'لا يوجد') . "</td>
            <td>
                <a class='a_delete' href='delete_category.php?id=" . $row['id'] . "' onclick='return confirmDelete();'>حذف</a>
            </td>
        </tr>";
    }
    $stmt->close();
    ?>
</table>

<script>
    // تحذير عند النقر على رابط الحذف
    function confirmDelete() {
        return confirm("هل أنت متأكد أنك تريد حذف هذا القسم؟");
    }
</script>
<button class="back" onclick="window.location.href='../dashbord.php';">العودة للصفحة الرئيسية</button>

</body>
</html>
