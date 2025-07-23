<?php
session_start();
include('../../include/connect_DB.php'); 

if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 5)) {
    $_SESSION['error_message'] = "ليس لديك صلاحية الوصول إلى هذه الصفحة.";
    header("Location: /NEW_IBB/login.php");
    exit();
}

$message = '';
if (isset($_SESSION['message'])) {
    $msg_type = $_SESSION['message']['type'];
    $msg_text = $_SESSION['message']['text'];
    $message = "<div class='message $msg_type'>$msg_text</div>";
    unset($_SESSION['message']);
}

$report_type = $_POST['report_type'] ?? 'total_by_period';
$start_date = $_POST['start_date'] ?? date('Y-m-01');
$end_date = $_POST['end_date'] ?? date('Y-m-d');
$supplier_id = $_POST['supplier_id'] ?? '';
$product_id = $_POST['product_id'] ?? '';

$suppliers = []; 
$products = [];  
$report_results = [];
$report_title = '';

if (!$con) {
    $_SESSION['message'] = ['type' => 'error', 'text' => "خطأ في الاتصال بقاعدة البيانات."];
} else {
    // جلب قائمة الموردين لفلتر التقرير
    $stmt_suppliers = $con->prepare("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name");
    if ($stmt_suppliers) {
        $stmt_suppliers->execute();
        $result_suppliers = $stmt_suppliers->get_result();
        while ($row = $result_suppliers->fetch_assoc()) {
            $suppliers[] = $row;
        }
        $stmt_suppliers->close();
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => "خطأ في جلب قائمة الموردين: " . $con->error];
    }

    // جلب قائمة المنتجات لفلتر التقرير
    $stmt_products = $con->prepare("SELECT id, name FROM products WHERE status = 'active' ORDER BY name");
    if ($stmt_products) {
        $stmt_products->execute();
        $result_products = $stmt_products->get_result();
        while ($row = $result_products->fetch_assoc()) {
            $products[] = $row;
        }
        $stmt_products->close();
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => "خطأ في جلب قائمة المنتجات: " . $con->error];
    }
}


if (isset($_POST['generate_report']) && $con) {
    switch ($report_type) {
        case 'total_by_period':
            $report_title = "إجمالي المشتريات حسب الفترة: من " . htmlspecialchars($start_date) . " إلى " . htmlspecialchars($end_date);
            // الاستعلام الآن يستخدم جدول "فواتير المشتريات" الذي يحتوي على total_amount و invoice_date
            $stmt = $con->prepare("SELECT SUM(total_amount) AS total_purchases FROM purchase_invoices WHERE invoice_date BETWEEN ? AND ?"); // <--- هذا هو التغيير الأساسي
            if ($stmt === false) {
                 $_SESSION['message'] = ['type' => 'error', 'text' => "خطأ في إعداد الاستعلام (total_by_period): " . $con->error];
                 header("Location: " . $_SERVER['PHP_SELF']);
                 exit();
            }
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $report_results = [['Total Purchases' => $result['total_purchases'] ?? 0]];
            $stmt->close();
            break;

        case 'purchases_by_supplier':
            $report_title = "المشتريات حسب المورد";
            // الاستعلام يستخدم جدول "فواتير المشتريات" ويربطه بجدول الموردين
            $query = "SELECT s.name AS supplier_name, SUM(pi.total_amount) AS total_purchased_amount, COUNT(pi.id) AS total_invoices
                      FROM purchase_invoices pi
                      JOIN suppliers s ON pi.supplier_id = s.id
                      WHERE pi.invoice_date BETWEEN ? AND ?"; // <--- هنا التغيير
            $params = "ss";
            $bind_values = [$start_date, $end_date];

            if (!empty($supplier_id)) {
                $query .= " AND s.id = ?";
                $params .= "i";
                $bind_values[] = $supplier_id;
            }
            $query .= " GROUP BY s.name ORDER BY total_purchased_amount DESC";

            $stmt = $con->prepare($query);
            if ($stmt === false) {
                 $_SESSION['message'] = ['type' => 'error', 'text' => "خطأ في إعداد الاستعلام (purchases_by_supplier): " . $con->error];
                 header("Location: " . $_SERVER['PHP_SELF']);
                 exit();
            }
            $stmt->bind_param($params, ...$bind_values);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $report_results[] = $row;
            }
            $stmt->close();
            break;

        case 'purchases_by_product':
            $report_title = "المشتريات حسب المنتج";
            // الاستعلام يستخدم جدول بنود فواتير المشتريات (purchase_invoice_items) ويربطه بالمنتجات
            $query = "SELECT p.name AS product_name, 
                                 SUM(pii.quantity_received) AS total_quantity_purchased, 
                                 SUM(pii.quantity_received * pii.unit_cost) AS total_cost
                      FROM purchase_invoice_items pii -- <--- هنا التغيير
                      JOIN purchase_invoices pi ON pii.invoice_id = pi.id -- <--- هنا التغيير
                      JOIN products p ON pii.product_id = p.id
                      WHERE pi.invoice_date BETWEEN ? AND ?";
            $params = "ss";
            $bind_values = [$start_date, $end_date];

            if (!empty($product_id)) {
                $query .= " AND p.id = ?";
                $params .= "i";
                $bind_values[] = $product_id;
            }
            $query .= " GROUP BY p.name ORDER BY total_cost DESC";

            $stmt = $con->prepare($query);
            if ($stmt === false) {
                 $_SESSION['message'] = ['type' => 'error', 'text' => "خطأ في إعداد الاستعلام (purchases_by_product): " . $con->error];
                 header("Location: " . $_SERVER['PHP_SELF']);
                 exit();
            }
            $stmt->bind_param($params, ...$bind_values);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $report_results[] = $row;
            }
            $stmt->close();
            break;

        default:
            $_SESSION['message'] = ['type' => 'error', 'text' => 'نوع تقرير غير صالح.'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
    }
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقارير المشتريات</title>
    <link rel="stylesheet" href="./Style.css">
