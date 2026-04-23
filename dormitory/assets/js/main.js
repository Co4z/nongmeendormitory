// assets/js/main.js

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}

function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
    }
});

// Auto-calculate bill
function calcBill() {
    const waterPrev = parseFloat(document.getElementById('water_prev')?.value) || 0;
    const waterCurr = parseFloat(document.getElementById('water_curr')?.value) || 0;
    const waterPrice = parseFloat(document.getElementById('water_unit_price')?.value) || 0;
    const elecPrev  = parseFloat(document.getElementById('elec_prev')?.value) || 0;
    const elecCurr  = parseFloat(document.getElementById('elec_curr')?.value) || 0;
    const elecPrice = parseFloat(document.getElementById('elec_unit_price')?.value) || 0;
    const roomCost  = parseFloat(document.getElementById('priceroom')?.value) || 0;
    const other     = parseFloat(document.getElementById('other_fee')?.value) || 0;

    const waterUsed  = Math.max(0, waterCurr - waterPrev);
    const elecUsed   = Math.max(0, elecCurr - elecPrev);
    const waterAmt   = waterUsed * waterPrice;
    const elecAmt    = elecUsed * elecPrice;
    const total      = roomCost + waterAmt + elecAmt + other;

    setVal('water_used', waterUsed);
    setVal('water_amount', waterAmt.toFixed(2));
    setVal('elec_used', elecUsed);
    setVal('elec_amount', elecAmt.toFixed(2));
    setVal('total_amount', total.toFixed(2));
    setVal('total_display', formatMoney(total));
}

function setVal(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val;
    const dis = document.getElementById(id + '_display');
    if (dis) dis.textContent = val;
}

function formatMoney(n) {
    return parseFloat(n).toLocaleString('th-TH', {minimumFractionDigits: 2});
}

// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(a => {
        a.style.transition = 'opacity 0.5s';
        a.style.opacity = '0';
        setTimeout(() => a.remove(), 500);
    });
}, 4000);

// Confirm delete
function confirmDelete(msg) {
    return confirm(msg || 'ยืนยันการลบ?');
}

// Print bill
function printBill(billId) {
    const url = window.location.origin + '/dormitory/pages/print_bill.php?id=' + billId;
    window.open(url, '_blank', 'width=900,height=700');
}
