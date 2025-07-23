<?php
// search_products.php
require_once '../connect_DB.php'; // تأكد من المسار الصحيح لملف الاتصال بقاعدة البيانات

header('Content-Type: application/json');

$searchTerm = $_GET['term'] ?? '';
$results = [];

// إذا كان طول مصطلح البحث أقل من 2 حرف، أرجع مصفوفة فارغة
if (strlen($searchTerm) < 2) {
    echo json_encode([]);
    exit();
}

// استخدام علامات النسبة المئوية للبحث عن تطابقات جزئية
$likeSearchTerm = '%' . $searchTerm . '%';

// الاستعلام للبحث عن المنتجات في المخزون بناءً على SKU فقط
$query = "
SELECT
    i.id AS inventory_id,
    p.id AS product_id,
    p.name AS product_name,
    ps.id AS size_id,
    ps.size AS size_name,
    i.sku, -- جلب SKU من جدول inventory
    i.barcode, -- جلب الباركود من جدول inventory
    i.cost_price,
    i.is_serial_tracked,
    NULL AS serial_number,
    FALSE AS is_specific_serial,
    -- نص العرض سيجمع اسم المنتج، الحجم، و SKU ليكون واضحاً للاختيار
    CONCAT(p.name, ' (', COALESCE(ps.size, 'عام'), ') - SKU: ', i.sku) AS display_text
FROM
    inventory i
JOIN
    products p ON i.product_id = p.id
LEFT JOIN
    product_sizes ps ON i.size_id = ps.id
WHERE
    i.sku LIKE ? -- البحث عن طريق SKU في جدول المخزون فقط
ORDER BY
    p.name, ps.size, i.sku;
";

$stmt = $con->prepare($query);

if ($stmt) {
    // ربط المعاملة: معامل واحد فقط للبحث عن SKU
    $stmt->bind_param("s", $likeSearchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
} else {
    // التعامل مع خطأ التحضير
    error_log("Error preparing search query: " . $con->error);
}

$con->close();

echo json_encode($results);
?>