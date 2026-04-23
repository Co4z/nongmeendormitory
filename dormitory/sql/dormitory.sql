-- =============================================
-- ระบบจัดการหอพักรายเดือน
-- Database: dormitory_db
-- =============================================

CREATE DATABASE IF NOT EXISTS dormitory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dormitory_db;

-- =============================================
-- ตาราง Admin (ผู้ดูแลระบบ)
-- =============================================
CREATE TABLE admin (
    ad_id       INT(5) AUTO_INCREMENT PRIMARY KEY,
    ad_name     VARCHAR(50) NOT NULL,
    ad_lastname VARCHAR(50) NOT NULL,
    ad_email    VARCHAR(100),
    ad_tel      VARCHAR(15),
    ad_password VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ตาราง RoomType (ประเภทห้อง)
-- =============================================
CREATE TABLE room_type (
    id_roomtype     INT(10) AUTO_INCREMENT PRIMARY KEY,
    name_roomtype   VARCHAR(50) NOT NULL,
    rentcost        INT(10) NOT NULL COMMENT 'ค่าเช่าพื้นฐาน (บาท/เดือน)',
    description     TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ตาราง StatusRoom (สถานะห้อง)
-- =============================================
CREATE TABLE status_room (
    id_statusroom   INT(1) AUTO_INCREMENT PRIMARY KEY,
    status_name     VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ตาราง RoomNumber (ห้องพัก)
-- =============================================
CREATE TABLE room_number (
    id_room         INT(4) AUTO_INCREMENT PRIMARY KEY,
    id_roomtype     INT(10) NOT NULL,
    id_statusroom   INT(1) NOT NULL DEFAULT 1,
    floor_number    INT(2),
    note            TEXT,
    FOREIGN KEY (id_roomtype) REFERENCES room_type(id_roomtype),
    FOREIGN KEY (id_statusroom) REFERENCES status_room(id_statusroom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ตาราง Customer (ลูกค้า/ผู้เช่า)
-- =============================================
CREATE TABLE customer (
    id_people       VARCHAR(13) PRIMARY KEY COMMENT 'เลขบัตรประชาชน',
    name            VARCHAR(50) NOT NULL,
    lastname        VARCHAR(50) NOT NULL,
    email           VARCHAR(100),
    tel             VARCHAR(15) NOT NULL,
    line_id         VARCHAR(50),
    emergency_tel   VARCHAR(15),
    id_card_img     VARCHAR(255) COMMENT 'path รูปบัตรประชาชน',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ตาราง Period (ระยะเวลาเช่า)
-- =============================================
CREATE TABLE period (
    rent_id         INT(10) AUTO_INCREMENT PRIMARY KEY,
    first_day       DATE NOT NULL COMMENT 'วันเริ่มเช่า',
    due_date        DATE NOT NULL COMMENT 'วันครบสัญญา',
    duration_months INT(3) COMMENT 'จำนวนเดือน'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ตาราง Contract (สัญญาเช่า)
-- =============================================
CREATE TABLE contract (
    contract_id     INT(10) AUTO_INCREMENT PRIMARY KEY,
    id_room         INT(4) NOT NULL,
    id_people       VARCHAR(13) NOT NULL,
    ad_id           INT(5) NOT NULL,
    rent_id         INT(10),
    priceroom       INT(10) NOT NULL COMMENT 'ราคาตกลง (อาจต่างจาก RoomType)',
    rentdate        DATE NOT NULL,
    water_unit_price    DECIMAL(8,2) DEFAULT 18.00 COMMENT 'ราคาต่อหน่วย น้ำ',
    electric_unit_price DECIMAL(8,2) DEFAULT 8.00  COMMENT 'ราคาต่อหน่วย ไฟ',
    deposit         INT(10) DEFAULT 0 COMMENT 'เงินประกัน',
    status          ENUM('active','ended','terminated') DEFAULT 'active',
    note            TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_room) REFERENCES room_number(id_room),
    FOREIGN KEY (id_people) REFERENCES customer(id_people),
    FOREIGN KEY (ad_id) REFERENCES admin(ad_id),
    FOREIGN KEY (rent_id) REFERENCES period(rent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ตาราง Bill (ใบแจ้งหนี้รายเดือน)
-- =============================================
CREATE TABLE bill (
    id_listbill     INT(10) AUTO_INCREMENT PRIMARY KEY,
    contract_id     INT(10) NOT NULL,
    id_people       VARCHAR(13) NOT NULL,
    billing_month   DATE NOT NULL COMMENT 'เดือนที่เก็บ (YYYY-MM-01)',
    billingdate     DATE NOT NULL COMMENT 'วันที่ออกใบแจ้งหนี้',
    due_date        DATE NOT NULL COMMENT 'วันกำหนดชำระ',
    priceroom       INT(10) NOT NULL,
    ad_id           INT(5) NOT NULL,
    status          ENUM('pending','paid','overdue') DEFAULT 'pending',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contract(contract_id),
    FOREIGN KEY (id_people) REFERENCES customer(id_people),
    FOREIGN KEY (ad_id) REFERENCES admin(ad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ตาราง BillList (รายการค่าน้ำ-ไฟ ในใบแจ้งหนี้)
-- =============================================
CREATE TABLE bill_list (
    id_billlist         INT(10) AUTO_INCREMENT PRIMARY KEY,
    id_listbill         INT(10) NOT NULL,
    water_prev_unit     INT(6) DEFAULT 0 COMMENT 'หน่วยน้ำเดือนก่อน',
    water_curr_unit     INT(6) DEFAULT 0 COMMENT 'หน่วยน้ำเดือนนี้',
    water_used          INT(6) DEFAULT 0 COMMENT 'หน่วยที่ใช้ (น้ำ)',
    water_unit_price    DECIMAL(8,2) DEFAULT 18.00,
    water_amount        DECIMAL(10,2) DEFAULT 0,
    electric_prev_unit  INT(6) DEFAULT 0 COMMENT 'หน่วยไฟเดือนก่อน',
    electric_curr_unit  INT(6) DEFAULT 0 COMMENT 'หน่วยไฟเดือนนี้',
    electric_used       INT(6) DEFAULT 0 COMMENT 'หน่วยที่ใช้ (ไฟ)',
    electric_unit_price DECIMAL(8,2) DEFAULT 8.00,
    electric_amount     DECIMAL(10,2) DEFAULT 0,
    other_fee           DECIMAL(10,2) DEFAULT 0 COMMENT 'ค่าใช้จ่ายอื่นๆ',
    other_fee_note      VARCHAR(200),
    total_amount        DECIMAL(10,2) DEFAULT 0 COMMENT 'รวมทั้งสิ้น',
    FOREIGN KEY (id_listbill) REFERENCES bill(id_listbill)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ตาราง Payment (การชำระเงิน)
-- =============================================
CREATE TABLE payment (
    payment_id          INT(10) AUTO_INCREMENT PRIMARY KEY,
    id_listbill         INT(10) NOT NULL,
    paid_amount         DECIMAL(10,2) NOT NULL,
    paid_date           DATETIME NOT NULL,
    payment_method      ENUM('cash','transfer','qr') DEFAULT 'cash',
    slip_img            VARCHAR(255) COMMENT 'path รูปสลิป',
    google_sheet_synced TINYINT(1) DEFAULT 0 COMMENT '0=ยังไม่ sync, 1=sync แล้ว',
    google_sheet_row    INT(10) COMMENT 'row ใน Google Sheet',
    note                VARCHAR(255),
    ad_id               INT(5) NOT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_listbill) REFERENCES bill(id_listbill),
    FOREIGN KEY (ad_id) REFERENCES admin(ad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ตาราง Settings (ตั้งค่าระบบ)
-- =============================================
CREATE TABLE settings (
    setting_key     VARCHAR(100) PRIMARY KEY,
    setting_value   TEXT,
    description     VARCHAR(255),
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- INSERT ข้อมูลเริ่มต้น
-- =============================================

-- สถานะห้อง
INSERT INTO status_room (id_statusroom, status_name) VALUES
(1, 'ว่าง'),
(2, 'มีผู้เช่า'),
(3, 'ซ่อมบำรุง');

-- ประเภทห้อง (ตัวอย่าง)
INSERT INTO room_type (name_roomtype, rentcost, description) VALUES
('ห้องเล็ก', 3000, 'ห้องขนาดเล็ก ไม่มีเครื่องปรับอากาศ'),
('ห้องใหญ่', 5000, 'ห้องขนาดใหญ่ พร้อมเครื่องปรับอากาศ');

-- ห้องพักตัวอย่าง (ชั้น 1-3, ห้องละ 4 ห้อง = 12 ห้อง)
INSERT INTO room_number (id_roomtype, id_statusroom, floor_number) VALUES
(1, 1, 1),(1, 1, 1),(2, 1, 1),(2, 1, 1),
(1, 1, 2),(1, 1, 2),(2, 1, 2),(2, 1, 2),
(1, 1, 3),(1, 1, 3),(2, 1, 3),(2, 1, 3);

-- Admin เริ่มต้น (password: admin1234)
INSERT INTO admin (ad_name, ad_lastname, ad_email, ad_tel, ad_password) VALUES
('เจ้าของ', 'หอพัก', 'admin@dormitory.com', '0812345678',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- ตั้งค่าระบบ
INSERT INTO settings (setting_key, setting_value, description) VALUES
('dorm_name', 'หอพักของฉัน', 'ชื่อหอพัก'),
('dorm_address', 'กรุณาใส่ที่อยู่หอพัก', 'ที่อยู่หอพัก'),
('dorm_tel', '0812345678', 'เบอร์ติดต่อ'),
('water_price_per_unit', '18', 'ราคาน้ำต่อหน่วย (บาท)'),
('electric_price_per_unit', '8', 'ราคาไฟต่อหน่วย (บาท)'),
('bill_due_days', '7', 'จำนวนวันครบกำหนดชำระหลังออกบิล'),
('google_sheet_id', '', 'Google Sheet ID สำหรับบันทึกการชำระ'),
('google_sheet_tab', 'Payments', 'ชื่อ Tab ใน Google Sheet'),
('google_script_url', '', 'Google Apps Script Web App URL');
