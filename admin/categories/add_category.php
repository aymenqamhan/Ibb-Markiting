<?php
include('../connect_DB.php');
// تضمين ملف الدوال
include('categories_functions.php');

// التحقق مما إذا كان المستخدم قد أرسل النموذج لإضافة قسم جديد
if (isset($_POST['add_category'])) {
    // التحقق من البيانات المدخلة
    $category_name = trim($_POST['category_name']); // إزالة المسافات البيضاء الزائدة
    $parent_id = ($_POST['category_type'] == 'sub' && !empty($_POST['parent_id'])) ? $_POST['parent_id'] : NULL;

    // استدعاء دالة إضافة القسم
    $message = addCategory($category_name, $parent_id);

    // إعادة توجيه المستخدم إلى نفس الصفحة بعد إضافة القسم
    // لتجنب إعادة إرسال النموذج عند تحديث الصفحة
    header("Location: " . $_SERVER['PHP_SELF'] . "?status=" . urlencode($message));
    exit();
}

// استلام رسالة الحالة بعد إعادة التوجيه
$status_message = '';
if (isset($_GET['status'])) {
    $status_message = urldecode($_GET['status']);
}

// جلب جميع الأقسام لعرضها في الجدول وفي قائمة الأقسام الرئيسية
$allCategories = getAllCategories();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأقسام - إضافة قسم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background-color: #f0f2f5; /* لون خلفية خفيف */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background-color: #2c3e50; /* لون داكن لشريط التنقل */
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
            background-color: #3498db; /* لون أزرق جذاب للعنوان */
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
        .container-fluid.py-4 {
            padding-top: 2rem !important;
            padding-bottom: 2rem !important;
        }
        .card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #2c3e50; /* لون داكن لرأس البطاقة */
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 1rem 1.5rem;
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
        .btn-primary {
            background-color: #28a745; /* لون أخضر للزر الأساسي */
            border-color: #28a745;
            font-weight: bold;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #218838;
            border-color: #1e7e34;
            transform: translateY(-2px);
        }
        .btn-close {
            filter: invert(1); /* لجعل زر الإغلاق أبيض في التنبيهات الداكنة */
        }
        .alert {
            border-radius: 8px;
            font-weight: 500;
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
        .table-responsive {
            margin-top: 1rem;
        }
        .table {
            border-radius: 8px;
            overflow: hidden; /* لضمان أن الزوايا المستديرة للجدول تظهر بشكل صحيح */
        }
        .table th, .table td {
            padding: 1rem;
            vertical-align: middle;
        }
        .table thead th {
            background-color: #34495e; /* لون رأس الجدول */
            color: white;
            border-bottom: none;
            font-weight: bold;
        }
        .table tbody tr:nth-child(even) {
            background-color: #f8f9fa; /* لون صفوف زوجية */
        }
        .table tbody tr:hover {
            background-color: #e9ecef; /* لون عند التحويم */
        }
        .btn-back-dashboard {
            background-color: #3498db; /* لون أزرق لزر العودة */
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
    </style>
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
                        <a class="nav-link active" aria-current="page" href="add_category.php">
                            <i class="fas fa-folder-plus me-1"></i> إضافة قسم
                        </a>
                    </li>
                    </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if (!empty($status_message)): ?>
            <div class="alert alert-<?php echo (strpos($status_message, 'بنجاح') !== false || strpos($status_message, 'تم') !== false) ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($status_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-lg mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i> إضافة قسم جديد</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="category_name" class="form-label">اسم القسم:</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required placeholder="أدخل اسم القسم هنا...">
                    </div>

                    <div class="mb-3">
                        <label for="category_type" class="form-label">نوع القسم:</label>
                        <select class="form-select" id="category_type" name="category_type" required onchange="toggleParentCategory()">
                            <option value="main">قسم رئيسي</option>
                            <option value="sub">قسم فرعي</option>
                        </select>
                    </div>

                    <div id="parent_category_div" class="mb-3" style="display:none;">
                        <label for="parent_id" class="form-label">القسم الرئيسي:</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="">-- اختر قسم رئيسي --</option>
                            <?php
                            // جلب جميع الأقسام لعرضها كخيارات للقسم الرئيسي
                            if (!empty($allCategories)) {
                                foreach ($allCategories as $category) {
                                    // تأكد من عرض الأقسام الرئيسية فقط هنا، أو جميع الأقسام إذا كان يمكن أن يكون القسم الفرعي لأي قسم
                                    // حالياً، الكود يعرض كل الأقسام. إذا كنت تريد فقط الأقسام الرئيسية، تحتاج لتعديل getAllCategories
                                    echo "<option value='" . htmlspecialchars($category['id']) . "'>" . htmlspecialchars($category['category_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <button type="submit" name="add_category" class="btn btn-primary w-100 mt-3">
                        <i class="fas fa-plus me-1"></i> إضافة قسم
                    </button>
                </form>
            </div>
        </div>

        <div class="card shadow-lg mt-5">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i> الأقسام الحالية</h5>
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allCategories as $category): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($category['id']) ?></td>
                                        <td><?= htmlspecialchars($category['category_name']) ?></td>
                                        <td><?= htmlspecialchars($category['parent_name'] ?? 'قسم رئيسي') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <div class="text-center">
                    <a href="../dashbord.php" class="btn btn-back-dashboard">
                        <i class="fas fa-arrow-left me-1"></i> العودة للوحة التحكم
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // جافا سكريبت لإظهار/إخفاء قائمة القسم الرئيسي بناءً على اختيار نوع القسم
        document.addEventListener('DOMContentLoaded', function() {
            var categoryTypeSelect = document.getElementById('category_type');
            var parentCategoryDiv = document.getElementById('parent_category_div');
            
            function toggleParentCategory() {
                if (categoryTypeSelect.value === 'sub') {
                    parentCategoryDiv.style.display = 'block';
                } else {
                    parentCategoryDiv.style.display = 'none';
                }
            }

            // تشغيل الدالة عند تحميل الصفحة للتأكد من الحالة الصحيحة (إذا كان هناك إعادة توجيه بعد خطأ واختيار "فرعي")
            toggleParentCategory(); 
            // إضافة المستمع لتغيير القيمة
            categoryTypeSelect.addEventListener('change', toggleParentCategory);
        });
    </script>
</body>
</html>
<?php
// إغلاق الاتصال بقاعدة البيانات
if (isset($con) && $con->ping()) {
    $con->close();
}
?>