<?php
session_start(); // تأكد من بدء الجلسة للحصول على user_id
include('../connect_DB.php'); // الاتصال بقاعدة البيانات

// هذه المتغيرات ستحتوي على رسائل الخطأ أو النجاح
$message = '';
$message_type = ''; // 'success' or 'error'

// *** الجزء الخاص بمعالجة طلب POST فقط ***
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // جمع البيانات من النموذج
    $name = $_POST['name'];
    $description = $_POST['description'];
    $sku = $_POST['sku'];
    $category_id = $_POST['category_id'];
    $subcategory_id = isset($_POST['subcategory_id']) && !empty($_POST['subcategory_id']) ? $_POST['subcategory_id'] : NULL;
    $subsubcategory_id = isset($_POST['subsubcategory_id']) && !empty($_POST['subsubcategory_id']) ? $_POST['subsubcategory_id'] : NULL;

    $status = $_POST['status'];
    $is_serial_tracked = isset($_POST['is_serial_tracked']) ? 1 : 0;

    // التحقق من الحقول المطلوبة
    if (empty($name) || empty($description) || empty($sku) || empty($category_id) || empty($status)) {
        $message = 'يرجى ملء جميع الحقول المطلوبة.';
        $message_type = 'error';
    } else {
        // التحقق من عدم تكرار الرقم التسلسلي (SKU)
        $check_stmt = $con->prepare("SELECT id FROM products WHERE sku = ?");
        $check_stmt->bind_param("s", $sku);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            $message = 'هذا الرقم التسلسلي مستخدم بالفعل، يرجى استخدام رقم آخر.';
            $message_type = 'error';
        }
        $check_stmt->close();
    }

    // إذا لم تكن هناك أخطاء أولية، ابدأ المعاملة
    if (empty($message)) {
        $con->begin_transaction();

        try {
            $subcat_id = $subcategory_id; // تم تعيينها بالفعل كـ NULL أو قيمة
            $subsubcat_id = $subsubcategory_id; // تم تعيينها بالفعل كـ NULL أو قيمة

            // إدخال المنتج في جدول products
            $stmt = $con->prepare("INSERT INTO products 
                (name, description, sku, category_id, subcategory_id, subsubcategory_id, created_at, status) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");

            if (!$stmt) {
                throw new Exception("خطأ في تهيئة استعلام إدخال المنتج: " . $con->error);
            }

            // Bind parameters based on your table structure
            // sssiiss -> name, description, sku, category_id, subcategory_id, subsubcategory_id, status
            // Note: If subcategory_id or subsubcategory_id can be NULL, 'i' is correct if they are integers.
            // If they are VARCHARs and can be NULL, 's' is also correct, but prepare statement should handle NULL correctly.
            // Assuming category_id, subcategory_id, subsubcategory_id are INT types.
            $stmt->bind_param(
                "sssiiss",
                $name,
                $description,
                $sku,
                $category_id,
                $subcat_id,
                $subsubcat_id,
                $status
            );

            if (!$stmt->execute()) {
                throw new Exception("خطأ في تنفيذ استعلام إدخال المنتج: " . $stmt->error);
            }
            $product_id = $stmt->insert_id;
            $stmt->close();

            // إدخال الأحجام في جدول product_sizes
            if (isset($_POST['sizes']) && is_array($_POST['sizes']) && !empty(array_filter($_POST['sizes']))) {
                $size_stmt = $con->prepare("INSERT INTO product_sizes (product_id, size) VALUES (?, ?)");
                if (!$size_stmt) {
                    throw new Exception("خطأ في تهيئة استعلام إدخال الأحجام: " . $con->error);
                }
                foreach ($_POST['sizes'] as $size_name) {
                    if (!empty($size_name)) {
                        $size_stmt->bind_param("is", $product_id, $size_name);
                        if (!$size_stmt->execute()) {
                            throw new Exception("خطأ في تنفيذ استعلام إدخال الحجم: " . $size_stmt->error);
                        }
                    }
                }
                $size_stmt->close();
            }

            // إدخال في جدول المخزون
            if (!isset($_POST['sizes']) || !is_array($_POST['sizes']) || empty(array_filter($_POST['sizes']))) {
                // قم بإنشاء سجل مخزون واحد للمنتج بدون حجم محدد (size_id = NULL)
                $inventory_insert_stmt = $con->prepare("INSERT INTO inventory (product_id, size_id, sku, quantity, cost_price, selling_price, is_serial_tracked, created_by_user_id, created_at, updated_at) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                if (!$inventory_insert_stmt) {
                    throw new Exception("خطأ في تهيئة استعلام إدخال المخزون (بدون حجم): " . $con->error);
                }

                $initial_quantity = 0;
                $initial_cost_price = 0.0;
                $initial_selling_price = 0.0;
                $created_by_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

                $inventory_insert_stmt->bind_param(
                    "isddiii",
                    $product_id,
                    $sku,
                    $initial_quantity,
                    $initial_cost_price,
                    $initial_selling_price,
                    $is_serial_tracked,
                    $created_by_user_id
                );
                if (!$inventory_insert_stmt->execute()) {
                    throw new Exception("خطأ في تنفيذ استعلام إدخال المخزون (بدون حجم): " . $inventory_insert_stmt->error);
                }
                $inventory_insert_stmt->close();
            } else {
                // إذا كان هناك أحجام محددة، لكل حجم يتم إنشاء سجل مخزون خاص به
                $get_sizes_id_stmt = $con->prepare("SELECT id, size FROM product_sizes WHERE product_id = ?");
                if (!$get_sizes_id_stmt) {
                    throw new Exception("خطأ في تهيئة استعلام جلب معرفات الأحجام: " . $con->error);
                }
                $get_sizes_id_stmt->bind_param("i", $product_id);
                $get_sizes_id_stmt->execute();
                $result_sizes_ids = $get_sizes_id_stmt->get_result();
                $product_sizes_map = [];
                while ($row = $result_sizes_ids->fetch_assoc()) {
                    $product_sizes_map[$row['size']] = $row['id'];
                }
                $get_sizes_id_stmt->close();

                $inventory_insert_stmt = $con->prepare("INSERT INTO inventory (product_id, size_id, sku, quantity, cost_price, selling_price, is_serial_tracked, created_by_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                if (!$inventory_insert_stmt) {
                    throw new Exception("خطأ في تهيئة استعلام إدخال المخزون (مع أحجام): " . $con->error);
                }

                $created_by_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

                foreach ($_POST['sizes'] as $size_name) {
                    if (!empty($size_name) && isset($product_sizes_map[$size_name])) {
                        $current_size_id = $product_sizes_map[$size_name];
                        $inventory_sku_for_size = $sku . '-' . strtoupper(substr($size_name, 0, 3));
                        $initial_quantity = 0;
                        $initial_cost_price = 0.0;
                        $initial_selling_price = 0.0;

                        $inventory_insert_stmt->bind_param(
                            "iisddiii",
                            $product_id,
                            $current_size_id,
                            $inventory_sku_for_size,
                            $initial_quantity,
                            $initial_cost_price,
                            $initial_selling_price,
                            $is_serial_tracked,
                            $created_by_user_id
                        );
                        if (!$inventory_insert_stmt->execute()) {
                            throw new Exception("خطأ في تنفيذ استعلام إدخال المخزون للحجم " . htmlspecialchars($size_name) . ": " . $inventory_insert_stmt->error);
                        }
                    }
                }
                $inventory_insert_stmt->close();
            }

            // رفع الصور
            if (isset($_FILES['images']) && $_FILES['images']['error'][0] == 0) {
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    $image_name = basename($_FILES['images']['name'][$key]);
                    // استخدام مسار آمن لمنع تجاوز الدليل
                    $upload_dir = 'uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $image_path = $upload_dir . time() . '_' . uniqid() . '_' . $image_name;

                    $extension = pathinfo($image_path, PATHINFO_EXTENSION);
                    if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])) {
                        if (move_uploaded_file($tmp_name, $image_path)) {
                            $img_stmt = $con->prepare("INSERT INTO products_images (product_id, image_path) VALUES (?, ?)");
                            if (!$img_stmt) {
                                throw new Exception("خطأ في تهيئة استعلام إدخال الصورة: " . $con->error);
                            }
                            $img_stmt->bind_param("is", $product_id, $image_path);
                            if (!$img_stmt->execute()) {
                                throw new Exception("خطأ في تنفيذ استعلام إدخال الصورة: " . $img_stmt->error);
                            }
                            $img_stmt->close();
                        } else {
                            error_log("Failed to move uploaded file: " . $tmp_name . " to " . $image_path);
                        }
                    } else {
                        error_log("Invalid image file type: " . $extension . " for file " . $image_name);
                    }
                }
            }

            $con->commit(); // تأكيد المعاملة
            $message = 'تمت إضافة المنتج والمخزون والأحجام بنجاح!';
            $message_type = 'success';
        } catch (Exception $e) {
            $con->rollback(); // التراجع عن المعاملة
            error_log("Add Product Error: " . $e->getMessage());
            $message = 'حدث خطأ أثناء إضافة المنتج: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة منتج</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            /* Light background */
            padding: 20px;
        }

        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            max-width: 800px;
            /* Limit width for better readability */
            margin: 30px auto;
            /* Center the container */
        }

        h1 {
            color: #007bff;
            /* Bootstrap primary blue */
            margin-bottom: 30px;
            text-align: center;
        }

        /* Style for form groups */
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
            /* Ensure label is on its own line */
        }

        .form-control,
        .form-select {
            margin-bottom: 15px;
            /* Space between form elements */
        }

        .form-check {
            margin-bottom: 15px;
        }

        .btn-primary {
            width: 100%;
            padding: 10px;
            font-size: 1.1rem;
            margin-top: 20px;
        }

        .btn-secondary {
            width: 100%;
            padding: 10px;
            font-size: 1.1rem;
            margin-top: 10px;
        }

        /* Specific styling for size options */
        #sizeOptions .form-check-inline {
            margin-left: 1rem;
            /* Adjust spacing for inline checkboxes */
        }
    </style>
