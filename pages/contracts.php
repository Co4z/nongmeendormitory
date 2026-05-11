<?php
require_once '../config/db.php';
requireLogin();
$page_title = 'สัญญาเช่า';

$msg = $err = '';

// Create contract
if (isset($_POST['add_contract'])) {
    $id_room   = (int)$_POST['id_room'];
    $id_people = esc($_POST['id_people']);
    $price     = (int)$_POST['priceroom'];
    $rentdate  = esc($_POST['rentdate']);
    $deposit   = (int)($_POST['deposit'] ?? 0);
    $water_p   = (float)($_POST['water_unit_price'] ?? getSetting('water_price_per_unit') ?: 18);
    $elec_p    = (float)($_POST['electric_unit_price'] ?? getSetting('electric_price_per_unit') ?: 8);
    $first_day = $rentdate;
    $months    = (int)($_POST['duration_months'] ?? 12);
    $due_date  = date('Y-m-d', strtotime("+$months months", strtotime($first_day)));

    // Create period
    $conn->query("INSERT INTO period (first_day, due_date, duration_months) VALUES ('$first_day','$due_date',$months)");
    $rent_id = $conn->insert_id;

    // Create contract
    if ($conn->query("INSERT INTO contract (id_room, id_people, ad_id, rent_id, priceroom, rentdate, water_unit_price, electric_unit_price, deposit)
        VALUES ($id_room, '$id_people', {$_SESSION['ad_id']}, $rent_id, $price, '$rentdate', $water_p, $elec_p, $deposit)")) {
        // Update room status to occupied
        $conn->query("UPDATE room_number SET id_statusroom=2 WHERE id_room=$id_room");
        $msg = "สร้างสัญญาเช่าสำเร็จ!";
    } else {
        $err = "เกิดข้อผิดพลาด: " . $conn->error;
    }
}

// End contract
if (isset($_POST['end_contract'])) {
    $cid = (int)$_POST['contract_id'];
    $rid = (int)$_POST['room_id'];
    $conn->query("UPDATE contract SET status='ended' WHERE contract_id=$cid");
    $conn->query("UPDATE room_number SET id_statusroom=1 WHERE id_room=$rid");
    $msg = "สิ้นสุดสัญญาแล้ว ห้องสถานะว่าง";
}

$filter_customer = esc($_GET['customer'] ?? '');
$where = $filter_customer ? "WHERE ct.id_people='$filter_customer'" : "WHERE 1=1";

$contracts = $conn->query("
    SELECT ct.*, c.name, c.lastname, c.tel, 
           rn.id_room, rt.name_roomtype,
           p.first_day, p.due_date as contract_due, p.duration_months
    FROM contract ct
    JOIN customer c ON ct.id_people = c.id_people
    JOIN room_number rn ON ct.id_room = rn.id_room
    JOIN room_type rt ON rn.id_roomtype = rt.id_roomtype
    LEFT JOIN period p ON ct.rent_id = p.rent_id
    $where
    ORDER BY ct.status, ct.created_at DESC
");

// Available rooms
$vacant_rooms = $conn->query("
    SELECT rn.*, rt.name_roomtype, rt.rentcost
    FROM room_number rn JOIN room_type rt ON rn.id_roomtype=rt.id_roomtype
    WHERE rn.id_statusroom=1 ORDER BY rn.id_room
");

$all_customers = $conn->query("SELECT * FROM customer ORDER BY name");

$water_default = getSetting('water_price_per_unit') ?: 18;
$elec_default  = getSetting('electric_price_per_unit') ?: 8;

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fa fa-times"></i> <?= $err ?></div><?php endif; ?>

<div class="card">
    <div class="card-title d-flex justify-between align-center">
        <span><i class="fa-solid fa-file-contract"></i> รายการสัญญาเช่า</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('modalAddContract')">
            <i class="fa-solid fa-plus"></i> สร้างสัญญาใหม่
        </button>
    </div>

    <?php if ($filter_customer): ?>
    <div class="alert alert-info" style="margin-bottom:16px;">
        กำลังแสดงสัญญาของผู้เช่า ID: <?= $filter_customer ?>
        <a href="contracts.php" style="margin-left:10px;">ดูทั้งหมด</a>
    </div>
    <?php endif; ?>

    <div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>ห้อง</th>
                <th>ผู้เช่า</th>
                <th>ค่าเช่า/เดือน</th>
                <th>วันเริ่ม</th>
                <th>วันสิ้นสุด</th>
                <th>ค้ำประกัน</th>
                <th>สถานะ</th>
                <th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($c = $contracts->fetch_assoc()):
            $status_map = [
                'active'     => ['badge-success','ใช้งาน'],
                'ended'      => ['badge-muted',  'สิ้นสุดแล้ว'],
                'terminated' => ['badge-danger',  'ยกเลิก'],
            ];
            [$badge_cls, $badge_txt] = $status_map[$c['status']] ?? ['badge-muted',$c['status']];
        ?>
        <tr>
            <td><?= $c['contract_id'] ?></td>
            <td><strong><?= $c['id_room'] ?></strong><br><small class="text-muted"><?= $c['name_roomtype'] ?></small></td>
            <td><?= $c['name'].' '.$c['lastname'] ?><br><small><?= $c['tel'] ?></small></td>
            <td><?= number_format($c['priceroom'], 0) ?> ฿</td>
            <td><?= $c['first_day'] ? date('d/m/Y', strtotime($c['first_day'])) : date('d/m/Y', strtotime($c['rentdate'])) ?></td>
            <td><?= $c['contract_due'] ? date('d/m/Y', strtotime($c['contract_due'])) : '-' ?></td>
            <td><?= number_format($c['deposit'], 0) ?> ฿</td>
            <td><span class="badge <?= $badge_cls ?>"><?= $badge_txt ?></span></td>
            <td>
                <a href="print_contract.php?id=<?= $c['contract_id'] ?>" target="_blank"
                   class="btn btn-outline btn-sm"><i class="fa-solid fa-print"></i></a>
                <?php if ($c['status'] === 'active'): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันสิ้นสุดสัญญา?')">
                    <input type="hidden" name="contract_id" value="<?= $c['contract_id'] ?>">
                    <input type="hidden" name="room_id" value="<?= $c['id_room'] ?>">
                    <button type="submit" name="end_contract" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-times"></i> สิ้นสุด
                    </button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal: Add Contract -->
<div class="modal-overlay" id="modalAddContract">
<div class="modal" style="max-width:620px;">
    <div class="modal-header">
        <div class="modal-title"><i class="fa-solid fa-file-signature"></i> สร้างสัญญาเช่าใหม่</div>
        <button class="modal-close" onclick="closeModal('modalAddContract')">✕</button>
    </div>
    <form method="POST">
    <div class="modal-body">
        <div class="form-row">
            <div class="form-group">
                <label class="required">ห้องพัก (ว่าง)</label>
                <select name="id_room" required>
                    <option value="">-- เลือกห้อง --</option>
                    <?php while($r=$vacant_rooms->fetch_assoc()): ?>
                    <option value="<?= $r['id_room'] ?>" data-price="<?= $r['rentcost'] ?>">
                        ห้อง <?= $r['id_room'] ?> - <?= $r['name_roomtype'] ?> (<?= number_format($r['rentcost'],0) ?>฿)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="required">ผู้เช่า</label>
                <select name="id_people" required>
                    <option value="">-- เลือกผู้เช่า --</option>
                    <?php while($cu=$all_customers->fetch_assoc()): ?>
                    <option value="<?= $cu['id_people'] ?>"><?= $cu['name'].' '.$cu['lastname'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        <div class="form-row-3">
            <div class="form-group">
                <label class="required">ค่าเช่า (฿/เดือน)</label>
                <input type="number" name="priceroom" id="priceroom_contract" required>
            </div>
            <div class="form-group">
                <label class="required">วันเริ่มเช่า</label>
                <input type="date" name="rentdate" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>ระยะเวลา (เดือน)</label>
                <input type="number" name="duration_months" value="12" min="1">
            </div>
        </div>
        <div class="form-row-3">
            <div class="form-group">
                <label>เงินประกัน (฿)</label>
                <input type="number" name="deposit" value="0">
            </div>
            <div class="form-group">
                <label>ราคาน้ำ/หน่วย</label>
                <input type="number" name="water_unit_price" value="<?= $water_default ?>" step="0.01">
            </div>
            <div class="form-group">
                <label>ราคาไฟ/หน่วย</label>
                <input type="number" name="electric_unit_price" value="<?= $elec_default ?>" step="0.01">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalAddContract')">ยกเลิก</button>
        <button type="submit" name="add_contract" class="btn btn-primary">
            <i class="fa-solid fa-file-circle-plus"></i> สร้างสัญญา
        </button>
    </div>
    </form>
</div>
</div>

<script>
document.querySelector('select[name="id_room"]').addEventListener('change', function() {
    const price = this.options[this.selectedIndex].dataset.price;
    document.getElementById('priceroom_contract').value = price || '';
});
</script>

<?php include '../includes/footer.php'; ?>
