<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    submission_require_post_csrf();
    if (($_POST['logout'] ?? '') === '1') {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: staff-login.php', true, 303);
        exit;
    }
    try {
        submission_rate_limit($submissionDb, new SubmissionCrypto(getenv('B2S_SUBMISSIONS_KEY')),
            'staff_login', $_SERVER['REMOTE_ADDR'], time(), 5, 900);
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        if (!is_string($username) || !is_string($password) || strlen($username) > 100 || strlen($password) > 1024) {
            throw new SubmissionInputException(['login' => 'Invalid credentials.']);
        }
        $stmt = $submissionDb->prepare('SELECT id,display_name,password_hash,role,is_active,must_change_password FROM users WHERE username = :username');
        $stmt->execute([':username' => strtolower(trim($username))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password_hash']) && $user['role'] === 'admin'
            && (int) $user['is_active'] === 1 && (int) $user['must_change_password'] === 0) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['role'] = 'admin';
            $_SESSION['must_change_password'] = false;
            $_SESSION['submission_csrf'] = bin2hex(random_bytes(32));
            header('Location: ../admin/submissions.php', true, 303);
            exit;
        }
        $notice = 'Invalid local administrator credentials.';
        http_response_code(401);
    } catch (SubmissionRateException $e) {
        $notice = $e->getMessage();
        http_response_code(429);
    } catch (SubmissionInputException $e) {
        $notice = 'Invalid local administrator credentials.';
        http_response_code(401);
    } catch (Throwable $e) {
        error_log('Local submission staff login failed.');
        http_response_code(503);
        $notice = 'Local staff authentication is unavailable.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method not allowed.');
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Local staff login | Book to Screen</title><link rel="stylesheet" href="../assets/css/site.css"><link rel="stylesheet" href="../assets/css/submission-prototype.css"></head>
<body class="submission-prototype"><main class="prototype-shell"><section class="step-panel">
<h1>Local submission staff login</h1><p>This uses only the isolated fixture's staff table, not the configured application database.</p>
<?php if ($notice !== ''): ?><p role="alert"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['submission_csrf'], ENT_QUOTES, 'UTF-8') ?>">
<div class="field"><label for="username">Local username</label><input id="username" name="username" required maxlength="100" autocomplete="username"></div>
<div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required maxlength="1024" autocomplete="current-password"></div>
<button class="primary-button" type="submit">Sign in locally</button></form></section></main></body></html>
