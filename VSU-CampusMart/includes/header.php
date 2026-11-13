<?php
$pageTitle = $pageTitle ?? 'VSU CampusMart';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | VSU CampusMart</title>
    <link rel="stylesheet" href="/VSU-CampusMart/assets/style.css">
</head>
<body>

<header class="topbar">
    <a class="brand" href="/VSU-CampusMart/index.php">
        VSU <span>CampusMart</span>
    </a>

    <nav>
        <?php if (!empty($_SESSION['user_id'])): ?>
            <span class="account-name">
                <?= e($_SESSION['full_name']) ?>
            </span>
            <a href="/VSU-CampusMart/orders.php">Orders</a>
            <?php if (in_array($_SESSION['role'], ['student', 'teacher'])): ?>
                <a href="/VSU-CampusMart/cart.php">Cart</a>
            <?php endif; ?>
            <a href="/VSU-CampusMart/<?= e(dashboardPath($_SESSION['role'])) ?>">Dashboard</a>
            <a href="/VSU-CampusMart/logout.php">Logout</a>
        <?php else: ?>
            <a href="/VSU-CampusMart/login.php">Login</a>
            <a href="/VSU-CampusMart/register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>

<main class="container">