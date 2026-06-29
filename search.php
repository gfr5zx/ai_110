<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'ค้นหาสินค้า';

$query = trim($_GET['q'] ?? '');
$products = [];
if ($query) {
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p
        JOIN categories c ON c.category_id = p.category_id
        WHERE p.is_active = 1 AND (p.name LIKE ? OR p.description LIKE ?)
        ORDER BY p.created_at DESC");
    $like = '%' . $query . '%';
    $stmt->execute([$like, $like]);
    $products = $stmt->fetchAll();
}

require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <h2>ผลการค้นหา: "<?= sanitize($query) ?>"</h2>
        <p style="color:var(--color-ink-soft); margin-bottom:24px;">พบ <?= count($products) ?> รายการ</p>

        <?php if (empty($products)): ?>
            <p style="color:var(--color-ink-soft); padding:40px 0; text-align:center;">ไม่พบสินค้าที่ตรงกับคำค้นหา ลองค้นหาด้วยคำอื่นดูนะครับ</p>
        <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $p): include __DIR__ . '/includes/product_card.php'; endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
