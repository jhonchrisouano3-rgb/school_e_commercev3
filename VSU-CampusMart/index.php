<?php
require_once 'includes/auth.php';

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT p.*, u.full_name AS seller_name
    FROM products p
    JOIN users u ON u.id = p.seller_id
    WHERE p.status = 'active' AND p.stock > 0
";

$params = [];

if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$pageTitle = 'Home';
require_once 'includes/header.php';
?>

<h1>VSU CampusMart</h1>
<p>Your online school supplies marketplace.</p>

<form method="GET" class="card">
    <label>Search Products</label>
    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search books, uniforms, and supplies">
    <button type="submit">Search</button>
</form>

<div class="grid">
    <?php foreach ($products as $product): ?>
        <div class="card">
            <?php if ($product['image']): ?>
                <img class="product-image" src="/VSU-CampusMart/<?= e($product['image']) ?>" alt="">
            <?php endif; ?>

            <h3><?= e($product['name']) ?></h3>
            <p><?= e($product['description']) ?></p>
            <p><strong><?= peso($product['price']) ?></strong></p>
            <p>Stock: <?= (int)$product['stock'] ?></p>
            <p>Seller: <?= e($product['seller_name']) ?></p>

            <?php if (!empty($_SESSION['user_id']) && in_array($_SESSION['role'], ['student', 'teacher'])): ?>
                <form method="POST" action="cart_action.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                    <input type="hidden" name="action" value="add">
                    <label>Quantity</label>
                    <input type="number" name="quantity" value="1" min="1" max="<?= (int)$product['stock'] ?>">
                    <button type="submit">Add to Cart</button>
                </form>
            <?php elseif (empty($_SESSION['user_id'])): ?>
                <a class="btn" href="login.php">Login to Purchase</a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if (!$products): ?>
        <div class="card">No products found.</div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>