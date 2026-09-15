<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$normalizedUsername = strtolower($username);

$db = get_db();

$userStmt = $db->prepare("
    SELECT id, username, display_name, password_hash, role, is_active, must_change_password
    FROM users
    WHERE username = :username
    LIMIT 1
");
$userStmt->execute([':username' => $normalizedUsername]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (
    $user !== false
    && (bool) $user['is_active']
    && password_verify($password, $user['password_hash'])
) {
    session_regenerate_id(true);

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['display_name'] = $user['display_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['must_change_password'] = (bool) $user['must_change_password'];

    $updateLoginStmt = $db->prepare("
        UPDATE users
        SET last_login_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $updateLoginStmt->execute([':id' => $user['id']]);

    header('Location: /admin/leads.php?status=pending');
    exit;
}

header('Location: /admin/?error=invalid');
exit;