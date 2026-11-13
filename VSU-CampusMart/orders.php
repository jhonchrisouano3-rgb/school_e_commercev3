<?php
require_once 'includes/auth.php';
requireLogin();

if ($_SESSION['role'] === 'admin') {
    $stmt = $pdo->query("
        SELECT o.*, u.full_name, u.school_id
        FROM orders o
        JOIN users u ON u.id = o.user_id
        ORDER BY o.created_at DESC
    ");
    $orders = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT *
        FROM orders
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll();
}

$pageTitle = 'Orders';
require_once 'includes/header.php';
?>

<h1><?= $_SESSION['role'] === 'admin' ? 'All Orders' : 'My Purchase History' ?></h1>

<?php if (isset($_GET['created'])): ?>
    <div class="alert">Your order was successfully placed.</div>
<?php endif; ?>

<?php if (isset($_GET['payment']) && $_GET['payment'] === 'cancelled'): ?>
    <div class="alert error">Payment was cancelled. Your order remains unpaid.</div>
<?php endif; ?>

<div class="card">
    <?php if (!$orders): ?>
        <p>No orders found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <th>Customer</th>
                        <th>School ID</th>
                    <?php endif; ?>
                    <th>Total</th>
                    <th>Delivery</th>
                    <th>Payment</th>
                    <th>Order Status</th>
                    <th>Date</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= e($order['reference_number']) ?></td>

                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <td><?= e($order['full_name']) ?></td>
                            <td><?= e($order['school_id']) ?></td>
                        <?php endif; ?>

                        <td><?= peso($order['total']) ?></td>
                        <td><?= e(strtoupper($order['delivery_method'])) ?></td>
                        <td>
                            <?= e($order['payment_method']) ?>
                            <br>
                            <span class="status <?= e($order['payment_status']) ?>">
                                <?= e(ucfirst($order['payment_status'])) ?>
                            </span>
                        </td>
                        <td><?= e(ucfirst($order['order_status'])) ?></td>
                        <td><?= e($order['created_at']) ?></td>
                        <td>
                            <a href="orders.php?view=<?= (int)$order['id'] ?>">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
if (isset($_GET['view'])):
    $viewId = (int)$_GET['view'];

    if ($_SESSION['role'] === 'admin') {
        $detail = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $detail->execute([$viewId]);
    } else {
        $detail = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
        $detail->execute([$viewId, $_SESSION['user_id']]);
    }

    $selectedOrder = $detail->fetch();

    if ($selectedOrder):
        $lineQuery = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $lineQuery->execute([$viewId]);
        $lineItems = $lineQuery->fetchAll();
?>
    <div class="card">
        <h2>Order Details: <?= e($selectedOrder['reference_number']) ?></h2>

        <?php foreach ($lineItems as $line): ?>
            <p>
                <?= e($line['product_name']) ?>
                × <?= (int)$line['quantity'] ?>
                — <?= peso($line['price'] * $line['quantity']) ?>
            </p>
        <?php endforeach; ?>

        <h3>Total: <?= peso($selectedOrder['total']) ?></h3>
        <p>Payment: <?= e($selectedOrder['payment_status']) ?></p>
        <p>Delivery: <?= e($selectedOrder['delivery_method']) ?></p>
        <?php if ($selectedOrder['address']): ?>
            <p>Address: <?= e($selectedOrder['address']) ?></p>
        <?php endif; ?>

        <?php if ($selectedOrder['checkout_url'] && $selectedOrder['payment_status'] !== 'paid'): ?>
            <a class="btn" href="<?= e($selectedOrder['checkout_url']) ?>">Continue PayMongo Checkout</a>
        <?php endif; ?>
    </div>
<?php
    endif;
endif;
?>

<?php require_once 'includes/footer.php'; ?>