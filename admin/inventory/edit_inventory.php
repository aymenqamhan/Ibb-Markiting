<?php
// edit_inventory.php
session_start(); // تأكد أن الجلسة بدأت لجلب user_id
include('../connect_DB.php'); // المسار الصحيح حسب هيكلة مجلداتك

$inventory_id = null;
$inventory_data = null;
$products = []; // لتخزين قائمة المنتجات المتاحة
$sizes = [];    // لتخزين قائمة الأحجام المتاحة

// --- IMPORTANT: Get actual session user ID ---
$current_user_id = $_SESSION['user_id'] ?? null;
if ($current_user_id === null) {
    // هذا كود احتياطي، في بيئة الإنتاج يجب أن تعيد التوجيه لصفحة تسجيل الدخول إذا لم يكن هناك user_id
    // $_SESSION['error_message'] = "ليس لديك صلاحية الوصول إلى هذه الصفحة.";
    // header("Location: /NEW_IBB/login.php"); 
    // exit();
    $current_user_id = 1; // Fallback for development: REPLACE THIS IN PRODUCTION!
}
// --- END IMPORTANT ---

// رسائل التنبيه
$success_message = '';
$error_message = '';

// جلب ID صنف المخزون من الـ URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $inventory_id = $_GET['id'];

    // جلب بيانات صنف المخزون المحدد مع اسم المستخدم الذي أنشأه وآخر من قام بالتحديث
    $stmt = $con->prepare("SELECT inv.*, p.name AS product_name, ps.size AS size_name,
                                 u_created.name AS created_by_user_name,
                                 u_updated.name AS updated_by_user_name
                           FROM inventory inv
                           LEFT JOIN products p ON inv.product_id = p.id
                           LEFT JOIN product_sizes ps ON inv.size_id = ps.id
                           LEFT JOIN user_tb u_created ON inv.created_by_user_id = u_created.id
                           LEFT JOIN user_tb u_updated ON inv.updated_by_user_id = u_updated.id
                           WHERE inv.id = ?");
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $inventory_data = $result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = "صنف المخزون غير موجود.";
        header("Location: list_inventory.php"); // تأكد من وجود هذه الصفحة
        exit();
    }
    $stmt->close();
} else {
    $_SESSION['error_message'] = "معرف صنف المخزون غير صالح.";
    header("Location: list_inventory.php"); // تأكد من وجود هذه الصفحة
    exit();
}

// جلب قائمة المنتجات لاختيارها في النموذج
$result_products = $con->query("SELECT id, name FROM products ORDER BY name ASC");
if ($result_products) {
    while($row = $result_products->fetch_assoc()) {
        $products[] = $row;
    }
}

// جلب قائمة الأحجام لاختيارها في النموذج مع الترتيب المحدد
// ترتيب الأحجام: X, M, L, XL, S (إذا كانت S موجودة)
$result_sizes = $con->query("SELECT id, size FROM product_sizes ORDER BY FIELD(size, 'X', 'M', 'L', 'XL', 'S'), size ASC");
if ($result_sizes) {
    while($row = $result_sizes->fetch_assoc()) {
        $sizes[] = $row;
    }
}

