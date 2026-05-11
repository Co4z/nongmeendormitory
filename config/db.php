<?php
// 1. ต้องมีบรรทัดนี้ที่บนสุด เพื่อให้ระบบจำการ Login ได้
session_start(); 

// =============================================
// config/db.php - เวอร์ชั่นสมบูรณ์ (TiDB Cloud + SSL)
// =============================================

define('DB_HOST', 'gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com');
define('DB_USER', '3Bfno6oj2JjfgJr.root'); 
define('DB_PASS', 'vEZsw0lKkIenD1cK'); 
define('DB_NAME', 'dormitory_db'); 
define('DB_PORT', 4000); 

define('SITE_URL', 'https://nongmeendormitory.onrender.com'); 
define('SITE_NAME', 'ระบบจัดการหอพัก');

// เริ่มต้นการเชื่อมต่อแบบปลอดภัย (SSL)
$conn = mysqli_init();
$conn->ssl_set(NULL, NULL, NULL, NULL, NULL);

if (!$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, NULL, MYSQLI_CLIENT_SSL)) {
    die('❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . mysqli_connect_error());
}

// 2. เพิ่มบรรทัดนี้เพื่อให้แสดงผลภาษาไทยได้ถูกต้อง ไม่เป็นภาษาต่างดาว
$conn->set_charset("utf8mb4");

// 3. เพิ่มฟังก์ชัน esc เพราะหน้า index.php มีการเรียกใช้ (ถ้าไม่มีหน้าเว็บจะขาวโพลน)
function esc($str) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($str));
}

// ฟังก์ชันตรวจสอบการ Login (สำหรับใช้ในหน้าอื่นๆ)
function requireLogin() {
    if (empty($_SESSION['ad_id'])) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

// ฟังก์ชันดึงค่า Setting
function getSetting($key) {
    global $conn;
    $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '$key' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc()['setting_value'];
    }
    return '';
}
?>
