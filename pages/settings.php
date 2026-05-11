<?php
require_once '../config/db.php';
requireLogin();
$page_title = 'ตั้งค่าระบบ';

$msg = '';
if (isset($_POST['save_settings'])) {
    $fields = ['dorm_name','dorm_address','dorm_tel',
               'water_price_per_unit','electric_price_per_unit','bill_due_days',
               'google_sheet_id','google_sheet_tab','google_script_url'];
    foreach ($fields as $f) {
        $v = esc($_POST[$f] ?? '');
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$f','$v')
            ON DUPLICATE KEY UPDATE setting_value='$v'");
    }
    $msg = 'บันทึกการตั้งค่าสำเร็จ';
}

// Load all settings
$settings_all = [];
$r = $conn->query("SELECT * FROM settings");
while ($row = $r->fetch_assoc()) $settings_all[$row['setting_key']] = $row['setting_value'];
$s = function($k) use ($settings_all) { return htmlspecialchars($settings_all[$k] ?? ''); };

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check"></i> <?= $msg ?></div><?php endif; ?>

<form method="POST">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

<!-- Dorm Info -->
<div class="card">
    <div class="card-title"><i class="fa-solid fa-building"></i> ข้อมูลหอพัก</div>
    <div class="form-group">
        <label>ชื่อหอพัก</label>
        <input type="text" name="dorm_name" value="<?= $s('dorm_name') ?>" placeholder="ชื่อหอพักของคุณ">
    </div>
    <div class="form-group">
        <label>ที่อยู่</label>
        <textarea name="dorm_address" rows="3" placeholder="ที่อยู่หอพัก"><?= $s('dorm_address') ?></textarea>
    </div>
    <div class="form-group">
        <label>เบอร์ติดต่อ</label>
        <input type="tel" name="dorm_tel" value="<?= $s('dorm_tel') ?>">
    </div>
</div>

<!-- Rate Settings -->
<div class="card">
    <div class="card-title"><i class="fa-solid fa-sliders"></i> อัตราค่าน้ำ-ไฟ</div>
    <div class="form-group">
        <label>ราคาน้ำต่อหน่วย (บาท)</label>
        <input type="number" name="water_price_per_unit" value="<?= $s('water_price_per_unit') ?>" step="0.01">
    </div>
    <div class="form-group">
        <label>ราคาไฟต่อหน่วย (บาท)</label>
        <input type="number" name="electric_price_per_unit" value="<?= $s('electric_price_per_unit') ?>" step="0.01">
    </div>
    <div class="form-group">
        <label>จำนวนวันครบกำหนดชำระ (หลังออกบิล)</label>
        <input type="number" name="bill_due_days" value="<?= $s('bill_due_days') ?: 7 ?>" min="1">
    </div>
</div>

<!-- Google Sheets -->
<div class="card" style="grid-column:1/-1;">
    <div class="card-title"><i class="fa-brands fa-google"></i> เชื่อมต่อ Google Sheets</div>
    
    <div class="alert alert-info">
        <i class="fa-solid fa-circle-info"></i>
        <div>
            <strong>วิธีตั้งค่า Google Sheets:</strong><br>
            1. เปิด Google Sheet ใหม่ → ตั้งชื่อ Tab ที่ต้องการ (เช่น "Payments")<br>
            2. ไปที่ Extensions → Apps Script → วางโค้ดจาก <code>google_script.js</code><br>
            3. Deploy → New Deployment → Web App → Execute as: Me, Access: Anyone<br>
            4. คัดลอก Web App URL มาวางในช่องด้านล่าง
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Google Sheet ID</label>
            <input type="text" name="google_sheet_id" value="<?= $s('google_sheet_id') ?>"
                   placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms">
            <small class="text-muted">ดูได้จาก URL: docs.google.com/spreadsheets/d/<strong>[ID นี้]</strong>/edit</small>
        </div>
        <div class="form-group">
            <label>ชื่อ Tab ใน Google Sheet</label>
            <input type="text" name="google_sheet_tab" value="<?= $s('google_sheet_tab') ?: 'Payments' ?>">
        </div>
    </div>
    <div class="form-group">
        <label>Google Apps Script Web App URL</label>
        <input type="url" name="google_script_url" value="<?= $s('google_script_url') ?>"
               placeholder="https://script.google.com/macros/s/AKfycb.../exec">
    </div>

    <?php if ($s('google_script_url')): ?>
    <div class="alert alert-success">
        <i class="fa-solid fa-check-circle"></i>
        Google Sheets เชื่อมต่อแล้ว — การชำระเงินใหม่จะถูกบันทึกอัตโนมัติ
    </div>
    <?php endif; ?>
</div>

</div><!-- end grid -->

<!-- Change Password -->
<div class="card">
    <div class="card-title"><i class="fa-solid fa-key"></i> เปลี่ยนรหัสผ่าน Admin</div>
    <div class="form-row">
        <div class="form-group">
            <label>รหัสผ่านใหม่</label>
            <input type="password" name="new_password" placeholder="••••••••">
        </div>
        <div class="form-group">
            <label>ยืนยันรหัสผ่านใหม่</label>
            <input type="password" name="confirm_password" placeholder="••••••••">
        </div>
    </div>
    <small class="text-muted">ปล่อยว่างไว้ถ้าไม่ต้องการเปลี่ยนรหัสผ่าน</small>
</div>

<div style="text-align:right;">
    <button type="submit" name="save_settings" class="btn btn-primary btn-lg">
        <i class="fa-solid fa-floppy-disk"></i> บันทึกการตั้งค่า
    </button>
</div>
</form>

<?php include '../includes/footer.php'; ?>
