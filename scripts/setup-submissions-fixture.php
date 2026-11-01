<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/submissions/local.php';
require_once __DIR__ . '/../includes/submissions/migrations.php';

try {
    $password = getenv('B2S_LOCAL_ADMIN_PASSWORD') ?: '';
    if (strlen($password) < 12) {
        throw new RuntimeException('Set a local fixture admin password of at least 12 characters.');
    }
    $db = submission_local_database();
    submission_migrate($db, new SubmissionCrypto(getenv('B2S_SUBMISSIONS_KEY') ?: ''));
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE,
        display_name TEXT NOT NULL, password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'editor', is_active INTEGER NOT NULL DEFAULT 1,
        must_change_password INTEGER NOT NULL DEFAULT 0
    )");
    $stmt = $db->prepare("INSERT INTO users(username,display_name,password_hash,role)
        VALUES ('local-reviewer','Local Reviewer',:password,'admin')
        ON CONFLICT(username) DO UPDATE SET password_hash = :password,role = 'admin',is_active = 1,must_change_password = 0");
    $stmt->execute([':password' => password_hash($password, PASSWORD_DEFAULT)]);
    echo "Local fixture initialized. Staff username: local-reviewer\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
