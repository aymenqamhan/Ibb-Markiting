<?php
session_start();
include('../connect_DB.php'); // تأكد من المسار الصحيح لملف اتصال قاعدة البيانات

//هذه الصفحة ستسمح للمدير بإنشاء أمر شراء، اختيار المورد، وإضافة المنتجات بكمياتها وحجمها.

// التحقق من صلاحيات المستخدم (مثال بسيط)
// افترض أن '1' هو معرف دور المدير
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: /login.php"); // أو صفحة خطأ الوصول
    exit();
}

$message = '';

// معالجة طلب إضافة أمر شراء جديد
if (isset($_POST['add_purchase_order'])) {
    $supplier_id = $_POST['supplier_id'];
    $notes = $_POST['notes'] ?? null;
    $items = $_POST['items'] ?? []; // عناصر المنتجات
    $user_id = $_SESSION['user_id']; // معرف المستخدم الحالي

    // التحقق الأساسي من المدخلات
    if (empty($supplier_id) || !is_numeric($supplier_id) || empty($items)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'الرجاء اختيار مورد وإضافة منتج واحد على الأقل.'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $con->begin_transaction(); // بدء معاملة لضمان تكامل البيانات

    try {
        // 1. إضافة أمر الشراء الرئيسي إلى جدول purchase_orders
        // الأعمدة: supplier_id, order_date, notes, created_by_user_id, status
        $stmt = $con->prepare("INSERT INTO purchase_orders (supplier_id, order_date, notes, created_by_user_id, status) VALUES (?, CURDATE(), ?, ?, 'جديد')");
        if ($stmt === false) {
            throw new Exception("خطأ في إعداد استعلام أمر الشراء: " . $con->error);
        }
        $stmt->bind_param("isi", $supplier_id, $notes, $user_id); // i=supplier_id, s=notes, i=user_id
        $stmt->execute();
        $purchase_order_id = $con->insert_id; // الحصول على ID أمر الشراء الذي تم إنشاؤه للتو
        $stmt->close();

        // 2. إضافة عناصر أمر الشراء إلى جدول purchase_order_items
        // الأعمدة: purchase_order_id, product_id, quantity_ordered, size
        foreach ($items as $item) {
            $product_id = $item['product_id'];
            $quantity_ordered = $item['quantity_ordered'];
            // هنا، product_size_id من الفورم سيحتوي على قيمة الحجم النصية (مثل 'S', 'M')
            $size = empty($item['product_size_id']) ? NULL : $item['product_size_id']; 

            // التحقق من صلاحية البيانات لكل عنصر
            if (empty($product_id) || !is_numeric($product_id) || !is_numeric($quantity_ordered) || $quantity_ordered <= 0) {
                throw new Exception("بيانات المنتج غير صالحة: تأكد من اختيار المنتج والكمية.");
            }

            // لا يتم حساب unit_cost أو item_total هنا لأنهما غير موجودين في الجداول الجديدة حسب طلبك
            
            // إضافة البند إلى purchase_order_items
            $stmt_item = $con->prepare("INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity_ordered, size) VALUES (?, ?, ?, ?)");
            if ($stmt_item === false) {
                throw new Exception("خطأ في إعداد استعلام بنود أمر الشراء: " . $con->error);
            }
            // استخدام "iiis" حيث i=integer (purchase_order_id, product_id, quantity_ordered), s=string (size)
            $stmt_item->bind_param("iiis", $purchase_order_id, $product_id, $quantity_ordered, $size);
            $stmt_item->execute();
            $stmt_item->close();
        }

        $con->commit(); // تأكيد المعاملة
        $_SESSION['message'] = ['type' => 'success', 'text' => 'تم إنشاء أمر الشراء بنجاح.'];

    } catch (Exception $e) {
        $con->rollback(); // التراجع عن المعاملة في حال حدوث خطأ
        $_SESSION['message'] = ['type' => 'error', 'text' => 'فشل إنشاء أمر الشراء: ' . $e->getMessage()];
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// جلب الموردين لعرضهم في القائمة المنسدلة
$suppliers = [];
$stmt_suppliers = $con->prepare("SELECT id, name FROM suppliers WHERE status = 'active'");
if ($stmt_suppliers === false) {
    error_log("خطأ في جلب الموردين: " . $con->error);
} else {
    $stmt_suppliers->execute();
    $result_suppliers = $stmt_suppliers->get_result();
    while ($row = $result_suppliers->fetch_assoc()) {
        $suppliers[] = $row;
    }
    $stmt_suppliers->close();
}

// جلب المنتجات ومتغيراتها (الأحجام)
$products = [];
$stmt_products = $con->prepare("
    SELECT p.id AS product_id, p.name AS product_name, 
           ps.id AS size_id, ps.size AS size_name 
    FROM products p
    LEFT JOIN product_sizes ps ON p.id = ps.product_id
    ORDER BY p.name, ps.size
");
if ($stmt_products === false) {
    error_log("خطأ في جلب المنتجات والأحجام: " . $con->error);
} else {
    $stmt_products->execute();
    $result_products = $stmt_products->get_result();

    $grouped_products = [];
    while ($row = $result_products->fetch_assoc()) {
        $product_id = $row['product_id'];
        if (!isset($grouped_products[$product_id])) {
            $grouped_products[$product_id] = [
                'id' => $product_id,
                'name' => $row['product_name'],
                'sizes' => []
            ];
        }
        if ($row['size_id']) { 
            $grouped_products[$product_id]['sizes'][] = [
                'id' => $row['size_id'],
                'name' => $row['size_name']
            ];
        }
    }
    $stmt_products->close();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة أمر شراء جديد - لوحة التحكم</title>
    <link rel="stylesheet" href="./add.css">
</head>
<body>
    <div class="container">
        <h1>إضافة أمر شراء جديد</h1>

        <?php
        if (isset($_SESSION['message'])) {
            $msg_type = $_SESSION['message']['type'];
            $msg_text = $_SESSION['message']['text'];
            echo "<div class='message $msg_type'>$msg_text</div>";
            unset($_SESSION['message']); // إزالة الرسالة بعد عرضها
        }
        ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="supplier_id">اختر المورد:</label>
                <select id="supplier_id" name="supplier_id" required>
                    <option value="">-- اختر موردًا --</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo htmlspecialchars($supplier['id']); ?>">
                            <?php echo htmlspecialchars($supplier['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="notes">ملاحظات (اختياري):</label>
                <textarea id="notes" name="notes" rows="3"></textarea>
            </div>

            <h2>عناصر الشراء</h2>
            <div id="purchaseItemsList" class="item-list">
                </div>
            <button type="button" id="addItemBtn" class="add-item-btn">+ إضافة منتج</button>

            <button type="submit" name="add_purchase_order">إنشاء أمر الشراء</button>
        </form>
        <button class="back" onclick="window.location.href='../dashbord.php';">العودة للصفحة الرئيسية</button>
    </div>

    <script>
        const productsData = <?php echo json_encode(array_values($grouped_products)); ?>;
        let itemCounter = 0; // لتعقب عدد عناصر الشراء

        function addPurchaseItemRow() {
            itemCounter++;
            const itemList = document.getElementById('purchaseItemsList');
            const newRow = document.createElement('div');
            newRow.className = 'item-row';
            newRow.setAttribute('data-item-id', itemCounter);

            // بناء خيارات المنتجات
            let productOptions = '<option value="">-- اختر منتجًا --</option>';
            productsData.forEach(product => {
                productOptions += `<option value="${product.id}">${product.name}</option>`;
            });

            newRow.innerHTML = `
                <div class="form-group">
                    <select class="product-select" name="items[${itemCounter}][product_id]" required>
                        ${productOptions}
                    </select>
                </div>
                <div class="form-group size-group">
                    <select class="size-select" name="items[${itemCounter}][product_size_id]">
                        <option value="">-- اختر حجمًا (اختياري) --</option>
                    </select>
                </div>
                <div class="form-group">
                    <input type="number" class="quantity-input" name="items[${itemCounter}][quantity_ordered]" placeholder="الكمية" min="1" required>
                </div>
                <div class="form-group">
                    <input type="text" class="size-text-input" name="items[${itemCounter}][custom_size]" placeholder="الحجم ان وجد" >
                </div>
                <div>
                    <button type="button" class="remove-item-btn">X</button>
                </div>
            `;
            itemList.appendChild(newRow);

            // إضافة المستمعين للأحداث
            newRow.querySelector('.remove-item-btn').addEventListener('click', function() {
                newRow.remove();
            });

            // التعامل مع تغيير اختيار المنتج لتحديث قائمة الأحجام المنسدلة
            newRow.querySelector('.product-select').addEventListener('change', function() {
                const selectedProductId = this.value;
                const sizeSelect = newRow.querySelector('.size-select');
                const sizeGroup = newRow.querySelector('.size-group');
                const customSizeInput = newRow.querySelector('.size-text-input'); // حقل الإدخال النصي للحجم

                sizeSelect.innerHTML = '<option value="">-- اختر حجمًا (اختياري) --</option>'; // تفريغ الخيارات القديمة
                customSizeInput.value = ''; // تفريغ حقل الإدخال النصي

                if (selectedProductId) {
                    const selectedProduct = productsData.find(p => p.id == selectedProductId);
                    if (selectedProduct && selectedProduct.sizes && selectedProduct.sizes.length > 0) {
                        // إذا كان للمنتج أحجام معرفة مسبقًا، نظهر القائمة المنسدلة ونخفي حقل الإدخال النصي
                        selectedProduct.sizes.forEach(size => {
                            const option = document.createElement('option');
                            option.value = size.name; // نرسل اسم الحجم مباشرة
                            option.textContent = size.name;
                            sizeSelect.appendChild(option);
                        });
                        sizeGroup.style.display = 'block';
                        customSizeInput.style.display = 'none'; // إخفاء حقل الإدخال النصي
                    } else {
                        // إذا لم يكن للمنتج أحجام معرفة، نخفي القائمة المنسدلة ونظهر حقل الإدخال النصي
                        sizeGroup.style.display = 'none';
                        customSizeInput.style.display = 'block'; // إظهار حقل الإدخال النصي
                    }
                } else {
                    // إذا لم يتم اختيار منتج، نخفي كليهما أو نظهر حقل الإدخال النصي فقط
                    sizeGroup.style.display = 'none';
                    customSizeInput.style.display = 'block'; // يمكن إظهار حقل الإدخال النصي كافتراضي
                }
            });

            // تشغيل حدث التغيير لضبط الحالة الأولية عند إضافة الصف الأول
            const initialProductSelect = newRow.querySelector('.product-select');
            initialProductSelect.dispatchEvent(new Event('change'));
        }

        document.getElementById('addItemBtn').addEventListener('click', addPurchaseItemRow);

        // إضافة صف أول عند تحميل الصفحة
        addPurchaseItemRow();
    </script>
</body>
</html>