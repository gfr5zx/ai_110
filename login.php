<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'เข้าสู่ระบบ';

if (isLoggedIn()) redirect('/member/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM members WHERE email = ?");
    $stmt->execute([$email]);
    $member = $stmt->fetch();

    if ($member && password_verify($password, $member['password_hash'])) {
        $_SESSION['member_id'] = $member['member_id'];

        // ผูกตะกร้า guest เดิม (ถ้ามี) เข้ากับสมาชิกที่ล็อกอินสำเร็จ
        $pdo->prepare("UPDATE cart_items SET member_id = ? WHERE cart_token = ?")
            ->execute([$member['member_id'], $cartToken]);

        redirect('/member/dashboard.php');
    } else {
        $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container" style="max-width:420px;">
        <h2 style="text-align:center;">เข้าสู่ระบบ</h2>

        <?php if ($error): ?><p style="color:var(--color-danger); background:#FCEAE8; padding:10px; border-radius:8px; font-size:14px;">⚠️ <?= sanitize($error) ?></p><?php endif; ?>

        <form method="post" style="background:#fff; border:2px solid var(--color-border); border-radius:var(--radius-card); padding:24px; display:grid; gap:12px;">
            <input type="email" name="email" placeholder="อีเมล" required style="padding:12px; border:2px solid var(--color-border); border-radius:10px;">
            <input type="password" name="password" placeholder="รหัสผ่าน" required style="padding:12px; border:2px solid var(--color-border); border-radius:10px;">
            <button type="submit" class="btn btn-primary btn-block">เข้าสู่ระบบ</button>
        </form>
        <p style="text-align:center; font-size:14px; margin-top:16px;">ยังไม่มีบัญชี? <a href="/register.php" style="color:var(--color-honey-dark); font-weight:700;">สมัครสมาชิก</a></p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
