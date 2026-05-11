<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com');
define('DB_USER', '3Bfno6oj2JjfgJr.root');
define('DB_PASS', 'vEZsw0lKkIenD1cK');
define('DB_NAME', 'dormitory_db');
define('DB_PORT', 4000);
define('SITE_URL', 'https://nongmeendormitory.onrender.com');
define('SITE_NAME', 'ระบบจัดการหอพัก');

$conn = mysqli_init();
$conn->ssl_set(NULL, NULL, NULL, NULL, NULL);

if (!$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, NULL, MYSQLI_CLIENT_SSL)) {
    die('❌ DB Error: ' . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

if (!function_exists('esc')) {
    function esc($str) {
        global $conn;
        return mysqli_real_escape_string($conn, trim($str));
    }
}

if (!function_exists('getSetting')) {
    function getSetting($key) {
        global $conn;
        $key = mysqli_real_escape_string($conn, $key);
        $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '$key' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            return $res->fetch_assoc()['setting_value'];
        }
        return '';
    }
}
?>
