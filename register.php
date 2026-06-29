<?php
require_once __DIR__ . '/includes/init.php';
$pageTitle = 'สมัครสมาชิก';

if (isLoggedIn()) redirect('/member/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$fullName || !$email || !$password) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'รูปแบบอีเมลไม่ถูกต้อง';
    } elseif (strlen($password) < 8) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    } elseif ($password !== $confirmPassword) {
        $error = 'รหัสผ่านยืนยันไม่ตรงกัน';
    } else {
        $checkStmt = $pdo->prepare("SELECT member_id FROM members WHERE email = ?");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            $error = 'อีเมลนี้ถูกใช้สมัครสมาชิกไปแล้ว';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO members (full_name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
            $ins->execute([$fullName, $email, $phone, $hash]);
            $_SESSION['member_id'] = $pdo->lastInsertId();
            redirect('/member/dashboard.php');
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container" style="max-width:440px;">
        <h2 style="text-align:center;">สมัครสมาชิกใหม่</h2>
        <p style="text-align:center; color:var(--color-ink-soft); font-size:14px; margin-bottom:24px;">รับโค้ดส่วนลดลูกค้าใหม่ทันทีหลังสมัคร!</p>

        <?php if ($error): ?><p style="color:var(--color-danger); background:#FCEAE8; padding:10px; border-radius:8px; font-size:14px;">⚠️ <?= sanitize($error) ?></p><?php endif; ?>

        <form method="post" style="background:#fff; border:2px solid var(--color-border); border-radius:var(--radius-card); padding:24px; display:grid; gap:12px;">
            <input type="text" name="full_name" placeholder="ชื่อ-นามสกุล" required value="<?= sanitize($_POST['full_name'] ?? '') ?>" style="padding:12px; border:2px solid var(--color-border); border-radius:10px;">
            <input type="email" name="email" placeholder="อีเมล" required value="<?= sanitize($_POST['email'] ?? '') ?>" style="padding:12px; border:2px solid var(--color-border); border-radius:10px;">
            <input type="tel" name="phone" placeholder="เบอร์โทรศัพท์" value="<?= sanitize($_POST['phone'] ?? '') ?>" style="padding:12px; border:2px solid var(--color-border); border-radius:10px;">
            <input type="password" name="password" placeholder="รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)" required style="padding:12px; border:2px solid var(--color-border); border-radius:10px;">
            <input type="password" name="confirm_password" placeholder="ยืนยันรหัสผ่าน" required style="padding:12px; border:2px solid var(--color-border); border-radius:10px;">
            <button type="submit" class="btn btn-primary btn-block">สมัครสมาชิก</button>
        </form>
        <p style="text-align:center; font-size:14px; margin-top:16px;">มีบัญชีอยู่แล้ว? <a href="/login.php" style="color:var(--color-honey-dark); font-weight:700;">เข้าสู่ระบบ</a></p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
