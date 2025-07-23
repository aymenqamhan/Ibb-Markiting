<?php
session_start();
include('../connect_DB.php'); // تأكد من المسار الصحيح لقاعدة البيانات
include('./session_check.php'); // للتحقق من تسجيل الدخول والصلاحية

// التحقق من صلاحيات المستخدم
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 5])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'ليس لديك الصلاحية للوصول إلى هذه الصفحة.'];
    header("Location: /NEW_IBB/login.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null; // جلب رقم الطلب
$order_details = null;
$message = '';

// عرض رسائل الجلسة (نجاح/خطأ)
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

// التحقق من تحديد رقم الطلب
if (!$order_id) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'لم يتم تحديد رقم الطلب.'];
    header("Location: list_orders.php");
    exit();
}

// جلب تفاصيل الطلب ومعلومات المستخدم
$query_order = "
    SELECT
        o.id,
        o.order_date,
        o.total_amount,
        o.status,
        o.payment_status,
        o.payment_method,
        o.notes,
        o.shipping_address,
        u.name AS user_name,
        u.email AS user_email,
        u.phone AS user_phone,
        u.id AS user_id -- جلب user_id هنا لاستخدامه في تحديث المحفظة
    FROM orders o
    JOIN user_tb u ON o.user_id = u.id
    WHERE o.id = ?
";
$stmt_order = $con->prepare($query_order);
if ($stmt_order === false) {
    error_log("Failed to prepare order details query: " . $con->error);
    $message = "<div class='alert alert-danger'>حدث خطأ في قاعدة البيانات أثناء جلب تفاصيل الطلب.</div>";
} else {
    $stmt_order->bind_param("i", $order_id);
    $stmt_order->execute();
    $result_order = $stmt_order->get_result();
    $order_details = $result_order->fetch_assoc();
    $stmt_order->close();

    if ($order_details) {
        // جلب عناصر الطلب (تم التعديل هنا)
        $query_items = "
            SELECT
                oi.quantity,
                oi.price_at_order,
                p.name AS product_name,
                COALESCE(pi.image_path, '/NEW_IBB/assets/images/default_product.png') AS product_image_url,
                ps.size AS size_name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN products_images pi ON p.id = pi.product_id AND pi.is_main_image = 1
            LEFT JOIN product_sizes ps ON oi.size_id = ps.id -- الربط بـ size_id من order_items
            WHERE oi.order_id = ?
        ";
        $stmt_items = $con->prepare($query_items);
        if ($stmt_items === false) {
            error_log("Failed to prepare order items query: " . $con->error);
        } else {
            $stmt_items->bind_param("i", $order_id);
            $stmt_items->execute();
            $order_details['items'] = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_items->close();
        }

        // جلب تفاصيل عنوان الشحن
        if ($order_details['shipping_address']) {
            $address_id = intval($order_details['shipping_address']);
            $query_address = "SELECT full_name, phone, address_line1, address_line2, city, state, zip_code, country FROM addresses WHERE id = ?";
            $stmt_address = $con->prepare($query_address);
            if ($stmt_address === false) {
                error_log("Failed to prepare address query: " . $con->error);
            } else {
                $stmt_address->bind_param("i", $address_id);
                $stmt_address->execute();
                $order_details['shipping_address_details'] = $stmt_address->get_result()->fetch_assoc();
                $stmt_address->close();
            }
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'الطلب المحدد غير موجود.'];
        header("Location: list_orders.php");
        exit();
    }
}

