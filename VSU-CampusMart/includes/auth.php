<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/VSU-CampusMart/config/database.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function peso($amount) {
    return '₱' . number_format((float)$amount, 2);
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf() {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }
}

function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        header('Location: /VSU-CampusMart/login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();

    if ($_SESSION['role'] !== $role) {
        header('Location: /VSU-CampusMart/index.php');
        exit;
    }
}

function dashboardPath($role) {
    $paths = [
        'admin' => 'admin/dashboard.php',
        'student' => 'student/dashboard.php',
        'teacher' => 'teacher/dashboard.php',
        'seller' => 'seller/dashboard.php'
    ];

    return $paths[$role] ?? 'login.php';
}


function uploadImage($file, $folder)
{
    $GLOBALS['uploadImageError'] = '';

    if (!is_array($file) || !isset($file['error'])) {
        $GLOBALS['uploadImageError'] = 'No image was received.';
        return false;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $GLOBALS['uploadImageError'] = 'PHP upload error code: ' . $file['error'];
        return false;
    }

    if (
        empty($file['tmp_name']) ||
        !is_uploaded_file($file['tmp_name'])
    ) {
        $GLOBALS['uploadImageError'] = 'Temporary uploaded file is missing.';
        return false;
    }

    $imageInfo = @getimagesize($file['tmp_name']);

    if ($imageInfo === false) {
        $GLOBALS['uploadImageError'] = 'The uploaded file is not a valid image.';
        return false;
    }

    $allowedTypes = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_BMP  => 'bmp'
    ];

    $imageType = $imageInfo[2];

    if (!isset($allowedTypes[$imageType])) {
        $GLOBALS['uploadImageError'] = 'Unsupported image format.';
        return false;
    }

    // Exact CampusMart upload location.
    $basePath = 'D:/Xampp/htdocs/VSU-CampusMart/uploads';
    $targetDir = $basePath . '/' . basename($folder);

    // Create folder if it does not exist.
    if (!is_dir($targetDir)) {
        if (!@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            $GLOBALS['uploadImageError'] = 'Cannot create folder: ' . $targetDir;
            return false;
        }
    }

    if (!is_writable($targetDir)) {
        $GLOBALS['uploadImageError'] = 'Folder is not writable: ' . $targetDir;
        return false;
    }

    $fileName = bin2hex(random_bytes(16))
        . '.' . $allowedTypes[$imageType];

    $targetPath = $targetDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $GLOBALS['uploadImageError'] = 'PHP could not move the picture into: ' . $targetPath;
        return false;
    }

    return $fileName;
}