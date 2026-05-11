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
    --primary: #1a4f8a;
    --primary-light: #2d6cb5;
    --primary-dark: #0f3460;
    --accent: #e8a020;
    --bg: #f0f4f8;
    --card-bg: #ffffff;
    --text: #1a2332;
    --text-muted: #6b7a8d;
    --border: #d1dbe8;
    --danger: #c0392b;
    --danger-bg: #fdf0ef;
    --success: #1a6b3c;
    --success-bg: #edfaf3;
    --shadow: 0 8px 40px rgba(26,79,138,0.12);
    --radius: 14px;
  }

  body {
    font-family: 'Sarabun', sans-serif;
    background: var(--bg);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
  }

  /* Background pattern */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
      radial-gradient(ellipse 80% 60% at 20% 10%, rgba(26,79,138,0.08) 0%, transparent 60%),
      radial-gradient(ellipse 60% 80% at 80% 90%, rgba(232,160,32,0.07) 0%, transparent 60%);
    pointer-events: none;
  }

  .grid-bg {
    position: fixed;
    inset: 0;
    background-image:
      linear-gradient(rgba(26,79,138,0.04) 1px, transparent 1px),
      linear-gradient(90deg, rgba(26,79,138,0.04) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
  }

  /* Card */
  .card {
    background: var(--card-bg);
    border-radius: 20px;
    box-shadow: var(--shadow), 0 1px 0 rgba(255,255,255,0.8) inset;
    width: 100%;
    max-width: 420px;
    padding: 44px 40px 40px;
    position: relative;
    z-index: 10;
    animation: slideUp 0.45s cubic-bezier(0.22,0.61,0.36,1) both;
  }

  @keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* Top accent bar */
  .card::before {
    content: '';
    position: absolute;
    top: 0; left: 20px; right: 20px;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--accent));
    border-radius: 0 0 4px 4px;
  }

  /* Logo */
  .logo-wrap {
    text-align: center;
    margin-bottom: 32px;
  }

  .logo-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
    box-shadow: 0 4px 16px rgba(26,79,138,0.25);
  }

  .logo-icon svg {
    width: 32px;
    height: 32px;
    fill: none;
    stroke: #fff;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  .logo-title {
    font-family: 'Prompt', sans-serif;
    font-size: 22px;
    font-weight: 600;
    color: var(--primary-dark);
    line-height: 1.2;
  }

  .logo-sub {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 4px;
  }

  /* Alert */
  .alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 14px;
    margin-bottom: 20px;
    animation: fadeIn 0.3s ease;
  }

  @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

  .alert-danger {
    background: var(--danger-bg);
    color: var(--danger);
    border: 1px solid rgba(192,57,43,0.2);
  }

  .alert-success {
    background: var(--success-bg);
    color: var(--success);
    border: 1px solid rgba(26,107,60,0.2);
  }

  .alert svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }

  /* Form */
  .form-group { margin-bottom: 18px; }

  label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 7px;
    letter-spacing: 0.02em;
  }

  .input-wrap {
    position: relative;
  }

  .input-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    pointer-events: none;
  }

  .input-icon svg { width: 17px; height: 17px; stroke: currentColor; fill: none; stroke-width: 1.8; }

  input[type="email"],
  input[type="password"],
  input[type="text"] {
    width: 100%;
    height: 46px;
    padding: 0 14px 0 42px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    font-size: 15px;
    font-family: 'Sarabun', sans-serif;
    color: var(--text);
    background: #f8fafc;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    outline: none;
  }

  input:focus {
    border-color: var(--primary);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(26,79,138,0.1);
  }

  input.error-input { border-color: var(--danger); }

  /* Password toggle */
  .toggle-pass {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    padding: 4px;
    display: flex;
    align-items: center;
    transition: color 0.2s;
  }

  .toggle-pass:hover { color: var(--primary); }
  .toggle-pass svg { width: 17px; height: 17px; stroke: currentColor; fill: none; stroke-width: 1.8; }

  /* Submit button */
  .btn-submit {
    width: 100%;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-family: 'Sarabun', sans-serif;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 24px;
    transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
    box-shadow: 0 4px 14px rgba(26,79,138,0.3);
    letter-spacing: 0.02em;
  }

  .btn-submit:hover { opacity: 0.92; box-shadow: 0 6px 20px rgba(26,79,138,0.35); }
  .btn-submit:active { transform: scale(0.98); }
  .btn-submit svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; }

  .btn-submit.loading { opacity: 0.7; pointer-events: none; }
  .btn-submit.loading .btn-text { display: none; }
  .btn-submit .loading-text { display: none; }
  .btn-submit.loading .loading-text { display: flex; align-items: center; gap: 8px; }

  @keyframes spin { to { transform: rotate(360deg); } }
  .spinner {
    width: 18px; height: 18px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
  }

  /* Divider */
  .divider {
    text-align: center;
    margin: 24px 0 0;
    font-size: 12px;
    color: var(--text-muted);
  }

  /* DB error */
  .db-error {
    background: var(--danger-bg);
    border: 1px solid rgba(192,57,43,0.3);
    border-radius: 10px;
    padding: 14px 16px;
    font-size: 13px;
    color: var(--danger);
    margin-bottom: 16px;
  }
