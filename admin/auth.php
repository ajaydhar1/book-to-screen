<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: /admin/');
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