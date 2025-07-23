<?php
include('../connect_DB.php');

function getAllProducts_OnePhoto() {
    global $con; // يجب أن يكون $con هو متغير اتصال قاعدة البيانات

    // الاستعلام المصحح لجلب المنتجات مع إجمالي الكمية من جدول المخزون
    // وصورة واحدة لكل منتج.
    $query = "
        SELECT 
            p.id, 
            p.name, 
            p.description, 
            p.sku, 
            p.status, 
            COALESCE(SUM(i.quantity), 0) AS total_stock_quantity, -- تم تعديل هنا: جلب الكمية من جدول المخزون
            c.category_name,  -- جلب اسم القسم الرئيسي
            sc.category_name AS subcategory_name, -- جلب اسم القسم الفرعي
            ssc.category_name AS subsubcategory_name, -- جلب اسم القسم التابع
            (
                SELECT image_path 
                FROM products_images 
                WHERE product_id = p.id 
                ORDER BY id ASC 
                LIMIT 1
            ) AS image_path,
            p.created_at,
            -- لا يوجد سعر افتراضي للمنتج في جدول المنتجات، يجب جلبه من جدول المخزون (أو تحديد سعر موحد)
            -- إذا كان لكل حجم سعر مختلف، ستحتاج لمنطق أكثر تعقيداً
            -- حالياً، سأضيف سعر افتراضي 0.00 لتجنب الأخطاء إذا لم يكن موجوداً
            -- الأفضل أن يكون لكل منتج سعر بيع (selling_price) افتراضي في جدول المخزون
            -- أو أن تقوم باحتساب متوسط السعر إذا كان هناك عدة أسعار لأحجام مختلفة
            COALESCE(AVG(i.selling_price), 0.00) AS price -- جلب متوسط سعر البيع من المخزون
        FROM 
            products p
        LEFT JOIN 
            inventory i ON p.id = i.product_id
        LEFT JOIN 
            categories c ON p.category_id = c.id
        LEFT JOIN 
            categories sc ON p.subcategory_id = sc.id
        LEFT JOIN 
            categories ssc ON p.subsubcategory_id = ssc.id
        GROUP BY 
            p.id, p.name, p.description, p.sku, p.status, p.created_at, 
            c.category_name, sc.category_name, ssc.category_name
        ORDER BY 
            p.created_at DESC";

    // تنفيذ الاستعلام
    $result = $con->query($query); // هذا هو السطر 51 المفترض الذي يسبب الخطأ في الصورة

    if (!$result) {
        // التعامل مع الخطأ إذا فشل الاستعلام
        error_log("SQL Error in getAllProducts_OnePhoto: " . $con->error);
        return []; 
    }

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    return $products;
}

function getAllProducts_NotPhoto() {
    global $con;

    // التحقق من الاتصال بقاعدة البيانات
    if (!$con) {
        die("خطأ في الاتصال بقاعدة البيانات: " . mysqli_connect_error());
    }

    // الاستعلام لاسترجاع جميع المنتجات بدون الصور
    $sql = "SELECT p.id, p.name, p.description, p.price, p.stock_quantity, p.status, c.category_name, s.name AS supplier_name, p.created_at
            FROM products p
            JOIN categories c ON p.category_id = c.id
            JOIN suppliers s ON p.supplier_id = s.id";
    
    // تنفيذ الاستعلام والتحقق من وجود أخطاء
    $result = $con->query($sql);
    if (!$result) {
        die("خطأ في الاستعلام: " . $con->error);
    }

    // تحويل النتائج إلى مصفوفة وإرجاعها
    $products = [];
    while ($row = $result->fetch_assoc()) {
        // لا داعي لإضافة الصور في هذا الكود
        $products[] = $row;
    }
    return $products;
}

?>