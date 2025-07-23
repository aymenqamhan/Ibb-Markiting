<?php
include('../connect_DB.php'); // الاتصال بقاعدة البيانات
// التحقق من استلام ID المنتج
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // جلب بيانات المنتج
    $query = "SELECT * FROM products WHERE id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
    } else {
        echo "<script>alert('المنتج غير موجود!'); window.location.href = './list_products.php';</script>";
        exit();
    }
} else {
    echo "<script>alert('لم يتم تحديد المنتج!'); window.location.href = './list_products.php';</script>";
    exit();
}

// جلب الصورة من جدول products_images
$image = '';
$query = "SELECT * FROM products_images WHERE product_id = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $image_data = $result->fetch_assoc();
    $image = $image_data['image_path']; // استرجاع الصورة الموجودة
}

// جلب الأقسام الفرعية بناءً على القسم الرئيسي
$subcategories = [];
if ($product['category_id']) {
    $query = "SELECT * FROM categories WHERE parent_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $product['category_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subcategories[] = $row;
    }
}

// جلب الأقسام التابعة بناءً على القسم الفرعي
$subsubcategories = [];
if ($product['subcategory_id']) {
    $query = "SELECT * FROM categories WHERE parent_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $product['subcategory_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subsubcategories[] = $row;
    }
}

// تحديث المنتج في قاعدة البيانات
if (isset($_POST['update_product'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $category_id = $_POST['category_id'];
    $subcategory_id = $_POST['subcategory_id'];
    $subsubcategory_id = isset($_POST['subsubcategory_id']) ? $_POST['subsubcategory_id'] : null; // تأكد من وجود القيمة
    $status = $_POST['status'];

    // معالجة الصورة
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        // إذا كانت صورة جديدة
        $image_path = 'uploads/' . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], $image_path);

        // حذف الصورة القديمة من جدول products_images
        $delete_old_image_query = "DELETE FROM products_images WHERE product_id = ?";
        $stmt = $con->prepare($delete_old_image_query);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // إضافة الصورة الجديدة إلى جدول products_images
        $update_image_query = "INSERT INTO products_images (product_id, image_path) VALUES (?, ?)";
        $stmt = $con->prepare($update_image_query);
        $stmt->bind_param("is", $id, $image_path);
        $stmt->execute();

        // تحديث المسار في المتغير
        $image = $image_path;
    } else {
        // إذا لم يتم رفع صورة جديدة، احتفظ بالصورة القديمة
        $image_path = $image;
    }

    // تحديث المنتج
    $update_query = "UPDATE products SET name=?, description=?, category_id=?, subcategory_id=?, subsubcategory_id=?,status=? WHERE id=?";
    $stmt = $con->prepare($update_query);
    $stmt->bind_param("ssiiisi", $name, $description, $category_id, $subcategory_id, $subsubcategory_id, $status, $id);

    if ($stmt->execute()) {
        echo "<script>alert('تم تحديث المنتج بنجاح!'); window.location.href = './list_products.php';</script>";
    } else {
        echo "<script>alert('حدث خطأ أثناء التحديث!');</script>";
    }
}

?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تعديل المنتج</title>
    <link rel="stylesheet" href="./products_styles.css">
</head>

<body>
    <h2>تعديل المنتج</h2>
    <form method="POST" action="" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">

    <label>اسم المنتج:</label>
    <input type="text" name="name" value="<?php echo $product['name']; ?>" required><br>

    <label>الوصف:</label>
    <textarea name="description" required><?php echo $product['description']; ?></textarea><br>

    <label>القسم الرئيسي:</label>
    <select name="category_id" id="category_id" required onchange="loadSubcategories()">
        <option value="">اختر قسم رئيسي</option>
        <?php
        $query = "SELECT * FROM categories WHERE parent_id IS NULL";
        $result = $con->query($query);
        while ($row = $result->fetch_assoc()) {
            $selected = ($row['id'] == $product['category_id']) ? "selected" : "";
            echo "<option value='{$row['id']}' $selected>{$row['category_name']}</option>";
        }
        ?>
    </select><br>

    <label>القسم الفرعي:</label>
    <select name="subcategory_id" id="subcategory_id" required onchange="loadSubsubcategories()">
        <option value="">اختر قسم فرعي</option>
        <?php
        foreach ($subcategories as $row) {
            $selected = ($row['id'] == $product['subcategory_id']) ? "selected" : "";
            echo "<option value='{$row['id']}' $selected>{$row['category_name']}</option>";
        }
        ?>
    </select><br>

    <label>القسم التابع:</label>
    <select name="subsubcategory_id" id="subsubcategory_id">
        <option value="">اختر قسم تابع</option>
        <?php
        foreach ($subsubcategories as $row) {
            $selected = ($row['id'] == $product['subsubcategory_id']) ? "selected" : "";
            echo "<option value='{$row['id']}' $selected>{$row['category_name']}</option>";
        }
        ?>
    </select><br>

    <label>حالة المنتج:</label>
    <select name="status" required>
        <option value="active" <?php echo ($product['status'] == 'active') ? "selected" : ""; ?>>نشط</option>
        <option value="inactive" <?php echo ($product['status'] == 'inactive') ? "selected" : ""; ?>>غير نشط</option>
    </select><br>

    <label>الصورة:</label>
    <input type="file" name="image" accept="image/*"><br>
    <img src="<?php echo $image ? $image : 'default_image.jpg'; ?>" width="100" height="100"><br>

    <button type="submit" name="update_product">تحديث المنتج</button>
    </form>
    <script src='./products_function.js?v=<?php echo time(); ?>'></script>
</body>
</html>
