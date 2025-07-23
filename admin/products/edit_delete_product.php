<?php
include('../connect_DB.php'); // الاتصال بقاعدة البيانات
include('./products_functions.php'); // تضمين ملف الدوال

// التحقق من صلاحيات المستخدم
session_start();
// التأكد من أن المستخدم مسجل الدخول وأن لديه الصلاحيات المطلوبة (1 أو 3)
// تم تصحيح منطق الشرط ليكون أكثر دقة
if (!isset($_SESSION['user_name']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3)) {
    echo "<script>alert('لا توجد صلاحيات لإجراء هذه العملية.'); window.location.href = '../dashbord.php';</script>";
    exit();
}

// استدعاء الدالة للحصول على جميع المنتجات
$products = getAllProducts_OnePhoto(); // استدعاء الدالة التي تُرجع قائمة المنتجات

// هذه المتغيرات ستحتوي على رسائل الخطأ أو النجاح
$message = '';
$message_type = ''; // 'success' or 'error'

// التحقق من وجود طلب حذف
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // بدء المعاملة لضمان سلامة البيانات
    $con->begin_transaction();

    try {
        // حذف الصور المرتبطة بالمنتج من جدول products_images أولاً
        $delete_images_query = "DELETE FROM products_images WHERE product_id = ?";
        $stmt_images = $con->prepare($delete_images_query);
        if (!$stmt_images) {
            throw new Exception("خطأ في تهيئة استعلام حذف الصور: " . $con->error);
        }
        $stmt_images->bind_param("i", $delete_id);
        if (!$stmt_images->execute()) {
            throw new Exception("خطأ في تنفيذ استعلام حذف الصور: " . $stmt_images->error);
        }
        $stmt_images->close();

        // حذف سجلات المخزون المرتبطة بالمنتج
        $delete_inventory_query = "DELETE FROM inventory WHERE product_id = ?";
        $stmt_inventory = $con->prepare($delete_inventory_query);
        if (!$stmt_inventory) {
            throw new Exception("خطأ في تهيئة استعلام حذف المخزون: " . $con->error);
        }
        $stmt_inventory->bind_param("i", $delete_id);
        if (!$stmt_inventory->execute()) {
            throw new Exception("خطأ في تنفيذ استعلام حذف المخزون: " . $stmt_inventory->error);
        }
        $stmt_inventory->close();

        // حذف الأحجام المرتبطة بالمنتج من جدول product_sizes
        $delete_sizes_query = "DELETE FROM product_sizes WHERE product_id = ?";
        $stmt_sizes = $con->prepare($delete_sizes_query);
        if (!$stmt_sizes) {
            throw new Exception("خطأ في تهيئة استعلام حذف الأحجام: " . $con->error);
        }
        $stmt_sizes->bind_param("i", $delete_id);
        if (!$stmt_sizes->execute()) {
            throw new Exception("خطأ في تنفيذ استعلام حذف الأحجام: " . $stmt_sizes->error);
        }
        $stmt_sizes->close();

        // ثم حذف المنتج نفسه من جدول products
        $delete_product_query = "DELETE FROM products WHERE id = ?";
        $stmt_product = $con->prepare($delete_product_query);
        if (!$stmt_product) {
            throw new Exception("خطأ في تهيئة استعلام حذف المنتج: " . $con->error);
        }
        $stmt_product->bind_param("i", $delete_id);
        if (!$stmt_product->execute()) {
            throw new Exception("خطأ في تنفيذ استعلام حذف المنتج: " . $stmt_product->error);
        }
        $stmt_product->close();

        $con->commit(); // تأكيد المعاملة
        $message = 'تم حذف المنتج بنجاح!';
        $message_type = 'success';
        // إعادة تحميل المنتجات بعد الحذف
        $products = getAllProducts_OnePhoto(); 

    } catch (Exception $e) {
        $con->rollback(); // التراجع عن المعاملة في حال حدوث خطأ
        $message = 'حدث خطأ أثناء حذف المنتج: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Product Deletion Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title class="mb-4">إدارة المنتجات (تعديل/حذف)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa; /* Light background */
            padding: 20px;
        }
        .container-fluid {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            margin: 30px auto;
        }
        h1 {
            color: #007bff; /* Bootstrap danger red for delete mode */
            margin-bottom: 30px;
            text-align: center;
        }
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }
        .table-responsive {
            margin-top: 20px;
        }
        /* Adjust column width for better readability if needed */
        .table th,
        .table td {
            vertical-align: middle; /* Center content vertically */
            padding: 0.75rem; /* Default Bootstrap padding */
        }
        .no-products-message {
            text-align: center;
            padding: 30px;
            font-size: 1.2rem;
            color: #6c757d;
        }
        .action-buttons {
            white-space: nowrap; /* Prevent buttons from wrapping */
        }
        .action-buttons .btn {
            margin: 0 3px; /* Small margin between buttons */
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <h1 class="mb-4">إدارة المنتجات (تعديل / حذف)</h1>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (count($products) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>الصورة</th>
                            <th>اسم المنتج</th>
                            <th>الوصف</th>
                            <th>السعر</th>
                            <th>القسم</th>
                            <th>الحالة</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $row): ?>
                            <tr>
                                <td>
                                    <?php if ($row['image_path']): ?>
                                        <img src="./<?php echo htmlspecialchars($row['image_path']); ?>" alt="صورة المنتج" class="product-image">
                                    <?php else: ?>
                                        <span class="text-muted">لا توجد صورة</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars(mb_strimwidth($row['description'], 0, 50, "...")); ?></td>
                                <td><?php echo number_format($row['price'], 2); ?> ج.م</td>
                                <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $row['status'] == 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $row['status'] == 'active' ? 'نشط' : 'غير نشط'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                <td class="action-buttons">
                                    <a class='btn btn-warning btn-sm' href="./edit_product.php?id=<?php echo $row['id']; ?>" title="تعديل المنتج">
                                        <i class="fas fa-edit"></i> تعديل
                                    </a>
                                    <a class='btn btn-danger btn-sm' href="./edit_delete_product.php?delete_id=<?php echo $row['id']; ?>" 
                                       onclick="return confirm('هل أنت متأكد أنك تريد حذف هذا المنتج وجميع بياناته المرتبطة (المخزون، الأحجام، الصور)؟')" title="حذف المنتج">
                                        <i class="fas fa-trash-alt"></i> حذف
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="no-products-message">لا توجد منتجات لعرضها.</p>
        <?php endif; ?>

        <div class="d-flex justify-content-end mt-4">
            <button class="btn btn-secondary" onclick="window.location.href='../dashbord.php';">
                <i class="fas fa-arrow-right-to-bracket me-2"></i> العودة للصفحة الرئيسية
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php
// إغلاق اتصال قاعدة البيانات بعد انتهاء عرض الصفحة
if (isset($con) && $con->ping()) {
    $con->close();
}
?>