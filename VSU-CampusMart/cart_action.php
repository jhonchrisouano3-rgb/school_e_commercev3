<?php
require_once 'includes/auth.php';
requireLogin();

if (!in_array($_SESSION['role'], ['student', 'teacher'], true)) {
    exit('Unauthorized.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}

verifyCsrf();

$action = $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];

if ($action === 'add') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    $stmt = $pdo->prepare("
        SELECT id, stock
        FROM products
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product || $quantity > $product['stock']) {
        exit('Product unavailable or insufficient stock.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO cart (user_id, product_id, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ");
    $stmt->execute([$userId, $productId, $quantity]);

} elseif ($action === 'update') {
    $cartId = (int)($_POST['cart_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    $stmt = $pdo->prepare("
        SELECT c.id, p.stock
        FROM cart c
        JOIN products p ON p.id = c.product_id
        WHERE c.id = ? AND c.user_id = ?
    ");
    $stmt->execute([$cartId, $userId]);
    $item = $stmt->fetch();

    if ($item) {
        $quantity = min($quantity, (int)$item['stock']);

        $update = $pdo->prepare("
            UPDATE cart
            SET quantity = ?
            WHERE id = ? AND user_id = ?
        ");
        $update->execute([$quantity, $cartId, $userId]);
    }

} elseif ($action === 'remove') {
    $cartId = (int)($_POST['cart_id'] ?? 0);

    $stmt = $pdo->prepare("
        DELETE FROM cart
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$cartId, $userId]);
}

header('Location: cart.php');
exit;