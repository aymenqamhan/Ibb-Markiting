<?php
// api/reports.php

// تضمين ملف الاتصال بقاعدة البيانات
// تأكد من المسار الصحيح بناءً على مكان ملف connect_DB.php
// إذا كان api و include في نفس المجلد الأصلي للمشروع، فالمسار الصحيح هو:
include('../include/connect_DB.php');

header('Content-Type: application/json'); // إخبار المتصفح بأن الاستجابة JSON

$report_type = $_GET['type'] ?? ''; // الحصول على نوع التقرير من طلب GET
$data = []; // مصفوفة لتخزين البيانات المسترجعة

try {
    switch ($report_type) {
        case 'users_by_role':
            // تقرير: عدد المستخدمين حسب الدور
            $stmt = $con->prepare("SELECT r.role_name, COUNT(u.id) AS user_count FROM user_tb u JOIN roles r ON u.role_id = r.id GROUP BY r.role_name");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $con->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            break;

        case 'user_status':
            // تقرير: حالة المستخدمين (نشط/غير نشط)
            $stmt = $con->prepare("SELECT status, COUNT(id) AS count FROM user_tb GROUP BY status");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $con->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            break;

        case 'top_selling_products':
            // تقرير: المنتجات الأكثر مبيعاً (حسب الكمية)
            $stmt = $con->prepare("SELECT p.name AS product_name, SUM(oi.quantity) AS total_quantity_sold FROM order_items oi JOIN products p ON oi.product_id = p.id GROUP BY p.name ORDER BY total_quantity_sold DESC LIMIT 10");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $con->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            break;

        case 'products_by_category':
            // تقرير: المنتجات حسب الفئة
            $stmt = $con->prepare("SELECT c.category_name, COUNT(p.id) AS product_count FROM products p JOIN categories c ON p.category_id = c.id GROUP BY c.category_name ORDER BY product_count DESC");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $con->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            break;

        case 'low_stock_products':
            // تقرير: مستويات المخزون للمنتجات (للتنبيه بالكميات المنخفضة)
            $stmt = $con->prepare("SELECT p.name AS product_name, i.quantity AS current_stock, i.min_stock_level FROM inventory i JOIN products p ON i.product_id = p.id WHERE i.quantity <= i.min_stock_level ORDER BY i.quantity ASC");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $con->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            break;

        case 'monthly_sales':
            // تقرير: إجمالي المبيعات بمرور الوقت (شهري)
            $stmt = $con->prepare("SELECT DATE_FORMAT(order_date, '%Y-%m') AS sale_month, SUM(total_amount) AS monthly_sales FROM orders WHERE status IN ('delivered', 'shipped', 'processing', 'paid') GROUP BY sale_month ORDER BY sale_month ASC");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $con->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            break;

        case 'orders_by_status':
            // تقرير: الطلبات حسب الحالة
            $stmt = $con->prepare("SELECT status, COUNT(id) AS order_count FROM orders GROUP BY status ORDER BY order_count DESC");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $con->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            break;

        case 'payment_method_distribution':
            // تقرير: طرق الدفع الأكثر استخداماً
            $stmt = $con->prepare("SELECT payment_method, COUNT(id) AS method_count FROM orders WHERE payment_status = 'paid' GROUP BY payment_method ORDER BY method_count DESC");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $con->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            break;

        case 'average_product_ratings':
            // تقرير: متوسط تقييم المنتجات
            $stmt = $con->prepare("SELECT p.name AS product_name, p.average_rating FROM products p WHERE p.total_reviews_count > 0 ORDER BY p.average_rating DESC");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $con->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            break;

        case 'rating_distribution':
            // تقرير: توزيع التقييمات (1-5 نجوم)
            $stmt = $con->prepare("SELECT rating, COUNT(id) AS review_count FROM product_reviews GROUP BY rating ORDER BY rating ASC");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $con->error);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            break;

        default:
            echo json_encode(['error' => 'Invalid report type specified.']);
            exit();
    }
    echo json_encode($data);

} catch (Exception $e) {
    // التعامل مع الأخطاء
    error_log("Report API Error: " . $e->getMessage()); // تسجيل الخطأ في log
    echo json_encode(['error' => 'An error occurred while fetching data. Please try again later.']);
} finally {
    // إغلاق الاتصال بقاعدة البيانات
    if (isset($con) && $con instanceof mysqli) {
        $con->close();
    }
}
?>