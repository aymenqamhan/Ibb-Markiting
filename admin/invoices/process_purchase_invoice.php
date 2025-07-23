<?php
// process_purchase_invoice.php
session_start();
require_once '../connect_DB.php'; // تأكد من المسار الصحيح

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'حدث خطأ غير معروف.'];

// تمكين عرض الأخطاء للتصحيح (أزلها في بيئة الإنتاج)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

error_log("----------- Starting process_purchase_invoice.php -----------");
error_log("POST Data Received: " . print_r($_POST, true)); // تسجيل كل بيانات POST

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'طريقة طلب غير صالحة.';
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode($response);
    exit();
}

// التحقق من أن المستخدم مسجل الدخول
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $response['message'] = 'لم يتم تسجيل دخول المستخدم. يرجى تسجيل الدخول أولاً.';
    error_log("User not logged in.");
    echo json_encode($response);
    exit();
}

// الحصول على معرف المستخدم من الجلسة وليس من POST لأمان أكبر
$created_by_user_id = $_SESSION['user_id'];

$con->begin_transaction(); // بدء المعاملة

try {
    // 1. جلب بيانات الفاتورة الرئيسية والتحقق منها
    $invoice_number = $_POST['invoice_number'] ?? '';
    $invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    $purchase_order_id = !empty($_POST['purchase_order_id']) ? (int)$_POST['purchase_order_id'] : null;
    $notes = $_POST['notes'] ?? '';
    // Total amount will be calculated from items, so it's temporary for invoice insert
    $temp_total_amount = 0.00; 

    if (empty($invoice_number) || $supplier_id <= 0) {
        throw new Exception("بيانات الفاتورة الرئيسية غير مكتملة: رقم الفاتورة أو المورد مفقود.");
    }

    // 2. إدخال الفاتورة في جدول purchase_invoices
    // سنستخدم استعلامين منفصلين للتعامل مع purchase_order_id الذي يمكن أن يكون NULL بشكل صحيح مع bind_param
    $sql_insert_invoice = "INSERT INTO purchase_invoices (invoice_number, invoice_date, supplier_id, purchase_order_id, total_amount, notes, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    if ($purchase_order_id === null) {
        $stmt_invoice = $con->prepare("INSERT INTO purchase_invoices (invoice_number, invoice_date, supplier_id, purchase_order_id, total_amount, notes, created_by_user_id) VALUES (?, ?, ?, NULL, ?, ?, ?)");
        if (!$stmt_invoice) {
            throw new Exception("فشل تحضير بيان إدخال الفاتورة (NULL PO): " . $con->error);
        }
        $stmt_invoice->bind_param("ssidsi", $invoice_number, $invoice_date, $supplier_id, $temp_total_amount, $notes, $created_by_user_id);
    } else {
        $stmt_invoice = $con->prepare($sql_insert_invoice);
        if (!$stmt_invoice) {
            throw new Exception("فشل تحضير بيان إدخال الفاتورة: " . $con->error);
        }
        $stmt_invoice->bind_param("ssiidsi", $invoice_number, $invoice_date, $supplier_id, $purchase_order_id, $temp_total_amount, $notes, $created_by_user_id);
    }
    
    if (!$stmt_invoice->execute()) {
        throw new Exception("فشل إدخال الفاتورة الرئيسية: " . $stmt_invoice->error);
    }
    $invoice_id = $con->insert_id;
    $stmt_invoice->close();
    error_log("New Invoice ID: " . $invoice_id);

    // 3. معالجة بنود الفاتورة وإدخال المخزون
    $items = $_POST['items'] ?? [];
    if (empty($items)) {
        throw new Exception("لم يتم إرسال بنود للفاتورة.");
    }

    // Prepare statements outside the loop for efficiency

    // ********** تحديث المخزون (الكمية وسعر التكلفة فقط) **********
    // لن يتم تحديث selling_price هنا للحفاظ على السعر السابق
    $sql_update_inventory = "UPDATE inventory SET quantity = quantity + ?, cost_price = ?, updated_at = NOW() WHERE id = ?";
    $stmt_update_inventory = $con->prepare($sql_update_inventory);
    if (!$stmt_update_inventory) {
        throw new Exception("فشل تحضير بيان تحديث المخزون: " . $con->error);
    }
    // *******************************
    
    // ********** تحديث جدول المنتجات (سعر التكلفة فقط) **********
    // لن يتم تحديث price هنا للحفاظ على السعر السابق
    $sql_update_product_cost_only = "UPDATE products SET cost_price = ?, updated_at = NOW() WHERE id = ?";
    $stmt_update_product_cost_only = $con->prepare($sql_update_product_cost_only);
    if (!$stmt_update_product_cost_only) {
        throw new Exception("فشل تحضير بيان تحديث سعر التكلفة للمنتج في جدول المنتجات: " . $con->error);
    }
    // **********************************************************

    // استعلام لإدخال بند الفاتورة
    $sql_insert_item = "INSERT INTO purchase_invoice_items (invoice_id, inventory_id, product_id, quantity_received, unit_cost, item_total) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_item = $con->prepare($sql_insert_item);
    if (!$stmt_item) {
        throw new Exception("فشل تحضير بيان إدخال بند الفاتورة: " . $con->error);
    }

    // استعلام للتحقق من وجود inventory_id (للتأكد فقط، فالواجهة الأمامية يجب أن توفرها)
    $stmt_check_inventory_exists = $con->prepare("SELECT id, product_id FROM inventory WHERE id = ?");
    if (!$stmt_check_inventory_exists) {
        throw new Exception("فشل تحضير بيان التحقق من المخزون: " . $con->error);
    }

    $calculated_total_amount = 0;

    foreach ($items as $index => $item) {
        $inventory_id = isset($item['inventory_id']) ? (int)$item['inventory_id'] : 0;
        $product_id = isset($item['product_id']) ? (int)$item['product_id'] : 0;
        $size_id = !empty($item['size_id']) ? (int)$item['size_id'] : null; // يمكن أن يكون NULL
        $quantity_received = isset($item['quantity_received']) ? (int)$item['quantity_received'] : 0;
        $unit_cost = isset($item['unit_cost']) ? (float)$item['unit_cost'] : 0.0;
        $is_serial_tracked = !empty($item['is_serial_tracked']) ? 1 : 0; // تحويل إلى int 0 أو 1

        error_log("Processing Item #" . $index . ": product_id=" . $product_id . ", inventory_id=" . $inventory_id . ", quantity=" . $quantity_received . ", cost=" . $unit_cost . ", is_serial_tracked=" . $is_serial_tracked);

        // التحقق من صحة البيانات
        if ($product_id <= 0) {
            throw new Exception("خطأ: معرف المنتج غير صالح لبند الفاتورة رقم " . ($index + 1) . ". يرجى التأكد من اختيار منتج صالح. (product_id: " . $product_id . ")");
        }
        if ($inventory_id <= 0) {
            // هذا الخطأ يجب ألا يحدث إذا كان search_products.php يعمل بشكل صحيح
            throw new Exception("خطأ: معرف المخزون غير صالح لبند الفاتورة رقم " . ($index + 1) . ". يرجى التأكد من اختيار منتج صالح في المخزون. (inventory_id: " . $inventory_id . ")");
        }
        if ($quantity_received <= 0) {
            throw new Exception("خطأ: الكمية المستلمة لبند الفاتورة رقم " . ($index + 1) . " يجب أن تكون أكبر من صفر.");
        }
        if ($unit_cost < 0) {
            throw new Exception("خطأ: سعر الشراء لبند الفاتورة رقم " . ($index + 1) . " يجب أن يكون أكبر من أو يساوي صفر.");
        }
        if ($is_serial_tracked && $quantity_received !== 1) {
            throw new Exception("خطأ: المنتج التسلسلي في الصف رقم " . ($index + 1) . " يجب أن تكون كميته 1.");
        }

        // تحقق إضافي: هل inventory_id الموجود في POST يتوافق مع product_id؟
        $stmt_check_inventory_exists->bind_param("i", $inventory_id);
        $stmt_check_inventory_exists->execute();
        $result_check = $stmt_check_inventory_exists->get_result();
        $inv_data = $result_check->fetch_assoc();
        if (!$inv_data || (int)$inv_data['product_id'] !== $product_id) {
             throw new Exception("تضارب في بيانات المخزون للمنتج رقم " . ($index + 1) . ". Product ID أو Inventory ID غير متطابق.");
        }
        $result_check->free_result(); // تحرير النتائج

        $subtotal = $quantity_received * $unit_cost;
        $calculated_total_amount += $subtotal;

        // إدخال بند الفاتورة
        $stmt_item->bind_param("iiiidd", $invoice_id, $inventory_id, $product_id, $quantity_received, $unit_cost, $subtotal);
        if (!$stmt_item->execute()) {
            throw new Exception("فشل إدخال بند الفاتورة للمنتج ID " . $product_id . ": " . $stmt_item->error);
        }
        error_log("Invoice Item inserted for product_id: " . $product_id . ", inventory_id: " . $inventory_id);

        // ********** تحديث المخزون (الكمية وسعر التكلفة فقط) **********
        // bind_param: d (unit_cost for cost_price), i (inventory_id)
        $stmt_update_inventory->bind_param("idi", $quantity_received, $unit_cost, $inventory_id);
        if (!$stmt_update_inventory->execute()) {
            throw new Exception("فشل تحديث المخزون لبند ID " . $inventory_id . ": " . $stmt_update_inventory->error);
        }
        error_log("Updated Inventory for ID: " . $inventory_id . " with quantity " . $quantity_received . " and new cost price. Selling price unchanged.");
        // *******************************

        // ********** تحديث جدول المنتجات (سعر التكلفة فقط) **********
        // bind_param: d (unit_cost for cost_price), i (product_id)
        $stmt_update_product_cost_only->bind_param("di", $unit_cost, $product_id);
        if (!$stmt_update_product_cost_only->execute()) {
            throw new Exception("فشل تحديث سعر التكلفة للمنتج في جدول المنتجات للمنتج ID " . $product_id . ": " . $stmt_update_product_cost_only->error);
        }
        error_log("Updated Product cost_price for product_id: " . $product_id . " to " . $unit_cost . ". Selling price unchanged.");
        // **********************************************************
    }

    // 4. تحديث إجمالي الفاتورة في جدول purchase_invoices
    $stmt_update_invoice_total = $con->prepare("UPDATE purchase_invoices SET total_amount = ? WHERE id = ?");
    if (!$stmt_update_invoice_total) {
        throw new Exception("فشل تحضير تحديث إجمالي الفاتورة: " . $con->error);
    }
    $stmt_update_invoice_total->bind_param("di", $calculated_total_amount, $invoice_id);
    if (!$stmt_update_invoice_total->execute()) {
        throw new Exception("فشل تحديث إجمالي الفاتورة: " . $stmt_update_invoice_total->error);
    }
    $stmt_update_invoice_total->close();

    $con->commit(); // تأكيد المعاملة
    $response['success'] = true;
    $response['message'] = 'تم تسجيل الفاتورة وترحيل المخزون بنجاح. تم تحديث سعر التكلفة فقط في جداول المخزون والمنتجات، ولم يتم تعديل أسعار البيع الحالية.';
    error_log("Invoice processed successfully. Invoice ID: " . $invoice_id);

} catch (Exception $e) {
    $con->rollback(); // التراجع عن المعاملة في حالة وجود خطأ
    $response['message'] = 'حدث خطأ أثناء معالجة الفاتورة: ' . $e->getMessage();
    error_log("CRITICAL ERROR: " . $e->getMessage()); // تسجيل الأخطاء الحرجة
} finally {
    // إغلاق البيانات المحضرة
    if (isset($stmt_item)) $stmt_item->close();
    if (isset($stmt_update_inventory)) $stmt_update_inventory->close();
    if (isset($stmt_update_product_cost_only)) $stmt_update_product_cost_only->close(); 
    if (isset($stmt_check_inventory_exists)) $stmt_check_inventory_exists->close();
    $con->close();
    error_log("----------- Finished process_purchase_invoice.php -----------");
}

echo json_encode($response);