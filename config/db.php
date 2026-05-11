<?php
// --- บรรทัดที่ 1 ต้องเป็น session_start(); เพื่อให้ระบบจำการ Login ได้ ---
session_start(); 

// ตั้งค่าฐานข้อมูล TiDB Cloud
define('DB_HOST', 'gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com');
define('DB_USER', '3Bfno6oj2JjfgJr.root'); 
define('DB_PASS', 'vEZsw0lKkIenD1cK'); 
define('DB_NAME', 'dormitory_db'); 
define('DB_PORT', 4000); 

// ตั้งค่า URL ของเว็บไซต์ (Render)
define('SITE_URL', 'https://nongmeendormitory.onrender.com'); 
define('SITE_NAME', 'ระบบจัดการหอพัก');

// เชื่อมต่อ SSL สำหรับ TiDB
$conn = mysqli_init();
$conn->ssl_set(NULL, NULL, NULL, NULL, NULL);

if (!$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, NULL, MYSQLI_CLIENT_SSL)) {
    die('❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . mysqli_connect_error());
}

// ตั้งค่าภาษาไทย
$conn->set_charset("utf8mb4");

// ฟังก์ชันล้างอักขระพิเศษ (ป้องกัน SQL Injection)
if (!function_exists('esc')) {
    function esc($str) {
        global $conn;
        return mysqli_real_escape_string($conn, trim($str));
    }
}

// ฟังก์ชันดึงค่าตั้งค่าหอพัก
if (!function_exists('getSetting')) {
    function getSetting($key) {
        global $conn;
        $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '$key' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            return $res->fetch_assoc()['setting_value'];
        }
        return '';
    }
}
?>
