'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '..', '__turnstile-validation', 'challenge.js'), 'utf8');
let checks = 0;
function check(condition, message) { assert.ok(condition, message); checks++; }
function fixture() {
    const nodes = new Map(['challenge-test', 'result', 'widget-status', 'diagnostic', 'fresh', 'reuse', 'hold', 'expired', 'missing', 'invalid', 'reset']
        .map((id) => [id, { dataset: {}, textContent: '' }]));
    nodes.get('challenge-test').dataset = { siteKey: 'synthetic-site', csrf: 'synthetic-csrf' };
    let script;
    let options;
    let time = 100000;
    let resets = 0;
    let status = 200;
    let release = null;
    let deferred = false;
    let malformed = false;
    const requests = [];
    const events = new Map();
    const document = {
        getElementById(id) { return nodes.get(id); },
        createElement() { return {}; },
        head: { append(value) { script = value; } },
    };
    vm.runInNewContext(source, {
        document, AbortSignal, Date: { now: () => time },
        window: {
            turnstile: { render(selector, settings) { options = settings; return 'widget'; }, reset() { resets++; } },
            addEventListener(name, callback) { events.set(name, callback); },
        },
        fetch: async (endpoint, settings) => {
            requests.push({ endpoint, ...settings, payload: JSON.parse(settings.body) });
            const responseStatus = status;
            if (deferred) await new Promise((resolve) => { release = resolve; });
            return { status: responseStatus, json: async () => ({
                message: malformed ? null : responseStatus === 200 ? 'Accepted' : 'Rejected',
                diagnostic: { test_number: requests.length, test_type: JSON.parse(settings.body).test_type,
                    token_id: 'a'.repeat(64), siteverify_made: true, provider_http_status: 200,
                    success: responseStatus === 200, error_codes: ['timeout-or-duplicate', 'private-unknown-code'],
                    same_as_previous_siteverify_token: requests.length > 1, hostname_pass: true,
                    action_pass: true, timestamp_pass: true, decision: responseStatus === 200 ? 'accept' : 'reject' },
            }) };
        },
    });
    return { nodes, requests, events, load() { script.onload(); },
        get options() { return options; }, get resets() { return resets; },
        set status(value) { status = value; }, set time(value) { time = value; },
        set deferred(value) { deferred = value; }, release() { release(); },
        set malformed(value) { malformed = value; },
        click(id) { return nodes.get(id).onclick(); } };
}
async function settle() { await new Promise((resolve) => setImmediate(resolve)); }
(async () => {
    const ui = fixture();
    ui.load();
    check(ui.options.action === 'author_submission' && ui.options['response-field'] === false, 'Exact action, no hidden token field');
    check(ui.options.retry === 'never' && ui.options['refresh-expired'] === 'manual', 'Widget errors/expiry do not automatically retry');
    ui.click('fresh');
    check(ui.requests.length === 0, 'No submit before challenge');
    ui.options.callback('unused-token');
    ui.click('hold');
    check(ui.requests.length === 0 && ui.resets === 1, 'Expiry token held unused, fresh challenge separate');
    ui.click('expired');
    check(ui.requests.length === 0, 'Expiry test must wait');
    ui.options.callback('fresh-token');
    ui.click('fresh');
    await settle();
    check(ui.requests[0].payload.token === 'fresh-token', 'Fresh token sent only on manual click');
    check(ui.requests[0].headers['X-CSRF-Token'] === 'synthetic-csrf' && ui.requests[0].endpoint === 'index.php', 'CSRF and same-origin endpoint');
    check(ui.nodes.get('result').textContent === 'Test 1 - Fresh token (HTTP 200): Accepted', 'Fresh success has distinct label and HTTP status');
    ui.options.callback('different-token');
    ui.options['expired-callback']();
    check(ui.nodes.get('result').textContent === 'Test 1 - Fresh token (HTTP 200): Accepted', 'Widget callbacks cannot overwrite verification results');
    ui.status = 422;
    ui.deferred = true;
    ui.click('reuse');
    check(ui.nodes.get('result').textContent === 'Test 2 - Reused token: In progress; previous result cleared.', 'Reuse immediately clears stale success while response pending');
    check(ui.nodes.get('fresh').disabled && ui.nodes.get('reuse').disabled, 'Controls disabled while validation pending');
    ui.click('reuse'); ui.click('hold'); ui.click('expired');
    check(ui.requests.length === 2 && ui.nodes.get('result').textContent.includes('In progress'), 'Pending clicks do not send or overwrite progress');
    ui.release();
    await settle();
    ui.deferred = false;
    check(ui.requests[1].payload.token === 'fresh-token', 'Explicit reuse tests exactly last submitted token');
    check(ui.nodes.get('result').textContent === 'Test 2 - Reused token (HTTP 422): Rejected', 'Reuse rejection clearly replaces previous success');
    check(!ui.nodes.get('reuse').disabled, 'Expected rejection restores manual controls');
    ui.time = 402000;
    ui.click('expired');
    await settle();
    check(ui.requests[2].payload.token === 'unused-token', 'Expiry uses held unsubmitted token after 301 seconds');
    ui.click('missing');
    await settle();
    ui.click('invalid');
    await settle();
    check(ui.requests[3].payload.token === '' && ui.requests[4].payload.token === 'synthetic-invalid-validation-token', 'Negative tests use no personal fields');
    check(ui.requests.every((request) => Object.keys(request.payload).join() === 'token,test_type'), 'Only token and allowlisted test type posted');
    ui.status = 503;
    ui.click('reuse');
    await settle();
    const count = ui.requests.length;
    ui.click('reuse'); ui.click('reset');
    await settle();
    check(ui.requests.length === count && ui.resets === 1, 'Unavailable response stops future sends and reset');
    check(ui.nodes.get('result').textContent.includes('Reused token (HTTP 503)') && ui.nodes.get('reuse').disabled,
        'Unsafe response has its own test label and leaves controls disabled');
    const hidden = fixture();
    hidden.load();
    hidden.options.callback('private-memory-token');
    hidden.events.get('pagehide')();
    hidden.click('fresh'); hidden.click('reuse');
    check(hidden.requests.length === 0, 'Page departure clears retained tokens');
    const rejected = fixture();
    rejected.load();
    rejected.status = 422;
    rejected.options.callback('rejected-token');
    rejected.click('fresh');
    await settle();
    rejected.click('reuse');
    check(rejected.requests.length === 1, 'Rejected fresh token is not retained as successfully verified reuse token');
    const malformed = fixture();
    malformed.load();
    malformed.malformed = true;
    malformed.options.callback('synthetic-token');
    malformed.click('fresh');
    await settle();
    malformed.click('reuse');
    check(malformed.nodes.get('result').textContent.startsWith('Test 1 - Fresh token: Request outcome unavailable.')
        && malformed.requests.length === 1 && malformed.nodes.get('reuse').disabled,
    'Malformed response explicitly stops and never retains a verified token or retries');
    const replay = fixture();
    replay.load();
    replay.options.callback('identical-synthetic-token');
    replay.click('fresh');
    await settle();
    replay.options.callback('replacement-synthetic-token');
    replay.click('reuse');
    await settle();
    check(replay.requests[0].payload.token === replay.requests[1].payload.token, 'Fresh and reuse serialize identical token bytes despite new widget callback');
    check(replay.nodes.get('result').textContent.includes('UNEXPECTED REPLAY ACCEPTANCE')
        && replay.nodes.get('reuse').disabled, 'Duplicate provider acceptance explicitly stops without concealing HTTP 200');
    replay.click('reuse'); replay.click('fresh');
    check(replay.requests.length === 2, 'Replay anomaly cannot trigger further manual requests');
    check(replay.nodes.get('diagnostic').textContent.includes('Same token as previous Siteverify request: YES')
        && replay.nodes.get('diagnostic').textContent.includes('Provider success: true')
        && !replay.nodes.get('diagnostic').textContent.includes('private-unknown-code'),
        'Diagnostic uses safe labelled fields and only allowlisted provider codes without further requests');
    console.log(`PASS: ${checks} challenge JavaScript checks; mocked browser/widget/fetch only.`);
})().catch((error) => { console.error(error); process.exitCode = 1; });
