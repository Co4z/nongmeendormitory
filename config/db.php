<?php
// =============================================
// config/db.php - Debug Version
// =============================================

define('DB_HOST', 'gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com');
define('DB_USER', '2KwDfypAKj7WupU.root'); 
define('DB_PASS', 'jpLbbarlEO72Wqwe'); 
define('DB_NAME', 'nongmeendormitory'); 
define('DB_PORT', 4000); 

define('SITE_URL', 'https://nongmeendormitory.onrender.com'); 
define('SITE_NAME', 'ระบบจัดการหอพัก');

// เริ่มต้นการเชื่อมต่อ
$conn = mysqli_init();
$conn->ssl_set(NULL, NULL, NULL, NULL, NULL);

try {
    // 1. เชื่อมต่อ Server
    $conn->real_connect(DB_HOST, DB_USER, DB_PASS, null, DB_PORT, NULL, MYSQLI_CLIENT_SSL);
    
    // 2. พยายามเลือก Database
    if (!$conn->select_db(DB_NAME)) {
        throw new Exception("Database not found");
    }

} catch (Exception $e) {
    // ถ้าหาไม่เจอ ให้ดึงรายชื่อ DB ทั้งหมดออกมาดู
    $res = $conn->query("SHOW DATABASES");
    $dbs = [];
    if ($res) {
        while($row = $res->fetch_array()) $dbs[] = $row[0];
    }
    
    die('<div style="padding:20px;background:#fee;border:1px solid #f00;font-family:sans-serif;line-height:1.6;">
        ❌ <b>เกิดข้อผิดพลาด:</b> ' . $e->getMessage() . '<br>
        พยายามเข้าหาชื่อ: <code style="background:#fff;padding:2px 5px;">' . DB_NAME . '</code><br><br>
        รายชื่อฐานข้อมูลที่มีอยู่ใน TiDB ของคุณขณะนี้คือ:<br>
        <ul style="margin:10px 0;"><li>' . implode('</li><li>', $dbs) . '</li></ul>
        <b>วิธีแก้:</b> ก๊อปปี้ชื่อจากรายการด้านบน (เอาอันที่มีตารางงานของคุณอยู่) ไปเปลี่ยนในตัวแปร <code style="background:#fff;padding:2px 5px;">DB_NAME</code> บน GitHub ครับ
    </div>');
}

$conn->set_charset('utf8mb4');

// --- ส่วน Helper คงเดิม ---
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
