<?php
require_once __DIR__ . '/includes/init.php';

$code = trim($_POST['coupon_code'] ?? '');
if ($code) {
    $_SESSION['applied_coupon'] = strtoupper($code);
} else {
    unset($_SESSION['applied_coupon']);
}
redirect('/cart.php');
