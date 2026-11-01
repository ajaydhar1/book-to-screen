<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/submissions/staging-browser.php';
$checks = 0;
function browser_check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
function browser_reject(callable $operation): void
{
    try { $operation(); } catch (RuntimeException|InvalidArgumentException $e) { browser_check(true, 'Guard rejects'); return; }
    throw new RuntimeException('FAIL: guard accepted invalid state.');
}
try {
    $config = submission_browser_config($argv[1] ?? '');
    if ($config['mode'] !== 'offline') throw new RuntimeException('This test permits only offline fixtures.');
    $base = substr($config['base_url'], 0, -strlen('/author-submissions'));
    $cookie = $config['directory'] . DIRECTORY_SEPARATOR . 'browser-test-cookies.txt';
    if (is_file($cookie)) unlink($cookie);
    $request = static function (string $path, ?array $body = null, ?string $csrf = null, bool $form = false, ?string $host = null) use ($base, $cookie): array {
        $ch = curl_init($base . $path);
        $headers = [];
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie,
            CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
                if (str_contains($line, ':')) { [$key, $value] = explode(':', $line, 2); $headers[strtolower(trim($key))] = trim($value); }
                return strlen($line);
            }]);
        $sentHeaders = $host === null ? [] : ['Host: ' . $host];
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $form ? http_build_query($body) : json_encode($body, JSON_THROW_ON_ERROR));
            $sentHeaders[] = $form ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json';
            $sentHeaders[] = 'X-CSRF-Token: ' . ($csrf ?? '');
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $sentHeaders);
        $body = curl_exec($ch);
        if ($body === false) throw new RuntimeException('Offline HTTP connection failed.');
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => $body, 'headers' => $headers];
    };
    $csrf = static function (string $html): string {
        if (preg_match('/(?:data-csrf="|name="csrf" value=")([a-f0-9]{64})"/', $html, $match)) return $match[1];
        throw new RuntimeException('CSRF field missing.');
    };
    $crypto = new SubmissionCrypto(submission_browser_key($config));
    $db = new LocalSqliteDatabase($config['fixture_db']);
    $schema = $db->query('SELECT * FROM sqlite_schema ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $ledger = $db->query('SELECT * FROM submission_schema_versions ORDER BY version')->fetchAll(PDO::FETCH_ASSOC);
    $page = $request('/author-submissions/');
    browser_check($page['code'] === 200 && str_contains($page['body'], 'OFFLINE MOCK browser test'), 'Three-step form, offline notice');
    browser_check($page['headers']['cache-control'] === 'no-store'
        && $page['headers']['referrer-policy'] === 'no-referrer', 'Private cache and referrer policy');
    browser_check(str_contains($page['body'], 'data-security-script="/__staging/turnstile.js"'), 'Offline widget isolated');
    browser_check($request('/admin/submissions.php')['code'] === 403, 'Anonymous reviewer blocked');
    browser_check($request('/__staging/mailbox')['code'] === 403, 'Anonymous mailbox blocked');
    foreach (['/config.php', '/scripts/submissions-staging.local.php', '/admin/index.php', '/includes/db.php',
        '/%2e%2e/config.php', '/assets/../config.php', '/favicon.png/secret'] as $denied) {
        browser_check($request($denied)['code'] === 404, 'Router does not fall through to production or private files');
    }
    browser_check($request('/author-submissions/', null, null, false, 'production.example.com')['code'] === 403, 'Host spoof rejected');
    $lookup = json_decode($request('/author-submissions/lookup.php?isbn=9780140328721')['body'], true, 16, JSON_THROW_ON_ERROR);
    browser_check($lookup['status'] === 'success' && str_starts_with($lookup['metadata']['title'], '[SYNTHETIC]'), 'ISBN lookup uses offline mock');
    $token = $csrf($page['body']);
    $input = submission_staging_synthetic_properties()[1];
    $input['title'] = '[SYNTHETIC] Browser Test ' . bin2hex(random_bytes(5));
    $input['email'] = 'browser-fiction@example.com';
    $input['request_key'] = bin2hex(random_bytes(32));
    $input['turnstile_token'] = 'XXXX.DUMMY.TOKEN.XXXX';
    $count = (int) $db->query('SELECT COUNT(*) FROM property_submissions')->fetchColumn();
    browser_check($request('/author-submissions/submit.php', $input, 'bad')['code'] === 403, 'Submission CSRF enforced');
    $bad = $input; $bad['email'] = 'real@example.org';
    browser_check($request('/author-submissions/submit.php', $bad, $token)['code'] === 422, 'Nonfictional email rejected');
    $bad = $input; $bad['title'] = 'Real title';
    browser_check($request('/author-submissions/submit.php', $bad, $token)['code'] === 422, 'Unmarked title rejected');
    $bad = $input; $bad['turnstile_token'] = '';
    browser_check($request('/author-submissions/submit.php', $bad, $token)['code'] === 422, 'Server-side Turnstile required');
    $bad = $input; $bad['turnstile_token'] = 'not-an-accepted-token';
    browser_check($request('/author-submissions/submit.php', $bad, $token)['code'] === 422, 'Invalid challenge rejected');
    $bad = $input; $bad['company-website'] = 'bot';
    browser_check($request('/author-submissions/submit.php', $bad, $token)['code'] === 422, 'Honeypot enforced');
    $bad = $input; $bad['consent'] = false;
    browser_check($request('/author-submissions/submit.php', $bad, $token)['code'] === 422, 'Consent enforced');
    browser_check((int) $db->query('SELECT COUNT(*) FROM property_submissions')->fetchColumn() === $count, 'Rejected requests create no properties');
    $sent = $request('/author-submissions/submit.php', $input, $token);
    browser_check($sent['code'] === 200, 'Fictional submission stored');
    browser_check($request('/author-submissions/submit.php', $input, $token)['body'] === $sent['body'], 'Idempotent receipt');
    $receipt = json_decode($sent['body'], true, 16, JSON_THROW_ON_ERROR)['receipt'];
    $state = json_decode($request('/author-submissions/receipt.php', ['receipt' => $receipt], $token)['body'], true);
    browser_check($state['state'] === 'pending_verification', 'Unverified record not actionable');
    $messages = file($config['mailbox'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $message = json_decode($crypto->decrypt(end($messages)), true, 16, JSON_THROW_ON_ERROR);
    parse_str(parse_url($message['link'], PHP_URL_FRAGMENT), $fragment);
    browser_check(!str_contains(implode('', $messages), 'browser-fiction@example.com'), 'Mock mailbox encrypted');
    $verify = $request('/author-submissions/verify.php');
    $verifyCsrf = $csrf($verify['body']);
    browser_check($request('/author-submissions/verify.php', ['token' => $fragment['token']], 'bad')['code'] === 403, 'Verification CSRF enforced');
    $confirmed = json_decode($request('/author-submissions/verify.php', ['token' => $fragment['token']], $verifyCsrf)['body'], true);
    browser_check($confirmed['state'] === 'verified', 'Mock email verification');
    $reused = json_decode($request('/author-submissions/verify.php', ['token' => $fragment['token']], $verifyCsrf)['body'], true);
    browser_check($reused['state'] === 'unavailable', 'Verification single use');
    $login = $request('/author-submissions/staff-login.php');
    $loginCsrf = $csrf($login['body']);
    $credentials = json_decode(file_get_contents($config['directory'] . DIRECTORY_SEPARATOR . 'reviewer-login.json'), true, 8, JSON_THROW_ON_ERROR);
    $credentials['csrf'] = $loginCsrf;
    browser_check($request('/author-submissions/staff-login.php', $credentials, 'bad', true)['code'] === 403, 'Login CSRF enforced');
    browser_check($request('/author-submissions/staff-login.php', $credentials, $loginCsrf, true)['code'] === 303, 'Fictional reviewer signs in');
    $queue = $request('/admin/submissions.php');
    browser_check($queue['code'] === 200 && str_contains($queue['body'], $input['title']), 'Verified property located in inbox');
    $duplicateId = $db->query("SELECT id FROM property_submissions WHERE review_status='duplicate' LIMIT 1")->fetchColumn();
    browser_check(is_string($duplicateId) && !str_contains($queue['body'], 'id=' . $duplicateId), 'Duplicate record outside default inbox');
    browser_check(str_contains($request('/admin/submissions.php?status=duplicate&verification=all')['body'], 'id=' . $duplicateId),
        'Duplicate remains in separate reviewer filter');
    $mail = $request('/__staging/mailbox');
    browser_check($mail['code'] === 200 && str_contains($mail['body'], '/verify.php#token='), 'Only authenticated mailbox exposes fragment links');
    $stmt = $db->prepare('SELECT id FROM property_submissions WHERE idempotency_hash=:hash');
    $stmt->execute(['hash' => $crypto->hash('idempotency', $input['request_key'])]); $id = $stmt->fetchColumn(); $stmt = null;
    $expiredToken = bin2hex(random_bytes(32));
    $stmt = $db->prepare('INSERT INTO submission_email_tokens(token_hash,submission_id,expires_at,created_at) VALUES (:hash,:id,:expiry,:created)');
    $stmt->execute(['hash' => hash('sha256', $expiredToken), 'id' => $id, 'expiry' => time() - 3600, 'created' => time() - 90000]); $stmt = null;
    $currentVerifyCsrf = $csrf($request('/author-submissions/verify.php')['body']);
    browser_check(json_decode($request('/author-submissions/verify.php', ['token' => $expiredToken], $currentVerifyCsrf)['body'], true)['state'] === 'unavailable',
        'Expired token handled safely');
    $originalId = $db->query("SELECT id FROM property_submissions WHERE review_status='research_candidate' LIMIT 1")->fetchColumn();
    browser_check(str_contains($request('/admin/submissions.php?id=' . $originalId)['body'], 'id=' . $duplicateId),
        'Duplicate warning shown on property detail');
    $detail = $request('/admin/submissions.php?id=' . $id);
    $reviewCsrf = $csrf($detail['body']);
    preg_match('/name="revision" value="(\d+)"/', $detail['body'], $revision);
    $review = ['id' => $id, 'status' => 'research_candidate', 'note' => 'Entirely fictional browser research note',
        'evaluation' => ['premise' => 'Synthetic visual potential', 'rights_questions' => 'Fictional rights only'],
        'revision' => $revision[1], 'csrf' => $reviewCsrf];
    browser_check($request('/admin/submissions.php?id=' . $id, $review, 'bad', true)['code'] === 403, 'Reviewer CSRF enforced');
    browser_check($request('/admin/submissions.php?id=' . $id, $review, $reviewCsrf, true)['code'] === 303, 'Notes, evaluations and status saved');
    $reload = $request('/admin/submissions.php?id=' . $id);
    browser_check(str_contains($reload['body'], 'Entirely fictional browser research note')
        && str_contains($reload['body'], 'Synthetic visual potential'), 'Encrypted review persists after reload');
    browser_check($request('/admin/submissions.php?id=' . $id, $review, $reviewCsrf, true)['code'] === 409, 'Stale revision rejected');
    $review['status'] = 'spam'; $review['revision'] = (string) ((int) $revision[1] + 1);
    browser_check($request('/admin/submissions.php?id=' . $id, $review, $reviewCsrf, true)['code'] === 303, 'Spam quarantine');
    browser_check(!str_contains($request('/admin/submissions.php')['body'], $input['title']), 'Spam outside default inbox');
    browser_check($db->query('SELECT * FROM sqlite_schema ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) === $schema
        && $db->query('SELECT * FROM submission_schema_versions ORDER BY version')->fetchAll(PDO::FETCH_ASSOC) === $ledger, 'Schema and ledger unchanged');
    browser_check((int) $db->query('SELECT COUNT(*) FROM _sqliteai_vector')->fetchColumn() === 0, 'Vector metadata empty and preserved');
    browser_check($db->query('PRAGMA foreign_key_check')->fetchAll() === [], 'Foreign-key integrity');
    browser_reject(fn() => submission_browser_gate(array_merge($config, ['mode' => 'live']), '--serve-offline', ''));
    browser_reject(fn() => submission_browser_gate(array_merge($config, ['mode' => 'live']), '--serve-live-staging', ''));
    browser_reject(fn() => submission_browser_private_path(__FILE__));
    browser_reject(fn() => submission_browser_verify_records(new SQLiteCloudDatabase(new StagingBrowserOfflineClient($config['fixture_db'])),
        new SubmissionCrypto(str_repeat('f', 64))));
    $logoutPage = $request('/author-submissions/staff-login.php');
    browser_check($request('/author-submissions/staff-login.php', ['logout' => '1', 'csrf' => $csrf($logoutPage['body'])],
        $csrf($logoutPage['body']), true)['code'] === 303, 'Logout destroys authorization');
    browser_check($request('/__staging/mailbox')['code'] === 403 && $request('/admin/submissions.php')['code'] === 403, 'Post-logout private routes blocked');
    echo "PASS: {$checks} staging browser offline checks; no Cloud connection, production access or real email.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e instanceof RuntimeException ? $e->getMessage() . " (after {$checks} checks)\n" : "Offline browser test failed; sensitive details suppressed.\n");
    exit(1);
} finally {
    if (isset($cookie) && is_file($cookie)) unlink($cookie);
}
