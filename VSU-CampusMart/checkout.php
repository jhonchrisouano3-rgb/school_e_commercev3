<?php
require_once 'includes/auth.php';
requireLogin();

if (!in_array($_SESSION['role'], ['student', 'teacher'], true)) {
    exit('Unauthorized.');
}

require_once 'config/paymongo.php';

$error = '';

$stmt = $pdo->prepare("
    SELECT c.id AS cart_id, c.quantity, p.id AS product_id,
           p.name, p.price, p.stock, p.seller_id
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE c.user_id = ? AND p.status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll();

if (!$items) {
    header('Location: cart.php');
    exit;
}

$total = 0;
foreach ($items as $item) {
    if ($item['quantity'] > $item['stock']) {
        exit('One or more products no longer have enough stock. Please update your cart.');
    }
    $total += $item['price'] * $item['quantity'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $delivery = $_POST['delivery_method'] ?? '';
    $payment = $_POST['payment_method'] ?? '';
    $address = trim($_POST['address'] ?? '');

    if (!in_array($delivery, ['pickup', 'cod'], true)) {
        $error = 'Select a valid delivery method.';
    } elseif ($delivery === 'cod' && $address === '') {
        $error = 'Enter your delivery address.';
    } elseif (
        ($delivery === 'pickup' && $payment !== 'pay_at_pickup') ||
        ($delivery === 'cod' && !in_array($payment, ['cash', 'paymongo'], true))
    ) {
        $error = 'Select a valid payment option.';
    } else {
        try {
            $pdo->beginTransaction();

            // Lock product rows and verify available stock again.
            $lockedItems = [];
            $grandTotal = 0;

            foreach ($items as $item) {
                $lock = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
                $lock->execute([$item['product_id']]);
                $product = $lock->fetch();

                if (!$product || $product['status'] !== 'active' || $product['stock'] < $item['quantity']) {
                    throw new Exception('Insufficient stock for ' . $item['name']);
                }

                $grandTotal += $product['price'] * $item['quantity'];
                $lockedItems[] = [
                    'product' => $product,
                    'quantity' => (int)$item['quantity']
                ];
            }

            $reference = 'VCM-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $methodName = $payment === 'paymongo'
                ? 'PayMongo'
                : ($payment === 'cash' ? 'Cash on Delivery' : 'Pay at Pickup');

            $insertOrder = $pdo->prepare("
                INSERT INTO orders
                (user_id, reference_number, total, delivery_method, address, payment_method, payment_status, order_status)
                VALUES (?, ?, ?, ?, ?, ?, 'unpaid', 'pending')
            ");

            $insertOrder->execute([
                $_SESSION['user_id'],
                $reference,
                $grandTotal,
                $delivery,
                $delivery === 'cod' ? $address : null,
                $methodName
            ]);

            $orderId = (int)$pdo->lastInsertId();

            foreach ($lockedItems as $locked) {
                $product = $locked['product'];
                $quantity = $locked['quantity'];

                $insertItem = $pdo->prepare("
                    INSERT INTO order_items
                    (order_id, product_id, seller_id, product_name, price, quantity)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $insertItem->execute([
                    $orderId,
                    $product['id'],
                    $product['seller_id'],
                    $product['name'],
                    $product['price'],
                    $quantity
                ]);

                $updateStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                $updateStock->execute([$quantity, $product['id']]);
            }

            if ($payment === 'paymongo') {
                $pdo->commit();

                $lineItems = [];

                foreach ($lockedItems as $locked) {
                    $lineItems[] = [
                        'name' => $locked['product']['name'],
                        'amount' => (int)round((float)$locked['product']['price'] * 100),
                        'currency' => 'PHP',
                        'quantity' => $locked['quantity']
                    ];
                }

                $payload = [
                    'data' => [
                        'attributes' => [
                            'line_items' => $lineItems,
                            'payment_method_types' => ['card', 'gcash', 'qrph', 'paymaya'],
                            'success_url' => CAMPUSMART_BASE_URL . '/payment_success.php?reference=' . urlencode($reference),
                            'cancel_url' => CAMPUSMART_BASE_URL . '/orders.php?payment=cancelled',
                            'reference_number' => $reference,
                            'send_email_receipt' => true,
                            'metadata' => [
                                'campusmart_order_id' => (string)$orderId,
                                'reference_number' => $reference
                            ]
                        ]
                    ]
                ];

                $ch = curl_init(PAYMONGO_API_URL);

                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_USERPWD => PAYMONGO_SECRET_KEY . ':',
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_TIMEOUT => 30
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                $result = json_decode($response ?: '', true);
                $checkoutUrl = $result['data']['attributes']['checkout_url'] ?? null;
                $sessionId = $result['data']['id'] ?? null;

                if ($curlError || $httpCode < 200 || $httpCode >= 300 || !$checkoutUrl || !$sessionId) {
                    // The order remains unpaid. Admin can investigate/refund stock if needed.
                    error_log('PayMongo error: ' . ($response ?: $curlError));
                    $error = 'Unable to start PayMongo checkout. Your order was created but payment was not started. Contact the administrator.';
                } else {
                    $update = $pdo->prepare("
                        UPDATE orders
                        SET paymongo_session_id = ?, checkout_url = ?
                        WHERE id = ?
                    ");
                    $update->execute([$sessionId, $checkoutUrl, $orderId]);

                    $clear = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                    $clear->execute([$_SESSION['user_id']]);

                    header('Location: ' . $checkoutUrl);
                    exit;
                }
            } else {
                $pdo->commit();

                $clear = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                $clear->execute([$_SESSION['user_id']]);

                header('Location: orders.php?created=1');
                exit;
            }

        } catch (Exception $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        }
    }
}

$pageTitle = 'Checkout';
require_once 'includes/header.php';
?>

<h1>Checkout</h1>

<div class="card">
    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <h2>Order Summary</h2>

    <?php foreach ($items as $item): ?>
        <p>
            <?= e($item['name']) ?> × <?= (int)$item['quantity'] ?>
            — <?= peso($item['price'] * $item['quantity']) ?>
        </p>
    <?php endforeach; ?>

    <h2>Total: <?= peso($total) ?></h2>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

        <label>Delivery Method</label>
        <select name="delivery_method" id="delivery_method" required>
            <option value="pickup">Pickup</option>
            <option value="cod">Cash on Delivery</option>
        </select>

        <div id="address_box" style="display:none;">
            <label>Delivery Address</label>
            <textarea name="address" placeholder="Enter your complete delivery address"></textarea>
        </div>

        <label>Payment Method</label>
        <select name="payment_method" id="payment_method" required>
            <option value="pay_at_pickup">Pay at Pickup</option>
        </select>

        <p id="payment_info"></p>

        <button type="submit">Place Order</button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>