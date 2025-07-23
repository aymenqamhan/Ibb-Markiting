<?php
include('../connect_DB.php');

if (isset($_GET['subcategory_id'])) {
    $subcategory_id = $_GET['subcategory_id'];

    // جلب الأقسام التابعة بناءً على القسم الفرعي
    $query = "SELECT * FROM categories WHERE parent_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $subcategory_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $subsubcategories = [];
    while ($row = $result->fetch_assoc()) {
        $subsubcategories[] = $row;
    }

    echo json_encode($subsubcategories);
}
?>