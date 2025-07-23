<?php
session_start();
include('../connect_DB.php'); // تأكد من المسار الصحيح لاتصال قاعدة البيانات

// التحقق من تسجيل دخول المستخدم
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "الرجاء تسجيل الدخول لعرض سلة التسوق.";
    header("Location: /NEW_IBB/login.php"); // أو صفحة تسجيل الدخول الخاصة بك
    exit();
}

$user_id = intval($_SESSION['user_id']);
$cart_items = [];
$total_cart_amount = 0;
$message = '';

// معالجة رسائل النظام (نجاح/خطأ/معلومات)
if (isset($_SESSION['message'])) {
    $msg_type = $_SESSION['message']['type'];
    $msg_text = $_SESSION['message']['text'];
    $bootstrap_alert_type = '';
    if ($msg_type == 'success') {
        $bootstrap_alert_type = 'alert-success';
    } elseif ($msg_type == 'error') {
        $bootstrap_alert_type = 'alert-danger';
    } else { // info or default
        $bootstrap_alert_type = 'alert-info';
    }
    $message = "<div class='alert $bootstrap_alert_type alert-dismissible fade show' role='alert'>
                    $msg_text
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
    unset($_SESSION['message']);
}

// --- معالجة تحديث/حذف الكميات ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $con) {
    if (isset($_POST['update_quantity'])) {
        $inventory_id_to_update = intval($_POST['inventory_id']);
        $new_quantity = intval($_POST['new_quantity']);

        if ($new_quantity > 0) {
            // التحقق من توفر الكمية في المخزون قبل التحديث
            $stmt_check_stock = $con->prepare("SELECT quantity FROM inventory WHERE id = ?");
            $stmt_check_stock->bind_param("i", $inventory_id_to_update);
            $stmt_check_stock->execute();
            $result_stock = $stmt_check_stock->get_result();
            $stock_data = $result_stock->fetch_assoc();
            $stmt_check_stock->close();

            if ($stock_data && $new_quantity <= $stock_data['quantity']) {
                $stmt_update = $con->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND inventory_id = ?");
                if ($stmt_update) {
                    $stmt_update->bind_param("iii", $new_quantity, $user_id, $inventory_id_to_update);
                    $stmt_update->execute();
                    $stmt_update->close();
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'تم تحديث كمية المنتج في السلة.'];
                } else {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'خطأ في تحضير تحديث الكمية: ' . $con->error];
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'الكمية المطلوبة غير متوفرة في المخزون. الكمية المتاحة: ' . ($stock_data ? $stock_data['quantity'] : 'غير معروف')];
            }
        } else {
            // إذا كانت الكمية 0 أو أقل، احذف العنصر
            $stmt_delete = $con->prepare("DELETE FROM cart_items WHERE user_id = ? AND inventory_id = ?");
            if ($stmt_delete) {
                $stmt_delete->bind_param("ii", $user_id, $inventory_id_to_update);
                $stmt_delete->execute();
                $stmt_delete->close();
                $_SESSION['message'] = ['type' => 'success', 'text' => 'تم حذف المنتج من السلة.'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'خطأ في تحضير حذف المنتج: ' . $con->error];
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } elseif (isset($_POST['delete_item'])) {
        $inventory_id_to_delete = intval($_POST['inventory_id']);
        $stmt_delete = $con->prepare("DELETE FROM cart_items WHERE user_id = ? AND inventory_id = ?");
        if ($stmt_delete) {
            $stmt_delete->bind_param("ii", $user_id, $inventory_id_to_delete);
            $stmt_delete->execute();
            $stmt_delete->close();
            $_SESSION['message'] = ['type' => 'success', 'text' => 'تم حذف المنتج من السلة.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'خطأ في تحضير حذف المنتج: ' . $con->error];
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}


// --- جلب عناصر السلة من قاعدة البيانات ---
if ($con) {
    $stmt_cart = $con->prepare("
        SELECT 
            ci.inventory_id, 
            ci.quantity, 
            ci.price_at_add,
            p.name AS product_name,
            inv.quantity AS available_stock_quantity -- جلب الكمية المتاحة في المخزون
        FROM cart_items ci
        JOIN inventory inv ON ci.inventory_id = inv.id
        JOIN products p ON inv.product_id = p.id
        WHERE ci.user_id = ?
        ORDER BY p.name ASC
    ");
    if ($stmt_cart) {
        $stmt_cart->bind_param("i", $user_id);
        $stmt_cart->execute();
        $result_cart = $stmt_cart->get_result();
        while ($row = $result_cart->fetch_assoc()) {
            $cart_items[] = $row;
            // حساب الإجمالي الكلي
            $total_cart_amount += ($row['quantity'] * $row['price_at_add']);
        }
        $stmt_cart->close();
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => "خطأ في جلب عناصر السلة: " . $con->error];
        // إعادة التوجيه لتجنب عرض الصفحة مع رسالة خطأ بعد فشل التحضير
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
} else {
    $_SESSION['message'] = ['type' => 'error', 'text' => "خطأ في الاتصال بقاعدة البيانات."];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سلة التسوق</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../Style.css"> <style>
        body {
            background-color: #f8f9fa; /* Light background */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            margin-top: 40px;
            margin-bottom: 40px;
        }
        .cart-header {
            text-align: center;
            margin-bottom: 30px;
            color: #0d6efd; /* Bootstrap primary blue */
            font-weight: 700;
        }
        .cart-item-card {
            background-color: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
        }
        .cart-item-card .product-details {
            flex-grow: 1;
            padding-right: 15px;
        }
        .cart-item-card .product-name {
            font-weight: bold;
            font-size: 1.25rem;
            color: #343a40;
        }
        .cart-item-card .product-price {
            font-size: 1.1rem;
            color: #6c757d;
        }
        .cart-item-card .quantity-control {
            display: flex;
            align-items: center;
            margin-top: 10px;
            flex-shrink: 0; /* Prevent shrinking */
        }
        .cart-item-card .quantity-control .form-control {
            width: 70px;
            text-align: center;
            margin: 0 5px;
            border-radius: 0.5rem;
        }
        .cart-item-card .item-total {
            font-weight: bold;
            font-size: 1.25rem;
            color: #28a745; /* Success green */
            margin-left: auto; /* Push to the right */
            white-space: nowrap; /* Prevent wrapping */
        }
        .cart-item-card .action-buttons {
            margin-left: 20px;
            flex-shrink: 0;
            display: flex; /* For alignment of buttons */
        }
        .cart-summary-card {
            background-color: #e9f5ff; /* Light blue background for summary */
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-top: 30px;
        }
        .cart-summary-card .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        .cart-summary-card .summary-total {
            font-size: 1.5rem;
            font-weight: bold;
            color: #0d6efd;
            border-top: 1px solid #cce5ff;
            padding-top: 15px;
            margin-top: 15px;
        }
        .empty-cart {
            text-align: center;
            padding: 50px;
            background-color: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
            color: #6c757d;
        }
        .empty-cart h2 {
            color: #dc3545; /* Danger red */
            margin-bottom: 20px;
        }
        .checkout-buttons .btn {
            padding: 12px 25px;
            font-size: 1.1rem;
            border-radius: 0.75rem;
        }
        .btn-checkout {
            background-color: #0d6efd; /* Primary blue */
            border-color: #0d6efd;
            color: white;
        }
        .btn-checkout:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        .btn-continue-shopping {
            background-color: #6c757d; /* Secondary grey */
            border-color: #6c757d;
            color: white;
        }
        .btn-continue-shopping:hover {
            background-color: #5a6268;
            border-color: #5a6268;
        }
        .stock-warning {
            color: #dc3545; /* Red for warning */
            font-size: 0.9em;
            margin-top: 5px;
        }
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .cart-item-card {
                flex-direction: column;
                align-items: flex-start;
            }
            .cart-item-card .product-details {
                padding-right: 0;
                width: 100%;
                margin-bottom: 10px;
            }
            .cart-item-card .quantity-control {
                width: 100%;
                justify-content: center;
                margin-bottom: 10px;
            }
            .cart-item-card .item-total {
                margin-left: 0;
                width: 100%;
                text-align: center;
                margin-top: 10px;
            }
            .cart-item-card .action-buttons {
                margin-left: 0;
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="cart-header"><i class="bi bi-cart-fill me-3"></i> سلة التسوق الخاصة بك</h1>
        
        <?php echo $message; ?>

        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <h2><i class="bi bi-emoji-frown me-2"></i> سلتك فارغة!</h2>
                <p>يبدو أنك لم تضف أي منتجات بعد. ابدأ التسوق الآن!</p>
                <a href="/NEW_IBB/index.php" class="btn btn-primary btn-lg mt-3">
                    <i class="bi bi-shop me-2"></i> ابدأ التسوق
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-lg-8">
                    <?php foreach ($cart_items as $item): 
                        $item_total = $item['quantity'] * $item['price_at_add'];
                        $stock_exceeded = $item['quantity'] > $item['available_stock_quantity'];
                    ?>
                        <div class="cart-item-card">
                            <div class="product-details">
                                <div class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="product-price">السعر: <?php echo number_format($item['price_at_add'], 2); ?> ر.ي</div>
                                <?php if ($stock_exceeded): ?>
                                    <div class="stock-warning">
                                        <i class="bi bi-exclamation-triangle-fill"></i> الكمية المطلوبة (<?php echo $item['quantity']; ?>) تتجاوز المتوفر (<?php echo $item['available_stock_quantity']; ?>). يرجى التعديل.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="quantity-control">
                                <form action="" method="POST" class="d-flex align-items-center">
                                    <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                                    <button type="submit" name="update_quantity" value="minus" class="btn btn-outline-secondary btn-sm"
                                            <?php echo ($item['quantity'] <= 1) ? 'disabled' : ''; ?>
                                            onclick="this.form.new_quantity.value = parseInt(this.form.new_quantity.value) - 1;">
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <input type="number" name="new_quantity" class="form-control form-control-sm" 
                                           value="<?php echo htmlspecialchars($item['quantity']); ?>" 
                                           min="1" max="<?php echo htmlspecialchars($item['available_stock_quantity']); ?>"
                                           onchange="this.form.submit()"> <button type="submit" name="update_quantity" value="plus" class="btn btn-outline-secondary btn-sm"
                                            <?php echo ($item['quantity'] >= $item['available_stock_quantity']) ? 'disabled' : ''; ?>
                                            onclick="this.form.new_quantity.value = parseInt(this.form.new_quantity.value) + 1;">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="item-total">
                                الإجمالي: <?php echo number_format($item_total, 2); ?> ر.ي
                            </div>
                            <div class="action-buttons">
                                <form action="" method="POST" class="ms-3">
                                    <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                                    <button type="submit" name="delete_item" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-trash"></i> حذف
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="col-lg-4">
                    <div class="cart-summary-card">
                        <h4 class="mb-4 text-center">ملخص السلة</h4>
                        <div class="summary-item">
                            <span>عدد المنتجات:</span>
                            <span><?php echo count($cart_items); ?></span>
                        </div>
                        <div class="summary-item">
                            <span>الإجمالي الفرعي:</span>
                            <span><?php echo number_format($total_cart_amount, 2); ?> ر.ي</span>
                        </div>
                        <div class="summary-item summary-total">
                            <span>الإجمالي الكلي:</span>
                            <span><?php echo number_format($total_cart_amount, 2); ?> ر.ي</span>
                        </div>
                        <div class="d-grid gap-2 mt-4 checkout-buttons">
                            <?php 
                                // تحقق مما إذا كانت هناك أي كميات تتجاوز المتوفر في المخزون
                                $has_stock_issues = false;
                                foreach ($cart_items as $item) {
                                    if ($item['quantity'] > $item['available_stock_quantity']) {
                                        $has_stock_issues = true;
                                        break;
                                    }
                                }
                            ?>
                            <a href="../pay/checkout.php" class="btn btn-checkout" <?php echo $has_stock_issues ? 'disabled' : ''; ?>>
                                <i class="bi bi-bag-check-fill me-2"></i> المتابعة إلى الدفع
                            </a>
                            <?php if ($has_stock_issues): ?>
                                <small class="text-danger text-center">يرجى تعديل الكميات التي تتجاوز المتوفر قبل المتابعة.</small>
                            <?php endif; ?>

                            <a href="/NEW_IBB/index.php" class="btn btn-continue-shopping mt-2">
                                <i class="bi bi-arrow-left-circle-fill me-2"></i> متابعة التسوق
                            </a>
                        </div>
                    </div>
                    
                </div>
>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // سكريبت لتحديث الكمية عند تغيير حقل input مباشرة
        // هذا ليس ضروريًا إذا كنت تعتمد على زر + و - فقط
        // ولكن يضيف مرونة إذا قام المستخدم بتعديل الرقم يدوياً
        document.querySelectorAll('.quantity-control input[type="number"]').forEach(input => {
            input.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });
    </script>
</body>
</html>

<?php
// إغلاق اتصال قاعدة البيانات
if (isset($con) && $con) {
    $con->close();
}
?>