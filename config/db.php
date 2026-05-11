<?php
// =============================================
// config/db.php - การเชื่อมต่อฐานข้อมูล (TiDB Cloud)
// =============================================

define('DB_HOST', 'gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com');
define('DB_USER', '2KwDfypAKj7WupU.root'); 
define('DB_PASS', 'jpLbbarlEO72Wqwe'); 
define('DB_NAME', 'dormitory_db'); 
define('DB_PORT', 4000); 

define('SITE_URL', 'https://nongmeendormitory.onrender.com'); 
define('SITE_NAME', 'ระบบจัดการหอพัก');

// เชื่อมต่อแบบ SSL
$conn = mysqli_init();
$conn->ssl_set(NULL, NULL, NULL, NULL, NULL);

// เชื่อมต่อเข้า Server ก่อน (ยังไม่ระบุ DB เพื่อเช็คว่าต่อติดไหม)
if (!$conn->real_connect(DB_HOST, DB_USER, DB_PASS, null, DB_PORT, NULL, MYSQLI_CLIENT_SSL)) {
    die('❌ เชื่อมต่อ Server ไม่ได้: ' . mysqli_connect_error());
}

// พยายามเลือกฐานข้อมูล
if (!$conn->select_db(DB_NAME)) {
    // ถ้าหาไม่เจอ ให้ดึงรายชื่อ DB ทั้งหมดมาโชว์เพื่อดูว่าเราพิมพ์ชื่อไหนผิด
    $res = $conn->query("SHOW DATABASES");
    $dbs = [];
    while($row = $res->fetch_array()) $dbs[] = $row[0];
    
    die('<div style="padding:20px;background:#fee;border:1px solid #f00;font-family:sans-serif;">
        ❌ หาฐานข้อมูล "' . DB_NAME . '" ไม่เจอ!<br>
        ใน TiDB ของคุณมีแค่ฐานข้อมูลชื่อ: <b>' . implode(', ', $dbs) . '</b><br>
        <small>กรุณาแก้ DB_NAME ใน db.php ให้ตรงกับชื่อด้านบน (เช็คตัวเล็ก/ใหญ่ และช่องว่างให้ดีครับ)</small>
    </div>');
}

$conn->set_charset('utf8mb4');

// Helper functions และส่วนอื่นๆ คงเดิม...
function esc($str) {
    global $conn;
    return $conn->real_escape_string(trim($str));
}

function getSetting($key) {
    global $conn;
    $key = esc($key);
    $r = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$key'");
    if ($r && $r->num_rows > 0) return $r->fetch_assoc()['setting_value'];
    return '';
}

if (session_status() === PHP_SESSION_NONE) session_start();

function requireLogin() {
    if (empty($_SESSION['ad_id'])) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}
?>
