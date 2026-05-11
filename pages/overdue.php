<?php
require_once '../config/db.php';
requireLogin();
$page_title = 'รายงานผู้ค้างชำระ';

// Auto-update overdue
$conn->query("UPDATE bill SET status='overdue' WHERE status='pending' AND due_date < CURDATE()");

$overdue = $conn->query("
    SELECT b.*, bl.total_amount, c.name, c.lastname, c.tel, rn.id_room,
           DATEDIFF(CURDATE(), b.due_date) as days_overdue
    FROM bill b
    LEFT JOIN bill_list bl ON b.id_listbill = bl.id_listbill
    JOIN customer c ON b.id_people = c.id_people
    JOIN contract ct ON b.contract_id = ct.contract_id
    JOIN room_number rn ON ct.id_room = rn.id_room
    WHERE b.status='overdue'
    ORDER BY days_overdue DESC
");

$total_overdue_amount = $conn->query("
    SELECT COALESCE(SUM(bl.total_amount),0) as total
    FROM bill b JOIN bill_list bl ON b.id_listbill=bl.id_listbill
    WHERE b.status='overdue'
")->fetch_assoc()['total'];

include '../includes/header.php';
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="stat-card red">
        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div>
            <div class="stat-label">จำนวนบิลค้างชำระ</div>
            <div class="stat-value"><?= $overdue->num_rows ?></div>
            <div class="stat-sub">บิล</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon"><i class="fa-solid fa-baht-sign"></i></div>
        <div>
            <div class="stat-label">ยอดค้างชำระรวม</div>
            <div class="stat-value" style="font-size:18px;"><?= number_format($total_overdue_amount, 0) ?></div>
            <div class="stat-sub">บาท</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fa-solid fa-print"></i></div>
        <div style="display:flex;flex-direction:column;gap:8px;justify-content:center;">
            <a href="?print=1" target="_blank" class="btn btn-warning btn-sm">
                <i class="fa-solid fa-print"></i> พิมพ์รายงาน
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title"><i class="fa-solid fa-triangle-exclamation"></i> รายชื่อผู้ค้างชำระ</div>
    <div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>ห้อง</th>
                <th>ผู้เช่า</th>
                <th>เบอร์โทร</th>
                <th>เดือน</th>
                <th>ยอดค้าง</th>
                <th>เกินกำหนด</th>
                <th>กำหนดชำระ</th>
                <th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $overdue->fetch_assoc()):
            $days = $row['days_overdue'];
            $urgency = $days > 30 ? 'danger' : ($days > 14 ? 'warning' : 'info');
        ?>
        <tr>
            <td><strong><?= $row['id_room'] ?></strong></td>
            <td><?= $row['name'].' '.$row['lastname'] ?></td>
            <td>
                <a href="tel:<?= $row['tel'] ?>"><?= $row['tel'] ?></a>
            </td>
            <td><?= date('M Y', strtotime($row['billing_month'])) ?></td>
            <td class="fw-bold text-danger"><?= number_format($row['total_amount'], 0) ?> ฿</td>
            <td>
                <span class="badge badge-<?= $urgency ?>">
                    <?= $days ?> วัน
                </span>
            </td>
            <td><?= date('d/m/Y', strtotime($row['due_date'])) ?></td>
            <td>
                <a href="payments.php?bill_id=<?= $row['id_listbill'] ?>"
                   class="btn btn-success btn-sm">
                    <i class="fa-solid fa-check"></i> รับชำระ
                </a>
                <a href="print_bill.php?id=<?= $row['id_listbill'] ?>" target="_blank"
                   class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-print"></i>
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
