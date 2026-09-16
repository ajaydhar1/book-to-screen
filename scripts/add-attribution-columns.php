<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$db = get_db();

try {
    $db->exec("
        ALTER TABLE leads
        ADD COLUMN reviewed_by_user_id INTEGER
    ");

    echo "Added reviewed_by_user_id to leads table.\n";
} catch (Throwable $e) {
    echo "Leads table: " . $e->getMessage() . "\n";
}

try {
    $db->exec("
        ALTER TABLE adaptations
        ADD COLUMN created_by_user_id INTEGER
    ");

    echo "Added created_by_user_id to adaptations table.\n";
} catch (Throwable $e) {
    echo "Adaptations table: " . $e->getMessage() . "\n";
}

echo "Migration complete.\n";
