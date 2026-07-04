<?php

declare(strict_types=1);

$output = [];
$status = 0;

exec(
    'php ' . escapeshellarg(__DIR__ . '/../scripts/fetch-deadline-rss.php'),
    $output,
    $status
);

$inserted = 0;
$skipped = 0;

foreach ($output as $line) {
    if (str_starts_with($line, 'Inserted:')) {
        $inserted = (int) trim(substr($line, strlen('Inserted:')));
    }

    if (str_starts_with($line, 'Skipped existing:')) {
        $skipped = (int) trim(substr($line, strlen('Skipped existing:')));
    }
}

$query = http_build_query([
    'fetch' => $status === 0 ? 'success' : 'error',
    'inserted' => $inserted,
    'skipped' => $skipped,
]);

header('Location: leads.php?' . $query);
exit;
