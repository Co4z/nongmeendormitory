<?php
// pages/pay.php - หน้าสำหรับลูกค้าจ่ายเงิน (ไม่ต้อง login)
require_once '../config/db.php';

$bill_id = (int)($_GET['bill'] ?? 0);
$msg = '';

if (!$bill_id) {
    die('<div style="font-family:sans-serif;text-align:center;padding:60px;">❌ ไม่พบบิลที่ระบุครับ</div>');
}

// ดึงข้อมูลบิล
$bill = $conn->query("
    SELECT b.*, bl.*, c.name, c.lastname, c.tel,
           rn.id_room, rt.name_roomtype
    FROM bill b
    LEFT JOIN bill_list bl ON b.id_listbill = bl.id_listbill
    JOIN customer c ON b.id_people = c.id_people
    JOIN contract ct ON b.contract_id = ct.contract_id
    JOIN room_number rn ON ct.id_room = rn.id_room
    JOIN room_type rt ON rn.id_roomtype = rt.id_roomtype
    WHERE b.id_listbill = $bill_id
")->fetch_assoc();

if (!$bill) {
    die('<div style="font-family:sans-serif;text-align:center;padding:60px;">❌ ไม่พบบิลที่ระบุครับ</div>');
}

$dorm_name   = getSetting('dorm_name') ?: 'หอพัก';
$promptpay   = '0925455200'; // เบอร์ PromptPay
$total       = $bill['total_amount'] ?: $bill['priceroom'];
$already_paid = $bill['status'] === 'paid';

// รับสลิป
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_paid) {
    $slip_path = '';
    if (!empty($_FILES['slip']['name'])) {
        $upload_dir = '../uploads/slips/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext   = pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION);
        $fname = 'slip_' . $bill_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['slip']['tmp_name'], $upload_dir . $fname)) {
            $slip_path = $fname;
        }
    }

    $note = esc($_POST['note'] ?? '');

    // บันทึกสลิปรอตรวจสอบ (status = pending_verify)
    $conn->query("INSERT INTO payment 
        (id_listbill, paid_amount, paid_date, payment_method, slip_img, note, ad_id, google_sheet_synced)
        VALUES ($bill_id, $total, NOW(), 'qr', '$slip_path', '$note แนบสลิปรอตรวจสอบ', 1, 0)");

    // อัปเดต bill เป็น pending_verify (ใช้ status paid ชั่วคราว รอ admin ยืนยัน)
    $conn->query("UPDATE bill SET status='paid' WHERE id_listbill=$bill_id");

    $msg = 'success';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ชำระค่าเช่า - <?= $dorm_name ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Sarabun', sans-serif;
    background: linear-gradient(135deg, #1a5276 0%, #2980b9 50%, #1abc9c 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.card {
    background: #fff;
    border-radius: 24px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.25);
    overflow: hidden;
}

