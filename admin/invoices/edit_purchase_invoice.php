<?php
session_start();
include('../connect_DB.php');

$invoice_id = $_GET['id'] ?? 0;

if ($invoice_id <= 0) {
    $_SESSION['error_message'] = "لم يتم تحديد فاتورة لتعديلها.";
    header("Location: list_purchase_invoices.php");
    exit();
}

$invoice_details = null;
$invoice_items = [];
$suppliers = [];
$products = [];

$sql_suppliers = "SELECT id, name FROM suppliers ORDER BY name ASC";
$result_suppliers = $con->query($sql_suppliers);
while ($row = $result_suppliers->fetch_assoc()) {
    $suppliers[] = $row;
}

$sql_products = "SELECT id, name FROM products ORDER BY name ASC";
$result_products = $con->query($sql_products);
while ($row = $result_products->fetch_assoc()) {
    $products[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_number = $_POST['invoice_number'];
    $invoice_date = $_POST['invoice_date'];
    $supplier_id = $_POST['supplier_id'];
    $purchase_order_id = (isset($_POST['purchase_order_id']) && filter_var($_POST['purchase_order_id'], FILTER_VALIDATE_INT) !== false && (int)$_POST['purchase_order_id'] > 0) ? (int)$_POST['purchase_order_id'] : null;
    $status = $_POST['status'];
    $notes = $_POST['notes'];
    
    $current_user_id = $_SESSION['user_id'] ?? null;
    if (!$current_user_id) {
        $_SESSION['error_message'] = "يجب تسجيل الدخول لتعديل الفواتير.";
        header("Location: login.php");
        exit();
    }

    $item_ids = $_POST['item_id'] ?? [];
    $product_ids = $_POST['product_id'] ?? [];
    $quantities_received = $_POST['quantity_received'] ?? [];
    $unit_costs = $_POST['unit_cost'] ?? [];

    $total_amount_calculated = 0;

    $con->begin_transaction();

    try {
        $sql_update_invoice = "UPDATE purchase_invoices SET
                                 invoice_number = ?,
                                 invoice_date = ?,
                                 supplier_id = ?,
                                 purchase_order_id = ?,
                                 status = ?,
                                 notes = ?,
                                 updated_at = NOW(),
                                 updated_by_user_id = ?
                                 WHERE id = ?";

        $stmt_update_invoice = $con->prepare($sql_update_invoice);
        if (!$stmt_update_invoice) {
            throw new Exception("خطأ في تحضير استعلام تحديث الفاتورة: " . $con->error);
        }
        
        $stmt_update_invoice->bind_param("ssiissii",
            $invoice_number, $invoice_date, $supplier_id, $purchase_order_id, $status, $notes, $current_user_id, $invoice_id
        );
        $stmt_update_invoice->execute();

        $sql_existing_items = "SELECT id FROM purchase_invoice_items WHERE invoice_id = ?";
        $stmt_existing_items = $con->prepare($sql_existing_items);
        if (!$stmt_existing_items) {
            throw new Exception("خطأ في تحضير استعلام جلب العناصر الموجودة: " . $con->error);
        }
        $stmt_existing_items->bind_param("i", $invoice_id);
        $stmt_existing_items->execute();
        $result_existing_items = $stmt_existing_items->get_result();
        $existing_item_ids = [];
        while ($row = $result_existing_items->fetch_assoc()) {
            $existing_item_ids[] = $row['id'];
        }
        $stmt_existing_items->close();

        $items_to_delete = array_diff($existing_item_ids, $item_ids);

        if (!empty($items_to_delete)) {
            $placeholders = implode(',', array_fill(0, count($items_to_delete), '?'));
            $sql_delete_items = "DELETE FROM purchase_invoice_items WHERE id IN ($placeholders)";
            $stmt_delete_items = $con->prepare($sql_delete_items);
            if (!$stmt_delete_items) {
                throw new Exception("خطأ في تحضير استعلام حذف العناصر: " . $con->error);
            }
            $types = str_repeat('i', count($items_to_delete));
            $stmt_delete_items->bind_param($types, ...$items_to_delete);
            $stmt_delete_items->execute();
        }

        for ($i = 0; $i < count($product_ids); $i++) {
            $current_item_id = $item_ids[$i] ?? 0;
            $current_product_id = $product_ids[$i];
            $current_quantity_received = (int)$quantities_received[$i];
            $current_unit_cost = (float)$unit_costs[$i];
            $current_item_total = $current_quantity_received * $current_unit_cost;

            if ($current_product_id <= 0 || $current_quantity_received <= 0 || $current_unit_cost < 0) {
                continue;
            }

            $total_amount_calculated += $current_item_total;

            if ($current_item_id > 0) {
                $sql_update_item = "UPDATE purchase_invoice_items SET
                                     product_id = ?,
                                     quantity_received = ?,
                                     unit_cost = ?,
                                     item_total = ?
                                     WHERE id = ? AND invoice_id = ?";
                $stmt_update_item = $con->prepare($sql_update_item);
                if (!$stmt_update_item) {
                    throw new Exception("خطأ في تحضير استعلام تحديث البند: " . $con->error);
                }
                $stmt_update_item->bind_param("iiddii",
                    $current_product_id, $current_quantity_received, $current_unit_cost, $current_item_total,
                    $current_item_id, $invoice_id
                );
                $stmt_update_item->execute();
            } else {
                $sql_insert_item = "INSERT INTO purchase_invoice_items (invoice_id, product_id, quantity_received, unit_cost, item_total)
                                     VALUES (?, ?, ?, ?, ?)";
                $stmt_insert_item = $con->prepare($sql_insert_item);
                if (!$stmt_insert_item) {
                    throw new Exception("خطأ في تحضير استعلام إضافة البند: " . $con->error);
                }
                $stmt_insert_item->bind_param("iiidd",
                    $invoice_id, $current_product_id, $current_quantity_received, $current_unit_cost, $current_item_total
                );
                $stmt_insert_item->execute();
            }
        }

        $sql_update_total = "UPDATE purchase_invoices SET total_amount = ? WHERE id = ?";
        $stmt_update_total = $con->prepare($sql_update_total);
        if (!$stmt_update_total) {
            throw new Exception("خطأ في تحضير استعلام تحديث الإجمالي: " . $con->error);
        }
        $stmt_update_total->bind_param("di", $total_amount_calculated, $invoice_id);
        $stmt_update_total->execute();

        $con->commit();
        $_SESSION['success_message'] = "تم تحديث فاتورة المشتريات بنجاح.";
        header("Location: view_purchase_invoice.php?id=" . $invoice_id);
        exit();

    } catch (Exception $e) {
        $con->rollback();
        $_SESSION['error_message'] = "خطأ في تحديث الفاتورة: " . $e->getMessage();
    }
}

$sql_invoice = "SELECT
                    inv.id AS invoice_id,
                    inv.invoice_number,
                    inv.invoice_date,
                    inv.total_amount,
                    inv.purchase_order_id,
                    inv.status AS invoice_status,
                    inv.notes,
                    inv.supplier_id,
                    inv.created_at,
                    inv.created_by_user_id,
                    inv.updated_at,
                    inv.updated_by_user_id,
                    s.name AS supplier_name,
                    u_created.name AS created_by_user_name,
                    u_updated.name AS updated_by_user_name
                FROM
                    purchase_invoices inv
                LEFT JOIN
                    suppliers s ON inv.supplier_id = s.id
                LEFT JOIN
                    user_tb u_created ON inv.created_by_user_id = u_created.id
                LEFT JOIN
                    user_tb u_updated ON inv.updated_by_user_id = u_updated.id
                WHERE inv.id = ?";

$stmt_invoice = $con->prepare($sql_invoice);
if ($stmt_invoice) {
    $stmt_invoice->bind_param("i", $invoice_id);
    $stmt_invoice->execute();
    $result_invoice = $stmt_invoice->get_result();
    $invoice_details = $result_invoice->fetch_assoc();
    $stmt_invoice->close();
} else {
    $_SESSION['error_message'] = "خطأ في تحضير استعلام الفاتورة: " . $con->error;
    header("Location: list_purchase_invoices.php");
    exit();
}

if (!$invoice_details) {
    $_SESSION['error_message'] = "الفاتورة المطلوبة غير موجودة.";
    header("Location: list_purchase_invoices.php");
    exit();
}

$sql_items = "SELECT
                    item.id AS item_id,
                    item.product_id,
                    item.quantity_received,
                    item.unit_cost,
                    item.item_total,
                    p.name AS product_name
                  FROM
                    purchase_invoice_items item
                  LEFT JOIN
                    products p ON item.product_id = p.id
                  WHERE item.invoice_id = ?";

$stmt_items = $con->prepare($sql_items);
if ($stmt_items) {
    $stmt_items->bind_param("i", $invoice_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $invoice_items = [];
    while ($row = $result_items->fetch_assoc()) {
        $invoice_items[] = $row;
    }
    $stmt_items->close();
} else {
    $_SESSION['error_message'] = "خطأ في تحضير استعلام عناصر الفاتورة: " . $con->error;
    header("Location: list_purchase_invoices.php");
    exit();
}

function getStatusClass($status) {
    switch ($status) {
        case 'paid': return 'text-success';
        case 'pending': return 'text-warning';
        case 'received': return 'text-primary';
        case 'cancelled': return 'text-danger';
        default: return '';
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل فاتورة المشتريات: <?php echo htmlspecialchars($invoice_details['invoice_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container-fluid { padding: 20px; }
        .card { margin-bottom: 20px; box-shadow: 0 0 15px rgba(0,0,0,0.05); border: none; border-radius: 8px; }
        .card-header { background-color: #007bff; color: white; font-weight: bold; border-radius: 8px 8px 0 0; padding: 15px; }
        .form-label { font-weight: bold; }
        .item-row { display: flex; align-items: flex-end; margin-bottom: 15px; border: 1px solid #eee; padding: 10px; border-radius: 5px; background-color: #fcfcfc; }
        .item-row .form-control, .item-row .form-select { margin-left: 10px; }
        .item-actions { margin-left: auto; }
        .remove-item-btn { margin-left: 5px; }
        .total-amount-display {
            font-size: 1.5em;
            font-weight: bold;
            color: #007bff;
            background-color: #e9f5ff;
            padding: 10px 15px;
            border-radius: 5px;
            text-align: right;
        }
    </style>
</head>
<body>

    <div class="container-fluid">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                تعديل فاتورة المشتريات: #<?php echo htmlspecialchars($invoice_details['invoice_number']); ?>
                <a href="view_purchase_invoice.php?id=<?php echo htmlspecialchars($invoice_id); ?>" class="btn btn-secondary btn-sm float-end">
                    <i class="fas fa-arrow-left"></i> العودة لتفاصيل الفاتورة
                </a>
            </div>
            <div class="card-body">
                <form action="edit_purchase_invoice.php?id=<?php echo htmlspecialchars($invoice_id); ?>" method="POST">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label for="invoice_number" class="form-label">رقم الفاتورة</label>
                            <input type="text" class="form-control" id="invoice_number" name="invoice_number" value="<?php echo htmlspecialchars($invoice_details['invoice_number']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="invoice_date" class="form-label">تاريخ الفاتورة</label>
                            <input type="datetime-local" class="form-control" id="invoice_date" name="invoice_date" value="<?php echo date('Y-m-d\TH:i', strtotime($invoice_details['invoice_date'])); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="supplier_id" class="form-label">المورد</label>
                            <select class="form-select" id="supplier_id" name="supplier_id" required>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo htmlspecialchars($supplier['id']); ?>"
                                        <?php echo ($supplier['id'] == $invoice_details['supplier_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($supplier['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label for="purchase_order_id" class="form-label">رقم أمر الشراء</label>
                            <input type="text" class="form-control" id="purchase_order_id" name="purchase_order_id" value="<?php echo htmlspecialchars($invoice_details['purchase_order_id'] ?? ''); ?>" placeholder="اختياري">
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label">حالة الفاتورة</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" <?php echo ($invoice_details['invoice_status'] == 'pending') ? 'selected' : ''; ?>>معلقة</option>
                                <option value="received" <?php echo ($invoice_details['invoice_status'] == 'received') ? 'selected' : ''; ?>>تم الاستلام</option>
                                <option value="paid" <?php echo ($invoice_details['invoice_status'] == 'paid') ? 'selected' : ''; ?>>مدفوعة</option>
                                <option value="cancelled" <?php echo ($invoice_details['invoice_status'] == 'cancelled') ? 'selected' : ''; ?>>ملغاة</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="notes" class="form-label">ملاحظات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($invoice_details['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3">عناصر الفاتورة</h5>
                    <div id="invoiceItemsContainer">
                        <?php if (!empty($invoice_items)): ?>
                            <?php foreach ($invoice_items as $index => $item): ?>
                                <div class="item-row" data-item-id="<?php echo htmlspecialchars($item['item_id']); ?>">
                                    <input type="hidden" name="item_id[]" value="<?php echo htmlspecialchars($item['item_id']); ?>">
                                    <div class="flex-grow-1">
                                        <label for="product_id_<?php echo $index; ?>" class="form-label d-block">المنتج</label>
                                        <select class="form-select product-select" id="product_id_<?php echo $index; ?>" name="product_id[]" required>
                                            <option value="">اختر منتج...</option>
                                            <?php foreach ($products as $product): ?>
                                                <option value="<?php echo htmlspecialchars($product['id']); ?>"
                                                    <?php echo ($product['id'] == $item['product_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="quantity_received_<?php echo $index; ?>" class="form-label">الكمية المستلمة</label>
                                        <input type="number" class="form-control item-quantity" id="quantity_received_<?php echo $index; ?>" name="quantity_received[]" value="<?php echo htmlspecialchars($item['quantity_received']); ?>" min="1" required>
                                    </div>
                                    <div>
                                        <label for="unit_cost_<?php echo $index; ?>" class="form-label">تكلفة الوحدة</label>
                                        <input type="number" class="form-control item-cost" id="unit_cost_<?php echo $index; ?>" name="unit_cost[]" value="<?php echo htmlspecialchars($item['unit_cost']); ?>" step="0.01" min="0" required>
                                    </div>
                                    <div>
                                        <label for="item_total_<?php echo $index; ?>" class="form-label">إجمالي البند</label>
                                        <input type="text" class="form-control item-total-display" id="item_total_<?php echo $index; ?>" value="<?php echo number_format($item['item_total'], 2); ?>" readonly>
                                    </div>
                                    <div class="item-actions">
                                        <button type="button" class="btn btn-danger remove-item-btn"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-info btn-sm mt-3" id="addItemBtn"><i class="fas fa-plus"></i> إضافة بند جديد</button>

                    <hr class="my-4">

                    <div class="row mb-3">
                        <div class="col-md-6 offset-md-6">
                            <label for="total_amount_display_field" class="form-label">الإجمالي الكلي للفاتورة:</label>
                            <div class="total-amount-display">
                                <span id="total_amount_display_field"><?php echo number_format($invoice_details['total_amount'], 2); ?></span>
                            </div>
                            <input type="hidden" name="total_amount" id="total_amount_hidden" value="<?php echo htmlspecialchars($invoice_details['total_amount']); ?>">
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> حفظ التعديلات</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZdfFk2U5fdg/NgfABwwbYqFDhbuIf7gof+6mojHujR4WpRcPRRSLn5W5eH" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const invoiceItemsContainer = document.getElementById('invoiceItemsContainer');
            const addItemBtn = document.getElementById('addItemBtn');
            const totalAmountDisplay = document.getElementById('total_amount_display_field');
            const hiddenTotalAmount = document.getElementById('total_amount_hidden');

            let itemIndex = <?php echo count($invoice_items); ?>;

            const itemRowTemplate = `
                <div class="item-row">
                    <input type="hidden" name="item_id[]" value="0"> <div class="flex-grow-1">
                        <label class="form-label d-block">المنتج</label>
                        <select class="form-select product-select" name="product_id[]" required>
                            <option value="">اختر منتج...</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo htmlspecialchars($product['id']); ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">الكمية المستلمة</label>
                        <input type="number" class="form-control item-quantity" name="quantity_received[]" value="1" min="1" required>
                    </div>
                    <div>
                        <label class="form-label">تكلفة الوحدة</label>
                        <input type="number" class="form-control item-cost" name="unit_cost[]" value="0.00" step="0.01" min="0" required>
                    </div>
                    <div>
                        <label class="form-label">إجمالي البند</label>
                        <input type="text" class="form-control item-total-display" value="0.00" readonly>
                    </div>
                    <div class="item-actions">
                        <button type="button" class="btn btn-danger remove-item-btn"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            `;

            function calculateItemTotal(itemRow) {
                const quantity = parseFloat(itemRow.querySelector('.item-quantity').value);
                const cost = parseFloat(itemRow.querySelector('.item-cost').value);
                const itemTotalField = itemRow.querySelector('.item-total-display');

                if (!isNaN(quantity) && !isNaN(cost)) {
                    const total = quantity * cost;
                    itemTotalField.value = total.toFixed(2);
                } else {
                    itemTotalField.value = (0.00).toFixed(2);
                }
                calculateOverallTotal();
            }

            function calculateOverallTotal() {
                let overallTotal = 0;
                document.querySelectorAll('.item-row').forEach(row => {
                    const itemTotal = parseFloat(row.querySelector('.item-total-display').value);
                    if (!isNaN(itemTotal)) {
                        overallTotal += itemTotal;
                    }
                });
                totalAmountDisplay.textContent = overallTotal.toFixed(2);
                hiddenTotalAmount.value = overallTotal.toFixed(2);
            }

            document.querySelectorAll('.item-row').forEach(row => {
                row.querySelector('.item-quantity').addEventListener('input', () => calculateItemTotal(row));
                row.querySelector('.item-cost').addEventListener('input', () => calculateItemTotal(row));
                row.querySelector('.remove-item-btn').addEventListener('click', function() {
                    row.remove();
                    calculateOverallTotal();
                });
                calculateItemTotal(row);
            });

            addItemBtn.addEventListener('click', function() {
                const newRow = document.createElement('div');
                newRow.innerHTML = itemRowTemplate;
                newRow.className = 'item-row';
                invoiceItemsContainer.appendChild(newRow);

                const newItemQuantity = newRow.querySelector('.item-quantity');
                const newItemCost = newRow.querySelector('.item-cost');
                const newRemoveBtn = newRow.querySelector('.remove-item-btn');

                newItemQuantity.addEventListener('input', () => calculateItemTotal(newRow));
                newItemCost.addEventListener('input', () => calculateItemTotal(newRow));
                newRemoveBtn.addEventListener('click', function() {
                    newRow.remove();
                    calculateOverallTotal();
                });

                itemIndex++;
                calculateOverallTotal();
            });

            calculateOverallTotal();
        });
    </script>
</body>
</html>
<?php
$con->close();
?>