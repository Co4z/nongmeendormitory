<?php
require_once '../config/db.php';
requireLogin();
$page_title = 'ภาพรวม';

// Stats
$total_rooms     = $conn->query("SELECT COUNT(*) as c FROM room_number")->fetch_assoc()['c'];
$vacant_rooms    = $conn->query("SELECT COUNT(*) as c FROM room_number WHERE id_statusroom=1")->fetch_assoc()['c'];
$occupied_rooms  = $conn->query("SELECT COUNT(*) as c FROM room_number WHERE id_statusroom=2")->fetch_assoc()['c'];

$month_income = $conn->query("
    SELECT COALESCE(SUM(p.paid_amount),0) as total FROM payment p
    WHERE DATE_FORMAT(p.paid_date,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')
")->fetch_assoc()['total'];

$overdue_count = $conn->query("
    SELECT COUNT(*) as c FROM bill 
    WHERE status='pending' AND due_date < CURDATE()
")->fetch_assoc()['c'];

$pending_bills = $conn->query("
    SELECT COUNT(*) as c FROM bill WHERE status='pending'
")->fetch_assoc()['c'];

// Recent payments
$recent_payments = $conn->query("
    SELECT p.*, b.billing_month, c.name, c.lastname, rn.id_room
    FROM payment p
    JOIN bill b ON p.id_listbill = b.id_listbill
    JOIN customer c ON b.id_people = c.id_people
    JOIN contract ct ON b.contract_id = ct.contract_id
    JOIN room_number rn ON ct.id_room = rn.id_room
    ORDER BY p.created_at DESC LIMIT 8
");

// Room status overview
$rooms_by_floor = $conn->query("
    SELECT rn.floor_number, rn.id_room, rt.name_roomtype, sr.status_name, rn.id_statusroom
    FROM room_number rn
    JOIN room_type rt ON rn.id_roomtype = rt.id_roomtype
    JOIN status_room sr ON rn.id_statusroom = sr.id_statusroom
    ORDER BY rn.floor_number, rn.id_room
");

include '../includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fa-solid fa-door-open"></i></div>
        <div>
            <div class="stat-label">ห้องทั้งหมด</div>
            <div class="stat-value"><?= $total_rooms ?></div>
            <div class="stat-sub">ห้อง</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fa-solid fa-check-circle"></i></div>
        <div>
            <div class="stat-label">ว่าง</div>
            <div class="stat-value"><?= $vacant_rooms ?></div>
            <div class="stat-sub">ห้องว่าง</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fa-solid fa-user"></i></div>
        <div>
            <div class="stat-label">มีผู้เช่า</div>
            <div class="stat-value"><?= $occupied_rooms ?></div>
            <div class="stat-sub">ห้อง</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fa-solid fa-baht-sign"></i></div>
        <div>
            <div class="stat-label">รายรับเดือนนี้</div>
            <div class="stat-value" style="font-size:18px;"><?= number_format($month_income, 0) ?></div>
            <div class="stat-sub">บาท</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div>
            <div class="stat-label">ค้างชำระ</div>
            <div class="stat-value"><?= $overdue_count ?></div>
            <div class="stat-sub">ราย</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fa-solid fa-file-invoice"></i></div>
        <div>
            <div class="stat-label">รอชำระ</div>
            <div class="stat-value"><?= $pending_bills ?></div>
            <div class="stat-sub">บิล</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">

<!-- Recent Payments -->
<div class="card">
    <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> การชำระเงินล่าสุด</div>
    <div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>ผู้เช่า</th>
                <th>ห้อง</th>
                <th>เดือน</th>
                <th>จำนวน</th>
                <th>วิธีชำระ</th>
                <th>วันที่</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $recent_payments->fetch_assoc()): ?>
            <tr>
                <td><?= $row['name'].' '.$row['lastname'] ?></td>
                <td><?= $row['id_room'] ?></td>
                <td><?= date('M y', strtotime($row['billing_month'])) ?></td>
                <td class="fw-bold text-success"><?= number_format($row['paid_amount'], 0) ?> ฿</td>
                <td>
                    <?php $m=$row['payment_method'];
                    $icons=['cash'=>'💵 เงินสด','transfer'=>'🏦 โอน','qr'=>'📱 QR'];
                    echo $icons[$m] ?? $m; ?>
                </td>
                <td class="text-muted"><?= date('d/m/y H:i', strtotime($row['created_at'])) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Room Quick View -->
<div class="card">
    <div class="card-title"><i class="fa-solid fa-layer-group"></i> สถานะห้องพัก</div>
    <div class="room-grid" style="grid-template-columns:repeat(auto-fill,minmax(70px,1fr));gap:8px;">
    <?php while($r = $rooms_by_floor->fetch_assoc()):
        $cls = ['1'=>'vacant','2'=>'occupied','3'=>'maintenance'][$r['id_statusroom']] ?? '';
        $dot = ['vacant'=>'dot-green','occupied'=>'dot-orange','maintenance'=>'dot-red'][$cls] ?? '';
    ?>
        <a href="rooms.php" class="room-card <?= $cls ?>" style="padding:10px;min-width:0;">
            <div class="room-number" style="font-size:16px;"><?= $r['id_room'] ?></div>
            <div class="room-type-name" style="font-size:10px;"><?= $r['name_roomtype'] ?></div>
        </a>
    <?php endwhile; ?>
    </div>
    <div style="margin-top:14px;display:flex;gap:16px;font-size:12px;">
        <span><span class="room-status-dot dot-green"></span> ว่าง</span>
        <span><span class="room-status-dot dot-orange"></span> มีผู้เช่า</span>
        <span><span class="room-status-dot dot-red"></span> ซ่อม</span>
    </div>
</div>

</div>

<?php include '../includes/footer.php'; ?>
