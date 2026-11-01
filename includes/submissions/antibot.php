<?php

declare(strict_types=1);

require_once __DIR__ . '/security.php';

final class SubmissionAntibotUnavailable extends RuntimeException
{
}

final class SubmissionTurnstile
{
    public const TEST_SITES = [
        '1x00000000000000000000AA', '2x00000000000000000000AB',
        '1x00000000000000000000BB', '2x00000000000000000000BB', '3x00000000000000000000FF',
    ];
    public const TEST_SECRETS = [
        '1x0000000000000000000000000000000AA', '2x0000000000000000000000000000000AA',
        '3x0000000000000000000000000000000AA',
    ];

    public function __construct(
        public readonly string $environment = 'disabled',
        public readonly string $siteKey = '',
        private string $secret = '',
        public readonly string $hostname = '',
        public readonly string $action = '',
        private ?Closure $transport = null,
        private ?Closure $observer = null
    ) {
        if (!in_array($environment, ['disabled', 'development', 'production'], true)) {
            throw new RuntimeException('Select a valid Turnstile environment.');
        }
        if ($environment === 'disabled') {
            if ($siteKey !== '' || $secret !== '' || $hostname !== '' || $action !== '') {
                throw new RuntimeException('Disabled Turnstile must not contain active settings.');
            }
            return;
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{20,100}$/', $siteKey)
            || !preg_match('/^[a-zA-Z0-9_-]{20,100}$/', $secret)
            || !preg_match('/^[a-z0-9.-]+$/', $hostname)
            || !preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $action)) {
            throw new RuntimeException('Complete the Turnstile key, hostname and action configuration.');
        }
        if ($environment === 'development') {
            // Official dummy responses use localhost/test rather than the requested widget context.
            if (!in_array($siteKey, self::TEST_SITES, true) || !in_array($secret, self::TEST_SECRETS, true)
                || $hostname !== 'localhost' || $action !== 'test') {
                throw new RuntimeException('Development uses official testing keys and the localhost/test response context.');
            }
        } elseif (in_array($siteKey, self::TEST_SITES, true) || in_array($secret, self::TEST_SECRETS, true)
            || $hostname === 'localhost' || filter_var($hostname, FILTER_VALIDATE_IP)
            || !filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || !str_contains($hostname, '.')) {
            throw new RuntimeException('Production Turnstile requires real keys and an explicit public hostname.');
        }
    }

    public static function fromEnvironment(): self
    {
        $environment = getenv('B2S_TURNSTILE_ENV') ?: 'disabled';
        if ($environment === 'disabled') {
            return new self();
        }
        $prefix = $environment === 'development' ? 'B2S_TURNSTILE_DEV_' : 'B2S_TURNSTILE_PROD_';
        return new self($environment, getenv($prefix . 'SITE_KEY') ?: '', getenv($prefix . 'SECRET') ?: '',
            getenv($prefix . 'HOSTNAME') ?: '', getenv($prefix . 'ACTION') ?: '');
    }

    public function requireForBaseUrl(string $baseUrl): void
    {
        $parts = parse_url($baseUrl);
        $host = $parts['host'] ?? '';
        if (in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) {
            if ($this->environment === 'production') {
                throw new RuntimeException('Production Turnstile must not be selected for a loopback fixture.');
            }
            return;
        }
        if ($this->environment !== 'production' || ($parts['scheme'] ?? '') !== 'https' || $host !== $this->hostname) {
            throw new RuntimeException('Non-local intake requires production Turnstile on its configured HTTPS hostname.');
        }
    }

    public function verify(mixed $token, int $now): void
    {
        if ($this->environment === 'disabled') {
            return;
        }
        if (!is_string($token) || trim($token) === '' || strlen($token) > 2048 || preg_match('/[\x00-\x20\x7f]/', $token)) {
            throw new SubmissionInputException(['verification' => 'Please complete the security check before continuing.']);
        }
        try {
            $result = $this->transport === null ? $this->siteverify($token, $now) : ($this->transport)($this->secret, $token);
        } catch (SubmissionAntibotUnavailable $e) {
            error_log('Submission security check unavailable.');
            throw $e;
        }
        if (!is_array($result) || !is_bool($result['success'] ?? null)) {
            $this->observeResult($result, $now);
            error_log('Submission security check returned an invalid response.');
            throw new SubmissionAntibotUnavailable('The security check is temporarily unavailable. Retry the check; your details remain on this page.');
        }
        $this->observeResult($result, $now);
        if (!$result['success']) {
            $codes = $result['error-codes'] ?? [];
            if (is_array($codes) && array_intersect($codes, ['internal-error', 'invalid-input-secret', 'missing-input-secret'])) {
                error_log('Submission security check provider/configuration failure.');
                throw new SubmissionAntibotUnavailable('The security check is temporarily unavailable. Please retry later.');
            }
            throw new SubmissionInputException(['verification' => 'The security check expired or was not accepted. Please retry the check and try again.']);
        }
        // The official dummy endpoint can return a fixed example.com context without an action.
        // This sentinel is accepted only with official keys in the loopback-only development mode.
        if ($this->environment === 'development' && $token === 'XXXX.DUMMY.TOKEN.XXXX'
            && ($result['metadata']['result_with_testing_key'] ?? null) === true
            && ($result['hostname'] ?? null) === 'example.com' && !array_key_exists('action', $result)) {
            return;
        }
        $timestamp = is_string($result['challenge_ts'] ?? null) ? strtotime($result['challenge_ts']) : false;
        if (($result['hostname'] ?? null) !== $this->hostname || ($result['action'] ?? null) !== $this->action
            || ($this->environment === 'production' && ($timestamp === false || $timestamp < $now - 300 || $timestamp > $now + 60))) {
            throw new SubmissionInputException(['verification' => 'The security check does not match this form or has expired. Please retry the check.']);
        }
    }

    private function observeResult(mixed $result, int $now): void
    {
        if ($this->observer === null) return;
        $codes = is_array($result) && is_array($result['error-codes'] ?? null) ? $result['error-codes'] : [];
        $allowed = ['missing-input-secret', 'invalid-input-secret', 'missing-input-response',
            'invalid-input-response', 'bad-request', 'timeout-or-duplicate', 'internal-error'];
        $timestamp = is_array($result) && is_string($result['challenge_ts'] ?? null)
            ? strtotime($result['challenge_ts']) : false;
        ($this->observer)([
            'success' => is_array($result) && is_bool($result['success'] ?? null) ? $result['success'] : null,
            'error_codes' => array_values(array_unique(array_intersect($allowed, array_filter($codes, 'is_string')))),
            'hostname_pass' => is_array($result) && ($result['hostname'] ?? null) === $this->hostname,
            'action_pass' => is_array($result) && ($result['action'] ?? null) === $this->action,
            'timestamp_pass' => $timestamp !== false && $timestamp >= $now - 300 && $timestamp <= $now + 60,
        ]);
    }

    private function siteverify(string $token, int $now): array
    {
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        $body = '';
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['secret' => $this->secret, 'response' => $token]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 16384) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        if ($this->observer !== null) ($this->observer)(['siteverify_made' => true]);
        $ok = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($this->observer !== null) ($this->observer)(['provider_http_status' => $status > 0 ? $status : null]);
        if ($ok === false || ($status !== 200 && $this->observer === null)) {
            throw new SubmissionAntibotUnavailable('The security check is temporarily unavailable. Please retry the check.');
        }
        try {
            $result = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SubmissionAntibotUnavailable('The security check is temporarily unavailable. Please retry later.');
        }
        if (!is_array($result)) {
            throw new SubmissionAntibotUnavailable('The security check is temporarily unavailable. Please retry later.');
        }
        if ($status !== 200) {
            $this->observeResult($result, $now);
            throw new SubmissionAntibotUnavailable('The security check is temporarily unavailable. Please retry the check.');
        }
        return $result;
    }
}

function submission_check_honeypot(array $input): void
{
    if (array_key_exists('company-website', $input) && (!is_string($input['company-website']) || $input['company-website'] !== '')) {
        throw new SubmissionInputException(['request' => 'The form could not be accepted. Leave the optional automation field empty and try again.']);
    }
}
