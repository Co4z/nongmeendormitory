<?php
require_once '../config/db.php';
requireLogin();

// รับค่า ID บิลแบบหลายตัวผ่าน URL เช่น ?ids=1,2,3,4
$ids_str = $_GET['ids'] ?? '';
if (empty($ids_str)) die('กรุณาระบุ ID บิลที่ต้องการพิมพ์ (เช่น ?ids=2)');

$ids_array = explode(',', $ids_str);
$ids_array = array_map('intval', $ids_array); 
$ids_list = implode(',', $ids_array);

// ดึงข้อมูลบิล
$sql = "SELECT b.*, bl.*, c.name, c.lastname, rn.id_room, rt.name_roomtype,
               ct.water_unit_price, ct.electric_unit_price
        FROM bill b
        LEFT JOIN bill_list bl ON b.id_listbill = bl.id_listbill
        JOIN customer c ON b.id_people = c.id_people
        JOIN contract ct ON b.contract_id = ct.contract_id
        JOIN room_number rn ON ct.id_room = rn.id_room
        JOIN room_type rt ON rn.id_roomtype = rt.id_roomtype
        WHERE b.id_listbill IN ($ids_list)
        ORDER BY rn.id_room ASC";

$result = $conn->query($sql);

$dorm_name    = getSetting('dorm_name');
$dorm_address = getSetting('dorm_address');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>พิมพ์บิลประหยัดกระดาษ</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sarabun', sans-serif; background: #eee; }
    
    @page { size: A4; margin: 0; }
    
    .a4-page {
        width: 210mm;
        height: 297mm;
        background: #fff;
        margin: auto;
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 1fr 1fr;
        padding: 5mm;
    }

    .bill-item {
        border: 1px dashed #ddd; /* เส้นประสำหรับใช้กรรไกรตัด */
        padding: 10mm;
        height: 143mm;
        display: flex;
        flex-direction: column;
    }

    .header-mini { display: flex; justify-content: space-between; margin-bottom: 5px; }
    .title-mini { font-size: 18px; font-weight: 800; color: #1a5276; }
    .bill-no { font-size: 11px; color: #888; }
    
    .info-row { font-size: 13px; margin-bottom: 3px; display: flex; justify-content: space-between; }
    
    table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
    th { border-bottom: 2px solid #1a5276; text-align: left; padding: 5px; }
    td { padding: 5px; border-bottom: 1px solid #eee; }
    
    .grand-total-mini {
        margin-top: auto;
        background: #1a5276;
        color: #fff;
        padding: 12px;
        border-radius: 8px;
        text-align: center;
    }
    .total-amt { font-size: 24px; font-weight: 800; }
    .bank-mini { font-size: 11px; margin-top: 10px; text-align: center; color: #555; }

    @media print {
        body { background: none; }
        .no-print { display: none; }
    }
</style>
</head>
<body>

<div class="no-print" style="padding: 20px; text-align: center;">
    <button onclick="window.print()" style="padding: 12px 25px; background: #27ae60; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
        🖨️ กดพิมพ์ใบแจ้งหนี้ (4 ใบ/แผ่น)
    </button>
</div>

<div class="a4-page">
    <?php while ($b = $result->fetch_assoc()): ?>
    <div class="bill-item">
        <div class="header-mini">
            <div class="title-mini">ใบแจ้งหนี้</div>
            <div class="bill-no">#<?= str_pad($b['id_listbill'], 5, '0', STR_PAD_LEFT) ?></div>
        </div>
        
        <div style="font-size: 12px; font-weight: bold; color: #1a5276; margin-bottom: 8px;"><?= $dorm_name ?></div>

        <div class="info-row">
            <span><strong>ห้อง: <?= $b['id_room'] ?></strong></span>
            <span>เดือน: <?= date('M Y', strtotime($b['billing_month'])) ?></span>
        </div>
        <div class="info-row">
            <span>คุณ <?= $b['name'].' '.$b['lastname'] ?></span>
        </div>

        <table>
            <thead>
                <tr>
                    <th>รายการ</th>
                    <th style="text-align:right;">ยอดเงิน</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>ค่าเช่าห้อง</td>
                    <td style="text-align:right;"><?= number_format($b['priceroom'], 2) ?></td>
                </tr>
                <?php if ($b['water_used'] > 0): ?>
                <tr>
                    <td>ค่าน้ำ (<?= $b['water_used'] ?> หน่วย)</td>
                    <td style="text-align:right;"><?= number_format($b['water_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($b['electric_used'] > 0): ?>
                <tr>
                    <td>ค่าไฟ (<?= $b['electric_used'] ?> หน่วย)</td>
                    <td style="text-align:right;"><?= number_format($b['electric_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="grand-total-mini">
            <div style="font-size: 11px; opacity: 0.9;">ยอดรวมสุทธิ</div>
            <div class="total-amt"><?= number_format($b['total_amount'], 2) ?> ฿</div>
        </div>

        <div class="bank-mini">
            <b>โอนที่:</b> กสิกรไทย 123-4-56789-0 (อภิสิทธิ์ เยียรยอง)
        </div>
    </div>
    <?php endwhile; ?>
</div>

</body>
</html>