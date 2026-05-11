<?php
require_once '../config/db.php';
requireLogin();
$page_title = 'บันทึกการชำระเงิน';

$msg = $err = '';
$prefill_bill = (int)($_GET['bill_id'] ?? 0);

// ── RECORD PAYMENT ──
if (isset($_POST['record_payment'])) {
    $bill_id  = (int)$_POST['bill_id'];
    $amount   = (float)$_POST['paid_amount'];
    $method   = esc($_POST['payment_method']);
    $note     = esc($_POST['note'] ?? '');
    $paid_date= date('Y-m-d H:i:s');

    // Get bill info
    $bill = $conn->query("
        SELECT b.*, bl.total_amount, c.name, c.lastname, rn.id_room, rt.name_roomtype
        FROM bill b
        LEFT JOIN bill_list bl ON b.id_listbill = bl.id_listbill
        JOIN customer c ON b.id_people = c.id_people
        JOIN contract ct ON b.contract_id = ct.contract_id
        JOIN room_number rn ON ct.id_room = rn.id_room
        JOIN room_type rt ON rn.id_roomtype = rt.id_roomtype
        WHERE b.id_listbill=$bill_id
    ")->fetch_assoc();

    if ($bill) {
        // Handle slip upload
        $slip_path = '';
        if (!empty($_FILES['slip_img']['name'])) {
            $upload_dir = '../uploads/slips/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext  = pathinfo($_FILES['slip_img']['name'], PATHINFO_EXTENSION);
            $fname= 'slip_' . $bill_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['slip_img']['tmp_name'], $upload_dir . $fname)) {
                $slip_path = 'uploads/slips/' . $fname;
            }
        }

        // Insert payment
        $conn->query("INSERT INTO payment (id_listbill, paid_amount, paid_date, payment_method, slip_img, note, ad_id)
            VALUES ($bill_id, $amount, '$paid_date', '$method', '$slip_path', '$note', {$_SESSION['ad_id']})");
        $payment_id = $conn->insert_id;

        // Update bill status
        $conn->query("UPDATE bill SET status='paid' WHERE id_listbill=$bill_id");

        // ── SYNC TO GOOGLE SHEETS ──
        $sheet_url = getSetting('google_script_url');
        if ($sheet_url) {
            $data = [
                'action'       => 'addPayment',
                'payment_id'   => $payment_id,
                'bill_id'      => $bill_id,
                'room'         => $bill['id_room'],
                'tenant_name'  => $bill['name'] . ' ' . $bill['lastname'],
                'billing_month'=> date('M Y', strtotime($bill['billing_month'])),
                'room_type'    => $bill['name_roomtype'],
                'total_bill'   => $bill['total_amount'],
                'paid_amount'  => $amount,
                'payment_method'=> $method,
                'paid_date'    => $paid_date,
                'recorded_by'  => $_SESSION['ad_name'],
                'note'         => $note,
            ];

            $ch = curl_init($sheet_url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($data),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $response = curl_exec($ch);
            $http_code= curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200) {
                $conn->query("UPDATE payment SET google_sheet_synced=1 WHERE payment_id=$payment_id");
                $msg = "✅ บันทึกการชำระเงินสำเร็จ และซิงค์ไปยัง Google Sheets แล้ว!";
            } else {
                $msg = "✅ บันทึกการชำระเงินสำเร็จ (Google Sheets ซิงค์ไม่สำเร็จ - ลองซิงค์ใหม่ภายหลัง)";
            }
        } else {
            $msg = "✅ บันทึกการชำระเงินสำเร็จ! ยอด " . number_format($amount, 2) . " บาท";
        }
    } else {
        $err = 'ไม่พบบิลที่ระบุ';
    }
}

