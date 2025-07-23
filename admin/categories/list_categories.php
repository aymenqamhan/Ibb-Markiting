<?php
// list_categories.php
session_start(); // تأكد أن الجلسة بدأت إذا كنت تستخدمها لاحقًا
include '../connect_DB.php';
include './categories_functions.php'; // تأكد من أن هذا المسار صحيح

$categories = getAllCategories();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأقسام</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h5 {
            margin-bottom: 0;
        }
        .btn-success {
            background-color: #28a745; /* لون أخضر لزر الإضافة */
            border-color: #28a745;
            font-weight: bold;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
            transform: translateY(-2px);
        }
        .alert {
            border-radius: 8px;
            font-weight: 500;
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
        .btn-info, .btn-danger {
            color: white;
            font-weight: bold;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
            transform: translateY(-1px);
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
            transform: translateY(-1px);
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
                        <a class="nav-link" href="add_category.php">
                            <i class="fas fa-folder-plus me-1"></i> إضافة قسم
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="card shadow-lg">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i> قائمة الأقسام</h5>
            </div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <div class="alert alert-info text-center" role="alert">
                        <i class="fas fa-info-circle me-2"></i> لا توجد أقسام مسجلة حتى الآن.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th scope="col">رقم القسم</th>
                                    <th scope="col">اسم القسم</th>
                                    <th scope="col">القسم الأب</th>
                                    <th scope="col">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($category['id']) ?></td>
                                        <td><?= htmlspecialchars($category['category_name']) ?></td>
                                        <td><?= htmlspecialchars($category['parent_name'] ?? 'قسم رئيسي') ?></td>
                                        <td>
                                            <a href="edit_category.php?id=<?= htmlspecialchars($category['id']) ?>" class="btn btn-info btn-sm me-2">
                                                <i class="fas fa-edit me-1"></i> تعديل
                                            </a>
                                            <a href="delete_category.php?id=<?= htmlspecialchars($category['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من حذف هذا القسم؟')">
                                                <i class="fas fa-trash-alt me-1"></i> حذف
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <div class="text-center mt-3">
                    <a href="../dashbord.php" class="btn btn-back-dashboard">
                        <i class="fas fa-arrow-left me-1"></i> العودة للوحة التحكم
                    </a>
                </div>
            </div>
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