<?php
include('../connect_DB.php'); // الاتصال بقاعدة البيانات
include('./products_functions.php'); // تضمين ملف الدوال

$products = getAllProducts_OnePhoto(); // استدعاء الدالة للحصول على جميع المنتجات

// جلب الأحجام لكل المنتجات دفعة واحدة
$sql_sizes = "SELECT product_id, GROUP_CONCAT(size ORDER BY size SEPARATOR ', ') AS sizes_list FROM product_sizes GROUP BY product_id";
$result_sizes = $con->query($sql_sizes);

$sizes_map = [];
if ($result_sizes) { // التحقق من نجاح الاستعلام
    while ($row_size = $result_sizes->fetch_assoc()) {
        $sizes_map[$row_size['product_id']] = $row_size['sizes_list'];
    }
} else {
    error_log("Error fetching product sizes: " . $con->error);
}

// إغلاق الاتصال بقاعدة البيانات إذا لم يعد مطلوبًا (إذا كانت الدالة getAllProducts_OnePhoto لا تغلقه)
// تأكد من أن الاتصال لا يزال مفتوحًا إذا كانت getAllProducts_OnePhoto تستخدمه ولم تغلقه
// إذا كانت getAllProducts_OnePhoto تغلق الاتصال، ستحتاج لإعادة فتحه أو تمرير الاتصال لها.
// في هذا المثال، سأفترض أن connect_DB.php يفتح الاتصال وgetAllProducts_OnePhoto تستخدم $con وتتركه مفتوحًا.
// ولكن يفضل إغلاقه هنا بعد كل العمليات.
if (isset($con) && $con) {
    // $con->close(); // لا تغلق هنا إذا كان هناك مزيد من العمليات أو إذا كانت الدالة تعتمد عليه
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>قائمة المنتجات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa; /* Light background */
            padding: 20px;
        }
        .container-fluid { /* Use container-fluid for full width */
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            margin: 30px auto;
        }
        h1 {
            color: #007bff; /* Bootstrap primary blue */
            margin-bottom: 30px;
            text-align: center;
        }
        .product-image {
            width: 80px; /* Adjust as needed */
            height: 80px;
            object-fit: cover; /* Ensures image covers the area without distortion */
            border-radius: 4px;
        }
        .table-responsive {
            margin-top: 20px;
        }
        /* Adjust column width for better readability if needed */
        #Products_Table th,
        #Products_Table td {
            vertical-align: middle; /* Center content vertically */
            padding: 0.75rem; /* Default Bootstrap padding */
        }
        #Products_Table th:nth-child(1), /* اسم المنتج */
        #Products_Table td:nth-child(1) {
            width: 15%;
        }
        #Products_Table th:nth-child(2), /* الوصف */
        #Products_Table td:nth-child(2) {
            width: 20%;
        }
        #Products_Table th:nth-child(3), /* السعر */
        #Products_Table td:nth-child(3) {
            width: 10%;
        }
        #Products_Table th:nth-child(4), /* القسم */
        #Products_Table td:nth-child(4) {
            width: 10%;
        }
        #Products_Table th:nth-child(5), /* القسم الفرعي */
        #Products_Table td:nth-child(5) {
            width: 10%;
        }
        #Products_Table th:nth-child(6), /* الحالة */
        #Products_Table td:nth-child(6) {
            width: 8%;
        }
        #Products_Table th:nth-child(7), /* الصورة */
        #Products_Table td:nth-child(7) {
            width: 10%;
        }
        #Products_Table th:nth-child(8), /* الاحجام */
        #Products_Table td:nth-child(8) {
            width: 10%;
        }
        #Products_Table th:nth-child(9), /* تاريخ الإنشاء */
        #Products_Table td:nth-child(9) {
            width: 12%;
        }
        #Products_Table th:nth-child(10), /* تفاصيل */
        #Products_Table td:nth-child(10) {
            width: 5%;
        }
        .views {
            color: #0d6efd; /* Bootstrap primary blue for links */
            text-decoration: none;
            font-weight: bold;
        }
        .views:hover {
            text-decoration: underline;
        }
        .no-products-message {
            text-align: center;
            padding: 30px;
            font-size: 1.2rem;
            color: #6c757d; /* Bootstrap secondary grey */
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <h1 class="mb-4">قائمة المنتجات</h1>

        <div class="d-flex justify-content-between mb-3">
            <button class="btn btn-primary" onclick="window.location.href='./add_product.php';">
                <i class="fas fa-plus-circle me-2"></i> إضافة منتج جديد
            </button>
            <button class="btn btn-info text-white" onclick="downloadPDF()">
                <i class="fas fa-file-pdf me-2"></i> تحميل كـ PDF
            </button>
        </div>

        <?php if (count($products) > 0): ?>
            <div class="table-responsive">
                <table id="Products_Table" class="table table-striped table-hover table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>اسم المنتج</th>
                            <th>الوصف</th>
                            <th>السعر</th>
                            <th>القسم</th>
                            <th>القسم الفرعي</th>
                            <th>الحالة</th>
                            <th>الصورة</th>
                            <th>الأحجام</th>
                            <th>تاريخ الإنشاء</th>
                            <th>تفاصيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                <td><?php echo number_format($row['price'], 2); ?> ج.م</td>
                                <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['subsubcategory_name'] ? $row['subsubcategory_name'] : 'لا يوجد'); ?></td>
                                <td>
                                    <span class="badge <?php echo $row['status'] == 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $row['status'] == 'active' ? 'نشط' : 'غير نشط'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['image_path']): ?>
                                        <img src="./<?php echo htmlspecialchars($row['image_path']); ?>" alt="صورة المنتج" class="product-image">
                                    <?php else: ?>
                                        <span class="text-muted">لا توجد صورة</span>
                                    <?php endif; ?>
                                </td>
                                <td> 
                                    <?= isset($sizes_map[$row['id']]) ? htmlspecialchars($sizes_map[$row['id']]) : 'لا توجد أحجام' ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                <td class="text-center">
                                    <a class='views btn btn-sm btn-outline-info' href="./product_view.php?id=<?php echo $row['id']; ?>">
                                        <i class="fas fa-info-circle"></i> عرض
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="no-products-message">لا توجد منتجات لعرضها.</p>
        <?php endif; ?>

        <div class="d-flex justify-content-end mt-4">
            <button class="btn btn-secondary" onclick="window.location.href='../dashbord.php';">
                <i class="fas fa-arrow-right-to-bracket me-2"></i> العودة للصفحة الرئيسية
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsIDtaDY9PEhhBwjpNvV6XQ5DyirO1AyNwvUdQKP+zGaGxQrfIfgvfRPfvFXXgMSkYXTKABhHqaLwiAFEFL9Qw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    
    <script>
        function downloadPDF() {
            const element = document.getElementById('Products_Table');
            
            // تهيئة الخيارات لـ html2pdf
            const opt = {
                margin:       10,
                filename:     'قائمة_المنتجات.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, logging: true, dpi: 192, letterRendering: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' } // 'landscape' للعرض الأفقي
            };

            html2pdf().set(opt).from(element).save();
        }
    </script> 
</body>

</html>
<?php
// إغلاق اتصال قاعدة البيانات بعد انتهاء عرض الصفحة
if (isset($con) && $con->ping()) {
    $con->close();
}
?>