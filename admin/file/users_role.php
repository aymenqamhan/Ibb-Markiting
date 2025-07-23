<?php
session_start();
include('../connect_DB.php'); // تأكد من أن هذا المسار صحيح لاتصال قاعدة البيانات

// التحقق من تسجيل الدخول
if (!isset($_SESSION['role_id']) || empty($_SESSION['role_id'])) {
    header("Location: ../file1/login_user.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الصلاحيات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background-color: #f8f9fa; /* لون خلفية فاتح من Bootstrap */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .custom-table-container {
            background-color: #fff;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.1);
            border: none;
        }
        .table-custom-header th {
            background-color: #007bff; /* لون أزرق أساسي للرأس */
            color: white;
            border-color: #007bff;
            font-weight: 600;
        }
        .btn-edit-custom {
            background-color: #28a745; /* أخضر */
            border-color: #28a745;
            color: white;
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
        }
        .btn-edit-custom:hover {
            background-color: #218838;
            border-color: #1e7e34;
            transform: translateY(-2px);
            color: white;
        }
        .btn-edit-custom:active {
            transform: translateY(0);
        }
        .btn-back-custom {
            background-color: #6c757d; /* رمادي */
            border-color: #6c757d;
            color: white;
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
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
    <div class="container py-4"> <h2 class="text-center text-primary mb-4 fw-bold">إدارة الصلاحيات</h2> <div class="custom-table-container p-3 my-4"> <?php
            // إذا كان المستخدم مديرًا
            if ($_SESSION['role_id'] == 1) {
                // جلب المستخدمين
                // *** تم تصحيح اسم الجدول هنا إلى 'roles' (بالجمع) بناءً على الصورة ***
                $sql = "SELECT user_tb.id, user_tb.name, roles.id AS role_id, roles.role_name, roles.description
                        FROM user_tb
                        INNER JOIN roles ON user_tb.role_id = roles.id"; // تم تصحيح 'role' إلى 'roles'

                $result = mysqli_query($con, $sql);

                if ($result && mysqli_num_rows($result) > 0) {
                    // استخدام فئات Bootstrap للجدول: .table .table-striped .table-hover .table-bordered
                    echo '<table class="table table-striped table-hover table-bordered">
                            <thead class="table-custom-header">
                                <tr>
                                    <th>رقم المستخدم</th>
                                    <th>اسم المستخدم</th>
                                    <th>الوظيفة</th>
                                    <th>رقم الوظيفة</th>
                                    <th>تعديل الوظيفة</th>
                                </tr>
                            </thead>
                            <tbody>';

                    while ($row = mysqli_fetch_assoc($result)) {
                        echo '<tr>
                                <td>' . htmlspecialchars($row['id']) . '</td>
                                <td>' . htmlspecialchars($row['name']) . '</td>
                                <td>' . htmlspecialchars($row['role_name']) . '</td>
                                <td>' . htmlspecialchars($row['role_id']) . '</td>
                                <td><a class="btn btn-edit-custom" href="./Edit_Role.php?id=' . htmlspecialchars($row['id']) . '">تعديل</a></td>
                            </tr>';
                    }

                    echo '</tbody></table>';
                } else {
                    echo '<p class="alert alert-warning text-center mt-4">لا يوجد مستخدمون بهذه الصلاحيات</p>'; // رسالة تحذير من Bootstrap
                }
            } else {
                echo '<p class="alert alert-danger text-center mt-4">ليس لديك الصلاحية لعرض هذه الصفحة</p>'; // رسالة خطأ من Bootstrap
            }
            ?>
        </div>
        <a href="../dashbord.php" class="btn btn-back-custom mt-4">
            <i class="fas fa-arrow-right me-2"></i> العودة للصفحة الرئيسية
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>