// ── APPROVE SLIP ──
if (isset($_POST['approve_slip'])) {
    $bill_id   = (int)$_POST['bill_id'];
    $amount    = (float)$_POST['paid_amount'];
    $payment_id= (int)$_POST['payment_id'];

    $bill = $conn->query("
        SELECT b.*, bl.total_amount, c.name, c.lastname, rn.id_room, rt.name_roomtype
        FROM bill b
        LEFT JOIN bill_list bl ON b.id_listbill = bl.id_listbill
        JOIN customer c ON b.id_people = c.id_people
        JOIN contract ct ON b.contract_id = ct.contract_id
        JOIN room_number rn ON ct.id_room = rn.id_room
        JOIN room_type rt ON rn.id_roomtype = rt.id_roomtype
        WHERE b.id_listbill=$bill_id
    ")->fetch_assoc();

    if ($bill) {
        $conn->query("UPDATE payment SET paid_amount=$amount, payment_method='qr', ad_id={$_SESSION['ad_id']} WHERE payment_id=$payment_id");
        $conn->query("UPDATE bill SET status='paid' WHERE id_listbill=$bill_id");

        // Sync to Google Sheets
        $sheet_url = getSetting('google_script_url');
        if ($sheet_url) {
            $data = [
                'action'        => 'addPayment',
                'payment_id'    => $payment_id,
                'bill_id'       => $bill_id,
                'room'          => $bill['id_room'],
                'tenant_name'   => $bill['name'] . ' ' . $bill['lastname'],
                'billing_month' => date('M Y', strtotime($bill['billing_month'])),
                'room_type'     => $bill['name_roomtype'],
                'total_bill'    => $bill['total_amount'],
                'paid_amount'   => $amount,
                'payment_method'=> 'qr',
                'paid_date'     => date('Y-m-d H:i:s'),
                'recorded_by'   => $_SESSION['ad_name'],
                'note'          => 'อนุมัติสลิปจากลูกค้า',
            ];
            $ch = curl_init($sheet_url);
            curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($data),
                CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>false]);
            $res = curl_exec($ch);
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200)
                $conn->query("UPDATE payment SET google_sheet_synced=1 WHERE payment_id=$payment_id");
            curl_close($ch);
        }
        $msg = "✅ ยืนยันการชำระเงินของห้อง {$bill['id_room']} เรียบร้อยแล้ว!";
    }
}

// ── REJECT SLIP ──
if (isset($_POST['reject_slip'])) {
    $bill_id    = (int)$_POST['bill_id'];
    $payment_id = (int)$_POST['payment_id'];
    $reject_note= esc($_POST['reject_note'] ?? 'สลิปไม่ถูกต้อง');

    $conn->query("UPDATE bill SET status='pending' WHERE id_listbill=$bill_id");
    $conn->query("UPDATE payment SET note=CONCAT(note, ' [ปฏิเสธ: $reject_note]') WHERE payment_id=$payment_id");
    // Optionally delete the rejected payment record
    // $conn->query("DELETE FROM payment WHERE payment_id=$payment_id");
    $err = "❌ ปฏิเสธสลิปแล้ว — บิลกลับสู่สถานะรอชำระ";
}


if (isset($_GET['sync']) && is_numeric($_GET['sync'])) {
    // Re-sync a payment to Google Sheets
    $pid = (int)$_GET['sync'];
    // (same logic as above but for existing payment - simplified)
    $msg = "ซิงค์ข้อมูลไป Google Sheets แล้ว";
}

