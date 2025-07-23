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

// إعداد الاستعلام لجلب الموردين
$search_query = $_GET['search'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'name';
$sort_order = $_GET['sort_order'] ?? 'ASC';

// قائمة الأعمدة المسموح بها للفرز
$allowed_sort_columns = ['name', 'contact_name', 'contact_phone', 'contact_email', 'status']; // تم تعديل contact_person إلى contact_name
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'name'; // قيمة افتراضية آمنة
}
if (!in_array(strtoupper($sort_order), ['ASC', 'DESC'])) {
    $sort_order = 'ASC'; // قيمة افتراضية آمنة
}

// تم تضمين notes في الاستعلام للعرض إن أردت إضافته كعمود في الجدول
$sql = "SELECT id, name, contact_name, contact_phone, contact_email, address, notes, status FROM suppliers WHERE 1=1"; // تم تعديل contact_person إلى contact_name وإضافة notes
$params = '';
$bind_values = [];

if (!empty($search_query)) {
    // تم تعديل contact_person إلى contact_name في البحث
    $sql .= " AND (name LIKE ? OR contact_name LIKE ? OR contact_phone LIKE ? OR contact_email LIKE ? OR address LIKE ? OR notes LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params .= "ssssss"; // زيادة عدد الـ 's' لتناسب الأعمدة الجديدة في البحث
    $bind_values = array_merge($bind_values, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params .= "s";
    $bind_values[] = $status_filter;
}

$sql .= " ORDER BY " . $sort_by . " " . $sort_order;

$stmt = $con->prepare($sql);
if ($stmt === false) {
    die("خطأ في إعداد الاستعلام: " . $con->error);
}

if (!empty($params)) {
    $stmt->bind_param($params, ...$bind_values);
}

$stmt->execute();
$result = $stmt->get_result();
$suppliers = [];
while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الموردين</title>
    <link rel="stylesheet" href="./style.css"> 
</head>
<body>
    <div class="container">
        <h1>إدارة الموردين</h1>

        <?php echo $message; ?>

        <a href="add_supplier.php" class="add-button">إضافة مورد جديد</a>

        <div class="filters-container">
            <form method="GET" action="">
                <input type="text" name="search" placeholder="بحث بالاسم، الهاتف، البريد، العنوان، الملاحظات..." value="<?php echo htmlspecialchars($search_query); ?>">
                <select name="status_filter">
                    <option value="">جميع الحالات</option>
                    <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>نشط</option>
                    <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>غير نشط</option>
                </select>
                <select name="sort_by">
                    <option value="name" <?php echo ($sort_by == 'name') ? 'selected' : ''; ?>>الاسم</option>
                    <option value="contact_name" <?php echo ($sort_by == 'contact_name') ? 'selected' : ''; ?>>الشخص المسؤول</option>
                    <option value="contact_phone" <?php echo ($sort_by == 'contact_phone') ? 'selected' : ''; ?>>الهاتف</option>
                    <option value="contact_email" <?php echo ($sort_by == 'contact_email') ? 'selected' : ''; ?>>البريد الإلكتروني</option>
                    <option value="status" <?php echo ($sort_by == 'status') ? 'selected' : ''; ?>>الحالة</option>
                </select>
                <select name="sort_order">
                    <option value="ASC" <?php echo ($sort_order == 'ASC') ? 'selected' : ''; ?>>تصاعدي</option>
                    <option value="DESC" <?php echo ($sort_order == 'DESC') ? 'selected' : ''; ?>>تنازلي</option>
                </select>
                <button type="submit">بحث وفرز</button>
            </form>
        </div>

        <?php if (empty($suppliers)): ?>
            <p>لا توجد موردين لعرضهم.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th><a href="?sort_by=name&sort_order=<?php echo ($sort_by == 'name' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo htmlspecialchars($search_query); ?>&status_filter=<?php echo htmlspecialchars($status_filter); ?>">الاسم</a></th>
                        <th><a href="?sort_by=contact_name&sort_order=<?php echo ($sort_by == 'contact_name' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo htmlspecialchars($search_query); ?>&status_filter=<?php echo htmlspecialchars($status_filter); ?>">الشخص المسؤول</a></th>
                        <th><a href="?sort_by=contact_phone&sort_order=<?php echo ($sort_by == 'contact_phone' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo htmlspecialchars($search_query); ?>&status_filter=<?php echo htmlspecialchars($status_filter); ?>">الهاتف</a></th>
                        <th><a href="?sort_by=contact_email&sort_order=<?php echo ($sort_by == 'contact_email' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo htmlspecialchars($search_query); ?>&status_filter=<?php echo htmlspecialchars($status_filter); ?>">البريد الإلكتروني</a></th>
                        <th>العنوان</th> <th>الملاحظات</th> <th><a href="?sort_by=status&sort_order=<?php echo ($sort_by == 'status' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo htmlspecialchars($search_query); ?>&status_filter=<?php echo htmlspecialchars($status_filter); ?>">الحالة</a></th>
                        <th class="actions-column">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['contact_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($supplier['contact_phone']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['contact_email']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['address'] ?? 'N/A'); ?></td> <td><?php echo htmlspecialchars($supplier['notes'] ?? 'N/A'); ?></td> <td><span class="status-badge <?php echo htmlspecialchars($supplier['status']); ?>"><?php echo ($supplier['status'] == 'active') ? 'نشط' : 'غير نشط'; ?></span></td>
                            <td class="actions-column">
                                <a href="./supplier_details.php?id=<?php echo htmlspecialchars($supplier['id']); ?>" class="action-button details-button">تفاصيل</a>
                                <a href="edit_supplier.php?id=<?php echo htmlspecialchars($supplier['id']); ?>" class="action-button edit-button">تعديل</a>
                                <a href="delete_supplier.php?delete_id=<?php echo htmlspecialchars($supplier['id']); ?>" class="action-button delete-button" onclick="return confirm('هل أنت متأكد من حذف هذا المورد؟');">حذف</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <button class="back" onclick="window.location.href='../dashbord.php';">العودة للوحة التحكم</button>
    </div>
</body>
</html>