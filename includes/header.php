<?php
// includes/header.php
$current_page = basename($_SERVER['PHP_SELF']);
$dorm_name = getSetting('dorm_name') ?: 'ระบบจัดการหอพัก';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?? $dorm_name ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-icon"><i class="fa-solid fa-building"></i></div>
        <div class="logo-text">
            <span class="logo-name"><?= $dorm_name ?></span>
            <span class="logo-sub">Admin Panel</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-title">หลัก</div>
        <a href="<?= SITE_URL ?>/pages/dashboard.php" class="nav-item <?= $current_page=='dashboard.php'?'active':'' ?>">
            <i class="fa-solid fa-chart-line"></i><span>ภาพรวม</span>
        </a>
        <a href="<?= SITE_URL ?>/pages/rooms.php" class="nav-item <?= $current_page=='rooms.php'?'active':'' ?>">
            <i class="fa-solid fa-door-open"></i><span>ห้องพัก</span>
        </a>
        <a href="<?= SITE_URL ?>/pages/customers.php" class="nav-item <?= $current_page=='customers.php'?'active':'' ?>">
            <i class="fa-solid fa-users"></i><span>ผู้เช่า</span>
        </a>
        <a href="<?= SITE_URL ?>/pages/contracts.php" class="nav-item <?= $current_page=='contracts.php'?'active':'' ?>">
            <i class="fa-solid fa-file-contract"></i><span>สัญญาเช่า</span>
        </a>

        <div class="nav-section-title">การเงิน</div>
        <a href="<?= SITE_URL ?>/pages/bills.php" class="nav-item <?= $current_page=='bills.php'?'active':'' ?>">
            <i class="fa-solid fa-file-invoice-dollar"></i><span>ใบแจ้งหนี้</span>
        </a>
        <a href="<?= SITE_URL ?>/pages/payments.php" class="nav-item <?= $current_page=='payments.php'?'active':'' ?>">
            <i class="fa-solid fa-qrcode"></i><span>บันทึกชำระเงิน</span>
        </a>
        <a href="<?= SITE_URL ?>/pages/overdue.php" class="nav-item <?= $current_page=='overdue.php'?'active':'' ?>">
            <i class="fa-solid fa-triangle-exclamation"></i><span>ค้างชำระ</span>
            <?php
            $od = $conn->query("SELECT COUNT(*) as c FROM bill WHERE status='overdue' OR (status='pending' AND due_date < CURDATE())");
            $odc = $od->fetch_assoc()['c'];
            if ($odc > 0) echo "<span class='badge-red'>$odc</span>";
            ?>
        </a>

        <div class="nav-section-title">ตั้งค่า</div>
        <a href="<?= SITE_URL ?>/pages/settings.php" class="nav-item <?= $current_page=='settings.php'?'active':'' ?>">
            <i class="fa-solid fa-gear"></i><span>ตั้งค่าระบบ</span>
        </a>
        <a href="<?= SITE_URL ?>/logout.php" class="nav-item nav-logout">
            <i class="fa-solid fa-right-from-bracket"></i><span>ออกจากระบบ</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-info">
            <i class="fa-solid fa-user-shield"></i>
            <span><?= $_SESSION['ad_name'] ?? 'Admin' ?></span>
        </div>
    </div>
</div>

<!-- Mobile overlay -->
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<!-- Main Content -->
<div class="main-content">
    <div class="topbar">
        <button class="menu-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
        <h1 class="page-title"><?= $page_title ?? '' ?></h1>
        <div class="topbar-right">
            <span class="date-display"><?= date('d/m/Y') ?></span>
        </div>
    </div>
    <div class="content-body">
