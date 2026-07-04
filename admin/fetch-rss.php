<?php

declare(strict_types=1);

$output = [];
$status = 0;

exec(
    'php ' . escapeshellarg(__DIR__ . '/../scripts/fetch-deadline-rss.php'),
    $output,
    $status
);

header('Location: leads.php');
exit;