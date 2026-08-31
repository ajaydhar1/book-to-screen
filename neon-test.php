<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Keep credentials private in this file for the test.
// Remove this file after testing.
$dbUrl = '';

$parts = parse_url($dbUrl);

if ($parts === false) {
    die('Invalid DATABASE_URL');
}

$host = $parts['host'] ?? '';
$port = $parts['port'] ?? 5432;
$user = isset($parts['user']) ? urldecode($parts['user']) : '';
$pass = isset($parts['pass']) ? urldecode($parts['pass']) : '';
$db   = ltrim($parts['path'] ?? '', '/');

$dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require;options=endpoint%3Dep-curly-mode-awekn99n-pooler";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $count = $pdo
        ->query('SELECT COUNT(*) FROM leads')
        ->fetchColumn();

    echo 'Neon connection OK. Leads: ' . $count;
} catch (Throwable $e) {
    echo '<pre>';
    echo htmlspecialchars(
        get_class($e) . ': ' . $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    );
    echo '</pre>';
}