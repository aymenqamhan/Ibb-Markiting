<?php
session_start();
// Corrected relative path to connect_DB.php assuming `include` folder is up two levels
include('../../include/connect_DB.php'); 

// Check user permissions (e.g., if it's an admin/manager)
// Added ?? 0 to prevent 'Undefined index' warning if 'role_id' is not set
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role_id'] ?? 0), [1, 3])) { // Allowing roles 1 and 3
    $_SESSION['error_message'] = "ليس لديك صلاحية الوصول إلى هذه الصفحة.";
    // Use an absolute path for redirect to login page for robustness
    header("Location: /NEW_IBB/login.php"); 
    exit();
}

$products = [];
// Check if $con (database connection) is successfully established
if ($con) {
    $query = "SELECT id, name, price FROM products ORDER BY name ASC";
    $result = $con->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $result->free(); // Free result set
    } else {
        $_SESSION['error_message'] = "خطأ في جلب المنتجات: " . $con->error;
        error_log("Error fetching products for price update: " . $con->error); // Log the error
    }
} else {
    $_SESSION['error_message'] = "خطأ في الاتصال بقاعدة البيانات. الرجاء المحاولة لاحقًا.";
    error_log("Database connection failed for price update."); // Log the error
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل أسعار المنتجات - لوحة الإدارة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <link rel="stylesheet" href="./price_update_styles.css"> 
</head>
<body>
    <div class="main-wrapper">
        <div class="container my-5">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center py-3 px-4 rounded-top-4">
                    <h4 class="mb-0 text-white"><i class="fas fa-dollar-sign me-2"></i> تعديل أسعار المنتجات</h4>
                    <a href="/NEW_IBB/admin/dashbord.php" class="btn btn-light btn-sm back-btn">
                        <i class="fas fa-arrow-right-to-bracket me-2"></i> العودة للوحة التحكم
                    </a>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($products)): ?>
                        <div class="alert alert-info text-center py-4 rounded-3" role="alert">
                            <i class="fas fa-info-circle fa-2x mb-3"></i>
                            <p class="mb-0 fs-5">لا توجد منتجات لعرضها حاليًا في قاعدة البيانات.</p>
                            <small class="text-muted">يرجى إضافة منتجات جديدة لبدء إدارة الأسعار.</small>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped product-price-table">
                                <thead class="bg-light-subtle">
                                    <tr>
                                        <th scope="col" class="text-nowrap">اسم المنتج</th>
                                        <th scope="col" class="text-nowrap">السعر الحالي</th>
                                        <th scope="col" class="text-nowrap">السعر الجديد</th>
                                        <th scope="col" class="text-nowrap">الإجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td class="text-start align-middle product-name-cell"><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td class="align-middle current-price-cell"><?php echo number_format($product['price'], 2); ?> $</td>
                                            <td class="align-middle new-price-input-cell">
                                                <form action="update_product_price.php" method="POST" class="d-flex align-items-center price-update-form">
                                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                                                    <input type="number" name="new_price" class="form-control form-control-sm price-input" step="0.01" min="0" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                                                    <button type="submit" class="btn btn-primary btn-sm update-btn ms-2">
                                                        <i class="fas fa-sync-alt me-1"></i> تحديث
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="align-middle action-cell">
                                                </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php
// Only close connection if it was successfully opened
if (isset($con) && $con->ping()) { 
    $con->close();
}
?>