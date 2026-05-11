<?php
// =============================================
// config/db.php - การเชื่อมต่อฐานข้อมูล
// =============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root'); 
define('DB_PASS', 'admin1234');    // <--- ลองใส่รหัสที่คุณตั้งตอนติดตั้ง ถ้าจำไม่ได้ลอง '1234' หรือ ''
define('DB_NAME', 'dormitory');  // <--- แก้ให้ตรงกับชื่อที่สร้างใน phpMyAdmin

define('SITE_URL', 'http://localhost:8080/dormitory'); // <--- เติม :8080 ลงไป
define('SITE_NAME', 'ระบบจัดการหอพัก');

// เชื่อมต่อ MySQL
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die('<div style="padding:20px;background:#fee;border:1px solid #f00;font-family:sans-serif;">
        ❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . $conn->connect_error . '
        <br><small>กรุณาตรวจสอบ config/db.php และตรวจสอบว่า AppServ กำลังทำงาน</small>
    </div>');
}

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
