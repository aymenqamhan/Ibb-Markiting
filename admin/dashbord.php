<?php
session_start();
include('./connect_DB.php'); // تأكد أن المسار صحيح لملف الاتصال بقاعدة البيانات

// جلب صلاحية المستخدم من الجلسة
$role_id = isset($_SESSION['role_id']) ? $_SESSION['role_id'] : 0;
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'غير معروف';

// معالجة تسجيل الخروج
if(isset($_GET['logout']) && $_GET['logout'] == 'out'){
    session_destroy();
    // تأكد من المسار الصحيح لصفحة تسجيل الدخول
    header("Location:./login/login_user.php"); 
    exit;
}

// ****** جلب البيانات من قاعدة البيانات لعرضها في الكروت ******
$total_users = 0;
$total_orders = 0;
$total_products = 0;
$total_categories = 0;
$total_suppliers = 0;
$total_purchase_orders = 0;
$total_sales_invoices = 0; // افتراض وجود جدول للفواتير المبيعات
$total_inventory_items = 0; // عدد الأصناف في المخزون

if ($con) { // تحقق إذا كان الاتصال بقاعدة البيانات ناجحًا
    // عدد المستخدمين
    $sql_users = "SELECT COUNT(id) AS total_users FROM user_tb";
    $result_users = mysqli_query($con, $sql_users);
    if ($result_users) {
        $data_users = mysqli_fetch_assoc($result_users);
        $total_users = $data_users['total_users'];
    }

    // عدد الطلبات (الطلبات الكلية، أو الطلبات قيد الانتظار مثلاً)
    $sql_orders = "SELECT COUNT(id) AS total_orders FROM orders"; // افترضت اسم الجدول 'orders'
    $result_orders = mysqli_query($con, $sql_orders);
    if ($result_orders) {
        $data_orders = mysqli_fetch_assoc($result_orders);
        $total_orders = $data_orders['total_orders'];
    }

    // عدد المنتجات
    $sql_products = "SELECT COUNT(id) AS total_products FROM products"; // افترضت اسم الجدول 'products'
    $result_products = mysqli_query($con, $sql_products);
    if ($result_products) {
        $data_products = mysqli_fetch_assoc($result_products);
        $total_products = $data_products['total_products'];
    }

    // عدد الأقسام
    $sql_categories = "SELECT COUNT(id) AS total_categories FROM categories"; // افترضت اسم الجدول 'categories'
    $result_categories = mysqli_query($con, $sql_categories);
    if ($result_categories) {
        $data_categories = mysqli_fetch_assoc($result_categories);
        $total_categories = $data_categories['total_categories'];
    }

    // عدد الموردين
    $sql_suppliers = "SELECT COUNT(id) AS total_suppliers FROM suppliers"; // افترضت اسم الجدول 'suppliers'
    $result_suppliers = mysqli_query($con, $sql_suppliers);
    if ($result_suppliers) {
        $data_suppliers = mysqli_fetch_assoc($result_suppliers);
        $total_suppliers = $data_suppliers['total_suppliers'];
    }

    // عدد أوامر الشراء
    $sql_purchase_orders = "SELECT COUNT(id) AS total_purchase_orders FROM purchase_orders"; // افترضت اسم الجدول 'purchase_orders'
    $result_purchase_orders = mysqli_query($con, $sql_purchase_orders);
    if ($result_purchase_orders) {
        $data_purchase_orders = mysqli_fetch_assoc($result_purchase_orders);
        $total_purchase_orders = $data_purchase_orders['total_purchase_orders'];
    }
    
    // عدد فواتير المبيعات (يمكنك افتراض جدول للمبيعات)
    $sql_sales_invoices = "SELECT COUNT(id) AS total_sales_invoices FROM purchase_invoices"; // افترضت اسم الجدول 'sales_invoices'
    $result_sales_invoices = mysqli_query($con, $sql_sales_invoices);
    if ($result_sales_invoices) {
        $data_sales_invoices = mysqli_fetch_assoc($result_sales_invoices);
        $total_sales_invoices = $data_sales_invoices['total_sales_invoices'];
    }

    // عدد الأصناف في المخزون (ليس كميات، بل عدد الأصناف المختلفة)
    $sql_inventory_items = "SELECT COUNT(DISTINCT id) AS total_inventory_items FROM inventory"; // افترضت اسم الجدول 'inventory'
    $result_inventory_items = mysqli_query($con, $sql_inventory_items);
    if ($result_inventory_items) {
        $data_inventory_items = mysqli_fetch_assoc($result_inventory_items);
        $total_inventory_items = $data_inventory_items['total_inventory_items'];
    }


    mysqli_close($con); // إغلاق الاتصال بعد جلب البيانات
} else {
    error_log("Failed to connect to database in dashbord.php: " . mysqli_connect_error());
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة الإدارة - NEW IBB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        body {
            background-color: #f0f2f5; /* Light grey background */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            display: flex;
            min-height: 100vh; /* Full viewport height */
        }
        .sidebar {
            width: 280px; /* Fixed width for sidebar */
            background-color: #343a40; /* Dark background for sidebar */
            color: #ffffff;
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            flex-shrink: 0; /* Prevent shrinking */
        }
        .sidebar h2 {
            font-size: 1.8rem;
            margin-bottom: 25px;
            text-align: center;
            color: #0d6efd; /* Bootstrap primary blue for title */
            font-weight: bold;
        }
        .user-info {
            background-color: #495057;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: center;
        }
        .user-info p {
            margin-bottom: 5px;
            color: #e9ecef;
            font-size: 0.95rem;
        }
        .user-info p:last-child {
            margin-bottom: 0;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1; /* Allow menu to take available space */
        }
        .sidebar ul li {
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            gap: 10px; /* Space between icon and text */
            font-size: 1.05rem;
        }
        .sidebar ul li:hover {
            background-color: #495057;
            transform: translateX(5px);
        }
        .sidebar ul li i {
            color: #adb5bd; /* Light grey for icons */
        }
        .sidebar ul li:hover i {
            color: #ffffff;
        }

        #content {
            flex-grow: 1; /* Content takes remaining space */
            padding: 30px;
            overflow-y: auto; /* Enable scrolling for content if it overflows */
        }

        /* Logo styles */
        .dashboard-logo {
            display: block;
            margin: 0 auto 30px auto; /* Center the logo and add space below */
            max-width: 350px; /* Adjust as needed */
            height: auto;
        }

        /* Card styles for dashboard statistics */
        .stat-card {
            border-radius: 0.75rem;
            text-align: center;
            padding: 1.5rem;
            color: white;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            margin-bottom: 1.5rem; /* Space between cards */
            display: flex; /* Use flexbox for content alignment */
            flex-direction: column; /* Stack icon, value, label vertically */
            justify-content: center; /* Center content vertically */
            align-items: center; /* Center content horizontally */
            min-height: 150px; /* Ensure consistent card height */
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.15);
        }
        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        .stat-card .label {
            font-size: 1rem;
            opacity: 0.9;
        }

        /* Card background colors */
        .bg-primary-gradient {
            background: linear-gradient(45deg, #0d6efd, #0b5ed7);
        }
        .bg-success-gradient {
            background: linear-gradient(45deg, #198754, #157347);
        }
        .bg-info-gradient {
            background: linear-gradient(45deg, #0dcaf0, #0aa3c2);
        }
        .bg-warning-gradient {
            background: linear-gradient(45deg, #ffc107, #e0a800);
            color: #343a40 !important; /* Ensure text is dark on yellow background */
        }
        .bg-danger-gradient {
            background: linear-gradient(45deg, #dc3545, #c82333);
        }
        .bg-secondary-gradient {
            background: linear-gradient(45deg, #6c757d, #5a6268);
        }
        .bg-dark-gradient {
            background: linear-gradient(45deg, #343a40, #212529);
        }
        .bg-purple-gradient {
            background: linear-gradient(45deg, #6f42c1, #5e33ae);
        }
        .bg-teal-gradient {
            background: linear-gradient(45deg, #20c997, #17a2b8);
        }


        /* Responsive adjustments */
        @media (max-width: 768px) {
            body {
                flex-direction: column; /* Stack sidebar and content vertically */
            }
            .sidebar {
                width: 100%; /* Full width sidebar on small screens */
                height: auto; /* Auto height */
                padding: 15px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
            .sidebar ul {
                display: flex; /* Make menu items horizontal */
                flex-wrap: wrap; /* Allow items to wrap */
                justify-content: center; /* Center items */
            }
            .sidebar ul li {
                flex: 1 1 auto; /* Allow items to grow/shrink based on content */
                min-width: 150px; /* Minimum width for each item */
                margin: 5px; /* Adjust margin for horizontal layout */
                text-align: center;
                justify-content: center;
            }
            #content {
                padding: 15px;
            }
            .stat-card {
                min-height: 120px; /* Slightly smaller height on mobile */
                padding: 1rem;
            }
            .stat-card .icon {
                font-size: 2rem;
            }
            .stat-card .value {
                font-size: 1.75rem;
            }
            .stat-card .label {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex w-100"> 
        <aside class="sidebar">
            <h2>لوحة الإدارة</h2>
            <div id="user-role" data-role-id="<?php echo htmlspecialchars($role_id); ?>"></div>
            <div id="user-info" class="user-info">
                <p id="user-name"><i class="fas fa-user-circle me-2"></i> اسم المستخدم: <?php echo htmlspecialchars($user_name); ?></p>
                <p id="user-role"><i class="fas fa-user-tag me-2"></i> الوظيفة: <?php echo htmlspecialchars($role_id); ?></p>
            </div>
            <ul id="menu" class="list-unstyled">
                <li onclick="changeMenu('manage_users')"><i class="fas fa-users"></i> إدارة المستخدمين</li>
                <li onclick="changeMenu('products')"><i class="fas fa-box"></i> إدارة المنتجات</li>
                <li onclick="changeMenu('inventory')"><i class="fas fa-warehouse"></i> إدارة المخزون</li>
                <li onclick="changeMenu('categories')"><i class="fas fa-folder"></i> إدارة الأقسام</li>
                <li onclick="changeMenu('purchase_order')"><i class="fas fa-shopping-cart"></i> إدارة المشتريات</li>
                <li onclick="changeMenu('manage_supplier')"><i class="fas fa-truck"></i> إدارة الموردين</li>
                <li onclick="changeMenu('order')"><i class="fas fa-file-invoice"></i> إدارة الطلبات</li>
                <li onclick="changeMenu('wallet_management')"><i class="fas fa-wallet"></i> ادارة المحافظ الالكترونية</li>
                <li onclick="changeMenu('Reports')"><i class="fas fa-chart-line"></i> التقارير والإحصائيات</li>
                <li onclick="loadSection('resort_ratings')"><i class="fas fa-star-half-alt"></i> تقييمات المنتجعات</li>
                <li onclick="loadSection('settings')"><i class="fas fa-cogs"></i> الإعدادات</li>
                <li onclick="logout()"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</li>
                <li onclick="GoToMarket()"><i class="fas fa-store"></i> المتجر</li>
            </ul>
        </aside>

        <div id="content" class="flex-grow-1 p-4">
            <div class="text-center mb-4">
                <img src="../image/icon/logo-removebg-preview.png" alt="شعار الموقع" class="dashboard-logo"> 
                </div>

            <h1 class="text-center text-secondary mb-4">لوحة التحكم الرئيسية</h1>
            
            <div class="row">
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card bg-primary-gradient">
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <div class="value"><?php echo $total_users; ?></div>
                        <div class="label">إجمالي المستخدمين</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card bg-success-gradient">
                        <div class="icon"><i class="fas fa-shopping-bag"></i></div>
                        <div class="value"><?php echo $total_orders; ?></div>
                        <div class="label">إجمالي الطلبات</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card bg-info-gradient">
                        <div class="icon"><i class="fas fa-cubes"></i></div> <div class="value"><?php echo $total_products; ?></div>
                        <div class="label">إجمالي المنتجات</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card bg-warning-gradient">
                        <div class="icon"><i class="fas fa-folder-open"></i></div>
                        <div class="value"><?php echo $total_categories; ?></div>
                        <div class="label">إجمالي الأقسام</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card bg-danger-gradient">
                        <div class="icon"><i class="fas fa-truck-moving"></i></div> <div class="value"><?php echo $total_suppliers; ?></div>
                        <div class="label">إجمالي الموردين</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card bg-secondary-gradient">
                        <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div> <div class="value"><?php echo $total_purchase_orders; ?></div>
                        <div class="label">أوامر الشراء</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card bg-dark-gradient">
                        <div class="icon"><i class="fas fa-handshake"></i></div> <div class="value"><?php echo $total_sales_invoices; ?></div>
                        <div class="label">فواتير المشتريات</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card bg-purple-gradient">
                        <div class="icon"><i class="fas fa-boxes"></i></div> <div class="value"><?php echo $total_inventory_items; ?></div>
                        <div class="label">أصناف المخزون</div>
                    </div>
                </div>
            </div>

            <div id="ajax-content-area" class="mt-4">
                <p class="text-center text-muted">استخدم القائمة الجانبية للتنقل بين أقسام الإدارة لعرض التفاصيل.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src='./dashboard/dashboard.js?v=<?php echo time(); ?>'></script>
</body>
</html>