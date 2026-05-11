<?php
require_once '../config/db.php';
requireLogin();

$bill_id = (int)($_GET['id'] ?? 0);

$bill = $conn->query("
    SELECT b.*, bl.*, c.name, c.lastname, c.tel, c.id_people,
           rn.id_room, rt.name_roomtype,
           ct.contract_id, ct.water_unit_price, ct.electric_unit_price
    FROM bill b
    LEFT JOIN bill_list bl ON b.id_listbill = bl.id_listbill
    JOIN customer c ON b.id_people = c.id_people
    JOIN contract ct ON b.contract_id = ct.contract_id
    JOIN room_number rn ON ct.id_room = rn.id_room
    JOIN room_type rt ON rn.id_roomtype = rt.id_roomtype
    WHERE b.id_listbill=$bill_id
")->fetch_assoc();

if (!$bill) die('ไม่พบบิล');

$dorm_name    = getSetting('dorm_name');
$dorm_address = getSetting('dorm_address');
$dorm_tel     = getSetting('dorm_tel');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ใบแจ้งหนี้ #<?= $bill_id ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Sarabun', sans-serif; background: #f0f3f7; }

.top-bar {
    background: #1a5276; padding: 12px 20px;
    display: flex; gap: 10px; align-items: center;
}
.top-bar button {
    padding: 8px 20px; border: none; border-radius: 6px;
    font-family: inherit; font-size: 14px; cursor: pointer; font-weight: 600;
}
.btn-a4 { background: #fff; color: #1a5276; }
.btn-a6 { background: #f39c12; color: #fff; }
.top-bar span { color: rgba(255,255,255,0.7); font-size: 13px; }

/* A4 */
.page-a4 {
    max-width: 750px; margin: 24px auto; padding: 40px;
    background: #fff; border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.1);
}

/* A6 */
.page-a6 {
    width: 105mm; margin: 24px auto; padding: 8mm;
    background: #fff; border-radius: 8px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.1);
    font-size: 11px; display: none;
}

.header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.dorm-name { font-size: 16px; font-weight: 800; color: #1a5276; }
.dorm-name.sm { font-size: 13px; }
.dorm-info { font-size: 11px; color: #555; line-height: 1.5; margin-top: 3px; }
.bill-title { text-align: right; }
.bill-title h2 { font-size: 18px; color: #1a5276; font-weight: 800; }
.bill-title h2.sm { font-size: 14px; }
.bill-title p { color: #777; font-size: 11px; }
.divider { border: none; border-top: 2px solid #1a5276; margin: 12px 0; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; }
.info-box { background: #f5f8fd; border-radius: 8px; padding: 10px; }
.info-label { font-size: 9px; color: #999; margin-bottom: 2px; }
.info-value { font-weight: 700; font-size: 12px; }
.info-sub   { font-size: 10px; color: #666; margin-top: 2px; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 11px; }
th { background: #1a5276; color: #fff; padding: 7px 10px; text-align: left; font-size: 10px; }
td { padding: 7px 10px; border-bottom: 1px solid #eee; }
.total-row { background: #eaf4fc; font-weight: 800; }
.total-row td { padding: 9px 10px; border-bottom: none; }
.amount { text-align: right; }
.grand-total {
    background: linear-gradient(135deg, #1a5276, #2980b9);
    color: #fff; border-radius: 10px;
    padding: 14px; text-align: center; margin: 12px 0;
}
.grand-total-label  { font-size: 11px; opacity: 0.8; }
.grand-total-amount { font-size: 28px; font-weight: 800; }
.grand-total-amount.sm { font-size: 22px; }
.status-paid    { display:inline-block; padding:4px 14px; border-radius:20px; background:#27ae60; color:#fff; font-weight:700; font-size:12px; }
.status-pending { display:inline-block; padding:4px 14px; border-radius:20px; background:#e67e22; color:#fff; font-weight:700; font-size:12px; }
.footer-note { margin-top: 12px; font-size: 10px; color: #999; text-align: center; }

@media print {
    body { background: #fff; }
    .top-bar { display: none !important; }
    .page-a4 { margin:0; padding:20px; box-shadow:none; border-radius:0; max-width:100%; }
    .page-a6 { margin:0; padding:6mm; box-shadow:none; border-radius:0; }
}
</style>
</head>
<body id="printBody">

<!-- Top Bar -->
<div class="top-bar">
    <button class="btn-a4" onclick="printA4()">🖨️ พิมพ์ A4</button>
    <button class="btn-a6" onclick="printA6()">📄 พิมพ์ A6 (ขนาดเล็ก)</button>
    <span>ใบแจ้งหนี้ BL-<?= str_pad($bill_id, 5, '0', STR_PAD_LEFT) ?></span>
</div>

<!-- A4 -->
<div class="page-a4" id="pageA4">
    <div class="header">
        <div>
            <div class="dorm-name"><?= $dorm_name ?></div>
            <div class="dorm-info"><?= nl2br($dorm_address) ?><br>โทร: <?= $dorm_tel ?></div>
        </div>
        <div class="bill-title">
            <h2>ใบแจ้งหนี้</h2>
            <p>เลขที่: BL-<?= str_pad($bill_id, 5, '0', STR_PAD_LEFT) ?></p>
            <p>วันที่: <?= date('d/m/Y', strtotime($bill['billingdate'])) ?></p>
            <p>กำหนดชำระ: <?= date('d/m/Y', strtotime($bill['due_date'])) ?></p>
            <br>
            <span class="<?= $bill['status']==='paid'?'status-paid':'status-pending' ?>">
                <?= $bill['status']==='paid' ? '✅ ชำระแล้ว' : '⏳ รอชำระ' ?>
            </span>
        </div>
    </div>
    <hr class="divider">
    <div class="info-grid">
        <div class="info-box">
            <div class="info-label">ผู้เช่า</div>
            <div class="info-value"><?= $bill['name'].' '.$bill['lastname'] ?></div>
            <div class="info-sub">บัตร: <?= $bill['id_people'] ?> | โทร: <?= $bill['tel'] ?></div>
        </div>
        <div class="info-box">
            <div class="info-label">ห้องพัก</div>
            <div class="info-value">ห้อง <?= $bill['id_room'] ?> (<?= $bill['name_roomtype'] ?>)</div>
            <div class="info-sub">เดือน: <?= date('F Y', strtotime($bill['billing_month'])) ?></div>
        </div>
    </div>
    <table>
        <thead><tr><th>รายการ</th><th>รายละเอียด</th><th class="amount">จำนวนเงิน (฿)</th></tr></thead>
        <tbody>
            <tr><td>ค่าเช่าห้อง</td><td>ประจำเดือน <?= date('F Y', strtotime($bill['billing_month'])) ?></td><td class="amount"><?= number_format($bill['priceroom'], 2) ?></td></tr>
            <?php if ($bill['water_used'] > 0 || $bill['water_curr_unit'] > 0): ?>
            <tr><td>ค่าน้ำ</td><td>หน่วยก่อน: <?= $bill['water_prev_unit'] ?> → หน่วยนี้: <?= $bill['water_curr_unit'] ?> (ใช้ <?= $bill['water_used'] ?> หน่วย × <?= $bill['water_unit_price'] ?> บาท)</td><td class="amount"><?= number_format($bill['water_amount'], 2) ?></td></tr>
            <?php endif; ?>
            <?php if ($bill['electric_used'] > 0 || $bill['electric_curr_unit'] > 0): ?>
            <tr><td>ค่าไฟ</td><td>หน่วยก่อน: <?= $bill['electric_prev_unit'] ?> → หน่วยนี้: <?= $bill['electric_curr_unit'] ?> (ใช้ <?= $bill['electric_used'] ?> หน่วย × <?= $bill['electric_unit_price'] ?> บาท)</td><td class="amount"><?= number_format($bill['electric_amount'], 2) ?></td></tr>
            <?php endif; ?>
            <?php if ($bill['other_fee'] > 0): ?>
            <tr><td>ค่าอื่นๆ</td><td><?= $bill['other_fee_note'] ?: '-' ?></td><td class="amount"><?= number_format($bill['other_fee'], 2) ?></td></tr>
            <?php endif; ?>
            <tr class="total-row"><td colspan="2" style="text-align:right;">รวมทั้งสิ้น</td><td class="amount"><?= number_format($bill['total_amount'], 2) ?></td></tr>
        </tbody>
    </table>
    <div class="grand-total">
        <div class="grand-total-label">ยอดรวมที่ต้องชำระ</div>
        <div class="grand-total-amount"><?= number_format($bill['total_amount'], 2) ?> ฿</div>
        <div style="font-size:12px;opacity:0.8;margin-top:4px;">กำหนดชำระภายใน <?= date('d/m/Y', strtotime($bill['due_date'])) ?></div>
    </div>
    <div class="footer-note">ขอบคุณที่ใช้บริการ <?= $dorm_name ?> | โทร: <?= $dorm_tel ?></div>
</div>

<!-- A6 -->
<div class="page-a6" id="pageA6">
    <div class="header">
        <div>
            <div class="dorm-name sm"><?= $dorm_name ?></div>
            <div class="dorm-info">โทร: <?= $dorm_tel ?></div>
        </div>
        <div class="bill-title">
            <h2 class="sm">ใบแจ้งหนี้</h2>
            <p>BL-<?= str_pad($bill_id, 5, '0', STR_PAD_LEFT) ?> | <?= date('d/m/Y', strtotime($bill['billingdate'])) ?></p>
            <span class="<?= $bill['status']==='paid'?'status-paid':'status-pending' ?>" style="font-size:10px;padding:2px 8px;">
                <?= $bill['status']==='paid' ? '✅ ชำระแล้ว' : '⏳ รอชำระ' ?>
            </span>
        </div>
    </div>
    <hr class="divider">
    <div style="margin-bottom:8px;">
        <strong><?= $bill['name'].' '.$bill['lastname'] ?></strong> | ห้อง <?= $bill['id_room'] ?> | <?= date('M Y', strtotime($bill['billing_month'])) ?>
    </div>
    <table>
        <thead><tr><th>รายการ</th><th class="amount">฿</th></tr></thead>
        <tbody>
            <tr><td>ค่าเช่าห้อง</td><td class="amount"><?= number_format($bill['priceroom'], 0) ?></td></tr>
            <?php if ($bill['water_used'] > 0): ?>
            <tr><td>ค่าน้ำ (<?= $bill['water_used'] ?> หน่วย × <?= $bill['water_unit_price'] ?>)</td><td class="amount"><?= number_format($bill['water_amount'], 0) ?></td></tr>
            <?php endif; ?>
            <?php if ($bill['electric_used'] > 0): ?>
            <tr><td>ค่าไฟ (<?= $bill['electric_used'] ?> หน่วย × <?= $bill['electric_unit_price'] ?>)</td><td class="amount"><?= number_format($bill['electric_amount'], 0) ?></td></tr>
            <?php endif; ?>
            <?php if ($bill['other_fee'] > 0): ?>
            <tr><td><?= $bill['other_fee_note'] ?: 'ค่าอื่นๆ' ?></td><td class="amount"><?= number_format($bill['other_fee'], 0) ?></td></tr>
            <?php endif; ?>
            <tr class="total-row"><td><strong>รวม</strong></td><td class="amount"><strong><?= number_format($bill['total_amount'], 0) ?></strong></td></tr>
        </tbody>
    </table>
    <div class="grand-total">
        <div class="grand-total-label">ยอดที่ต้องชำระ</div>
        <div class="grand-total-amount sm"><?= number_format($bill['total_amount'], 0) ?> ฿</div>
        <div style="font-size:10px;opacity:0.8;">ครบกำหนด <?= date('d/m/Y', strtotime($bill['due_date'])) ?></div>
    </div>
    <div class="footer-note"><?= $dorm_name ?> โทร <?= $dorm_tel ?></div>
</div>

<script>
function printA4() {
    document.getElementById('pageA4').style.display = 'block';
    document.getElementById('pageA6').style.display = 'none';
    removePageStyle();
    setTimeout(() => window.print(), 100);
}

function printA6() {
    document.getElementById('pageA4').style.display = 'none';
    document.getElementById('pageA6').style.display = 'block';
    addPageStyle('@page { size: A6; margin: 5mm; }');
    setTimeout(() => {
        window.print();
        setTimeout(() => {
            document.getElementById('pageA4').style.display = 'block';
            document.getElementById('pageA6').style.display = 'none';
        }, 800);
    }, 100);
}

function addPageStyle(css) {
    removePageStyle();
    const s = document.createElement('style');
    s.id = 'dynamicPageStyle';
    s.innerHTML = css;
    document.head.appendChild(s);
}

function removePageStyle() {
    document.getElementById('dynamicPageStyle')?.remove();
}

// ถ้ามี ?size=a6 ใน URL ให้เปิด A6 อัตโนมัติ
if (new URLSearchParams(window.location.search).get('size') === 'a6') {
    window.addEventListener('load', () => printA6());
}
</script>

</body>