.card-header {
    background: linear-gradient(135deg, #1a5276, #2980b9);
    color: #fff;
    padding: 28px 28px 24px;
    text-align: center;
}

.dorm-name { font-size: 13px; opacity: 0.8; margin-bottom: 4px; }
.bill-title { font-size: 22px; font-weight: 800; }
.bill-sub   { font-size: 13px; opacity: 0.75; margin-top: 4px; }

.card-body { padding: 28px; }

/* Room Info */
.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
}
.info-row:last-child { border-bottom: none; }
.info-label { color: #888; }
.info-value { font-weight: 600; color: #222; }

/* Amount */
.amount-box {
    background: linear-gradient(135deg, #1a5276, #2980b9);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    color: #fff;
    margin: 20px 0;
}
.amount-label  { font-size: 13px; opacity: 0.8; }
.amount-number { font-size: 40px; font-weight: 800; line-height: 1.1; }
.amount-unit   { font-size: 14px; opacity: 0.8; }

/* QR */
.qr-section { text-align: center; margin: 20px 0; }
.qr-label { font-size: 13px; color: #888; margin-bottom: 12px; }
.qr-img {
    width: 200px; height: 200px;
    border: 3px solid #1a5276;
    border-radius: 16px;
    padding: 8px;
    background: #fff;
}
.promptpay-num {
    margin-top: 10px;
    font-size: 18px;
    font-weight: 700;
    color: #1a5276;
    letter-spacing: 2px;
}
.promptpay-hint { font-size: 12px; color: #aaa; margin-top: 4px; }

/* Divider */
.divider {
    display: flex; align-items: center; gap: 12px;
    margin: 20px 0; color: #bbb; font-size: 13px;
}
.divider::before, .divider::after {
    content: ''; flex: 1; height: 1px; background: #eee;
}

/* Upload */
.upload-box {
    border: 2px dashed #ddd;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}
.upload-box:hover { border-color: #2980b9; background: #f0f7ff; }
.upload-box input { 
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%;
}
.upload-icon { font-size: 32px; margin-bottom: 8px; }
.upload-text { font-size: 14px; color: #555; }
.upload-hint { font-size: 12px; color: #aaa; margin-top: 4px; }

#preview-img {
    width: 100%; border-radius: 10px;
    margin-top: 12px; display: none;
    max-height: 200px; object-fit: contain;
}

/* Note */
textarea {
    width: 100%; padding: 12px;
    border: 2px solid #eee; border-radius: 10px;
    font-family: inherit; font-size: 14px;
    resize: none; outline: none;
    transition: border-color 0.2s;
    margin-top: 12px;
}
textarea:focus { border-color: #2980b9; }

/* Button */
.btn-pay {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 17px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    margin-top: 16px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-pay:hover { filter: brightness(1.05); transform: translateY(-1px); }
.btn-pay:disabled { background: #ccc; cursor: not-allowed; transform: none; }

/* Success */
.success-box {
    text-align: center;
    padding: 20px 0;
}
.success-icon { font-size: 64px; margin-bottom: 16px; }
.success-title { font-size: 22px; font-weight: 800; color: #27ae60; }
.success-text  { font-size: 14px; color: #666; margin-top: 8px; line-height: 1.6; }

/* Paid badge */
.paid-badge {
    background: #eafaf1;
    border: 2px solid #27ae60;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    color: #27ae60;
    font-weight: 700;
    font-size: 16px;
    margin: 16px 0;
}
</style>
</head>
<body>

<div class="card">
    <div class="card-header">
        <div class="dorm-name"><?= $dorm_name ?></div>
        <div class="bill-title">ใบแจ้งหนี้ค่าเช่า</div>
        <div class="bill-sub">เลขที่ BL-<?= str_pad($bill_id, 5, '0', STR_PAD_LEFT) ?> | <?= date('M Y', strtotime($bill['billing_month'])) ?></div>
    </div>

    <div class="card-body">

        <?php if ($msg === 'success'): ?>
        <!-- Success State -->
        <div class="success-box">
            <div class="success-icon">✅</div>
            <div class="success-title">ส่งสลิปสำเร็จ!</div>
            <div class="success-text">
                เจ้าหน้าที่จะตรวจสอบและยืนยันการชำระเงินของคุณ<br>
                ภายใน 24 ชั่วโมง ขอบคุณครับ 🙏
            </div>
        </div>

        <?php elseif ($already_paid): ?>
        <!-- Already Paid -->
        <div class="info-row"><span class="info-label">ห้อง</span><span class="info-value">ห้อง <?= $bill['id_room'] ?></span></div>
        <div class="info-row"><span class="info-label">ผู้เช่า</span><span class="info-value"><?= $bill['name'].' '.$bill['lastname'] ?></span></div>
        <div class="info-row"><span class="info-label">เดือน</span><span class="info-value"><?= date('F Y', strtotime($bill['billing_month'])) ?></span></div>
        <div class="paid-badge">✅ ชำระเงินแล้ว ขอบคุณครับ!</div>

        <?php else: ?>
        <!-- Payment Form -->

        <!-- Bill Info -->
        <div class="info-row"><span class="info-label">ห้อง</span><span class="info-value">ห้อง <?= $bill['id_room'] ?> (<?= $bill['name_roomtype'] ?>)</span></div>
        <div class="info-row"><span class="info-label">ผู้เช่า</span><span class="info-value"><?= $bill['name'].' '.$bill['lastname'] ?></span></div>
        <div class="info-row"><span class="info-label">เดือน</span><span class="info-value"><?= date('F Y', strtotime($bill['billing_month'])) ?></span></div>
        <div class="info-row"><span class="info-label">ค่าเช่า</span><span class="info-value"><?= number_format($bill['priceroom'], 0) ?> ฿</span></div>
        <?php if ($bill['water_amount'] > 0): ?>
        <div class="info-row"><span class="info-label">ค่าน้ำ (<?= $bill['water_used'] ?> หน่วย)</span><span class="info-value"><?= number_format($bill['water_amount'], 0) ?> ฿</span></div>
        <?php endif; ?>
        <?php if ($bill['electric_amount'] > 0): ?>
        <div class="info-row"><span class="info-label">ค่าไฟ (<?= $bill['electric_used'] ?> หน่วย)</span><span class="info-value"><?= number_format($bill['electric_amount'], 0) ?> ฿</span></div>
        <?php endif; ?>

        <!-- Total -->
        <div class="amount-box">
            <div class="amount-label">ยอดที่ต้องชำระ</div>
            <div class="amount-number"><?= number_format($total, 0) ?></div>
            <div class="amount-unit">บาท</div>
        </div>

        <!-- QR Code -->
        <div class="qr-section">
            <div class="qr-label">สแกน QR PromptPay เพื่อชำระเงิน</div>
            <img class="qr-img"
                 src="https://promptpay.io/<?= $promptpay ?>/<?= $total ?>.png"
                 alt="QR PromptPay"
                 onerror="this.src='https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=PromptPay:<?= $promptpay ?>:<?= $total ?>'">
            <div class="promptpay-num"><?= $promptpay ?></div>
            <div class="promptpay-hint">PromptPay / โอนตามยอดที่แสดง</div>
        </div>

        <div class="divider">จากนั้นแนบสลิปด้านล่าง</div>

        <!-- Upload Slip -->
        <form method="POST" enctype="multipart/form-data" id="payForm">
            <div class="upload-box" id="uploadBox">
                <input type="file" name="slip" accept="image/*" id="slipInput" onchange="previewSlip(this)" required>
                <div class="upload-icon">📎</div>
                <div class="upload-text">แตะเพื่อแนบสลิปโอนเงิน</div>
                <div class="upload-hint">รองรับ JPG, PNG</div>
                <img id="preview-img" src="" alt="preview">
            </div>

            <textarea name="note" rows="2" placeholder="หมายเหตุ (ถ้ามี) เช่น โอนเมื่อ 10:30 น."></textarea>

            <button type="submit" class="btn-pay" id="btnPay">
                ✅ ส่งสลิปยืนยันการชำระเงิน
            </button>
        </form>

        <p style="text-align:center;font-size:12px;color:#aaa;margin-top:16px;">
            กำหนดชำระ: <?= date('d/m/Y', strtotime($bill['due_date'])) ?><br>
            สอบถาม: <?= getSetting('dorm_tel') ?>
        </p>

        <?php endif; ?>

    </div>
</div>

<script>
function previewSlip(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.getElementById('preview-img');
            img.src = e.target.result;
            img.style.display = 'block';
            document.querySelector('.upload-icon').textContent = '✅';
            document.querySelector('.upload-text').textContent = file.name;
        };
        reader.readAsDataURL(file);
    }
}

document.getElementById('payForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('btnPay');
    btn.disabled = true;
    btn.textContent = '⏳ กำลังส่ง...';
});
</script>

</body>
</html>