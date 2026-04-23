<?php
require_once '../config/db.php';
requireLogin();
$page_title = 'ผู้เช่า';

$msg = $err = '';

// Add customer
if (isset($_POST['add_customer'])) {
    $id   = esc($_POST['id_people']);
    $name = esc($_POST['name']);
    $last = esc($_POST['lastname']);
    $tel  = esc($_POST['tel']);
    $email= esc($_POST['email'] ?? '');
    $line = esc($_POST['line_id'] ?? '');
    $emtel= esc($_POST['emergency_tel'] ?? '');

    if ($conn->query("INSERT INTO customer (id_people,name,lastname,tel,email,line_id,emergency_tel) 
        VALUES ('$id','$name','$last','$tel','$email','$line','$emtel')")) {
        $msg = 'เพิ่มข้อมูลผู้เช่าสำเร็จ';
    } else {
        $err = 'เลขบัตรประชาชนนี้มีในระบบแล้ว';
    }
}

// Delete customer (ถ้าไม่มีสัญญา)
if (isset($_GET['del']) && is_numeric($_GET['del'])) {
    // skip for safety
}

$search = esc($_GET['q'] ?? '');
$where  = $search ? "WHERE c.name LIKE '%$search%' OR c.lastname LIKE '%$search%' OR c.tel LIKE '%$search%'" : '';

$customers = $conn->query("
    SELECT c.*, 
           COUNT(ct.contract_id) as contract_count,
           SUM(CASE WHEN ct.status='active' THEN 1 ELSE 0 END) as active_contracts,
           GROUP_CONCAT(rn.id_room ORDER BY rn.id_room SEPARATOR ', ') as rooms
    FROM customer c
    LEFT JOIN contract ct ON c.id_people = ct.id_people
    LEFT JOIN room_number rn ON ct.id_room = rn.id_room AND ct.status='active'
    $where
    GROUP BY c.id_people
    ORDER BY c.created_at DESC
");

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fa fa-times"></i> <?= $err ?></div><?php endif; ?>

<div class="card">
    <div class="card-title d-flex justify-between align-center">
        <span><i class="fa-solid fa-users"></i> รายชื่อผู้เช่า</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('modalAddCustomer')">
            <i class="fa-solid fa-user-plus"></i> เพิ่มผู้เช่า
        </button>
    </div>

    <form method="GET" style="margin-bottom:16px;display:flex;gap:10px;">
        <input type="text" name="q" value="<?= htmlspecialchars($_GET['q']??'') ?>" placeholder="🔍 ค้นหาชื่อ, นามสกุล, เบอร์โทร">
        <button type="submit" class="btn btn-primary btn-sm">ค้นหา</button>
        <?php if ($search): ?><a href="customers.php" class="btn btn-outline btn-sm">รีเซ็ต</a><?php endif; ?>
    </form>

    <div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>เลขบัตรประชาชน</th>
                <th>ชื่อ-นามสกุล</th>
                <th>เบอร์โทร</th>
                <th>Line</th>
                <th>ห้องปัจจุบัน</th>
                <th>สัญญา</th>
                <th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($c = $customers->fetch_assoc()): ?>
        <tr>
            <td><?= $c['id_people'] ?></td>
            <td><strong><?= $c['name'].' '.$c['lastname'] ?></strong></td>
            <td><a href="tel:<?= $c['tel'] ?>"><?= $c['tel'] ?></a></td>
            <td><?= $c['line_id'] ?: '-' ?></td>
            <td>
                <?php if ($c['rooms']): ?>
                    <span class="badge badge-warning">ห้อง <?= $c['rooms'] ?></span>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </td>
            <td><?= $c['contract_count'] ?> สัญญา</td>
            <td>
                <a href="contracts.php?customer=<?= urlencode($c['id_people']) ?>"
                   class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-file-contract"></i> สัญญา
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Modal: Add Customer -->
<div class="modal-overlay" id="modalAddCustomer">
<div class="modal">
    <div class="modal-header">
        <div class="modal-title"><i class="fa-solid fa-user-plus"></i> เพิ่มผู้เช่าใหม่</div>
        <button class="modal-close" onclick="closeModal('modalAddCustomer')">✕</button>
    </div>
    <form method="POST">
    <div class="modal-body">
        <div class="form-group">
            <label class="required">เลขบัตรประชาชน (13 หลัก)</label>
            <input type="text" name="id_people" maxlength="13" pattern="[0-9]{13}" required placeholder="1234567890123">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="required">ชื่อ</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label class="required">นามสกุล</label>
                <input type="text" name="lastname" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="required">เบอร์โทร</label>
                <input type="tel" name="tel" required>
            </div>
            <div class="form-group">
                <label>Line ID</label>
                <input type="text" name="line_id">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>อีเมล</label>
                <input type="email" name="email">
            </div>
            <div class="form-group">
                <label>เบอร์ฉุกเฉิน</label>
                <input type="tel" name="emergency_tel">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalAddCustomer')">ยกเลิก</button>
        <button type="submit" name="add_customer" class="btn btn-primary">บันทึก</button>
    </div>
    </form>
</div>
</div>

<?php include '../includes/footer.php'; ?>
