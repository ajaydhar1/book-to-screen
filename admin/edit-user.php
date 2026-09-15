<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$db = get_db();

function edit_user_fetch(Database $db, int $id): array|false
{
    $stmt = $db->prepare("
        SELECT id, display_name, username, email, role, is_active, must_change_password, created_at, last_login_at
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $id]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function edit_user_other_active_admins(Database $db, int $excludeId): int
{
    $stmt = $db->prepare("
        SELECT COUNT(*) AS active_admin_count
        FROM users
        WHERE role = 'admin' AND is_active = 1 AND id != :id
    ");

    $stmt->execute([':id' => $excludeId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int) ($row['active_admin_count'] ?? 0);
}

$allowedRoles = ['admin', 'editor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUserId = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

    if ($targetUserId === false || $targetUserId <= 0) {
        header('Location: /admin/users.php?notice=not_found');
        exit;
    }

    $existingUser = edit_user_fetch($db, $targetUserId);

    if ($existingUser === false) {
        header('Location: /admin/users.php?notice=not_found');
        exit;
    }

    $displayName = trim($_POST['display_name'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $userIsActive = isset($_POST['is_active']) ? 1 : 0;

    $redirectBack = '/admin/edit-user.php?id=' . $targetUserId . '&notice=';

    if ($displayName === '' || $username === '' || !in_array($role, $allowedRoles, true)) {
        header('Location: ' . $redirectBack . 'invalid');
        exit;
    }

    if (!preg_match('/^[a-z0-9._-]+$/', $username)) {
        header('Location: ' . $redirectBack . 'invalid_username');
        exit;
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ' . $redirectBack . 'invalid_email');
        exit;
    }

    $duplicateStmt = $db->prepare("
        SELECT id
        FROM users
        WHERE username = :username AND id != :id
        LIMIT 1
    ");

    $duplicateStmt->execute([
        ':username' => $username,
        ':id' => $targetUserId,
    ]);

    if ($duplicateStmt->fetch() !== false) {
        header('Location: ' . $redirectBack . 'username_exists');
        exit;
    }

    $wouldRemainActiveAdmin = ($role === 'admin' && $userIsActive === 1);

    if (!$wouldRemainActiveAdmin && edit_user_other_active_admins($db, $targetUserId) === 0) {
        header('Location: ' . $redirectBack . 'last_admin');
        exit;
    }

    $updateStmt = $db->prepare("
        UPDATE users
        SET display_name = :display_name,
            username = :username,
            email = :email,
            role = :role,
            is_active = :is_active,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $updateStmt->execute([
        ':display_name' => $displayName,
        ':username' => $username,
        ':email' => $email !== '' ? $email : null,
        ':role' => $role,
        ':is_active' => $userIsActive,
        ':id' => $targetUserId,
    ]);

    $isEditingSelf = (int) ($_SESSION['user_id'] ?? 0) === $targetUserId;

    if ($isEditingSelf) {
        if ($userIsActive === 0) {
            // An admin who just deactivated their own account cannot keep an authenticated session.
            $_SESSION = [];
            session_destroy();
            header('Location: /admin/?notice=account_deactivated');
            exit;
        }

        $_SESSION['username'] = $username;
        $_SESSION['display_name'] = $displayName;
        $_SESSION['role'] = $role;

        if ($role !== 'admin') {
            header('Location: /admin/leads.php?status=pending&notice=role_changed');
            exit;
        }
    }

    header('Location: /admin/users.php?notice=user_updated');
    exit;
}

$targetUserId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$targetUserId || $targetUserId <= 0) {
    header('Location: /admin/users.php?notice=not_found');
    exit;
}

$user = edit_user_fetch($db, $targetUserId);

if ($user === false) {
    header('Location: /admin/users.php?notice=not_found');
    exit;
}

$otherActiveAdmins = edit_user_other_active_admins($db, $targetUserId);
$isLastActiveAdmin = $user['role'] === 'admin'
    && (int) $user['is_active'] === 1
    && $otherActiveAdmins === 0;

$notice = $_GET['notice'] ?? '';

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Admin | Edit User</title>
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
        select,
        button {
            font: inherit;
        }

        .admin-shell {
            max-width: 720px;
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .field {
            display: grid;
            gap: 7px;
        }

        .field label {
            font-size: 13px;
            font-weight: 800;
            color: #4c4034;
        }

        .field input,
        .field select {
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

        .checkbox-field {
            display: flex;
            align-items: center;
            gap: 9px;
            padding-top: 26px;
            font-weight: 700;
        }

        .checkbox-field.is-disabled {
            opacity: .55;
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
            .form-grid {
                grid-template-columns: 1fr;
            }

            .checkbox-field {
                padding-top: 0;
            }

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
            <h1>Edit User</h1>
            <span>Editing @<?= h($user['username']) ?> (#<?= h((string) $user['id']) ?>)</span>
        </header>

        <?php if ($notice === 'invalid'): ?>
            <div class="notice error">Please complete all required fields.</div>
        <?php elseif ($notice === 'invalid_username'): ?>
            <div class="notice error">
                Usernames may contain letters, numbers, periods, underscores, and hyphens.
            </div>
        <?php elseif ($notice === 'invalid_email'): ?>
            <div class="notice error">Please enter a valid email address.</div>
        <?php elseif ($notice === 'username_exists'): ?>
            <div class="notice error">That username is already in use.</div>
        <?php elseif ($notice === 'last_admin'): ?>
            <div class="notice error">
                This is the last active Administrator. Their role cannot be changed and their account cannot be deactivated.
            </div>
        <?php endif; ?>

        <section class="panel">
            <form method="post" action="/admin/edit-user.php">
                <input type="hidden" name="id" value="<?= h((string) $user['id']) ?>">

                <div class="form-grid">
                    <div class="field">
                        <label for="display_name">Display name</label>
                        <input
                            id="display_name"
                            name="display_name"
                            type="text"
                            autocomplete="name"
                            value="<?= h($user['display_name']) ?>">
                    </div>

                    <div class="field">
                        <label for="username">Username</label>
                        <input
                            id="username"
                            name="username"
                            type="text"
                            autocomplete="off"
                            value="<?= h($user['username']) ?>">
                        <small>Must be unique. Lowercase letters, numbers, periods, hyphens, and underscores are safest.</small>
                    </div>

                    <div class="field">
                        <label for="email">Email address <span aria-hidden="true">(optional)</span></label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autocomplete="email"
                            value="<?= h((string) $user['email']) ?>">
                    </div>

                    <div class="field">
                        <label for="role">Role</label>
                        <select id="role" name="role" <?= $isLastActiveAdmin ? 'disabled' : '' ?>>
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="editor" <?= $user['role'] === 'editor' ? 'selected' : '' ?>>Editor</option>
                        </select>

                        <?php if ($isLastActiveAdmin): ?>
                            <small>This is the last active Administrator, so the role is locked to Admin.</small>
                        <?php endif; ?>

                        <?php if ($isLastActiveAdmin): ?>
                            <input type="hidden" name="role" value="admin">
                        <?php endif; ?>
                    </div>

                    <label class="checkbox-field<?= $isLastActiveAdmin ? ' is-disabled' : '' ?>">
                        <input
                            name="is_active"
                            type="checkbox"
                            value="1"
                            <?= $user['is_active'] ? 'checked' : '' ?>
                            <?= $isLastActiveAdmin ? 'disabled' : '' ?>>
                        Account is active

                        <?php if ($isLastActiveAdmin): ?>
                            <input type="hidden" name="is_active" value="1">
                        <?php endif; ?>
                    </label>
                </div>

                <div class="form-actions">
                    <button class="button button-primary" type="submit">Save Changes</button>
                    <a class="button" href="/admin/users.php">Cancel / Back to Users</a>
                </div>
            </form>
        </section>
    </main>
</body>

</html>
