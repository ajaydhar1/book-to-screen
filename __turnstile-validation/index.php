<?php

declare(strict_types=1);

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'none'; script-src 'self' https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com; connect-src 'self'; style-src 'none'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
require_once __DIR__ . '/../includes/submissions/turnstile-challenge.php';
$settingsPath = getenv('B2S_TURNSTILE_VALIDATION_SETTINGS_FILE') ?: '';
if ($settingsPath === '') {
    http_response_code(404);
    exit('Validation endpoint unavailable.');
}
try {
    $settings = submission_challenge_settings($settingsPath);
    if (!submission_challenge_gate($_SERVER, $settings, time())) {
        http_response_code(404);
        exit('Validation endpoint unavailable.');
    }
    $config = submission_challenge_config($settings['widget_config']);
    $sessionPath = submission_private_config_path($settings['sessions'], false);
    if (!is_dir($sessionPath)) throw new RuntimeException('Private sessions unavailable.');
    ini_set('session.use_strict_mode', '1');
    session_cache_limiter('');
    session_save_path($sessionPath);
    session_name('B2S_TURNSTILE_VALIDATION');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/__turnstile-validation/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            http_response_code(403);
            exit('{"message":"Invalid request verification."}');
        }
        if (($_SERVER['CONTENT_TYPE'] ?? '') !== 'application/json') {
            http_response_code(415);
            exit('{"message":"Use a bounded JSON request."}');
        }
        $body = file_get_contents('php://input', false, null, 0, 4097);
        try {
            $input = is_string($body) && strlen($body) <= 4096 ? json_decode($body, true, 4, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException $e) {
            $input = null;
        }
        if (!is_array($input) || count($input) !== 2 || !array_key_exists('token', $input)
            || !is_string($input['test_type'] ?? null)
            || !in_array($input['test_type'], ['Fresh token', 'Reused token', 'Expired unused token', 'Missing token', 'Invalid token'], true)) {
            http_response_code(422);
            exit('{"message":"Invalid bounded test request."}');
        }
        $diagnostic = submission_challenge_diagnostic_start($_SESSION, $input['token'], $input['test_type'], $settings['deadline']);
        $observer = static function (array $event) use (&$diagnostic): void {
            $diagnostic = array_replace($diagnostic, $event);
        };
        $verifier = new SubmissionTurnstile('production', $config['site_key'], $config['secret_key'],
            $config['hostname'], $config['action'], null, $observer);
        $result = submission_challenge_verify($verifier, $input['token'], time(),
            static fn() => submission_challenge_budget($settings['budget']));
        if ($diagnostic['siteverify_made'] && $diagnostic['token_id'] !== null) {
            $_SESSION['diagnostic_previous'] = $diagnostic['token_id'];
        }
        $diagnostic['decision'] = $result['status'] === 200 ? 'accept' : 'reject';
        session_write_close();
        http_response_code($result['status']);
        echo json_encode(['message' => $result['message'], 'diagnostic' => $diagnostic], JSON_THROW_ON_ERROR);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || $_GET !== []) {
        http_response_code(405);
        header('Allow: GET, POST');
        exit('Only the exact validation page is available.');
    }
} catch (Throwable) {
    error_log('Restricted Turnstile validation failed; details suppressed.');
    http_response_code(503);
    exit('Validation unavailable. Stop and inspect privately; no automatic retry.');
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Restricted Turnstile validation</title><script src="challenge.js" defer></script></head>
<body><main id="challenge-test" data-site-key="<?= htmlspecialchars($config['site_key'], ENT_QUOTES, 'UTF-8') ?>"
data-csrf="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
<h1>Restricted challenge-only test</h1>
<p>No book, contact, email, database or reviewer data is collected. Cloudflare processes browser/network signals.
Tokens stay in page memory and are sent only for validation. At most 12 Siteverify requests are approved for this shared test window.</p>
<div id="widget"></div>
<button id="fresh" type="button">Validate current token</button>
<button id="reuse" type="button">Test last submitted token again</button>
<button id="hold" type="button">Hold current token unused for expiry test</button>
<button id="expired" type="button">Test held token after five minutes</button>
<button id="missing" type="button">Test missing token</button>
<button id="invalid" type="button">Test synthetic invalid token</button>
<button id="reset" type="button">Get a fresh challenge</button>
<p id="widget-status" role="status" aria-live="polite">Loading security check...</p>
<p id="result" role="status" aria-live="polite">No validation test submitted yet.</p>
<h2>Sanitized server diagnostics</h2>
<pre id="diagnostic" aria-live="polite">No server diagnostic yet.</pre>
<p>Do not refresh to evade limits or share tokens. Disable and remove this test after the approved window.</p>
</main></body></html>