</head>

<body>
    <div class="container">
        <h1 class="mb-4">إضافة منتج جديد</h1>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="./add_product.php" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="name" class="form-label">اسم المنتج:</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">الوصف:</label>
                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
            </div>

            <div class="mb-3">
                <label for="sku" class="form-label">الرقم التسلسلي (SKU):</label>
                <input type="text" class="form-control" id="sku" name="sku" required placeholder="رقم المنتج التسلسلي (SKU)">
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="is_serial_tracked" name="is_serial_tracked" value="1">
                <label class="form-check-label" for="is_serial_tracked">تتبع المنتج بالأرقام التسلسلية</label>
            </div>

            <div class="mb-3">
                <label for="category_id" class="form-label">القسم الرئيسي:</label>
                <select class="form-select" id="category_id" name="category_id" required onchange="loadSubcategories();toggleSizeOptions()">
                    <option value="">اختر قسم رئيسي</option>
                    <?php
                    // استخدام اتصال قاعدة البيانات
                    if (isset($con) && $con->ping()) {
                        $query = "SELECT * FROM categories WHERE parent_id IS NULL";
                        $result = $con->query($query);
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['category_name']) . "</option>";
                            }
                        } else {
                            error_log("Error fetching categories: " . $con->error);
                            echo "<option value=''>خطأ في تحميل الأقسام</option>";
                        }
                    } else {
                        echo "<option value=''>خطأ في الاتصال بقاعدة البيانات</option>";
                    }
                    ?>
                </select>
            </div>

            <div id="sizeOptions" class="mb-3" style="display:none;">
                <label class="form-label">اختر الأحجام المتوفرة:</label><br>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="sizes[]" id="sizeX" value="X">
                    <label class="form-check-label" for="sizeX">X</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="sizes[]" id="sizeL" value="L">
                    <label class="form-check-label" for="sizeL">L</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="sizes[]" id="sizeM" value="M">
                    <label class="form-check-label" for="sizeM">M</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="sizes[]" id="sizeXL" value="XL">
                    <label class="form-check-label" for="sizeXL">XL</label>
                </div>
            </div>

            <div class="mb-3">
                <label for="subcategory_id" class="form-label">القسم الفرعي:</label>
                <select class="form-select" id="subcategory_id" name="subcategory_id" onchange="loadSubsubcategories()">
                    <option value="">اختر قسم فرعي</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="subsubcategory_id" class="form-label">القسم التابع:</label>
                <select class="form-select" id="subsubcategory_id" name="subsubcategory_id">
                    <option value="">اختر قسم تابع</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">حالة المنتج:</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="active">نشط</option>
                    <option value="inactive">غير نشط</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="images" class="form-label">الصور (يمكنك إضافة أكثر من صورة):</label>
                <input type="file" class="form-control" id="images" name="images[]" accept="image/*" multiple>
            </div>

            <button type="submit" class="btn btn-primary">إضافة المنتج</button>
        </form>

        <button class="btn btn-secondary mt-3" onclick="window.location.href='../dashbord.php';">العودة للصفحة الرئيسية</button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src='./products_function.js'></script>
    <script>
        function toggleSizeOptions() {
            const categorySelect = document.getElementById('category_id');
            const sizeOptionsDiv = document.getElementById('sizeOptions');

            // تأكد من أن categorySelect.options[categorySelect.selectedIndex] ليس null
            if (categorySelect.selectedIndex !== -1 && categorySelect.options[categorySelect.selectedIndex].text.includes('ملابس')) {
                sizeOptionsDiv.style.display = 'block';
            } else {
                sizeOptionsDiv.style.display = 'none';
                const sizeCheckboxes = sizeOptionsDiv.querySelectorAll('input[type="checkbox"]');
                sizeCheckboxes.forEach(checkbox => checkbox.checked = false);
            }
        }

        // استدعاء الدالة عند تحميل الصفحة للتأكد من الحالة الصحيحة
        window.onload = function() {
            toggleSizeOptions();
            // هنا يجب أن يتم استدعاء دالة تحميل الأقسام الفرعية إذا كان هناك قسم رئيسي محدد مسبقًا (في حال التحرير مثلاً)
            // loadSubcategories(); // إذا كنت تستخدم هذا لملء الأقسام الفرعية عند تحميل الصفحة
        };
    </script>
</body>

</html>
<?php
// إغلاق اتصال قاعدة البيانات بعد انتهاء عرض الصفحة
if (isset($con) && $con) {
    $con->close();
}
?>