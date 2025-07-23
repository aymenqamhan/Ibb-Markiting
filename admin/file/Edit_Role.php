<?php
session_start();
include('../connect_DB.php');

if (!isset($_SESSION['role_id'])) {
    header("Location: ../file1/login_user.php");
    exit;
}

$user_data = null;
$user_id = null;

if (isset($_GET['id'])) {
    $user_id = $_GET['id'];

    $sql = "SELECT user_tb.id, user_tb.name, roles.id AS role_id, roles.role_name, roles.description
            FROM user_tb
            INNER JOIN roles ON user_tb.role_id = roles.id
            WHERE user_tb.id = ?";
    
    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $user_data = mysqli_fetch_assoc($result);
        } else {
            echo '<script>alert("لا يوجد مستخدم بهذا المعرف"); window.location.href="./users_role.php";</script>';
            exit;
        }
        mysqli_stmt_close($stmt);
    } else {
        echo '<script>alert("خطأ في تهيئة الاستعلام");</script>';
        exit;
    }
} else {
    echo '<script>alert("لم يتم تحديد معرف المستخدم"); window.location.href="./users_role.php";</script>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_role_id = $_POST['role_id'];

    $update_sql = "UPDATE user_tb SET role_id = ? WHERE id = ?";
    if ($stmt = mysqli_prepare($con, $update_sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $new_role_id, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            echo '<script>alert("تم تعديل الوظيفة بنجاح"); window.location.href="./users_role.php";</script>';
        } else {
            echo '<script>alert("حدث خطأ أثناء تعديل الوظيفة: ' . mysqli_error($con) . '");</script>';
        }
        mysqli_stmt_close($stmt);
    } else {
        echo '<script>alert("خطأ في تهيئة استعلام التحديث");</script>';
    }
}

$roles_sql = "SELECT id, role_name, description FROM roles";
$roles_result = mysqli_query($con, $roles_sql);
if (!$roles_result) {
    echo '<script>alert("خطأ في جلب الوظائف: ' . mysqli_error($con) . '");</script>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل الوظيفة</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            max-width: 500px;
            width: 100%;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .dropdown-menu {
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>

<div class="container">
    <h2 class="text-center text-primary mb-4">تعديل الوظيفة - <?php echo htmlspecialchars($user_data['name']); ?></h2>

    <div class="card mx-auto">
        <div class="card-body">
            <form method="POST">
                <label for="roleDropdown" class="form-label d-block text-end">اختر الوظيفة الجديدة:</label>
                <div class="dropdown text-end w-100 mb-3">
                    <button class="btn btn-outline-primary dropdown-toggle w-100" type="button" id="roleDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php echo htmlspecialchars($user_data['role_name']); ?>
                    </button>
                    <ul class="dropdown-menu w-100 text-end" aria-labelledby="roleDropdown">
                        <?php
                        mysqli_data_seek($roles_result, 0);
                        while ($role = mysqli_fetch_assoc($roles_result)):
                        ?>
                            <li>
                                <a class="dropdown-item<?php echo ($user_data['role_id'] == $role['id']) ? ' active' : ''; ?>" href="#" onclick="selectRole(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['role_name']); ?>')">
                                    <?php echo htmlspecialchars($role['role_name']) . ' - ' . htmlspecialchars($role['description']); ?>
                                </a>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                    <input type="hidden" name="role_id" id="role_id" value="<?php echo $user_data['role_id']; ?>">
                </div>

                <button type="submit" class="btn btn-success w-100">حفظ التعديل</button>
            </form>
        </div>
    </div>

    <div class="text-center mt-3">
        <button class="btn btn-secondary" onclick="window.location.href=document.referrer;">العودة</button>
    </div>
</div>

<script>
    function selectRole(id, name) {
        document.getElementById('role_id').value = id;
        document.querySelector('#roleDropdown').textContent = name;
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
