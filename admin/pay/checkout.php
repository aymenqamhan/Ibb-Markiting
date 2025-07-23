<?php
session_start();
include('../../include/connect_DB.php');

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'يجب تسجيل الدخول لإتمام عملية الشراء.'];
    header("Location: /NEW_IBB/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$total_cart_price = 0;
$cart_items_from_db = [];
$has_stock_issues_on_checkout = false;
$current_step = isset($_GET['step']) ? $_GET['step'] : 'payment'; // 'payment' or 'address'
$order_id_for_address = isset($_SESSION['temp_order_id']) ? intval($_SESSION['temp_order_id']) : null;
$payment_method_chosen = isset($_SESSION['payment_method_chosen']) ? $_SESSION['payment_method_chosen'] : null;

// === استرداد بيانات السلة من قاعدة البيانات وتحديث إجمالي السعر ===
$cart_query = "
    SELECT
        ci.inventory_id,
        ci.quantity AS cart_quantity,
        ci.price_at_add,
        inv.product_id,
        p.name AS product_name,
        pi.image_path AS product_image_url,
        ps.size AS size_name,
        inv.quantity AS stock_quantity
    FROM cart_items ci
    JOIN inventory inv ON ci.inventory_id = inv.id
    JOIN products p ON inv.product_id = p.id
    LEFT JOIN product_sizes ps ON inv.size_id = ps.id
    LEFT JOIN products_images pi ON p.id = pi.product_id AND pi.is_main_image = 1
    WHERE ci.user_id = ?
";

$stmt_cart = $con->prepare($cart_query);

if ($stmt_cart === false) {
    error_log("Failed to prepare cart items query in checkout.php: " . $con->error);
    $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ في قاعدة البيانات أثناء جلب سلة التسوق. الرجاء المحاولة لاحقاً.'];
    header("Location: /NEW_IBB/admin/carts/cart_view.php");
    exit();
}

$stmt_cart->bind_param("i", $user_id);
$stmt_cart->execute();
$cart_result = $stmt_cart->get_result();

if ($cart_result->num_rows === 0 && $current_step == 'payment') {
    $_SESSION['message'] = ['type' => 'info', 'text' => 'سلة التسوق فارغة، لا يمكن إتمام الشراء.'];
    header("Location: /NEW_IBB/admin/carts/cart_view.php");
    exit();
}
// If we are on the address step and there's no temporary order, something went wrong or cart was empty initially.
if ($cart_result->num_rows === 0 && $current_step == 'address' && !$order_id_for_address) {
     $_SESSION['message'] = ['type' => 'error', 'text' => 'لا يوجد طلب معلق لاختيار العنوان.'];
     header("Location: /NEW_IBB/admin/carts/cart_view.php");
     exit();
}

while ($item = $cart_result->fetch_assoc()) {
    if ($item['cart_quantity'] > $item['stock_quantity']) {
        $has_stock_issues_on_checkout = true;
    }
    $cart_items_from_db[] = $item;
    $total_cart_price += ($item['price_at_add'] * $item['cart_quantity']);
}
$stmt_cart->close();

// === جلب عناوين المستخدم لخطوة العنوان ===
$user_addresses = [];
if ($current_step == 'address') {
    $stmt_addresses = $con->prepare("SELECT id, full_name, phone, address_line1, address_line2, city, state, zip_code, country, is_default FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    if ($stmt_addresses === false) {
        error_log("Failed to prepare user addresses query: " . $con->error);
    } else {
        $stmt_addresses->bind_param("i", $user_id);
        $stmt_addresses->execute();
        $addresses_result = $stmt_addresses->get_result();
        while ($address = $addresses_result->fetch_assoc()) {
            $user_addresses[] = $address;
        }
        $stmt_addresses->close();
    }
}

// === معالجة بيانات النموذج عند الإرسال ===
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($has_stock_issues_on_checkout) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'لا يمكن إتمام الدفع بسبب وجود منتجات بكميات تتجاوز المتوفر في المخزون. يرجى العودة للسلة وتعديلها.'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if (isset($_POST['process_payment_method'])) { // الخطوة الأولى: اختيار طريقة الدفع
        $payment_method = $_POST['payment_method'];

        if ($payment_method == 'wallet') {
            $account_number = htmlspecialchars($_POST['account_number']);
            $password = $_POST['wallet_password'];

            if (empty($account_number) || empty($password)) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'الرجاء إدخال رقم الحساب وكلمة السر للمحفظة.'];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }

            $stmt_wallet = $con->prepare("SELECT id as wallet_id, user_id, password_hash, balance FROM wallets WHERE account_number = ?");
            if ($stmt_wallet === false) {
                error_log("Failed to prepare statement for wallet lookup: " . $con->error);
                $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ في قاعدة البيانات أثناء التحقق من المحفظة. الرجاء المحاولة لاحقاً.'];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
            $stmt_wallet->bind_param("s", $account_number);
            $stmt_wallet->execute();
            $result_wallet = $stmt_wallet->get_result();
            $wallet_data = $result_wallet->fetch_assoc();
            $stmt_wallet->close();

            if ($wallet_data) {
                if ($wallet_data['user_id'] != $user_id) {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'هذا الحساب لا يخص المستخدم الحالي.'];
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }

                $stored_hash = $wallet_data['password_hash'];
                $password_is_verified = false;

                if (str_starts_with($stored_hash, '$2y$') || str_starts_with($stored_hash, '$2a$')) {
                    if (password_verify($password, $stored_hash)) {
                        $password_is_verified = true;
                    }
                } else {
                    $hashed_entered_password_old_format = hash('sha256', $password);
                    if ($hashed_entered_password_old_format === $stored_hash) {
                        $password_is_verified = true;
                        $new_hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $update_hash_stmt = $con->prepare("UPDATE wallets SET password_hash = ? WHERE account_number = ?");
                        if ($update_hash_stmt === false) { error_log("Failed to prepare hash update: " . $con->error); } else {
                            $update_hash_stmt->bind_param("ss", $new_hashed_password, $account_number);
                            $update_hash_stmt->execute();
                            $update_hash_stmt->close();
                            

                        }
                         
                        $_SESSION['message'] = ['type' => 'info', 'text' => 'تم تحديث أمان كلمة مرور محفظتك تلقائياً.'];
                    }
                }

                if ($password_is_verified) {
                    if ($wallet_data['balance'] >= $total_cart_price) {
                        try {
                            $con->begin_transaction();

                            $order_date = date('Y-m-d H:i:s');
                            $status = 'pending';
                            $payment_method_db = 'wallet';
                            $payment_status_db = 'paid'; // For wallet payment, it's paid immediately

                            // Insert into orders table with payment method, but no address yet
                            $insert_order_stmt = $con->prepare("INSERT INTO orders (user_id, total_amount, order_date, status, payment_method, payment_status) VALUES (?, ?, ?, ?, ?, ?)");
                            if ($insert_order_stmt === false) {
                                throw new mysqli_sql_exception("Failed to prepare order insert: " . $con->error);
                            }
                            $insert_order_stmt->bind_param("idssss", $user_id, $total_cart_price, $order_date, $status, $payment_method_db, $payment_status_db);
                            $insert_order_stmt->execute();
                            $order_id = $con->insert_id;
                            $insert_order_stmt->close();

                            // Insert order items
                            $insert_item_stmt = $con->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_order) VALUES (?, ?, ?, ?)");
                            if ($insert_item_stmt === false) {
                                throw new mysqli_sql_exception("Failed to prepare order item insert: " . $con->error);
                            }
                            foreach ($cart_items_from_db as $item) {
                                $inventory_id = $item['inventory_id']; // This is actually inventory_id, not product_id in cart_items but order_items needs product_id or inventory_id based on your design. Assuming product_id from inventory.
                                $product_id = $item['product_id']; // Use product_id from inventory join
                                $quantity = $item['cart_quantity'];
                                $price_at_add = $item['price_at_add'];

                                // Update inventory quantity
                                $update_inventory_stmt = $con->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
                                if ($update_inventory_stmt === false) {
                                    throw new mysqli_sql_exception("Failed to prepare inventory update: " . $con->error);
                                }
                                $update_inventory_stmt->bind_param("iii", $quantity, $inventory_id, $quantity);
                                $update_inventory_stmt->execute();
                                if ($con->affected_rows === 0) {
                                    throw new mysqli_sql_exception("فشل تحديث المخزون للمنتج " . htmlspecialchars($item['product_name']) . " (" . htmlspecialchars($item['size_name']) . "). قد يكون المخزون غير كافٍ.");
                                }
                                $update_inventory_stmt->close();

                                // Bind parameters for order_items using product_id
                                $insert_item_stmt->bind_param("iiid", $order_id, $product_id, $quantity, $price_at_add);
                                $insert_item_stmt->execute();
                            }
                            $insert_item_stmt->close();

                            // Update wallet balance
                            $new_balance = $wallet_data['balance'] - $total_cart_price;
                            $update_wallet_stmt = $con->prepare("UPDATE wallets SET balance = ? WHERE account_number = ?");
                            if ($update_wallet_stmt === false) {
                                throw new mysqli_sql_exception("Failed to prepare wallet update: " . $con->error);
                            }
                            $update_wallet_stmt->bind_param("ds", $new_balance, $account_number);
                            $update_wallet_stmt->execute();
                            $update_wallet_stmt->close();

                            // Log wallet transaction (assuming wallet_transactions table exists)
                            // $transaction_type = 'debit';
                            // $transaction_description = 'دفع فاتورة طلب #' . $order_id;
                            // $insert_transaction_stmt = $con->prepare("INSERT INTO wallet_transactions (wallet_id, user_id, order_id, amount, type, description, created_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                            // if ($insert_transaction_stmt === false) {
                            //     throw new mysqli_sql_exception("Failed to prepare transaction insert: " . $con->error);
                            // }
                            // $insert_transaction_stmt->bind_param("iidss", $wallet_data['wallet_id'], $user_id, $order_id, $total_cart_price, $transaction_type, $transaction_description);
                            // $insert_transaction_stmt->execute();
                            // $insert_transaction_stmt->close();

                            // Delete cart items
                            $delete_cart_stmt = $con->prepare("DELETE FROM cart_items WHERE user_id = ?");
                            if ($delete_cart_stmt === false) {
                                throw new mysqli_sql_exception("Failed to prepare cart delete: " . $con->error);
                            }
                            $delete_cart_stmt->bind_param("i", $user_id);
                            $delete_cart_stmt->execute();
                            $delete_cart_stmt->close();

                            $con->commit();
                            $_SESSION['temp_order_id'] = $order_id; // Store order_id for next step
                            $_SESSION['payment_method_chosen'] = $payment_method_db; // Store payment method
                            $_SESSION['message'] = ['type' => 'success', 'text' => 'تم الدفع بنجاح! الآن اختر عنوان الشحن.'];
                            header("Location: " . $_SERVER['PHP_SELF'] . "?step=address");
                            exit();

                        } catch (mysqli_sql_exception $e) {
                            $con->rollback();
                            error_log("خطأ في عملية الشراء (المحفظة): " . $e->getMessage());
                            $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ أثناء إتمام عملية الدفع: ' . $e->getMessage() . '. الرجاء المحاولة مرة أخرى.'];
                            header("Location: " . $_SERVER['PHP_SELF']);
                            exit();
                        }
                    } else {
                        $_SESSION['message'] = ['type' => 'error', 'text' => 'رصيدك غير كافٍ لإتمام عملية الشراء. رصيدك الحالي: ' . number_format($wallet_data['balance'], 2) . '$. المبلغ المطلوب: ' . number_format($total_cart_price, 2) . '$.'];
                    }
                } else {
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'كلمة مرور المحفظة غير صحيحة.'];
                }
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'رقم الحساب غير موجود.'];
            }
        } elseif ($payment_method == 'cash_on_delivery') {
            try {
                $con->begin_transaction();

                $order_date = date('Y-m-d H:i:s');
                $status = 'pending'; // Order is pending until COD payment is confirmed
                $payment_method_db = 'cash_on_delivery';
                $payment_status_db = 'unpaid'; // Initial status for COD

                // Insert into orders table with payment method, but no address yet
                $insert_order_stmt = $con->prepare("INSERT INTO orders (user_id, total_amount, order_date, status, payment_method, payment_status) VALUES (?, ?, ?, ?, ?, ?)");
                if ($insert_order_stmt === false) {
                    throw new mysqli_sql_exception("Failed to prepare order insert (COD): " . $con->error);
                }
                $insert_order_stmt->bind_param("idssss", $user_id, $total_cart_price, $order_date, $status, $payment_method_db, $payment_status_db);
                $insert_order_stmt->execute();
                $order_id = $con->insert_id;
                $insert_order_stmt->close();

                // Insert order items
                $insert_item_stmt = $con->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_order) VALUES (?, ?, ?, ?)");
                if ($insert_item_stmt === false) {
                    throw new mysqli_sql_exception("Failed to prepare order item insert (COD): " . $con->error);
                }
                foreach ($cart_items_from_db as $item) {
                    $inventory_id = $item['inventory_id'];
                    $product_id = $item['product_id']; // Use product_id from inventory join
                    $quantity = $item['cart_quantity'];
                    $price_at_add = $item['price_at_add'];

                    // Update inventory quantity
                    $update_inventory_stmt = $con->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
                    if ($update_inventory_stmt === false) {
                        throw new mysqli_sql_exception("Failed to prepare inventory update (COD): " . $con->error);
                    }
                    $update_inventory_stmt->bind_param("iii", $quantity, $inventory_id, $quantity);
                    $update_inventory_stmt->execute();
                    if ($con->affected_rows === 0) {
                        throw new mysqli_sql_exception("فشل تحديث المخزون للمنتج " . htmlspecialchars($item['product_name']) . " (" . htmlspecialchars($item['size_name']) . "). قد يكون المخزون غير كافٍ.");
                    }
                    $update_inventory_stmt->close();

                    // Bind parameters for order_items using product_id
                    $insert_item_stmt->bind_param("iiid", $order_id, $product_id, $quantity, $price_at_add);
                    $insert_item_stmt->execute();
                }
                $insert_item_stmt->close();

                // Delete cart items
                $delete_cart_stmt = $con->prepare("DELETE FROM cart_items WHERE user_id = ?");
                if ($delete_cart_stmt === false) {
                    throw new mysqli_sql_exception("Failed to prepare cart delete (COD): " . $con->error);
                }
                $delete_cart_stmt->bind_param("i", $user_id);
                $delete_cart_stmt->execute();
                $delete_cart_stmt->close();

                $con->commit();
                $_SESSION['temp_order_id'] = $order_id; // Store order_id for next step
                $_SESSION['payment_method_chosen'] = $payment_method_db; // Store payment method
                $_SESSION['message'] = ['type' => 'success', 'text' => 'تم تسجيل طلبك للدفع عند الاستلام بنجاح! الآن اختر عنوان الشحن.'];
                header("Location: " . $_SERVER['PHP_SELF'] . "?step=address");
                exit();

            } catch (mysqli_sql_exception $e) {
                $con->rollback();
                error_log("خطأ في عملية الشراء (الدفع عند الاستلام): " . $e->getMessage());
                $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ أثناء إتمام عملية الطلب: ' . $e->getMessage() . '. الرجاء المحاولة مرة أخرى.'];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'الرجاء اختيار طريقة دفع صحيحة.'];
        }
        header("Location: " . $_SERVER['PHP_SELF']); // Redirect in case of an error within this step
        exit();

    } elseif (isset($_POST['confirm_address']) && $order_id_for_address) { // الخطوة الثانية: اختيار العنوان
        $selected_address_id = isset($_POST['existing_address_id']) ? intval($_POST['existing_address_id']) : null;
        $order_id = $order_id_for_address;

        // If no existing address is selected, try to add a new one
        if (!$selected_address_id && isset($_POST['new_address_line1'])) {
            $full_name = htmlspecialchars($_POST['new_full_name'] ?? '');
            $phone = htmlspecialchars($_POST['new_phone'] ?? '');
            $address_line1 = htmlspecialchars($_POST['new_address_line1']);
            $address_line2 = htmlspecialchars($_POST['new_address_line2'] ?? '');
            $city = htmlspecialchars($_POST['new_city']);
            $state = htmlspecialchars($_POST['new_state'] ?? '');
            $zip_code = htmlspecialchars($_POST['new_zip_code'] ?? '');
            $country = htmlspecialchars($_POST['new_country'] ?? 'Yemen');
            $is_default_new = isset($_POST['is_default_new']) ? 1 : 0;
            $address_type = 'shipping'; // Assuming new address is for shipping

            if (empty($address_line1) || empty($city) || empty($country)) {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'الرجاء ملء جميع الحقول المطلوبة للعنوان الجديد (السطر 1، المدينة، الدولة).'];
                header("Location: " . $_SERVER['PHP_SELF'] . "?step=address");
                exit();
            }

            try {
                $con->begin_transaction();

                // If setting as default, unset other defaults for this user
                if ($is_default_new) {
                    $update_default_stmt = $con->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ? AND is_default = 1");
                    if ($update_default_stmt === false) {
                        throw new mysqli_sql_exception("Failed to prepare default address update: " . $con->error);
                    }
                    $update_default_stmt->bind_param("i", $user_id);
                    $update_default_stmt->execute();
                    $update_default_stmt->close();
                }

                $insert_address_stmt = $con->prepare("INSERT INTO addresses (user_id, full_name, phone, address_line1, address_line2, city, state, zip_code, country, is_default, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($insert_address_stmt === false) {
                    throw new mysqli_sql_exception("Failed to prepare insert new address: " . $con->error);
                }
                $insert_address_stmt->bind_param("issssssssis", $user_id, $full_name, $phone, $address_line1, $address_line2, $city, $state, $zip_code, $country, $is_default_new, $address_type);
                $insert_address_stmt->execute();
                $selected_address_id = $con->insert_id;
                $insert_address_stmt->close();

                $con->commit();

            } catch (mysqli_sql_exception $e) {
                $con->rollback();
                error_log("خطأ في إضافة العنوان الجديد: " . $e->getMessage());
                $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ أثناء إضافة العنوان الجديد: ' . $e->getMessage() . '. الرجاء المحاولة مرة أخرى.'];
                header("Location: " . $_SERVER['PHP_SELF'] . "?step=address");
                exit();
            }
        }

        if ($selected_address_id) {
            // Update the order with the selected shipping address ID
            $update_order_address_stmt = $con->prepare("UPDATE orders SET shipping_address = ? WHERE id = ? AND user_id = ?");
            if ($update_order_address_stmt === false) {
                error_log("Failed to prepare order address update: " . $con->error);
                $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ في قاعدة البيانات أثناء حفظ العنوان. الرجاء المحاولة لاحقاً.'];
                header("Location: " . $_SERVER['PHP_SELF'] . "?step=address");
                exit();
            }
            $update_order_address_stmt->bind_param("iii", $selected_address_id, $order_id, $user_id);
            $update_order_address_stmt->execute();
            $update_order_address_stmt->close();

            unset($_SESSION['temp_order_id']); // Clear temporary order_id
            unset($_SESSION['payment_method_chosen']); // Clear temporary payment method
            $_SESSION['message'] = ['type' => 'success', 'text' => 'تم تأكيد طلبك بنجاح! رقم طلبك هو: <strong>' . $order_id . '</strong>.'];
            header("Location: /NEW_IBB/user/order_confirmation.php?order_id=" . $order_id);
            exit();

        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'الرجاء اختيار عنوان شحن أو إضافة عنوان جديد.'];
            header("Location: " . $_SERVER['PHP_SELF'] . "?step=address");
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
    <title>إتمام الشراء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/NEW_IBB/Style/mainscrain.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .checkout-container {
            max-width: 900px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            padding: 30px;
        }
        .checkout-header {
            text-align: center;
            margin-bottom: 30px;
            color: #0d6efd;
            font-weight: 700;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .order-summary-section, .payment-details-section, .address-selection-section {
            padding: 20px;
            border-radius: 0.75rem;
            background-color: #f8f9fa;
            margin-bottom: 25px;
            border: 1px solid #dee2e6;
        }
        .section-title {
            color: #343a40;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
        }
        .product-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #e9ecef;
        }
        .product-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .product-item img {
            width: 60px;
            height: 60px;
            border-radius: 0.5rem;
            margin-left: 15px;
            object-fit: cover;
            border: 1px solid #ddd;
        }
        .product-item-details {
            flex-grow: 1;
        }
        .product-item-name {
            font-weight: 600;
            color: #333;
            font-size: 1.1em;
        }
        .product-item-qty-price {
            color: #6c757d;
            font-size: 0.95em;
        }
        .product-item-total {
            font-weight: bold;
            color: #28a745;
            white-space: nowrap;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        .summary-total {
            font-weight: bold;
            font-size: 1.6rem;
            color: #dc3545;
            padding-top: 15px;
            margin-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }
        .form-control {
            border-radius: 0.5rem;
            padding: 12px 15px;
            font-size: 1.05rem;
            border: 1px solid #ced4da;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .btn-checkout {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
            padding: 12px 25px;
            font-size: 1.2rem;
            border-radius: 0.75rem;
            transition: background-color 0.3s ease, transform 0.2s ease;
            width: 100%;
        }
        .btn-checkout:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
            transform: translateY(-2px);
        }
        .btn-pay { /* Button for final confirmation (address step) */
            background-color: #28a745;
            border-color: #28a745;
            color: white;
            padding: 12px 25px;
            font-size: 1.2rem;
            border-radius: 0.75rem;
            transition: background-color 0.3s ease, transform 0.2s ease;
            width: 100%;
        }
        .btn-pay:hover {
            background-color: #218838;
            border-color: #1e7e34;
            transform: translateY(-2px);
        }
        .btn-back {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
            padding: 10px 20px;
            font-size: 1.1rem;
            border-radius: 0.75rem;
            margin-top: 20px;
            transition: background-color 0.3s ease;
        }
        .btn-back:hover {
            color: #fff;
            background-color: #5a6268;
            border-color: #545b62;
        }
        .stock-warning {
            color: #dc3545;
            font-size: 0.9em;
            margin-top: 5px;
            font-weight: bold;
        }
        .payment-method-option {
            border: 1px solid #ccc;
            border-radius: 0.75rem;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .payment-method-option.selected {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            background-color: #e7f0ff;
        }
        .payment-method-option input[type="radio"] {
            margin-top: 5px;
            margin-left: 10px;
        }
        .payment-method-details {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #eee;
        }
        .hidden-details {
            display: none;
        }
        .address-item {
            border: 1px solid #ced4da;
            border-radius: 0.5rem;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #fff;
        }
        .address-item.selected-address {
            border-color: #28a745;
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
            background-color: #e6ffe9;
        }
        .address-item:hover {
            background-color: #f2f2f2;
        }
        .address-item input[type="radio"] {
            margin-top: 5px;
            margin-left: 10px;
        }
        .add-new-address-toggle {
            cursor: pointer;
            color: #0d6efd;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .new-address-form {
            display: none; /* Hidden by default */
            padding-top: 15px;
            border-top: 1px dashed #eee;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <?php // include("../../include/head.php"); ?>

    <div class="checkout-container">
        <h2 class="checkout-header">
            <?php if ($current_step == 'payment'): ?>
                <i class="bi bi-credit-card-fill me-2"></i> اختيار طريقة الدفع
            <?php elseif ($current_step == 'address'): ?>
                <i class="bi bi-geo-alt-fill me-2"></i> اختيار عنوان الشحن
            <?php endif; ?>
        </h2>

        <?php echo $message; ?>

        <div class="order-summary-section">
            <h3 class="section-title"><i class="bi bi-card-checklist me-2"></i> ملخص الطلب</h3>
            <?php if (!empty($cart_items_from_db)): ?>
                <?php foreach ($cart_items_from_db as $item): ?>
                    <?php $item_subtotal = $item['cart_quantity'] * $item['price_at_add']; ?>
                    <div class="product-item">
                        <img src="<?php echo htmlspecialchars($item['product_image_url'] ?? '../../assets/images/default_product.png'); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                        <div class="product-item-details">
                            <div class="product-item-name">
                                <?php echo htmlspecialchars($item['product_name']); ?>
                                <?php echo !empty($item['size_name']) ? ' (' . htmlspecialchars($item['size_name']) . ')' : ''; ?>
                            </div>
                            <div class="product-item-qty-price">
                                الكمية: <?php echo htmlspecialchars($item['cart_quantity']); ?> &times;
                                السعر: <?php echo number_format($item['price_at_add'], 2); ?> ر.ي
                            </div>
                            <?php if ($item['cart_quantity'] > $item['stock_quantity']): ?>
                                <div class="stock-warning">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> الكمية المطلوبة (<?php echo $item['cart_quantity']; ?>) تتجاوز المتوفر (<?php echo $item['stock_quantity']; ?>).
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="product-item-total">
                            <?php echo number_format($item_subtotal, 2); ?> ر.ي
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-muted">لا توجد منتجات في سلة التسوق حالياً.</p>
            <?php endif; ?>

            <div class="summary-total">
                <span>الإجمالي الكلي:</span>
                <span><?php echo number_format($total_cart_price, 2); ?> ر.ي</span>
            </div>
        </div>

        <?php if ($current_step == 'payment'): ?>
            <div class="payment-details-section">
                <h3 class="section-title"><i class="bi bi-credit-card-fill me-2"></i> اختر طريقة الدفع</h3>
                <form action="" method="post" id="paymentMethodForm">
                    <div class="mb-3">
                        <div class="payment-method-option" id="walletOption">
                            <label class="d-flex align-items-center mb-0 w-100" for="payment_method_wallet">
                                <input type="radio" name="payment_method" id="payment_method_wallet" value="wallet" checked>
                                <span class="ms-2"><i class="bi bi-wallet-fill me-2"></i> الدفع عبر المحفظة الإلكترونية</span>
                            </label>
                            <div id="walletDetails" class="payment-method-details">
                                <div class="mb-3 mt-3">
                                    <label for="account_number" class="form-label">رقم حساب المحفظة:</label>
                                    <input type="text" id="account_number" name="account_number" class="form-control" placeholder="ادخل رقم حساب المحفظة" required>
                                </div>
                                <div class="mb-3">
                                    <label for="wallet_password" class="form-label">كلمة مرور المحفظة:</label>
                                    <input type="password" id="wallet_password" name="wallet_password" class="form-control" placeholder="ادخل كلمة مرور المحفظة" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="payment-method-option" id="codOption">
                            <label class="d-flex align-items-center mb-0 w-100" for="payment_method_cod">
                                <input type="radio" name="payment_method" id="payment_method_cod" value="cash_on_delivery">
                                <span class="ms-2"><i class="bi bi-cash-stack me-2"></i> الدفع عند الاستلام</span>
                            </label>
                            <div id="codDetails" class="payment-method-details hidden-details">
                                <p class="text-muted text-center mt-3 mb-0">ستدفع عند استلام طلبك.</p>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" name="process_payment_method" class="btn btn-checkout"
                                <?php echo $has_stock_issues_on_checkout ? 'disabled' : ''; ?>>
                            <i class="bi bi-arrow-left-circle-fill me-2"></i> متابعة
                        </button>
                        <?php if ($has_stock_issues_on_checkout): ?>
                            <small class="text-danger text-center mt-2">لا يمكن المتابعة بسبب مشاكل في المخزون. يرجى مراجعة ملخص الطلب.</small>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php elseif ($current_step == 'address' && $order_id_for_address): ?>
            <div class="address-selection-section">
                <h3 class="section-title"><i class="bi bi-geo-alt-fill me-2"></i> اختر عنوان الشحن</h3>
                <form action="" method="post" id="addressForm">
                    <input type="hidden" name="confirm_address" value="1">
                    <?php if (!empty($user_addresses)): ?>
                        <p class="text-muted text-center">اختر أحد عناوينك المحفوظة:</p>
                        <?php foreach ($user_addresses as $address): ?>
                            <div class="address-item" data-address-id="<?php echo $address['id']; ?>">
                                <label class="d-flex align-items-start mb-0 w-100" for="address_<?php echo $address['id']; ?>">
                                    <input type="radio" name="existing_address_id" id="address_<?php echo $address['id']; ?>" value="<?php echo $address['id']; ?>" <?php echo $address['is_default'] ? 'checked' : ''; ?>>
                                    <div class="ms-2 flex-grow-1">
                                        <strong><?php echo htmlspecialchars($address['full_name'] ?? ''); ?></strong><br>
                                        <?php echo htmlspecialchars($address['address_line1']); ?><br>
                                        <?php echo !empty($address['address_line2']) ? htmlspecialchars($address['address_line2']) . '<br>' : ''; ?>
                                        <?php echo htmlspecialchars($address['city']); ?>, <?php echo htmlspecialchars($address['state'] ?? ''); ?> <?php echo htmlspecialchars($address['zip_code'] ?? ''); ?><br>
                                        <?php echo htmlspecialchars($address['country']); ?><br>
                                        <?php echo !empty($address['phone']) ? 'الهاتف: ' . htmlspecialchars($address['phone']) : ''; ?>
                                        <?php if ($address['is_default']): ?>
                                            <span class="badge bg-info text-dark ms-2">افتراضي</span>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <hr>
                    <?php endif; ?>

                    <div class="add-new-address-toggle" data-bs-toggle="collapse" href="#addNewAddressForm" role="button" aria-expanded="false" aria-controls="addNewAddressForm">
                        <i class="bi bi-plus-circle-fill me-2"></i> أضف عنوانًا جديدًا
                    </div>
                    <div class="collapse new-address-form" id="addNewAddressForm">
                        <h4 class="mb-3">إضافة عنوان جديد</h4>
                        <div class="mb-3">
                            <label for="new_full_name" class="form-label">الاسم الكامل (اختياري):</label>
                            <input type="text" id="new_full_name" name="new_full_name" class="form-control" placeholder="ادخل الاسم الكامل للمستلم">
                        </div>
                        <div class="mb-3">
                            <label for="new_phone" class="form-label">رقم الهاتف (اختياري):</label>
                            <input type="text" id="new_phone" name="new_phone" class="form-control" placeholder="ادخل رقم الهاتف">
                        </div>
                        <div class="mb-3">
                            <label for="new_address_line1" class="form-label">الشارع/المنزل (السطر 1):</label>
                            <input type="text" id="new_address_line1" name="new_address_line1" class="form-control" placeholder="مثال: شارع رئيسي، مبنى 123">
                        </div>
                        <div class="mb-3">
                            <label for="new_address_line2" class="form-label">تفاصيل إضافية (السطر 2 - اختياري):</label>
                            <input type="text" id="new_address_line2" name="new_address_line2" class="form-control" placeholder="مثال: شقة 4، بالقرب من المسجد">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_city" class="form-label">المدينة:</label>
                                <input type="text" id="new_city" name="new_city" class="form-control" placeholder="مثال: صنعاء">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_state" class="form-label">المنطقة/المحافظة (اختياري):</label>
                                <input type="text" id="new_state" name="new_state" class="form-control" placeholder="مثال: الأمانة">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_zip_code" class="form-label">الرمز البريدي (اختياري):</label>
                                <input type="text" id="new_zip_code" name="new_zip_code" class="form-control" placeholder="مثال: 12345">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_country" class="form-label">الدولة:</label>
                                <input type="text" id="new_country" name="new_country" class="form-control" value="اليمن" required>
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="is_default_new" name="is_default_new">
                            <label class="form-check-label" for="is_default_new">
                                تعيين كعنوان افتراضي
                            </label>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-pay">
                            <i class="bi bi-map-fill me-2"></i> تأكيد العنوان وإتمام الطلب
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="text-center">
            <a href="/NEW_IBB/admin/carts/cart_view.php" class="btn btn-secondary btn-back">
                <i class="bi bi-arrow-right-circle-fill me-2"></i> العودة إلى السلة
            </a>
        </div>
    </div>

    <?php // include("../../include/footer.php"); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const walletOption = document.getElementById('walletOption');
            const codOption = document.getElementById('codOption');
            const walletDetails = document.getElementById('walletDetails');
            const codDetails = document.getElementById('codDetails');
            const paymentMethodRadios = document.querySelectorAll('input[name="payment_method"]');
            const newAddressToggle = document.querySelector('.add-new-address-toggle');
            const addNewAddressForm = document.getElementById('addNewAddressForm');
            const existingAddressRadios = document.querySelectorAll('input[name="existing_address_id"]');
            const addressItems = document.querySelectorAll('.address-item');

            function togglePaymentDetails() {
                if (document.getElementById('payment_method_wallet').checked) {
                    walletDetails.classList.remove('hidden-details');
                    codDetails.classList.add('hidden-details');
                    walletOption.classList.add('selected');
                    codOption.classList.remove('selected');
                    // Make wallet fields required
                    document.getElementById('account_number').setAttribute('required', 'required');
                    document.getElementById('wallet_password').setAttribute('required', 'required');
                } else {
                    walletDetails.classList.add('hidden-details');
                    codDetails.classList.remove('hidden-details');
                    walletOption.classList.remove('selected');
                    codOption.classList.add('selected');
                    // Remove required from wallet fields
                    document.getElementById('account_number').removeAttribute('required');
                    document.getElementById('wallet_password').removeAttribute('required');
                }
            }

            // For the first step (payment methods)
            if (walletOption && codOption) {
                paymentMethodRadios.forEach(radio => {
                    radio.addEventListener('change', togglePaymentDetails);
                });
                togglePaymentDetails(); // Initial call to set the correct state on page load
            }

            // For the second step (address selection)
            if (newAddressToggle && addNewAddressForm) {
                newAddressToggle.addEventListener('click', function() {
                    // Uncheck any existing address when opening new address form
                    existingAddressRadios.forEach(radio => {
                        radio.checked = false;
                        radio.closest('.address-item').classList.remove('selected-address');
                    });
                    // Make new address fields required when opening the form, optional when closing
                    const isCollapsed = !addNewAddressForm.classList.contains('show'); // Check if it's currently collapsed (about to be shown)
                    const newAddressFields = addNewAddressForm.querySelectorAll('input[required]');
                    if (isCollapsed) {
                         // Set required for new address fields when showing
                        document.getElementById('new_address_line1').setAttribute('required', 'required');
                        document.getElementById('new_city').setAttribute('required', 'required');
                        document.getElementById('new_country').setAttribute('required', 'required');
                    } else {
                        // Remove required for new address fields when hiding
                        document.getElementById('new_address_line1').removeAttribute('required');
                        document.getElementById('new_city').removeAttribute('required');
                        document.getElementById('new_country').removeAttribute('required');
                    }
                });

                // Set required for new address fields if form is already open on page load (e.g., due to validation error)
                if (addNewAddressForm.classList.contains('show')) {
                    document.getElementById('new_address_line1').setAttribute('required', 'required');
                    document.getElementById('new_city').setAttribute('required', 'required');
                    document.getElementById('new_country').setAttribute('required', 'required');
                }
            }

            addressItems.forEach(item => {
                item.addEventListener('click', function() {
                    addressItems.forEach(i => i.classList.remove('selected-address'));
                    this.classList.add('selected-address');
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                        // When an existing address is selected, hide the new address form and remove its required attributes
                        if (addNewAddressForm.classList.contains('show')) {
                            const collapse = new bootstrap.Collapse(addNewAddressForm, { toggle: false });
                            collapse.hide();
                        }
                        document.getElementById('new_address_line1').removeAttribute('required');
                        document.getElementById('new_city').removeAttribute('required');
                        document.getElementById('new_country').removeAttribute('required');
                    }
                });
            });

            // Set default selected address visually on load
            existingAddressRadios.forEach(radio => {
                if (radio.checked) {
                    radio.closest('.address-item').classList.add('selected-address');
                }
            });
        });
    </script>
    <div class="row">
    <div class="col-md-6">
        <div class="card p-3 mb-3">
            <h5>اختر عنوان التوصيل أو أضف عنوانًا جديدًا</h5>
            </div>
    </div>
</div>
</body>
</html>

<?php
if (isset($con) && $con) {
    $con->close();
}
?>