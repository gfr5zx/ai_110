<?php
require_once __DIR__ . '/includes/init.php';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p
    JOIN categories c ON c.category_id = p.category_id
    WHERE p.slug = ? AND p.is_active = 1");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    echo '<div class="container section"><p>ไม่พบสินค้าที่คุณค้นหา</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $product['name'];

// รูปภาพหลายมุม
$imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
$imgStmt->execute([$product['product_id']]);
$images = $imgStmt->fetchAll();

// ตัวละครในกล่องสุ่ม (Drop Rate) - เฉพาะสินค้าประเภทสุ่ม
$variants = [];
if (in_array($product['product_type'], ['blind_single', 'check_card'])) {
    $vStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY is_secret ASC, drop_rate DESC");
    $vStmt->execute([$product['product_id']]);
    $variants = $vStmt->fetchAll();
}

// รีวิวล่าสุดของสินค้านี้
$reviewStmt = $pdo->prepare("SELECT * FROM reviews WHERE product_id = ? AND is_approved = 1 ORDER BY created_at DESC LIMIT 4");
$reviewStmt->execute([$product['product_id']]);
$reviews = $reviewStmt->fetchAll();

$typeLabels = [
    'blind_single' => 'แบบสุ่มเดี่ยว (Blind Box)',
    'full_set'     => 'แบบยกกล่อง (Full Set)',
    'check_card'   => 'แบบเช็กการ์ด',
    'figure'       => 'ของเล่น & ตุ๊กตา',
];

$hasDiscount = !empty($product['sale_price']) && $product['sale_price'] < $product['price'];
$displayPrice = $hasDiscount ? $product['sale_price'] : $product['price'];

