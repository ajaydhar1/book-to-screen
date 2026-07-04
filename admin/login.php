<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/config.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (
    $username === ADMIN_USERNAME &&
    password_verify($password, ADMIN_PASSWORD_HASH)
) {
    $_SESSION['admin_logged_in'] = true;

    header('Location: /admin/leads.php?status=pending');
    exit;
}

header('Location: /admin/?error=invalid');
exit;