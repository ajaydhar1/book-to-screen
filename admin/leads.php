<?php

require_once __DIR__ . '/../includes/db.php';

$db = get_db();

$allowedStatuses = ['all', 'pending', 'ignored', 'approved', 'rejected'];
$currentStatus = $_GET['status'] ?? 'all';

if (!in_array($currentStatus, $allowedStatuses, true)) {
    $currentStatus = 'all';
}

$countStmt = $db->query("
    SELECT status, COUNT(*) AS total
    FROM leads
    GROUP BY status
");

$statusCounts = [
    'pending' => 0,
    'ignored' => 0,
    'approved' => 0,
    'rejected' => 0,
];

foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $statusCounts[$row['status']] = (int) $row['total'];
}

$totalLeads = array_sum($statusCounts);

if ($currentStatus === 'all') {
    $stmt = $db->query("
        SELECT
            id,
            article_title,
            article_url,
            article_excerpt,
            published_at,
            status,
            notes,
            created_at
        FROM leads
        ORDER BY published_at DESC
    ");
} else {
    $stmt = $db->prepare("
        SELECT
            id,
            article_title,
            article_url,
            article_excerpt,
            published_at,
            status,
            notes,
            created_at
        FROM leads
        WHERE status = :status
        ORDER BY published_at DESC
    ");

    $stmt->execute(['status' => $currentStatus]);
}

$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h(string|null $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function status_class(string $status): string
{
    return match ($status) {
        'pending' => 'status-pending',
        'approved' => 'status-approved',
        'rejected' => 'status-rejected',
        'ignored' => 'status-ignored',
        default => 'status-default',
    };
}

function filter_url(string $status): string
{
    return '?status=' . urlencode($status);
}

?>
<!doctype html>
<html lang="en">

<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-LRF3X9CMCT"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-LRF3X9CMCT');
    </script>

    <meta charset="utf-8">
    <title>Admin | Leads</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">

    <link rel="icon" type="image/png" href="/favicon.png">

    <style>
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f6f3ee;
            color: #1f1f1f;
        }

        .admin-shell {
            max-width: 1120px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .admin-header {
            margin-bottom: 28px;
        }

        .admin-header p {
            margin: 0 0 8px;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: 13px;
            color: #7a5c3e;
            font-weight: 700;
        }

        .admin-header h1 {
            margin: 0;
            font-size: 38px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #e3d8c8;
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .04);
        }

        .stat-card span {
            display: block;
            margin-bottom: 8px;
            color: #756553;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .stat-card strong {
            font-size: 30px;
        }

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 24px;
        }

        .filter-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 13px;
            border: 1px solid #d7c7b2;
            border-radius: 999px;
            background: #fff;
            color: #2b2118;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .filter-link.active,
        .filter-link:hover {
            background: #2b2118;
            border-color: #2b2118;
            color: #fff;
        }

        .lead-list {
            display: grid;
            gap: 18px;
        }

        .lead-card {
            background: #fff;
            border: 1px solid #e3d8c8;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .04);
        }

        .lead-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 13px;
            color: #666;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .status-pending {
            background: #fff3cd;
            color: #7a5600;
        }

        .status-approved {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-rejected {
            background: #f8d7da;
            color: #842029;
        }

        .status-ignored {
            background: #e2e3e5;
            color: #41464b;
        }

        .status-default {
            background: #e7f1ff;
            color: #084298;
        }

        .lead-card h2 {
            margin: 0 0 10px;
            font-size: 22px;
            line-height: 1.25;
        }

        .lead-card h2 a {
            color: #1f1f1f;
            text-decoration: none;
        }

        .lead-card h2 a:hover {
            text-decoration: underline;
        }

        .excerpt {
            margin: 0 0 14px;
            color: #444;
            line-height: 1.55;
        }

        .notes {
            margin: 14px 0;
            padding: 12px 14px;
            background: #faf7f1;
            border-left: 4px solid #c9a66b;
            border-radius: 8px;
            color: #3d342a;
        }

        .lead-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 13px;
            border-radius: 10px;
            border: 1px solid #d7c7b2;
            background: #fff;
            color: #2b2118;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: default;
        }

        .button-primary {
            background: #2b2118;
            border-color: #2b2118;
            color: #fff;
        }

        .button-muted {
            opacity: .65;
        }

        .empty-state {
            background: #fff;
            border: 1px dashed #c9a66b;
            border-radius: 16px;
            padding: 24px;
            color: #555;
        }

        @media (max-width: 850px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 520px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .admin-header h1 {
                font-size: 32px;
            }
        }
    </style>
</head>

<body>
    <main class="admin-shell">
        <header class="admin-header">
            <p>Book-to-Screen Admin</p>
            <h1>Article Leads</h1>
        </header>

        <section class="stats-grid" aria-label="Lead summary">
            <div class="stat-card">
                <span>Total</span>
                <strong><?= h((string) $totalLeads) ?></strong>
            </div>

            <div class="stat-card">
                <span>Pending</span>
                <strong><?= h((string) $statusCounts['pending']) ?></strong>
            </div>

            <div class="stat-card">
                <span>Ignored</span>
                <strong><?= h((string) $statusCounts['ignored']) ?></strong>
            </div>

            <div class="stat-card">
                <span>Approved</span>
                <strong><?= h((string) $statusCounts['approved']) ?></strong>
            </div>

            <div class="stat-card">
                <span>Rejected</span>
                <strong><?= h((string) $statusCounts['rejected']) ?></strong>
            </div>
        </section>

        <nav class="filter-bar" aria-label="Lead filters">
            <?php foreach (['all', 'pending', 'ignored', 'approved', 'rejected'] as $status): ?>
                <a
                    class="filter-link <?= $currentStatus === $status ? 'active' : '' ?>"
                    href="<?= h(filter_url($status)) ?>">
                    <?= h(ucfirst($status)) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (empty($leads)): ?>
            <div class="empty-state">
                No leads found for this filter.
            </div>
        <?php else: ?>
            <section class="lead-list">
                <?php foreach ($leads as $lead): ?>
                    <article class="lead-card">
                        <div class="lead-meta">
                            <span class="status-badge <?= h(status_class($lead['status'])) ?>">
                                <?= h($lead['status']) ?>
                            </span>

                            <?php if (!empty($lead['published_at'])): ?>
                                <span>Published: <?= h($lead['published_at']) ?></span>
                            <?php endif; ?>

                            <span>Lead #<?= h((string) $lead['id']) ?></span>
                        </div>

                        <h2>
                            <a href="<?= h($lead['article_url']) ?>" target="_blank" rel="noopener">
                                <?= h($lead['article_title']) ?>
                            </a>
                        </h2>

                        <?php if (!empty($lead['article_excerpt'])): ?>
                            <p class="excerpt"><?= h($lead['article_excerpt']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($lead['notes'])): ?>
                            <p class="notes"><?= h($lead['notes']) ?></p>
                        <?php endif; ?>

                        <div class="lead-actions">
                            <a class="button button-primary" href="<?= h($lead['article_url']) ?>" target="_blank" rel="noopener">
                                View Article
                            </a>

                            <span class="button button-muted">Approve</span>
                            <span class="button button-muted">Reject</span>
                            <span class="button button-muted">Ignore</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</body>

</html>