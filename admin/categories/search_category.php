<?php
include('../connect_DB.php'); // الاتصال بقاعدة البيانات

// التحقق من صلاحيات المستخدم
session_start();
if (!isset($_SESSION['user_name']) && ($_SESSION['role_id'] != 1||$_SESSION['role_id'] != 3)) {
    echo "<script>alert('لا توجد صلاحيات لإجراء هذه العملية.'); window.location.href = '../dashbord.php';</script>";
    exit();
}

// التحقق من وجود استعلام البحث
$search_query = isset($_POST['search_query']) ? $_POST['search_query'] : '';

// تعديل استعلام SQL للبحث عن الأقسام إذا كان هناك استعلام بحث
$query = "SELECT c1.id, c1.category_name, c2.category_name AS parent_name 
          FROM categories c1 
          LEFT JOIN categories c2 ON c1.parent_id = c2.id 
          WHERE c1.category_name LIKE ?";

// تحضير الاستعلام
$stmt = $con->prepare($query);
$search_term = "%" . $search_query . "%";
$stmt->bind_param("s", $search_term);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأقسام - بحث</title>
    <link rel="stylesheet" href="./categories_styles.css">
</head>
<body>

<h2>إدارة الأقسام - بحث</h2>

<!-- نموذج البحث -->
<form method="POST" action="search_category.php">
    <input type="text" name="search_query" placeholder="ابحث عن قسم..." value="<?php echo isset($_POST['search_query']) ? $_POST['search_query'] : ''; ?>" required>
    <button type="submit">بحث</button>
</form>

<!-- عرض الجدول -->
<?php if (isset($_POST['search_query']) && $search_query != ''): ?>
    <!-- عرض الجدول فقط إذا كان هناك استعلام بحث -->
    <?php if ($result->num_rows > 0): ?>
        <h3>نتائج البحث:</h3>
        <table border="1">
            <tr>
                <th>الرقم</th>
                <th>اسم القسم</th>
                <th>القسم الرئيسي</th>
            </tr>
            <?php
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                    <td>" . $row['id'] . "</td>
                    <td>" . $row['category_name'] . "</td>
                    <td>" . ($row['parent_name'] ?? 'لا يوجد') . "</td>
                </tr>";
            }
            ?>
        </table>
    <?php else: ?>
        <p>لا توجد نتائج للبحث.</p>
    <?php endif; ?>
<?php endif; ?>
<button class="back" onclick="window.location.href='../dashbord.php';">العودة للصفحة الرئيسية</button>

</body>
</html>

<?php
// اغلاق الاستعلام
$stmt->close();
?>
