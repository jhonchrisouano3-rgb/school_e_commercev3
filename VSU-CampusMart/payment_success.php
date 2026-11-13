<?php
require_once 'includes/auth.php';
requireLogin();

$reference = trim($_GET['reference'] ?? '');

$stmt = $pdo->prepare("
    SELECT *
    FROM orders
    WHERE reference_number = ? AND user_id = ?
");
$stmt->execute([$reference, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    exit('Order not found.');
}

$pageTitle = 'Payment Result';
require_once 'includes/header.php';
?>

<div class="card">
    <h1>Payment Submitted</h1>

    <p>Order Reference: <strong><?= e($order['reference_number']) ?></strong></p>
    <p>Amount: <strong><?= peso($order['total']) ?></strong></p>

    <?php if ($order['payment_status'] === 'paid'): ?>
        <div class="alert">Your payment has been confirmed. Thank you for your purchase!</div>
    <?php else: ?>
        <div class="alert">
            PayMongo has redirected you back to CampusMart. Your payment is awaiting webhook confirmation.
            Refresh this page in a moment to check the updated status.
        </div>
    <?php endif; ?>

    <a class="btn" href="orders.php">View My Orders</a>
</div>

<?php require_once 'includes/footer.php'; ?>