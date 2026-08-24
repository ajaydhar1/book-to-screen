<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$db = get_db();

$potentialKeywords = [
    'adaptation'    => 5,
    'based on'      => 5,
    'optioned'      => 5,
    'novel'         => 4,
    'book'          => 3,
    'memoir'        => 3,
    'graphic novel' => 4,
    'short story'   => 4,
    'bestseller'    => 2,
    'author'        => 2,
];

$stmt = $db->query("
    SELECT id, article_title, article_excerpt, published_at
    FROM leads
    WHERE status = 'pending'
    ORDER BY published_at DESC
");

$pendingCount = 0;
$flaggedCount = 0;
$unflaggedCount = 0;
$unflagged = [];

while ($lead = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $pendingCount++;

    $potentialText = strtolower(
        ($lead['article_title'] ?? '') . ' ' .
        ($lead['article_excerpt'] ?? '')
    );

    $potentialScore = 0;

    foreach ($potentialKeywords as $keyword => $points) {
        if (str_contains($potentialText, $keyword)) {
            $potentialScore += $points;
        }
    }

    if ($potentialScore >= 3) {
        $flaggedCount++;
    } else {
        $unflaggedCount++;

        $unflagged[] = [
            'id' => $lead['id'],
            'title' => $lead['article_title'],
            'score' => $potentialScore,
        ];
    }
}

header('Content-Type: text/html; charset=utf-8');

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Preview Unflagged Pending Leads</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
            line-height: 1.5;
        }

        .summary {
            padding: 20px;
            background: #f4f4f4;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
    </style>
</head>
<body>

<h1>Unflagged Pending Leads — Preview Only</h1>

<div class="summary">
    <p><strong>Total pending:</strong> <?= number_format($pendingCount) ?></p>
    <p><strong>Flagged and retained:</strong> <?= number_format($flaggedCount) ?></p>
    <p><strong>Unflagged that would be ignored:</strong> <?= number_format($unflaggedCount) ?></p>
</div>

<p>
    <strong>No database changes have been made.</strong>
</p>

<h2>Sample of unflagged leads</h2>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Score</th>
        <th>Article</th>
    </tr>
    </thead>
    <tbody>

    <?php foreach (array_slice($unflagged, 0, 100) as $lead): ?>
        <tr>
            <td><?= htmlspecialchars((string) $lead['id']) ?></td>
            <td><?= htmlspecialchars((string) $lead['score']) ?></td>
            <td><?= htmlspecialchars($lead['title']) ?></td>
        </tr>
    <?php endforeach; ?>

    </tbody>
</table>

</body>
</html>