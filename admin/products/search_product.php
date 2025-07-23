<?php
include('../connect_DB.php'); // الاتصال بقاعدة البيانات

// التحقق من صلاحيات المستخدم
session_start();

if (!isset($_SESSION['user_name']) || ($_SESSION['role_id'] != 1 && $_SESSION['role_id'] != 3)) {
    echo "<script>alert('لا توجد صلاحيات لإجراء هذه العملية.'); window.location.href = '../dashbord.php';</script>";
    exit();
}

// استقبال مدخلات البحث والفلترة
$search_query = isset($_GET['search_query']) ? $_GET['search_query'] : '';
$search_type = isset($_GET['search_type']) ? $_GET['search_type'] : 'name';
$min_price = isset($_GET['min_price']) ? $_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) ? $_GET['max_price'] : '';
$sort_price = isset($_GET['sort_price']) ? $_GET['sort_price'] : ''; // إضافة ترتيب السعر

// بناء استعلام SQL ديناميكي بناءً على الفلترة
$query = "SELECT p.id, p.name, p.price, 
                 COALESCE(c1.category_name, 'غير محدد') AS subcategory, 
                 COALESCE(c2.category_name, 'غير محدد') AS main_category 
          FROM products p
          LEFT JOIN categories c1 ON p.subcategory_id = c1.id
          LEFT JOIN categories c2 ON p.category_id = c2.id -- ربط مباشر بالقسم الرئيسي
          WHERE 1=1";

// تطبيق الفلترة
$params = [];
$types = "";

// البحث حسب النوع المختار
if (!empty($search_query)) {
    if ($search_type == "id") {
        $query .= " AND p.id = ?";
        $types .= "i";
        $params[] = $search_query;
    } elseif ($search_type == "name") {
        $query .= " AND p.name LIKE ?";
        $types .= "s";
        $params[] = "%" . $search_query . "%";
    } elseif ($search_type == "subcategory") {
        $query .= " AND c1.category_name LIKE ?";
        $types .= "s";
        $params[] = "%" . $search_query . "%";
    } elseif ($search_type == "category") {
        $query .= " AND c2.category_name LIKE ?";
        $types .= "s";
        $params[] = "%" . $search_query . "%";
    }
}

// فلترة السعر
if (!empty($min_price) && is_numeric($min_price)) {
    $query .= " AND p.price >= ?";
    $types .= "d";
    $params[] = $min_price;
}

if (!empty($max_price) && is_numeric($max_price)) {
    $query .= " AND p.price <= ?";
    $types .= "d";
    $params[] = $max_price;
}

// إضافة شرط ترتيب السعر إذا كان مفعلاً
if (!empty($sort_price) && ($sort_price == 'asc' || $sort_price == 'desc')) {
    $query .= " ORDER BY p.price " . ($sort_price == 'asc' ? 'ASC' : 'DESC');
}

// تنفيذ الاستعلام فقط إذا تم إرسال معايير بحث أو فلترة
$results_found = false;
$product_results = [];

// إذا كان هناك بحث أو فلترة أو طلب عرض "أعلى 5" (رغم أنه غير مطبق حاليا في الاستعلام)
// يجب أن يكون هناك معايير لكي يتم تنفيذ الاستعلام وعرض النتائج.
// هنا، سنقوم بتنفيذ الاستعلام دائمًا لعرض الجدول إذا كانت الصفحة مفتوحة بالكامل.
// إذا كنت تريد عرض الجدول فقط بعد البحث، قم بتغليف كتلة التنفيذ بشرط $_GET['submit_search'] أو ما شابه.

// تحضير الاستعلام وتنفيذه
$stmt = $con->prepare($query);
if ($stmt) { // تحقق من أن التحضير تم بنجاح
    if (!empty($types)) {
        // استخدام call_user_func_array للتعامل مع bind_param ديناميكيًا
        $bind_names = array_merge([$types], $params);
        call_user_func_array([$stmt, 'bind_param'], refValues($bind_names));
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $results_found = true;
        while ($row = $result->fetch_assoc()) {
            $product_results[] = $row;
        }
    }
    $stmt->close();
} else {
    error_log("Failed to prepare statement: " . $con->error);
}

