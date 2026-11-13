
<?php
require_once '../includes/auth.php';

requireRole('seller');

$sellerId = $_SESSION['user_id'];
$error = '';
$success = '';

// ADD PRODUCT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    verifyCsrf();

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);

    if ($name === '' || $price <= 0 || $stock < 0) {
        $error = 'Please enter valid product information.';
    } else {
        $image = uploadImage($_FILES['product_image'] ?? null, 'products');

        if (!$image) {
            $error = $GLOBALS['uploadImageError'] ?? 'Product picture upload failed.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO products
                (seller_id, name, description, price, stock, image)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $sellerId, $name, $description,
                $price, $stock, $image
            ]);

            $success = 'Product added successfully.';
        }
    }
}

// EDIT PRODUCT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    verifyCsrf();

    $productId = (int) ($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT * FROM products
        WHERE id = ? AND seller_id = ?
    ");
    $stmt->execute([$productId, $sellerId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $error = 'Product not found.';
    } elseif ($name === '' || $price <= 0 || $stock < 0) {
        $error = 'Please enter valid product information.';
    } else {
        $image = $product['image'];

        // Replace picture only if a new one is uploaded
        if (
            isset($_FILES['product_image']) &&
            $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            $newImage = uploadImage($_FILES['product_image'], 'products');

            if (!$newImage) {
                $error = $GLOBALS['uploadImageError'] ?? 'New picture upload failed.';
            } else {
                $image = $newImage;
            }
        }

        if ($error === '') {
            $stmt = $pdo->prepare("
                UPDATE products
                SET name = ?, description = ?, price = ?,
                    stock = ?, image = ?
                WHERE id = ? AND seller_id = ?
            ");

            $stmt->execute([
                $name, $description, $price,
                $stock, $image, $productId, $sellerId
            ]);

            $success = 'Product updated successfully.';
        }
    }
}

// DELETE PRODUCT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    verifyCsrf();

    $productId = (int) ($_POST['product_id'] ?? 0);

    $stmt = $pdo->prepare("
        DELETE FROM products
        WHERE id = ? AND seller_id = ?
    ");
    $stmt->execute([$productId, $sellerId]);

    $success = $stmt->rowCount() > 0
        ? 'Product deleted successfully.'
        : 'Product not found.';
}

// GET PRODUCTS
$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE seller_id = ?
    ORDER BY id DESC
");
$stmt->execute([$sellerId]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GET PRODUCT TO EDIT
$editProduct = null;

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM products
        WHERE id = ? AND seller_id = ?
    ");
    $stmt->execute([$editId, $sellerId]);
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = 'Seller Dashboard';
require_once '../includes/header.php';
?>

<div class="container">

    <h2>Seller Dashboard</h2>
    <p>Manage your products and product pictures.</p>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert"><?= e($success) ?></div>
    <?php endif; ?>

    <!-- ADD PRODUCT -->
    <?php if (!$editProduct): ?>
    <div class="card">
        <h3>Add New Product</h3>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token"
                   value="<?= e(csrfToken()) ?>">

            <label>Product Name</label>
            <input type="text" name="name" required>

            <label>Description</label>
            <textarea name="description" required></textarea>

            <label>Price (₱)</label>
            <input type="number" name="price"
                   min="0.01" step="0.01" required>

            <label>Stock Quantity</label>
            <input type="number" name="stock"
                   min="0" required>

            <label>Product Picture</label>
            <input type="file" name="product_image"
                   accept="image/jpeg,image/png,image/webp" required>

            <button type="submit" name="add_product">
                Add Product
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- EDIT PRODUCT -->
    <?php if ($editProduct): ?>
    <div class="card">
        <h3>Edit Product</h3>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token"
                   value="<?= e(csrfToken()) ?>">

            <input type="hidden" name="product_id"
                   value="<?= (int) $editProduct['id'] ?>">

            <label>Product Name</label>
            <input type="text" name="name"
                   value="<?= e($editProduct['name']) ?>" required>

            <label>Description</label>
            <textarea name="description" required><?= e($editProduct['description']) ?></textarea>

            <label>Price (₱)</label>
            <input type="number" name="price"
                   value="<?= e($editProduct['price']) ?>"
                   min="0.01" step="0.01" required>

            <label>Stock Quantity</label>
            <input type="number" name="stock"
                   value="<?= (int) $editProduct['stock'] ?>"
                   min="0" required>

            <label>Current Product Picture</label><br>

            <?php if (!empty($editProduct['image'])): ?>
                <img
                    src="/VSU-CampusMart/uploads/products/<?= e($editProduct['image']) ?>"
                    alt="Current Product"
                    style="width:150px;height:120px;object-fit:cover;border-radius:8px;"
                >
            <?php else: ?>
                <p>No picture available.</p>
            <?php endif; ?>

            <br><br>

            <label>Change Product Picture (Optional)</label>
            <input type="file" name="product_image"
                   accept="image/jpeg,image/png,image/webp">

            <button type="submit" name="update_product">
                Save Changes
            </button>

            <a href="dashboard.php">Cancel</a>
        </form>
    </div>
    <?php endif; ?>

    <!-- PRODUCT LIST -->
    <div class="card">
        <h3>My Products</h3>

        <?php if ($products): ?>

            <div class="product-grid">

                <?php foreach ($products as $product): ?>

                    <div class="card product-card">

                        <?php if (!empty($product['image'])): ?>
                            <img
                                src="/VSU-CampusMart/uploads/products/<?= e($product['image']) ?>"
                                alt="<?= e($product['name']) ?>"
                                style="width:100%;height:180px;object-fit:cover;border-radius:8px;"
                            >
                        <?php else: ?>
                            <p>No product picture available.</p>
                        <?php endif; ?>

                        <h3><?= e($product['name']) ?></h3>

                        <p><?= e($product['description']) ?></p>

                        <p><strong>Price:</strong>
                            <?= peso($product['price']) ?>
                        </p>

                        <p><strong>Stock:</strong>
                            <?= (int) $product['stock'] ?>
                        </p>

                        <a href="dashboard.php?edit=<?= (int) $product['id'] ?>">
                            <button type="button">Edit Product</button>
                        </a>

                        <form method="POST"
                              onsubmit="return confirm('Are you sure you want to delete this product?');">

                            <input type="hidden" name="csrf_token"
                                   value="<?= e(csrfToken()) ?>">

                            <input type="hidden" name="product_id"
                                   value="<?= (int) $product['id'] ?>">

                            <button type="submit" name="delete_product">
                                Delete Product
                            </button>
                        </form>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>
            <p>You have not added any products yet.</p>
        <?php endif; ?>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>