// معالجة إرسال النموذج (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // No need to validate product_id, size_id, sku, barcode, quantity, cost_price, is_serial_tracked
    // as they are readonly/disabled in the form
    // Only selling_price, unit, and min_stock_level are editable.

    $selling_price = floatval($_POST['selling_price']); 
    $unit = htmlspecialchars($_POST['unit']);
    $min_stock_level = intval($_POST['min_stock_level']); 

    // Retrieve original product_id, size_id, sku, barcode, quantity, cost_price, is_serial_tracked
    // directly from $inventory_data as they are not editable in the form
    $product_id = $inventory_data['product_id'];
    $size_id = $inventory_data['size_id'];
    $sku = $inventory_data['sku'];
    $barcode = $inventory_data['barcode'];
    $quantity = $inventory_data['quantity'];
    $cost_price = $inventory_data['cost_price'];
    $is_serial_tracked = $inventory_data['is_serial_tracked'];

    $created_by_user_id_fixed = $inventory_data['created_by_user_id']; // Retain original creator
    $updated_by_user_id = $current_user_id; // Set the updater

    $stmt = $con->prepare("UPDATE inventory SET
                                 selling_price = ?,
                                 unit = ?,
                                 min_stock_level = ?, 
                                 updated_by_user_id = ?,
                                 updated_at = CURRENT_TIMESTAMP
                                 WHERE id = ?");

    if (!$stmt) {
        $error_message = "خطأ في تهيئة استعلام التحديث: " . $con->error;
    } else {
        // Updated bind_param types: dsiii (double, string, int, int, int)
        $stmt->bind_param("dsiii", 
                          $selling_price, $unit, $min_stock_level, 
                          $updated_by_user_id, $inventory_id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "تم تحديث صنف المخزون بنجاح!";
            // Re-fetch data to reflect updated values and the 'updated by' user
            header("Location: edit_inventory.php?id={$inventory_id}");
            exit();
        } else {
            $error_message = "خطأ في تحديث صنف المخزون: " . $stmt->error;
        }
        $stmt->close();
    }
}

// رسائل النجاح أو الخطأ من الجلسة بعد إعادة التوجيه
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// حساب سعر البيع الافتراضي لعرضه في النموذج (PHP) إذا لم يكن موجوداً
$default_selling_price_calc = $inventory_data['cost_price'] * 1.30; // حساب هامش 30%
$initial_selling_price_display = htmlspecialchars(number_format($inventory_data['selling_price'] ?? $default_selling_price_calc, 2));

