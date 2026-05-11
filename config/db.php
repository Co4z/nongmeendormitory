<?php
// =============================================
// config/db.php - การเชื่อมต่อฐานข้อมูล (TiDB Cloud)
// =============================================

// แก้ไขข้อมูลตามที่ปรากฏในหน้า TiDB Cloud ของคุณ
define('DB_HOST', 'gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com');
// ⚠️ อย่าลืมก๊อปปี้ชื่อ User ที่มีรหัสคลัสเตอร์นำหน้าจากหน้า Connect ใน TiDB มาใส่ตรงนี้ครับ
define('DB_USER', '2KwDfypAKj7WupU.root'); 
define('DB_PASS', 'jpLbbarlEO72Wqwe'); 
define('DB_NAME', 'dormitory_db'); // <--- ใช้ชื่อนี้ตามที่เห็นใน Schema ครับ
define('DB_PORT', 4000); 

define('SITE_URL', 'https://nongmeendormitory.onrender.com'); 
define('SITE_NAME', 'ระบบจัดการหอพัก');

// เชื่อมต่อแบบ SSL (บังคับสำหรับ TiDB Cloud Serverless)
$conn = mysqli_init();
$conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
$options = $conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, NULL, MYSQLI_CLIENT_SSL);

if (!$options) {
    die('❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . mysqli_connect_error());
}

$conn->set_charset('utf8mb4');

// เชื่อมต่อโดยระบุ MYSQLI_CLIENT_SSL
$options = $conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, NULL, MYSQLI_CLIENT_SSL);

if (!$options) {
    die('<div style="padding:20px;background:#fee;border:1px solid #f00;font-family:sans-serif;">
        ❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . mysqli_connect_error() . '
        <br><small>กรุณาตรวจสอบการตั้งค่า SSL ใน config/db.php</small>
    </div>');
}

$conn->set_charset('utf8mb4');

// Helper: escape string
function esc($str) {
    global $conn;
    return $conn->real_escape_string(trim($str));
}

// Helper: get setting
function getSetting($key) {
    global $conn;
    $key = esc($key);
    $r = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$key'");
    if ($r && $r->num_rows > 0) return $r->fetch_assoc()['setting_value'];
    return '';
}

// Session
if (session_status() === PHP_SESSION_NONE) session_start();

// Auth check
function requireLogin() {
    if (empty($_SESSION['ad_id'])) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}
?>
