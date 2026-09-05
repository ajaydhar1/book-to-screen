<?php

declare(strict_types=1);

session_start();

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: /admin/leads.php?status=pending');
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

$error = $_GET['error'] ?? '';

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Admin Login | Book to Screen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">

    <link rel="icon" type="image/png" href="/favicon.png">

    <link rel="stylesheet" href="/assets/css/admin.css">
</head>

<body>

    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <main>

        <p class="eyebrow">Editorial Administration</p>

        <h1>Book to Screen</h1>

        <p class="lede">
            Sign in to review article leads, approve adaptations, and manage the editorial workflow.
        </p>

        <section class="card">

            <h2>Administrator Login</h2>

            <?php if ($error === 'invalid'): ?>
                <div class="notice">Invalid username or password.</div>
            <?php endif; ?>

            <form method="post" action="/admin/login.php">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" autocomplete="username" required>

                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>

                <button class="button" type="submit">Sign In →</button>
            </form>

        </section>

        <footer>
            <p>
                <a href="/">← Return to Book to Screen</a>
            </p>

            <p>Authorized editorial access only.</p>
        </footer>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>