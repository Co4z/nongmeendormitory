// =============================================
// Google Apps Script - วางในไฟล์ Code.gs
// =============================================
// วิธีใช้:
// 1. เปิด Google Sheet → Extensions → Apps Script
// 2. ลบโค้ดเดิม → วางโค้ดนี้ทั้งหมด
// 3. แก้ SHEET_NAME ให้ตรงกับชื่อ Tab ของคุณ
// 4. Deploy → New Deployment → Web App
//    - Execute as: Me
//    - Who has access: Anyone
// 5. คัดลอก URL ไปวางในหน้า Settings ของระบบ

const SHEET_NAME = 'Payments'; // ← เปลี่ยนชื่อ Tab ตรงนี้

function doPost(e) {
  try {
    const data = JSON.parse(e.postData.contents);
    
    if (data.action === 'addPayment') {
      return addPaymentRow(data);
    }
    
    return ContentService
      .createTextOutput(JSON.stringify({ status: 'error', message: 'Unknown action' }))
      .setMimeType(ContentService.MimeType.JSON);
      
  } catch (err) {
    return ContentService
      .createTextOutput(JSON.stringify({ status: 'error', message: err.toString() }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

function addPaymentRow(data) {
  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  let sheet   = ss.getSheetByName(SHEET_NAME);
  
  // สร้าง Sheet ถ้ายังไม่มี + สร้าง Header
  if (!sheet) {
    sheet = ss.insertSheet(SHEET_NAME);
    const headers = [
      'Payment ID', 'Bill ID', 'ห้อง', 'ผู้เช่า',
      'เดือนที่เก็บ', 'ประเภทห้อง',
      'ยอดบิลทั้งหมด', 'ยอดที่ชำระ',
      'วิธีชำระเงิน', 'วันเวลาที่ชำระ',
      'บันทึกโดย', 'หมายเหตุ', 'วันที่บันทึก'
    ];
    sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
    
    // จัดสไตล์ Header
    const headerRange = sheet.getRange(1, 1, 1, headers.length);
    headerRange.setBackground('#1a5276');
    headerRange.setFontColor('#ffffff');
    headerRange.setFontWeight('bold');
    sheet.setFrozenRows(1);
  }
  
  // เพิ่มแถวข้อมูล
  const methodMap = { cash: 'เงินสด', transfer: 'โอนเงิน', qr: 'QR Code' };
  
  const row = [
    data.payment_id    || '',
    data.bill_id       || '',
    'ห้อง ' + (data.room || ''),
    data.tenant_name   || '',
    data.billing_month || '',
    data.room_type     || '',
    parseFloat(data.total_bill   || 0),
    parseFloat(data.paid_amount  || 0),
    methodMap[data.payment_method] || data.payment_method || '',
    data.paid_date     || '',
    data.recorded_by   || '',
    data.note          || '',
    new Date().toLocaleString('th-TH', { timeZone: 'Asia/Bangkok' })
  ];
  
  sheet.appendRow(row);
  
  // จัดรูปแบบตัวเลข
  const lastRow  = sheet.getLastRow();
  const amountCol = [7, 8]; // คอลัมน์ยอดเงิน
  amountCol.forEach(col => {
    sheet.getRange(lastRow, col).setNumberFormat('#,##0.00');
  });
  
  // สีแถว สลับกัน
  if (lastRow % 2 === 0) {
    sheet.getRange(lastRow, 1, 1, row.length).setBackground('#f8f9fa');
  }
  
  return ContentService
    .createTextOutput(JSON.stringify({ 
      status: 'ok', 
      row: lastRow,
      message: 'บันทึกสำเร็จ แถวที่ ' + lastRow
    }))
    .setMimeType(ContentService.MimeType.JSON);
}

// ทดสอบการทำงาน (รันใน Apps Script Editor)
function testAddPayment() {
  const mockData = {
    action:         'addPayment',
    payment_id:     999,
    bill_id:        1,
    room:           101,
    tenant_name:    'ทดสอบ ระบบ',
    billing_month:  'Apr 2025',
    room_type:      'ห้องใหญ่',
    total_bill:     5500,
    paid_amount:    5500,
    payment_method: 'qr',
    paid_date:      new Date().toISOString(),
    recorded_by:    'Admin',
    note:           'ทดสอบระบบ'
  };
  const result = addPaymentRow(mockData);
  Logger.log(result.getContent());
}
