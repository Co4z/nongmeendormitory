<?php
// เรียกใช้ไฟล์ config (ซึ่งในนั้นมี session_start และเชื่อมต่อ DB ไว้แล้ว)
require_once 'config/db.php';

// ถ้า Login อยู่แล้วให้ redirect
if (!empty($_SESSION['ad_id'])) {
    header('Location: ' . SITE_URL . '/pages/dashboard.php');
    exit;
}

$error = '';
$success = '';
$db_error = ''; // เติมบรรทัดนี้เพื่อแก้ Warning

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (empty($email) || empty($pass)) {
        $error = 'กรุณากรอกอีเมลและรหัสผ่าน';
    } else {
        // ใช้ฟังก์ชัน esc() ที่เราสร้างไว้ใน config/db.php ได้เลย
        $safe_email = esc($email);
        $r = $conn->query("SELECT * FROM admin WHERE ad_email = '$safe_email' LIMIT 1");

        if ($r && $r->num_rows > 0) {
            $admin = $r->fetch_assoc();
            $stored_pass = $admin['ad_password'];

            // รองรับทั้ง bcrypt และ plain text
            $verified = false;
            if (password_verify($pass, $stored_pass)) {
                $verified = true;
            } elseif ($pass === $stored_pass) {
                // plain text fallback — อัปเดตเป็น hash ทันที
                $new_hash = password_hash($pass, PASSWORD_BCRYPT);
                $safe_hash = esc($new_hash);
                $conn->query("UPDATE admin SET ad_password = '$safe_hash' WHERE ad_email = '$safe_email'");
                $verified = true;
            }

            if ($verified) {
                session_regenerate_id(true);
                $_SESSION['ad_id']    = $admin['ad_id'];
                $_SESSION['ad_name']  = $admin['ad_name'] . ' ' . $admin['ad_lastname'];
                $_SESSION['ad_email'] = $admin['ad_email'];
                
                header('Location: ' . SITE_URL . '/pages/dashboard.php');
                exit;
            }
        }
        $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
