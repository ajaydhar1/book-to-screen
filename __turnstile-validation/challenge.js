(() => {
    'use strict';
    const page = document.getElementById('challenge-test');
    const status = document.getElementById('result');
    const widgetStatus = document.getElementById('widget-status');
    const diagnostic = document.getElementById('diagnostic');
    const buttons = ['fresh', 'reuse', 'hold', 'expired', 'missing', 'invalid', 'reset']
        .map((id) => document.getElementById(id));
    let current = '';
    let last = '';
    let first = '';
    let firstTime = 0;
    let widget = null;
    let busy = false;
    let stopped = false;
    let attempt = 0;
    const script = document.createElement('script');
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
    script.onload = () => {
        widget = window.turnstile.render('#widget', {
            sitekey: page.dataset.siteKey, action: 'author_submission', 'response-field': false,
            retry: 'never', 'refresh-expired': 'manual', 'refresh-timeout': 'manual',
            callback: (token) => {
                current = token;
                widgetStatus.textContent = 'Challenge ready. Choose a manual validation test.';
            },
            'expired-callback': () => { current = ''; widgetStatus.textContent = 'Token expired. Any held token remains available for the negative test.'; },
            'error-callback': () => { current = ''; widgetStatus.textContent = 'Challenge failed. Stop and inspect; no automatic request retry.'; },
            'timeout-callback': () => { current = ''; widgetStatus.textContent = 'Challenge timed out. Get a fresh challenge manually.'; },
        });
    };
    script.onerror = () => { widgetStatus.textContent = 'Challenge script blocked or unavailable. Stop and inspect.'; };
    document.head.append(script);
    async function send(token, label, remember = false) {
        if (busy || stopped) return;
        busy = true;
        const prefix = `Test ${++attempt} - ${label}`;
        status.textContent = `${prefix}: In progress; previous result cleared.`;
        diagnostic.textContent = 'Awaiting sanitized server diagnostic.';
        buttons.forEach((button) => { button.disabled = true; });
        try {
            const response = await fetch('index.php', {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': page.dataset.csrf },
                body: JSON.stringify({ token, test_type: label }), signal: AbortSignal.timeout(15000),
            });
            const result = await response.json();
            if (!result || typeof result.message !== 'string' || result.message === '') {
                throw new Error('Invalid test response.');
            }
            if (remember && response.status === 200) last = token;
            status.textContent = `${prefix} (HTTP ${response.status}): ${result.message}`;
            const data = result.diagnostic;
            if (data && typeof data === 'object') {
                const flag = (value) => value === true ? 'PASS' : value === false ? 'FAIL' : 'NOT OBSERVED';
                const types = ['Fresh token', 'Reused token', 'Expired unused token', 'Missing token', 'Invalid token'];
                const codes = ['missing-input-secret', 'invalid-input-secret', 'missing-input-response',
                    'invalid-input-response', 'bad-request', 'timeout-or-duplicate', 'internal-error'];
                diagnostic.textContent = [
                    `Server test: ${Number.isSafeInteger(data.test_number) ? data.test_number : 'UNKNOWN'} - ${types.includes(data.test_type) ? data.test_type : 'UNKNOWN'}`,
                    `Same token as previous Siteverify request: ${data.same_as_previous_siteverify_token === true ? 'YES' : data.same_as_previous_siteverify_token === false ? 'NO' : 'NOT COMPARABLE'}`,
                    `Test-scoped token ID: ${typeof data.token_id === 'string' && /^[a-f0-9]{64}$/.test(data.token_id) ? data.token_id : 'NONE'}`,
                    `Siteverify request made: ${data.siteverify_made === true ? 'YES' : 'NO'}`,
                    `Provider HTTP status: ${Number.isInteger(data.provider_http_status) && data.provider_http_status >= 100 && data.provider_http_status <= 599 ? data.provider_http_status : 'NOT OBSERVED'}`,
                    `Provider success: ${data.success === true ? 'true' : data.success === false ? 'false' : 'NOT OBSERVED'}`,
                    `Provider error codes: ${Array.isArray(data.error_codes) ? data.error_codes.filter((code) => codes.includes(code)).join(', ') || 'NONE ALLOWLISTED' : 'NONE ALLOWLISTED'}`,
                    `Hostname: ${flag(data.hostname_pass)}`,
                    `Action: ${flag(data.action_pass)}`,
                    `Timestamp: ${flag(data.timestamp_pass)}`,
                    `Application decision: ${data.decision === 'accept' ? 'ACCEPT' : 'REJECT'}`,
                ].join('\n');
            } else {
                diagnostic.textContent = 'Server diagnostic unavailable. Stop and inspect deployed version before further testing.';
                stopped = true;
            }
            if (label === 'Reused token' && response.status === 200) {
                status.textContent = `${prefix} (HTTP 200): UNEXPECTED REPLAY ACCEPTANCE. Stop and disable the harness; provider single-use behavior is not established.`;
                stopped = true;
            }
            if (![200, 422].includes(response.status)) stopped = true;
        } catch {
            stopped = true;
            status.textContent = `${prefix}: Request outcome unavailable. Stop; do not retry automatically or reload to repeat it.`;
        } finally {
            busy = false;
            buttons.forEach((button) => { button.disabled = stopped; });
        }
    }
    document.getElementById('fresh').onclick = () => {
        if (busy || stopped) return;
        if (!current) { status.textContent = 'Complete a fresh challenge first.'; return; }
        send(current, 'Fresh token', true);
    };
    document.getElementById('reuse').onclick = () => {
        if (busy || stopped) return;
        if (!last) { status.textContent = 'Successfully verify a fresh token before the reuse test.'; return; }
        send(last, 'Reused token');
    };
    document.getElementById('expired').onclick = () => {
        if (busy || stopped) return;
        if (!first || Date.now() - firstTime <= 301000) { status.textContent = 'Wait at least 301 seconds after the first token was issued.'; return; }
        send(first, 'Expired unused token');
    };
    document.getElementById('hold').onclick = () => {
        if (busy || stopped) return;
        if (!current || current === last) {
            status.textContent = 'Get a fresh, unused token before holding it.';
            return;
        }
        first = current;
        firstTime = Date.now();
        current = '';
        window.turnstile.reset(widget);
        status.textContent = 'Unused token held in memory. Wait 301 seconds; a new challenge is separate.';
    };
    document.getElementById('missing').onclick = () => send('', 'Missing token');
    document.getElementById('invalid').onclick = () => send('synthetic-invalid-validation-token', 'Invalid token');
    document.getElementById('reset').onclick = () => {
        if (stopped || busy || widget === null) return;
        current = '';
        window.turnstile.reset(widget);
    };
    window.addEventListener('pagehide', () => { current = last = first = ''; });
})();
