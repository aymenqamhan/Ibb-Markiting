<?php
include('connect_DB.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// تسجيل الخروج
if(isset($_GET['logout'])){
    session_destroy();
    echo "<script>alert('تم تسجيل الخروج بنجاح!');</script>";
    header("Location:/new_ibb/index.php");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="/new_ibb/Style/header.css">
</head>
<body>
<header class="navbar">
        <div class="logo">
            <img src="/new_ibb/image/icon/logo-removebg-preview.png" alt="logo">
        </div>
    
        <!-- زر القائمة للجوال -->
        <div class="menu-toggle">☰</div>

        <nav>
            <ul>
                <li><a href="/new_ibb/admin/Carts/cart_view.php">السله</a></li>
                <li><a href="#">آراء عملائنا</a></li>
                <li><a href="#">العروض</a></li>
                <li><a href="/new_ibb/include/abutus.php">حولنا</a></li>
                <li class="dropdown">
                    <a href="/new_ibb/file1/catogries.php">الفئات</a>
                    <ul class="dropdown-menu">
                        <li><a href="/new_ibb/admin/categories/categories_in_page/section_man/pants_man.php">ملابس</a></li>
                        <li><a href="#">أجهزة إلكترونية</a></li>
                        <li><a href="#">أجهزة رقمية</a></li>
                        <li><a href="#">سيارات</a></li>
                    </ul>
                </li>
                <?php if (isset($_SESSION['role_id']) && ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 3|| $_SESSION['role_id'] == 4|| $_SESSION['role_id'] == 5)): ?>
                    <li><a href="/new_ibb/admin/dashbord.php">الإدارة</a></li>
                <?php endif; ?>
                
                <li><a href="/new_ibb/index.php">الرئيسية</a></li>
            </ul>
        </nav>

        <!-- نموذج البحث -->
        <form action="search.php" method="GET" class="search-form">
            <input type="text" name="query" placeholder="ابحث هنا..." required>
            <button type="submit">🔍</button>
        </form>
    <div class="login">
        <?php if (isset($_SESSION['user_name'])): ?>
        <a href="#" onclick="logout()">
            <div class="logout">
                <h5><?php echo $_SESSION["user_name"]; ?></h5>
                <img src="/new_ibb/image/icon/logout.png" alt="logout">
            </div>
        </a>
        <?php elseif (!isset($_SESSION['user_name'])): ?>
        <a href="/new_ibb/admin/login/login_user.php">
            <img src="/new_ibb/image/icon/enter.png" alt="login">
        </a>
<?php endif; ?>
</div>
</header>
<script>
    function logout() {
    if (confirm("هل أنت متأكد من تسجيل الخروج؟")) {
        window.location.href = "/new_ibb/admin/login/login_user.php?logout=out";
    }
    }
    // ✅ تشغيل القائمة الجانبية للجوال
    document.querySelector('.menu-toggle').addEventListener('click', function() {
        document.querySelector('nav ul').classList.toggle('active');
    });
</script>
</body>
</html>