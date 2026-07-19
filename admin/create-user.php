<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Replace this with your real authorization helper.
/*
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('You do not have permission to create users.');
}
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/users.php');
    exit;
}

$displayName = trim($_POST['display_name'] ?? '');
$username = strtolower(trim($_POST['username'] ?? ''));
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? 'editor';
$password = $_POST['password'] ?? '';
$passwordConfirmation = $_POST['password_confirmation'] ?? '';

$isActive = isset($_POST['is_active']) ? 1 : 0;
$mustChangePassword = isset($_POST['must_change_password']) ? 1 : 0;

$allowedRoles = ['admin', 'editor'];

if (
    $displayName === ''
    || $username === ''
    || $password === ''
    || !in_array($role, $allowedRoles, true)
) {
    header('Location: /admin/users.php?notice=invalid');
    exit;
}

if (!preg_match('/^[a-z0-9._-]+$/', $username)) {
    header('Location: /admin/users.php?notice=invalid_username');
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: /admin/users.php?notice=invalid_email');
    exit;
}

if (strlen($password) < 8) {
    header('Location: /admin/users.php?notice=weak_password');
    exit;
}

if ($password !== $passwordConfirmation) {
    header('Location: /admin/users.php?notice=password_mismatch');
    exit;
}

$db = get_db();

$existingUserStmt = $db->prepare("
    SELECT id
    FROM users
    WHERE username = :username
    LIMIT 1
");

$existingUserStmt->execute([
    ':username' => $username,
]);

if ($existingUserStmt->fetch() !== false) {
    header('Location: /admin/users.php?notice=username_exists');
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$insertStmt = $db->prepare("
    INSERT INTO users (
        display_name,
        username,
        email,
        password_hash,
        role,
        is_active,
        must_change_password,
        created_by_user_id
    )
    VALUES (
        :display_name,
        :username,
        :email,
        :password_hash,
        :role,
        :is_active,
        :must_change_password,
        :created_by_user_id
    )
");

$insertStmt->execute([
    ':display_name' => $displayName,
    ':username' => $username,
    ':email' => $email !== '' ? $email : null,
    ':password_hash' => $passwordHash,
    ':role' => $role,
    ':is_active' => $isActive,
    ':must_change_password' => $mustChangePassword,
    ':created_by_user_id' => null,
]);
// ':created_by_user_id' => $_SESSION['user_id'] ?? null,

header('Location: /admin/users.php?notice=created');
exit;