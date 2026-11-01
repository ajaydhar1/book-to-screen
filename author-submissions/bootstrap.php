<?php

declare(strict_types=1);

require_once __DIR__ . '/../submission-prototype/local-only.php';
require_once __DIR__ . '/../includes/submissions/local.php';

header("Content-Security-Policy: default-src 'self'; connect-src 'self'; script-src 'self'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (getenv('B2S_SUBMISSIONS_LOCAL') !== '1') {
    http_response_code(404);
    exit('Local submission testing is not enabled. The demonstration remains available.');
}
try {
    $submissionDb = submission_local_database();
    $base = parse_url(submission_local_base_url());
    $expectedHost = $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
    if (strtolower($_SERVER['HTTP_HOST'] ?? '') !== strtolower($expectedHost)) {
        http_response_code(403);
        exit('Use the configured loopback URL.');
    }
    $submissionService = submission_local_service($submissionDb);
} catch (RuntimeException $e) {
    error_log('Local submission configuration is incomplete.');
    http_response_code(503);
    exit('Local submission fixtures are not configured.');
}

ini_set('session.use_strict_mode', '1');
session_name('B2S_LOCAL_SUBMISSIONS');
session_cache_limiter('');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict',
    'secure' => ($_SERVER['HTTPS'] ?? '') === 'on',
]);
session_start();
$_SESSION['submission_csrf'] ??= bin2hex(random_bytes(32));

function submission_require_post_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit('POST required.');
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? null;
    if (!submission_csrf_valid($_SESSION, $token)) {
        http_response_code(403);
        exit('Invalid request verification. Reload and try again.');
    }
}

function submission_read_json(): array
{
    if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
        || (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
        throw new SubmissionInputException(['request' => 'Use a bounded JSON request.']);
    }
    $body = file_get_contents('php://input', false, null, 0, 32769);
    if ($body === false || strlen($body) > 32768) {
        throw new SubmissionInputException(['request' => 'Request is too large.']);
    }
    try {
        $input = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new SubmissionInputException(['request' => 'Invalid JSON request.']);
    }
    if (!is_array($input) || array_is_list($input)) {
        throw new SubmissionInputException(['request' => 'Provide a JSON object.']);
    }
    return $input;
}

function submission_json_action(callable $operation): never
{
    header('Content-Type: application/json; charset=UTF-8');
    try {
        $result = $operation();
    } catch (SubmissionAntibotUnavailable $e) {
        http_response_code(503);
        $result = ['message' => $e->getMessage()];
    } catch (SubmissionInputException $e) {
        http_response_code(422);
        $result = ['message' => $e->getMessage(), 'errors' => $e->errors];
    } catch (SubmissionRateException $e) {
        http_response_code(429);
        header('Retry-After: 3600');
        $result = ['message' => $e->getMessage()];
    } catch (SubmissionConflictException $e) {
        http_response_code(409);
        $result = ['message' => $e->getMessage()];
    } catch (Throwable $e) {
        error_log('Local submission action failed.'); // Do not log private request bodies or tokens.
        http_response_code(503);
        $result = ['message' => 'The request could not be completed. Please retry later with the same request.'];
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
