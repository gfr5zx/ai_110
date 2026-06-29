<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'ดำเนินการสั่งซื้อ';

$itemsStmt = $pdo->prepare("
    SELECT ci.cart_item_id, ci.quantity, p.product_id, p.name, p.price, p.sale_price, p.stock_qty, p.is_preorder
    FROM cart_items ci JOIN products p ON p.product_id = ci.product_id
    WHERE ci.cart_token = ?
");
$itemsStmt->execute([$cartToken]);
$items = $itemsStmt->fetchAll();

if (empty($items)) {
    redirect('/cart.php');
}

$couponCode = $_SESSION['applied_coupon'] ?? '';
$couponDiscount = 0;
if ($couponCode) {
    $cStmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
    $cStmt->execute([$couponCode]);
    $coupon = $cStmt->fetch();
    if ($coupon) {
        $subtotalCheck = array_sum(array_map(fn($i) => ($i['sale_price'] ?? $i['price']) * $i['quantity'], $items));
        if ($subtotalCheck >= $coupon['min_purchase']) {
            $couponDiscount = $coupon['discount_type'] === 'percent'
                ? $subtotalCheck * ($coupon['discount_value'] / 100)
                : $coupon['discount_value'];
            $couponDiscount = min($couponDiscount, $subtotalCheck);
        }
    }
}
$totals = calculateCartTotals($items, $couponDiscount);

// ดึงข้อมูลสมาชิก (ถ้าล็อกอิน) เพื่อช่วย autofill
$memberData = null;
$savedAddress = null;
if (isLoggedIn()) {
    $mStmt = $pdo->prepare("SELECT * FROM members WHERE member_id = ?");
    $mStmt->execute([currentMemberId()]);
    $memberData = $mStmt->fetch();

    $aStmt = $pdo->prepare("SELECT * FROM member_addresses WHERE member_id = ? ORDER BY is_default DESC LIMIT 1");
    $aStmt->execute([currentMemberId()]);
    $savedAddress = $aStmt->fetch();
}

require __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h2>📦 ดำเนินการสั่งซื้อ</h2>

        <form action="/place_order.php" method="post" enctype="multipart/form-data" id="checkoutForm">
        <div style="display:grid; grid-template-columns:1.4fr 1fr; gap:30px;" class="checkout-grid">
            <div>
                <div style="background:#fff; border:2px solid var(--color-border); border-radius:var(--radius-card); padding:22px; margin-bottom:20px;">
                    <h3 style="font-size:16px;">ข้อมูลผู้รับสินค้า</h3>
                    <div style="display:grid; gap:12px; margin-top:12px;">
                        <input type="text" name="recipient_name" placeholder="ชื่อ-นามสกุลผู้รับ" required
                            value="<?= sanitize($savedAddress['recipient_name'] ?? $memberData['full_name'] ?? '') ?>"
                            style="padding:12px; border:2px solid var(--color-border); border-radius:10px;">
                        <input type="tel" name="phone" placeholder="เบอร์โทรศัพท์" required
                            value="<?= sanitize($savedAddress['phone'] ?? $memberData['phone'] ?? '') ?>"
                            style="padding:12px; border:2px solid var(--color-border); border-radius:10px;">
                        <?php if (!isLoggedIn()): ?>
                        <input type="email" name="guest_email" placeholder="อีเมล (สำหรับติดต่อเรื่องคำสั่งซื้อ)" required
                            style="padding:12px; border:2px solid var(--color-border); border-radius:10px;">
                        <?php endif; ?>
                        <textarea name="address_line" placeholder="ที่อยู่จัดส่ง (บ้านเลขที่ ถนน ตำบล อำเภอ จังหวัด รหัสไปรษณีย์)" required rows="3"
                            style="padding:12px; border:2px solid var(--color-border); border-radius:10px; font-family:var(--font-body); resize:vertical;"><?= sanitize($savedAddress['address_line'] ?? '') ?></textarea>
                    </div>
                </div>

                <div style="background:#fff; border:2px solid var(--color-border); border-radius:var(--radius-card); padding:22px;">
                    <h3 style="font-size:16px;">ช่องทางชำระเงิน</h3>
                    <div style="display:grid; gap:10px; margin-top:12px;">
                        <label class="pay-option">
                            <input type="radio" name="payment_method" value="bank_transfer" checked onchange="togglePaymentInfo()"> โอนเงินผ่านธนาคาร (แนบสลิป)
                        </label>
                        <label class="pay-option">
                            <input type="radio" name="payment_method" value="qr" onchange="togglePaymentInfo()"> สแกน QR พร้อมเพย์
                        </label>
                        <label class="pay-option">
                            <input type="radio" name="payment_method" value="cod" onchange="togglePaymentInfo()"> เก็บเงินปลายทาง (COD)
                        </label>
                    </div>

                    <div id="bankInfo" class="pay-info">
                        <p style="font-size:14px; background:var(--color-bg); padding:14px; border-radius:10px; margin-top:14px;">
                            ธนาคารกสิกรไทย เลขที่บัญชี 123-4-56789-0<br>ชื่อบัญชี: บริษัท อาร์ตทอย ช็อป จำกัด
                        </p>
                        <label style="display:block; margin-top:10px; font-size:14px;">แนบสลิปการโอนเงิน</label>
                        <input type="file" name="payment_slip" accept="image/*" style="margin-top:6px;">
                    </div>
                    <div id="qrInfo" class="pay-info" style="display:none;">
                        <p style="font-size:14px; background:var(--color-bg); padding:14px; border-radius:10px; margin-top:14px; text-align:center;">
                            📱 สแกน QR Code นี้เพื่อชำระเงิน<br><span style="font-size:60px;">▢</span>
                        </p>
                        <label style="display:block; margin-top:10px; font-size:14px;">แนบสลิปการโอนเงิน</label>
                        <input type="file" name="payment_slip_qr" accept="image/*" style="margin-top:6px;">
                    </div>
                    <div id="codInfo" class="pay-info" style="display:none;">
                        <p style="font-size:13px; color:var(--color-ink-soft); margin-top:14px;">ชำระเงินสดกับพนักงานจัดส่งเมื่อได้รับสินค้า (อาจมีค่าธรรมเนียม COD เพิ่มเติมตามผู้ให้บริการขนส่ง)</p>
                    </div>
                </div>
            </div>

            <div>
                <div style="background:#fff; border:2px solid var(--color-border); border-radius:var(--radius-card); padding:22px; position:sticky; top:90px;">
                    <h3 style="font-size:16px;">รายการสั่งซื้อ</h3>
                    <?php foreach ($items as $item): $price = $item['sale_price'] ?? $item['price']; ?>
                    <div style="display:flex; justify-content:space-between; font-size:14px; margin:10px 0;">
                        <span><?= sanitize($item['name']) ?> × <?= $item['quantity'] ?></span>
                        <span>฿<?= formatPrice($price * $item['quantity']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div style="border-top:1px solid var(--color-border); margin-top:10px; padding-top:10px; font-size:14px;">
                        <div style="display:flex; justify-content:space-between;"><span>ยอดรวมสินค้า</span><span>฿<?= formatPrice($totals['subtotal']) ?></span></div>
                        <div style="display:flex; justify-content:space-between;"><span>ส่วนลด</span><span>-฿<?= formatPrice($totals['discount']) ?></span></div>
                        <div style="display:flex; justify-content:space-between;"><span>ค่าจัดส่ง</span><span><?= $totals['shipping_fee'] == 0 ? 'ฟรี' : '฿'.formatPrice($totals['shipping_fee']) ?></span></div>
                        <div style="display:flex; justify-content:space-between; font-weight:800; font-size:17px; margin-top:8px;"><span>รวมทั้งหมด</span><span style="color:var(--color-honey-dark);">฿<?= formatPrice($totals['total']) ?></span></div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">ยืนยันคำสั่งซื้อ</button>
                </div>
            </div>
        </div>
        </form>
    </div>
</section>

<style>
.pay-option { display:flex; align-items:center; gap:10px; padding:12px; border:2px solid var(--color-border); border-radius:10px; cursor:pointer; font-size:14px; }
@media (max-width: 900px) { .checkout-grid { grid-template-columns: 1fr !important; } }
</style>
<script>
function togglePaymentInfo() {
    document.getElementById('bankInfo').style.display = 'none';
    document.getElementById('qrInfo').style.display = 'none';
    document.getElementById('codInfo').style.display = 'none';
    const val = document.querySelector('input[name="payment_method"]:checked').value;
    if (val === 'bank_transfer') document.getElementById('bankInfo').style.display = 'block';
    if (val === 'qr') document.getElementById('qrInfo').style.display = 'block';
    if (val === 'cod') document.getElementById('codInfo').style.display = 'block';
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
