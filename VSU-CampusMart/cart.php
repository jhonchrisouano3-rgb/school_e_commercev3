<?php
require_once 'includes/auth.php';
requireLogin();

if (!in_array($_SESSION['role'], ['student', 'teacher'], true)) {
    exit('Unauthorized.');
}

$stmt = $pdo->prepare("
    SELECT c.id AS cart_id, c.quantity, p.id AS product_id,
           p.name, p.price, p.stock, p.image
    FROM cart c
    JOIN products p ON p.id = c.product_id
    WHERE c.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll();

$total = 0;

$pageTitle = 'Shopping Cart';
require_once 'includes/header.php';
?>

<h1>Shopping Cart</h1>

<?php if (!$items): ?>
    <div class="card">
        <p>Your cart is empty.</p>
        <a class="btn" href="index.php">Browse Products</a>
    </div>
<?php else: ?>

    <div class="card">
        <?php foreach ($items as $item): ?>
            <?php
            $subtotal = $item['price'] * $item['quantity'];
            $total += $subtotal;
            ?>

            <div class="card">
                <h3><?= e($item['name']) ?></h3>
                <p>Price: <?= peso($item['price']) ?></p>
                <p>Subtotal: <?= peso($subtotal) ?></p>
                <p>Available Stock: <?= (int)$item['stock'] ?></p>

                <form method="POST" action="cart_action.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="cart_id" value="<?= (int)$item['cart_id'] ?>">
                    <input type="hidden" name="action" value="update">
                    <label>Quantity</label>
                    <input type="number" name="quantity" min="1"
                           max="<?= (int)$item['stock'] ?>"
                           value="<?= (int)$item['quantity'] ?>" required>
                    <button type="submit">Update Quantity</button>
                </form>

                <form method="POST" action="cart_action.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="cart_id" value="<?= (int)$item['cart_id'] ?>">
                    <input type="hidden" name="action" value="remove">
                    <button class="btn-danger" type="submit" data-confirm="Remove this product from your cart?">
                        Remove
                    </button>
                </form>
            </div>
        <?php endforeach; ?>

        <h2>Total: <?= peso($total) ?></h2>
        <a class="btn" href="checkout.php">Proceed to Checkout</a>
    </div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>