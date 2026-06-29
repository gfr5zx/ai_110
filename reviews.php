<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'รีวิวจากลูกค้า';

$reviews = $pdo->query("
    SELECT r.*, p.name AS product_name, p.slug AS product_slug
    FROM reviews r JOIN products p ON p.product_id = r.product_id
    WHERE r.is_approved = 1
    ORDER BY r.created_at DESC LIMIT 40
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <h2>📸 รีวิวจากลูกค้า</h2>
        <p style="color:var(--color-ink-soft); margin-bottom:24px;">ลูกค้าจริง ของจริง รีวิวจริง — โดยเฉพาะใครที่สุ่มเจอตัว Secret มาอวดกันได้เลย!</p>

        <?php if (empty($reviews)): ?>
            <p style="color:var(--color-ink-soft);">ยังไม่มีรีวิวในระบบ</p>
        <?php else: ?>
        <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:18px;" class="review-grid-full">
            <?php foreach ($reviews as $r): ?>
            <div style="background:#fff; border:2px solid var(--color-border); border-radius:var(--radius-card); padding:16px;">
                <?php if ($r['image_path']): ?>
                    <img src="<?= sanitize($r['image_path']) ?>" style="width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:10px; margin-bottom:10px;">
                <?php endif; ?>
                <p style="font-weight:700; font-size:14px; margin-bottom:2px;"><?= sanitize($r['display_name']) ?> <?= $r['got_secret'] ? '🌟' : '' ?></p>
                <a href="/product.php?slug=<?= urlencode($r['product_slug']) ?>" style="font-size:12px; color:var(--color-honey-dark);"><?= sanitize($r['product_name']) ?></a>
                <p style="font-size:13px; color:var(--color-honey-dark); margin:6px 0 2px;"><?= str_repeat('⭐', (int)$r['rating']) ?></p>
                <p style="font-size:13px; color:var(--color-ink-soft);"><?= sanitize($r['comment']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<style>@media (max-width:900px){.review-grid-full{grid-template-columns:repeat(2,1fr) !important;}}</style>
<?php require __DIR__ . '/includes/footer.php'; ?>
