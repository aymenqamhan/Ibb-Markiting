<?php
session_start();
include('../connect_DB.php'); // تأكد من المسار الصحيح لملف اتصال قاعدة البيانات

// التحقق من صلاحيات المستخدم
// افترض أن '1' هو معرف دور المدير
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
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

$search_results = [];
$search_performed = false;
$search_query_parts = []; // لتخزين شروط البحث

// جلب قائمة الموردين لعرضها في قائمة البحث
$suppliers = [];
$stmt_suppliers = $con->prepare("SELECT id, name FROM suppliers WHERE status = 'active'");
$stmt_suppliers->execute();
$result_suppliers = $stmt_suppliers->get_result();
while ($row = $result_suppliers->fetch_assoc()) {
    $suppliers[] = $row;
}
$stmt_suppliers->close();


if (isset($_GET['search'])) {
    $search_performed = true;
    $conditions = [];
    $params = [];
    $param_types = '';

    // البحث برقم أمر الشراء
    if (!empty($_GET['order_id'])) {
        $order_id = $_GET['order_id'];
        if (is_numeric($order_id)) {
            $conditions[] = "po.id = ?";
            $params[] = $order_id;
            $param_types .= 'i';
            $search_query_parts[] = "رقم الأمر: " . htmlspecialchars($order_id);
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'رقم أمر الشراء يجب أن يكون رقمًا صحيحًا.'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // البحث باسم المورد
    if (!empty($_GET['supplier_id'])) {
        $supplier_id = $_GET['supplier_id'];
        if (is_numeric($supplier_id)) {
            $conditions[] = "po.supplier_id = ?";
            $params[] = $supplier_id;
            $param_types .= 'i';
            $supplier_name_found = '';
            foreach($suppliers as $sup) {
                if ($sup['id'] == $supplier_id) {
                    $supplier_name_found = $sup['name'];
                    break;
                }
            }
            $search_query_parts[] = "المورد: " . htmlspecialchars($supplier_name_found);
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'معرف المورد يجب أن يكون رقمًا صحيحًا.'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // البحث بتاريخ الطلب من (Start Date)
    if (!empty($_GET['order_date_from'])) {
        $order_date_from = $_GET['order_date_from'];
        if (strtotime($order_date_from)) { // التحقق من أن التاريخ صالح
            $conditions[] = "po.order_date >= ?";
            $params[] = $order_date_from . " 00:00:00"; // بداية اليوم
            $param_types .= 's';
            $search_query_parts[] = "من تاريخ: " . htmlspecialchars($order_date_from);
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'صيغة تاريخ البدء غير صحيحة.'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // البحث بتاريخ الطلب إلى (End Date)
    if (!empty($_GET['order_date_to'])) {
        $order_date_to = $_GET['order_date_to'];
        if (strtotime($order_date_to)) { // التحقق من أن التاريخ صالح
            $conditions[] = "po.order_date <= ?";
            $params[] = $order_date_to . " 23:59:59"; // نهاية اليوم
            $param_types .= 's';
            $search_query_parts[] = "إلى تاريخ: " . htmlspecialchars($order_date_to);
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'صيغة تاريخ الانتهاء غير صحيحة.'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // البحث بحالة الطلب
    if (!empty($_GET['status']) && $_GET['status'] != 'all') {
        $status = $_GET['status'];
        $allowed_statuses = ['draft', 'pending', 'received', 'completed', 'cancelled']; // حالات ممكنة
        if (in_array($status, $allowed_statuses)) {
            $conditions[] = "po.status = ?";
            $params[] = $status;
            $param_types .= 's';
            $search_query_parts[] = "الحالة: " . htmlspecialchars($status);
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'حالة الطلب غير صالحة.'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // بناء الاستعلام بناءً على الشروط
    $sql = "
        SELECT po.id, po.order_date,po.status, s.name AS supplier_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
    ";

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY po.order_date DESC";

    $stmt = $con->prepare($sql);

    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $search_results[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بحث عن أمر شراء - لوحة التحكم</title>
    <link rel="stylesheet" href="./Search.css">
</head>
<body>
    <div class="container">
        <h1>بحث عن أمر شراء</h1>

        <?php echo $message; ?>

        <form method="GET" action="" class="search-form">
            <div class="form-group">
                <label for="order_id">رقم أمر الشراء:</label>
                <input type="number" id="order_id" name="order_id" value="<?php echo htmlspecialchars($_GET['order_id'] ?? ''); ?>" min="1">
            </div>

            <div class="form-group">
                <label for="supplier_id">المورد:</label>
                <select id="supplier_id" name="supplier_id">
                    <option value="">-- كل الموردين --</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo htmlspecialchars($supplier['id']); ?>"
                            <?php echo (isset($_GET['supplier_id']) && $_GET['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($supplier['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="order_date_from">تاريخ الطلب من:</label>
                <input type="date" id="order_date_from" name="order_date_from" value="<?php echo htmlspecialchars($_GET['order_date_from'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="order_date_to">تاريخ الطلب إلى:</label>
                <input type="date" id="order_date_to" name="order_date_to" value="<?php echo htmlspecialchars($_GET['order_date_to'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="status">حالة الطلب:</label>
                <select id="status" name="status">
                    <option value="all">-- كل الحالات --</option>
                    <option value="draft" <?php echo (isset($_GET['status']) && $_GET['status'] == 'draft') ? 'selected' : ''; ?>>مسودة</option>
                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>معلق</option>
                    <option value="received" <?php echo (isset($_GET['status']) && $_GET['status'] == 'received') ? 'selected' : ''; ?>>مستلم جزئياً</option>
                    <option value="completed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'completed') ? 'selected' : ''; ?>>مكتمل</option>
                    <option value="cancelled" <?php echo (isset($_GET['status']) && $_GET['status'] == 'cancelled') ? 'selected' : ''; ?>>ملغي</option>
                </select>
            </div>

            <button type="submit" name="search">بحث</button>
        </form>

        <?php if ($search_performed): ?>
            <?php if (!empty($search_query_parts)): ?>
                <div class="search-summary">
                    <strong>نتائج البحث عن:</strong> <?php echo implode(' | ', $search_query_parts); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($search_results)): ?>
                <p class="message error">لم يتم العثور على أوامر شراء تطابق معايير البحث.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>رقم الأمر</th>
                            <th>المورد</th>
                            <th>تاريخ الطلب</th>
                            <th>الحالة</th>
                            <th>تفاصيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($search_results as $po): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($po['id']); ?></td>
                                <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($po['order_date']))); ?></td>
                                <td><?php echo htmlspecialchars($po['status']); ?></td>
                                <td>
                                    <a href="view_purchase_order.php?id=<?php echo htmlspecialchars($po['id']); ?>" class="button view-button">عرض</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>

        <button class="back" onclick="window.location.href='list_purchase_orders.php';">العودة لقائمة أوامر الشراء</button>
    </div>
</body>
</html>