<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'อวดรีวิวของคุณ';

$productId = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
$pStmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
$pStmt->execute([$productId]);
$product = $pStmt->fetch();

if (!$product) redirect('/index.php');

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $displayName = sanitize($_POST['display_name'] ?? '');
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comment = sanitize($_POST['comment'] ?? '');
    $gotSecret = isset($_POST['got_secret']) ? 1 : 0;

    if (!$displayName || !$comment) {
        $error = 'กรุณากรอกชื่อและความเห็นของคุณ';
    } else {
        $imagePath = null;
        if (!empty($_FILES['review_image']) && $_FILES['review_image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = uploadProductImage($_FILES['review_image']);
        }
        $ins = $pdo->prepare("INSERT INTO reviews (product_id, member_id, display_name, rating, comment, image_path, got_secret)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$productId, currentMemberId(), $displayName, $rating, $comment, $imagePath, $gotSecret]);
        $success = true;
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container" style="max-width:480px;">
        <h2>📸 อวดรีวิวของคุณ</h2>
        <p style="color:var(--color-ink-soft); margin-bottom:20px;">สำหรับสินค้า: <strong><?= sanitize($product['name']) ?></strong></p>

        <?php if ($success): ?>
            <div style="background:#fff; border:2px dashed var(--color-mint); border-radius:var(--radius-card); padding:30px; text-align:center;">
                <p style="font-size:40px; margin:0;">🎉</p>
                <p style="font-weight:700;">ขอบคุณสำหรับรีวิวของคุณ!</p>
                <a href="/product.php?slug=<?= urlencode($product['slug']) ?>" class="btn btn-primary" style="margin-top:10px;">กลับไปหน้าสินค้า</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?><p style="color:var(--color-danger); background:#FCEAE8; padding:10px; border-radius:8px; font-size:14px;">⚠️ <?= sanitize($error) ?></p><?php endif; ?>
            <form method="post" enctype="multipart/form-data" style="background:#fff; border:2px solid var(--color-border); border-radius:var(--radius-card); padding:24px; display:grid; gap:12px;">
                <input type="hidden" name="product_id" value="<?= $productId ?>">
                <input type="text" name="display_name" placeholder="ชื่อที่จะแสดง" required style="padding:12px; border:2px solid var(--color-border); border-radius:10px;">
                <select name="rating" style="padding:12px; border:2px solid var(--color-border); border-radius:10px;">
                    <option value="5">⭐⭐⭐⭐⭐ ประทับใจมาก</option>
                    <option value="4">⭐⭐⭐⭐ ดี</option>
                    <option value="3">⭐⭐⭐ ปานกลาง</option>
                </select>
                <textarea name="comment" placeholder="เล่าความรู้สึกของคุณ..." rows="4" required style="padding:12px; border:2px solid var(--color-border); border-radius:10px; resize:vertical;"></textarea>
                <label style="font-size:14px;"><input type="checkbox" name="got_secret"> 🌟 ฉันสุ่มเจอตัว Secret!</label>
                <label style="font-size:14px;">แนบรูปถ่าย</label>
                <input type="file" name="review_image" accept="image/*">
                <button type="submit" class="btn btn-primary btn-block">ส่งรีวิว</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
