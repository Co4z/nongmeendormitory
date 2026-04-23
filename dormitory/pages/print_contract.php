<?php
require_once '../config/db.php';
requireLogin();

$contract_id = (int)($_GET['id'] ?? 0);

// ดึงข้อมูลสัญญาเช่า
$contract = $conn->query("
    SELECT ct.*, c.name, c.lastname, rn.id_room, rt.name_roomtype
    FROM contract ct
    JOIN customer c ON ct.id_people = c.id_people
    JOIN room_number rn ON ct.id_room = rn.id_room
    JOIN room_type rt ON rn.id_roomtype = rt.id_roomtype
    WHERE ct.contract_id = $contract_id
")->fetch_assoc();

if (!$contract) die('ไม่พบข้อมูลสัญญาเช่า');

$dorm_name    = getSetting('dorm_name');
$dorm_address = getSetting('dorm_address');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>สัญญาเช่าห้องพัก #<?= $contract_id ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Sarabun', sans-serif; line-height: 1.8; color: #333; padding: 40px; }
    .paper { max-width: 800px; margin: auto; border: 1px solid #eee; padding: 50px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
    .header { text-align: center; margin-bottom: 40px; }
    .header h1 { font-size: 24px; margin-bottom: 5px; color: #1a5276; }
    .content-section { margin-bottom: 25px; }
    .title { font-weight: 700; border-bottom: 2px solid #1a5276; display: inline-block; margin-bottom: 10px; }
    .row { display: flex; justify-content: space-between; margin-bottom: 10px; }
    .signature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 50px; margin-top: 80px; text-align: center; }
    .sig-line { border-bottom: 1px solid #333; margin-bottom: 10px; height: 40px; }
    @media print { 
        .no-print { display: none; }
        body { padding: 0; }
        .paper { border: none; box-shadow: none; width: 100%; max-width: none; }
    }
</style>
</head>
<body>

<div class="no-print" style="text-align:center; margin-bottom: 20px;">
    <button onclick="window.print()" style="padding: 10px 20px; background: #1a5276; color: #fff; border: none; border-radius: 5px; cursor: pointer;">🖨️ พิมพ์สัญญาเช่า</button>
</div>

<div class="paper">
    <div class="header">
        <h1>สัญญาเช่าที่พักอาศัย</h1>
        <p><?= $dorm_name ?></p>
        <p style="font-size: 14px;"><?= nl2br($dorm_address) ?></p>
    </div>

    <div class="content-section">
        <p><strong>ทำขึ้นเมื่อวันที่:</strong> <?= date('d/m/Y', strtotime($contract['start_date'])) ?></p>
        <p>สัญญาฉบับนี้ทำขึ้นระหว่าง <strong><?= $dorm_name ?></strong> (ผู้ให้เช่า) และ <strong>คุณ <?= $contract['name'].' '.$contract['lastname'] ?></strong> (ผู้เช่า)</p>
    </div>

    <div class="content-section">
        <div class="title">รายละเอียดการเช่า</div>
        <p>ผู้เช่าตกลงเช่าพักอาศัย ณ <strong>ห้องหมายเลข <?= $contract['id_room'] ?></strong> ประเภท <strong><?= $contract['name_roomtype'] ?></strong></p>
        <div class="row">
            <span>อัตราค่าเช่ารายเดือน:</span>
            <span><strong><?= number_format($contract['rent_price'] ?? 0, 2) ?> บาท</strong></span>
        </div>
        <div class="row">
            <span>เงินประกัน/มัดจำ:</span>
            <span><strong><?= number_format($contract['deposit'] ?? 0, 2) ?> บาท</strong></span>
        </div>
    </div>

    <div class="content-section">
        <div class="title">ข้อตกลงและเงื่อนไข</div>
        <ol>
            <li>ผู้เช่าตกลงจะชำระค่าเช่าภายในวันที่กำหนดในใบแจ้งหนี้ทุกเดือน</li>
            <li>ค่าน้ำหน่วยละ <?= number_format($contract['water_unit_price'], 2) ?> บาท และค่าไฟหน่วยละ <?= number_format($contract['electric_unit_price'], 2) ?> บาท</li>
            <li>ผู้เช่าต้องดูแลรักษาความสะอาดและไม่กระทำการใดๆ ที่รบกวนผู้อื่น</li>
            <li>หากมีการยกเลิกสัญญา ต้องแจ้งล่วงหน้าอย่างน้อย 30 วัน</li>
        </ol>
    </div>

    <div class="signature-grid">
        <div>
            <div class="sig-line"></div>
            <p>(......................................................)</p>
            <p>ลงชื่อ ผู้เช่า</p>
        </div>
        <div>
            <div class="sig-line"></div>
            <p>(......................................................)</p>
            <p>ลงชื่อ ผู้ให้เช่า (<?= $dorm_name ?>)</p>
        </div>
    </div>
</div>

</body>
</html>