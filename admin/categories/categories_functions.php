<?php
include(__DIR__ . '/../../include/connect_DB.php');

// جلب كل الأقسام
function getAllCategories() {
    global $con;

    if (!$con) {
        die("خطأ في الاتصال بقاعدة البيانات!");
    }

    $sql = "SELECT id, category_name, parent_id FROM categories";
    $result = $con->query($sql);

    if (!$result) {
        die("خطأ في الاستعلام: " . $con->error);
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

// جلب اسم القسم الرئيسي
function getCategoryName($parent_id) {
    global $con;
    
    if (!$parent_id) {
        return null;
    }

    $stmt = $con->prepare("SELECT category_name FROM categories WHERE id = ?");
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $category_name = ($result->num_rows > 0) ? $result->fetch_assoc()['category_name'] : null;
   
    $stmt->close();
    return $category_name;
}

// إضافة قسم جديد
function addCategory($category_name, $parent_id) {
    global $con;

    if (empty($category_name)) {
        return "يرجى إدخال اسم القسم.";
    }

    $query = "INSERT INTO categories (category_name, parent_id) VALUES (?, ?)";
    $stmt = $con->prepare($query);
    $stmt->bind_param("si", $category_name, $parent_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result ? "تم إضافة القسم بنجاح." : "حدث خطأ أثناء إضافة القسم.";
}

// جلب الأقسام الفرعية بناءً على القسم الرئيسي
function get_subsubcategories($parent_id) {
    global $con;

    // استعلام للحصول على الأقسام الفرعية للأقسام الفرعية بناءً على parent_id
    $stmt = $con->prepare("SELECT id, category_name FROM categories WHERE parent_id = ?");
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // تخزين النتائج في مصفوفة
    $subsubcategories = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();
    return $subsubcategories;
}

?>
