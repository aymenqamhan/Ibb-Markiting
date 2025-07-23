<?php
include('../connect_DB.php');

if(isset($_POST["create"])) {
    // التحقق من أن جميع الحقول غير فارغة
    if(!empty($_POST["name"]) && !empty($_POST["email"]) && !empty($_POST["password"]) && !empty($_POST["password_again"])) {
        $name = htmlspecialchars(trim($_POST["name"])); // استخدام trim لإزالة المسافات الزائدة
        $email = htmlspecialchars(trim($_POST['email']));
        $password = $_POST['password']; // لا تقم بـ htmlspecialchars لكلمة المرور قبل التشفير
        $password_again = $_POST['password_again'];

        // يفضل تشفير كلمة المرور هنا قبل مقارنتها وحفظها
        // مثال: $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        if ($password !== $password_again) {
            echo "<script>alert('❌ خطأ: كلمتا المرور غير متطابقتين!');</script>";
        } else {
            // تحقق مما إذا كان البريد الإلكتروني موجودًا بالفعل
            $check_email_sql = $con->prepare("SELECT id FROM user_tb WHERE email = ?");
            $check_email_sql->bind_param("s", $email);
            $check_email_sql->execute();
            $check_email_result = $check_email_sql->get_result();

            if ($check_email_result->num_rows > 0) {
                echo "<script>alert('❌ هذا البريد الإلكتروني مسجل بالفعل. يرجى استخدام بريد إلكتروني آخر.');</script>";
            } else {
                
                // تعيين دور افتراضي للمستخدم الجديد (مثلاً 2 للمستخدم العادي، 1 للمدير)
                // تأكد من وجود role_id افتراضي أو اطلب من المستخدم تحديده
                $default_role_id = 2; // يمكنك تغيير هذا بناءً على هيكل قاعدة بياناتك

                // استخدام Prepared Statement لإدخال البيانات بأمان
                $sql = $con->prepare("INSERT INTO user_tb (name, email, password, role_id) VALUES (?, ?, ?, ?)");
                if ($sql === false) {
                    echo "<script>alert('❌ خطأ في تهيئة الاستعلام: " . $con->error . "');</script>";
                } else {
                    $sql->bind_param("sssi", $name, $email, $password, $default_role_id); // 'i' لنوع role_id إذا كان عددًا صحيحًا

                    if ($sql->execute()) {
                        echo "<script>alert('✅ تم التسجيل بنجاح!');</script>";
                        echo "<script> window.location.href = './login_user.php' </script>";
                    } else {
                        echo "<script>alert('❌ فشل في تسجيل البيانات: " . $sql->error . "');</script>";
                    }
                    $sql->close();
                }
            }
            $check_email_sql->close();
        }
    } else {
        echo "<script>alert('الرجاء ملء جميع الحقول أولاً!');</script>";
    }
    // لا تغلق الاتصال هنا، بل اتركه لنهاية الصفحة
    $con->close(); // اغلاق الاتصال بقاعدة البيانات في النهاية
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>انشاء حساب</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background-color: #f8f9fa; /* لون خلفية فاتح من Bootstrap */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex; /* تفعيل فليكس بوكس */
            justify-content: center; /* توسيط أفقي */
            align-items: center; /* توسيط عمودي */
            min-height: 100vh; /* جعل الـ body يأخذ كامل ارتفاع الشاشة */
            margin: 0; /* إزالة الهامش الافتراضي للـ body */
            padding: 20px; /* لإضافة بعض الهامش حول المحتوى على الشاشات الصغيرة */
        }
        .register-card {
            background-color: white;
            border-radius: 0.75rem; /* حواف مستديرة */
            padding: 2.5rem; /* تباعد داخلي */
            box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.1); /* ظل أعمق قليلاً */
            width: 100%;
            max-width: 500px; /* أقصى عرض للبطاقة */
            text-align: center; /* توسيط النصوص والأزرار داخل البطاقة */
        }
        .register-card .form-label {
            text-align: start; /* محاذاة النص لليسار */
            width: 100%; /* جعل اللافتة تأخذ عرض 100% */
            margin-bottom: 0.25rem; /* مسافة أقل بين اللافتة وحقل الإدخال */
            font-weight: 500;
        }
        .register-card .form-control {
            margin-bottom: 1rem; /* مسافة بين حقول الإدخال */
        }
        .btn-custom-primary {
            background-color: #007bff; /* أزرق أساسي */
            border-color: #007bff;
            color: white;
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
            border-radius: 0.5rem;
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
            width: 100%; /* جعل الزر يأخذ عرض 100% */
            margin-top: 1rem; /* مسافة من حقول الإدخال */
        }
        .btn-custom-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            transform: translateY(-2px);
        }
        .btn-custom-primary:active {
            transform: translateY(0);
        }
        .link-login {
            display: block; /* لجعل الرابط يأخذ سطرًا جديدًا */
            margin-top: 1.5rem; /* مسافة من الزر العلوي */
            color: #007bff;
            text-decoration: none;
            font-size: 1rem;
            transition: color 0.2s ease-in-out;
        }
        .link-login:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        /* Overriding default alert styles for better Bootstrap integration */
        .alert {
            position: fixed; /* لتظهر الرسائل فوق المحتوى */
            top: 20px;
            right: 20px;
            z-index: 1050; /* للتأكد من ظهورها فوق العناصر الأخرى */
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
            animation: fadeOut 5s forwards; /* للتحكم في اختفاء الرسالة */
        }
        @keyframes fadeOut {
            0% { opacity: 1; }
            80% { opacity: 1; }
            100% { opacity: 0; display: none; }
        }
    </style>
</head>

<body>
    <div class="register-card">
        <h3 class="text-center text-primary mb-4 fw-bold">انشاء حساب</h3>

        <form action="create_user.php" method="post">
            <div class="mb-3">
                <label for="name" class="form-label">اسم المستخدم</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">البريد الإلكتروني</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">كلمة المرور</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label for="password_again" class="form-label">أعد كلمة المرور</label>
                <input type="password" class="form-control" id="password_again" name="password_again" required>
            </div>
            
            <button type="submit" name="create" class="btn btn-custom-primary">انشاء حساب</button>
            
            <a href="./login_user.php" class="link-login">هل لديك حساب بالفعل؟ تسجيل دخول</a>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>