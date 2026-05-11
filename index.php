<?php
require_once 'config/db.php';

// ถ้า Login อยู่แล้ว ให้ข้ามไปหน้า Dashboard ทันที
if (!empty($_SESSION['ad_id'])) {
    header('Location: ' . SITE_URL . '/pages/dashboard.php');
    exit;
}
echo password_hash('admin1234', PASSWORD_DEFAULT);
exit;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = esc($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    // ตรวจสอบ Email ในฐานข้อมูล
    $r = $conn->query("SELECT * FROM admin WHERE ad_email='$email' LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $admin = $r->fetch_assoc();
        // ตรวจสอบรหัสผ่านที่เข้ารหัส (Bcrypt)
        if (password_verify($pass, $admin['ad_password'])) {
            $_SESSION['ad_id']   = $admin['ad_id'];
            $_SESSION['ad_name'] = $admin['ad_name'] . ' ' . $admin['ad_lastname'];
            
            header('Location: ' . SITE_URL . '/pages/dashboard.php');
            exit;
        }
    }
    $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="login-page">
<div class="login-box">
    <div class="login-logo">
        <div class="icon"><i class="fa-solid fa-building"></i></div>
        <h1><?= getSetting('dorm_name') ?: 'ระบบจัดการหอพัก' ?></h1>
        <p>เข้าสู่ระบบสำหรับผู้ดูแล</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label class="required">อีเมล</label>
            <input type="email" name="email" placeholder="admin@dormitory.com" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label class="required">รหัสผ่าน</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:8px;">
            <i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบ
        </button>
    </form>

    <p style="text-align:center;margin-top:20px;font-size:12px;color:#aaa;">
        รหัสเริ่มต้น: admin@dormitory.com / admin1234
    </p>
</div>
</body>
</html>
