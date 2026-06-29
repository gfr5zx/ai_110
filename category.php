<?php
require_once __DIR__ . '/includes/init.php';

$slug = $_GET['slug'] ?? '';
$category = null;

if ($slug) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    $category = $stmt->fetch();
    if (!$category) {
        http_response_code(404);
    }
}

$pageTitle = $category ? $category['name'] : 'สินค้าทั้งหมด';

// ตัวกรอง: เรียงราคา
$sort = $_GET['sort'] ?? 'newest';
$orderBy = match ($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    default      => 'p.created_at DESC',
};

if ($category) {
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p
        JOIN categories c ON c.category_id = p.category_id
        WHERE p.is_active = 1 AND p.category_id = ?
        ORDER BY $orderBy");
    $stmt->execute([$category['category_id']]);
} else {
    $stmt = $pdo->query("SELECT p.*, c.name AS category_name FROM products p
        JOIN categories c ON c.category_id = p.category_id
        WHERE p.is_active = 1
        ORDER BY $orderBy");
}
$products = $stmt->fetchAll();

$allCategories = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2><?= $category ? sanitize($category['icon'] . ' ' . $category['name']) : 'สินค้าทั้งหมด' ?></h2>
            <form method="get" style="display:flex; gap:8px; align-items:center;">
                <?php if ($category): ?><input type="hidden" name="slug" value="<?= sanitize($slug) ?>"><?php endif; ?>
                <label for="sort" style="font-size:14px; color:var(--color-ink-soft);">เรียงตาม:</label>
                <select id="sort" name="sort" onchange="this.form.submit()" style="padding:8px 12px; border-radius:10px; border:2px solid var(--color-border); font-family:var(--font-body);">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>ใหม่ล่าสุด</option>
                    <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>ราคา: ต่ำ → สูง</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>ราคา: สูง → ต่ำ</option>
                </select>
            </form>
        </div>

        <?php if ($category): ?>
        <p style="color:var(--color-ink-soft); margin-top:-14px; margin-bottom:24px;"><?= sanitize($category['description']) ?></p>
        <?php endif; ?>

        <?php if (empty($products)): ?>
            <p style="color:var(--color-ink-soft); padding:40px 0; text-align:center;">ยังไม่มีสินค้าในหมวดนี้ในขณะนี้ แวะมาดูใหม่เร็วๆนี้นะครับ 🧸</p>
        <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $p): include __DIR__ . '/includes/product_card.php'; endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
