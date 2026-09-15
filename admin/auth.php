<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: /admin/');
    exit;
}

// A user with a pending forced password change may only use change-password.php
// until it is cleared, to avoid an infinite redirect loop on that page itself.
if (!empty($_SESSION['must_change_password']) && basename($_SERVER['SCRIPT_NAME']) !== 'change-password.php') {
    header('Location: /admin/change-password.php');
    exit;
}

// Gate for admin-only pages/actions. Requires a database-backed session
// (set during login) whose role is admin.
function require_admin(): void
{
    if (($_SESSION['role'] ?? null) !== 'admin') {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}