<?php

include('D:\xampp\htdocs\new_ibb\include\connect_DB.php');

// جلب المنتجات النشطة والتي لديها كمية متوفرة في المخزون
$query = "
    SELECT 
        p.id AS product_id, 
        p.name, 
        p.description, 
        p.price, 
        p.status, 
        (
            SELECT pi.image_path 
            FROM products_images pi 
            WHERE pi.product_id = p.id 
            ORDER BY pi.is_main_image DESC, pi.id ASC 
            LIMIT 1
        ) AS image_path,
        inv.id AS inventory_id,         
        inv.quantity AS stock_quantity  
    FROM products p
    JOIN inventory inv ON p.id = inv.product_id 
    WHERE p.status = 'active' 
    AND inv.quantity > 0 
    AND p.category_id IN (
        SELECT id FROM categories WHERE id in (
            SELECT id FROM categories WHERE id = 1
        ) OR id = 3
    )
    ORDER BY p.name ASC
";

$result = $con->query($query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IBB Marketing - المنتجات</title>
    <link rel="stylesheet" href="/Style/mainscrain.css">
    <link rel="stylesheet" href="/Style/cards.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body>
    <header>
        <?php include("D:/xampp/htdocs/new_ibb/include/head.php"); ?>
        <?php include("D:/xampp/htdocs/new_ibb/admin/categories/categories_functions.php");
        $all_categories = getAllCategories();
        ?>

    </header>
    <main>
        <section id="home" class="hero">
            <hr>
            <h1>حقق أهدافك بسهولة مع IBB Marketing</h1>
            <p>نقدم لك أفضل الأدوات والاستراتيجيات لتسويق منتجاتك وزيادة مبيعاتك، لأن نجاحك هو هدفنا الأول.</p>
            <button class="button type1">
                <span class="btn-txt">IBB Marketing</span>
            </button>
        </section>

        <div class="product-container">
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $inventory_id = $row['inventory_id'] ?? null;
                    $stock_quantity = $row['stock_quantity'] ?? 0;

                    $image_base_path = '/NEW_IBB/admin/products/';
                    $image = (!empty($row['image_path'])) ? $image_base_path . htmlspecialchars($row['image_path']) : '/NEW_IBB/default_image.jpg';

                    $name = htmlspecialchars($row['name'] ?? 'اسم غير متوفر');
                    $description = htmlspecialchars($row['description'] ?? 'وصف غير متوفر');
                    $price = number_format($row['price'] ?? 0, 2);

                    $button_disabled_class = (empty($inventory_id) || $stock_quantity <= 0) ? 'disabled' : '';
                    $cartLink = "/NEW_IBB/admin/products/product_view.php?id=" . htmlspecialchars($row['product_id']);
            ?>
                    <div class="product-card">
                        <div class="product-status">جديد</div>
                        <img class="product-image" src="<?php echo $image; ?>" alt="<?php echo $name; ?>" onerror="this.src='/new_ibb/default_image.jpg'">

                        <h3 class="product-title"><?php echo $name; ?></h3>

                        <div class="rating">★★★★☆</div>

                        <p class="product-description"><i class="fas fa-info-circle"></i> <?php echo $description; ?></p>

                        <span class="product-price"><i class="fas fa-tag"></i><?php echo $price; ?> $</span>

                        <div class="product-buttons">
                            <a href="<?php echo $cartLink; ?>" class="cart-btn <?php echo $button_disabled_class; ?>">
                                <i class="fas fa-cart-plus"></i> أضف إلى السلة
                            </a>
                        </div>
                    </div>
            <?php
                }
            } else {
                echo "<p>لا توجد منتجات نشطة ومتوفرة لعرضها حاليًا.</p>";
            }
            ?>
        </div>
    </main>

    <footer>
        <?php include("D:/xampp/htdocs/new_ibb/include/footer.php"); ?>
    </footer>
</body>

</html>