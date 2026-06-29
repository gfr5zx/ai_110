<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'สั่งซื้อสำเร็จ';

$orderCode = $_GET['order_code'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ?");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    redirect('/index.php');
}

$itemsStmt = $pdo->prepare("
    SELECT oi.*, v.variant_name, v.image_path AS variant_image, v.is_secret
    FROM order_items oi
    LEFT JOIN product_variants v ON v.variant_id = oi.revealed_variant_id
    WHERE oi.order_id = ?
");
$itemsStmt->execute([$order['order_id']]);
$orderItems = $itemsStmt->fetchAll();

$paymentLabels = ['bank_transfer' => 'โอนเงินผ่านธนาคาร', 'qr' => 'สแกน QR', 'cod' => 'เก็บเงินปลายทาง'];

require __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container" style="max-width:760px;">
        <div style="text-align:center; margin-bottom:30px;">
            <p style="font-size:60px; margin:0;">🎉</p>
            <h1>สั่งซื้อสำเร็จแล้ว!</h1>
            <p style="color:var(--color-ink-soft);">รหัสคำสั่งซื้อ: <strong style="color:var(--color-honey-dark);"><?= sanitize($order['order_code']) ?></strong></p>
            <p style="color:var(--color-ink-soft); font-size:14px;">ชำระเงินผ่าน: <?= $paymentLabels[$order['payment_method']] ?? '' ?> · ยอดรวม ฿<?= formatPrice($order['total_amount']) ?></p>
        </div>

        <?php
        $revealItems = array_filter($orderItems, fn($i) => $i['is_revealed']);
        ?>
        <?php if (!empty($revealItems)): ?>
        <div style="background:#fff; border:3px dashed var(--color-honey); border-radius:var(--radius-card); padding:30px; margin-bottom:30px;">
            <h2 style="text-align:center; font-size:20px;">🎁 เปิดกล่องสุ่มของคุณ</h2>
            <p style="text-align:center; color:var(--color-ink-soft); font-size:13px; margin-bottom:24px;">กล่องที่คุณซื้อเป็นของคุณแน่นอนแล้ว มาดูกันว่าได้ตัวไหน!</p>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:18px;">
                <?php foreach ($revealItems as $idx => $ri): ?>
                <div class="reveal-box" data-idx="<?= $idx ?>" style="text-align:center; cursor:pointer; perspective:600px;">
                    <div class="reveal-inner" style="background:var(--color-bg); border:2px solid var(--color-border); border-radius:14px; padding:20px; transition:transform 0.5s; min-height:140px; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                        <div class="reveal-front" style="font-size:42px;">🎲</div>
                        <div class="reveal-back" style="display:none;">
                            <div style="font-size:38px;"><?= $ri['is_secret'] ? '🌟' : '🧸' ?></div>
                            <p style="font-weight:700; font-size:13px; margin:8px 0 2px;"><?= sanitize($ri['variant_name'] ?: 'ของเล่นสุ่ม') ?></p>
                            <?php if ($ri['is_secret']): ?><span class="badge badge-rare" style="position:static; display:inline-block; font-size:10px;">SECRET!</span><?php endif; ?>
                        </div>
                    </div>
                    <p style="font-size:12px; color:var(--color-ink-soft); margin-top:6px;">แตะเพื่อเปิดกล่อง</p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div style="background:#fff; border:2px solid var(--color-border); border-radius:var(--radius-card); padding:22px;">
            <h3 style="font-size:16px;">รายการสินค้า</h3>
            <?php foreach ($orderItems as $item): ?>
            <div style="display:flex; justify-content:space-between; font-size:14px; padding:8px 0; border-bottom:1px solid var(--color-border);">
                <span><?= sanitize($item['product_name']) ?></span>
                <span>฿<?= formatPrice($item['unit_price']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align:center; margin-top:30px;">
            <a href="/index.php" class="btn btn-primary">เลือกซื้อสินค้าต่อ</a>
        </div>
    </div>
</section>

<script>
document.querySelectorAll('.reveal-box').forEach(box => {
    box.addEventListener('click', function() {
        const inner = this.querySelector('.reveal-inner');
        const front = this.querySelector('.reveal-front');
        const back = this.querySelector('.reveal-back');
        if (back.style.display === 'none') {
            front.style.display = 'none';
            back.style.display = 'block';
            inner.style.borderColor = 'var(--color-honey)';
            inner.style.transform = 'scale(1.04)';
        }
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
