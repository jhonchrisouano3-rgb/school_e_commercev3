<?php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        exit('Invalid request.');
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    header('Location: /VSU-CampusMart/login.php');
    exit;
}

require_once 'includes/auth.php';
$pageTitle = 'Logout';
require_once 'includes/header.php';
?>

<div class="card auth-box">
    <h2>Logout Confirmation</h2>
    <p>Are you sure you want to log out?</p>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <button type="submit">Yes, Logout</button>
        <a class="btn btn-secondary" href="index.php">Cancel</a>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>