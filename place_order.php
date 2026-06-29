<?php
/**
 * place_order.php
 * สร้างคำสั่งซื้อ, ตัดสต็อกสินค้า, สุ่มเปิดเผยผล Blind Box จากสต็อกจริง
 * ทุกอย่างอยู่ใน transaction เดียวกันเพื่อป้องกัน race condition ตอนสต็อกใกล้หมด
 */
require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/cart.php');
}

$itemsStmt = $pdo->prepare("
    SELECT ci.cart_item_id, ci.quantity, p.product_id, p.name, p.price, p.sale_price, p.stock_qty, p.is_preorder, p.product_type
    FROM cart_items ci JOIN products p ON p.product_id = ci.product_id
    WHERE ci.cart_token = ?
");
$itemsStmt->execute([$cartToken]);
$items = $itemsStmt->fetchAll();

if (empty($items)) {
    redirect('/cart.php');
}

$couponCode = $_SESSION['applied_coupon'] ?? '';
$couponDiscount = 0;
if ($couponCode) {
    $cStmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
    $cStmt->execute([$couponCode]);
    $coupon = $cStmt->fetch();
    if ($coupon) {
        $subtotalCheck = array_sum(array_map(fn($i) => ($i['sale_price'] ?? $i['price']) * $i['quantity'], $items));
        if ($subtotalCheck >= $coupon['min_purchase']) {
            $couponDiscount = $coupon['discount_type'] === 'percent'
                ? $subtotalCheck * ($coupon['discount_value'] / 100)
                : $coupon['discount_value'];
            $couponDiscount = min($couponDiscount, $subtotalCheck);
        }
    }
}
$totals = calculateCartTotals($items, $couponDiscount);

$recipientName = sanitize($_POST['recipient_name'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$addressLine = sanitize($_POST['address_line'] ?? '');
$paymentMethod = $_POST['payment_method'] ?? 'bank_transfer';
$guestEmail = sanitize($_POST['guest_email'] ?? '');

if (!$recipientName || !$phone || !$addressLine) {
    flash('checkout_error', 'กรุณากรอกข้อมูลผู้รับสินค้าให้ครบถ้วน');
    redirect('/checkout.php');
}

if (!in_array($paymentMethod, ['bank_transfer', 'qr', 'cod'])) {
    $paymentMethod = 'bank_transfer';
}

// อัปโหลดสลิป (ถ้ามี)
$slipPath = null;
if (!empty($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
    $slipPath = uploadProductImage($_FILES['payment_slip']);
} elseif (!empty($_FILES['payment_slip_qr']) && $_FILES['payment_slip_qr']['error'] === UPLOAD_ERR_OK) {
    $slipPath = uploadProductImage($_FILES['payment_slip_qr']);
}

try {
    $pdo->beginTransaction();

    // ล็อกแถวสินค้าที่เกี่ยวข้องเพื่อป้องกันการขายเกินสต็อก (race condition)
    $orderCode = generateOrderCode();
    $orderStmt = $pdo->prepare("
        INSERT INTO orders (order_code, member_id, guest_name, guest_email, guest_phone, recipient_name, shipping_address,
            subtotal, discount_amount, shipping_fee, total_amount, coupon_code, payment_method, payment_slip, order_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $initialStatus = ($paymentMethod === 'cod') ? 'processing' : 'pending';
    $orderStmt->execute([
        $orderCode,
        currentMemberId(),
        isLoggedIn() ? null : $recipientName,
        isLoggedIn() ? null : $guestEmail,
        isLoggedIn() ? null : $phone,
        $recipientName,
        $addressLine,
        $totals['subtotal'],
        $totals['discount'],
        $totals['shipping_fee'],
        $totals['total'],
        $couponCode ?: null,
        $paymentMethod,
        $slipPath,
        $initialStatus,
    ]);
    $orderId = $pdo->lastInsertId();

    foreach ($items as $item) {
        // ล็อกแถวสินค้าแบบ FOR UPDATE เพื่อตัดสต็อกอย่างปลอดภัย
        $lockStmt = $pdo->prepare("SELECT stock_qty, is_preorder FROM products WHERE product_id = ? FOR UPDATE");
        $lockStmt->execute([$item['product_id']]);
        $locked = $lockStmt->fetch();

        if (!$locked) {
            throw new Exception("ไม่พบสินค้า: " . $item['name']);
        }
        if (!$locked['is_preorder'] && $locked['stock_qty'] < $item['quantity']) {
            throw new Exception("สินค้า \"{$item['name']}\" มีไม่พอในสต็อก (เหลือ {$locked['stock_qty']} ชิ้น)");
        }

        // ตัดสต็อกสินค้าหลักทันที
        if (!$locked['is_preorder']) {
            $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ?")
                ->execute([$item['quantity'], $item['product_id']]);
        }

        $unitPrice = $item['sale_price'] ?? $item['price'];
        $isBlindType = in_array($item['product_type'], ['blind_single', 'check_card']);

        for ($i = 0; $i < $item['quantity']; $i++) {
            $revealedVariantId = null;
            $isRevealed = 0;

            // สุ่มเปิดเผยผลจากสต็อกตัวละครจริง เฉพาะคำสั่งซื้อที่ "ไม่ใช่ COD รอเก็บเงิน"
            // (เปิดเผยเมื่อคำสั่งซื้อถูกบันทึกเป็นการซื้อที่สมบูรณ์แล้วเท่านั้น)
            if ($isBlindType) {
                $variant = revealBlindBoxVariant($pdo, $item['product_id']);
                if ($variant) {
                    $revealedVariantId = $variant['variant_id'];
                    $isRevealed = 1;
                }
            }

            $oiStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, revealed_variant_id, is_revealed)
                VALUES (?, ?, ?, ?, 1, ?, ?)
            ");
            $oiStmt->execute([$orderId, $item['product_id'], $item['name'], $unitPrice, $revealedVariantId, $isRevealed]);
        }
    }

    // ถ้าใช้คูปอง อัปเดตจำนวนการใช้งาน
    if ($couponCode && $couponDiscount > 0 && isset($coupon)) {
        $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE coupon_id = ?")->execute([$coupon['coupon_id']]);
    }

    // เคลียร์ตะกร้า
    $pdo->prepare("DELETE FROM cart_items WHERE cart_token = ?")->execute([$cartToken]);

    $pdo->commit();
    unset($_SESSION['applied_coupon']);

    redirect('/order_success.php?order_code=' . urlencode($orderCode));

} catch (Exception $e) {
    $pdo->rollBack();
    flash('checkout_error', 'ไม่สามารถสั่งซื้อได้: ' . $e->getMessage());
    redirect('/checkout.php');
}