</style>
</head>
<body>
<div class="grid-bg"></div>

<div class="card">
  <div class="logo-wrap">
    <div class="logo-icon">
      <!-- building icon -->
      <svg viewBox="0 0 24 24">
        <rect x="3" y="5" width="18" height="16" rx="1"/>
        <path d="M3 9h18M9 9v12M15 9v12M7 5V3h10v2"/>
        <rect x="10.5" y="13" width="3" height="4" rx="0.5"/>
      </svg>
    </div>
    <div class="logo-title">ระบบจัดการหอพัก</div>
    <div class="logo-sub">สำหรับผู้ดูแลระบบเท่านั้น</div>
  </div>

  <?php if ($db_error): ?>
  <div class="db-error">
    ⚠️ <?= htmlspecialchars($db_error) ?> — กรุณาลองใหม่อีกครั้ง
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alert alert-danger">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <?php if ($success): ?>
  <div class="alert alert-success">
    <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    <?= htmlspecialchars($success) ?>
  </div>
  <?php endif; ?>

  <form method="POST" id="loginForm" autocomplete="off">
    <div class="form-group">
      <label for="email">อีเมล</label>
      <div class="input-wrap">
        <span class="input-icon">
          <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </span>
        <input
          type="email"
          id="email"
          name="email"
          placeholder="admin@dormitory.com"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          autocomplete="email"
          <?= $error ? 'class="error-input"' : '' ?>
          required
        >
      </div>
    </div>

    <div class="form-group">
      <label for="password">รหัสผ่าน</label>
      <div class="input-wrap">
        <span class="input-icon">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </span>
        <input
          type="password"
          id="password"
          name="password"
          placeholder="••••••••"
          autocomplete="current-password"
          <?= $error ? 'class="error-input"' : '' ?>
          required
        >
        <button type="button" class="toggle-pass" onclick="togglePass()" aria-label="แสดง/ซ่อนรหัสผ่าน">
          <svg id="eyeIcon" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <button type="submit" class="btn-submit" id="submitBtn">
      <span class="btn-text">
        <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        เข้าสู่ระบบ
      </span>
      <span class="loading-text">
        <div class="spinner"></div>
        กำลังเข้าสู่ระบบ...
      </span>
    </button>
  </form>

  <div class="divider">
    หอพักน้องมีน &nbsp;·&nbsp; <?= date('Y') ?>
  </div>
</div>

<script>
function togglePass() {
  const input = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
  } else {
    input.type = 'password';
    icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }
}

document.getElementById('loginForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.classList.add('loading');
});
</script>
</body>
</html>