// Prepare 'updated_by' user name for display
$updated_by_display_name = $inventory_data['updated_by_user_name'] ?? 'لم يتم التحديث بعد';

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل صنف المخزون</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="./inventory_styles.css"> </head>
<body>

    <div class="page-header">
        <h1><i class="fas fa-cubes"></i> تعديل صنف المخزون</h1>
        <a href="list_inventory.php" class="btn">
            <i class="fas fa-arrow-left"></i> العودة للمخزون
        </a>
    </div>

    <div class="container-fluid py-4">
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-lg">
            <div class="card-header">
                <h5 class="mb-0 text-white">تفاصيل صنف المخزون #<?php echo htmlspecialchars($inventory_id); ?></h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="product_id" class="form-label">المنتج</label>
                            <select class="form-select" id="product_id" name="product_id" required readonly style="pointer-events: none; background-color: #e9ecef;">
                                <option value="">اختر منتج...</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo htmlspecialchars($product['id']); ?>"
                                        <?php echo ($inventory_data['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="size_id" class="form-label">الحجم</label>
                            <select class="form-select" id="size_id" name="size_id" readonly style="pointer-events: none; background-color: #e9ecef;">
                                <option value="">لا يوجد حجم</option>
                                <?php foreach ($sizes as $size): ?>
                                    <option value="<?php echo htmlspecialchars($size['id']); ?>"
                                        <?php echo ($inventory_data['size_id'] == $size['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($size['size']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="sku" class="form-label">SKU</label>
                            <input type="text" class="form-control" id="sku" name="sku" value="<?php echo htmlspecialchars($inventory_data['sku']); ?>" required readonly style="background-color: #e9ecef;">
                        </div>
                        <div class="col-md-6">
                            <label for="barcode" class="form-label">الباركود</label>
                            <input type="text" class="form-control" id="barcode" name="barcode" value="<?php echo htmlspecialchars($inventory_data['barcode']); ?>" readonly style="background-color: #e9ecef;">
                        </div>
                        <div class="col-md-6">
                            <label for="quantity" class="form-label">الكمية</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="<?php echo htmlspecialchars($inventory_data['quantity']); ?>" required min="0" readonly style="background-color: #e9ecef;">
                        </div>
                        <div class="col-md-6">
                            <label for="cost_price" class="form-label">سعر الوحدة (التكلفة)</label>
                            <input type="number" step="0.01" class="form-control" id="cost_price" name="cost_price" value="<?php echo htmlspecialchars($inventory_data['cost_price']); ?>" required min="0" readonly style="background-color: #e9ecef;">
                        </div>
                        <div class="col-md-6">
                            <label for="total_cost_price_display" class="form-label">إجمالي سعر التكلفة</label>
                            <input type="text" class="form-control" id="total_cost_price_display" value="<?php echo htmlspecialchars(number_format($inventory_data['cost_price'] * $inventory_data['quantity'], 2)); ?>" readonly style="background-color: #e9ecef;">
                        </div>
                        <div class="col-md-6">
                            <label for="selling_price" class="form-label">سعر البيع</label>
                            <input type="number" step="0.01" class="form-control" id="selling_price" name="selling_price"
                                   value="<?php echo $initial_selling_price_display; ?>" required min="0">
                        </div>
                        <div class="col-md-6">
                            <label for="unit" class="form-label">الوحدة</label>
                            <input type="text" class="form-control" id="unit" name="unit" value="<?php echo htmlspecialchars($inventory_data['unit']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="min_stock_level" class="form-label">الحد الأدنى للمخزون</label>
                            <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" value="<?php echo htmlspecialchars($inventory_data['min_stock_level'] ?? 0); ?>" required min="0">
                        </div>
                        <div class="col-md-6">
                            <label for="created_by_user_name" class="form-label">تم الإنشاء بواسطة</label>
                            <input type="text" class="form-control" id="created_by_user_name" value="<?php echo htmlspecialchars($inventory_data['created_by_user_name'] ?? 'غير معروف'); ?>" readonly style="background-color: #e9ecef;">
                        </div>
                        <div class="col-md-6">
                            <label for="updated_by_user_name_display" class="form-label">من قام بالتحديث</label>
                            <input type="text" class="form-control" id="updated_by_user_name_display" value="<?php echo htmlspecialchars($updated_by_display_name); ?>" readonly style="background-color: #e9ecef;">
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="is_serial_tracked" name="is_serial_tracked" value="1"
                                    <?php echo ($inventory_data['is_serial_tracked'] == 1) ? 'checked' : ''; ?> disabled style="pointer-events: none;">
                                <label class="form-check-label" for="is_serial_tracked">تتبع بالأرقام التسلسلية</label>
                            </div>
                        </div>

                        <div class="col-12 text-end mt-4">
                            <button type="submit" class="btn btn-primary btn-lg me-2">
                                <i class="fas fa-save me-2"></i> حفظ التعديلات
                            </button>
                            <a href="list_inventory.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-times-circle me-2"></i> إلغاء
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="text-center mt-4">
            <a href="../dashbord.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-arrow-left me-2"></i> العودة للوحة التحكم
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInput = document.getElementById('quantity');
            const costPriceInput = document.getElementById('cost_price');
            const sellingPriceInput = document.getElementById('selling_price');
            const totalCostPriceDisplay = document.getElementById('total_cost_price_display');

            function calculateAndDisplayPrices() {
                const quantity = parseFloat(quantityInput.value) || 0;
                const costPrice = parseFloat(costPriceInput.value) || 0;

                // حساب إجمالي سعر التكلفة
                const totalCostPrice = costPrice * quantity;
                totalCostPriceDisplay.value = totalCostPrice.toFixed(2);
            }

            // Listen for changes on cost_price (though it's readonly now) and quantity
            // This ensures total cost is updated if quantity/cost_price were somehow changed via dev tools
            quantityInput.addEventListener('input', calculateAndDisplayPrices);
            costPriceInput.addEventListener('input', calculateAndDisplayPrices);
            
            // Initial calculation on page load
            calculateAndDisplayPrices();
        });
    </script>
</body>
</html>
<?php
// Close the connection only if it's still open
if (isset($con) && $con->ping()) {
    $con->close();
}
?>