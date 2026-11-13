<?php
require_once '../includes/auth.php';
requireRole('admin');

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'approval') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (in_array($status, ['approved', 'rejected'], true)) {
            $stmt = $pdo->prepare("
                UPDATE users
                SET status = ?
                WHERE id = ? AND role IN ('student', 'teacher', 'seller')
            ");
            $stmt->execute([$status, $userId]);
            $message = 'Account status updated.';
        }
    }

    if ($action === 'order_status') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $status = $_POST['order_status'] ?? '';

        $allowed = ['pending', 'processing', 'ready', 'shipped', 'completed', 'cancelled'];

        if (in_array($status, $allowed, true)) {
            $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
            $stmt->execute([$status, $orderId]);
            $message = 'Order status updated.';
        }
    }
}

$counts = [];

foreach (['student', 'teacher', 'seller'] as $role) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM users WHERE role = ? AND status = 'pending'");
    $stmt->execute([$role]);
    $counts[$role] = $stmt->fetch()['total'];
}

function adminUsers($pdo, $role) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? ORDER BY created_at DESC");
    $stmt->execute([$role]);
    return $stmt->fetchAll();
}

$students = adminUsers($pdo, 'student');
$teachers = adminUsers($pdo, 'teacher');
$sellers = adminUsers($pdo, 'seller');

$products = $pdo->query("
    SELECT p.*, u.full_name AS seller_name
    FROM products p
    JOIN users u ON u.id = p.seller_id
    ORDER BY p.created_at DESC
")->fetchAll();

$orders = $pdo->query("
    SELECT o.*, u.full_name, u.school_id
    FROM orders o
    JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
")->fetchAll();

$pageTitle = 'Admin Dashboard';
require_once '../includes/header.php';
?>

<h1>Administrator Dashboard</h1>
<p>Welcome, <?= e($_SESSION['full_name']) ?>!</p>

<?php if ($error): ?>
    <div class="alert error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert"><?= e($message) ?></div>
<?php endif; ?>

<div class="grid">
    <div class="card">
        <h2><?= count($students) ?></h2>
        <p>Students</p>
        <p>Pending: <?= (int)$counts['student'] ?></p>
    </div>

    <div class="card">
        <h2><?= count($teachers) ?></h2>
        <p>Teachers</p>
        <p>Pending: <?= (int)$counts['teacher'] ?></p>
    </div>

    <div class="card">
        <h2><?= count($sellers) ?></h2>
        <p>Sellers</p>
        <p>Pending: <?= (int)$counts['seller'] ?></p>
    </div>

    <div class="card">
        <h2><?= count($products) ?></h2>
        <p>Products</p>
    </div>

    <div class="card">
        <h2><?= count($orders) ?></h2>
        <p>Orders</p>
    </div>
</div>

<?php
function renderApprovalTable($users, $title) {
    ?>
    <h2><?= e($title) ?></h2>
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>School ID</th>
                    <th>Name</th>
                    <th>School Picture</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= e($user['school_id']) ?></td>
                    <td><?= e($user['full_name']) ?></td>
                    <td>
                        <?php if (!empty($user['school_picture'])): ?>
                            <a href="/VSU-CampusMart/uploads/school_ids/<?= e($user['school_picture']) ?>" target="_blank">
                                <img
                                    src="/VSU-CampusMart/uploads/school_ids/<?= e($user['school_picture']) ?>"
                                    alt="School ID Picture"
                                    width="120"
                                    style="cursor:pointer; border-radius:8px;"
            >
        </a>
    <?php else: ?>
        No school picture uploaded.
    <?php endif; ?>
                    </td>
                    <td><span class="status <?= e($user['status']) ?>"><?= e($user['status']) ?></span></td>
                    <td><?= e($user['created_at']) ?></td>
                    <td>
                        <?php if ($user['status'] !== 'approved'): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="approval">
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <input type="hidden" name="status" value="approved">
                                <button type="submit">Approve</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($user['status'] !== 'rejected'): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="approval">
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <input type="hidden" name="status" value="rejected">
                                <button class="btn-danger" type="submit" data-confirm="Reject this account?">
                                    Reject
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$users): ?>
                <tr><td colspan="6">No registered accounts.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

renderApprovalTable($students, 'Student / Buyer Accounts');
renderApprovalTable($teachers, 'Teacher Accounts');
renderApprovalTable($sellers, 'Seller Accounts');
?>

<h2>All Products</h2>
<div class="card">
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Seller</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $product): ?>
            <tr>
                <td><?= e($product['name']) ?></td>
                <td><?= e($product['seller_name']) ?></td>
                <td><?= peso($product['price']) ?></td>
                <td><?= (int)$product['stock'] ?></td>
                <td><?= e($product['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<h2>Manage Orders</h2>
<div class="card">
    <table>
        <thead>
            <tr>
                <th>Reference</th>
                <th>Customer</th>
                <th>School ID</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Order Status</th>
                <th>Update</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td><?= e($order['reference_number']) ?></td>
                <td><?= e($order['full_name']) ?></td>
                <td><?= e($order['school_id']) ?></td>
                <td><?= peso($order['total']) ?></td>
                <td><?= e($order['payment_status']) ?></td>
                <td><?= e($order['order_status']) ?></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="order_status">
                        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                        <select name="order_status">
                            <?php foreach (['pending','processing','ready','shipped','completed','cancelled'] as $status): ?>
                                <option value="<?= e($status) ?>"
                                    <?= $order['order_status'] === $status ? 'selected' : '' ?>>
                                    <?= e(ucfirst($status)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit">Update</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>