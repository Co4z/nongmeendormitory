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
$db_error = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');

    if (empty($email) || empty($pass)) {
        $error = 'กรุณากรอกอีเมลและรหัสผ่าน';
    } else {
        // ใช้ฟังก์ชัน esc() ที่เราสร้างไว้ใน config/db.php
        $safe_email = esc($email);
        $r = $conn->query("SELECT * FROM admin WHERE ad_email = '$safe_email' LIMIT 1");

        // -------------------------------------------------------------
        // เริ่มส่วน DEBUG (แอบดู Raw Data ตอนวิ่งเข้า Backend)
        // -------------------------------------------------------------
        echo "<div style='background:#fff; color:#000; padding:20px; z-index:9999; position:fixed; top:0; left:0; width:100%; border-bottom:4px solid red; font-family:sans-serif;'>";
        echo "<h3 style='color:red;'>🚨 Debug Mode 🚨</h3>";
        echo "<b>1. สิ่งที่กรอกมาจากหน้าเว็บ (Input):</b><br>";
        echo "- Email: [" . htmlspecialchars($email) . "] (ความยาว: " . strlen($email) . " ตัวอักษร)<br>";
        echo "- Password: [" . htmlspecialchars($pass) . "] (ความยาว: " . strlen($pass) . " ตัวอักษร)<br><br>";
        
        if (!$r) {
            echo "<b>2. SQL Query Error:</b> " . htmlspecialchars($conn->error) . "<br>";
        } else {
            echo "<b>2. ผลการค้นหาจาก DB:</b> เจอข้อมูล " . $r->num_rows . " แถว<br><br>";
            if ($r->num_rows > 0) {
                // เอาข้อมูลมาเก็บในตัวแปร $admin ชั่วคราวเพื่อปริ้นท์
                $admin = $r->fetch_assoc();
                echo "<b>3. ข้อมูลดิบใน DB (Raw Data):</b><br>";
                echo "- Email ใน DB: [" . htmlspecialchars($admin['ad_email']) . "] (ความยาว: " . strlen($admin['ad_email']) . " ตัวอักษร)<br>";
                echo "- Password ใน DB: [" . htmlspecialchars($admin['ad_password']) . "] (ความยาว: " . strlen($admin['ad_password']) . " ตัวอักษร)<br><br>";
                
                $pass_match = ($pass === $admin['ad_password']) ? "✅ ตรงกัน" : "❌ ไม่ตรงกัน";
                echo "<b>4. ผลการตรวจสอบตรรกะ:</b><br>";
                echo "- เทียบรหัสแบบข้อความธรรมดา: " . $pass_match . "<br>";
                echo "- เทียบรหัสแบบ Bcrypt: " . (password_verify($pass, $admin['ad_password']) ? "✅ ตรงกัน" : "❌ ไม่ตรงกัน") . "<br>";
            }
        }
        echo "</div>";
        exit;
        // -------------------------------------------------------------
        // จบส่วน DEBUG
        // -------------------------------------------------------------

        // โค้ดส่วนนี้จะทำงานหลังจากที่เราเอา exit; ด้านบนออก
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

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบ — ระบบจัดการหอพัก</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --primary: #1a4f8a; --primary-light: #2d6cb5; --primary-dark: #0f3460;
    --accent: #e8a020; --bg: #f0f4f8; --card-bg: #ffffff;
    --text: #1a2332; --text-muted: #6b7a8d; --border: #d1dbe8;
    --danger: #c0392b; --danger-bg: #fdf0ef; --shadow: 0 8px 40px rgba(26,79,138,0.12);
  }
  body { font-family: 'Sarabun', sans-serif; background: var(--bg); min-height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; }
  .card { background: var(--card-bg); border-radius: 20px; box-shadow: var(--shadow); width: 100%; max-width: 420px; padding: 40px; position: relative; z-index: 10; }
  .logo-wrap { text-align: center; margin-bottom: 32px; }
  .logo-title { font-family: 'Prompt', sans-serif; font-size: 22px; font-weight: 600; color: var(--primary-dark); }
  .alert { padding: 12px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(192,57,43,0.2); }
  .
