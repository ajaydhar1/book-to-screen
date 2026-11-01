(() => {
    'use strict';
    let settings;
    window.turnstile = {
        render(selector, options) {
            settings = options;
            document.querySelector(selector).textContent = 'OFFLINE MOCK security check (no provider connection)';
            queueMicrotask(() => options.callback('XXXX.DUMMY.TOKEN.XXXX'));
            return 'offline-widget';
        },
        reset() { queueMicrotask(() => settings.callback('XXXX.DUMMY.TOKEN.XXXX')); },
    };
})();
