'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');

const source = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'submission-intake.js'), 'utf8');
let checks = 0;
function check(condition, message) {
    assert.ok(condition, message);
    checks++;
}
function fixture(enabled = true) {
    const elements = new Map();
    function element(id) {
        const listeners = new Map();
        return {
            id, hidden: true, textContent: '', disabled: false, dataset: {},
            addEventListener(name, callback) { listeners.set(name, callback); },
            async emit(name, detail) { return listeners.get(name)?.({ detail }); },
            focus() { this.focused = true; },
            remove() { this.removed = true; },
            removeAttribute(name) { delete this[name]; },
        };
    }
    for (const id of [
        'prototype-flow', 'demo-complete', 'complete-demo', 'receipt-link',
        'receipt-link-container', 'resend-verification', 'security-message',
        'retry-security',
    ]) elements.set(id, element(id));
    elements.get('prototype-flow').dataset = { mode: 'local-intake', csrf: 'sample-csrf' };
    if (enabled) {
        elements.set('submission-security', element('submission-security'));
        elements.get('submission-security').dataset = { siteKey: 'public-test-key', action: 'test' };
    }
    const scripts = [];
    const calls = [];
    const windowEvents = new Map();
    let options = null;
    let resets = 0;
    const window = { addEventListener(name, fn) { windowEvents.set(name, fn); } };
    const turnstile = {
        render(selector, settings) { options = settings; return 'widget-id'; },
        reset(id) { assert.equal(id, 'widget-id'); resets++; },
    };
    const document = {
        getElementById(id) { return elements.get(id) || null; },
        createElement() { return element('script'); },
        head: { append(script) { scripts.push(script); } },
    };
    let responseStatus = 200;
    vm.runInNewContext(source, {
        window, document, crypto: webcrypto, AbortSignal, Uint8Array, URLSearchParams, console,
        fetch: async (endpoint, request) => {
            calls.push({ endpoint, request, body: JSON.parse(request.body) });
            return {
                ok: responseStatus === 200, status: responseStatus,
                json: async () => responseStatus === 200
                    ? endpoint === 'submit.php' ? { receipt: 'sample-receipt' } : { state: 'pending_verification' }
                    : { message: 'Please correct the fields.', errors: { verification: 'Retry the security check.' } },
            };
        },
    });
    return {
        elements, scripts, calls, windowEvents,
        step: (number) => elements.get('prototype-flow').emit('submission-step', number),
        submit: (detail) => elements.get('prototype-flow').emit('local-submission', detail),
        load() { window.turnstile = turnstile; scripts.at(-1).onload(); },
        get options() { return options; },
        get resets() { return resets; },
        set status(value) { responseStatus = value; },
    };
}

(async () => {
    const input = { consent: true, privacy: true, title: 'Sample Book', 'company-website': '' };
    const ui = fixture();
    await ui.step(1);
    await ui.step(2);
    check(ui.scripts.length === 0, 'Widget never loads in steps 1/2');
    await ui.step(3);
    check(ui.scripts.length === 1 && ui.scripts[0].src === 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', 'Loads exact official explicit-render script in step 3');
    await ui.step(3);
    check(ui.scripts.length === 1, 'Repeated navigation does not load another script');
    ui.load();
    check(ui.options.action === 'test' && ui.options.size === 'flexible' && ui.options['response-field'] === false, 'Context, flexible layout and no hidden token field');
    await ui.submit(input);
    check(ui.calls.length === 0 && ui.elements.get('demo-complete').textContent.includes('security check'), 'Missing token blocks request accessibly');
    ui.options.callback('sample-token');
    await ui.submit(input);
    check(ui.calls.length === 2 && ui.calls[0].body.turnstile_token === 'sample-token', 'Token reaches only same-origin submission request');
    check(ui.calls[0].request.headers['X-CSRF-Token'] === 'sample-csrf', 'CSRF preserved');
    check(ui.calls[0].body.request_key.length === 64, 'Idempotency preserved');
    check(ui.calls.every(({ endpoint }) => !endpoint.startsWith('https:')), 'No private payload sent to external browser endpoints');
    check(ui.resets === 1 && ui.elements.get('receipt-link-container').hidden === false, 'Attempt clears widget and displays private receipt');
    await ui.submit(input);
    check(ui.calls.length === 2, 'Spent token not reused in browser');
    ui.options['expired-callback']();
    check(ui.elements.get('security-message').textContent.includes('expired'), 'Accessible expiry feedback');
    ui.options['timeout-callback']();
    check(ui.elements.get('security-message').textContent.includes('timed out'), 'Accessible timeout feedback');
    ui.options['error-callback']();
    check(ui.elements.get('security-message').textContent.includes('details are preserved'), 'Provider error preserves form');
    await ui.elements.get('retry-security').emit('click');
    check(ui.resets === 2, 'Explicit retry resets widget');
    ui.options.callback('fresh-token');
    await ui.submit(input);
    check(ui.calls[2].body.request_key === ui.calls[0].body.request_key, 'Fresh token keeps same idempotency key');
    ui.options.callback('third-token');
    await ui.submit({ ...input, title: 'Different Book' });
    check(ui.calls.length === 4 && ui.elements.get('demo-complete').textContent.includes('recording attempt already started'), 'Ambiguous/finished attempts cannot change frozen payload');
    ui.windowEvents.get('pagehide')();
    check(ui.elements.get('receipt-link-container').hidden && !ui.elements.get('receipt-link').href, 'Page departure clears receipt UI');

    const corrected = fixture();
    await corrected.step(3);
    corrected.load();
    corrected.options.callback('token-one');
    corrected.status = 422;
    await corrected.submit(input);
    corrected.status = 200;
    corrected.options.callback('token-two');
    await corrected.submit({ ...input, title: 'Corrected Book' });
    check(corrected.calls.length === 3 && corrected.calls[1].body.title === 'Corrected Book', 'Server validation rejection permits editing and fresh-token retry');

    const blocked = fixture();
    await blocked.step(3);
    blocked.scripts[0].onerror();
    check(blocked.elements.get('security-message').textContent.includes('content blocker'), 'Script blocking has actionable message');
    await blocked.elements.get('retry-security').emit('click');
    check(blocked.scripts.length === 2 && blocked.scripts[0].removed, 'Failed script can be retried');
    await blocked.submit(input);
    check(blocked.calls.length === 0, 'Script failure cannot bypass verification');

    const disabled = fixture(false);
    await disabled.step(3);
    await disabled.submit(input);
    check(disabled.scripts.length === 0 && disabled.calls.length === 2, 'Disabled loopback fixture retains old flow without third-party requests');
    console.log(`PASS: ${checks} intake JavaScript checks; browser APIs and requests mocked.`);
})().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