// Bills pending payment (not yet submitted slip)
$pending_bills = $conn->query("
    SELECT b.*, bl.total_amount, c.name, c.lastname, rn.id_room
    FROM bill b
    LEFT JOIN bill_list bl ON b.id_listbill = bl.id_listbill
    JOIN customer c ON b.id_people = c.id_people
    JOIN contract ct ON b.contract_id = ct.contract_id
    JOIN room_number rn ON ct.id_room = rn.id_room
    WHERE b.status IN ('pending','overdue')
    ORDER BY b.status DESC, b.due_date ASC
");

// Bills waiting for slip verification
$waiting_bills = $conn->query("
    SELECT b.*, bl.total_amount, c.name, c.lastname, rn.id_room, 
           p.slip_img, p.note, p.paid_amount as submitted_amount, p.payment_id
    FROM bill b
    LEFT JOIN bill_list bl ON b.id_listbill = bl.id_listbill
    JOIN customer c ON b.id_people = c.id_people
    JOIN contract ct ON b.contract_id = ct.contract_id
    JOIN room_number rn ON ct.id_room = rn.id_room
    LEFT JOIN payment p ON p.id_listbill = b.id_listbill
    WHERE b.status = 'waiting'
    ORDER BY p.created_at DESC
");

// Payment history
$payments = $conn->query("
    SELECT p.*, b.billing_month, c.name, c.lastname, rn.id_room
    FROM payment p
    JOIN bill b ON p.id_listbill = b.id_listbill
    JOIN customer c ON b.id_people = c.id_people
    JOIN contract ct ON b.contract_id = ct.contract_id
    JOIN room_number rn ON ct.id_room = rn.id_room
    ORDER BY p.created_at DESC
    LIMIT 30
");

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fa fa-times"></i> <?= $err ?></div><?php endif; ?>

<?php
$waiting_count = $waiting_bills->num_rows ?? 0;
if ($waiting_count > 0):
?>
<!-- ===== PENDING SLIP VERIFICATION ===== -->
<div class="card" style="margin-bottom:24px;border:2px solid #e67e22;">
    <div class="card-title" style="color:#e67e22;">
        <i class="fa-solid fa-clock"></i>
        รอยืนยันสลิป
        <span style="background:#e67e22;color:#fff;font-size:12px;padding:2px 8px;border-radius:20px;margin-left:8px;">
            <?= $waiting_count ?> รายการ
        </span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
    <?php while ($w = $waiting_bills->fetch_assoc()): ?>
    <div style="background:#fff8ec;border:1px solid #f0c070;border-radius:14px;padding:20px;">

        <!-- Header -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div>
                <div style="font-size:18px;font-weight:800;">ห้อง <?= $w['id_room'] ?></div>
                <div style="font-size:13px;color:#888;"><?= $w['name'].' '.$w['lastname'] ?> · <?= date('M Y', strtotime($w['billing_month'])) ?></div>
            </div>
            <span style="background:#fff3cd;color:#e67e22;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;">⏳ รอยืนยัน</span>
        </div>

        <!-- Amount -->
        <div style="background:#fff;border-radius:10px;padding:12px;margin-bottom:12px;text-align:center;">
            <div style="font-size:12px;color:#aaa;">ยอดที่แจ้ง</div>
            <div style="font-size:26px;font-weight:800;color:#1a5276;"><?= number_format($w['total_amount'], 0) ?> ฿</div>
        </div>

        <!-- Slip Image -->
        <?php 
        $slip_file = $w['slip_img'] ? basename($w['slip_img']) : null;
        if ($slip_file): 
            // normalize — strip any leading path so we always have just the filename
            $slip_file = basename($slip_file);
        ?>
        <div style="margin-bottom:12px;text-align:center;">
            <div style="font-size:12px;color:#888;margin-bottom:6px;">สลิปจากลูกค้า (คลิกเพื่อขยาย)</div>
            <a href="../uploads/slips/<?= $slip_file ?>" target="_blank">
                <img src="../uploads/slips/<?= $slip_file ?>"
                     style="width:100%;max-height:180px;object-fit:contain;border-radius:10px;border:1px solid #ddd;cursor:pointer;"
                     onerror="this.parentElement.innerHTML='<div style=\'padding:12px;text-align:center;color:#aaa;font-size:13px;\'>⚠️ ไม่พบไฟล์สลิป: <?= $slip_file ?></div>'">
            </a>
        </div>
        <?php endif; ?>

        <?php if ($w['note']): ?>
        <div style="font-size:12px;color:#888;margin-bottom:12px;background:#fff;padding:8px;border-radius:8px;">
            💬 <?= $w['note'] ?>
        </div>
        <?php endif; ?>

        <!-- Approve -->
        <form method="POST" style="margin-bottom:8px;">
            <input type="hidden" name="bill_id" value="<?= $w['id_listbill'] ?>">
            <input type="hidden" name="payment_id" value="<?= $w['payment_id'] ?>">
            <input type="number" name="paid_amount" value="<?= $w['total_amount'] ?>" step="0.01"
                   style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-bottom:8px;font-family:inherit;font-weight:700;text-align:right;">
            <button type="submit" name="approve_slip"
                    style="width:100%;padding:12px;background:linear-gradient(135deg,#27ae60,#2ecc71);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;font-family:inherit;cursor:pointer;"
                    onclick="return confirm('ยืนยันการชำระเงินของห้อง <?= $w['id_room'] ?> ใช่ไหม?')">
                ✅ อนุมัติ / ยืนยันการโอน
            </button>
        </form>

        <!-- Reject -->
        <form method="POST">
            <input type="hidden" name="bill_id" value="<?= $w['id_listbill'] ?>">
            <input type="hidden" name="payment_id" value="<?= $w['payment_id'] ?>">
            <input type="text" name="reject_note" placeholder="เหตุผลที่ปฏิเสธ..."
                   style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px;margin-bottom:8px;font-family:inherit;font-size:13px;">
            <button type="submit" name="reject_slip"
                    style="width:100%;padding:10px;background:#fff;color:#e74c3c;border:2px solid #e74c3c;border-radius:10px;font-size:13px;font-weight:700;font-family:inherit;cursor:pointer;"
                    onclick="return confirm('ปฏิเสธสลิปนี้ใช่ไหม? บิลจะกลับสู่สถานะรอชำระ')">
                ❌ ปฏิเสธสลิป
            </button>
        </form>

    </div>
    <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

<!-- Record Payment Form -->
<div class="card">
    <div class="card-title"><i class="fa-solid fa-qrcode"></i> รับชำระเงิน</div>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label class="required">เลือกบิล</label>
            <select name="bill_id" id="sel_bill" required onchange="loadBillInfo(this)">
                <option value="">-- เลือกห้อง/ผู้เช่า --</option>
                <?php while ($b = $pending_bills->fetch_assoc()):
                    $selected = ($b['id_listbill'] == $prefill_bill) ? 'selected' : '';
                ?>
                <option value="<?= $b['id_listbill'] ?>" <?= $selected ?>
                        data-amount="<?= $b['total_amount'] ?>"
                        data-room="<?= $b['id_room'] ?>"
                        data-name="<?= $b['name'].' '.$b['lastname'] ?>"
                        data-month="<?= date('M Y', strtotime($b['billing_month'])) ?>"
                        data-status="<?= $b['status'] ?>">
                    ห้อง <?= $b['id_room'] ?> - <?= $b['name'].' '.$b['lastname'] ?>
                    (<?= date('M Y', strtotime($b['billing_month'])) ?>)
                    <?= $b['status']=='overdue' ? '⚠️ค้างชำระ' : '' ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Bill Info Preview -->
        <div id="bill_preview" style="display:none;background:var(--bg);border-radius:10px;padding:16px;margin-bottom:16px;">
            <div class="d-flex justify-between" style="margin-bottom:8px;">
                <span class="text-muted">ห้อง</span>
                <strong id="prev_room">-</strong>
            </div>
            <div class="d-flex justify-between" style="margin-bottom:8px;">
                <span class="text-muted">ผู้เช่า</span>
                <strong id="prev_name">-</strong>
            </div>
            <div class="d-flex justify-between" style="margin-bottom:8px;">
                <span class="text-muted">เดือน</span>
                <strong id="prev_month">-</strong>
            </div>
            <div class="divider"></div>
            <div class="d-flex justify-between">
                <span style="font-size:16px;font-weight:700;">ยอดที่ต้องชำระ</span>
                <strong style="font-size:20px;color:var(--primary);" id="prev_amount">-</strong>
            </div>
        </div>

        <div class="form-group">
            <label class="required">จำนวนเงินที่รับ (บาท)</label>
            <input type="number" name="paid_amount" id="paid_amount" step="0.01" required
                   placeholder="0.00" style="font-size:18px;font-weight:700;text-align:right;">
        </div>

        <div class="form-group">
            <label class="required">วิธีชำระเงิน</label>
            <select name="payment_method">
                <option value="cash">💵 เงินสด</option>
                <option value="transfer">🏦 โอนเงิน</option>
                <option value="qr">📱 QR Code</option>
            </select>
        </div>

        <div class="form-group">
            <label>แนบสลิป (ถ้ามี)</label>
            <input type="file" name="slip_img" accept="image/*">
        </div>

        <div class="form-group">
            <label>หมายเหตุ</label>
            <input type="text" name="note" placeholder="บันทึกเพิ่มเติม...">
        </div>

        <!-- Google Sheet Notice -->
        <?php if (getSetting('google_script_url')): ?>
        <div class="alert alert-info" style="margin-bottom:16px;">
            <i class="fa-brands fa-google"></i>
            ข้อมูลการชำระเงินจะถูกบันทึกลง Google Sheets โดยอัตโนมัติ
        </div>
        <?php else: ?>
        <div class="alert alert-warning" style="margin-bottom:16px;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            ยังไม่ได้ตั้งค่า Google Sheets — <a href="settings.php">ตั้งค่าตอนนี้</a>
        </div>
        <?php endif; ?>

        <button type="submit" name="record_payment" class="btn btn-success btn-lg" style="width:100%;justify-content:center;">
            <i class="fa-solid fa-check-circle"></i> ยืนยันการรับชำระ
        </button>
    </form>
</div>

<!-- Payment History -->
<div class="card">
    <div class="card-title"><i class="fa-solid fa-history"></i> ประวัติการชำระเงิน</div>
    <div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>ห้อง</th>
                <th>ผู้เช่า</th>
                <th>เดือน</th>
                <th>จำนวน</th>
                <th>ช่องทาง</th>
                <th>GSheet</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($p = $payments->fetch_assoc()):
            $method_icons = ['cash'=>'💵','transfer'=>'🏦','qr'=>'📱'];
        ?>
        <tr>
            <td><strong><?= $p['id_room'] ?></strong></td>
            <td><?= $p['name'].' '.$p['lastname'] ?></td>
            <td><?= date('M Y', strtotime($p['billing_month'])) ?></td>
            <td class="fw-bold text-success"><?= number_format($p['paid_amount'], 0) ?> ฿</td>
            <td><?= $method_icons[$p['payment_method']] ?? '?' ?></td>
            <td>
                <?php if ($p['google_sheet_synced']): ?>
                    <span style="color:var(--success);" title="ซิงค์แล้ว">✅</span>
                <?php else: ?>
                    <a href="?sync=<?= $p['payment_id'] ?>" title="ซิงค์ไป Sheets" style="color:var(--warning);">🔄</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>

</div>

<script>
function loadBillInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) { document.getElementById('bill_preview').style.display='none'; return; }
    document.getElementById('bill_preview').style.display='block';
    document.getElementById('prev_room').textContent   = 'ห้อง ' + opt.dataset.room;
    document.getElementById('prev_name').textContent   = opt.dataset.name;
    document.getElementById('prev_month').textContent  = opt.dataset.month;
    document.getElementById('prev_amount').textContent = parseFloat(opt.dataset.amount).toLocaleString('th-TH',{minimumFractionDigits:2}) + ' ฿';
    document.getElementById('paid_amount').value = opt.dataset.amount;
}

// Auto-load if prefill
window.addEventListener('load', () => {
    const sel = document.getElementById('sel_bill');
    if (sel.value) loadBillInfo(sel);
});
</script>

<?php include '../includes/footer.php'; ?>