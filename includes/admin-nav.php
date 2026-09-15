<?php

$adminCurrentPage = basename($_SERVER['PHP_SELF']);
$adminIsAdmin = ($_SESSION['role'] ?? null) === 'admin';

$adminNavItems = [
    'leads.php' => [
        'label' => 'Leads',
        'href' => '/admin/leads.php',
    ],
];

// These sections expose system administration and are hidden from Editors.
// The destination endpoints also enforce require_admin() server-side.
if ($adminIsAdmin) {
    $adminNavItems['users.php'] = [
        'label' => 'Users',
        'href' => '/admin/users.php',
    ];

    $adminNavItems['cron-status.php'] = [
        'label' => 'Cron Status',
        'href' => '/admin/cron-status.php',
    ];

    $adminNavItems['database.php'] = [
        'label' => 'Database',
        'href' => '/admin/database.php',
    ];
}
?>

<nav class="admin-subnav" aria-label="Admin sections">
    <div class="admin-subnav__inner">
        <?php foreach ($adminNavItems as $adminPage => $item): ?>
            <?php $isActive = $adminPage === $adminCurrentPage; ?>
            <a
                class="admin-subnav__link<?= $isActive ? ' is-active' : ''; ?>"
                href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                <?= $isActive ? 'aria-current="page"' : ''; ?>>
                <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endforeach; ?>

        <a class="admin-subnav__link admin-subnav__logout" href="/admin/logout.php">
            Logout
        </a>
    </div>
</nav>

<div id="admin-nav-loading" class="admin-nav-loading" role="status" aria-live="polite" hidden>
    <div class="admin-nav-loading__spinner" aria-hidden="true"></div>
    <span class="admin-nav-loading__text">Loading&hellip;</span>
</div>

<script>
(function () {
    var overlay = document.getElementById('admin-nav-loading');
    if (!overlay) {
        return;
    }

    function showOverlay() {
        overlay.hidden = false;
    }

    function hideOverlay() {
        overlay.hidden = true;
    }

    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0) {
            return;
        }
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        var link = event.target.closest ? event.target.closest('.admin-subnav__link') : null;
        if (!link || link.getAttribute('aria-current') === 'page' || link.target === '_blank') {
            return;
        }

        showOverlay();
    });

    window.addEventListener('pageshow', hideOverlay);
})();
</script>
