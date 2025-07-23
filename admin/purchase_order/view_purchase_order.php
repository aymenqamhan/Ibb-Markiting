<?php
session_start();
include('../connect_DB.php'); // تأكد من المسار الصحيح لملف اتصال قاعدة البيانات

// التحقق من صلاحيات المستخدم (مثال بسيط)
// افترض أن '1' هو معرف دور المدير أو '5' لمدير المخزون
if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 5)) {
    header("Location: /login.php"); // أو صفحة خطأ الوصول
    exit();
}

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

// التحقق من وجود ID لأمر الشراء
if (!$purchase_order_id || !is_numeric($purchase_order_id)) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'معرف أمر الشراء غير صالح.'];
    header("Location: list_purchase_orders.php"); // العودة إلى قائمة أوامر الشراء
    exit();
}

// -----------------------------------------------------------
// 1. معالجة تحديث حالة أمر الشراء واستلام المنتجات
// -----------------------------------------------------------
if (isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $allowed_statuses = ['draft', 'pending', 'received', 'completed', 'cancelled'];

    if (!in_array($new_status, $allowed_statuses)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'حالة غير صالحة.'];
    } else {
        $con->begin_transaction(); // بدء معاملة قاعدة البيانات

        try {
            // تحديث حالة أمر الشراء الرئيسي
            $stmt_status = $con->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
            if ($stmt_status === false) {
                throw new Exception("خطأ في إعداد استعلام تحديث الحالة: " . $con->error);
            }
            $stmt_status->bind_param("si", $new_status, $purchase_order_id);
            if (!$stmt_status->execute()) {
                throw new Exception("فشل تحديث حالة أمر الشراء: " . $stmt_status->error);
            }
            $stmt_status->close();

            // معالجة الكميات المستلمة وتحديث المخزون
            // هذا الجزء يتم تنفيذه فقط إذا كانت الحالة الجديدة "مكتمل" أو "مستلم جزئياً"
            // أو إذا تم إرسال حقول 'items_received' (للسماح بالاستلام الجزئي بدون تغيير الحالة بالضرورة)
            if (isset($_POST['items_received']) && is_array($_POST['items_received'])) {
                foreach ($_POST['items_received'] as $item_id => $received_quantity) {
                    $received_quantity = (int)$received_quantity;

                    // تخطي المنتجات التي لم يتم إدخال كمية للاستلام لها أو قيمتها 0
                    if ($received_quantity <= 0) {
                        continue;
                    }

                    // جلب الكمية المطلوبة وتفاصيل المنتج والكمية المستلمة حالياً
                    $stmt_get_item = $con->prepare("SELECT product_id, product_size_id, quantity_ordered, quantity_received FROM purchase_order_items WHERE id = ? AND purchase_order_id = ?");
                    if ($stmt_get_item === false) {
                        throw new Exception("خطأ في إعداد استعلام جلب تفاصيل العنصر: " . $con->error);
                    }
                    $stmt_get_item->bind_param("ii", $item_id, $purchase_order_id);
                    $stmt_get_item->execute();
                    $item_data = $stmt_get_item->get_result()->fetch_assoc();
                    $stmt_get_item->close();

                    if ($item_data) {
                        $current_received = $item_data['quantity_received'];
                        $new_total_received = $current_received + $received_quantity;
                        $quantity_ordered = $item_data['quantity_ordered'];

                        // تأكد من عدم تجاوز الكمية المستلمة للكمية المطلوبة
                        if ($new_total_received > $quantity_ordered) {
                             throw new Exception("الكمية المستلمة لعنصر المنتج رقم (" . $item_data['product_id'] . ") تجاوزت الكمية المطلوبة. الكمية المطلوبة: " . $quantity_ordered . "، تم استلام: " . $current_received . "، تحاول استلام الآن: " . $received_quantity);
                        }

                        // تحديث الكمية المستلمة في purchase_order_items
                        $stmt_update_item = $con->prepare("UPDATE purchase_order_items SET quantity_received = ? WHERE id = ?");
                        if ($stmt_update_item === false) {
                            throw new Exception("خطأ في إعداد استعلام تحديث كمية العنصر: " . $con->error);
                        }
                        $stmt_update_item->bind_param("ii", $new_total_received, $item_id);
                        if (!$stmt_update_item->execute()) {
                            throw new Exception("فشل تحديث الكمية المستلمة للعنصر: " . $stmt_update_item->error);
                        }
                        $stmt_update_item->close();

                        // تحديث المخزون (أو إضافة سجل جديد للمخزون)
                        $product_id = $item_data['product_id'];
                        $product_size_id = $item_data['product_size_id']; // يمكن أن يكون NULL

                        if ($product_size_id !== NULL) {
                             $stmt_stock = $con->prepare("
                                INSERT INTO stock (product_id, product_size_id, quantity) 
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE quantity = quantity + ?
                             ");
                             if ($stmt_stock === false) {
                                throw new Exception("خطأ في إعداد استعلام تحديث المخزون (بحجم): " . $con->error);
                            }
                             $stmt_stock->bind_param("iiii", $product_id, $product_size_id, $received_quantity, $received_quantity);
                        } else {
                            $stmt_stock = $con->prepare("
                                INSERT INTO stock (product_id, quantity) 
                                VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE quantity = quantity + ?
                            ");
                            if ($stmt_stock === false) {
                                throw new Exception("خطأ في إعداد استعلام تحديث المخزون (بدون حجم): " . $con->error);
                            }
                            $stmt_stock->bind_param("iii", $product_id, $received_quantity, $received_quantity);
                        }
                        if (!$stmt_stock->execute()) {
                            throw new Exception("فشل تحديث المخزون: " . $stmt_stock->error);
                        }
                        $stmt_stock->close();
                    }
                }
            }
            $con->commit(); // تأكيد المعاملة إذا سارت جميع الخطوات بنجاح
            $_SESSION['message'] = ['type' => 'success', 'text' => 'تم تحديث حالة أمر الشراء بنجاح وتم تحديث المخزون (إن وجدت كميات مستلمة).'];

        } catch (Exception $e) {
            $con->rollback(); // التراجع عن المعاملة في حال حدوث أي خطأ
            $_SESSION['message'] = ['type' => 'error', 'text' => 'فشل تحديث أمر الشراء: ' . $e->getMessage()];
        }
    }
    // إعادة التوجيه لضمان تحديث الصفحة وعرض الرسائل
    header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $purchase_order_id);
    exit();
}


