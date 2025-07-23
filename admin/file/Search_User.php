<?php
session_start();
include('../connect_DB.php'); // تأكد من أن هذا المسار صحيح لاتصال قاعدة البيانات

// يجب أن يتم التحقق من تسجيل الدخول هنا أيضًا إذا كانت هذه الصفحة مخصصة للمستخدمين المسجلين فقط
if (!isset($_SESSION['user_id'])) {
    header("Location:../login/login_user.php");
    exit;
}

// الحصول على صلاحية المستخدم (إذا كنت تريد التحكم في عرض كلمة المرور بناءً عليها)
$role_id = isset($_SESSION['role_id']) ? intval($_SESSION['role_id']) : 0;
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>استعلام عن مستخدم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background-color: #f8f9fa; /* لون خلفية فاتح من Bootstrap */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex; /* تفعيل فليكس بوكس */
            flex-direction: column; /* ترتيب العناصر عمودياً */
            justify-content: center; /* توسيط عمودي */
            align-items: center; /* توسيط أفقي */
            min-height: 100vh; /* جعل الـ body يأخذ كامل ارتفاع الشاشة */
            padding: 20px; /* لإضافة بعض الهامش حول المحتوى */
            margin: 0; /* إزالة هامش الـ body الافتراضي */
        }
        .main-card { /* حاوية رئيسية تحل محل .container القديمة */
            background-color: white;
            border-radius: 0.75rem; /* حواف مستديرة */
            padding: 2rem; /* تباعد داخلي */
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.1); /* ظل ناعم */
            width: 100%;
            max-width: 600px; /* أقصى عرض للحاوية */
            display: flex;
            flex-direction: column;
            align-items: center; /* توسيط المحتوى داخل البطاقة */
        }

        /* لا حاجة لـ .title المخصصة إذا استخدمت فئات Bootstrap */
        /* لا حاجة لـ .form-group المخصصة إذا استخدمت فئات Bootstrap */

        /* تنسيقات الجدول إذا ظهر بعد البحث */
        .search-results-table-container {
            width: 100%;
            margin-top: 2rem; /* مسافة بين الفورم والجدول */
            background-color: #fff; /* خلفية بيضاء للجدول */
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.1);
            border: none;
        }
        .table-custom-header th {
            background-color: #007bff; /* Primary blue for header */
            color: white;
            border-color: #007bff;
            font-weight: 600;
        }

        /* زر العودة */
        .btn-back-custom {
            background-color: #6c757d; /* Bootstrap secondary gray */
            border-color: #6c757d;
            color: white;
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            margin-top: 20px; /* Space from content above */
        }
        .btn-back-custom:hover {
            background-color: #5a6268;
            border-color: #545b62;
            transform: translateY(-2px);
            color: white;
        }
        .btn-back-custom:active {
            transform: translateY(0);
        }
    </style>
</head>

<body>
    <div class="main-card">
        <h2 class="text-center text-primary mb-4 fw-bold">استعلام عن مستخدم</h2>
        
        <form action="" method="post" class="w-100 mb-4"> <div class="input-group"> <label for="username" class="input-group-text">الاسم</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="أدخل اسم المستخدم للبحث" />
                <button type="submit" name="search" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i> بحث
                </button>
            </div>
        </form>
    </div> <?php
    if (isset($_POST['search'])) {
        $username = trim($_POST['username']);
        if (!empty($username)) {
            // استخدام Prepared Statement لتجنب حقن SQL
            $sql = "SELECT id, name, email, password, role_id FROM user_tb WHERE name LIKE ?";
            $stmt = $con->prepare($sql);
            if ($stmt === false) {
                echo '<p class="alert alert-danger text-center mt-4">خطأ في تهيئة الاستعلام: ' . $con->error . '</p>';
            } else {
                $search_param = '%' . $username . '%';
                $stmt->bind_param("s", $search_param);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    echo '<div class="search-results-table-container mt-4">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-bordered">
                                    <thead class="table-custom-header">
                                        <tr>
                                            <th>الرقم</th>
                                            <th>اسم المستخدم</th>
                                            <th>البريد الإلكتروني</th>';

                    // إظهار كلمة المرور فقط للمدير
                    if ($role_id == 1) { // استخدام $role_id بدلاً من $_SESSION مباشرة هنا
                        echo '<th>كلمة المرور</th>';
                    }

                    echo '</tr></thead><tbody>';
                    $count = 1;
                    while ($row = $result->fetch_assoc()) {
                        echo '<tr>
                                <td>' . $count++ . '</td>
                                <td>' . htmlspecialchars($row['name']) . '</td>
                                <td>' . htmlspecialchars($row['email']) . '</td>';

                        // إظهار كلمة المرور فقط للمدير
                        if ($role_id == 1) { // استخدام $role_id
                            // ملاحظة أمنية: لا يفضل عرض كلمة المرور الخام هكذا
                            echo '<td>' . htmlspecialchars($row['password']) . '</td>';
                        }

                        echo '</tr>';
                    }
                    echo '</tbody></table></div></div>'; // إغلاق div.table-responsive و div.search-results-table-container
                } else {
                    echo '<p class="alert alert-warning text-center mt-4">لا يوجد بيانات مطابقة لاسم المستخدم هذا.</p>';
                }
                $stmt->close();
            }
        } else {
            echo '<p class="alert alert-info text-center mt-4">الرجاء إدخال اسم المستخدم للبحث.</p>';
        }
    }
    ?>
    <a href="../dashbord.php" class="btn btn-back-custom">
        <i class="fas fa-arrow-right me-2"></i> العودة للصفحة الرئيسية
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>
<?php
$con->close(); // يفضل إغلاق الاتصال بقاعدة البيانات عند الانتهاء
?>