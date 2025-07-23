<?php
session_start();
include('../connect_DB.php');

// دالة لمعالجة المدخلات
function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data); // لا تزال تستخدم لمدخلات المستخدم غير كلمة المرور
    return $data;
}

// معالجة طلب تسجيل الخروج
if (isset($_GET['logout']) && $_GET['logout'] == 'out') {
    session_unset();
    session_destroy();
    echo "<script> window.location.href = './login_user.php';</script>";
    exit();
}

// معالجة طلب تسجيل الدخول
if(isset($_POST["login"])) {
    $mail = test_input($_POST["email"]);
    // هنا لا يتم تشفير كلمة المرور أو استخدام htmlspecialchars
    $password_input = $_POST["password"]; 

    // استخدام Prepared Statement لجلب بيانات المستخدم
    $sql = "SELECT id, name, email, password, role_id FROM user_tb WHERE email=?";
    $stmt = mysqli_prepare($con, $sql);
    
    if ($stmt === false) {
        echo "<script>alert('❌ خطأ في تهيئة الاستعلام: " . mysqli_error($con) . "');</script>";
    } else {
        mysqli_stmt_bind_param($stmt, "s", $mail);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            // التحقق من كلمة المرور (مقارنة نص عادي - غير آمن)
            if ($password_input === $row['password']) { // تم التغيير هنا
                session_regenerate_id(true);
                $_SESSION["user_name"] = $row['name'];
                $_SESSION["role_id"] = $row['role_id'];
                $_SESSION["user_id"] = $row['id'];

                // جلب تفاصيل الدور
                $role_id = $row['role_id'];
                $role_sql = "SELECT role_name, description FROM roles WHERE id=?";
                $role_stmt = mysqli_prepare($con, $role_sql);
                mysqli_stmt_bind_param($role_stmt, "i", $role_id);
                mysqli_stmt_execute($role_stmt);
                $role_result = mysqli_stmt_get_result($role_stmt);
                $role_data = mysqli_fetch_assoc($role_result);

                $role_name = $role_data['role_name'] ?? 'غير معروف';
                $role_description = $role_data['description'] ?? 'لا يوجد وصف';

                // توجيه المستخدم بناءً على الدور
                if (in_array($role_id, [1, 3, 4, 5])) {
                    echo "<script>
                            alert('✅ تم تسجيل دخولك بنجاح! \\nوظيفتك هي: " . $role_name . " \\n" . $role_description . "');
                            setTimeout(function() {
                                window.location.href = '../dashbord.php';
                            }, 1000);
                          </script>";
                } else {
                    echo "<script>
                            alert('✅ تم تسجيل دخولك بنجاح! \\nوظيفتك هي: " . $role_name . " \\n" . $role_description . "');
                            setTimeout(function() {
                                window.location.href = '/NEW_IBB/index.php';
                            }, 1000);
                          </script>";
                }
                exit();
            } else {
                echo "<script>alert('❌ كلمة المرور غير صحيحة!');</script>";
            }
        } else {
            echo "<script>alert('❌ البريد الإلكتروني غير مسجل!');</script>";
        }

        mysqli_stmt_close($stmt);
    }
    mysqli_close($con);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .login-card {
            background-color: white;
            border-radius: 0.75rem;
            padding: 2.5rem;
            box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        .login-card .form-label {
            text-align: start;
            width: 100%;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }
        .login-card .form-control {
            margin-bottom: 1rem;
        }
        .btn-custom-primary {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
            border-radius: 0.5rem;
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
            width: 100%;
            margin-top: 1rem;
        }
        .btn-custom-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            transform: translateY(-2px);
        }
        .btn-custom-primary:active {
            transform: translateY(0);
        }
        .link-text {
            display: block;
            margin-top: 1.5rem;
            color: #007bff;
            text-decoration: none;
            font-size: 1rem;
            transition: color 0.2s ease-in-out;
        }
        .link-text:hover {
            color: #0056b3;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h3 class="text-center text-primary mb-4 fw-bold">تسجيل دخول</h3>

        <form action="./login_user.php" method="Post">
            <div class="mb-3">
                <label for="email" class="form-label">البريد الإلكتروني</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">كلمة المرور</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <button type="submit" name="login" class="btn btn-custom-primary">دخول</button>
            
            <a href="./create_user.php" class="link-text">انشاء حساب جديد؟</a>
            <a href="/NEW_IBB/index.php" class="link-text">العودة للصفحة الرئيسية</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>