<?php
session_start();
include('../connect_DB.php'); // تأكد من المسار الصحيح لملف اتصال قاعدة البيانات

// --- Configuration Constants ---
define('ROLE_ADMIN', 1);
define('ROLE_PURCHASING_MANAGER', 5);
define('PO_STATUS_COMPLETED', 'completed');
define('PO_STATUS_RECEIVED', 'received');

// --- Authorization Check ---
if (!isset($_SESSION['user_id']) || (!in_array($_SESSION['role_id'], [ROLE_ADMIN, ROLE_PURCHASING_MANAGER]))) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'لا تملك الصلاحيات الكافية للوصول إلى هذه الصفحة.'];
    header("Location: /NEW_IBB/admin/login/login.php"); // أو صفحة خطأ الوصول
    exit();
}

// --- Message Handling ---
$message = '';
if (isset($_SESSION['message'])) {
    $msg_type = $_SESSION['message']['type'];
    $msg_text = $_SESSION['message']['text'];
    $message = "<div class='message $msg_type'>$msg_text</div>";
    unset($_SESSION['message']);
}

$purchase_order_id = $_GET['id'] ?? null;
$purchase_order = null;
$order_items = [];
$suppliers = []; // قائمة الموردين
$products = [];  // قائمة المنتجات

// --- 1. Validate Purchase Order ID ---
if (!$purchase_order_id || !is_numeric($purchase_order_id)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'معرف أمر الشراء غير صالح.'];
    header("Location: list_purchase_orders.php");
    exit();
}

// --- 2. Fetch Suppliers ---
try {
    $stmt_suppliers = $con->prepare("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name");
    if ($stmt_suppliers === false) {
        throw new Exception("خطأ في إعداد استعلام جلب الموردين: " . $con->error);
    }
    $stmt_suppliers->execute();
    $result_suppliers = $stmt_suppliers->get_result();
    while ($row = $result_suppliers->fetch_assoc()) {
        $suppliers[] = $row;
    }
    $stmt_suppliers->close();
} catch (Exception $e) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'فشل جلب الموردين: ' . $e->getMessage()];
    header("Location: list_purchase_orders.php"); // Redirect to list if suppliers can't be fetched
    exit();
}

// --- 3. Fetch Products ---
try {
    $stmt_products = $con->prepare("SELECT id, name FROM products WHERE status = 'active' ORDER BY name");
    if ($stmt_products === false) {
        throw new Exception("خطأ في إعداد استعلام جلب المنتجات: " . $con->error);
    }
    $stmt_products->execute();
    $result_products = $stmt_products->get_result();
    while ($row = $result_products->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt_products->close();
} catch (Exception $e) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'فشل جلب المنتجات: ' . $e->getMessage()];
    header("Location: list_purchase_orders.php"); // Redirect to list if products can't be fetched
    exit();
}

