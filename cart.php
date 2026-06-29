<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'ตะกร้าสินค้า';

$itemsStmt = $pdo->prepare("
    SELECT ci.cart_item_id, ci.quantity, p.product_id, p.name, p.slug, p.price, p.sale_price, p.cover_image, p.stock_qty, p.is_preorder
    FROM cart_items ci
    JOIN products p ON p.product_id = ci.product_id
    WHERE ci.cart_token = ?
    ORDER BY ci.cart_item_id DESC
");
$itemsStmt->execute([$cartToken]);
$items = $itemsStmt->fetchAll();

$couponCode = $_SESSION['applied_coupon'] ?? '';
$couponDiscount = 0;
$couponError = '';

if ($couponCode) {
    $cStmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
    $cStmt->execute([$couponCode]);
    $coupon = $cStmt->fetch();
    $subtotalCheck = array_sum(array_map(fn($i) => ($i['sale_price'] ?? $i['price']) * $i['quantity'], $items));

    if (!$coupon) {
        $couponError = 'คูปองไม่ถูกต้อง';
        unset($_SESSION['applied_coupon']);
    } elseif ($coupon['valid_until'] && strtotime($coupon['valid_until']) < time()) {
        $couponError = 'คูปองหมดอายุแล้ว';
        unset($_SESSION['applied_coupon']);
    } elseif ($subtotalCheck < $coupon['min_purchase']) {
        $couponError = 'ยอดซื้อยังไม่ถึงขั้นต่ำ ฿' . formatPrice($coupon['min_purchase']) . ' สำหรับคูปองนี้';
    } else {
        $couponDiscount = $coupon['discount_type'] === 'percent'
            ? $subtotalCheck * ($coupon['discount_value'] / 100)
            : $coupon['discount_value'];
        $couponDiscount = min($couponDiscount, $subtotalCheck);
    }
}

$totals = calculateCartTotals($items, $couponDiscount);

require __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <h2>🛒 ตะกร้าสินค้าของคุณ</h2>

        <?php if (empty($items)): ?>
            <div style="text-align:center; padding:60px 0;">
                <p style="font-size:48px; margin-bottom:10px;">🧸</p>
                <p style="color:var(--color-ink-soft); margin-bottom:20px;">ตะกร้าของคุณยังว่างอยู่เลย ไปเลือกของเล่นกันเถอะ!</p>
                <a href="/index.php" class="btn btn-primary">เลือกซื้อสินค้า</a>
            </div>
        <?php else: ?>
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:30px;" class="cart-grid">
            <div id="cartItemsList">
                <?php foreach ($items as $item):
                    $price = $item['sale_price'] ?? $item['price'];
                ?>
                <div class="cart-row" data-id="<?= $item['cart_item_id'] ?>" style="display:flex; gap:14px; background:#fff; border:2px solid var(--color-border); border-radius:14px; padding:14px; margin-bottom:12px; align-items:center;">
                    <div style="width:70px; height:70px; background:var(--color-bg); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:32px; overflow:hidden; flex-shrink:0;">
                        <?php if ($item['cover_image']): ?>
                            <img src="<?= sanitize($item['cover_image']) ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>🎲<?php endif; ?>
                    </div>
                    <div style="flex:1;">
                        <a href="/product.php?slug=<?= urlencode($item['slug']) ?>" style="font-weight:700; font-size:15px;"><?= sanitize($item['name']) ?></a>
                        <p style="color:var(--color-honey-dark); font-weight:700; margin:4px 0 0;">฿<?= formatPrice($price) ?></p>
                    </div>
                    <input type="number" class="qty-input" value="<?= $item['quantity'] ?>" min="1" max="<?= max(1,(int)$item['stock_qty']) ?>"
                        style="width:60px; padding:8px; border:2px solid var(--color-border); border-radius:8px; text-align:center;">
                    <button class="remove-btn" style="background:none; border:none; color:var(--color-danger); cursor:pointer; font-size:14px; font-weight:700;">ลบ</button>
                </div>
                <?php endforeach; ?>
            </div>

            <div>
                <div style="background:#fff; border:2px solid var(--color-border); border-radius:var(--radius-card); padding:22px; position:sticky; top:90px;">
                    <h3 style="font-size:17px;">สรุปคำสั่งซื้อ</h3>

                    <form method="post" action="/apply_coupon.php" style="display:flex; gap:8px; margin-bottom:16px;">
                        <input type="text" name="coupon_code" placeholder="กรอกโค้ดส่วนลด" value="<?= sanitize($couponCode) ?>"
                            style="flex:1; padding:10px; border:2px solid var(--color-border); border-radius:8px;">
                        <button type="submit" class="btn btn-secondary btn-sm">ใช้โค้ด</button>
                    </form>
                    <?php if ($couponError): ?><p style="color:var(--color-danger); font-size:13px; margin-top:-10px;">⚠️ <?= sanitize($couponError) ?></p><?php endif; ?>
                    <?php if ($couponDiscount > 0): ?><p style="color:var(--color-mint); font-size:13px; margin-top:-10px;">✅ ใช้โค้ด "<?= sanitize($couponCode) ?>" สำเร็จ</p><?php endif; ?>

                    <div id="totalsBox" style="font-size:14px; border-top:1px solid var(--color-border); padding-top:14px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;"><span>ยอดรวมสินค้า</span><span id="t-subtotal">฿<?= formatPrice($totals['subtotal']) ?></span></div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;"><span>ส่วนลด</span><span id="t-discount" style="color:var(--color-mint);">-฿<?= formatPrice($totals['discount']) ?></span></div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;"><span>ค่าจัดส่ง</span><span id="t-shipping"><?= $totals['shipping_fee'] == 0 ? 'ฟรี' : '฿' . formatPrice($totals['shipping_fee']) ?></span></div>
                        <div style="display:flex; justify-content:space-between; font-weight:800; font-size:17px; border-top:1px solid var(--color-border); padding-top:10px; margin-top:6px;">
                            <span>ยอดรวมทั้งหมด</span><span id="t-total" style="color:var(--color-honey-dark);">฿<?= formatPrice($totals['total']) ?></span>
                        </div>
                    </div>
                    <p style="font-size:12px; color:var(--color-ink-soft); margin-top:10px;">ซื้อครบ ฿<?= formatPrice(FREE_SHIPPING_THRESHOLD) ?> ส่งฟรีทันที!</p>

                    <a href="/checkout.php" class="btn btn-primary btn-block" style="margin-top:14px;">ดำเนินการสั่งซื้อ →</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
@media (max-width: 900px) { .cart-grid { grid-template-columns: 1fr !important; } }
</style>

<script>
document.querySelectorAll('.qty-input').forEach(input => {
    input.addEventListener('change', () => updateCartItem(input));
});
document.querySelectorAll('.remove-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const row = btn.closest('.cart-row');
        updateCartItem(row.querySelector('.qty-input'), 'remove', row);
    });
});

async function updateCartItem(input, action = 'update', rowToRemove = null) {
    const row = input.closest('.cart-row');
    const id = row.dataset.id;
    const formData = new FormData();
    formData.append('cart_item_id', id);
    formData.append('quantity', input.value);
    formData.append('action', action);

    const res = await fetch('/api/cart_update.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        if (action === 'remove') {
            row.remove();
            if (document.querySelectorAll('.cart-row').length === 0) location.reload();
        }
        document.getElementById('t-subtotal').textContent = '฿' + Number(data.totals.subtotal).toLocaleString('th-TH', {minimumFractionDigits:2});
        document.getElementById('t-discount').textContent = '-฿' + Number(data.totals.discount).toLocaleString('th-TH', {minimumFractionDigits:2});
        document.getElementById('t-shipping').textContent = data.totals.shipping_fee == 0 ? 'ฟรี' : '฿' + Number(data.totals.shipping_fee).toLocaleString('th-TH', {minimumFractionDigits:2});
        document.getElementById('t-total').textContent = '฿' + Number(data.totals.total).toLocaleString('th-TH', {minimumFractionDigits:2});
    }
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
