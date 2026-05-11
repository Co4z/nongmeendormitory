<?php
// =============================================
// config/db.php - เวอร์ชั่นสมบูรณ์ (TiDB Cloud + SSL)
// =============================================

define('DB_HOST', 'gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com');
// ⚠️ ตรวจสอบรหัส Prefix หน้า .root ให้ตรงกับที่เห็นในหน้า Connect ของ TiDB อีกครั้งครับ
define('DB_USER', '3Bfno6oj2JjfgJr.root'); 
define('DB_PASS', 'vEZsw0lKkIenD1cK'); 
define('DB_NAME', 'dormitory_db'); // <--- ใช้ชื่อนี้ เพราะตารางทั้งหมดอยู่ที่นี่ครับ
define('DB_PORT', 4000); 

define('SITE_URL', 'https://nongmeendormitory.onrender.com'); 
define('SITE_NAME', 'ระบบจัดการหอพัก');

// เริ่มต้นการเชื่อมต่อแบบปลอดภัย (SSL)
$conn = mysqli_init();
$conn->ssl_set(NULL, NULL, NULL, NULL, NULL);

if (!$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, NULL, MYSQLI_CLIENT_SSL)) {
    die('❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . mysqli_connect_error());
}

$conn->set_charset('utf8mb4');

// --- Helper Functions ---
function esc($str) {
    global $conn; return $conn->real_escape_string(trim($str));
}

function getSetting($key) {
    global $conn; $key = esc($key);
    $r = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$key'");
    return ($r && $r->num_rows > 0) ? $r->fetch_assoc()['setting_value'] : '';
}

if (session_status() === PHP_SESSION_NONE) session_start();

function requireLogin() {
    if (empty($_SESSION['ad_id'])) {
        header('Location: ' . SITE_URL . '/index.php'); exit;
    }
}
?>
