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
body { font-family: 'Sarabun', sans-serif; font-size: 14px; color: #222; }
.page { max-width: 750px; margin: 20px auto; padding: 40px; }
.header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; }
.dorm-name { font-size: 22px; font-weight: 800; color: #1a5276; }
.dorm-info { font-size: 13px; color: #555; line-height: 1.6; }
.bill-title { text-align: right; }
.bill-title h2 { font-size: 24px; color: #1a5276; }
.bill-title p  { color: #777; font-size: 13px; }
.divider { border: none; border-top: 2px solid #1a5276; margin: 20px 0; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
.info-box { background: #f5f8fd; border-radius: 10px; padding: 16px; }
.info-label { font-size: 11px; color: #999; margin-bottom: 4px; }
.info-value { font-weight: 700; font-size: 15px; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
th { background: #1a5276; color: #fff; padding: 10px 14px; text-align: left; font-size: 13px; }
td { padding: 10px 14px; border-bottom: 1px solid #eee; }
.total-row { background: #eaf4fc; font-weight: 800; font-size: 16px; }
.total-row td { padding: 14px; border-bottom: none; }
.amount { text-align: right; }
.grand-total { 
    background: linear-gradient(135deg, #1a5276, #2980b9); 
    color: #fff; border-radius: 12px; 
    padding: 24px; text-align: center; margin: 20px 0;
}
.grand-total-label { font-size: 14px; opacity: 0.8; }
.grand-total-amount { font-size: 36px; font-weight: 800; margin-bottom: 10px; }
.bank-info {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid rgba(255,255,255,0.2);
}
.bank-details {
    font-size: 20px;
    font-weight: 700;
    letter-spacing: 1px;
    margin: 5px 0;
}
.status-paid { 
    display: inline-block; padding: 6px 20px; border-radius: 20px;
    background: #27ae60; color: #fff; font-weight: 700; font-size: 14px;
}
.status-pending {
    display: inline-block; padding: 6px 20px; border-radius: 20px;
    background: #e67e22; color: #fff; font-weight: 700; font-size: 14px;
}
.footer-note { margin-top: 30px; font-size: 12px; color: #999; text-align: center; }
.print-btn { text-align: center; margin: 20px; }
.print-btn button { 
    padding: 12px 32px; background: #1a5276; color: #fff; 
    border: none; border-radius: 8px; font-size: 16px; 
    cursor: pointer; font-family: inherit;
}
@media print {
    .print-btn { display: none; }
    .page { margin: 0; padding: 20px; }
}
</style>
</head>
<body>

<div class="print-btn no-print">
    <button onclick="window.print()">🖨️ พิมพ์ใบแจ้งหนี้</button>
</div>

<div class="page">
    <div class="header">
        <div>
            <div class="dorm-name"><?= $dorm_name ?></div>
            <div class="dorm-info">
                <?= nl2br($dorm_address) ?>
            </div>
        </div>
        <div class="bill-title">
            <h2>ใบแจ้งหนี้</h2>
            <p>เลขที่: BL-<?= str_pad($bill_id, 5, '0', STR_PAD_LEFT) ?></p>
            <p>วันที่: <?= date('d/m/Y', strtotime($bill['billingdate'])) ?></p>
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
            <div style="font-size:13px;color:#666;margin-top:4px;">
                ขอบคุณที่ไว้วางใจใช้บริการกับเรา
            </div>
        </div>
        <div class="info-box">
            <div class="info-label">ห้องพัก</div>
            <div class="info-value">ห้อง <?= $bill['id_room'] ?> (<?= $bill['name_roomtype'] ?>)</div>
            <div style="font-size:13px;color:#666;margin-top:4px;">
                เดือนที่เก็บ: <?= date('F Y', strtotime($bill['billing_month'])) ?>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>รายการ</th>
                <th>รายละเอียด</th>
                <th class="amount">จำนวนเงิน (฿)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>ค่าเช่าห้อง</td>
                <td>ประจำเดือน <?= date('F Y', strtotime($bill['billing_month'])) ?></td>
                <td class="amount"><?= number_format($bill['priceroom'], 2) ?></td>
            </tr>
            <?php if ($bill['water_used'] > 0 || $bill['water_curr_unit'] > 0): ?>
            <tr>
                <td>ค่าน้ำ</td>
                <td>
                    หน่วยก่อน: <?= number_format($bill['water_prev_unit']) ?> → 
                    หน่วยนี้: <?= number_format($bill['water_curr_unit']) ?><br>
                    ใช้ <?= $bill['water_used'] ?> หน่วย × <?= number_format($bill['water_unit_price'], 2) ?> บาท
                </td>
                <td class="amount"><?= number_format($bill['water_amount'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($bill['electric_used'] > 0 || $bill['electric_curr_unit'] > 0): ?>
            <tr>
                <td>ค่าไฟฟ้า</td>
                <td>
                    หน่วยก่อน: <?= number_format($bill['electric_prev_unit']) ?> → 
                    หน่วยนี้: <?= number_format($bill['electric_curr_unit']) ?><br>
                    ใช้ <?= $bill['electric_used'] ?> หน่วย × <?= number_format($bill['electric_unit_price'], 2) ?> บาท
                </td>
                <td class="amount"><?= number_format($bill['electric_amount'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($bill['other_fee'] > 0): ?>
            <tr>
                <td>ค่าใช้จ่ายอื่นๆ</td>
                <td><?= $bill['other_fee_note'] ?: '-' ?></td>
                <td class="amount"><?= number_format($bill['other_fee'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="total-row">
                <td colspan="2" style="text-align:right;">รวมทั้งสิ้น</td>
                <td class="amount"><?= number_format($bill['total_amount'], 2) ?></td>
            </tr>
        </tbody>
    </table>

    <div class="grand-total">
        <div class="grand-total-label">ยอดรวมที่ต้องชำระ</div>
        <div class="grand-total-amount"><?= number_format($bill['total_amount'], 2) ?> ฿</div>
        
        <div class="bank-info">
            <div style="font-size: 14px; opacity: 0.9;">ชำระเงินผ่าน: ธนาคารไทยพาณิชย์</div>
            <div class="bank-details">4110837333</div>
            <div style="font-size: 15px; font-weight: 600;">ชื่อบัญชี: นายอภิสิทธิ์ เยียระยงค์</div>
        </div>

        <div style="font-size:13px; opacity:0.8; margin-top:15px;">
            กรุณาชำระภายในวันที่ <?= date('d/m/Y', strtotime($bill['due_date'])) ?>
        </div>
    </div>

    <div class="footer-note">
        ขอบคุณที่ใช้บริการ <?= $dorm_name ?>
        <br>หากมีข้อสงสัยกรุณาติดต่อเจ้าหน้าที่ที่เบอร์ <?= $dorm_tel ?>
    </div>
</div>

</body>
</html>