// -----------------------------------------------------------
// 2. جلب تفاصيل أمر الشراء الرئيسية
// -----------------------------------------------------------
$stmt_po = $con->prepare("
    SELECT po.id, po.order_date, po.status, po.notes AS order_notes, -- تغيير اسم عمود ملاحظات أمر الشراء
           s.name AS supplier_name, s.contact_phone AS supplier_phone, s.address, s.notes AS supplier_notes, s.contact_email AS supplier_email, -- تغيير اسم عمود ملاحظات المورد
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
// إذا لم يتم العثور على أمر الشراء، أعد التوجيه برسالة خطأ
if (!$purchase_order) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'أمر الشراء غير موجود.'];
    header("Location: list_purchase_orders.php");
    exit();
}

// -----------------------------------------------------------
// 3. جلب عناصر أمر الشراء التفصيلية
// -----------------------------------------------------------
$stmt_items = $con->prepare("
    SELECT poi.id AS item_id, poi.product_id, poi.size, 
           p.name AS product_name,
           poi.size AS size_name,  -- استخدم العمود size مباشرة بدل الانضمام
           poi.quantity_ordered
    FROM purchase_order_items poi
    JOIN products p ON poi.product_id = p.id
    WHERE poi.purchase_order_id = ?
");

if ($stmt_items === false) {
    die("خطأ في إعداد استعلام جلب عناصر أمر الشراء: " . $con->error);
}
$stmt_items->bind_param("i", $purchase_order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
while ($row = $result_items->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt_items->close();

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل أمر الشراء #<?php echo htmlspecialchars($purchase_order['id']); ?></title>
    <link rel="stylesheet" href="purchase_orders_styles.css">
    <style>
        /* تنسيقات إضافية خاصة بصفحة عرض التفاصيل */
        .order-details, .supplier-details, .status-update-form {
            background-color: #f8f8f8;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }
        .order-details p, .supplier-details p {
            margin-bottom: 8px;
        }
        .order-details h2, .supplier-details h2 {
            margin-top: 0;
            color: #333;
        }
        .status-update-form .form-group {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        .status-update-form select, .status-update-form input[type="submit"] {
            padding: 8px 12px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .status-update-form input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            cursor: pointer;
            border: none;
        }
        .status-update-form input[type="submit"]:hover {
            background-color: #45a049;
        }
        .received-quantity-input {
            width: 80px; /* لتحديد عرض حقل الكمية المستلمة */
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            color:rgb(0, 0, 0);
            font-weight: bold;
            text-transform: capitalize; /* لأول حرف كبير */
        }
        .status-badge.draft { background-color: #888; }
        .status-badge.pending { background-color: #f0ad4e; } /* برتقالي */
        .status-badge.received { background-color: #5bc0de; } /* أزرق فاتح */
        .status-badge.completed { background-color: #5cb85c; } /* أخضر */
        .status-badge.cancelled { background-color: #d9534f; } /* أحمر */
    </style>
</head>
<body>
    <div class="container">
        <h1>تفاصيل أمر الشراء #<?php echo htmlspecialchars($purchase_order['id']); ?></h1>

        <?php echo $message; ?>

        <div class="order-details">
            <h2>معلومات أمر الشراء</h2>
            <p><strong>رقم الأمر:</strong> <?php echo htmlspecialchars($purchase_order['id']); ?></p>
            <p><strong>تاريخ الطلب:</strong> <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($purchase_order['order_date']))); ?></p>
            <p><strong>الحالة:</strong> <span class="status-badge <?php echo htmlspecialchars($purchase_order['status']); ?>"><?php echo htmlspecialchars($purchase_order['status']); ?></span></p>
            <p><strong>أنشئ بواسطة:</strong> <?php echo htmlspecialchars($purchase_order['created_by_user'] ?? 'غير معروف'); ?></p>
        </div>

        <div class="supplier-details">
            <h2>معلومات المورد</h2>
            <p><strong>اسم المورد:</strong> <?php echo htmlspecialchars($purchase_order['supplier_name']); ?></p>
            <p><strong>الهاتف:</strong> <?php echo htmlspecialchars($purchase_order['supplier_phone']); ?></p>
            <p><strong>البريد الإلكتروني:</strong> <?php echo htmlspecialchars($purchase_order['supplier_email']); ?></p>
            <p><strong> العنوان :</strong> <?php echo htmlspecialchars($purchase_order['address']); ?></p>
            <p><strong>ملاحظات أمر الشراء:</strong> <?php echo htmlspecialchars($purchase_order['order_notes'] ?? 'لا يوجد'); ?></p>
            <p><strong>ملاحظات المورد:</strong> <?php echo htmlspecialchars($purchase_order['supplier_notes'] ?? 'لا يوجد'); ?></p>        </div>
            
        <h2>عناصر أمر الشراء</h2>
        <?php if (empty($order_items)): ?>
            <p>لا توجد عناصر في أمر الشراء هذا.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>المنتج</th>
                        <th>الحجم</th>
                        <th>الكمية المطلوبة</th>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['size_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($item['quantity_ordered']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2>تحديث حالة أمر الشراء واستلام المنتجات</h2>
        <a class='button add-button' href="./edit_purchase_order.php?id=<?php echo htmlspecialchars($purchase_order['id']); ?>">تعديل</a> 

    <button class="back" onclick="window.location.href='list_purchase_orders.php';">العودة لقائمة أوامر الشراء</button>
    </div>
</body>
</html>