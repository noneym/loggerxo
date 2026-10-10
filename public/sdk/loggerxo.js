/*!
 * Loggerxo Browser SDK v0.1.0
 * https://loggerxo.com
 *
 * Auto-init:
 *   <script src="https://loggerxo.com/sdk/loggerxo.js" data-key="PUBLIC_KEY" data-env="production" data-release="1.0.0"></script>
 * Manual:
 *   Loggerxo.init({ key: 'PUBLIC_KEY', endpoint: 'https://loggerxo.com', environment: 'production' });
 */
(function (root) {
    'use strict';
    if (root.Loggerxo && root.Loggerxo.__loaded) { return; }

    var VERSION = '0.1.0';
    var SDK = { name: 'loggerxo-browser', version: VERSION };

    var config = {
        key: '',
        endpoint: '',
        environment: 'production',
        release: '',
        captureConsole: ['error', 'warn'],
        consoleBreadcrumbs: true,
        captureUnhandled: true,
        captureHttpErrors: true,
        httpErrorStatus: 500,
        captureResourceErrors: true,
        tracing: true,
        maxBreadcrumbs: 30,
        flushInterval: 2500,
        maxBatch: 25,
        maxEventsPerMinute: 60,
        sampleRate: 1,
        ignoreErrors: [],
        ignoreUrls: [],
        beforeSend: null,
        debug: false,
        user: null,
        tags: {}
    };

    var state = {
        initialized: false,
        queue: [],
        breadcrumbs: [],
        timer: null,
        sentThisMinute: 0,
        minuteStart: 0,
        lastFingerprints: {},
        pageloadSent: false,
        lcp: 0,
        fcp: 0,
        cls: 0
    };

    /* ------------------------------------------------------------ utils */
    function now() { return new Date().toISOString(); }

    function uuid() {
        if (root.crypto && root.crypto.randomUUID) { return root.crypto.randomUUID(); }
        var d = Date.now();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (d + Math.random() * 16) % 16 | 0; d = Math.floor(d / 16);
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    function log() {
        if (config.debug && root.console && root.console.__loggerxoOriginal) {
            root.console.__loggerxoOriginal.log.apply(root.console, ['[loggerxo]'].concat([].slice.call(arguments)));
        }
    }

    function safeStringify(value, depth) {
        depth = depth || 0;
        try {
            if (value === undefined) { return 'undefined'; }
            if (value === null) { return 'null'; }
            if (typeof value === 'string') { return value; }
            if (typeof value === 'number' || typeof value === 'boolean') { return String(value); }
            if (value instanceof Error) { return value.name + ': ' + value.message; }
            if (typeof value === 'function') { return '[Function ' + (value.name || 'anonymous') + ']'; }
            if (root.Element && value instanceof root.Element) { return '<' + value.tagName.toLowerCase() + '>'; }
            if (depth > 2) { return Object.prototype.toString.call(value); }
            var seen = [];
            var str = JSON.stringify(value, function (k, v) {
                if (typeof v === 'object' && v !== null) {
                    if (seen.indexOf(v) !== -1) { return '[Circular]'; }
                    seen.push(v);
                }
                if (typeof v === 'bigint') { return v.toString(); }
                return v;
            });
            return str.length > 4000 ? str.slice(0, 4000) + '...' : str;
        } catch (e) {
            return '[Unserializable]';
        }
    }

    function formatArgs(args) {
        var parts = [];
        for (var i = 0; i < args.length; i++) { parts.push(safeStringify(args[i])); }
        return parts.join(' ');
    }

    function truncate(s, n) { s = String(s || ''); return s.length > n ? s.slice(0, n) + '...' : s; }

    function extend(target, src) {
        for (var k in src) { if (Object.prototype.hasOwnProperty.call(src, k)) { target[k] = src[k]; } }
        return target;
    }

    function elementPath(el) {
        var parts = [];
        var depth = 0;
        while (el && el.nodeType === 1 && depth < 5) {
            var part = el.tagName.toLowerCase();
            if (el.id) { part += '#' + el.id; parts.unshift(part); break; }
            if (el.className && typeof el.className === 'string') {
                var cls = el.className.trim().split(/\s+/).slice(0, 2).join('.');
                if (cls) { part += '.' + cls; }
            }
            parts.unshift(part);
            el = el.parentNode;
            depth++;
        }
        return parts.join(' > ');
    }

    /* ----------------------------------------------------- stack parsing */
    var CHROME_RE = /^\s*at (?:(.*?) ?\()?((?:file|https?|blob|chrome-extension|native|eval|webpack|<anonymous>|[a-z]:|\/).*?)(?::(\d+))?(?::(\d+))?\)?\s*$/i;
    var GECKO_RE = /^\s*(.*?)(?:\((.*?)\))?(?:^|@)((?:file|https?|blob|chrome|webpack|resource|moz-extension|safari-extension|safari-web-extension|capacitor)?:\/.*?|\[native code\]|[^@]*(?:bundle|\d+\.js)|\/[\w\-. \/=]+)(?::(\d+))?(?::(\d+))?\s*$/i;

    function parseStack(stack) {
        var frames = [];
        if (!stack || typeof stack !== 'string') { return frames; }
        var lines = stack.split('\n');
        for (var i = 0; i < lines.length && frames.length < 50; i++) {
            var line = lines[i];
            var m = CHROME_RE.exec(line);
            if (m) {
                frames.push({ function: m[1] || '<anonymous>', file: m[2], line: m[3] ? parseInt(m[3], 10) : null, col: m[4] ? parseInt(m[4], 10) : null, in_app: isInApp(m[2]) });
                continue;
            }
            m = GECKO_RE.exec(line);
            if (m) {
                frames.push({ function: m[1] || '<anonymous>', file: m[3], line: m[4] ? parseInt(m[4], 10) : null, col: m[5] ? parseInt(m[5], 10) : null, in_app: isInApp(m[3]) });
            }
        }
        return frames;
    }

    function isInApp(file) {
        if (!file) { return false; }
        if (/node_modules|loggerxo\.js|chrome-extension|moz-extension|safari-extension|\[native code\]|<anonymous>/.test(file)) { return false; }
        try { return file.indexOf(root.location.origin) === 0 || file.charAt(0) === '/'; } catch (e) { return true; }
    }

    function exceptionFromError(err) {
        if (!err) { return null; }
        if (typeof err === 'string') { return { type: 'Error', value: err, frames: [] }; }
        if (typeof err !== 'object') { return { type: 'Error', value: safeStringify(err), frames: [] }; }
        var ex = {
            type: err.name || (err.constructor && err.constructor.name) || 'Error',
            value: truncate(err.message || safeStringify(err), 2000),
            frames: parseStack(err.stack)
        };
        if (err.cause && err.cause !== err) {
            ex.chain = [exceptionFromError(err.cause)];
        }
        return ex;
    }

    /* ---------------------------------------------------------- context */
    function browserInfo() {
        var nav = root.navigator || {};
        var scr = root.screen || {};
        return {
            language: nav.language,
            referrer: root.document ? root.document.referrer : '',
            screen: scr.width ? scr.width + 'x' + scr.height : undefined,
            viewport: root.innerWidth ? root.innerWidth + 'x' + root.innerHeight : undefined,
            online: nav.onLine,
            memory: nav.deviceMemory,
            cores: nav.hardwareConcurrency,
            connection: nav.connection ? nav.connection.effectiveType : undefined
        };
    }

    function baseEvent(type, level) {
        return {
            event_id: uuid(),
            type: type,
            level: level || 'error',
            timestamp: now(),
            platform: 'javascript',
            logger: 'browser',
            url: root.location ? root.location.href : '',
            user_agent: root.navigator ? root.navigator.userAgent : '',
            user: config.user || undefined,
            tags: extend({}, config.tags),
            context: { browser: browserInfo() },
            breadcrumbs: state.breadcrumbs.slice(-config.maxBreadcrumbs)
        };
    }

    /* ------------------------------------------------------ breadcrumbs */
    function addBreadcrumb(crumb) {
        crumb = extend({ timestamp: now(), type: 'default', level: 'info' }, crumb || {});
        if (crumb.message) { crumb.message = truncate(crumb.message, 500); }
        state.breadcrumbs.push(crumb);
        if (state.breadcrumbs.length > config.maxBreadcrumbs) { state.breadcrumbs.shift(); }
    }

    /* ----------------------------------------------------------- queue */
    function shouldDrop(event) {
        if (config.sampleRate < 1 && event.type !== 'transaction' && Math.random() > config.sampleRate) { return true; }
        var t = Date.now();
        if (t - state.minuteStart > 60000) { state.minuteStart = t; state.sentThisMinute = 0; }
        if (state.sentThisMinute >= config.maxEventsPerMinute) { return true; }
        var msg = event.message || (event.exception && event.exception.value) || '';
        for (var i = 0; i < config.ignoreErrors.length; i++) {
            var p = config.ignoreErrors[i];
            if (p instanceof RegExp ? p.test(msg) : msg.indexOf(p) !== -1) { return true; }
        }
        for (var j = 0; j < config.ignoreUrls.length; j++) {
            var u = config.ignoreUrls[j];
            if (u instanceof RegExp ? u.test(event.url) : event.url.indexOf(u) !== -1) { return true; }
        }
        if (event.type !== 'transaction') {
            var fp = event.type + '|' + event.level + '|' + msg.slice(0, 200);
            if (state.lastFingerprints[fp] && t - state.lastFingerprints[fp] < 2000) { return true; }
            state.lastFingerprints[fp] = t;
        }
        return false;
    }

    function enqueue(event) {
        if (!state.initialized || !config.key) { return; }
        if (shouldDrop(event)) { log('dropped', event); return; }
        if (typeof config.beforeSend === 'function') {
            try { event = config.beforeSend(event); } catch (e) { /* ignore */ }
            if (!event) { return; }
        }
        state.sentThisMinute++;
        state.queue.push(event);
        if (state.queue.length >= config.maxBatch) { flush(); }
        else if (!state.timer) { state.timer = setTimeout(flush, config.flushInterval); }
    }

    function endpointUrl() {
        return config.endpoint.replace(/\/+$/, '') + '/api/v1/browser?key=' + encodeURIComponent(config.key);
    }

    function flush(useBeacon) {
        if (state.timer) { clearTimeout(state.timer); state.timer = null; }
        if (!state.queue.length) { return; }
        var batch = state.queue.splice(0, config.maxBatch);
        var body;
        try {
            body = JSON.stringify({ sdk: SDK, environment: config.environment, release: config.release, events: batch });
        } catch (e) { return; }
        var url = endpointUrl();
        log('flush', batch.length);
        var sent = false;
        if (useBeacon && root.navigator && root.navigator.sendBeacon) {
            try { sent = root.navigator.sendBeacon(url, new Blob([body], { type: 'text/plain;charset=UTF-8' })); } catch (e) { sent = false; }
        }
        if (!sent && root.fetch) {
            var f = root.fetch.__loggerxoOriginal || root.fetch;
            try {
                f.call(root, url, { method: 'POST', body: body, keepalive: true, mode: 'cors', credentials: 'omit', headers: { 'Content-Type': 'text/plain;charset=UTF-8' } })['catch'](function () { /* swallow */ });
                sent = true;
            } catch (e) { sent = false; }
        }
        if (!sent) {
            try {
                var X = root.XMLHttpRequest;
                var xhr = new X();
                xhr.__loggerxoInternal = true;
                xhr.open('POST', url, true);
                xhr.setRequestHeader('Content-Type', 'text/plain;charset=UTF-8');
                xhr.send(body);
            } catch (e) { /* give up */ }
        }
        if (state.queue.length) { state.timer = setTimeout(flush, config.flushInterval); }
    }

    /* ------------------------------------------------------ capturing */
    function captureException(err, extra) {
        var ex = exceptionFromError(err);
        if (!ex) { return; }
        var ev = baseEvent('error', 'error');
        ev.exception = ex;
        ev.message = ex.type + ': ' + ex.value;
        ev.logger = (extra && extra.logger) || 'javascript';
        if (extra) {
            if (extra.tags) { extend(ev.tags, extra.tags); }
            if (extra.context) { extend(ev.context, extra.context); }
            if (extra.level) { ev.level = extra.level; }
            if (extra.fingerprint) { ev.fingerprint = extra.fingerprint; }
        }
        enqueue(ev);
        return ev.event_id;
    }

    function captureMessage(message, level, extra) {
        var ev = baseEvent('console', level || 'info');
        ev.message = truncate(message, 4000);
        ev.logger = (extra && extra.logger) || 'message';
        if (extra && extra.tags) { extend(ev.tags, extra.tags); }
        if (extra && extra.context) { extend(ev.context, extra.context); }
        enqueue(ev);
        return ev.event_id;
    }

    function installErrorHandlers() {
        root.addEventListener('error', function (e) {
            if (!e) { return; }
            var target = e.target;
            if (target && target !== root && target.tagName && config.captureResourceErrors) {
                var src = target.src || target.href || '';
                if (!src) { return; }
                var ev = baseEvent('console', 'error');
                ev.logger = 'resource';
                ev.message = 'Resource failed to load: ' + target.tagName.toLowerCase() + ' ' + truncate(src, 500);
                ev.context.resource = { tag: target.tagName.toLowerCase(), src: src };
                ev.fingerprint = ['resource', target.tagName.toLowerCase(), src.split('?')[0]];
                enqueue(ev);
                return;
            }
            var err = e.error;
            if (!err) {
                err = new Error(e.message || 'Unknown error');
                err.name = 'Error';
                err.stack = '';
            }
            var ev2 = baseEvent('error', 'error');
            ev2.exception = exceptionFromError(err);
            if (!ev2.exception.frames.length && e.filename) {
                ev2.exception.frames = [{ function: '<anonymous>', file: e.filename, line: e.lineno || null, col: e.colno || null, in_app: isInApp(e.filename) }];
            }
            ev2.message = ev2.exception.type + ': ' + ev2.exception.value;
            ev2.logger = 'window.onerror';
            enqueue(ev2);
        }, true);

        root.addEventListener('unhandledrejection', function (e) {
            var reason = e && e.reason;
            var ev = baseEvent('error', 'error');
            if (reason && typeof reason === 'object' && (reason.stack || reason.message)) {
                ev.exception = exceptionFromError(reason);
            } else {
                ev.exception = { type: 'UnhandledRejection', value: truncate(safeStringify(reason), 2000), frames: [] };
            }
            ev.message = ev.exception.type + ': ' + ev.exception.value;
            ev.logger = 'unhandledrejection';
            enqueue(ev);
        });
    }

    function installConsole() {
        if (!root.console) { return; }
        var methods = ['log', 'info', 'warn', 'error', 'debug'];
        var original = {};
        for (var i = 0; i < methods.length; i++) {
            (function (method) {
                var orig = root.console[method];
                if (typeof orig !== 'function') { return; }
                original[method] = orig;
                root.console[method] = function () {
                    var args = [].slice.call(arguments);
                    try {
                        var level = method === 'log' ? 'info' : (method === 'warn' ? 'warning' : method);
                        var msg = formatArgs(args);
                        if (config.consoleBreadcrumbs) {
                            addBreadcrumb({ type: 'console', category: 'console', level: level, message: msg });
                        }
                        if (config.captureConsole.indexOf(method) !== -1) {
                            var ev = baseEvent('console', level);
                            ev.message = truncate(msg, 4000);
                            ev.logger = 'console.' + method;
                            ev.breadcrumbs = ev.breadcrumbs.slice(0, -1);
                            for (var a = 0; a < args.length; a++) {
                                if (args[a] instanceof Error) { ev.exception = exceptionFromError(args[a]); break; }
                            }
                            enqueue(ev);
                        }
                    } catch (e) { /* never break console */ }
                    return orig.apply(root.console, args);
                };
            })(methods[i]);
        }
        root.console.__loggerxoOriginal = original;
    }

    function recordHttp(method, url, status, duration, failed) {
        if (url.indexOf('/api/v1/browser') !== -1) { return; }
        var level = failed || status >= 500 ? 'error' : (status >= 400 ? 'warning' : 'info');
        addBreadcrumb({ type: 'http', category: 'http', level: level, message: method + ' ' + truncate(url, 300) + (status ? ' [' + status + ']' : ' [failed]'), data: { method: method, url: truncate(url, 500), status_code: status, duration_ms: Math.round(duration) } });
        if (config.captureHttpErrors && (failed || status >= config.httpErrorStatus)) {
            var ev = baseEvent('console', 'error');
            ev.logger = 'http';
            ev.message = (failed ? 'Network request failed: ' : 'HTTP ' + status + ': ') + method + ' ' + truncate(url, 500);
            ev.context.http = { method: method, url: url, status_code: status, duration_ms: Math.round(duration) };
            ev.fingerprint = ['http', method, String(status || 'failed'), url.split('?')[0].replace(/\d+/g, ':id')];
            enqueue(ev);
        }
    }

    function installFetch() {
        if (!root.fetch) { return; }
        var orig = root.fetch;
        var wrapped = function (input, init) {
            var method = (init && init.method) || (input && input.method) || 'GET';
            var url = typeof input === 'string' ? input : (input && input.url) || String(input);
            var start = Date.now();
            return orig.apply(this, arguments).then(function (res) {
                recordHttp(method.toUpperCase(), url, res.status, Date.now() - start, false);
                return res;
            }, function (err) {
                recordHttp(method.toUpperCase(), url, 0, Date.now() - start, true);
                throw err;
            });
        };
        wrapped.__loggerxoOriginal = orig;
        root.fetch = wrapped;
    }

    function installXhr() {
        var X = root.XMLHttpRequest;
        if (!X || !X.prototype) { return; }
        var open = X.prototype.open;
        var send = X.prototype.send;
        X.prototype.open = function (method, url) {
            this.__lx = { method: String(method || 'GET').toUpperCase(), url: String(url || '') };
            return open.apply(this, arguments);
        };
        X.prototype.send = function () {
            var xhr = this;
            if (xhr.__lx && !xhr.__loggerxoInternal) {
                var start = Date.now();
                xhr.addEventListener('loadend', function () {
                    recordHttp(xhr.__lx.method, xhr.__lx.url, xhr.status, Date.now() - start, xhr.status === 0);
                });
            }
            return send.apply(this, arguments);
        };
    }

    function installNavigation() {
        if (!root.history) { return; }
        var last = root.location.href;
        function record(to) {
            if (to === last) { return; }
            addBreadcrumb({ type: 'navigation', category: 'navigation', message: truncate(last, 300) + ' -> ' + truncate(to, 300), data: { from: last, to: to } });
            last = to;
        }
        var push = root.history.pushState;
        var replace = root.history.replaceState;
        if (push) { root.history.pushState = function () { var r = push.apply(this, arguments); record(root.location.href); return r; }; }
        if (replace) { root.history.replaceState = function () { var r = replace.apply(this, arguments); record(root.location.href); return r; }; }
        root.addEventListener('popstate', function () { record(root.location.href); });
        root.addEventListener('hashchange', function () { record(root.location.href); });
    }

    function installClicks() {
        if (!root.document) { return; }
        root.document.addEventListener('click', function (e) {
            try {
                var el = e.target;
                if (!el || el.nodeType !== 1) { return; }
                var text = (el.innerText || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 60);
                addBreadcrumb({ type: 'ui', category: 'ui.click', message: elementPath(el) + (text ? ' "' + text + '"' : '') });
            } catch (err) { /* ignore */ }
        }, true);
        root.document.addEventListener('submit', function (e) {
            try { addBreadcrumb({ type: 'ui', category: 'ui.submit', message: elementPath(e.target) }); } catch (err) { /* ignore */ }
        }, true);
    }

    /* ---------------------------------------------------------- tracing */
    function installTracing() {
        if (!root.performance || !root.PerformanceObserver) { return; }
        try {
            new PerformanceObserver(function (list) {
                var entries = list.getEntries();
                for (var i = 0; i < entries.length; i++) { state.lcp = entries[i].startTime; }
            }).observe({ type: 'largest-contentful-paint', buffered: true });
        } catch (e) { /* unsupported */ }
        try {
            new PerformanceObserver(function (list) {
                var entries = list.getEntries();
                for (var i = 0; i < entries.length; i++) { if (entries[i].name === 'first-contentful-paint') { state.fcp = entries[i].startTime; } }
            }).observe({ type: 'paint', buffered: true });
        } catch (e) { /* unsupported */ }
        try {
            new PerformanceObserver(function (list) {
                var entries = list.getEntries();
                for (var i = 0; i < entries.length; i++) { if (!entries[i].hadRecentInput) { state.cls += entries[i].value; } }
            }).observe({ type: 'layout-shift', buffered: true });
        } catch (e) { /* unsupported */ }

        function sendPageload() {
            if (state.pageloadSent) { return; }
            var nav = root.performance.getEntriesByType ? root.performance.getEntriesByType('navigation')[0] : null;
            if (!nav || !nav.loadEventEnd) { return; }
            state.pageloadSent = true;
            var ev = baseEvent('transaction', 'info');
            ev.op = 'pageload';
            ev.name = root.location.pathname;
            ev.http_method = 'GET';
            ev.status_code = nav.responseStatus || 200;
            ev.duration_ms = Math.round(nav.loadEventEnd);
            ev.measurements = {
                ttfb_ms: Math.round(nav.responseStart),
                dom_content_loaded_ms: Math.round(nav.domContentLoadedEventEnd),
                load_ms: Math.round(nav.loadEventEnd),
                fcp_ms: Math.round(state.fcp),
                lcp_ms: Math.round(state.lcp),
                cls: Math.round(state.cls * 1000) / 1000,
                transfer_kb: Math.round((nav.transferSize || 0) / 1024)
            };
            var spans = [];
            var push = function (op, desc, start, end) { if (end > start) { spans.push({ op: op, description: desc, start_ms: Math.round(start), duration_ms: Math.round(end - start) }); } };
            push('browser.dns', 'DNS lookup', nav.domainLookupStart, nav.domainLookupEnd);
            push('browser.connect', 'TCP/TLS connect', nav.connectStart, nav.connectEnd);
            push('browser.request', 'Request to first byte', nav.requestStart, nav.responseStart);
            push('browser.response', 'Response download', nav.responseStart, nav.responseEnd);
            push('browser.dom', 'DOM processing', nav.responseEnd, nav.domContentLoadedEventEnd);
            push('browser.load', 'Load event', nav.domContentLoadedEventEnd, nav.loadEventEnd);
            var resources = root.performance.getEntriesByType('resource') || [];
            var slow = resources.filter(function (r) { return r.duration > 250; }).sort(function (a, b) { return b.duration - a.duration; }).slice(0, 15);
            for (var i = 0; i < slow.length; i++) {
                push('resource.' + (slow[i].initiatorType || 'other'), truncate(slow[i].name, 300), slow[i].startTime, slow[i].startTime + slow[i].duration);
            }
            ev.spans = spans;
            ev.breadcrumbs = [];
            enqueue(ev);
        }
        if (root.document.readyState === 'complete') { setTimeout(sendPageload, 3000); }
        else { root.addEventListener('load', function () { setTimeout(sendPageload, 3000); }); }
        root.addEventListener('pagehide', sendPageload);
    }

    /* ------------------------------------------------------------ init */
    function init(options) {
        if (state.initialized) { extend(config, options || {}); return api; }
        extend(config, options || {});
        if (!config.endpoint) {
            try { config.endpoint = (root.document.currentScript && root.document.currentScript.src) ? new URL(root.document.currentScript.src).origin : root.location.origin; } catch (e) { config.endpoint = ''; }
        }
        if (!config.key) { return api; }
        state.initialized = true;
        state.minuteStart = Date.now();
        if (config.captureUnhandled) { installErrorHandlers(); }
        installConsole();
        installFetch();
        installXhr();
        installNavigation();
        installClicks();
        if (config.tracing) { installTracing(); }
        root.addEventListener('pagehide', function () { flush(true); });
        root.document.addEventListener('visibilitychange', function () { if (root.document.visibilityState === 'hidden') { flush(true); } });
        log('initialized', config.endpoint);
        return api;
    }

    var api = {
        __loaded: true,
        VERSION: VERSION,
        init: init,
        captureException: captureException,
        captureMessage: captureMessage,
        addBreadcrumb: addBreadcrumb,
        setUser: function (user) { config.user = user || null; return api; },
        setTag: function (k, v) { config.tags[k] = String(v); return api; },
        setTags: function (tags) { extend(config.tags, tags || {}); return api; },
        setRelease: function (r) { config.release = r; return api; },
        setEnvironment: function (e) { config.environment = e; return api; },
        flush: function () { flush(false); return api; },
        wrap: function (fn) {
            return function () { try { return fn.apply(this, arguments); } catch (e) { captureException(e); throw e; } };
        }
    };

    root.Loggerxo = api;

    try {
        var script = root.document && root.document.currentScript;
        if (script && script.getAttribute('data-key')) {
            var ds = script.dataset || {};
            init({
                key: ds.key,
                endpoint: ds.endpoint || new URL(script.src).origin,
                environment: ds.env || ds.environment || 'production',
                release: ds.release || '',
                debug: ds.debug === 'true',
                tracing: ds.tracing !== 'false',
                captureConsole: ds.console ? ds.console.split(',') : config.captureConsole
            });
        }
    } catch (e) { /* ignore */ }
})(typeof window !== 'undefined' ? window : this);
