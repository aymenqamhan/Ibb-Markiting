<?php
include('../connect_DB.php'); // الاتصال بقاعدة البيانات
include('categories_functions.php'); // تضمين ملف الدوال، لكي نستطيع استخدام getAllCategories مثلاً

// التحقق من صلاحيات المستخدم
session_start();
// التأكد من أن المستخدم مسجل الدخول ولديه الصلاحيات (role_id 1 أو 3)
// لاحظ أن الشرط `$_SESSION['role_id'] != 1 || $_SESSION['role_id'] != 3` سيجعل الشرط دائمًا صحيحًا تقريبًا.
// يجب أن يكون الشرط `!($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 3)` أو `($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3)`
if (!isset($_SESSION['user_name']) || (!isset($_SESSION['role_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3))) {
    echo "<script>alert('لا توجد صلاحيات لإجراء هذه العملية.'); window.location.href = '../dashbord.php';</script>";
    exit();
}

$category = null; // تهيئة متغير القسم
$category_id = null; // تهيئة متغير ID القسم

// إذا كان هناك ID للقسم للتعديل من خلال الرابط (GET)
if (isset($_GET['id'])) {
    $category_id = $_GET['id'];

    // جلب بيانات القسم لتعديله
    $stmt = $con->prepare("SELECT id, category_name, parent_id FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // إذا لم يتم العثور على القسم
    if (!$category) {
        echo "<script>alert('القسم غير موجود.'); window.location.href = 'list_categories.php';</script>";
        exit();
    }
}

// معالجة التعديل عند إرسال النموذج (POST)
$status_message = '';
if (isset($_POST['update_category'])) {
    // التأكد أن الـ ID موجود، قد لا يكون موجودًا إذا تم الوصول للصفحة مباشرة بدون ID في الـ GET
    if (!$category_id) {
        $status_message = 'لم يتم تحديد القسم للتعديل.';
    } else {
        $category_name = trim($_POST['category_name']); // إزالة المسافات البيضاء
        $parent_id = $_POST['parent_id'] !== '' ? $_POST['parent_id'] : NULL;

        // التحقق من أن القسم لا يكون ابنًا لنفسه أو لأحد أبنائه لمنع التكرار اللانهائي
        if ($parent_id == $category_id) {
            $status_message = 'لا يمكن أن يكون القسم أبًا لنفسه.';
        } else {
            // تحقق إضافي: منع أن يصبح القسم الفرعي أبًا لقسمه الأصلي أو لأي من أسلافه
            $is_ancestor = isAncestor($con, $category_id, $parent_id);
            if ($is_ancestor) {
                $status_message = 'لا يمكن تعيين قسم فرعي ليكون قسماً رئيسياً لأحد أجداده أو نفسه.';
            } else {
                // تحديث البيانات
                $stmt = $con->prepare("UPDATE categories SET category_name = ?, parent_id = ? WHERE id = ?");
                $stmt->bind_param("sii", $category_name, $parent_id, $category_id);
                if ($stmt->execute()) {
                    $status_message = 'تم تحديث القسم بنجاح!';
                    // لإعادة تحميل بيانات القسم بعد التحديث وعرضها في النموذج
                    $stmt_re = $con->prepare("SELECT id, category_name, parent_id FROM categories WHERE id = ?");
                    $stmt_re->bind_param("i", $category_id);
                    $stmt_re->execute();
                    $category = $stmt_re->get_result()->fetch_assoc();
                    $stmt_re->close();
                } else {
                    $status_message = 'حدث خطأ أثناء تحديث القسم: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// جلب جميع الأقسام لعرضها في الجدول وفي قائمة الأقسام الرئيسية
$allCategories = getAllCategories();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأقسام - تعديل قسم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background-color: #2c3e50;
            padding: 1rem 0;
        }
        .navbar-brand {
            color: #ecf0f1 !important;
            font-weight: bold;
            font-size: 1.5rem;
        }
        .navbar-nav .nav-link {
            color: #ecf0f1 !important;
            margin-left: 15px;
        }
        .navbar-nav .nav-link:hover {
            color: #3498db !important;
        }
        .page-header {
            background-color: #3498db;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .page-header h1 {
            margin: 0;
            font-size: 2.5rem;
        }
        .container.py-4 {
            padding-top: 2rem !important;
            padding-bottom: 2rem !important;
        }
        .card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #2c3e50;
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h5 {
            margin-bottom: 0;
        }
        .form-label {
            font-weight: bold;
            color: #34495e;
            margin-bottom: 0.5rem;
        }
        .form-control, .form-select {
            border-radius: 5px;
            border: 1px solid #ced4da;
            padding: 0.75rem 1rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        .btn-warning {
            background-color: #ffc107; /* لون أصفر لزر التعديل */
            border-color: #ffc107;
            color: #343a40; /* لون نص داكن */
            font-weight: bold;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #d39e00;
            transform: translateY(-2px);
        }
        .btn-close {
            filter: invert(1);
        }
        .alert {
            border-radius: 8px;
            font-weight: 500;
            margin-bottom: 1.5rem; /* مسافة أسفل التنبيهات */
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }
        .table-responsive {
            margin-top: 1rem;
        }
        .table {
            border-radius: 8px;
            overflow: hidden;
        }
        .table th, .table td {
            padding: 1rem;
            vertical-align: middle;
        }
        .table thead th {
            background-color: #34495e;
            color: white;
            border-bottom: none;
            font-weight: bold;
        }
        .table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .table tbody tr:hover {
            background-color: #e9ecef;
        }
        .table tbody tr.highlight {
            background-color: #fff3cd; /* لون تمييز للقسم الذي يتم تعديله */
            font-weight: bold;
        }
        .btn-back-dashboard {
            background-color: #3498db;
            border-color: #3498db;
            color: white;
            margin-top: 1.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-back-dashboard:hover {
            background-color: #2980b9;
            border-color: #2980b9;
            transform: translateY(-2px);
        }
        /* Style for the link buttons inside the table */
        .table .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
        .table .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }
        .table .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }
    </style>
    <?php
    // هذا الكود يمكن وضعه في ملف categories_functions.php أو في ملف utilites.php
    // لتجنب تكرار الكود ولتحسين التنظيم.
    function isAncestor($con, $child_id, $potential_parent_id) {
        if ($potential_parent_id === NULL) {
            return false; // لا يمكن أن يكون القسم الرئيسي (NULL) سلفًا لأحد
        }

        $current_id = $child_id;
        while ($current_id !== NULL) {
            $stmt = $con->prepare("SELECT parent_id FROM categories WHERE id = ?");
            $stmt->bind_param("i", $current_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if ($row['parent_id'] == $potential_parent_id) {
                    return true; // وجدنا أن الـ potential_parent_id هو سلف
                }
                $current_id = $row['parent_id'];
            } else {
                $current_id = NULL; // لم يتم العثور على القسم أو لا يوجد له أب
            }
            $stmt->close();
        }
        return false;
    }
    ?>
</head>
<body>

    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashbord.php">
                <i class="fas fa-cubes me-2"></i> لوحة التحكم
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">

                    <li class="nav-item">
                        <a class="nav-link" href="list_categories.php">
                            <i class="fas fa-list me-1"></i> إدارة الأقسام
                        </a>
                    </li>

                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if (!empty($status_message)): ?>
            <div class="alert alert-<?php echo (strpos($status_message, 'بنجاح') !== false) ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($status_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-lg mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-tags me-2"></i> الأقسام الحالية</h5>
            </div>
            <div class="card-body">
                <?php if (empty($allCategories)): ?>
                    <div class="alert alert-info text-center" role="alert">
                        <i class="fas fa-info-circle me-2"></i> لا توجد أقسام مسجلة حتى الآن.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th scope="col">الرقم</th>
                                    <th scope="col">اسم القسم</th>
                                    <th scope="col">القسم الرئيسي</th>
                                    <th scope="col">العمليات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allCategories as $row): ?>
                                    <tr class="<?= ($category && $row['id'] == $category['id']) ? 'highlight' : '' ?>">
                                        <td><?= htmlspecialchars($row['id']) ?></td>
                                        <td><?= htmlspecialchars($row['category_name']) ?></td>
                                        <td><?= htmlspecialchars($row['parent_name'] ?? 'قسم رئيسي') ?></td>
                                        <td>
                                            <a href='edit_category.php?id=<?= htmlspecialchars($row['id']) ?>' class="btn btn-info btn-sm">
                                                <i class="fas fa-edit me-1"></i> تعديل
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($category): ?>
            <div class="card shadow-lg mt-5">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-pen-to-square me-2"></i> تعديل القسم: <span class="text-warning"><?= htmlspecialchars($category['category_name']) ?></span></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="category_id" value="<?= htmlspecialchars($category['id']) ?>">
                        <div class="mb-3">
                            <label for="category_name" class="form-label">اسم القسم:</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" value="<?= htmlspecialchars($category['category_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="parent_id" class="form-label">القسم الرئيسي:</label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">-- اختر قسم رئيسي --</option>
                                <?php
                                // جلب جميع الأقسام ما عدا القسم نفسه لمنع أن يصبح أبًا لنفسه
                                // وتحقق من عدم إضافة قسم فرعي كأب لأحد أجداده
                                if (!empty($allCategories)) {
                                    foreach ($allCategories as $row) {
                                        if ($row['id'] == $category_id) continue; // لا يمكن أن يكون القسم أبًا لنفسه

                                        // لا تظهر الأقسام التي هي بالفعل من نسل القسم الحالي
                                        if (isAncestor($con, $row['id'], $category_id)) {
                                            // إذا كان القسم الحالي (الذي نعدله) سلفاً للقسم في القائمة
                                            // فهذا يعني أن القسم في القائمة هو ابن للقسم الحالي أو حفيد
                                            // لذا لا يجب أن نستخدمه كأب للقسم الحالي.
                                            continue;
                                        }

                                        $selected = ($row['id'] == $category['parent_id']) ? 'selected' : '';
                                        $prefix = ($row['parent_id'] !== null) ? '&nbsp;&nbsp;&nbsp;↳ ' : '';
                                        echo "<option value='" . htmlspecialchars($row['id']) . "' $selected>" . $prefix . htmlspecialchars($row['category_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" name="update_category" class="btn btn-warning w-100 mt-3">
                            <i class="fas fa-sync-alt me-1"></i> تحديث القسم
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> يرجى اختيار قسم من الجدول أعلاه لتعديله.
            </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="../dashbord.php" class="btn btn-back-dashboard">
                <i class="fas fa-arrow-left me-1"></i> العودة للوحة التحكم
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php
// Close the database connection
if (isset($con) && $con->ping()) {
    $con->close();
}
?>