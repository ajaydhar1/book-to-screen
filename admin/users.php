<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$db = get_db();

$userStmt = $db->query("
    SELECT
        id,
        display_name,
        username,
        email,
        role,
        is_active,
        must_change_password,
        last_login_at,
        created_at
    FROM users
    ORDER BY
        CASE role
            WHEN 'admin' THEN 0
            ELSE 1
        END,
        display_name COLLATE NOCASE ASC
");

$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

function user_datetime(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return 'Never';
    }

    return (new DateTimeImmutable($datetime, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone(TIMEZONE))
        ->format('M j, Y \a\t g:i A');
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'editor' => 'Editor',
        default => ucfirst($role),
    };
}

$activeUsers = count(array_filter(
    $users,
    static fn(array $user): bool => $user['is_active'] === true
));

$adminUsers = count(array_filter(
    $users,
    static fn(array $user): bool => $user['role'] === 'admin' && $user['is_active'] === true
));

$editorUsers = count(array_filter(
    $users,
    static fn(array $user): bool => $user['role'] === 'editor' && $user['is_active'] === true
));

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin | Users</title>
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
            color: #1f1f1f;
        }

        input,
        select,
        button {
            font: inherit;
        }

        .admin-shell {
            max-width: 1120px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .admin-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
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
            font-size: 38px;
        }

        .view-site-link {
            flex-shrink: 0;
            margin-bottom: 4px;
            color: #7a5c3e;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .view-site-link:hover {
            color: #2b2118;
            text-decoration: underline;
        }

        .admin-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 24px;
        }

        .admin-nav a {
            display: inline-flex;
            align-items: center;
            padding: 9px 13px;
            border: 1px solid #d7c7b2;
            border-radius: 999px;
            background: #fff;
            color: #2b2118;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .admin-nav a.active,
        .admin-nav a:hover {
            background: #2b2118;
            border-color: #2b2118;
            color: #fff;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .stat-card,
        .panel,
        .user-card {
            background: #fff;
            border: 1px solid #e3d8c8;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .04);
        }

        .stat-card {
            padding: 18px;
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

        .panel {
            margin-bottom: 24px;
            padding: 22px;
        }

        .panel-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
        }

        .panel-heading h2 {
            margin: 0;
            font-size: 21px;
        }

        .panel-heading p {
            margin: 6px 0 0;
            color: #756553;
            line-height: 1.5;
        }

        details[open] .add-user-summary {
            margin-bottom: 22px;
        }

        .add-user-summary {
            list-style: none;
            cursor: pointer;
        }

        .add-user-summary::-webkit-details-marker {
            display: none;
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

        .button-danger {
            color: #842029;
        }

        .button-muted {
            opacity: .58;
            cursor: default;
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

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .prototype-note {
            margin: 18px 0 0;
            padding: 12px 14px;
            border-left: 4px solid #c9a66b;
            border-radius: 8px;
            background: #faf7f1;
            color: #3d342a;
            line-height: 1.5;
        }

        .section-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 18px;
            margin: 0 0 14px;
        }

        .section-heading h2 {
            margin: 0;
            font-size: 23px;
        }

        .section-heading p {
            margin: 0;
            color: #756553;
            font-size: 14px;
        }

        .user-list {
            display: grid;
            gap: 16px;
        }

        .user-card {
            padding: 20px 22px;
        }

        .user-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 18px;
        }

        .user-identity h3 {
            margin: 0 0 5px;
            font-size: 21px;
        }

        .user-identity p {
            margin: 0;
            color: #756553;
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .badge-admin {
            background: #e7f1ff;
            color: #084298;
        }

        .badge-editor {
            background: #f3e8ff;
            color: #6b21a8;
        }

        .badge-active {
            background: #d1e7dd;
            color: #0f5132;
        }

        .badge-inactive {
            background: #e2e3e5;
            color: #41464b;
        }

        .badge-password {
            background: #fff3cd;
            color: #7a5600;
        }

        .user-details {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .user-detail {
            padding: 12px 13px;
            border: 1px solid #eee4d7;
            border-radius: 11px;
            background: #faf7f1;
        }

        .user-detail span {
            display: block;
            margin-bottom: 5px;
            color: #756553;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .user-detail strong {
            display: block;
            overflow-wrap: anywhere;
            font-size: 14px;
            line-height: 1.4;
        }

        .user-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        @media (max-width: 850px) {
            .stats-grid,
            .user-details {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 650px) {
            .admin-header,
            .panel-heading,
            .user-card-header,
            .section-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .form-grid,
            .stats-grid,
            .user-details {
                grid-template-columns: 1fr;
            }

            .admin-header h1 {
                font-size: 32px;
            }

            .badges {
                justify-content: flex-start;
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
    <main class="admin-shell">
        <header class="admin-header">
            <div>
                <p>Book-to-Screen Admin</p>
                <h1>User Management</h1>
            </div>

            <a class="view-site-link" href="/" target="_blank" rel="noopener">
                View Site ↗
            </a>
        </header>

        <nav class="admin-nav" aria-label="Admin sections">
            <a href="/admin/leads.php">Article Leads</a>
            <a class="active" href="/admin/users.php" aria-current="page">Users</a>
        </nav>

        <section class="stats-grid" aria-label="User summary">
            <div class="stat-card">
                <span>Total Users</span>
                <strong><?= h((string) count($users)) ?></strong>
            </div>

            <div class="stat-card">
                <span>Active</span>
                <strong><?= h((string) $activeUsers) ?></strong>
            </div>

            <div class="stat-card">
                <span>Admins</span>
                <strong><?= h((string) $adminUsers) ?></strong>
            </div>

            <div class="stat-card">
                <span>Editors</span>
                <strong><?= h((string) $editorUsers) ?></strong>
            </div>
        </section>

        <details class="panel">
            <summary class="add-user-summary">
                <div class="panel-heading">
                    <div>
                        <h2>Add User</h2>
                        <p>Create a named account and assign its initial access level.</p>
                    </div>

                    <span class="button button-primary">+ Add User</span>
                </div>
            </summary>

            <form action="#" method="post">
                <div class="form-grid">
                    <div class="field">
                        <label for="display_name">Display name</label>
                        <input id="display_name" name="display_name" type="text" autocomplete="name" placeholder="Sarah C.">
                    </div>

                    <div class="field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" autocomplete="off" placeholder="sarah">
                        <small>Must be unique. Lowercase letters, numbers, periods, hyphens, and underscores are safest.</small>
                    </div>

                    <div class="field">
                        <label for="email">Email address <span aria-hidden="true">(optional)</span></label>
                        <input id="email" name="email" type="email" autocomplete="email" placeholder="name@example.com">
                        <small>Useful later for invitations and password recovery, but not required for the first release.</small>
                    </div>

                    <div class="field">
                        <label for="role">Role</label>
                        <select id="role" name="role">
                            <option value="editor" selected>Editor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="password">Temporary password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password">
                        <small>The final handler will hash this value. It will never be retrievable or displayed.</small>
                    </div>

                    <div class="field">
                        <label for="password_confirm">Confirm temporary password</label>
                        <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password">
                    </div>

                    <label class="checkbox-field">
                        <input name="must_change_password" type="checkbox" value="1" checked>
                        Require password change at first login
                    </label>

                    <label class="checkbox-field">
                        <input name="is_active" type="checkbox" value="1" checked>
                        Account is active
                    </label>
                </div>

                <div class="form-actions">
                    <button class="button button-primary button-muted" type="button" aria-disabled="true">
                        Create User (prototype)
                    </button>
                </div>

                <p class="prototype-note">
                    This form is visual only for now. After we approve the fields, we can create the users table,
                    validation rules, CSRF protection, and the actual create-user handler.
                </p>
            </form>
        </details>

        <div class="section-heading">
            <h2>Current Users</h2>
            <p>Passwords are never shown.</p>
        </div>

        <section class="user-list" aria-label="Current users">
            <?php foreach ($users as $user): ?>
                <article class="user-card">
                    <div class="user-card-header">
                        <div class="user-identity">
                            <h3><?= h($user['display_name']) ?></h3>
                            <p>@<?= h($user['username']) ?></p>
                        </div>

                        <div class="badges">
                            <span class="badge badge-<?= h($user['role']) ?>">
                                <?= h(role_label($user['role'])) ?>
                            </span>

                            <span class="badge <?= $user['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>

                            <?php if ($user['must_change_password']): ?>
                                <span class="badge badge-password">Password change required</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="user-details">
                        <div class="user-detail">
                            <span>Email</span>
                            <strong><?= h($user['email'] !== '' ? $user['email'] : 'Not provided') ?></strong>
                        </div>

                        <div class="user-detail">
                            <span>Last Login</span>
                            <strong><?= h(user_datetime($user['last_login_at'])) ?></strong>
                        </div>

                        <div class="user-detail">
                            <span>Created</span>
                            <strong><?= h(user_datetime($user['created_at'])) ?></strong>
                        </div>

                        <div class="user-detail">
                            <span>User ID</span>
                            <strong>#<?= h((string) $user['id']) ?></strong>
                        </div>
                    </div>

                    <div class="user-actions">
                        <button class="button button-muted" type="button" aria-disabled="true">Edit User</button>
                        <button class="button button-muted" type="button" aria-disabled="true">Reset Password</button>

                        <?php if ($user['role'] === 'admin' && $adminUsers === 1): ?>
                            <button
                                class="button button-muted"
                                type="button"
                                aria-disabled="true"
                                title="The final active administrator cannot be deactivated">
                                Protected Admin
                            </button>
                        <?php else: ?>
                            <button class="button button-danger button-muted" type="button" aria-disabled="true">
                                <?= $user['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>