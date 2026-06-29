<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'หน้าแรก';

// สินค้าแนะนำ (active เท่านั้น เรียงใหม่สุดก่อน)
$featured = $pdo->query("
    SELECT p.*, c.name AS category_name FROM products p
    JOIN categories c ON c.category_id = p.category_id
    WHERE p.is_active = 1
    ORDER BY p.created_at DESC LIMIT 8
")->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="hero-slider" id="heroSlider">
        <div class="hero-slide active">
            <div class="hero-slide-inner">
                <div class="hero-text">
                    <span class="eyebrow">คอลเลกชันใหม่มาแล้ว</span>
                    <h1>Art Toy คอลเลกชันใหม่<br>มาถึงแล้ววันนี้!</h1>
                    <p>อัปเดตวงการของเล่นสะสมกับ Art Toy ลิมิเต็ดล่าสุด ก่อนใครหมด รีบจองด่วน</p>
                    <a href="/category.php?slug=blind-box" class="btn btn-primary">เลือกซื้อเลย 🎲</a>
                </div>
                <div class="hero-image">🎁</div>
            </div>
        </div>
        <div class="hero-slide">
            <div class="hero-slide-inner">
                <div class="hero-text">
                    <span class="eyebrow">ลิขสิทธิ์แท้ 100%</span>
                    <h1>ตุ๊กตาหมีพูห์<br>ลิขสิทธิ์แท้ กระแสแรง</h1>
                    <p>หมีพูห์ตัวโปรดของทุกคน วัสดุดี งานละเอียด พร้อมส่งถึงบ้านคุณ</p>
                    <a href="/category.php?slug=winnie-the-pooh" class="btn btn-primary">ดูคอลเลกชันหมีพูห์ 🧸</a>
                </div>
                <div class="hero-image">🧸</div>
            </div>
        </div>
        <div class="hero-dots">
            <button class="active" data-slide="0" aria-label="สไลด์ 1"></button>
            <button data-slide="1" aria-label="สไลด์ 2"></button>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2>หมวดหมู่สินค้า</h2>
        </div>
        <div class="category-grid">
            <?php foreach ($categories as $cat): ?>
            <a href="/category.php?slug=<?= urlencode($cat['slug']) ?>" class="category-card">
                <div class="cat-icon"><?= $cat['icon'] ?></div>
                <h3><?= sanitize($cat['name']) ?></h3>
                <p><?= sanitize($cat['description']) ?></p>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" style="background:#fff;">
    <div class="container">
        <div class="section-head">
            <h2>สินค้าแนะนำ</h2>
            <a href="/category.php" class="view-all">ดูทั้งหมด →</a>
        </div>

        <?php if (empty($featured)): ?>
            <p style="color:var(--color-ink-soft);">ยังไม่มีสินค้าในระบบ กรุณาเพิ่มสินค้าผ่านหน้า Admin</p>
        <?php else: ?>
        <div class="product-grid">
            <?php foreach ($featured as $p): include __DIR__ . '/includes/product_card.php'; endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
// Hero slider auto-play แบบเบสิค
(function(){
    const slides = document.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.hero-dots button');
    let current = 0;
    function show(i) {
        slides.forEach((s, idx) => s.classList.toggle('active', idx === i));
        dots.forEach((d, idx) => d.classList.toggle('active', idx === i));
        current = i;
    }
    dots.forEach(d => d.addEventListener('click', () => show(parseInt(d.dataset.slide))));
    if (slides.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        setInterval(() => show((current + 1) % slides.length), 5000);
    }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
