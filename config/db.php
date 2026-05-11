<?php
// =============================================
// config/db.php - การเชื่อมต่อฐานข้อมูล (TiDB Cloud)
// =============================================

// แก้ไขข้อมูลตามที่ปรากฏในหน้า TiDB Cloud ของคุณ
define('DB_HOST', 'gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com'); // ตรวจสอบ Endpoint อีกครั้งจากหน้าเว็บ TiDB
define('DB_USER', '2KwDfypAKj7WupU.root');
define('DB_PASS', 'jpLbbarlEO72Wqwe'); 
define('DB_NAME', 'dormitory_db');
define('DB_PORT', 4000); // TiDB ต้องใช้พอร์ต 4000

define('SITE_URL', 'https://nongmeendormitory.onrender.com'); // เมื่อขึ้น Render แล้วให้เปลี่ยนเป็น URL ของ Render ครับ
define('SITE_NAME', 'ระบบจัดการหอพัก');

// สร้างตัวแปรเชื่อมต่อ
$conn = mysqli_init();

// ตั้งค่าให้ใช้ SSL (TiDB Cloud บังคับ)
// ใน Docker/Render ปกติจะมีไฟล์ cert พื้นฐานอยู่แล้ว ไม่ต้องระบุ path ก็ได้ครับ
$conn->ssl_set(NULL, NULL, NULL, NULL, NULL);

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