// معالجة تغيير الحالة أو حالة الدفع (تم التعديل هنا لإضافة منطق المحفظة)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $new_status = $_POST['new_status'];
    $new_payment_status = $_POST['new_payment_status'];
    // $order_id تم جلبه بالفعل في بداية السكربت، ولكن نكرر التأكيد هنا للحماية
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null; 

    if (!$order_id) { // في حال تم إرسال POST بدون order_id في الـ GET
        $_SESSION['message'] = ['type' => 'error', 'text' => 'خطأ في معالجة الطلب: لم يتم تحديد رقم الطلب.'];
        header("Location: list_orders.php");
        exit();
    }

    // جلب تفاصيل الطلب الحالية قبل التحديث لمعرفة حالته السابقة
    $current_order_query = "SELECT total_amount, user_id, payment_method, payment_status FROM orders WHERE id = ?";
    $stmt_current_order = $con->prepare($current_order_query);
    if ($stmt_current_order === false) {
        error_log("Failed to prepare current order query: " . $con->error);
        $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ في قاعدة البيانات أثناء جلب بيانات الطلب الحالية.'];
        header("Location: order_details.php?order_id=$order_id");
        exit();
    }
    $stmt_current_order->bind_param("i", $order_id);
    $stmt_current_order->execute();
    $result_current_order = $stmt_current_order->get_result();
    $current_order_data = $result_current_order->fetch_assoc();
    $stmt_current_order->close();

    if ($current_order_data) {
        $old_payment_status = $current_order_data['payment_status'];
        $user_id = $current_order_data['user_id'];
        $total_amount = $current_order_data['total_amount'];
        $payment_method = $current_order_data['payment_method'];

        // بدء معاملة SQL لضمان تناسق البيانات
        $con->begin_transaction();

        try {
            // تحديث حالة الطلب وحالة الدفع
            $update_query = "UPDATE orders SET status = ?, payment_status = ?, updated_at = NOW() WHERE id = ?";
            $stmt_update = $con->prepare($update_query);
            if ($stmt_update === false) {
                throw new Exception("Failed to prepare order status update query: " . $con->error);
            }
            $stmt_update->bind_param("ssi", $new_status, $new_payment_status, $order_id);
            if (!$stmt_update->execute()) {
                throw new Exception("فشل تحديث حالة الطلب: " . $stmt_update->error);
            }
            $stmt_update->close();

            // منطق خصم المبلغ من المحفظة وتسجيل المعاملة
            // هذا يحدث فقط إذا:
            // 1. طريقة الدفع كانت "wallet"
            // 2. حالة الدفع الجديدة هي "paid"
            // 3. حالة الدفع القديمة لم تكن "paid" (لتجنب الخصم المتكرر)
            if ($payment_method === 'wallet' && $new_payment_status === 'paid' && $old_payment_status !== 'paid') {
                // خصم المبلغ من محفظة المستخدم
                $update_wallet_query = "UPDATE user_tb SET wallet_balance = wallet_balance - ? WHERE id = ?";
                $stmt_wallet = $con->prepare($update_wallet_query);
                if ($stmt_wallet === false) {
                    throw new Exception("Failed to prepare wallet update query: " . $con->error);
                }
                $stmt_wallet->bind_param("di", $total_amount, $user_id);
                if (!$stmt_wallet->execute()) {
                    throw new Exception("فشل خصم المبلغ من المحفظة: " . $stmt_wallet->error);
                }
                $stmt_wallet->close();

                // تسجيل المعاملة (تعديل أسماء الجداول والأعمدة لتناسب قاعدة بياناتك)
                // افترض أن لديك جدول اسمه 'transactions'
                $insert_transaction_query = "
                    INSERT INTO transactions (user_id, order_id, amount, transaction_type, transaction_date, description, status)
                    VALUES (?, ?, ?, 'debit', NOW(), ?, 'completed')
                ";
                $transaction_description = "دفع فاتورة الطلب رقم " . $order_id . " من المحفظة";
                $stmt_transaction = $con->prepare($insert_transaction_query);
                if ($stmt_transaction === false) {
                    throw new Exception("Failed to prepare transaction insert query: " . $con->error);
                }
                $stmt_transaction->bind_param("iids", $user_id, $order_id, $total_amount, $transaction_description);
                if (!$stmt_transaction->execute()) {
                    throw new Exception("فشل تسجيل المعاملة: " . $stmt_transaction->error);
                }
                $stmt_transaction->close();

                $_SESSION['message'] = ['type' => 'success', 'text' => 'تم تحديث حالة الطلب وخصم المبلغ من المحفظة بنجاح.'];
            } else {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'تم تحديث حالة الطلب بنجاح.'];
            }

            // تأكيد المعاملة
            $con->commit();

        } catch (Exception $e) {
            // التراجع عن المعاملة في حالة حدوث أي خطأ
            $con->rollback();
            $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ: ' . $e->getMessage()];
            error_log("Order update transaction failed: " . $e->getMessage()); // سجل الخطأ لمراجعة المسؤول
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'الطلب المحدد غير موجود للتحقق من حالته.'];
    }

    header("Location: order_details.php?order_id=$order_id");
    exit();
}


