<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$db = get_db();

function reset_password_fetch_user(Database $db, int $id): array|false
{
    $stmt = $db->prepare("
        SELECT id, display_name, username
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $id]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUserId = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

    if ($targetUserId === false || $targetUserId <= 0) {
        header('Location: /admin/users.php?notice=not_found');
        exit;
    }

    $targetUser = reset_password_fetch_user($db, $targetUserId);

    if ($targetUser === false) {
        header('Location: /admin/users.php?notice=not_found');
        exit;
    }

    $password = $_POST['password'] ?? '';
    $passwordConfirmation = $_POST['password_confirmation'] ?? '';

    $redirectBack = '/admin/reset-password.php?id=' . $targetUserId . '&notice=';

    if ($password === '' || $passwordConfirmation === '') {
        header('Location: ' . $redirectBack . 'invalid');
        exit;
    }

    if (strlen($password) < 8) {
        header('Location: ' . $redirectBack . 'weak_password');
        exit;
    }

    if ($password !== $passwordConfirmation) {
        header('Location: ' . $redirectBack . 'password_mismatch');
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $updateStmt = $db->prepare("
        UPDATE users
        SET password_hash = :password_hash,
            must_change_password = 1,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $updateStmt->execute([
        ':password_hash' => $passwordHash,
        ':id' => $targetUserId,
    ]);

    if ((int) ($_SESSION['user_id'] ?? 0) === $targetUserId) {
        // The stored hash changed, but the current session stays valid; only the flag is synced.
        $_SESSION['must_change_password'] = true;
    }

    header('Location: /admin/users.php?notice=password_reset');
    exit;
}

$targetUserId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$targetUserId || $targetUserId <= 0) {
    header('Location: /admin/users.php?notice=not_found');
    exit;
}

$user = reset_password_fetch_user($db, $targetUserId);

if ($user === false) {
    header('Location: /admin/users.php?notice=not_found');
    exit;
}

$notice = $_GET['notice'] ?? '';

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Admin | Reset Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="/assets/css/header-footer.css?v=<?= filemtime(__DIR__ . '/../assets/css/header-footer.css') ?>">
    <link rel="stylesheet" href="/assets/css/admin-nav.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin-nav.css') ?>">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f6f3ee;
            color: #1f1f1f;
        }

        input,
        button {
            font: inherit;
        }

        .admin-shell {
            max-width: 640px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .admin-header {
            margin-bottom: 24px;
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
            font-size: 34px;
        }

        .admin-header span {
            display: block;
            margin-top: 6px;
            color: #756553;
            font-size: 15px;
        }

        .panel {
            background: #fff;
            border: 1px solid #e3d8c8;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .04);
            padding: 22px;
        }

        .field {
            display: grid;
            gap: 7px;
            margin-bottom: 18px;
        }

        .field label {
            font-size: 13px;
            font-weight: 800;
            color: #4c4034;
        }

        .field input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d7c7b2;
            border-radius: 10px;
            background: #fff;
            color: #1f1f1f;
        }

        .field small {
            color: #756553;
            line-height: 1.4;
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 13px;
            border: 1px solid #d7c7b2;
            border-radius: 10px;
            background: #fff;
            color: #2b2118;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .button:hover {
            border-color: #2b2118;
        }

        .button-primary {
            background: #2b2118;
            border-color: #2b2118;
            color: #fff;
        }

        .notice {
            margin: 0 0 20px;
            padding: 14px 18px;
            border-radius: 12px;
            border: 1px solid;
            font-size: 15px;
            font-weight: 600;
        }

        .notice.error {
            background: #fef2f2;
            border-color: #f5a5a5;
            color: #991b1b;
        }

        @media (max-width: 650px) {
            .button {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>
    <?php require_once __DIR__ . '/../includes/admin-nav.php'; ?>

    <main class="admin-shell">
        <header class="admin-header">
            <p>Book-to-Screen Admin</p>
            <h1>Reset Password</h1>
            <span>Resetting the password for <?= h($user['display_name']) ?> (@<?= h($user['username']) ?>)</span>
        </header>

        <?php if ($notice === 'invalid'): ?>
            <div class="notice error">Please enter and confirm the new temporary password.</div>
        <?php elseif ($notice === 'weak_password'): ?>
            <div class="notice error">The temporary password must be at least 8 characters.</div>
        <?php elseif ($notice === 'password_mismatch'): ?>
            <div class="notice error">The passwords do not match.</div>
        <?php endif; ?>

        <section class="panel">
            <form method="post" action="/admin/reset-password.php">
                <input type="hidden" name="id" value="<?= h((string) $user['id']) ?>">

                <div class="field">
                    <label for="password">New temporary password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password">
                    <small>Must be at least 8 characters. The user will be required to change it later.</small>
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm temporary password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                </div>

                <div class="form-actions">
                    <button class="button button-primary" type="submit">Reset Password</button>
                    <a class="button" href="/admin/users.php">Cancel / Back to Users</a>
                </div>
            </form>
        </section>
    </main>
</body>

</html>
