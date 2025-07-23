<?php
include('../connect_DB.php');

if (isset($_GET['category_id'])) {
    $category_id = $_GET['category_id'];

    // جلب الأقسام الفرعية بناءً على القسم الرئيسي
    $query = "SELECT * FROM categories WHERE parent_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $subcategories = [];
    while ($row = $result->fetch_assoc()) {
        $subcategories[] = $row;
    }

    echo json_encode($subcategories);
}
?>