// دوال مساعدة لترجمة الحالات (يمكن وضعها في ملف include مشترك)
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
    <title>إدارة الطلبات - تفاصيل الطلب #<?php echo htmlspecialchars($order_id); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/NEW_IBB/Style/admin_styles.css">
    <style>
        .container-fluid {
            padding: 30px;
        }
        .card {
            border-radius: 0.75rem;
            margin-bottom: 20px;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            font-weight: bold;
        }
        .order-info p {
            margin-bottom: 8px;
        }
        .order-info strong {
            color: #343a40;
        }
        .status-badge {
            padding: 0.4em 0.8em;
            border-radius: 0.375rem;
            font-weight: bold;
            font-size: 0.9em;
            display: inline-block;
            min-width: 80px; /* لتوحيد عرض الأزرار */
            text-align: center;
        }
        .status-pending { background-color: #ffc107; color: #343a40; }
        .status-processing { background-color: #0dcaf0; color: #343a40; }
        .status-shipped { background-color: #6f42c1; color: white; }
        .status-delivered { background-color: #28a745; color: white; }
        .status-cancelled { background-color: #dc3545; color: white; }

        .payment-status-paid { background-color: #28a745; color: white; }
        .payment-status-unpaid { background-color: #dc3545; color: white; }
        .payment-status-pending-payment { background-color: #ffc107; color: #343a40; }

        .item-list img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 0.5rem;
            margin-left: 10px;
            border: 1px solid #dee2e6;
        }
        .item-row {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px dashed #e9ecef;
        }
        .item-row:last-child {
            border-bottom: none;
        }
        .address-box {
            background-color: #e9f5ff;
            border: 1px solid #b3d9ff;
            border-radius: 0.5rem;
            padding: 15px;
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .address-box strong {
            color: #0d6efd;
        }
        .form-select {
            max-width: 250px;
        }
    </style>
</head>
<body>

    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4 text-primary"><i class="bi bi-info-circle-fill me-2"></i> تفاصيل الطلب #<?php echo htmlspecialchars($order_id); ?></h2>
            <?php echo $message; ?>

            <?php if ($order_details): ?>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                معلومات الطلب الأساسية
                            </div>
                            <div class="card-body order-info">
                                <div class="row">
                                    <div class="col-md-6"><p><strong>تاريخ الطلب:</strong> <?php echo date('Y-m-d H:i', strtotime($order_details['order_date'])); ?></p></div>
                                    <div class="col-md-6"><p><strong>الإجمالي الكلي:</strong> <?php echo number_format($order_details['total_amount'], 2); ?> ر.ي</p></div>
                                    <div class="col-md-6"><p><strong>العميل:</strong> <?php echo htmlspecialchars($order_details['user_name']); ?> (<a href="mailto:<?php echo htmlspecialchars($order_details['user_email']); ?>"><?php echo htmlspecialchars($order_details['user_email']); ?></a>)</p></div>
                                    <div class="col-md-6"><p><strong>هاتف العميل:</strong> <?php echo htmlspecialchars($order_details['user_phone'] ?? 'غير متوفر'); ?></p></div>
                                    <div class="col-md-6"><p><strong>طريقة الدفع:</strong> <?php echo getPaymentMethodArabic($order_details['payment_method']); ?></p></div>
                                    <div class="col-md-6"><p><strong>ملاحظات العميل:</strong> <?php echo nl2br(htmlspecialchars($order_details['notes'] ?? 'لا توجد ملاحظات.')); ?></p></div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header">
                                المنتجات في الطلب
                            </div>
                            <div class="card-body">
                                <?php if (!empty($order_details['items'])): ?>
                                    <div class="item-list">
                                        <?php foreach ($order_details['items'] as $item): ?>
                                            <div class="item-row">
                                                <img src="<?php echo htmlspecialchars($item['product_image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                                <div class="flex-grow-1">
                                                    <div><strong><?php echo htmlspecialchars($item['product_name']); ?></strong> <?php echo !empty($item['size_name']) ? ' (' . htmlspecialchars($item['size_name']) . ')' : ''; ?></div>
                                                    <small class="text-muted">الكمية: <?php echo htmlspecialchars($item['quantity']); ?> &times; السعر: <?php echo number_format($item['price_at_order'], 2); ?> ر.ي</small>
                                                </div>
                                                <div class="text-nowrap fw-bold">
                                                    <?php echo number_format($item['quantity'] * $item['price_at_order'], 2); ?> ر.ي
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning text-center">لا توجد منتجات لهذا الطلب.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                حالة الطلب والدفع
                            </div>
                            <div class="card-body">
                                <form action="order_details.php?order_id=<?php echo $order_id; ?>" method="POST">
                                    <div class="mb-3">
                                        <label for="current_status" class="form-label">الحالة الحالية:</label>
                                        <span class="status-badge status-<?php echo htmlspecialchars($order_details['status']); ?>">
                                            <?php echo getStatusArabic($order_details['status']); ?>
                                        </span>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_status" class="form-label">تغيير حالة الطلب إلى:</label>
                                        <select class="form-select" id="new_status" name="new_status">
                                            <option value="pending" <?php echo ($order_details['status'] == 'pending') ? 'selected' : ''; ?>>قيد الانتظار</option>
                                            <option value="processing" <?php echo ($order_details['status'] == 'processing') ? 'selected' : ''; ?>>قيد المعالجة</option>
                                            <option value="shipped" <?php echo ($order_details['status'] == 'shipped') ? 'selected' : ''; ?>>تم الشحن</option>
                                            <option value="delivered" <?php echo ($order_details['status'] == 'delivered') ? 'selected' : ''; ?>>تم التوصيل</option>
                                            <option value="cancelled" <?php echo ($order_details['status'] == 'cancelled') ? 'selected' : ''; ?>>ملغى</option>
                                        </select>
                                    </div>

                                    <hr>

                                    <div class="mb-3">
                                        <label for="current_payment_status" class="form-label">حالة الدفع الحالية:</label>
                                        <span class="status-badge payment-status-<?php echo htmlspecialchars($order_details['payment_status']); ?>">
                                            <?php echo getPaymentStatusArabic($order_details['payment_status']); ?>
                                        </span>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_payment_status" class="form-label">تغيير حالة الدفع إلى:</label>
                                        <select class="form-select" id="new_payment_status" name="new_payment_status">
                                            <option value="pending" <?php echo ($order_details['payment_status'] == 'pending') ? 'selected' : ''; ?>>معلق</option>
                                            <option value="paid" <?php echo ($order_details['payment_status'] == 'paid') ? 'selected' : ''; ?>>مدفوع</option>
                                            <option value="unpaid" <?php echo ($order_details['payment_status'] == 'unpaid') ? 'selected' : ''; ?>>غير مدفوع</option>
                                        </select>
                                    </div>

                                    <button type="submit" name="update_order_status" class="btn btn-primary w-100"><i class="bi bi-arrow-clockwise me-2"></i> تحديث حالة الطلب</button>
                                </form>
                            </div>
                        </div>

                        <?php if (isset($order_details['shipping_address_details']) && $order_details['shipping_address_details']): ?>
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-geo-alt-fill me-2"></i> تفاصيل عنوان الشحن
                            </div>
                            <div class="card-body address-box">
                                <p class="mb-1"><strong><?php echo htmlspecialchars($order_details['shipping_address_details']['full_name'] ?? ''); ?></strong></p>
                                <p class="mb-1"><?php echo htmlspecialchars($order_details['shipping_address_details']['address_line1']); ?></p>
                                <?php if (!empty($order_details['shipping_address_details']['address_line2'])): ?>
                                <p class="mb-1"><?php echo htmlspecialchars($order_details['shipping_address_details']['address_line2']); ?></p>
                                <?php endif; ?>
                                <p class="mb-1"><?php echo htmlspecialchars($order_details['shipping_address_details']['city']); ?>, <?php echo htmlspecialchars($order_details['shipping_address_details']['state'] ?? ''); ?> <?php echo htmlspecialchars($order_details['shipping_address_details']['zip_code'] ?? ''); ?></p>
                                <p class="mb-1"><?php echo htmlspecialchars($order_details['shipping_address_details']['country']); ?></p>
                                <?php if (!empty($order_details['shipping_address_details']['phone'])): ?>
                                <p class="mb-1"><strong>الهاتف:</strong> <?php echo htmlspecialchars($order_details['shipping_address_details']['phone']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php else: ?>
                            <div class="alert alert-warning mt-3">لم يتم العثور على تفاصيل عنوان الشحن لهذا الطلب.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="list_orders.php" class="btn btn-secondary"><i class="bi bi-arrow-right-circle-fill me-2"></i> العودة إلى جميع الطلبات</a>
                </div>

            <?php else: ?>
                <div class="alert alert-danger text-center" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i> خطأ: لم يتم العثور على تفاصيل الطلب.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
<?php $con->close(); ?>