// --- 4. Fetch Current Purchase Order Details ---
$stmt_po = $con->prepare("
    SELECT po.id, po.order_date, po.status, po.notes AS order_notes, -- استخدم 'order_notes' لتمييزها
           s.name AS supplier_name, s.contact_phone AS supplier_phone, s.address, s.notes AS supplier_notes, s.contact_email AS supplier_email, -- استخدم 'supplier_notes' لتمييزها
           u.name AS created_by_user
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN user_tb u ON po.created_by_user_id = u.id
    WHERE po.id = ?
");

if ($stmt_po === false) {
    die("خطأ في إعداد استعلام جلب أمر الشراء: " . $con->error);
}
$stmt_po->bind_param("i", $purchase_order_id);
$stmt_po->execute();
$result_po = $stmt_po->get_result();
$purchase_order = $result_po->fetch_assoc();
$stmt_po->close();

// --- 5. Fetch Current Purchase Order Items ---
try {
    $stmt_items = $con->prepare("
        SELECT poi.id AS item_id, poi.product_id,
            poi.quantity_ordered,
            p.name AS product_name
        FROM purchase_order_items poi
        JOIN products p ON poi.product_id = p.id
        WHERE poi.purchase_order_id = ?
    ");
    if ($stmt_items === false) {
        throw new Exception("خطأ في إعداد استعلام جلب عناصر أمر الشراء: " . $con->error);
    }
    $stmt_items->bind_param("i", $purchase_order_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    while ($row = $result_items->fetch_assoc()) {
        $order_items[] = $row;
    }
    $stmt_items->close();
} catch (Exception $e) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'فشل جلب عناصر أمر الشراء: ' . $e->getMessage()];
    header("Location: list_purchase_orders.php");
    exit();
}

// --- Process Form Submission ---
if (isset($_POST['update_po'])) {
    // Basic server-side validation
    $supplier_id = filter_var($_POST['supplier_id'], FILTER_VALIDATE_INT);
    $order_date = $_POST['order_date'];
    $notes = trim($_POST['notes']);

    if (!$supplier_id || empty($order_date)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'يرجى ملء جميع الحقول المطلوبة (المورد، تاريخ الطلب).'];
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $purchase_order_id);
        exit();
    }

    $con->begin_transaction();
    try {
        // 1. Update main purchase order details
        $stmt_update_po = $con->prepare("UPDATE purchase_orders SET supplier_id = ?, order_date = ?, notes = ? WHERE id = ?");
        if ($stmt_update_po === false) {
            throw new Exception("خطأ في إعداد استعلام تحديث أمر الشراء الرئيسي: " . $con->error);
        }
        $stmt_update_po->bind_param("isss", $supplier_id, $order_date, $notes, $purchase_order_id);
        if (!$stmt_update_po->execute()) {
            throw new Exception("فشل تحديث أمر الشراء الرئيسي: " . $stmt_update_po->error);
        }
        $stmt_update_po->close();

        // 2. Process purchase order items (update, add, delete)
        $existing_item_ids = array_column($order_items, 'item_id'); // IDs of items currently in DB
        $posted_item_ids = []; // IDs of items received from the form

        if (isset($_POST['product_id']) && is_array($_POST['product_id'])) {
            foreach ($_POST['product_id'] as $key => $product_id) {
                $item_id = $_POST['item_id'][$key];
                $product_size_id = empty($_POST['product_size_id'][$key]) ? NULL : filter_var($_POST['product_size_id'][$key], FILTER_VALIDATE_INT);
                $quantity_ordered = filter_var($_POST['quantity_ordered'][$key], FILTER_VALIDATE_INT);
                $unit_cost = filter_var($_POST['unit_cost'][$key], FILTER_VALIDATE_FLOAT);

                // Server-side validation for item quantities/costs
                if ($quantity_ordered === false || $quantity_ordered <= 0 || $unit_cost === false || $unit_cost <= 0) {
                    // Skip invalid items or throw an error
                    throw new Exception("خطأ: كمية أو تكلفة وحدة غير صالحة لأحد المنتجات.");
                }

                if (!empty($item_id)) { // Existing item (update)
                    $posted_item_ids[] = (int)$item_id; // Cast to int to ensure type consistency for array_diff
                    $stmt_update_item = $con->prepare("UPDATE purchase_order_items SET product_id = ?, product_size_id = ?, quantity_ordered = ?, unit_cost = ? WHERE id = ? AND purchase_order_id = ?");
                    if ($stmt_update_item === false) {
                        throw new Exception("خطأ في إعداد استعلام تحديث عنصر: " . $con->error);
                    }
                    $stmt_update_item->bind_param("iiidii", $product_id, $product_size_id, $quantity_ordered, $unit_cost, $item_id, $purchase_order_id);
                    if (!$stmt_update_item->execute()) {
                        throw new Exception("فشل تحديث عنصر أمر الشراء: " . $stmt_update_item->error);
                    }
                    $stmt_update_item->close();
                } else { // New item (add)
                    $stmt_insert_item = $con->prepare("INSERT INTO purchase_order_items (purchase_order_id, product_id, product_size_id, quantity_ordered, unit_cost) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt_insert_item === false) {
                        throw new Exception("خطأ في إعداد استعلام إضافة عنصر جديد: " . $con->error);
                    }
                    $stmt_insert_item->bind_param("iiidi", $purchase_order_id, $product_id, $product_size_id, $quantity_ordered, $unit_cost);
                    if (!$stmt_insert_item->execute()) {
                        throw new Exception("فشل إضافة عنصر أمر الشراء جديد: " . $stmt_insert_item->error);
                    }
                    $stmt_insert_item->close();
                }
            }
        }

        // 3. Delete items not present in the submitted form
        $items_to_delete = array_diff($existing_item_ids, $posted_item_ids);
        if (!empty($items_to_delete)) {
            $placeholders = implode(',', array_fill(0, count($items_to_delete), '?'));
            $stmt_delete_items = $con->prepare("DELETE FROM purchase_order_items WHERE id IN ($placeholders) AND purchase_order_id = ?");
            if ($stmt_delete_items === false) {
                throw new Exception("خطأ في إعداد استعلام حذف العناصر: " . $con->error);
            }
            $types = str_repeat('i', count($items_to_delete)) . 'i'; // All item IDs are integers, plus the purchase_order_id
            $bind_params = array_merge($items_to_delete, [$purchase_order_id]);
            $stmt_delete_items->bind_param($types, ...$bind_params);
            if (!$stmt_delete_items->execute()) {
                throw new Exception("فشل حذف عناصر أمر الشراء: " . $stmt_delete_items->error);
            }
            $stmt_delete_items->close();
        }

        // 4. Recalculate and update the total amount for the purchase order
        $stmt_recalculate_total = $con->prepare("SELECT SUM(quantity_ordered * unit_cost) AS new_total FROM purchase_order_items WHERE purchase_order_id = ?");
        if ($stmt_recalculate_total === false) {
            throw new Exception("خطأ في إعداد استعلام إعادة حساب الإجمالي: " . $con->error);
        }
        $stmt_recalculate_total->bind_param("i", $purchase_order_id);
        $stmt_recalculate_total->execute();
        $result_recalculate = $stmt_recalculate_total->get_result()->fetch_assoc();
        $new_total_amount = $result_recalculate['new_total'] ?? 0.00;
        $stmt_recalculate_total->close();

        $stmt_update_total = $con->prepare("UPDATE purchase_orders SET total_amount = ? WHERE id = ?");
        if ($stmt_update_total === false) {
            throw new Exception("خطأ في إعداد استعلام تحديث الإجمالي: " . $con->error);
        }
        $stmt_update_total->bind_param("di", $new_total_amount, $purchase_order_id);
        if (!$stmt_update_total->execute()) {
            throw new Exception("فشل تحديث الإجمالي الكلي لأمر الشراء: " . $stmt_update_total->error);
        }
        $stmt_update_total->close();

        $con->commit(); // Commit the transaction
        $_SESSION['message'] = ['type' => 'success', 'text' => 'تم تعديل أمر الشراء بنجاح.'];
        header("Location: view_purchase_order.php?id=" . $purchase_order_id);
        exit();

    } catch (Exception $e) {
        $con->rollback(); // Rollback on error
        $_SESSION['message'] = ['type' => 'error', 'text' => 'فشل تعديل أمر الشراء: ' . $e->getMessage()];
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $purchase_order_id); // Return to the same page
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل أمر شراء #<?php echo htmlspecialchars($purchase_order['id']); ?></title>
    <link rel="stylesheet" href="style.css"> </head>
