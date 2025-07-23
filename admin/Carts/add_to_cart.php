<?php
session_start();
header('Content-Type: application/json');
include('../../include/connect_DB.php');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'الرجاء تسجيل الدخول أولاً لإضافة منتجات إلى السلة.']);
    exit();
}
$user_id = intval($_SESSION['user_id']);

if (!isset($input['product_id'], $input['quantity'])) {
    echo json_encode(['status' => 'error', 'message' => 'بيانات المنتج غير مكتملة.']);
    exit();
}

$product_id = intval($input['product_id']);
$quantity_to_add = intval($input['quantity']);
// --- التعديل هنا: المقاس أصبح اختياريًا ---
$size_id = isset($input['size_id']) ? intval($input['size_id']) : null;

if ($product_id <= 0 || $quantity_to_add <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'بيانات المنتج غير صالحة.']);
    exit();
}

$inventory_item = null;

// --- بداية المنطق الجديد ---
if ($size_id) {
    // الحالة 1: المنتج له مقاس (المنطق القديم)
    $stmt = $con->prepare("SELECT id AS inventory_id, quantity, selling_price FROM inventory WHERE product_id = ? AND size_id = ?");
    $stmt->bind_param("ii", $product_id, $size_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $inventory_item = $result->fetch_assoc();
    $stmt->close();
} else {
    // الحالة 2: المنتج ليس له مقاس
    // نفترض أن المنتجات بدون مقاسات لديها size_id = NULL أو 0 في جدول inventory
    // سنستخدم IS NULL لأنه الأكثر دقة
    $stmt = $con->prepare("SELECT id AS inventory_id, quantity, selling_price FROM inventory WHERE product_id = ? AND (size_id IS NULL OR size_id = 0)");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $inventory_item = $result->fetch_assoc();
    $stmt->close();
}
// --- نهاية المنطق الجديد ---


if (!$inventory_item) {
    echo json_encode(['status' => 'error', 'message' => 'عفواً، هذا المنتج أو المقاس غير موجود في المخزون.']);
    exit();
}

$inventory_id = $inventory_item['inventory_id'];
$available_stock = $inventory_item['quantity'];
$price_at_add = $inventory_item['selling_price'];

// التحقق من الكمية الموجودة حالياً في سلة قاعدة البيانات
$check_stmt = $con->prepare("SELECT quantity FROM cart_items WHERE user_id = ? AND inventory_id = ?");
$check_stmt->bind_param("ii", $user_id, $inventory_id);
$check_stmt->execute();
$existing_cart_item = $check_stmt->get_result()->fetch_assoc();
$quantity_in_cart = $existing_cart_item ? $existing_cart_item['quantity'] : 0;
$check_stmt->close();

if (($quantity_to_add + $quantity_in_cart) > $available_stock) {
    echo json_encode(['status' => 'error', 'message' => 'عفواً، الكمية المطلوبة تتجاوز المخزون المتاح. (' . $available_stock . ' قطعة متبقية)']);
    exit();
}

// --- إضافة المنتج أو تحديثه في قاعدة البيانات (هذا الجزء لم يتغير) ---
if ($existing_cart_item) {
    $update_stmt = $con->prepare("UPDATE cart_items SET quantity = quantity + ? WHERE user_id = ? AND inventory_id = ?");
    $update_stmt->bind_param("iii", $quantity_to_add, $user_id, $inventory_id);
    $update_stmt->execute();
    $update_stmt->close();
} else {
    $insert_stmt = $con->prepare("INSERT INTO cart_items (user_id, inventory_id, quantity, price_at_add, added_at) VALUES (?, ?, ?, ?, NOW())");
    $insert_stmt->bind_param("iiid", $user_id, $inventory_id, $quantity_to_add, $price_at_add);
    $insert_stmt->execute();
    $insert_stmt->close();
}

// --- إرسال رد ناجح ---
$count_stmt = $con->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_assoc()['total_items'] ?? 0;
$count_stmt->close();

echo json_encode([
    'status' => 'success',
    'message' => 'تمت إضافة المنتج إلى السلة بنجاح!',
    'cart_item_count' => $total_items
]);

$con->close();
?>