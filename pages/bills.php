<?php
require_once '../config/db.php';
requireLogin();
$page_title = 'จัดการใบแจ้งหนี้';

$msg = $err = '';

// ── 1. ยืนยันการรับเงิน (APPROVE PAYMENT) ──
if (isset($_GET['approve_id'])) {
    $id = (int)$_GET['approve_id'];
    // เปลี่ยนสถานะเป็น paid (ชำระแล้ว)
    $conn->query("UPDATE bill SET status = 'paid' WHERE id_listbill = $id");
    header("Location: bills.php?success=1");
    exit;
}

// ── 2. ลบบิล ──
if (isset($_GET['delete_bill'])) {
    $del_id = (int)$_GET['delete_bill'];
    $conn->query("DELETE FROM payment WHERE id_listbill=$del_id");
    $conn->query("DELETE FROM bill_list WHERE id_listbill=$del_id");
    $conn->query("DELETE FROM bill WHERE id_listbill=$del_id");
    $msg = 'ลบบิลสำเร็จ';
}

// ── 3. สร้างบิลใหม่ ──
if (isset($_POST['create_bill'])) {
    $contract_id    = (int)$_POST['contract_id'];
    $billing_month  = esc($_POST['billing_month']);
    $billingdate    = date('Y-m-d');
    $due_days       = (int)getSetting('bill_due_days') ?: 7;
    $due_date       = date('Y-m-d', strtotime("+$due_days days"));

    $ct = $conn->query("SELECT * FROM contract WHERE contract_id=$contract_id")->fetch_assoc();
    if ($ct) {
        $priceroom   = $ct['priceroom'];
        $id_people   = $ct['id_people'];
        $water_price = $ct['water_unit_price'];
        $elec_price  = $ct['electric_unit_price'];
        $bm = date('Y-m-01', strtotime($billing_month));

        $conn->query("INSERT INTO bill (contract_id, id_people, billing_month, billingdate, due_date, priceroom, ad_id)
            VALUES ($contract_id, '$id_people', '$bm', '$billingdate', '$due_date', $priceroom, {$_SESSION['ad_id']})");
        $bill_id = $conn->insert_id;

        $wp = (float)$_POST['water_prev'];
        $wc = (float)$_POST['water_curr'];
        $ep = (float)$_POST['elec_prev'];
        $ec = (float)$_POST['elec_curr'];
        $wu = max(0, $wc - $wp);
        $eu = max(0, $ec - $ep);
        $wa = $wu * $water_price;
        $ea = $eu * $elec_price;
        $of = (float)($_POST['other_fee'] ?? 0);
        $ofn = esc($_POST['other_fee_note'] ?? '');
        $total = $priceroom + $wa + $ea + $of;

        $conn->query("INSERT INTO bill_list 
            (id_listbill, water_prev_unit, water_curr_unit, water_used, water_unit_price, water_amount,
             electric_prev_unit, electric_curr_unit, electric_used, electric_unit_price, electric_amount,
             other_fee, other_fee_note, total_amount)
            VALUES ($bill_id, $wp, $wc, $wu, $water_price, $wa,
                    $ep, $ec, $eu, $elec_price, $ea,
                    $of, '$ofn', $total)");

        $msg = "สร้างใบแจ้งหนี้สำเร็จ! ยอดรวม " . number_format($total, 2) . " บาท";
    }
}

// ── 4. FILTER ──
$filter_status = esc($_GET['status'] ?? '');
$filter_month  = esc($_GET['month'] ?? '');
$where = "WHERE 1=1";
if ($filter_status) $where .= " AND b.status='$filter_status'";
if ($filter_month)  $where .= " AND DATE_FORMAT(b.billing_month,'%Y-%m')='$filter_month'";

// ── 5. ดึงข้อมูล (แก้ไขเพื่อให้รองรับ TiDB โดยเปลี่ยน Subquery เป็นการ Join ปกติ) ──
$bills = $conn->query("
    SELECT b.*, bl.total_amount, c.name, c.lastname, rn.id_room 
    FROM bill b
    LEFT JOIN bill_list bl ON b.id_listbill = bl.id_listbill
    LEFT JOIN customer c ON b.id_people = c.id_people
    JOIN contract ct ON b.contract_id = ct.contract_id
    JOIN room_number rn ON ct.id_room = rn.id_room
    LEFT JOIN payment p ON p.id_listbill = b.id_listbill
    $where
    ORDER BY b.id_listbill DESC
");

$contracts = $conn->query("
    SELECT ct.*, c.name, c.lastname, rn.id_room
    FROM contract ct
    JOIN customer c ON ct.id_people = c.id_people
    JOIN room_number rn ON ct.id_room = rn.id_room
    WHERE ct.status='active'
    ORDER BY rn.id_room
");

$water_default = getSetting('water_price_per_unit') ?: 18;
$elec_default  = getSetting('electric_price_per_unit') ?: 8;

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<div class="card">
    <div class="card-title d-flex justify-between align-center">
        <span><i class="fa-solid fa-file-invoice-dollar"></i> รายการใบแจ้งหนี้</span>
        <div class="d-flex gap-10">
            <button class="btn btn-outline btn-sm" onclick="printSelected()">
                <i class="fa-solid fa-print"></i> พิมพ์ที่เลือก (4 ใบ/แผ่น)
            </button>
            <button class="btn btn-primary btn-sm" onclick="openModal('modalCreateBill')" style="background-color: #1a5276; border: none; padding: 6px 15px;">
                <span style="color: #ffd700; font-weight: 800; font-size: 16px; margin-right: 4px;">+</span> 
                ออกบิลใหม่
            </button>
        </div>
    </div>

    <form method="GET" style="display:flex;gap:10px;margin-bottom:15px;">
        <select name="status" onchange="this.form.submit()" class="form-control" style="max-width: 150px;">
            <option value="">ทุกสถานะ</option>
            <option value="pending" <?= $filter_status=='pending'?'selected':'' ?>>รอชำระ</option>
            <option value="waiting" <?= $filter_status=='waiting'?'selected':'' ?>>รอตรวจสอบ</option>
            <option value="paid" <?= $filter_status=='paid'?'selected':'' ?>>ชำระแล้ว</option>
        </select>
        <input type="month" name="month" value="<?= $filter_month ?>" onchange="this.form.submit()" class="form-control" style="max-width: 180px;">
        <a href="bills.php" class="btn btn-sm btn-outline" style="padding: 8px 15px;">รีเซ็ต</a>
    </form>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th width="40"><input type="checkbox" id="checkAll"></th>
                    <th>ห้อง</th>
                    <th>ผู้เช่า</th>
                    <th>ยอดรวม</th>
                    <th>สถานะ</th>
                    <th class="text-center" width="240">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($b = $bills->fetch_assoc()): 
                    $badge_style = "badge-warning"; 
                    $status_text = "รอชำระ";
                    
                    if ($b['status'] == 'paid') {
                        $badge_style = "badge-success"; 
                        $status_text = "ชำระแล้ว";
                    } elseif ($b['status'] == 'waiting') {
                        $badge_style = "badge-info"; 
                        $status_text = "รอตรวจสอบ";
                    }
                ?>
                <tr style="<?= $b['status'] == 'paid' ? 'background: #f8fff9;' : ($b['status'] == 'waiting' ? 'background: #f0f8ff;' : '') ?>">
                    <td><input type="checkbox" class="bill-check" value="<?= $b['id_listbill'] ?>"></td>
                    <td><strong><?= $b['id_room'] ?></strong></td>
                    <td><?= $b['name'] ?></td>
                    <td class="fw-bold"><?= number_format($b['total_amount'], 0) ?> ฿</td>
                    <td>
                        <span class="badge <?= $badge_style ?>" style="padding: 6px 12px; border-radius: 20px;">
                            <?= $status_text ?>
                        </span>
                    </td>
                    <td class="text-center">
                    <div class="d-flex gap-5 justify-center">
                        
                        <?php if ($b['status'] == 'waiting'): ?>
                            <?php if(!empty($b['slip_img'])): ?>
                                <a href="../uploads/slips/<?= basename($b['slip_img']) ?>" target="_blank" class="btn btn-sm" style="background-color: #f1c40f; color: #fff; border: none;" title="ดูรูปสลิป">
                                    <i class="fa-solid fa-image"></i> ดูสลิป
                                </a>
                            <?php endif; ?>
                            
                            <a href="?approve_id=<?= $b['id_listbill'] ?>" class="btn btn-sm btn-success" onclick="return confirm('ตรวจสอบสลิปและเงินในบัญชีเรียบร้อยแล้วใช่หรือไม่?')" title="ยืนยันยอดเงิน">
                                <i class="fa-solid fa-check"></i> รับเงิน
                            </a>

                        <?php elseif ($b['status'] !== 'paid'): ?>
                            <a href="?approve_id=<?= $b['id_listbill'] ?>" class="btn btn-sm btn-outline" style="color: #27ae60; border-color: #27ae60;" onclick="return confirm('ยืนยันการรับชำระเงิน? (สำหรับกรณีลูกค้าจ่ายเงินสด)')" title="รับเงินสด">
                                <i class="fa-solid fa-hand-holding-dollar"></i> เงินสด
                            </a>
                        <?php endif; ?>
                        
                        <a href="print_bill.php?id=<?= $b['id_listbill'] ?>" target="_blank" class="btn btn-sm btn-outline" title="พิมพ์">
                            <i class="fa-solid fa-print"></i>
                        </a>
                        
                        <a href="?delete_bill=<?= $b['id_listbill'] ?>" 
                        class="btn btn-sm" 
                        style="background-color: #fff5f5; color: #e53e3e; border: 1px solid #feb2b2; padding: 5px 10px;" 
                        onclick="return confirm('คุณต้องการลบบิลนี้ใช่หรือไม่?')" title="ลบ">
                            <i class="fa-regular fa-trash-can"></i>
                        </a>
                    </div>
                </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modalCreateBill">
<div class="modal" style="max-width:600px;">
    <div class="modal-header">
        <div class="modal-title">ออกใบแจ้งหนี้</div>
        <button class="modal-close" onclick="closeModal('modalCreateBill')">✕</button>
    </div>
    <form method="POST">
        <div class="modal-body">
            <div class="form-group">
                <label>เลือกห้อง</label>
                <select name="contract_id" id="sel_contract" required onchange="loadContractInfo(this)" class="form-control">
                    <option value="">-- เลือกห้อง --</option>
                    <?php 
                    $contracts->data_seek(0);
                    while($c=$contracts->fetch_assoc()): ?>
                    <option value="<?= $c['contract_id'] ?>" data-price="<?= $c['priceroom'] ?>">
                        ห้อง <?= $c['id_room'] ?> - <?= $c['name'] ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>เดือน</label>
                    <input type="month" name="billing_month" value="<?= date('Y-m') ?>" required class="form-control">
                </div>
                <div class="form-group">
                    <label>ค่าเช่า</label>
                    <input type="number" id="priceroom" readonly class="form-control" style="background:#f5f5f5;">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>น้ำ (ก่อน-หลัง)</label>
                    <div style="display:flex; gap:5px;">
                        <input type="number" name="water_prev" id="water_prev" value="0" oninput="calcBill()" class="form-control">
                        <input type="number" name="water_curr" id="water_curr" value="0" oninput="calcBill()" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>ไฟ (ก่อน-หลัง)</label>
                    <div style="display:flex; gap:5px;">
                        <input type="number" name="elec_prev" id="elec_prev" value="0" oninput="calcBill()" class="form-control">
                        <input type="number" name="elec_curr" id="elec_curr" value="0" oninput="calcBill()" class="form-control">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>ค่าอื่นๆ</label>
                <input type="number" name="other_fee" id="other_fee" value="0" oninput="calcBill()" class="form-control">
            </div>
            <div style="background:#1a5276; color:#fff; padding:20px; border-radius:8px; text-align:center;">
                <div style="font-size: 14px; opacity: 0.8; margin-bottom: 5px;">ยอดรวมที่ต้องชำระ</div>
                <span id="total_display" style="font-size:32px; font-weight:800;">0</span> <span style="font-size: 20px;">฿</span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="submit" name="create_bill" class="btn btn-primary" style="background-color: #1a5276; border: none; padding: 10px 25px; font-weight: bold;">ยืนยันออกบิล</button>
        </div>
    </form>
</div>
</div>

<script>
function loadContractInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('priceroom').value = opt.dataset.price || 0;
    calcBill();
}
function calcBill() {
    const price = parseFloat(document.getElementById('priceroom').value) || 0;
    const w = (parseFloat(document.getElementById('water_curr').value) - parseFloat(document.getElementById('water_prev').value)) * <?= $water_default ?>;
    const e = (parseFloat(document.getElementById('elec_curr').value) - parseFloat(document.getElementById('elec_prev').value)) * <?= $elec_default ?>;
    const o = parseFloat(document.getElementById('other_fee').value) || 0;
    const total = price + Math.max(0, w) + Math.max(0, e) + o;
    document.getElementById('total_display').innerText = total.toLocaleString();
}
function printSelected() {
    let ids = [];
    document.querySelectorAll('.bill-check:checked').forEach(c => ids.push(c.value));
    if(ids.length == 0) return alert('กรุณาเลือกบิลอย่างน้อย 1 ใบก่อนพิมพ์');
    window.open('print_bill_4.php?ids=' + ids.join(','), '_blank');
}
document.getElementById('checkAll').onclick = function() {
    document.querySelectorAll('.bill-check').forEach(c => c.checked = this.checked);
}
</script>

<?php include '../includes/footer.php'; ?>
