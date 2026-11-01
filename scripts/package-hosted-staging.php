<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$destination = $argv[1] ?? '';
$parent = realpath(dirname($destination));
if ($parent === false || basename($destination) !== '__author-staging' || file_exists($destination)
    || str_starts_with(strtolower($parent) . DIRECTORY_SEPARATOR, strtolower($root) . DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Use a new __author-staging folder under an existing private external directory.');
}
$files = [];
foreach (['author-submissions', 'includes/submissions', 'includes/database'] as $directory) {
    foreach (glob($root . '/' . $directory . '/*.php') as $file) {
        $relative = substr($file, strlen($root) + 1);
        if (in_array(basename($file), ['turnstile-challenge.php', 'smtp-test.php', 'hosted-check.php'], true)) continue;
        $files[$relative] = $file;
    }
}
foreach (['admin/submissions.php', 'includes/functions.php', 'includes/book-metadata.php', 'submission-prototype/lookup.php', 'submission-prototype/index.php',
    'submission-prototype/local-only.php', 'favicon.png', 'assets/css/site.css',
    'assets/css/submission-prototype.css', 'assets/css/admin-nav.css', 'assets/css/submission-review.css',
    'assets/js/submission-prototype.js', 'assets/js/submission-intake.js', 'vendor/autoload.php',
    'vendor/composer/LICENSE', 'vendor/phpmailer/phpmailer/LICENSE'] as $relative) {
    $files[$relative] = $root . '/' . $relative;
}
foreach (['vendor/composer', 'vendor/sqlitecloud/sqlitecloud/src', 'vendor/phpmailer/phpmailer/src'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $files[$relative] = $file->getPathname();
        }
    }
}
if (!mkdir($destination, 0700)) throw new RuntimeException('Package creation failed.');
foreach (['gateway.php', '.htaccess', 'app/.htaccess', 'app/mailbox.php'] as $name) {
    $target = $destination . '/' . $name;
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true)) throw new RuntimeException('Package directory failed.');
    if (!copy($root . '/__author-staging/' . $name, $target)) throw new RuntimeException('Package source failed.');
}
foreach ($files as $relative => $source) {
    $relative = str_replace('\\', '/', $relative);
    $target = $destination . '/app/' . $relative;
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true)) throw new RuntimeException('Package directory failed.');
    if (!is_file($source)) throw new RuntimeException('Missing package dependency: ' . $relative);
    if (str_ends_with($relative, '.php')) {
        $contents = file_get_contents($source);
        if (str_starts_with($relative, 'author-submissions/') || $relative === 'admin/submissions.php'
            || $relative === 'submission-prototype/index.php') {
            $contents = str_replace(['/assets/', '/admin/submissions.php', '/author-submissions/staff-login.php'],
                ['/__author-staging/assets/', '/__author-staging/admin/submissions.php', '/__author-staging/author-submissions/staff-login.php'], $contents);
        }
        if (file_put_contents($target, $contents) === false) throw new RuntimeException('Package write failed.');
    } elseif (!copy($source, $target)) throw new RuntimeException('Asset copy failed.');
}
require_once $destination . '/app/includes/submissions/staging-migration.php';
$migrationSource = '/includes/submissions/migrations.php';
if (!hash_equals(hash_file('sha256', $root . $migrationSource),
    hash_file('sha256', $destination . '/app' . $migrationSource))) {
    throw new RuntimeException('Packaged migration source bytes differ; do not upload.');
}
$migrations = submission_migrations();
if (array_keys($migrations) !== [1, 2]) throw new RuntimeException('Unexpected packaged migration versions; do not upload.');
foreach ($migrations as $version => $statements) {
    if (!hash_equals(SUBMISSION_STAGING_APPROVED_CHECKSUMS[$version], hash('sha256', implode("\n", $statements)))) {
        throw new RuntimeException('Packaged migration approval checksum differs; do not upload.');
    }
}
$manifest = array_keys($files);
sort($manifest);
echo "PACKAGED_OFFLINE: source only; no credentials, Cloud connection, deployment or activation.\n";
foreach (array_merge(['gateway.php', '.htaccess', 'app/.htaccess', 'app/mailbox.php'],
    array_map(static fn(string $path): string => 'app/' . str_replace('\\', '/', $path), $manifest)) as $path) echo $path . "\n";
