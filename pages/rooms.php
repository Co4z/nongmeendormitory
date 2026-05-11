<?php
require_once '../config/db.php';
requireLogin();
$page_title = 'จัดการห้องพัก';

$msg = $err = '';

// ── 1. เพิ่มห้องพัก ──
if (isset($_POST['add_room'])) {
    $type   = (int)$_POST['id_roomtype'];
    $floor  = (int)$_POST['floor_number'];
    $note   = esc($_POST['note'] ?? '');
    
    // รับค่าจาก Checkbox (ถ้าติ๊กจะได้ 1 ถ้าไม่ติ๊กจะได้ 0)
    $air    = isset($_POST['has_air']) ? 1 : 0;
    $heater = isset($_POST['has_water_heater']) ? 1 : 0;
    $fridge = isset($_POST['has_fridge']) ? 1 : 0;
    
    $conn->query("INSERT INTO room_number (id_roomtype, id_statusroom, floor_number, note, has_air, has_water_heater, has_fridge) 
                  VALUES ($type, 1, $floor, '$note', $air, $heater, $fridge)");
    $msg = 'เพิ่มห้องพักสำเร็จ';
}

// ── 2. แก้ไขรายละเอียดห้องพัก ──
if (isset($_POST['edit_room_full'])) {
    $rid    = (int)$_POST['room_id'];
    $type   = (int)$_POST['id_roomtype'];
    $floor  = (int)$_POST['floor_number'];
    $status = (int)$_POST['id_statusroom'];
    $note   = esc($_POST['note'] ?? '');
    
    $air    = isset($_POST['has_air']) ? 1 : 0;
    $heater = isset($_POST['has_water_heater']) ? 1 : 0;
    $fridge = isset($_POST['has_fridge']) ? 1 : 0;

    $sql = "UPDATE room_number SET 
            id_roomtype = $type, 
            floor_number = $floor, 
            id_statusroom = $status, 
            note = '$note',
            has_air = $air,
            has_water_heater = $heater,
            has_fridge = $fridge
            WHERE id_room = $rid";
    
    $conn->query($sql);
    $msg = 'อัปเดตข้อมูลห้องเรียบร้อยแล้ว';
}

// ── 3. ลบห้องพัก ──
if (isset($_POST['delete_room'])) {
    $rid = (int)$_POST['room_id'];
    $check = $conn->query("SELECT contract_id FROM contract WHERE id_room=$rid AND status='active'")->num_rows;
    if ($check == 0) {
        $conn->query("DELETE FROM room_number WHERE id_room=$rid");
        $msg = 'ลบห้องพักเรียบร้อยแล้ว';
    } else {
        $err = 'ไม่สามารถลบได้ เนื่องจากห้องนี้ยังมีสัญญาเช่าที่ใช้งานอยู่';
    }
}

// ดึงข้อมูลห้อง
$rooms = $conn->query("
    SELECT rn.*, rt.name_roomtype, rt.rentcost, sr.status_name, rn.id_statusroom, c.name, c.lastname
    FROM room_number rn
    JOIN room_type rt ON rn.id_roomtype = rt.id_roomtype
    JOIN status_room sr ON rn.id_statusroom = sr.id_statusroom
    LEFT JOIN contract ct ON rn.id_room = ct.id_room AND ct.status='active'
    LEFT JOIN customer c ON ct.id_people = c.id_people
    ORDER BY rn.floor_number ASC, rn.id_room ASC
");

$room_types_list = $conn->query("SELECT * FROM room_type");

include '../includes/header.php';
?>

<style>
    .amenities-group { display: flex; gap: 15px; margin-top: 10px; flex-wrap: wrap; background: #f9f9f9; padding: 10px; border-radius: 8px; border: 1px solid #ddd; }
    .amenity-item { display: flex; align-items: center; gap: 5px; font-size: 14px; cursor: pointer; }
    .room-icons { font-size: 12px; margin-top: 5px; color: #888; display: flex; gap: 5px; justify-content: center; }
</style>

<?php if ($msg): ?><div class="alert alert-success"><i class="fa fa-check"></i> <?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><i class="fa fa-times"></i> <?= $err ?></div><?php endif; ?>

<div class="card">
    <div class="card-title d-flex justify-between align-center">
        <span><i class="fa-solid fa-door-open"></i> รายการห้องพักทั้งหมด</span>
        <button class="btn btn-primary btn-sm" onclick="openModal('modalAddRoom')">
            <i class="fa-solid fa-plus"></i> เพิ่มห้อง
        </button>
    </div>

    <div class="room-grid">
    <?php while ($r = $rooms->fetch_assoc()):
        $cls = ['1'=>'vacant','2'=>'occupied','3'=>'maintenance'][$r['id_statusroom']] ?? '';
    ?>
        <div class="room-card <?= $cls ?>" onclick="openEditRoom(<?= htmlspecialchars(json_encode($r)) ?>)">
            <div class="room-number"><?= $r['id_room'] ?></div>
            <div class="room-type-name"><?= $r['name_roomtype'] ?></div>
            <div style="font-size:11px; margin:4px 0;">ชั้น <?= $r['floor_number'] ?></div>
            
            <div class="room-icons">
                <?php if($r['has_air']): ?><i class="fa-solid fa-wind" title="แอร์"></i><?php endif; ?>
                <?php if($r['has_water_heater']): ?><i class="fa-solid fa-faucet-dotted" title="เครื่องทำน้ำอุ่น"></i><?php endif; ?>
                <?php if($r['has_fridge']): ?><i class="fa-solid fa-refrigerator" title="ตู้เย็น"></i><?php endif; ?>
            </div>

            <div class="badge <?= $cls=='vacant'?'badge-success':($cls=='occupied'?'badge-warning':'badge-danger') ?>" style="font-size:10px; margin-top:5px;">
                <?= $r['status_name'] ?>
            </div>
        </div>
    <?php endwhile; ?>
    </div>
</div>

<div class="modal-overlay" id="modalAddRoom">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">เพิ่มห้องพัก</div>
            <button class="modal-close" onclick="closeModal('modalAddRoom')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">ประเภทห้อง</label>
                        <select name="id_roomtype" required>
                            <?php $room_types_list->data_seek(0); while($rt=$room_types_list->fetch_assoc()): ?>
                                <option value="<?= $rt['id_roomtype'] ?>"><?= $rt['name_roomtype'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="required">ชั้น</label>
                        <input type="number" name="floor_number" value="1" required>
                    </div>
                </div>
                
                <label>สิ่งอำนวยความสะดวกพื้นฐาน</label>
                <div class="amenities-group">
                    <label class="amenity-item"><input type="checkbox" name="has_air"> แอร์</label>
                    <label class="amenity-item"><input type="checkbox" name="has_water_heater"> เครื่องทำน้ำอุ่น</label>
                    <label class="amenity-item"><input type="checkbox" name="has_fridge"> ตู้เย็น</label>
                    </div>

                <div class="form-group" style="margin-top:15px;">
                    <label>หมายเหตุอื่นๆ</label>
                    <input type="text" name="note" placeholder="เช่น ห้องมุม, วิวสวน">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_room" class="btn btn-primary" style="width:100%">บันทึกเพิ่มห้อง</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalEditRoom">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="editRoomTitle">แก้ไขรายละเอียดห้อง</div>
            <button class="modal-close" onclick="closeModal('modalEditRoom')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="room_id" id="edit_room_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>ประเภทห้อง/ราคา</label>
                        <select name="id_roomtype" id="edit_roomtype">
                            <?php $room_types_list->data_seek(0); while($rt=$room_types_list->fetch_assoc()): ?>
                                <option value="<?= $rt['id_roomtype'] ?>"><?= $rt['name_roomtype'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ชั้น</label>
                        <input type="number" name="floor_number" id="edit_floor" required>
                    </div>
                </div>

                <label>สิ่งอำนวยความสะดวก</label>
                <div class="amenities-group">
                    <label class="amenity-item"><input type="checkbox" name="has_air" id="edit_air"> แอร์</label>
                    <label class="amenity-item"><input type="checkbox" name="has_water_heater" id="edit_heater"> เครื่องทำน้ำอุ่น</label>
                    <label class="amenity-item"><input type="checkbox" name="has_fridge" id="edit_fridge"> ตู้เย็น</label>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label>สถานะห้อง</label>
                    <select name="id_statusroom" id="edit_status">
                        <option value="1">ว่าง</option>
                        <option value="2">มีผู้เช่า</option>
                        <option value="3">ซ่อมบำรุง</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>หมายเหตุ</label>
                    <input type="text" name="note" id="edit_note">
                </div>
            </div>
            <div class="modal-footer d-flex justify-between">
                <button type="submit" name="delete_room" class="btn btn-danger" onclick="return confirm('ลบห้องนี้ถาวร?')">ลบห้อง</button>
                <button type="submit" name="edit_room_full" class="btn btn-primary">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditRoom(room) {
    document.getElementById('edit_room_id').value = room.id_room;
    document.getElementById('editRoomTitle').textContent = 'จัดการห้อง ' + room.id_room;
    document.getElementById('edit_roomtype').value = room.id_roomtype;
    document.getElementById('edit_floor').value = room.floor_number;
    document.getElementById('edit_status').value = room.id_statusroom;
    document.getElementById('edit_note').value = room.note || '';
    
    // โหลดสถานะ Checkbox
    document.getElementById('edit_air').checked = room.has_air == 1;
    document.getElementById('edit_heater').checked = room.has_water_heater == 1;
    document.getElementById('edit_fridge').checked = room.has_fridge == 1;

    openModal('modalEditRoom');
}
</script>

<?php include '../includes/footer.php'; ?>