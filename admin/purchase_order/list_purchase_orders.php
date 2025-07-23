<?php
session_start();
include('../connect_DB.php');

// التحقق من صلاحيات المستخدم
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: /login.php");
    exit();
}

$message = '';
if (isset($_SESSION['message'])) {
    $msg_type = $_SESSION['message']['type'];
    $msg_text = $_SESSION['message']['text'];
    // تحويل type إلى class Bootstrap المناسب
    $alert_class = '';
    if ($msg_type == 'success') {
        $alert_class = 'alert-success';
    } elseif ($msg_type == 'error') {
        $alert_class = 'alert-danger';
    } elseif ($msg_type == 'info') {
        $alert_class = 'alert-info';
    }
    $message = "<div class='alert $alert_class alert-dismissible fade show' role='alert'>" . htmlspecialchars($msg_text) . "
                  <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
    unset($_SESSION['message']);
}

// جلب جميع أوامر الشراء مع اسم المورد
$purchase_orders = [];
$stmt = $con->prepare("
    SELECT po.id, po.order_date,po.status, s.name AS supplier_name
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.id
    ORDER BY po.order_date DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $purchase_orders[] = $row;
}
$stmt->close();

// إغلاق الاتصال بقاعدة البيانات
if (isset($con) && $con->ping()) {
    $con->close();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>قائمة أوامر الشراء - لوحة التحكم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        /* ==================== الأنماط العامة للصفحة ==================== */
        body {
            background-color: #f0f2f5;
            /* لون خلفية خفيف */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            direction: rtl;
            /* لضمان التوجيه الصحيح للعربية */
            text-align: right;
            /* محاذاة النص لليمين */
        }

        /* ==================== شريط التنقل (Navbar) ==================== */
        .navbar {
            background-color: #2c3e50;
            /* لون داكن لشريط التنقل */
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            /* ظل خفيف لشريط التنقل */
        }

        .navbar-brand {
            color: #ecf0f1 !important;
            font-weight: bold;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }

        .navbar-nav .nav-link {
            color: #ecf0f1 !important;
            margin-left: 15px;
            /* لتغيير الهامش في اتجاه RTL */
            transition: color 0.3s ease;
        }

        .navbar-nav .nav-link:hover {
            color: #3498db !important;
        }

        .navbar-nav .nav-link.active {
            font-weight: bold;
            color: #3498db !important;
        }

        /* ==================== عنوان الصفحة الرئيسي (Page Header) ==================== */
        .page-header {
            background-color: #3498db;
            /* لون أزرق جذاب للعنوان */
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .page-header h1 {
            margin: 0;
            font-size: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-header p.lead {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-top: 0.5rem;
        }

        /* ==================== الحاويات والبطاقات (Containers & Cards) ==================== */
        .container-fluid.py-4,
        .container.py-4 {
            padding-top: 2rem !important;
            padding-bottom: 2rem !important;
        }

        .card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background-color: #2c3e50;
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h5 {
            margin-bottom: 0;
        }

        /* ==================== الأزرار (Buttons) ==================== */
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            font-weight: bold;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
            transform: translateY(-2px);
        }

        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
            font-weight: bold;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
            transform: translateY(-1px);
        }

        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
            font-weight: bold;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
            transform: translateY(-1px);
        }

        .btn-back-dashboard {
            background-color: #3498db;
            border-color: #3498db;
            color: white;
            margin-top: 1.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-back-dashboard:hover {
            background-color: #2980b9;
            border-color: #2980b9;
            transform: translateY(-2px);
        }

        /* ==================== رسائل التنبيه (Alerts) ==================== */
        .alert {
            border-radius: 8px;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .btn-close {
            filter: invert(1);
            /* يجعل زر الإغلاق أبيض في التنبيهات الداكنة */
        }

        /* ==================== الجداول (Tables) ==================== */
        .table-responsive {
            margin-top: 1rem;
        }

        .table {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .table th,
        .table td {
            padding: 1rem;
            vertical-align: middle;
        }

        .table thead th {
            background-color: #34495e;
            color: white;
            border-bottom: none;
            font-weight: bold;
        }

        .table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .table tbody tr:hover {
            background-color: #e9ecef;
        }

        /* ==================== أنماط خاصة بأوامر الشراء (Status Badges) ==================== */
        .status-badge {
            padding: .35em .65em;
            font-size: .75em;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: .375rem;
        }

        .status-pending {
            background-color: #ffc107;
            color: #343a40;
        }

        /* أصفر */
        .status-completed {
            background-color: #28a745;
        }

        /* أخضر */
        .status-canceled {
            background-color: #dc3545;
        }

        /* أحمر */
        .status-processing {
            background-color: #007bff;
        }

        /* أزرق */

        /* ==================== الأيقونات (Font Awesome) - للتأكد من التباعد الصحيح ==================== */
        .me-1 {
            margin-inline-end: 0.25rem !important;
        }

        /* في RTL، me تعني margin-inline-end */
        .me-2 {
            margin-inline-end: 0.5rem !important;
        }

        /* أي تعديلات على أحجام الأيقونات في الأزرار والعناوين */
        .navbar-brand .fa-cubes,
        .page-header h1 .fas,
        .card-header h5 .fas,
        .btn .fas {
            font-size: 1.1em;
            /* تعديل حجم الأيقونات ليتناسب مع النص */
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashbord.php">
                <i class="fas fa-cubes me-2"></i> لوحة التحكم
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="list_purchase_orders.php">
                            <i class="fas fa-file-invoice-dollar me-1"></i> أوامر الشراء
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_purchase_order.php">
                            <i class="fas fa-plus-circle me-1"></i> إضافة أمر شراء
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php echo $message; // عرض رسائل النظام هنا 
        ?>

        <div class="card shadow-lg">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i> أوامر الشراء الحالية</h5>
            </div>
            <div class="card-body">
                <?php if (empty($purchase_orders)): ?>
                    <div class="alert alert-info text-center" role="alert">
                        <i class="fas fa-info-circle me-2"></i> لا توجد أوامر شراء مسجلة حتى الآن.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th scope="col">رقم الأمر</th>
                                    <th scope="col">المورد</th>
                                    <th scope="col">تاريخ الطلب</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchase_orders as $po): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($po['id']); ?></td>
                                        <td><?= htmlspecialchars($po['supplier_name']); ?></td>
                                        <td><?= htmlspecialchars(date('Y-m-d', strtotime($po['order_date']))); ?></td>
                                        <td>
                                            <?php
                                            // تحديد لون الشارة حسب الحالة
                                            $status_class = '';
                                            switch ($po['status']) {
                                                case 'Pending':
                                                    $status_class = 'status-pending';
                                                    break;
                                                case 'Completed':
                                                    $status_class = 'status-completed';
                                                    break;
                                                case 'Canceled':
                                                    $status_class = 'status-canceled';
                                                    break;
                                                case 'Processing':
                                                    $status_class = 'status-processing';
                                                    break;
                                                default:
                                                    $status_class = 'badge bg-secondary'; // حالة افتراضية
                                                    break;
                                            }
                                            ?>
                                            <span class="status-badge <?= $status_class ?>"><?= htmlspecialchars($po['status']); ?></span>
                                        </td>
                                        <td>
                                            <a href="view_purchase_order.php?id=<?= htmlspecialchars($po['id']); ?>" class="btn btn-info btn-sm me-2">
                                                <i class="fas fa-eye me-1"></i> عرض
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <div class="text-center mt-3">
                    <a href="../dashbord.php" class="btn btn-back-dashboard">
                        <i class="fas fa-arrow-left me-1"></i> العودة للوحة التحكم
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>