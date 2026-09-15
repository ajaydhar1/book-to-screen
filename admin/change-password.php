<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// A user who does not need to change their password has no reason to be here.
if (empty($_SESSION['must_change_password'])) {
    header('Location: /admin/leads.php?status=pending');
    exit;
}

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    $password = $_POST['password'] ?? '';
    $passwordConfirmation = $_POST['password_confirmation'] ?? '';

    if ($password === '' || $passwordConfirmation === '') {
        header('Location: /admin/change-password.php?notice=invalid');
        exit;
    }

    if (strlen($password) < 8) {
        header('Location: /admin/change-password.php?notice=weak_password');
        exit;
    }

    if ($password !== $passwordConfirmation) {
        header('Location: /admin/change-password.php?notice=password_mismatch');
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $updateStmt = $db->prepare("
        UPDATE users
        SET password_hash = :password_hash,
            must_change_password = 0,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $updateStmt->execute([
        ':password_hash' => $passwordHash,
        ':id' => $userId,
    ]);

    $_SESSION['must_change_password'] = false;

    header('Location: /admin/leads.php?status=pending');
    exit;
}

$notice = $_GET['notice'] ?? '';

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Admin | Change Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">

    <link rel="icon" type="image/png" href="/favicon.png">

    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
    <link rel="stylesheet" href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/../assets/css/header-footer.css') ?>">
</head>

<body>

    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <main>

        <p class="eyebrow">Editorial Administration</p>

        <h1>Password Change Required</h1>

        <p class="lede">
            Hi <?= h($_SESSION['display_name']) ?>, you must choose a new password before continuing.
        </p>

        <section class="card">

            <h2>Change Password</h2>

            <?php if ($notice === 'invalid'): ?>
                <div class="notice">Please enter and confirm your new password.</div>
            <?php elseif ($notice === 'weak_password'): ?>
                <div class="notice">The new password must be at least 8 characters.</div>
            <?php elseif ($notice === 'password_mismatch'): ?>
                <div class="notice">The passwords do not match.</div>
            <?php endif; ?>

            <form method="post" action="/admin/change-password.php">
                <label for="password">New password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required>

                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

                <button class="button" type="submit">Change Password →</button>
            </form>

        </section>

        <footer>
            <p>
                <a href="/admin/logout.php">Logout</a>
            </p>
        </footer>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>
