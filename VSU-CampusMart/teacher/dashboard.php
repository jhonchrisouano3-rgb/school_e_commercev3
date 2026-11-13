
<?php
require_once '../includes/auth.php';

requireRole('teacher');


$stmt = $pdo->query("
    SELECT *
    FROM products
    ORDER BY id DESC
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Teacher Dashboard';
require_once '../includes/header.php';
?>

<div class="container">

    <h2>Teacher Dashboard</h2>
    <h3>Available School Supplies</h3>

    <div class="product-grid">

        <?php if (count($products) > 0): ?>

            <?php foreach ($products as $product): ?>

                <div class="card product-card">

                    <?php if (!empty($product['image'])): ?>
                        <img
                            src="/VSU-CampusMart/uploads/products/<?= e($product['image']) ?>"
                            alt="<?= e($product['name']) ?>"
                            style="width:100%; height:180px; object-fit:cover; border-radius:8px;"
                        >
                    <?php else: ?>
                        <p>No product picture available.</p>
                    <?php endif; ?>

                    <h3><?= e($product['name']) ?></h3>

                    <p><?= e($product['description']) ?></p>

                    <p>
                        <strong>Price:</strong>
                        <?= peso($product['price']) ?>
                    </p>

                    <p>
                        <strong>Stock:</strong>
                        <?= (int) $product['stock'] ?>
                    </p>

                    <?php if ((int) $product['stock'] > 0): ?>
                        <form action="../cart_action.php" method="POST">

                            <input type="hidden" name="csrf_token"
                                   value="<?= e(csrfToken()) ?>">

                            <input type="hidden" name="product_id"
                                   value="<?= (int) $product['id'] ?>">

                            <input type="hidden" name="action" value="add">

                            <label>Quantity</label>
                            <input type="number" name="quantity"
                                   value="1" min="1"
                                   max="<?= (int) $product['stock'] ?>"
                                   required>

                            <button type="submit">Add to Cart</button>
                        </form>
                    <?php else: ?>
                        <p>Out of Stock</p>
                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <p>No products available yet.</p>

        <?php endif; ?>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>
