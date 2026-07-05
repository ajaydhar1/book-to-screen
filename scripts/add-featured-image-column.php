<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$db = get_db();

try {
    $db->exec("
        ALTER TABLE leads
        ADD COLUMN featured_image_url TEXT
    ");

    echo "Added featured_image_url to leads table.\n";
} catch (PDOException $e) {
    echo "Leads table: " . $e->getMessage() . "\n";
}

try {
    $db->exec("
        ALTER TABLE adaptations
        ADD COLUMN featured_image_url TEXT
    ");

    echo "Added featured_image_url to adaptations table.\n";
} catch (PDOException $e) {
    echo "Adaptations table: " . $e->getMessage() . "\n";
}

echo "Migration complete.\n";