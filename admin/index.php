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

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f6f3ee;
            color: #222;
        }

        main {
            max-width: 760px;
            margin: 0 auto;
            padding: 80px 24px;
        }

        .eyebrow {
            margin: 0;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: 13px;
            font-weight: 700;
            color: #7a5c3e;
        }

        h1 {
            margin: 12px 0 20px;
            font-size: 52px;
            line-height: 1.1;
        }

        .lede {
            font-size: 20px;
            line-height: 1.7;
            color: #444;
            margin-bottom: 36px;
        }

        .card {
            background: white;
            border: 1px solid #e3d8c8;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .05);
        }

        .card h2 {
            margin-top: 0;
        }

        label {
            display: block;
            margin: 18px 0 6px;
            font-weight: 700;
        }

        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d7c7b2;
            border-radius: 10px;
            font: inherit;
        }

        .button {
            display: inline-block;
            margin-top: 26px;
            padding: 12px 18px;
            background: #2b2118;
            color: white;
            border: 0;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
        }

        .notice {
            margin-bottom: 18px;
            padding: 12px 14px;
            background: #fef2f2;
            border: 1px solid #f5a5a5;
            color: #991b1b;
            border-radius: 10px;
        }

        footer {
            margin-top: 50px;
            color: #777;
            font-size: 14px;
        }

        @media (max-width: 520px) {
            main {
                padding-top: 52px;
            }

            h1 {
                font-size: 40px;
            }

            .lede {
                font-size: 18px;
            }
        }

        footer a {
            color: #7a5c3e;
            text-decoration: none;
            font-weight: 600;
        }

        footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

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

</body>

</html>