<body>
    <div class="container">
        <h1>تعديل أمر الشراء #<?php echo htmlspecialchars($purchase_order['id']); ?></h1>

        <?php echo $message; ?>

        <form method="POST" action="">
            <input type="hidden" name="purchase_order_id" value="<?php echo htmlspecialchars($purchase_order['id']); ?>">
            <div class="form-section">
                <h2>تفاصيل أمر الشراء</h2>
                <div class="form-group">
                    <label for="supplier_id">المورد:</label>
                    <select id="supplier_id" name="supplier_id" required>
                        <option value="">اختر المورد</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo htmlspecialchars($supplier['id']); ?>"
                                <?php echo ($supplier['id'] == $purchase_order['supplier_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="order_date">تاريخ الطلب:</label>
                    <input type="date" id="order_date" name="order_date" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($purchase_order['order_date']))); ?>" required>
                </div>
                <div class="form-group">
                    <label for="notes">ملاحظات:</label>
                    <textarea id="notes" name="notes"><?php echo htmlspecialchars($purchase_order['notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h2>عناصر أمر الشراء</h2>
                <div id="order-items-container">
                    <?php if (!empty($order_items)): ?>
                        <?php foreach ($order_items as $index => $item): ?>
                            <div class="item-row">
                                <input type="hidden" name="item_id[]" value="<?php echo htmlspecialchars($item['item_id']); ?>">
                                <select name="product_id[]" class="product-select" required onchange="loadProductSizes(this, '<?php echo $index; ?>')">
                                    <option value="">اختر المنتج</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo htmlspecialchars($product['id']); ?>"
                                            <?php echo ($product['id'] == $item['product_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="product_size_id[]" class="product-size-select" id="product_size_<?php echo $index; ?>">
                                    <option value="">لا يوجد حجم</option>
                                    <?php
                                        // Load existing product sizes for the currently selected product
                                        if (!empty($item['product_id'])) {
                                            $stmt_sizes = $con->prepare("SELECT id, size FROM product_sizes WHERE product_id = ? ORDER BY size");
                                            if ($stmt_sizes === false) {
                                                error_log("Failed to prepare product size statement: " . $con->error);
                                            } else {
                                                $stmt_sizes->bind_param("i", $item['product_id']);
                                                $stmt_sizes->execute();
                                                $result_sizes = $stmt_sizes->get_result();
                                                while ($size_row = $result_sizes->fetch_assoc()) {
                                                    $selected = ($size_row['id'] == $item['product_size_id']) ? 'selected' : '';
                                                    echo "<option value='" . htmlspecialchars($size_row['id']) . "' $selected>" . htmlspecialchars($size_row['size']) . "</option>";
                                                }
                                                $stmt_sizes->close();
                                            }
                                        }
                                    ?>
                                </select>
                                <input type="number" name="quantity_ordered[]" placeholder="الكمية" min="1" value="<?php echo htmlspecialchars($item['quantity_ordered']); ?>" required>
                                <button type="button" class="remove-item">حذف</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="add-item-button">إضافة منتج</button>
            </div>

            <div class="action-buttons">
                <input type="submit" name="update_po" value="حفظ التعديلات">
                <button type="button" class="cancel-button" onclick="window.location.href='view_purchase_order.php?id=<?php echo htmlspecialchars($purchase_order['id']); ?>';">إلغاء</button>
            </div>
        </form>
    </div>

    <script>
        // Function to fetch product sizes based on product ID
        function loadProductSizes(selectElement, index) {
            const productId = selectElement.value;
            const sizeSelectElement = document.getElementById('product_size_' + index);
            sizeSelectElement.innerHTML = '<option value="">لا يوجد حجم</option>'; // Reset options

            if (productId) {
                fetch('get_product_sizes.php?product_id=' + productId)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        data.forEach(size => {
                            const option = document.createElement('option');
                            option.value = size.id;
                            option.textContent = size.size;
                            sizeSelectElement.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error fetching product sizes:', error));
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            let itemCounter = <?php echo count($order_items); ?>; // To ensure unique IDs for new elements

            // Add new item row
            document.querySelector('.add-item-button').addEventListener('click', function() {
                const container = document.getElementById('order-items-container');
                const newItemRow = document.createElement('div');
                newItemRow.classList.add('item-row');
                newItemRow.innerHTML = `
                    <input type="hidden" name="item_id[]" value="">
                    <select name="product_id[]" class="product-select" required onchange="loadProductSizes(this, '${itemCounter}')">
                        <option value="">اختر المنتج</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo htmlspecialchars($product['id']); ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="product_size_id[]" class="product-size-select" id="product_size_${itemCounter}">
                        <option value="">لا يوجد حجم</option>
                    </select>
                    <input type="number" name="quantity_ordered[]" placeholder="الكمية" min="1" required>
                    <input type="number" name="unit_cost[]" placeholder="تكلفة الوحدة" step="0.01" min="0.01" required>
                    <button type="button" class="remove-item">حذف</button>
                `;
                container.appendChild(newItemRow);
                itemCounter++;
            });

            // Remove item row using event delegation
            document.getElementById('order-items-container').addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-item')) {
                    if (confirm('هل أنت متأكد أنك تريد حذف هذا المنتج من أمر الشراء؟')) {
                        e.target.closest('.item-row').remove();
                    }
                }
            });
        });
    </script>
</body>
</html>