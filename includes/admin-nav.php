<?php

$adminCurrentPage = basename($_SERVER['PHP_SELF']);

$adminNavItems = [
    'leads.php' => [
        'label' => 'Leads',
        'href' => '/admin/leads.php',
    ],
    'users.php' => [
        'label' => 'Users',
        'href' => '/admin/users.php',
    ],
    'cron-status.php' => [
        'label' => 'Cron Status',
        'href' => '/admin/cron-status.php',
    ],
    'database.php' => [
        'label' => 'Database',
        'href' => '/admin/database.php',
    ],
];
?>

<nav class="admin-subnav" aria-label="Admin sections">
    <div class="admin-subnav__inner">
        <?php foreach ($adminNavItems as $page => $item): ?>
            <?php $isActive = $page === $adminCurrentPage; ?>
            <a
                class="admin-subnav__link<?= $isActive ? ' is-active' : ''; ?>"
                href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                <?= $isActive ? 'aria-current="page"' : ''; ?>>
                <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