// دالة مساعدة لـ bind_param
function refValues($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) // PHP 5.3+
    {
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بحث المنتجات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa; /* Light background */
            padding: 20px;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            max-width: 900px; /* Adjust width for better form/table display */
            margin: 30px auto; /* Center the container */
        }
        h2 {
            color: #007bff; /* Bootstrap primary blue */
            margin-bottom: 30px;
            text-align: center;
        }
        .form-control, .form-select {
            margin-bottom: 15px;
        }
        .btn {
            margin-top: 10px; /* Space above buttons */
        }
        .table-responsive {
            margin-top: 30px;
        }
        .no-results-message {
            text-align: center;
            padding: 20px;
            font-size: 1.1rem;
            color: #6c757d;
        }
    </style>
</head>
<body>

    <div class="container">
        <h2 class="mb-4">بحث المنتجات</h2>

        <form method="GET" action="search_product.php" class="mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="search_query" class="form-label">كلمة البحث:</label>
                    <input type="text" class="form-control" id="search_query" name="search_query" placeholder="ابحث عن منتج..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="col-md-4">
                    <label for="search_type" class="form-label">نوع البحث:</label>
                    <select class="form-select" id="search_type" name="search_type">
                        <option value="name" <?php if ($search_type == "name") echo "selected"; ?>>حسب الاسم</option>
                        <option value="id" <?php if ($search_type == "id") echo "selected"; ?>>حسب ID</option>
                        <option value="subcategory" <?php if ($search_type == "subcategory") echo "selected"; ?>>حسب القسم الفرعي</option>
                        <option value="category" <?php if ($search_type == "category") echo "selected"; ?>>حسب القسم الرئيسي</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="sort_price" class="form-label">ترتيب السعر:</label>
                    <select class="form-select" id="sort_price" name="sort_price">
                        <option value="">لا يوجد ترتيب</option>
                        <option value="asc" <?php if ($sort_price == 'asc') echo 'selected'; ?>>من الأقل إلى الأعلى</option>
                        <option value="desc" <?php if ($sort_price == 'desc') echo 'selected'; ?>>من الأعلى إلى الأقل</option>
                    </select>
                </div>
            </div>
            
            <div class="row g-3 mt-2">
                <div class="col-md-4">
                    <label for="min_price" class="form-label">السعر الأدنى:</label>
                    <input type="number" class="form-control" id="min_price" name="min_price" placeholder="أقل سعر" value="<?php echo htmlspecialchars($min_price); ?>" step="0.01">
                </div>
                <div class="col-md-4">
                    <label for="max_price" class="form-label">السعر الأعلى:</label>
                    <input type="number" class="form-control" id="max_price" name="max_price" placeholder="أعلى سعر" value="<?php echo htmlspecialchars($max_price); ?>" step="0.01">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i> بحث
                    </button>
                </div>
            </div>
        </form>

        <?php if (!empty($product_results)): ?>
            <h3 class="mt-4 mb-3 text-secondary">نتائج البحث:</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>الرقم</th>
                            <th>اسم المنتج</th>
                            <th>القسم الفرعي</th>
                            <th>القسم الرئيسي</th>
                            <th>السعر</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($product_results as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['subcategory']); ?></td>
                                <td><?php echo htmlspecialchars($row['main_category']); ?></td>
                                <td><?php echo number_format($row['price'], 2); ?> $</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="no-results-message">لا توجد نتائج للبحث بالمعايير المحددة.</p>
        <?php endif; ?>

        <div class="d-flex justify-content-end mt-4">
            <button class="btn btn-secondary" onclick="window.location.href='../dashbord.php';">
                <i class="fas fa-arrow-right-to-bracket me-2"></i> العودة للصفحة الرئيسية
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

<?php
// إغلاق اتصال قاعدة البيانات بعد انتهاء عرض الصفحة
if (isset($con) && $con->ping()) {
    $con->close();
}
?>