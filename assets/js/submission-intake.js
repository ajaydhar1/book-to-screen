(() => {
    'use strict';
    const flow = document.getElementById('prototype-flow');
    const verification = document.getElementById('verification-page');
    const receiptPage = document.getElementById('receipt-page');
    const byId = (id) => document.getElementById(id);
    async function request(endpoint, body, csrf) {
        const response = await fetch(endpoint, {
            method: 'POST', credentials: 'same-origin', cache: 'no-store',
            signal: AbortSignal.timeout(20000),
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(body),
        });
        let result;
        try {
            result = await response.json();
        } catch {
            throw new Error('The request could not be completed. Reload if your session expired.');
        }
        if (!response.ok) {
            const error = new Error(result.message + (result.errors ? ' ' + Object.entries(result.errors).map(([field, message]) => `${field}: ${message}`).join(' ') : ''));
            error.status = response.status;
            throw error;
        }
        return result;
    }
    if (verification) {
        const token = new URLSearchParams(location.hash.slice(1)).get('token') || '';
        history.replaceState(null, '', location.pathname);
        byId('confirm-email').addEventListener('click', async () => {
            const button = byId('confirm-email');
            button.disabled = true;
            try {
                const result = await request('verify.php', { token }, verification.dataset.csrf);
                byId('verification-message').textContent = result.message;
            } catch (error) {
                byId('verification-message').textContent = error.message;
                button.disabled = false;
            }
        });
    }
    if (receiptPage) {
        const receipt = new URLSearchParams(location.hash.slice(1)).get('receipt') || '';
        history.replaceState(null, '', location.pathname);
        for (const [buttonId, endpoint] of [['check-receipt', 'receipt.php'], ['receipt-resend', 'resend.php']]) {
            byId(buttonId).addEventListener('click', async () => {
                const button = byId(buttonId);
                button.disabled = true;
                try {
                    const result = await request(endpoint, { receipt }, receiptPage.dataset.csrf);
                    const messages = {
                        verified: 'Email confirmed. The sample submission is in the private local review queue.',
                        pending_verification: 'Submission is pending email verification. Check the local mock mailbox.',
                        delivery_failed: 'Verification delivery could not be completed. You may request a resend after the cooldown.',
                        unavailable: 'This receipt is unavailable. Check your private receipt link.',
                    };
                    byId('receipt-message').textContent = result.message || messages[result.state] || 'Status unavailable.';
                } catch (error) {
                    byId('receipt-message').textContent = error.message;
                } finally {
                    button.disabled = false;
                }
            });
        }
    }
    if (!flow || flow.dataset.mode !== 'local-intake') return;
    const bytes = crypto.getRandomValues(new Uint8Array(32));
    const requestKey = [...bytes].map((value) => value.toString(16).padStart(2, '0')).join('');
    let receipt = null;
    let frozenPayload = null;
    const security = byId('submission-security');
    let securityToken = '';
    let widgetId = null;
    let scriptLoading = false;
    function securityMessage(text) {
        byId('security-message').textContent = text;
    }
    function renderSecurity() {
        if (!security || widgetId !== null) return;
        widgetId = window.turnstile.render('#turnstile-widget', {
            sitekey: security.dataset.siteKey, action: security.dataset.action,
            size: 'flexible', 'response-field': false,
            callback: (token) => {
                securityToken = token;
                securityMessage('Security check complete. You can continue.');
            },
            'expired-callback': () => {
                securityToken = '';
                securityMessage('The security check expired. Please retry it before continuing.');
            },
            'timeout-callback': () => {
                securityToken = '';
                securityMessage('The security check timed out. Please retry it.');
            },
            'error-callback': () => {
                securityToken = '';
                securityMessage('The security check could not load. Check your connection or content blocker and retry. Your details are preserved.');
            },
        });
    }
    function startSecurity() {
        if (!security) return;
        if (window.turnstile) {
            renderSecurity();
            return;
        }
        if (scriptLoading) return;
        scriptLoading = true;
        securityMessage('Loading the security check...');
        const script = document.createElement('script');
        script.id = 'turnstile-script';
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.onload = () => {
            scriptLoading = false;
            renderSecurity();
        };
        script.onerror = () => {
            scriptLoading = false;
            script.remove();
            securityMessage('The security check could not load. Check your connection or content blocker and retry. Your details are preserved.');
        };
        document.head.append(script);
    }
    function resetSecurity() {
        securityToken = '';
        if (widgetId !== null) {
            window.turnstile.reset(widgetId);
            securityMessage('Please complete a fresh security check before retrying.');
        } else {
            startSecurity();
        }
    }
    flow.addEventListener('submission-step', (event) => {
        if (event.detail === 3) startSecurity();
    });
    if (security) byId('retry-security').addEventListener('click', resetSecurity);
    function message(text) {
        byId('demo-complete').hidden = false;
        byId('demo-complete').textContent = text;
        byId('demo-complete').focus();
    }
    flow.addEventListener('local-submission', async (event) => {
        const button = byId('complete-demo');
        if (button.disabled) return;
        if (!event.detail.consent || !event.detail.privacy) {
            message('Please acknowledge both consent statements before recording a local test.');
            return;
        }
        if (security && !securityToken) {
            message('Please complete the security check before continuing. Your details are preserved.');
            startSecurity();
            return;
        }
        if (frozenPayload && JSON.stringify(event.detail) !== frozenPayload) {
            message('A recording attempt already started. Reload to start a different submission; unchanged retries use the same identifier.');
            return;
        }
        frozenPayload = JSON.stringify(event.detail);
        button.disabled = true;
        try {
            const result = await request('submit.php', { ...event.detail, request_key: requestKey, turnstile_token: securityToken }, flow.dataset.csrf);
            receipt = result.receipt;
            byId('receipt-link').href = `status.php#receipt=${receipt}`;
            byId('receipt-link-container').hidden = false;
            const state = await request('receipt.php', { receipt }, flow.dataset.csrf);
            message(state.state === 'verified'
                ? 'Email is confirmed. The sample submission is in the private local review queue.'
                : state.state === 'delivery_failed'
                ? 'Sample submission stored privately. Verification delivery is disabled in this local build; the record is pending verification, not in the review queue. You can retry delivery locally.'
                : 'Sample submission stored privately, pending email verification through the local mock mailbox. No real email was sent. This is not acceptance or rights verification.');
            byId('resend-verification').hidden = false;
        } catch (error) {
            if (error.status === 422) frozenPayload = null;
            message(error.message);
        } finally {
            button.disabled = false;
            if (security) resetSecurity();
        }
    });
    byId('resend-verification').addEventListener('click', async () => {
        const button = byId('resend-verification');
        button.disabled = true;
        try {
            const result = await request('resend.php', { receipt }, flow.dataset.csrf);
            message(result.message);
        } catch (error) {
            message(error.message);
        } finally {
            button.disabled = false;
        }
    });
    window.addEventListener('pagehide', () => {
        receipt = null;
        frozenPayload = null;
        securityToken = '';
        byId('receipt-link').removeAttribute('href');
        byId('receipt-link-container').hidden = true;
        byId('resend-verification').hidden = true;
    });
})();
