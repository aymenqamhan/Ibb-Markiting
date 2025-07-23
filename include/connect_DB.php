<?php
if (!defined('DB_SERVER')) {
    define('DB_SERVER', 'localhost');
}
if (!defined('DB_USERNAME')) {
    define('DB_USERNAME', 'root');
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', '1234');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'Users_DB2');
}

// إنشاء الاتصال بقاعدة البيانات
$con = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// التحقق من الاتصال بقاعدة البيانات
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error); // تصحيح الخطأ في الكتابة من "coonect_error" إلى "connect_error"
}
