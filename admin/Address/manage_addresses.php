<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../../include/connect_DB.php'); // تأكد من المسار الصحيح لملف اتصال قاعدة البيانات

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'يجب تسجيل الدخول لإدارة العناوين.'];
    header("Location: /NEW_IBB/login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$message = '';

// --- معالجة طلبات POST (إضافة، تعديل، حذف، تعيين كافتراضي) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_address'])) {
        $full_name = htmlspecialchars($_POST['full_name'] ?? '');
        $phone = htmlspecialchars($_POST['phone'] ?? '');
        $address_line1 = htmlspecialchars($_POST['address_line1'] ?? '');
        $address_line2 = htmlspecialchars($_POST['address_line2'] ?? '');
        $city = htmlspecialchars($_POST['city'] ?? '');
        $state = htmlspecialchars($_POST['state'] ?? '');
        $zip_code = htmlspecialchars($_POST['zip_code'] ?? '');
        $country = htmlspecialchars($_POST['country'] ?? 'Yemen');
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        $address_type = htmlspecialchars($_POST['address_type'] ?? 'shipping'); // يمكن أن يكون 'shipping', 'billing', 'both'

        if (empty($full_name) || empty($phone) || empty($address_line1) || empty($city) || empty($country)) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'الرجاء ملء جميع الحقول المطلوبة (الاسم الكامل، رقم الهاتف، عنوان الشارع 1، المدينة، الدولة).'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        try {
            $con->begin_transaction();

            // إذا تم تعيينه كافتراضي، قم بإلغاء تعيين العناوين الافتراضية الأخرى للمستخدم
            if ($is_default) {
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
            $insert_address_stmt->bind_param("issssssssis", $user_id, $full_name, $phone, $address_line1, $address_line2, $city, $state, $zip_code, $country, $is_default, $address_type);
            $insert_address_stmt->execute();
            $insert_address_stmt->close();

            $con->commit();
            $_SESSION['message'] = ['type' => 'success', 'text' => 'تم إضافة العنوان بنجاح!'];
        } catch (mysqli_sql_exception $e) {
            $con->rollback();
            error_log("Error adding address: " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ أثناء إضافة العنوان: ' . $e->getMessage()];
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } elseif (isset($_POST['edit_address'])) {
        $address_id = intval($_POST['address_id']);
        $full_name = htmlspecialchars($_POST['full_name'] ?? '');
        $phone = htmlspecialchars($_POST['phone'] ?? '');
        $address_line1 = htmlspecialchars($_POST['address_line1'] ?? '');
        $address_line2 = htmlspecialchars($_POST['address_line2'] ?? '');
        $city = htmlspecialchars($_POST['city'] ?? '');
        $state = htmlspecialchars($_POST['state'] ?? '');
        $zip_code = htmlspecialchars($_POST['zip_code'] ?? '');
        $country = htmlspecialchars($_POST['country'] ?? 'Yemen');
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        $address_type = htmlspecialchars($_POST['address_type'] ?? 'shipping');

        if (empty($full_name) || empty($phone) || empty($address_line1) || empty($city) || empty($country)) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'الرجاء ملء جميع الحقول المطلوبة (الاسم الكامل، رقم الهاتف، عنوان الشارع 1، المدينة، الدولة).'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        try {
            $con->begin_transaction();

            // إذا تم تعيينه كافتراضي، قم بإلغاء تعيين العناوين الافتراضية الأخرى للمستخدم
            if ($is_default) {
                $update_default_stmt = $con->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ? AND id != ? AND is_default = 1");
                if ($update_default_stmt === false) {
                    throw new mysqli_sql_exception("Failed to prepare default address unset: " . $con->error);
                }
                $update_default_stmt->bind_param("ii", $user_id, $address_id);
                $update_default_stmt->execute();
                $update_default_stmt->close();
            }

            $update_address_stmt = $con->prepare("UPDATE addresses SET full_name = ?, phone = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, zip_code = ?, country = ?, is_default = ?, type = ? WHERE id = ? AND user_id = ?");
            if ($update_address_stmt === false) {
                throw new mysqli_sql_exception("Failed to prepare update address: " . $con->error);
            }
            $update_address_stmt->bind_param("sssssssisii", $full_name, $phone, $address_line1, $address_line2, $city, $state, $zip_code, $country, $is_default, $address_type, $address_id, $user_id);
            $update_address_stmt->execute();
            $update_address_stmt->close();

            $con->commit();
            if ($con->affected_rows > 0) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'تم تعديل العنوان بنجاح!'];
            } else {
                $_SESSION['message'] = ['type' => 'info', 'text' => 'لم يتم إجراء أي تغييرات على العنوان أو أن العنوان غير موجود.'];
            }
        } catch (mysqli_sql_exception $e) {
            $con->rollback();
            error_log("Error updating address: " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ أثناء تعديل العنوان: ' . $e->getMessage()];
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } elseif (isset($_POST['delete_address'])) {
        $address_id = intval($_POST['address_id']);

        try {
            $delete_address_stmt = $con->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
            if ($delete_address_stmt === false) {
                throw new mysqli_sql_exception("Failed to prepare delete address: " . $con->error);
            }
            $delete_address_stmt->bind_param("ii", $address_id, $user_id);
            $delete_address_stmt->execute();

            if ($con->affected_rows > 0) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'تم حذف العنوان بنجاح!'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'فشل حذف العنوان أو أن العنوان غير موجود/ليس مملوكاً لك.'];
            }
            $delete_address_stmt->close();
        } catch (mysqli_sql_exception $e) {
            error_log("Error deleting address: " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ أثناء حذف العنوان: ' . $e->getMessage()];
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } elseif (isset($_POST['set_default'])) {
        $address_id = intval($_POST['address_id']);

        try {
            $con->begin_transaction();

            // Unset current default for this user
            $update_old_default_stmt = $con->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ? AND is_default = 1");
            if ($update_old_default_stmt === false) {
                throw new mysqli_sql_exception("Failed to prepare unset old default: " . $con->error);
            }
            $update_old_default_stmt->bind_param("i", $user_id);
            $update_old_default_stmt->execute();
            $update_old_default_stmt->close();

            // Set new default
            $set_new_default_stmt = $con->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
            if ($set_new_default_stmt === false) {
                throw new mysqli_sql_exception("Failed to prepare set new default: " . $con->error);
            }
            $set_new_default_stmt->bind_param("ii", $address_id, $user_id);
            $set_new_default_stmt->execute();

            if ($con->affected_rows > 0) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'تم تعيين العنوان كافتراضي بنجاح!'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'فشل تعيين العنوان كافتراضي.'];
            }
            $set_new_default_stmt->close();
            $con->commit();
        } catch (mysqli_sql_exception $e) {
            $con->rollback();
            error_log("Error setting default address: " . $e->getMessage());
            $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ أثناء تعيين العنوان كافتراضي: ' . $e->getMessage()];
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// --- جلب جميع عناوين المستخدم ---
$user_addresses = [];
$stmt_addresses = $con->prepare("SELECT id, full_name, phone, address_line1, address_line2, city, state, zip_code, country, is_default, type FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
if ($stmt_addresses === false) {
    error_log("Failed to prepare user addresses query: " . $con->error);
    $_SESSION['message'] = ['type' => 'error', 'text' => 'حدث خطأ في قاعدة البيانات أثناء جلب العناوين.'];
} else {
    $stmt_addresses->bind_param("i", $user_id);
    $stmt_addresses->execute();
    $addresses_result = $stmt_addresses->get_result();
    while ($address = $addresses_result->fetch_assoc()) {
        $user_addresses[] = $address;
    }
    $stmt_addresses->close();
}

// === عرض رسائل النظام ===
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
    <title>إدارة العناوين</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/NEW_IBB/Style/mainscrain.css"> <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 960px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #0d6efd;
            font-weight: 700;
        }
        .address-card {
            border: 1px solid #dee2e6;
            border-radius: 0.75rem;
            padding: 20px;
            margin-bottom: 20px;
            background-color: #fefefe;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.05);
            position: relative;
        }
        .address-card.default-address {
            border-color: #28a745;
            background-color: #e6ffe9;
            box-shadow: 0 0.25rem 0.5rem rgba(40, 167, 69, 0.1);
        }
        .address-card .default-badge {
            position: absolute;
            top: 15px;
            left: 15px; /* Adjust for RTL */
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 0.5rem;
            font-size: 0.85em;
            font-weight: bold;
        }
        .address-card h5 {
            color: #343a40;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .address-card p {
            margin-bottom: 5px;
            color: #6c757d;
        }
        .address-actions {
            margin-top: 15px;
            border-top: 1px dashed #e9ecef;
            padding-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .address-actions .btn {
            font-size: 0.9em;
            padding: 8px 15px;
            border-radius: 0.5rem;
        }
        .modal-content {
            border-radius: 1rem;
        }
        .modal-header {
            border-bottom: none;
            padding: 1.5rem 1.5rem 0.5rem 1.5rem;
        }
        .modal-title {
            font-weight: bold;
            color: #0d6efd;
        }
        .modal-body {
            padding: 0.5rem 1.5rem 1.5rem 1.5rem;
        }
        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }
        .form-control {
            border-radius: 0.5rem;
            padding: 10px 12px;
            font-size: 1em;
            border: 1px solid #ced4da;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
    </style>
</head>
<body>
    <?php // include("../../include/head.php"); // هذا الجزء غالباً يكون الهيدر العام للموقع ?>

    <div class="container">
        <h2><i class="bi bi-geo-alt-fill me-2"></i> إدارة العناوين</h2>

        <?php echo $message; // عرض رسائل النظام هنا (نجاح/خطأ) ?>

        <div class="d-grid gap-2 mb-4">
            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                <i class="bi bi-plus-circle me-2"></i> إضافة عنوان جديد
            </button>
        </div>

        <?php if (empty($user_addresses)): ?>
            <div class="alert alert-info text-center" role="alert">
                <i class="bi bi-info-circle me-2"></i> لم يتم إضافة أي عناوين بعد.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($user_addresses as $address): ?>
                    <div class="col-md-6">
                        <div class="address-card <?php echo $address['is_default'] ? 'default-address' : ''; ?>">
                            <?php if ($address['is_default']): ?>
                                <span class="default-badge"><i class="bi bi-star-fill me-1"></i> افتراضي</span>
                            <?php endif; ?>
                            <h5><?php echo htmlspecialchars($address['full_name']); ?></h5>
                            <p><i class="bi bi-telephone-fill me-2"></i> <?php echo htmlspecialchars($address['phone']); ?></p>
                            <p><i class="bi bi-house-door-fill me-2"></i> <?php echo htmlspecialchars($address['address_line1']); ?></p>
                            <?php if (!empty($address['address_line2'])): ?>
                                <p><?php echo htmlspecialchars($address['address_line2']); ?></p>
                            <?php endif; ?>
                            <p><i class="bi bi-building me-2"></i> <?php echo htmlspecialchars($address['city']); ?>, <?php echo htmlspecialchars($address['state']); ?> <?php echo htmlspecialchars($address['zip_code']); ?></p>
                            <p><i class="bi bi-globe me-2"></i> <?php echo htmlspecialchars($address['country']); ?></p>
                            <p><small class="text-muted">النوع: <?php echo htmlspecialchars($address['type']); ?></small></p>

                            <div class="address-actions">
                                <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#editAddressModal"
                                    data-id="<?php echo $address['id']; ?>"
                                    data-fullname="<?php echo htmlspecialchars($address['full_name']); ?>"
                                    data-phone="<?php echo htmlspecialchars($address['phone']); ?>"
                                    data-address1="<?php echo htmlspecialchars($address['address_line1']); ?>"
                                    data-address2="<?php echo htmlspecialchars($address['address_line2']); ?>"
                                    data-city="<?php echo htmlspecialchars($address['city']); ?>"
                                    data-state="<?php echo htmlspecialchars($address['state']); ?>"
                                    data-zip="<?php echo htmlspecialchars($address['zip_code']); ?>"
                                    data-country="<?php echo htmlspecialchars($address['country']); ?>"
                                    data-isdefault="<?php echo $address['is_default']; ?>"
                                    data-type="<?php echo htmlspecialchars($address['type']); ?>">
                                    <i class="bi bi-pencil-square me-1"></i> تعديل
                                </button>
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAddressModal" data-id="<?php echo $address['id']; ?>">
                                    <i class="bi bi-trash me-1"></i> حذف
                                </button>
                                <?php if (!$address['is_default']): ?>
                                    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" class="d-inline-block">
                                        <input type="hidden" name="set_default" value="1">
                                        <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-star me-1"></i> تعيين كافتراضي
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="addAddressModal" tabindex="-1" aria-labelledby="addAddressModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAddressModalLabel"><i class="bi bi-plus-circle me-2"></i> إضافة عنوان جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="add_address" value="1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="add_full_name" class="form-label">الاسم الكامل:</label>
                                <input type="text" class="form-control" id="add_full_name" name="full_name" placeholder="الاسم الكامل" required>
                            </div>
                            <div class="col-md-6">
                                <label for="add_phone" class="form-label">رقم الهاتف:</label>
                                <input type="text" class="form-control" id="add_phone" name="phone" placeholder="رقم الهاتف" required>
                            </div>
                            <div class="col-12">
                                <label for="add_address_line1" class="form-label">عنوان الشارع 1:</label>
                                <input type="text" class="form-control" id="add_address_line1" name="address_line1" placeholder="مثال: شارع رئيسي 123" required>
                            </div>
                            <div class="col-12">
                                <label for="add_address_line2" class="form-label">عنوان الشارع 2 (اختياري):</label>
                                <input type="text" class="form-control" id="add_address_line2" name="address_line2" placeholder="مثال: شقة 4ب">
                            </div>
                            <div class="col-md-6">
                                <label for="add_city" class="form-label">المدينة:</label>
                                <input type="text" class="form-control" id="add_city" name="city" required>
                            </div>
                            <div class="col-md-4">
                                <label for="add_state" class="form-label">المحافظة/الولاية:</label>
                                <input type="text" class="form-control" id="add_state" name="state">
                            </div>
                            <div class="col-md-2">
                                <label for="add_zip_code" class="form-label">الرمز البريدي:</label>
                                <input type="text" class="form-control" id="add_zip_code" name="zip_code">
                            </div>
                            <div class="col-md-6">
                                <label for="add_country" class="form-label">الدولة:</label>
                                <input type="text" class="form-control" id="add_country" name="country" value="Yemen" required>
                            </div>
                            <div class="col-md-6">
                                <label for="add_address_type" class="form-label">نوع العنوان:</label>
                                <select class="form-select" id="add_address_type" name="address_type">
                                    <option value="shipping" selected>شحن</option>
                                    <option value="billing">فوترة</option>
                                    <option value="both">كلاهما</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="add_is_default" name="is_default">
                                    <label class="form-check-label" for="add_is_default">تعيين كعنوان افتراضي</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إضافة العنوان</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editAddressModal" tabindex="-1" aria-labelledby="editAddressModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAddressModalLabel"><i class="bi bi-pencil-square me-2"></i> تعديل العنوان</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_address" value="1">
                        <input type="hidden" name="address_id" id="edit_address_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_full_name" class="form-label">الاسم الكامل:</label>
                                <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_phone" class="form-label">رقم الهاتف:</label>
                                <input type="text" class="form-control" id="edit_phone" name="phone" required>
                            </div>
                            <div class="col-12">
                                <label for="edit_address_line1" class="form-label">عنوان الشارع 1:</label>
                                <input type="text" class="form-control" id="edit_address_line1" name="address_line1" required>
                            </div>
                            <div class="col-12">
                                <label for="edit_address_line2" class="form-label">عنوان الشارع 2 (اختياري):</label>
                                <input type="text" class="form-control" id="edit_address_line2" name="address_line2">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_city" class="form-label">المدينة:</label>
                                <input type="text" class="form-control" id="edit_city" name="city" required>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_state" class="form-label">المحافظة/الولاية:</label>
                                <input type="text" class="form-control" id="edit_state" name="state">
                            </div>
                            <div class="col-md-2">
                                <label for="edit_zip_code" class="form-label">الرمز البريدي:</label>
                                <input type="text" class="form-control" id="edit_zip_code" name="zip_code">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_country" class="form-label">الدولة:</label>
                                <input type="text" class="form-control" id="edit_country" name="country" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_address_type" class="form-label">نوع العنوان:</label>
                                <select class="form-select" id="edit_address_type" name="address_type">
                                    <option value="shipping">شحن</option>
                                    <option value="billing">فوترة</option>
                                    <option value="both">كلاهما</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_is_default" name="is_default">
                                    <label class="form-check-label" for="edit_is_default">تعيين كعنوان افتراضي</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteAddressModal" tabindex="-1" aria-labelledby="deleteAddressModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAddressModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i> تأكيد الحذف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="delete_address" value="1">
                        <input type="hidden" name="address_id" id="delete_address_id">
                        <p>هل أنت متأكد أنك تريد حذف هذا العنوان؟ لا يمكن التراجع عن هذا الإجراء.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger">نعم، احذف</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Populate Edit Address Modal
            var editAddressModal = document.getElementById('editAddressModal');
            if (editAddressModal) {
                editAddressModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget; // Button that triggered the modal
                    var id = button.getAttribute('data-id');
                    var fullName = button.getAttribute('data-fullname');
                    var phone = button.getAttribute('data-phone');
                    var address1 = button.getAttribute('data-address1');
                    var address2 = button.getAttribute('data-address2');
                    var city = button.getAttribute('data-city');
                    var state = button.getAttribute('data-state');
                    var zip = button.getAttribute('data-zip');
                    var country = button.getAttribute('data-country');
                    var isDefault = button.getAttribute('data-isdefault');
                    var type = button.getAttribute('data-type');

                    var modalIdInput = editAddressModal.querySelector('#edit_address_id');
                    var modalFullNameInput = editAddressModal.querySelector('#edit_full_name');
                    var modalPhoneInput = editAddressModal.querySelector('#edit_phone');
                    var modalAddress1Input = editAddressModal.querySelector('#edit_address_line1');
                    var modalAddress2Input = editAddressModal.querySelector('#edit_address_line2');
                    var modalCityInput = editAddressModal.querySelector('#edit_city');
                    var modalStateInput = editAddressModal.querySelector('#edit_state');
                    var modalZipInput = editAddressModal.querySelector('#edit_zip_code');
                    var modalCountryInput = editAddressModal.querySelector('#edit_country');
                    var modalIsDefaultCheckbox = editAddressModal.querySelector('#edit_is_default');
                    var modalAddressTypeSelect = editAddressModal.querySelector('#edit_address_type');

                    modalIdInput.value = id;
                    modalFullNameInput.value = fullName;
                    modalPhoneInput.value = phone;
                    modalAddress1Input.value = address1;
                    modalAddress2Input.value = address2;
                    modalCityInput.value = city;
                    modalStateInput.value = state;
                    modalZipInput.value = zip;
                    modalCountryInput.value = country;
                    modalIsDefaultCheckbox.checked = (isDefault == '1');
                    modalAddressTypeSelect.value = type;
                });
            }

            // Populate Delete Address Modal
            var deleteAddressModal = document.getElementById('deleteAddressModal');
            if (deleteAddressModal) {
                deleteAddressModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget; // Button that triggered the modal
                    var id = button.getAttribute('data-id');
                    var modalIdInput = deleteAddressModal.querySelector('#delete_address_id');
                    modalIdInput.value = id;
                });
            }
        });
    </script>
</body>
</html>
<?php
// إغلاق الاتصال بقاعدة البيانات في نهاية الصفحة
if (isset($con) && $con instanceof mysqli) {
    $con->close();
}
?>