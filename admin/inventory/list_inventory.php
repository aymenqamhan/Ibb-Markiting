<?php
session_start();
// تضمين ملف الاتصال بقاعدة البيانات
include('../../include/connect_DB.php'); // المسار الصحيح حسب هيكلة مجلداتك

// تحقق من صلاحيات المستخدم (مثلاً، هل هو مدير/مسؤول)
// Added ?? 0 to prevent 'Undefined index' warning if 'role_id' is not set
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role_id'] ?? 0), [1, 3])) { 
    $_SESSION['error_message'] = "ليس لديك صلاحية الوصول إلى هذه الصفحة.";
    // Use an absolute path for redirect to login page for robustness
    header("Location: /NEW_IBB/login.php"); 
    exit();
}

// متغيرات البحث والتصفية الافتراضية
$search_product_name = $_GET['search_product_name'] ?? '';
$inventory_status = $_GET['inventory_status'] ?? 'all'; // Default to 'all' for clarity

$sql = "SELECT
            inv.id AS inventory_id,
            inv.sku AS inventory_sku,
            inv.barcode,
            inv.quantity,
            inv.cost_price,
            inv.selling_price, 
            (inv.cost_price * inv.quantity) AS calculated_total_cost_price, 
            inv.unit,
            inv.is_serial_tracked,
            inv.created_at AS inventory_created_at,
            inv.updated_at AS inventory_updated_at,
            p.name AS product_name,
            p.min_stock_level, /* Added p.min_stock_level from products table */
            p.status AS product_status,
            s.size AS size_name,
            u_created.name AS created_by_user_name,
            u_updated.name AS updated_by_user_name 
        FROM
            inventory inv
        LEFT JOIN
            products p ON inv.product_id = p.id
        LEFT JOIN
            product_sizes s ON inv.size_id = s.id
        LEFT JOIN
            user_tb u_created ON inv.created_by_user_id = u_created.id 
        LEFT JOIN
            user_tb u_updated ON inv.updated_by_user_id = u_updated.id 
        WHERE 1=1"; 

// تطبيق فلتر البحث باسم المنتج
if (!empty($search_product_name)) {
    $search_product_name_escaped = $con->real_escape_string($search_product_name);
    $sql .= " AND (p.name LIKE '%{$search_product_name_escaped}%' OR inv.sku LIKE '%{$search_product_name_escaped}%' OR inv.barcode LIKE '%{$search_product_name_escaped}%')";
}

// تطبيق فلتر حالة المخزون
if ($inventory_status != 'all') { // No need to check for empty string if default is 'all'
    switch ($inventory_status) {
        case 'in_stock':
            $sql .= " AND inv.quantity > 0";
            break;
        case 'low_stock':
            // Ensure min_stock_level is not NULL and quantity is less than or equal to it but greater than 0
            $sql .= " AND inv.quantity > 0 AND p.min_stock_level IS NOT NULL AND inv.quantity <= p.min_stock_level";
            break;
        case 'out_of_stock':
            $sql .= " AND inv.quantity = 0";
            break;
        case 'active_product':
            $sql .= " AND p.status = 'active'";
            break;
    }
}

$sql .= " ORDER BY inv.created_at DESC"; // ترتيب حسب تاريخ الإنشاء الأحدث أولاً

$result = $con->query($sql);

