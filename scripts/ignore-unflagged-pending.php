<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$db = get_db();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

function calculatePotentialScore(
    array $lead,
    array $potentialKeywords
): int {
    $potentialText = strtolower(
        ($lead['article_title'] ?? '') . ' ' .
        ($lead['article_excerpt'] ?? '')
    );

    $score = 0;

    foreach ($potentialKeywords as $keyword => $points) {
        if (str_contains($potentialText, $keyword)) {
            $score += $points;
        }
    }

    return $score;
}

/*
 * Re-read the current pending leads every time the page loads.
 * This ensures we operate only on rows that are still pending.
 */
$stmt = $db->query("
    SELECT
        id,
        article_title,
        article_excerpt,
        published_at
    FROM leads
    WHERE status = 'pending'
    ORDER BY published_at DESC
");

$pendingLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flagged = [];
$unflagged = [];

foreach ($pendingLeads as $lead) {
    $score = calculatePotentialScore($lead, $potentialKeywords);

    $lead['potential_score'] = $score;

    if ($score >= 3) {
        $flagged[] = $lead;
    } else {
        $unflagged[] = $lead;
    }
}

$totalPending = count($pendingLeads);
$flaggedCount = count($flagged);
$unflaggedCount = count($unflagged);

$message = null;
$error = null;
$rowsUpdated = null;

/*
 * Database changes happen ONLY through POST and only after the
 * user types the exact confirmation phrase shown on the page.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expectedConfirmation = 'IGNORE ' . $unflaggedCount;
    $providedConfirmation = trim($_POST['confirmation'] ?? '');

    if ($providedConfirmation !== $expectedConfirmation) {
        $error = "Confirmation did not match. No database changes were made.";
    } elseif ($unflaggedCount === 0) {
        $error = "There are no unflagged pending leads to update.";
    } else {
        try {
            $db->beginTransaction();

            /*
             * Update one ID at a time.
             *
             * The additional AND status = 'pending' condition protects
             * against changing a lead whose status somehow changed after
             * this page calculated the list.
             */
            $updateStmt = $db->prepare("
                UPDATE leads
                SET
                    status = 'ignored',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
                  AND status = 'pending'
            ");

            $rowsUpdated = 0;

            foreach ($unflagged as $lead) {
                $updateStmt->execute([
                    ':id' => (int) $lead['id'],
                ]);

                $rowsUpdated += $updateStmt->rowCount();
            }

            $db->commit();

            $message =
                "Success. " .
                number_format($rowsUpdated) .
                " unflagged pending leads were changed to ignored.";

            /*
             * Refresh the counts after the update.
             */
            $stmt = $db->query("
                SELECT
                    id,
                    article_title,
                    article_excerpt,
                    published_at
                FROM leads
                WHERE status = 'pending'
                ORDER BY published_at DESC
            ");

            $pendingLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $flagged = [];
            $unflagged = [];

            foreach ($pendingLeads as $lead) {
                $score = calculatePotentialScore(
                    $lead,
                    $potentialKeywords
                );

                $lead['potential_score'] = $score;

                if ($score >= 3) {
                    $flagged[] = $lead;
                } else {
                    $unflagged[] = $lead;
                }
            }

            $totalPending = count($pendingLeads);
            $flaggedCount = count($flagged);
            $unflaggedCount = count($unflagged);

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $error =
                "The update failed and the transaction was rolled back. " .
                "No partial update should have been committed. Error: " .
                $e->getMessage();
        }
    }
}

header('Content-Type: text/html; charset=utf-8');

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Ignore Unflagged Pending Leads</title>

    <style>
        body {
            font-family: system-ui, sans-serif;
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
            line-height: 1.5;
        }

        .summary,
        .warning,
        .success,
        .error {
            padding: 18px 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .summary {
            background: #f4f4f4;
        }

        .warning {
            background: #fff3cd;
        }

        .success {
            background: #d1e7dd;
        }

        .error {
            background: #f8d7da;
        }

        form {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        input[type="text"] {
            font: inherit;
            padding: 10px;
            width: 260px;
            max-width: 100%;
        }

        button {
            font: inherit;
            padding: 10px 16px;
            margin-left: 8px;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        .muted {
            color: #666;
        }
    </style>
</head>

<body>

<h1>Ignore Unflagged Pending Leads</h1>

<?php if ($message !== null): ?>
    <div class="success">
        <strong><?= htmlspecialchars($message) ?></strong>
    </div>
<?php endif; ?>

<?php if ($error !== null): ?>
    <div class="error">
        <strong><?= htmlspecialchars($error) ?></strong>
    </div>
<?php endif; ?>

<div class="summary">
    <p>
        <strong>Current pending:</strong>
        <?= number_format($totalPending) ?>
    </p>

    <p>
        <strong>Flagged and retained:</strong>
        <?= number_format($flaggedCount) ?>
    </p>

    <p>
        <strong>Unflagged that would be ignored:</strong>
        <?= number_format($unflaggedCount) ?>
    </p>
</div>

<?php if ($unflaggedCount > 0): ?>

    <div class="warning">
        <strong>Warning:</strong>
        Submitting the form below will change these
        <?= number_format($unflaggedCount) ?>
        leads from
        <code>pending</code>
        to
        <code>ignored</code>.
    </div>

    <form method="post">
        <p>
            To confirm, type:
            <strong>
                IGNORE <?= number_format($unflaggedCount, 0, '.', '') ?>
            </strong>
        </p>

        <input
            type="text"
            name="confirmation"
            autocomplete="off"
            required
        >

        <button type="submit">
            Ignore Unflagged Pending Leads
        </button>
    </form>

<?php else: ?>

    <p>
        There are currently no unflagged pending leads to update.
    </p>

<?php endif; ?>

<h2>Unflagged sample</h2>

<p class="muted">
    These are the first 100 leads that would be ignored,
    using the same published-date order as the Leads UI.
</p>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Score</th>
        <th>Published</th>
        <th>Article</th>
    </tr>
    </thead>

    <tbody>

    <?php foreach (array_slice($unflagged, 0, 100) as $lead): ?>
        <tr>
            <td>
                <?= htmlspecialchars((string) $lead['id']) ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    (string) $lead['potential_score']
                ) ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    (string) ($lead['published_at'] ?? '')
                ) ?>
            </td>

            <td>
                <?= htmlspecialchars(
                    (string) $lead['article_title']
                ) ?>
            </td>
        </tr>
    <?php endforeach; ?>

    </tbody>
</table>

</body>
</html>