require __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <p style="font-size:13px; color:var(--color-ink-soft); margin-bottom:20px;">
            <a href="/index.php" style="color:var(--color-ink-soft);">หน้าแรก</a> /
            <a href="/category.php?slug=<?= urlencode($product['category_slug']) ?>" style="color:var(--color-ink-soft);"><?= sanitize($product['category_name']) ?></a> /
            <span><?= sanitize($product['name']) ?></span>
        </p>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:40px;" class="product-detail-grid">
            <!-- รูปภาพหลายมุม -->
            <div>
                <div style="background:#fff; border-radius:var(--radius-card); aspect-ratio:1/1; display:flex; align-items:center; justify-content:center; font-size:100px; overflow:hidden; border:2px solid var(--color-border); margin-bottom:12px;" id="mainImageBox">
                    <?php if (!empty($images)): ?>
                        <img src="<?= sanitize($images[0]['image_path']) ?>" id="mainImage" alt="<?= sanitize($product['name']) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php elseif (!empty($product['cover_image'])): ?>
                        <img src="<?= sanitize($product['cover_image']) ?>" id="mainImage" alt="<?= sanitize($product['name']) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <span id="mainImageEmoji">🎲</span>
                    <?php endif; ?>
                </div>
                <?php if (count($images) > 1): ?>
                <div style="display:flex; gap:10px; overflow-x:auto;">
                    <?php foreach ($images as $img): ?>
                    <button onclick="document.getElementById('mainImage').src='<?= sanitize($img['image_path']) ?>'"
                        style="border:2px solid var(--color-border); border-radius:10px; padding:0; width:70px; height:70px; overflow:hidden; cursor:pointer; flex-shrink:0; background:#fff;">
                        <img src="<?= sanitize($img['image_path']) ?>" style="width:100%;height:100%;object-fit:cover;">
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- รายละเอียดสินค้า -->
            <div>
                <span class="product-type-tag"><?= $typeLabels[$product['product_type']] ?? '' ?></span>
                <?php if (!empty($product['badge_label'])): ?>
                    <span class="badge badge-rare" style="position:static; display:inline-block; margin-left:8px;"><?= sanitize($product['badge_label']) ?></span>
                <?php endif; ?>
                <h1 style="margin-top:10px;"><?= sanitize($product['name']) ?></h1>

                <div class="product-price-row" style="margin-bottom:18px;">
                    <span class="price-now" style="font-size:28px;">฿<?= formatPrice($displayPrice) ?></span>
                    <?php if ($hasDiscount): ?><span class="price-old">฿<?= formatPrice($product['price']) ?></span><?php endif; ?>
                </div>

                <p style="color:var(--color-ink-soft); margin-bottom:20px;"><?= nl2br(sanitize($product['description'])) ?></p>

                <p style="font-size:14px; margin-bottom:6px;">
                    <strong>สถานะสต็อก:</strong>
                    <?php if ($product['stock_qty'] > 3): ?>
                        <span style="color:var(--color-mint); font-weight:700;">พร้อมส่ง (<?= (int)$product['stock_qty'] ?> ชิ้น)</span>
                    <?php elseif ($product['stock_qty'] > 0): ?>
                        <span style="color:var(--color-danger); font-weight:700;">เหลือเพียง <?= (int)$product['stock_qty'] ?> ชิ้นสุดท้าย!</span>
                    <?php elseif ($product['is_preorder']): ?>
                        <span style="color:var(--color-mint); font-weight:700;">เปิด Pre-Order</span>
                    <?php else: ?>
                        <span style="color:var(--color-danger); font-weight:700;">สินค้าหมด</span>
                    <?php endif; ?>
                </p>
                <p style="font-size:14px; color:var(--color-ink-soft); margin-bottom:24px;">น้ำหนัก: <?= (int)$product['weight_gram'] ?> กรัม (คำนวณค่าจัดส่งจากน้ำหนักรวม)</p>

                <form id="addToCartForm" style="display:flex; gap:12px; align-items:center; margin-bottom:14px;">
                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                    <label for="qty" style="font-size:14px;">จำนวน:</label>
                    <input type="number" id="qty" name="quantity" value="1" min="1" max="<?= max(1, (int)$product['stock_qty']) ?>"
                        style="width:70px; padding:10px; border:2px solid var(--color-border); border-radius:10px; text-align:center;">
                    <button type="submit" class="btn btn-primary" <?= ($product['stock_qty'] <= 0 && !$product['is_preorder']) ? 'disabled' : '' ?>>
                        🛒 เพิ่มลงตะกร้า
                    </button>
                </form>
                <p id="cartMsg" style="color:var(--color-mint); font-weight:600; font-size:14px;"></p>

                <?php if (!empty($variants)): ?>
                <div style="margin-top:30px; background:#fff; border:2px solid var(--color-border); border-radius:var(--radius-card); padding:20px;">
                    <h3 style="font-size:17px;">🎯 โอกาสสุ่มเจอ (Drop Rate)</h3>
                    <p style="font-size:13px; color:var(--color-ink-soft); margin-bottom:14px;">ทุกกล่องที่ซื้อ คือสินค้าจริงที่กำหนดไว้แล้วในคลัง ระบบจะเปิดเผยผลให้ทราบหลังชำระเงินสำเร็จ</p>
                    <table style="width:100%; font-size:14px; border-collapse:collapse;">
                        <?php foreach ($variants as $v): ?>
                        <tr style="border-bottom:1px solid var(--color-border);">
                            <td style="padding:8px 0;">
                                <?= $v['is_secret'] ? '🌟 ' : '' ?><?= sanitize($v['variant_name']) ?>
                                <?php if ($v['is_secret']): ?><span class="badge badge-rare" style="position:static; display:inline-block; padding:2px 8px; font-size:10px;">SECRET</span><?php endif; ?>
                            </td>
                            <td style="padding:8px 0; text-align:right; font-weight:700; color:var(--color-honey-dark);"><?= number_format($v['drop_rate'], 2) ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Review Corner -->
        <div style="margin-top:50px;">
            <div class="section-head">
                <h2>📸 รีวิวจากลูกค้า</h2>
                <a href="/submit_review.php?product_id=<?= $product['product_id'] ?>" class="view-all">อวดรีวิวของคุณ →</a>
            </div>
            <?php if (empty($reviews)): ?>
                <p style="color:var(--color-ink-soft);">ยังไม่มีรีวิวสำหรับสินค้านี้ เป็นคนแรกที่รีวิวสิ!</p>
            <?php else: ?>
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px;" class="review-grid">
                <?php foreach ($reviews as $r): ?>
                <div style="background:#fff; border:2px solid var(--color-border); border-radius:var(--radius-card); padding:16px;">
                    <?php if ($r['image_path']): ?>
                        <img src="<?= sanitize($r['image_path']) ?>" style="width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:10px; margin-bottom:10px;">
                    <?php endif; ?>
                    <p style="font-weight:700; font-size:14px; margin-bottom:4px;">
                        <?= sanitize($r['display_name']) ?>
                        <?= $r['got_secret'] ? ' 🌟' : '' ?>
                    </p>
                    <p style="font-size:13px; color:var(--color-honey-dark);"><?= str_repeat('⭐', (int)$r['rating']) ?></p>
                    <p style="font-size:13px; color:var(--color-ink-soft);"><?= sanitize($r['comment']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
@media (max-width: 900px) {
    .product-detail-grid { grid-template-columns: 1fr !important; }
    .review-grid { grid-template-columns: repeat(2,1fr) !important; }
}
</style>

<script>
document.getElementById('addToCartForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const res = await fetch('/api/cart_add.php', { method: 'POST', body: formData });
    const data = await res.json();
    const msg = document.getElementById('cartMsg');
    if (data.success) {
        msg.style.color = 'var(--color-mint)';
        msg.textContent = '✅ เพิ่มลงตะกร้าแล้ว! (' + data.cart_count + ' ชิ้นในตะกร้า)';
        document.querySelector('.cart-badge')?.remove();
        document.querySelector('.icon-btn[aria-label="ตะกร้าสินค้า"]')?.insertAdjacentHTML('beforeend', '<span class="cart-badge">' + data.cart_count + '</span>');
    } else {
        msg.style.color = 'var(--color-danger)';
        msg.textContent = '⚠️ ' + (data.message || 'เกิดข้อผิดพลาด');
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
