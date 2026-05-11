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
    $pass  = trim($_POST['password'] ?? '');

    if (empty($email) || empty($pass)) {
        $error = 'กรุณากรอกอีเมลและรหัสผ่าน';
    } else {
        // ใช้ฟังก์ชัน esc() ที่เราสร้างไว้ใน config/db.php
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
  .form-group { margin-bottom: 18px; }
  label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 7px; }
  input { width: 100%; height: 46px; padding: 0 15px; border: 1.5px solid var(--border); border-radius: 10px; outline: none; }
  input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,79,138,0.1); }
  .btn-submit { width: 100%; height: 48px; background: var(--primary); color: #fff; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; margin-top: 20px; }
</style>
</head>
<body>
<div class="card">
  <div class="logo-wrap">
    <div class="logo-title">ระบบจัดการหอพัก</div>
    <div class="logo-sub">สำหรับผู้ดูแลระบบเท่านั้น</div>
  </div>

  <?php if ($error): ?>
  <div class="alert"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <div class="form-group">
      <label>อีเมล</label>
      <input type="email" name="email" placeholder="admin@dormitory.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>รหัสผ่าน</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn-submit">เข้าสู่ระบบ</button>
  </form>
  <div style="text-align:center; margin-top:20px; font-size:12px; color:#aaa;">หอพักน้องมีน · 2026</div>
</div>
</body>
</html>
