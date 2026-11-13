<?php
require_once 'includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $schoolId = trim($_POST['school_id'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE school_id = ?");
    $stmt->execute([$schoolId]);
    $user = $stmt->fetch();

    if ($user) {
        $validPassword = password_verify($password, $user['password']);

        // Upgrade the original demo administrator's legacy plaintext password.
        if (!$validPassword && hash_equals($user['password'], $password)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$newHash, $user['id']]);
            $validPassword = true;
        }

        if ($validPassword && $user['status'] === 'approved') {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['school_id'] = $user['school_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            header('Location: ' . dashboardPath($user['role']));
            exit;
        }

        if ($validPassword && $user['status'] === 'pending') {
            $error = 'Your account is waiting for administrator approval.';
        } elseif ($validPassword && $user['status'] === 'rejected') {
            $error = 'Your registration was rejected. Please contact the administrator.';
        } else {
            $error = 'Invalid School ID Number or password.';
        }
    } else {
        $error = 'Invalid School ID Number or password.';
    }
}

$pageTitle = 'Login';
require_once 'includes/header.php';
?>

<div class="card auth-box">
    <h2>Login to VSU CampusMart</h2>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

        <label>School ID Number</label>
        <input type="text" name="school_id" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit">Login</button>
    </form>

    <p>Don't have an account? <a href="register.php">Register here</a></p>
</div>

<?php require_once 'includes/footer.php'; ?>