// Handle database query errors
if (!$result) {
    $_SESSION['error_message'] = "خطأ في استعلام قاعدة البيانات: " . $con->error;
    error_log("Inventory query error: " . $con->error); // Log the error for debugging
    // You might want to redirect or show a critical error page here
    // header("Location: /error_page.php"); exit();
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المخزون - متجري</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="inventory_styles.css"> 
</head>
<body>

    <div class="container-fluid">

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

        <div class="card mb-4"> <div class="card-header filter-header" data-bs-toggle="collapse" data-bs-target="#searchFilters" aria-expanded="true" aria-controls="searchFilters">
                <h5 class="mb-0 text-white">
                    <i class="fas fa-filter me-2"></i> فلاتر البحث والمخزون
                </h5>
                <i class="fas fa-chevron-down"></i> 
            </div>
            <div id="searchFilters" class="collapse show">
                <div class="card-body">
                    <form action="" method="GET">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="search_product_name" class="form-label">بحث باسم المنتج / SKU / باركود</label>
                                <input type="text" class="form-control" id="search_product_name" name="search_product_name" placeholder="أدخل اسم المنتج أو SKU أو باركود" value="<?php echo htmlspecialchars($search_product_name); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="inventory_status" class="form-label">حالة المخزون</label>
                                <select class="form-select" id="inventory_status" name="inventory_status">
                                    <option value="all" <?php echo ($inventory_status == 'all') ? 'selected' : ''; ?>>الكل</option>
                                    <option value="in_stock" <?php echo ($inventory_status == 'in_stock') ? 'selected' : ''; ?>>متوفر</option>
                                    <option value="low_stock" <?php echo ($inventory_status == 'low_stock') ? 'selected' : ''; ?>>مخزون منخفض</option>
                                    <option value="out_of_stock" <?php echo ($inventory_status == 'out_of_stock') ? 'selected' : ''; ?>>نفذ المخزون</option>
                                    <option value="active_product" <?php echo ($inventory_status == 'active_product') ? 'selected' : ''; ?>>منتج نشط</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i> تطبيق الفلتر
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 text-white">
                    <i class="fas fa-boxes me-2"></i> قائمة المنتجات في المخزون
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>اسم المنتج</th>
                                <th>الحجم</th>
                                <th>الكمية المتوفرة</th>
                                <th>سعر الوحدة (التكلفة)</th>
                                <th>إجمالي سعر التكلفة</th>
                                <th>سعر البيع للحبه</th>
                                <th>الحد الأدنى للمخزون</th>
                                <th>الحالة</th>
                                <th>تاريخ الإنشاء</th>
                                <th>من أنشأ</th>
                                <th>تاريخ التحديث</th>
                                <th>من قام بالتحديث</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $inventory_status_display = "متوفر";
                                    $status_class = "badge-in-stock";
                                    
                                    // Check for product status first, if product is inactive, inventory is also treated as such
                                    if ($row['product_status'] == 'inactive') {
                                        $inventory_status_display = "منتج غير نشط";
                                        $status_class = "badge-out-of-stock"; // Or a specific badge-inactive
                                    } elseif ($row['quantity'] == 0) {
                                        $inventory_status_display = "نفذ المخزون";
                                        $status_class = "badge-out-of-stock";
                                    } elseif ($row['quantity'] > 0 && $row['min_stock_level'] !== null && $row['quantity'] <= $row['min_stock_level']) {
                                        $inventory_status_display = "مخزون منخفض";
                                        $status_class = "badge-low-stock";
                                    }

                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['inventory_sku']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['product_name'] ?? 'N/A') . "</td>"; // Product name can be null if product is deleted
                                    echo "<td>" . htmlspecialchars($row['size_name'] ?? 'N/A') . "</td>";
                                    echo "<td>" . htmlspecialchars($row['quantity']) . " " . htmlspecialchars($row['unit'] ?? '') . "</td>"; // Unit might be null
                                    echo "<td>" . number_format($row['cost_price'], 2) . "</td>";
                                    echo "<td>" . number_format($row['calculated_total_cost_price'], 2) . "</td>";
                                    echo "<td>" . number_format($row['selling_price'], 2) . "</td>"; 
                                    echo "<td>" . htmlspecialchars($row['min_stock_level'] ?? 'N/A') . "</td>";
                                    echo "<td><span class='badge {$status_class}'>" . $inventory_status_display . "</span></td>";
                                    echo "<td>" . date('Y-m-d H:i', strtotime($row['inventory_created_at'])) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['created_by_user_name'] ?? 'N/A') . "</td>";
                                    echo "<td>" . ($row['inventory_updated_at'] ? date('Y-m-d H:i', strtotime($row['inventory_updated_at'])) : 'N/A') . "</td>";
                                    echo "<td>" . htmlspecialchars($row['updated_by_user_name'] ?? 'N/A') . "</td>";
                                    echo "<td>";
                                    echo "<a href='edit_inventory.php?id=" . $row['inventory_id'] . "' class='btn btn-sm btn-info me-1' title='تعديل عنصر المخزون'><i class='fas fa-edit'></i></a>";
                                    echo "<a href='delete_inventory.php?id=" . $row['inventory_id'] . "' class='btn btn-sm btn-danger' onclick=\"return confirm('هل أنت متأكد أنك تريد حذف هذا العنصر من المخزون بشكل دائم؟ هذا الإجراء لا يمكن التراجع عنه.');\" title='حذف عنصر المخزون'><i class='fas fa-trash'></i></a>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='14' class='text-center py-4 text-muted'><i class='fas fa-box-open me-2'></i> لا توجد منتجات لعرضها بناءً على الفلاتر المحددة.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-4">
                    <a href="../dashbord.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-right-to-bracket me-2"></i> العودة للصفحة الرئيسية
                    </a>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    </body>
</html>

<?php
// إغلاق الاتصال بقاعدة البيانات في نهاية الصفحة
if ($con) {
    $con->close();
}
?>