</head>
<body>
    <div class="container">
        <h1>تقارير المشتريات</h1>

        <?php echo $message; ?>

        <div class="report-filters">
            <form method="POST" action="">
                <div class="filter-group">
                    <label for="report_type">نوع التقرير:</label>
                    <select id="report_type" name="report_type" onchange="toggleFilters()">
                        <option value="total_by_period" <?php echo ($report_type == 'total_by_period') ? 'selected' : ''; ?>>إجمالي المشتريات حسب الفترة</option>
                        <option value="purchases_by_supplier" <?php echo ($report_type == 'purchases_by_supplier') ? 'selected' : ''; ?>>المشتريات حسب المورد</option>
                        <option value="purchases_by_product" <?php echo ($report_type == 'purchases_by_product') ? 'selected' : ''; ?>>المشتريات حسب المنتج</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="start_date">تاريخ البدء:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
                </div>
                <div class="filter-group">
                    <label for="end_date">تاريخ الانتهاء:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
                </div>

                <div class="filter-group" id="supplier_filter_group" style="display: <?php echo ($report_type == 'purchases_by_supplier') ? 'flex' : 'none'; ?>;">
                    <label for="supplier_id">المورد:</label>
                    <select id="supplier_id" name="supplier_id">
                        <option value="">جميع الموردين</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo htmlspecialchars($supplier['id']); ?>" <?php echo ($supplier['id'] == $supplier_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group" id="product_filter_group" style="display: <?php echo ($report_type == 'purchases_by_product') ? 'flex' : 'none'; ?>;">
                    <label for="product_id">المنتج:</label>
                    <select id="product_id" name="product_id">
                        <option value="">جميع المنتجات</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo htmlspecialchars($product['id']); ?>" <?php echo ($product['id'] == $product_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <button type="submit" name="generate_report">توليد التقرير</button>
                </div>
            </form>
        </div>

        <?php if (!empty($report_results)): ?>
            <div class="report-results">
                <h2><?php echo htmlspecialchars($report_title); ?></h2>
                <table class="report-table">
                    <thead>
                        <tr>
                            <?php
                            if ($report_type == 'total_by_period') {
                                echo '<th>إجمالي المشتريات</th>';
                            } elseif ($report_type == 'purchases_by_supplier') {
                                echo '<th>اسم المورد</th><th>إجمالي مبلغ الشراء</th><th>عدد الفواتير</th>';
                            } elseif ($report_type == 'purchases_by_product') {
                                // أزلت 'الحجم' لأنه غير ظاهر في الأعمدة المتاحة في الصورة لـ purchase_order_items
                                echo '<th>اسم المنتج</th><th>إجمالي الكمية المشتراة</th><th>إجمالي التكلفة</th>'; 
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_results as $row): ?>
                            <tr>
                                <?php
                                if ($report_type == 'total_by_period') {
                                    echo '<td>' . htmlspecialchars(number_format($row['Total Purchases'], 2)) . '</td>';
                                } elseif ($report_type == 'purchases_by_supplier') {
                                    echo '<td>' . htmlspecialchars($row['supplier_name']) . '</td>';
                                    echo '<td>' . htmlspecialchars(number_format($row['total_purchased_amount'], 2)) . '</td>';
                                    echo '<td>' . htmlspecialchars($row['total_invoices']) . '</td>';
                                } elseif ($report_type == 'purchases_by_product') {
                                    echo '<td>' . htmlspecialchars($row['product_name']) . '</td>';
                                    // أزلت عرض الحجم هنا أيضاً
                                    echo '<td>' . htmlspecialchars($row['total_quantity_purchased']) . '</td>';
                                    echo '<td>' . htmlspecialchars(number_format($row['total_cost'], 2)) . '</td>';
                                }
                                ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (isset($_POST['generate_report'])): ?>
            <div class="report-results">
                <p>لا توجد بيانات متاحة لهذا التقرير بالمعايير المحددة.</p>
            </div>
        <?php endif; ?>
        <button class="back" onclick="window.location.href='../dashboard.php';">العودة للوحة التحكم</button>
    </div>

    <script>
        function toggleFilters() {
            const reportType = document.getElementById('report_type').value;
            const supplierFilterGroup = document.getElementById('supplier_filter_group');
            const productFilterGroup = document.getElementById('product_filter_group');

            supplierFilterGroup.style.display = 'none';
            productFilterGroup.style.display = 'none';

            if (reportType === 'purchases_by_supplier') {
                supplierFilterGroup.style.display = 'flex';
            } else if (reportType === 'purchases_by_product') {
                productFilterGroup.style.display = 'flex';
            }
        }

        document.addEventListener('DOMContentLoaded', toggleFilters);
    </script>
</body>
</html>
<?php
if (isset($con) && $con) {
    $con->close();
}
?>