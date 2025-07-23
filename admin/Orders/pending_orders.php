<?php
session_start();
include('../connect_DB.php'); // تأكد من المسار الصحيح لقاعدة البيانات
include('./session_check.php'); // للتحقق من تسجيل الدخول والصلاحية

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 5])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'ليس لديك الصلاحية للوصول إلى هذه الصفحة.'];
    header("Location: /NEW_IBB/login.php");
    exit();
}

$orders = [];
$message = '';

if (isset($_SESSION['message'])) {
    $msg_type = $_SESSION['message']['type'];
    $msg_text = $_SESSION['message']['text'];
    $bootstrap_alert_type = ($msg_type == 'success') ? 'alert-success' : 'alert-danger';
    $message = "<div class='alert $bootstrap_alert_type alert-dismissible fade show' role='alert'>
                    $msg_text
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
    unset($_SESSION['message']);
}

$query = "
    SELECT
        o.id,
        o.order_date,
        o.total_amount,
        o.status,
        o.payment_status,
        o.payment_method,
        u.name AS user_name,
        u.email AS user_email
    FROM orders o
    JOIN user_tb u ON o.user_id = u.id
    WHERE o.status = 'pending'
    ORDER BY o.order_date DESC
";

$stmt = $con->prepare($query);
if ($stmt === false) {
    error_log("Failed to prepare pending orders query: " . $con->error);
    $message = "<div class='alert alert-danger'>حدث خطأ في قاعدة البيانات أثناء جلب الطلبات المعلقة.</div>";
} else {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
}

function getStatusArabic($status) {
    $map = [
        'pending' => 'قيد الانتظار',
        'processing' => 'قيد المعالجة',
        'shipped' => 'تم الشحن',
        'delivered' => 'تم التوصيل',
        'cancelled' => 'ملغى'
    ];
    return $map[$status] ?? $status;
}

function getPaymentStatusArabic($status) {
    $map = [
        'paid' => 'مدفوع',
        'unpaid' => 'غير مدفوع',
        'pending' => 'معلق'
    ];
    return $map[$status] ?? $status;
}

function getPaymentMethodArabic($method) {
    $map = [
        'wallet' => 'المحفظة الإلكترونية',
        'cash_on_delivery' => 'الدفع عند الاستلام',
        'paypal' => 'باي بال',
        'credit_card' => 'بطاقة ائتمان'
    ];
    return $map[$method] ?? $method;
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الطلبات - طلبات قيد الانتظار</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/NEW_IBB/Style/admin_styles.css">
    <style>
        /* نفس الستايلات من list_orders.php */
        .container-fluid { padding: 30px; }
        .table-responsive { margin-top: 20px; }
        .table thead th { background-color: #0d6efd; color: white; vertical-align: middle; }
        .table tbody tr:hover { background-color: #f1f1f1; }
        .status-badge { padding: 0.4em 0.8em; border-radius: 0.375rem; font-weight: bold; font-size: 0.85em; }
        .status-pending { background-color: #ffc107; color: #343a40; }
        .status-processing { background-color: #0dcaf0; color: #343a40; }
        .status-shipped { background-color: #6f42c1; color: white; }
        .status-delivered { background-color: #28a745; color: white; }
        .status-cancelled { background-color: #dc3545; color: white; }
        .payment-status-paid { background-color: #28a745; color: white; }
        .payment-status-unpaid { background-color: #dc3545; color: white; }
        .payment-status-pending-payment { background-color: #ffc107; color: #343a40; }
    </style>
</head>
<body>

    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4 text-warning"><i class="bi bi-hourglass-split me-2"></i> طلبات قيد الانتظار</h2>
            <?php echo $message; ?>

            <?php if (empty($orders)): ?>
                <div class="alert alert-info text-center" role="alert">
                    <i class="bi bi-info-circle me-2"></i> لا توجد طلبات قيد الانتظار حالياً.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>تاريخ الطلب</th>
                                <th>العميل</th>
                                <th>الإجمالي</th>
                                <th>الحالة</th>
                                <th>حالة الدفع</th>
                                <th>طريقة الدفع</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['id']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($order['order_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($order['user_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($order['user_email']); ?></small></td>
                                    <td><?php echo number_format($order['total_amount'], 2); ?> ر.ي</td>
                                    <td><span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>"><?php echo getStatusArabic($order['status']); ?></span></td>
                                    <td><span class="status-badge payment-status-<?php echo htmlspecialchars($order['payment_status']); ?>"><?php echo getPaymentStatusArabic($order['payment_status']); ?></span></td>
                                    <td><?php echo getPaymentMethodArabic($order['payment_method']); ?></td>
                                    <td>
                                        <a href="order_details.php?order_id=<?php echo $order['id']; ?>" class="btn btn-info btn-sm" title="تفاصيل الطلب"><i class="bi bi-eye-fill"></i></a>
                                        <a href="process_order.php?order_id=<?php echo $order['id']; ?>&action=approve" class="btn btn-success btn-sm" title="اعتماد الطلب"><i class="bi bi-check-circle-fill"></i></a>
                                        <a href="process_order.php?order_id=<?php echo $order['id']; ?>&action=cancel" class="btn btn-danger btn-sm" title="إلغاء الطلب"><i class="bi bi-x-circle-fill"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php $con->close(); ?>