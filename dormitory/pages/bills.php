<?php
require_once '../config/db.php';
requireLogin();
$page_title = 'ใบแจ้งหนี้';

$msg = $err = '';

// ── CREATE BILL ──
if (isset($_POST['create_bill'])) {
    $contract_id    = (int)$_POST['contract_id'];
    $billing_month  = esc($_POST['billing_month']);
    $billingdate    = date('Y-m-d');
    $due_days       = (int)getSetting('bill_due_days') ?: 7;
    $due_date       = date('Y-m-d', strtotime("+$due_days days"));

    // Get contract info
    $ct = $conn->query("SELECT * FROM contract WHERE contract_id=$contract_id")->fetch_assoc();
    if ($ct) {
        $priceroom   = $ct['priceroom'];
        $id_people   = $ct['id_people'];
        $water_price = $ct['water_unit_price'];
        $elec_price  = $ct['electric_unit_price'];

        // Billing month as first of month
        $bm = date('Y-m-01', strtotime($billing_month));

        // Insert bill
        $conn->query("INSERT INTO bill (contract_id, id_people, billing_month, billingdate, due_date, priceroom, ad_id)
            VALUES ($contract_id, '$id_people', '$bm', '$billingdate', '$due_date', $priceroom, {$_SESSION['ad_id']})");
        $bill_id = $conn->insert_id;

        // Insert bill_list
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

// ── DELETE BILL (ส่วนที่เพิ่มใหม่) ──
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    // ตรวจสอบก่อนว่าบิลนี้จ่ายหรือยัง (เพื่อความปลอดภัย)
    $check = $conn->query("SELECT status FROM bill WHERE id_listbill = $delete_id")->fetch_assoc();
    
    if ($check && $check['status'] !== 'paid') {
        // ลบข้อมูลใน bill_list ก่อน
        $conn->query("DELETE FROM bill_list WHERE id_listbill = $delete_id");
        // ลบข้อมูลใน bill
        $res = $conn->query("DELETE FROM bill WHERE id_listbill = $delete_id");
        
        if ($res) {
            $msg = "ลบใบแจ้งหนี้รหัส #$delete_id เรียบร้อยแล้ว";
        } else {
            $err = "เกิดข้อผิดพลาดในการลบข้อมูล";
        }
    } else if ($check && $check['status'] === 'paid') {
        $err = "ไม่สามารถลบบิลที่ชำระเงินแล้วได้";
    }
}

// ── FILTER ──
$filter_status = esc($_GET['status'] ?? '');
$filter_month  = esc($_GET['month'] ?? '');
$where = "WHERE 1=1";
if ($filter_status) $where .= " AND b.status='$filter_status'";
if ($filter_month)  $where .= " AND DATE_FORMAT(b.billing_month,'%Y-%m')='$filter_month'";

// ── Update overdue status ──
$conn->query("UPDATE bill SET status='overdue' WHERE status='pending' AND due_date < CURDATE()");

// Bills list
$bills = $conn->query("
    SELECT b.*, bl.total_amount, c.name, c.lastname, rn.id_room,
            COALESCE(p.paid_amount,0) as paid_amount
    FROM bill b
    LEFT JOIN bill_list bl ON b.id_listbill = bl.id_listbill
    LEFT JOIN customer c ON b.id_people = c.id_people
    LEFT JOIN contract ct ON b.contract_id = ct.contract_id
    LEFT JOIN room_number rn ON ct.id_room = rn.id_room
    LEFT JOIN payment p ON b.id_listbill = p.id_listbill
    $where
    ORDER BY b.billing_month DESC, b.id_listbill DESC
");

// Active contracts for new bill form
$contracts = $conn->query("
    SELECT ct.*, c.name, c.lastname, rn.id_room, rt.name_roomtype
    FROM contract ct
    JOIN customer c ON ct.id_people = c.id_people
    JOIN room_number rn ON ct.id_room = rn.id_room
    JOIN room_type rt ON rn.id_roomtype = rt.id_roomtype
    WHERE ct.status='active'
    ORDER BY rn.id_room
");

$water_default = getSetting('water_price_per_unit') ?: 18;
$elec_default  = getSetting('electric_price_per_unit') ?: 8;

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fa fa-times"></i> <?= $err ?></div><?php endif; ?>

<div class="card">
    <div class="card-title d-flex justify-between align-center">
        <span><i class="fa-solid fa-file-invoice-dollar"></i> รายการใบแจ้งหนี้</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('modalCreateBill')">
            <i class="fa-solid fa-plus"></i> ออกบิลใหม่
        </button>
    </div>

    <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
        <select name="status" onchange="this.form.submit()">
            <option value="">ทุกสถานะ</option>
            <option value="pending"  <?= $filter_status=='pending'  ?'selected':'' ?>>รอชำระ</option>
            <option value="paid"     <?= $filter_status=='paid'     ?'selected':'' ?>>ชำระแล้ว</option>
            <option value="overdue"  <?= $filter_status=='overdue'  ?'selected':'' ?>>ค้างชำระ</option>
        </select>
        <input type="month" name="month" value="<?= $filter_month ?>" onchange="this.form.submit()">
        <a href="bills.php" class="btn btn-outline btn-sm">รีเซ็ต</a>
    </form>

    <div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>ห้อง</th>
                <th>ผู้เช่า</th>
                <th>เดือน</th>
                <th>ค่าเช่า</th>
                <th>ยอดรวม</th>
                <th>กำหนดชำระ</th>
                <th>สถานะ</th>
                <th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($b = $bills->fetch_assoc()):
            $status_map = [
                'pending' => ['badge-warning','รอชำระ'],
                'paid'    => ['badge-success','ชำระแล้ว'],
                'overdue' => ['badge-danger', 'ค้างชำระ'],
            ];
            [$badge_cls, $badge_txt] = $status_map[$b['status']] ?? ['badge-muted',$b['status']];
        ?>
        <tr>
            <td><?= $b['id_listbill'] ?></td>
            <td><strong><?= $b['id_room'] ?></strong></td>
            <td><?= $b['name'].' '.$b['lastname'] ?></td>
            <td><?= date('M Y', strtotime($b['billing_month'])) ?></td>
            <td><?= number_format($b['priceroom'], 0) ?> ฿</td>
            <td class="fw-bold"><?= $b['total_amount'] ? number_format($b['total_amount'], 0).' ฿' : '-' ?></td>
            <td><?= date('d/m/Y', strtotime($b['due_date'])) ?></td>
            <td><span class="badge <?= $badge_cls ?>"><?= $badge_txt ?></span></td>
            <td style="white-space:nowrap;">
                <a href="print_bill.php?id=<?= $b['id_listbill'] ?>" target="_blank"
                   class="btn btn-outline btn-sm"><i class="fa-solid fa-print"></i></a>
                
                <?php if ($b['status'] !== 'paid'): ?>
                <a href="payments.php?bill_id=<?= $b['id_listbill'] ?>"
                   class="btn btn-success btn-sm" title="รับชำระ"><i class="fa-solid fa-check"></i></a>
                
                <a href="bills.php?delete_id=<?= $b['id_listbill'] ?>" 
                   class="btn btn-danger btn-sm" 
                   onclick="return confirm('ยืนยันการลบบิลใบนี้? ข้อมูลค่าใช้จ่ายจะหายไปทั้งหมด')" title="ลบ">
                   <i class="fa-solid fa-trash"></i>
                </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="modal-overlay" id="modalCreateBill">
<div class="modal" style="max-width:700px;">
    <div class="modal-header">
        <div class="modal-title"><i class="fa-solid fa-file-invoice"></i> ออกใบแจ้งหนี้</div>
        <button class="modal-close" onclick="closeModal('modalCreateBill')">✕</button>
    </div>
    <form method="POST">
    <div class="modal-body">
        <div class="form-group">
            <label class="required">ห้อง / สัญญาเช่า</label>
            <select name="contract_id" id="sel_contract" required onchange="loadContractInfo(this)">
                <option value="">-- เลือกห้อง --</option>
                <?php 
                $contracts->data_seek(0); // รีเซ็ตตัวชี้ข้อมูลเพื่อให้วนลูปใหม่ได้
                while($c=$contracts->fetch_assoc()): 
                ?>
                <option value="<?= $c['contract_id'] ?>"
                        data-price="<?= $c['priceroom'] ?>"
                        data-water="<?= $water_default ?>"
                        data-elec="<?= $elec_default ?>">
                    ห้อง <?= $c['id_room'] ?> - <?= $c['name'].' '.$c['lastname'] ?> (<?= number_format($c['priceroom'],0) ?> ฿)
                </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="required">เดือนที่เก็บ</label>
                <input type="month" name="billing_month" value="<?= date('Y-m') ?>" required>
            </div>
            <div class="form-group">
                <label>ค่าเช่า (฿)</label>
                <input type="number" id="priceroom" value="" readonly style="background:#f5f5f5;">
            </div>
        </div>

        <div class="divider"></div>
        <div style="font-weight:700;margin-bottom:12px;color:var(--primary);">💧 ค่าน้ำ</div>

        <div class="form-row-3">
            <div class="form-group">
                <label>หน่วยเดือนก่อน</label>
                <input type="number" name="water_prev" id="water_prev" value="0" oninput="calcBill()">
            </div>
            <div class="form-group">
                <label>หน่วยเดือนนี้</label>
                <input type="number" name="water_curr" id="water_curr" value="0" oninput="calcBill()">
            </div>
            <div class="form-group">
                <label>ราคาต่อหน่วย</label>
                <input type="number" id="water_unit_price" name="water_unit_price_display"
                       value="<?= $water_default ?>" oninput="calcBill()" step="0.01" readonly>
            </div>
        </div>

        <div class="divider"></div>
        <div style="font-weight:700;margin-bottom:12px;color:var(--primary);">⚡ ค่าไฟ</div>

        <div class="form-row-3">
            <div class="form-group">
                <label>หน่วยเดือนก่อน</label>
                <input type="number" name="elec_prev" id="elec_prev" value="0" oninput="calcBill()">
            </div>
            <div class="form-group">
                <label>หน่วยเดือนนี้</label>
                <input type="number" name="elec_curr" id="elec_curr" value="0" oninput="calcBill()">
            </div>
            <div class="form-group">
                <label>ราคาต่อหน่วย</label>
                <input type="number" id="elec_unit_price" name="elec_unit_price_display"
                       value="<?= $elec_default ?>" oninput="calcBill()" step="0.01" readonly>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>ค่าใช้จ่ายอื่นๆ (฿)</label>
                <input type="number" name="other_fee" id="other_fee" value="0" oninput="calcBill()" step="0.01">
            </div>
            <div class="form-group">
                <label>หมายเหตุ</label>
                <input type="text" name="other_fee_note" id="other_fee_note" placeholder="ระบุรายการ...">
            </div>
        </div>

        <div style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border-radius:12px;padding:20px;color:#fff;text-align:center;">
            <div style="font-size:13px;opacity:0.8;margin-bottom:4px;">ยอดรวมทั้งสิ้น</div>
            <div style="font-size:32px;font-weight:800;" id="total_display">0.00</div>
            <div style="font-size:12px;opacity:0.7;">บาท</div>
            <input type="hidden" name="total_amount" id="total_amount" value="0">
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalCreateBill')">ยกเลิก</button>
        <button type="submit" name="create_bill" class="btn btn-primary">
            <i class="fa-solid fa-file-circle-plus"></i> ออกใบแจ้งหนี้
        </button>
    </div>
    </form>
</div>
</div>

<script>
function loadContractInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('priceroom').value = opt.dataset.price || '';
    document.getElementById('water_unit_price').value = opt.dataset.water || <?= $water_default ?>;
    document.getElementById('elec_unit_price').value  = opt.dataset.elec  || <?= $elec_default ?>;
    calcBill();
}

function calcBill() {
    const waterPrev  = parseFloat(document.getElementById('water_prev').value) || 0;
    const waterCurr  = parseFloat(document.getElementById('water_curr').value) || 0;
    const waterPrice = parseFloat(document.getElementById('water_unit_price').value) || 0;
    const elecPrev   = parseFloat(document.getElementById('elec_prev').value) || 0;
    const elecCurr   = parseFloat(document.getElementById('elec_curr').value) || 0;
    const elecPrice  = parseFloat(document.getElementById('elec_unit_price').value) || 0;
    const roomCost   = parseFloat(document.getElementById('priceroom').value) || 0;
    const other      = parseFloat(document.getElementById('other_fee').value) || 0;

    const waterUsed = Math.max(0, waterCurr - waterPrev);
    const elecUsed  = Math.max(0, elecCurr - elecPrev);
    const waterAmt  = waterUsed * waterPrice;
    const elecAmt   = elecUsed * elecPrice;
    const total     = roomCost + waterAmt + elecAmt + other;

    document.getElementById('total_amount').value = total.toFixed(2);
    document.getElementById('total_display').textContent = 
        total.toLocaleString('th-TH', {minimumFractionDigits: 2});
}

document.addEventListener('DOMContentLoaded', function() {
    ['water_prev','water_curr','elec_prev','elec_curr','other_fee'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', calcBill);
            el.addEventListener('keyup', calcBill);
        }
    });
});
</script>
<?php include '../includes/footer.php'; ?>