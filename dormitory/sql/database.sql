-- ============================================================
--  ระบบจัดการหอพัก (Dormitory Management System)
--  สร้างโดย: ออกแบบตาม ER-Diagram จากรายงาน
-- ============================================================

CREATE DATABASE IF NOT EXISTS dormitory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dormitory;

-- ============================================================
-- 1. ตาราง Admin (ผู้ดูแลระบบ)
-- ============================================================
CREATE TABLE admin (
    ad_id       INT(5) AUTO_INCREMENT PRIMARY KEY,
    ad_name     VARCHAR(50) NOT NULL,
    ad_lastname VARCHAR(50) NOT NULL,
    ad_email    VARCHAR(100),
    ad_tel      VARCHAR(15),
    ad_password VARCHAR(255) NOT NULL,  -- bcrypt hash
    ad_role     ENUM('admin','staff') DEFAULT 'staff',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. ตาราง room_type (ประเภทห้อง)
-- ============================================================
CREATE TABLE room_type (
    id_roomtype      INT(10) AUTO_INCREMENT PRIMARY KEY,
    name_roomtype    VARCHAR(50) NOT NULL,
    rentcost         INT(10) NOT NULL,         -- ค่าเช่าต่อเดือน (บาท)
    description      TEXT,
    water_rate       DECIMAL(6,2) DEFAULT 18,  -- บาท/หน่วย
    power_rate       DECIMAL(6,2) DEFAULT 7,   -- บาท/หน่วย
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 3. ตาราง status_room (สถานะห้อง)
-- ============================================================
CREATE TABLE status_room (
    id_statusroom INT(1) AUTO_INCREMENT PRIMARY KEY,
    statusname    VARCHAR(20) NOT NULL
) ENGINE=InnoDB;

INSERT INTO status_room (statusname) VALUES
    ('ว่าง'),
    ('ไม่ว่าง'),
    ('ซ่อมบำรุง');

-- ============================================================
-- 4. ตาราง room_number (หมายเลขห้อง)
-- ============================================================
CREATE TABLE room_number (
    id_room       INT(4) AUTO_INCREMENT PRIMARY KEY,
    id_roomtype   INT(10) NOT NULL,
    id_statusroom INT(1)  NOT NULL DEFAULT 1,
    floor         INT(2)  DEFAULT 1,
    note          TEXT,
    FOREIGN KEY (id_roomtype)   REFERENCES room_type(id_roomtype),
    FOREIGN KEY (id_statusroom) REFERENCES status_room(id_statusroom)
) ENGINE=InnoDB;

-- ============================================================
-- 5. ตาราง customer (ลูกค้า/ผู้เช่า)
-- ============================================================
CREATE TABLE customer (
    id_people   VARCHAR(13) PRIMARY KEY,       -- เลขบัตรประชาชน
    name        VARCHAR(50) NOT NULL,
    lastname    VARCHAR(50) NOT NULL,
    email       VARCHAR(100),
    tel         VARCHAR(15),
    id_line     VARCHAR(50),
    photo       VARCHAR(255),                  -- path รูปถ่าย
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 6. ตาราง period (ระยะเวลาเช่า)
-- ============================================================
CREATE TABLE period (
    rent_id     INT(10) AUTO_INCREMENT PRIMARY KEY,
    first_day   DATE NOT NULL,                 -- วันเริ่มเช่า
    due_date    DATE NOT NULL                  -- วันสิ้นสุดสัญญา
) ENGINE=InnoDB;

-- ============================================================
-- 7. ตาราง contract (สัญญาเช่า)
-- ============================================================
CREATE TABLE contract (
    contract_id VARCHAR(20) PRIMARY KEY,
    id_room     INT(4)       NOT NULL,
    id_people   VARCHAR(13)  NOT NULL,
    ad_id       INT(5)       NOT NULL,
    rent_id     INT(10)      NOT NULL,
    priceroom   INT(10)      NOT NULL,          -- ราคาที่ตกลงไว้
    rentdate    DATE         NOT NULL,
    status      ENUM('active','expired','terminated') DEFAULT 'active',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_room)   REFERENCES room_number(id_room),
    FOREIGN KEY (id_people) REFERENCES customer(id_people),
    FOREIGN KEY (ad_id)     REFERENCES admin(ad_id),
    FOREIGN KEY (rent_id)   REFERENCES period(rent_id)
) ENGINE=InnoDB;

-- ============================================================
-- 8. ตาราง bill (ใบแจ้งหนี้รายเดือน)
-- ============================================================
CREATE TABLE bill (
    id_listbill   INT(10) AUTO_INCREMENT PRIMARY KEY,
    contract_id   VARCHAR(20) NOT NULL,
    id_people     VARCHAR(13) NOT NULL,
    billingdate   DATE        NOT NULL,
    due_date      DATE        NOT NULL,         -- กำหนดชำระ
    priceroom     INT(10)     NOT NULL,
    unit_water_prev DECIMAL(8,2) DEFAULT 0,     -- มิเตอร์น้ำก่อนหน้า
    unit_water_cur  DECIMAL(8,2) DEFAULT 0,     -- มิเตอร์น้ำปัจจุบัน
    unit_power_prev DECIMAL(8,2) DEFAULT 0,     -- มิเตอร์ไฟก่อนหน้า
    unit_power_cur  DECIMAL(8,2) DEFAULT 0,     -- มิเตอร์ไฟปัจจุบัน
    water_rate    DECIMAL(6,2) DEFAULT 18,
    power_rate    DECIMAL(6,2) DEFAULT 7,
    water_cost    DECIMAL(10,2) GENERATED ALWAYS AS
                  ((unit_water_cur - unit_water_prev) * water_rate) STORED,
    power_cost    DECIMAL(10,2) GENERATED ALWAYS AS
                  ((unit_power_cur - unit_power_prev) * power_rate) STORED,
    other_cost    DECIMAL(10,2) DEFAULT 0,      -- ค่าอื่นๆ
    total_cost    DECIMAL(10,2) GENERATED ALWAYS AS
                  (priceroom + ((unit_water_cur - unit_water_prev) * water_rate)
                   + ((unit_power_cur - unit_power_prev) * power_rate) + other_cost) STORED,
    status        ENUM('pending','paid','overdue') DEFAULT 'pending',
    note          TEXT,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contract(contract_id),
    FOREIGN KEY (id_people)   REFERENCES customer(id_people)
) ENGINE=InnoDB;

-- ============================================================
-- 9. ตาราง payment (การชำระเงิน — เชื่อม Google Sheet)
-- ============================================================
CREATE TABLE payment (
    id_payment    INT(10) AUTO_INCREMENT PRIMARY KEY,
    id_listbill   INT(10) NOT NULL,
    id_people     VARCHAR(13) NOT NULL,
    amount        DECIMAL(10,2) NOT NULL,
    pay_method    ENUM('cash','transfer','qr') DEFAULT 'qr',
    pay_date      DATETIME DEFAULT CURRENT_TIMESTAMP,
    ref_code      VARCHAR(50),                  -- รหัสอ้างอิงการโอน
    slip_path     VARCHAR(255),                 -- path รูปสลิป
    synced_sheet  TINYINT(1) DEFAULT 0,         -- 0=ยังไม่ sync, 1=sync แล้ว
    ad_id         INT(5),
    FOREIGN KEY (id_listbill) REFERENCES bill(id_listbill),
    FOREIGN KEY (id_people)   REFERENCES customer(id_people),
    FOREIGN KEY (ad_id)       REFERENCES admin(ad_id)
) ENGINE=InnoDB;

-- ============================================================
-- Data เริ่มต้น
-- ============================================================

-- Admin ตัวอย่าง (password: admin1234)
INSERT INTO admin (ad_name, ad_lastname, ad_email, ad_tel, ad_password, ad_role) VALUES
('เจ้าของ', 'หอพัก', 'owner@dorm.com', '0812345678',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- ประเภทห้อง
INSERT INTO room_type (name_roomtype, rentcost, description, water_rate, power_rate) VALUES
('ห้องเดี่ยว ห้องน้ำในห้อง', 3500, 'ห้องเดี่ยว พัดลม ห้องน้ำในห้อง', 18, 7),
('ห้องเดี่ยว แอร์', 5000, 'ห้องเดี่ยว แอร์ ห้องน้ำในห้อง', 18, 7),
('ห้องคู่ แอร์', 6500, 'ห้องคู่ แอร์ ห้องน้ำในห้อง', 18, 7);

-- ห้องพักตัวอย่าง (10 ห้อง)
INSERT INTO room_number (id_roomtype, id_statusroom, floor) VALUES
(1, 1, 1),(1, 1, 1),(1, 2, 1),(2, 2, 1),(2, 1, 1),
(1, 1, 2),(2, 2, 2),(2, 1, 2),(3, 1, 2),(3, 1, 2);

-- ============================================================
-- VIEW สำหรับดูข้อมูลรวม
-- ============================================================

-- ภาพรวมห้องพัก
CREATE VIEW v_room_overview AS
SELECT
    r.id_room,
    rt.name_roomtype,
    rt.rentcost,
    s.statusname,
    r.floor,
    c.contract_id,
    CONCAT(cu.name,' ',cu.lastname) AS tenant_name,
    cu.tel AS tenant_tel,
    p.due_date AS contract_end
FROM room_number r
JOIN room_type rt   ON r.id_roomtype   = rt.id_roomtype
JOIN status_room s  ON r.id_statusroom = s.id_statusroom
LEFT JOIN contract c  ON r.id_room = c.id_room AND c.status = 'active'
LEFT JOIN customer cu ON c.id_people = cu.id_people
LEFT JOIN period p    ON c.rent_id   = p.rent_id;

-- ผู้ค้างชำระ
CREATE VIEW v_overdue AS
SELECT
    b.id_listbill,
    CONCAT(cu.name,' ',cu.lastname) AS tenant_name,
    cu.tel,
    r.id_room,
    b.billingdate,
    b.due_date,
    b.total_cost,
    b.status,
    DATEDIFF(CURDATE(), b.due_date) AS days_overdue
FROM bill b
JOIN customer cu ON b.id_people = cu.id_people
JOIN contract c  ON b.contract_id = c.contract_id
JOIN room_number r ON c.id_room = r.id_room
WHERE b.status IN ('pending','overdue')
  AND b.due_date < CURDATE()
ORDER BY days_overdue DESC;
