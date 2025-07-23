<?php
session_start();
include('../../include/connect_DB.php'); // تأكد من المسار الصحيح لقاعدة البيانات

// التحقق مما إذا كان المستخدم مسجلاً الدخول
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'يجب تسجيل الدخول لعرض طلباتك.'];
    header("Location: /NEW_IBB/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$orders = [];
$current_order_details = null;
$order_id_to_view = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;

// جلب جميع طلبات المستخدم
$orders_query = "SELECT id, order_date, total_amount, status, payment_status, payment_method, shipping_address FROM orders WHERE user_id = ? ORDER BY order_date DESC";
$stmt_orders = $con->prepare($orders_query);
if ($stmt_orders === false) {
    error_log("Failed to prepare orders query: " . $con->error);
    $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ في قاعدة البيانات أثناء جلب الطلبات.'];
    // لا نخرج هنا لأننا قد نكون في وضع عرض تفاصيل طلب واحد
} else {
    $stmt_orders->bind_param("i", $user_id);
    $stmt_orders->execute();
    $result_orders = $stmt_orders->get_result();
    while ($order = $result_orders->fetch_assoc()) {
        $orders[] = $order;
    }
    $stmt_orders->close();
}

// إذا تم تحديد order_id، جلب تفاصيله
if ($order_id_to_view) {
    // جلب تفاصيل الطلب المحدد للتأكد أنه يخص المستخدم الحالي
    $single_order_query = "SELECT id, order_date, total_amount, status, payment_status, payment_method, shipping_address, notes FROM orders WHERE id = ? AND user_id = ?";
    $stmt_single_order = $con->prepare($single_order_query);
    if ($stmt_single_order === false) {
        error_log("Failed to prepare single order query: " . $con->error);
    } else {
        $stmt_single_order->bind_param("ii", $order_id_to_view, $user_id);
        $stmt_single_order->execute();
        $result_single_order = $stmt_single_order->get_result();
        $current_order_details = $result_single_order->fetch_assoc();
        $stmt_single_order->close();

        if ($current_order_details) {
            // جلب عناصر الطلب
            $items_query = "
                SELECT
                    oi.quantity,
                    oi.price_at_order,
                    p.name AS product_name,
                    pi.image_path AS product_image_url,
                    ps.size AS size_name
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                LEFT JOIN products_images pi ON p.id = pi.product_id AND pi.is_main_image = 1
                LEFT JOIN inventory inv ON inv.product_id = p.id -- ممكن يكون inv.id == oi.inventory_id لو كان order_items يحفظ inventory_id
                LEFT JOIN product_sizes ps ON inv.size_id = ps.id
                WHERE oi.order_id = ?
            ";
            $stmt_items = $con->prepare($items_query);
            if ($stmt_items === false) {
                error_log("Failed to prepare order items query: " . $con->error);
            } else {
                $stmt_items->bind_param("i", $order_id_to_view);
                $stmt_items->execute();
                $current_order_details['items'] = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_items->close();
            }

            // جلب تفاصيل عنوان الشحن
            if ($current_order_details['shipping_address']) {
                $address_id = intval($current_order_details['shipping_address']);
                $address_query = "SELECT full_name, phone, address_line1, address_line2, city, state, zip_code, country FROM addresses WHERE id = ? AND user_id = ?";
                $stmt_address = $con->prepare($address_query);
                if ($stmt_address === false) {
                    error_log("Failed to prepare address query: " . $con->error);
                } else {
                    $stmt_address->bind_param("ii", $address_id, $user_id);
                    $stmt_address->execute();
                    $current_order_details['shipping_address_details'] = $stmt_address->get_result()->fetch_assoc();
                    $stmt_address->close();
                }
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'الطلب المحدد غير موجود أو لا تملك صلاحية الوصول إليه.'];
            header("Location: " . $_SERVER['PHP_SELF']); // توجيه العودة إلى قائمة الطلبات
            exit();
        }
    }
}

$message = '';
if (isset($_SESSION['message'])) {
    $msg_type = $_SESSION['message']['type'];
    $msg_text = $_SESSION['message']['text'];
    $bootstrap_alert_type = '';
    if ($msg_type == 'success') {
        $bootstrap_alert_type = 'alert-success';
    } elseif ($msg_type == 'error') {
        $bootstrap_alert_type = 'alert-danger';
    } else {
        $bootstrap_alert_type = 'alert-info';
    }
    $message = "<div class='alert $bootstrap_alert_type alert-dismissible fade show' role='alert'>
                    $msg_text
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلباتي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/NEW_IBB/Style/mainscrain.css"> <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 900px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            color: #0d6efd;
            font-weight: 700;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .order-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.75rem;
            margin-bottom: 20px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }
        .order-card:hover {
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
            transform: translateY(-3px);
        }
        .order-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #e9ecef;
        }
        .order-id {
            font-weight: bold;
            color: #343a40;
            font-size: 1.2rem;
        }
        .order-date {
            font-size: 0.9em;
            color: #6c757d;
        }
        .order-total {
            font-weight: bold;
            color: #28a745;
            font-size: 1.1rem;
        }
        .order-status {
            font-weight: bold;
        }
        .status-pending { color: #ffc107; } /* Yellow */
        .status-processing { color: #0dcaf0; } /* Info Blue */
        .status-shipped { color: #6f42c1; } /* Purple */
        .status-delivered { color: #28a745; } /* Green */
        .status-cancelled { color: #dc3545; } /* Red */
        .payment-status-paid { color: #28a745; } /* Green */
        .payment-status-unpaid { color: #dc3545; } /* Red */
        .payment-status-pending { color: #ffc107; } /* Yellow */

        .order-details-section {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.75rem;
            padding: 20px;
            margin-top: 20px;
        }
        .order-item-list {
            margin-top: 20px;
            border-top: 1px dashed #e9ecef;
            padding-top: 20px;
        }
        .order-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dotted #e9ecef;
        }
        .order-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .order-item img {
            width: 70px;
            height: 70px;
            border-radius: 0.5rem;
            margin-left: 15px;
            object-fit: cover;
            border: 1px solid #ddd;
        }
        .order-item-details {
            flex-grow: 1;
        }
        .order-item-name {
            font-weight: 600;
            color: #333;
            font-size: 1.1em;
        }
        .order-item-qty-price {
            color: #6c757d;
            font-size: 0.95em;
        }
        .order-item-total {
            font-weight: bold;
            color: #28a745;
            white-space: nowrap;
        }
        .address-details-box {
            background-color: #e9f5ff;
            border: 1px solid #b3d9ff;
            border-radius: 0.5rem;
            padding: 15px;
            margin-top: 15px;
        }
        .address-details-box strong {
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <?php // include("../../include/head.php"); // تأكد من تضمين رأس الصفحة إن وجد ?>

    <div class="container">
        <h2 class="header">
            <i class="bi bi-box-seam-fill me-2"></i>
            <?php echo $order_id_to_view ? 'تفاصيل الطلب #' . htmlspecialchars($order_id_to_view) : 'طلباتي'; ?>
        </h2>

        <?php echo $message; ?>

        <?php if ($order_id_to_view && $current_order_details): ?>
            <div class="order-details-section">
                <div class="row mb-3">
                    <div class="col-md-6"><strong>رقم الطلب:</strong> <?php echo htmlspecialchars($current_order_details['id']); ?></div>
                    <div class="col-md-6"><strong>تاريخ الطلب:</strong> <?php echo date('Y-m-d H:i', strtotime($current_order_details['order_date'])); ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6"><strong>الإجمالي الكلي:</strong> <?php echo number_format($current_order_details['total_amount'], 2); ?> ر.ي</div>
                    <div class="col-md-6">
                        <strong>الحالة:</strong>
                        <span class="order-status status-<?php echo htmlspecialchars($current_order_details['status']); ?>">
                            <?php
                            $status_map = [
                                'pending' => 'قيد الانتظار',
                                'processing' => 'قيد المعالجة',
                                'shipped' => 'تم الشحن',
                                'delivered' => 'تم التوصيل',
                                'cancelled' => 'ملغى'
                            ];
                            echo htmlspecialchars($status_map[$current_order_details['status']] ?? $current_order_details['status']);
                            ?>
                        </span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>حالة الدفع:</strong>
                        <span class="payment-status-<?php echo htmlspecialchars($current_order_details['payment_status']); ?>">
                            <?php
                            $payment_status_map = [
                                'paid' => 'مدفوع',
                                'unpaid' => 'غير مدفوع',
                                'pending' => 'معلق'
                            ];
                            echo htmlspecialchars($payment_status_map[$current_order_details['payment_status']] ?? $current_order_details['payment_status']);
                            ?>
                        </span>
                    </div>
                    <div class="col-md-6"><strong>طريقة الدفع:</strong>
                        <?php
                        $payment_method_map = [
                            'wallet' => 'المحفظة الإلكترونية',
                            'cash_on_delivery' => 'الدفع عند الاستلام',
                            'paypal' => 'باي بال',
                            'credit_card' => 'بطاقة ائتمان'
                        ];
                        echo htmlspecialchars($payment_method_map[$current_order_details['payment_method']] ?? $current_order_details['payment_method']);
                        ?>
                    </div>
                </div>

                <?php if (!empty($current_order_details['notes'])): ?>
                <div class="mb-3"><strong>ملاحظات:</strong> <?php echo nl2br(htmlspecialchars($current_order_details['notes'])); ?></div>
                <?php endif; ?>

                <?php if (isset($current_order_details['shipping_address_details']) && $current_order_details['shipping_address_details']): ?>
                <div class="address-details-box">
                    <h5><i class="bi bi-geo-alt-fill me-2"></i>عنوان الشحن:</h5>
                    <p class="mb-1"><strong><?php echo htmlspecialchars($current_order_details['shipping_address_details']['full_name'] ?? ''); ?></strong></p>
                    <p class="mb-1"><?php echo htmlspecialchars($current_order_details['shipping_address_details']['address_line1']); ?></p>
                    <?php if (!empty($current_order_details['shipping_address_details']['address_line2'])): ?>
                    <p class="mb-1"><?php echo htmlspecialchars($current_order_details['shipping_address_details']['address_line2']); ?></p>
                    <?php endif; ?>
                    <p class="mb-1"><?php echo htmlspecialchars($current_order_details['shipping_address_details']['city']); ?>, <?php echo htmlspecialchars($current_order_details['shipping_address_details']['state'] ?? ''); ?> <?php echo htmlspecialchars($current_order_details['shipping_address_details']['zip_code'] ?? ''); ?></p>
                    <p class="mb-1"><?php echo htmlspecialchars($current_order_details['shipping_address_details']['country']); ?></p>
                    <?php if (!empty($current_order_details['shipping_address_details']['phone'])): ?>
                    <p class="mb-1"><strong>الهاتف:</strong> <?php echo htmlspecialchars($current_order_details['shipping_address_details']['phone']); ?></p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <div class="alert alert-warning mt-3">لم يتم العثور على تفاصيل عنوان الشحن لهذا الطلب.</div>
                <?php endif; ?>

                <h4 class="mt-4 mb-3">المنتجات في الطلب:</h4>
                <?php if (!empty($current_order_details['items'])): ?>
                    <div class="order-item-list">
                        <?php foreach ($current_order_details['items'] as $item): ?>
                            <div class="order-item">
                                <img src="<?php echo htmlspecialchars($item['product_image_url'] ?? '../../assets/images/default_product.png'); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                <div class="order-item-details">
                                    <div class="order-item-name">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                        <?php echo !empty($item['size_name']) ? ' (' . htmlspecialchars($item['size_name']) . ')' : ''; ?>
                                    </div>
                                    <div class="order-item-qty-price">
                                        الكمية: <?php echo htmlspecialchars($item['quantity']); ?> &times;
                                        السعر: <?php echo number_format($item['price_at_order'], 2); ?> ر.ي
                                    </div>
                                </div>
                                <div class="order-item-total">
                                    <?php echo number_format($item['quantity'] * $item['price_at_order'], 2); ?> ر.ي
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">لا توجد منتجات لهذا الطلب.</p>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <a href="my_orders.php" class="btn btn-secondary"><i class="bi bi-arrow-right-circle-fill me-2"></i> العودة إلى قائمة الطلبات</a>
                </div>
            </div>

        <?php else: ?>
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card" onclick="location.href='my_orders.php?order_id=<?php echo $order['id']; ?>'">
                        <div class="order-card-header">
                            <div class="order-id">طلب رقم: #<?php echo htmlspecialchars($order['id']); ?></div>
                            <div class="order-date"><?php echo date('Y-m-d H:i', strtotime($order['order_date'])); ?></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>الإجمالي:</strong> <?php echo number_format($order['total_amount'], 2); ?> ر.ي
                            </div>
                            <div class="col-md-6 text-md-end">
                                <strong>الحالة:</strong>
                                <span class="order-status status-<?php echo htmlspecialchars($order['status']); ?>">
                                    <?php echo htmlspecialchars($status_map[$order['status']] ?? $order['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>طريقة الدفع:</strong>
                                <?php echo htmlspecialchars($payment_method_map[$order['payment_method']] ?? $order['payment_method']); ?>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <strong>حالة الدفع:</strong>
                                <span class="payment-status-<?php echo htmlspecialchars($order['payment_status']); ?>">
                                    <?php echo htmlspecialchars($payment_status_map[$order['payment_status']] ?? $order['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info text-center" role="alert">
                    <i class="bi bi-info-circle me-2"></i> ليس لديك أي طلبات سابقة حتى الآن.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php // include("../../include/footer.php"); // تأكد من تضمين تذييل الصفحة إن وجد ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

<?php
if (isset($con) && $con) {
    $con->close();
}
?>