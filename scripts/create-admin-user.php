<?php

require_once __DIR__ . '/../includes/db.php';

$db = get_db();

$passwordHash = password_hash('Books&Movies2026!', PASSWORD_DEFAULT);

$stmt = $db->prepare("
    INSERT INTO users (
        display_name,
        username,
        password_hash,
        role,
        is_active,
        must_change_password
    )
    VALUES (
        :display_name,
        :username,
        :password_hash,
        :role,
        :is_active,
        :must_change_password
    )
");

$stmt->execute([
    ':display_name' => 'Ajay Dhar',
    ':username' => 'ajay',
    ':password_hash' => $passwordHash,
    ':role' => 'admin',
    ':is_active' => 1,
    ':must_change_password' => 0,
]);

echo "Admin user created.\n";