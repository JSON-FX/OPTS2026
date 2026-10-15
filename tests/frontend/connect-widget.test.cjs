const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const ts = require('typescript');
const source = ts.transpileModule(fs.readFileSync('resources/js/connect-widget.ts', 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
}).outputText;

function harness(loaded = true) {
    const calls = { mounts: [], destroys: 0, scripts: [], badges: [], requests: [], unsubscribed: false };
    let navigate;
    const window = {};
    const loader = { mount(options) { calls.mounts.push(options); return { destroy() { calls.destroys++; } }; } };
    if (loaded) window.LGUConnectWidget = loader;
    const context = {
        exports: {}, window,
        require: () => ({ router: { on(event, callback) { assert.equal(event, 'navigate'); navigate = callback; return () => { calls.unsubscribed = true; }; } } }),
        document: {
            querySelector: () => ({ content: 'test-csrf' }),
            createElement() { return {
                style: {}, attributes: {}, hidden: false, textContent: '',
                setAttribute(name, value) { this.attributes[name] = value; },
                addEventListener(event, callback) { this.load = callback; },
                remove() { this.removed = true; },
            }; },
            body: { appendChild(badge) { calls.badges.push(badge); } },
            head: { appendChild(script) { calls.scripts.push(script); } },
        },
        fetch: async (url, options) => { calls.requests.push({ url, options }); return { ok: true, json: async () => ({ launch_ref: 'opaque-reference' }) }; },
    };
    vm.runInNewContext(source, context);
    return { calls, window, loader, start: context.exports.initializeConnectWidget, navigate: (props) => navigate({ detail: { page: { props } } }) };
}
const config = { origin: 'https://connect.example.test', launchUrl: '/connect/pilot/launch', identity: 'employee-a' };

test('ordinary Inertia navigation preserves the same widget; identity/logout destroys it', () => {
    const h = harness();
    const dispose = h.start({ connectWidget: config });
    h.navigate({ connectWidget: { ...config }, unrelated: 'new page' });
    assert.equal(h.calls.mounts.length, 1);
    assert.equal(h.calls.destroys, 0);
    h.navigate({ connectWidget: { ...config, identity: 'employee-b' } });
    assert.equal(h.calls.destroys, 1);
    assert.equal(h.calls.mounts.length, 2);
    h.navigate({ connectWidget: null });
    assert.equal(h.calls.destroys, 2);
    dispose();
    assert.equal(h.calls.unsubscribed, true);
});

test('a delayed loader cannot mount the previous employee after logout', () => {
    const h = harness(false);
    h.start({ connectWidget: config });
    const script = h.calls.scripts[0];
    h.navigate({ connectWidget: null });
    h.window.LGUConnectWidget = h.loader;
    script.load();
    assert.equal(script.removed, true);
    assert.equal(h.calls.mounts.length, 0);
});

test('anonymous pages never fetch the loader; launch sends only CSRF-protected host request', async () => {
    const h = harness();
    h.start({ connectWidget: null });
    assert.equal(h.calls.mounts.length, 0);
    assert.equal(h.calls.scripts.length, 0);
    h.navigate({ connectWidget: config });
    const signal = new AbortController().signal;
    await h.calls.mounts[0].launch({ signal });
    const { url, options } = h.calls.requests[0];
    assert.equal(url, '/connect/pilot/launch');
    assert.equal(options.method, 'POST');
    assert.equal(options.credentials, 'same-origin');
    assert.equal(options.redirect, 'error');
    assert.equal(options.headers['X-CSRF-TOKEN'], 'test-csrf');
    assert.equal(options.body, undefined);
    assert.equal(options.signal, signal);
});


test('unread badge appears, caps the protocol count, clears, and survives ordinary navigation', () => {
    const h = harness();
    h.start({ connectWidget: config });
    const badge = h.calls.badges[0];
    const { onBadge } = h.calls.mounts[0];
    assert.equal(badge.hidden, true);
    onBadge(4);
    assert.equal(badge.hidden, false);
    assert.equal(badge.textContent, '4');
    assert.equal(badge.attributes['aria-label'], '4 unread Connect messages');
    assert.equal(badge.attributes.role, 'status');
    h.navigate({ connectWidget: { ...config } });
    assert.equal(h.calls.badges.length, 1);
    assert.equal(badge.removed, undefined);
    onBadge(99);
    assert.equal(badge.textContent, '99+');
    assert.equal(badge.attributes['aria-label'], '99 or more unread Connect messages');
    onBadge(120);
    assert.equal(badge.textContent, '99+');
    onBadge(0);
    assert.equal(badge.hidden, true);
    assert.equal(badge.textContent, '');
});

test('identity changes, logout and disposal remove badges and ignore delayed old callbacks', () => {
    const h = harness();
    const dispose = h.start({ connectWidget: config });
    const first = h.calls.badges[0];
    const firstCallback = h.calls.mounts[0].onBadge;
    firstCallback(2);
    h.navigate({ connectWidget: { ...config, identity: 'employee-b' } });
    assert.equal(first.removed, true);
    const second = h.calls.badges[1];
    firstCallback(8);
    assert.equal(first.textContent, '2');
    assert.equal(second.hidden, true);
    const secondCallback = h.calls.mounts[1].onBadge;
    secondCallback(3);
    h.navigate({ connectWidget: null });
    assert.equal(second.removed, true);
    secondCallback(9);
    assert.equal(second.textContent, '3');
    h.navigate({ connectWidget: config });
    const third = h.calls.badges[2];
    const thirdCallback = h.calls.mounts[2].onBadge;
    dispose();
    assert.equal(third.removed, true);
    thirdCallback(7);
    assert.equal(third.hidden, true);
});

test('failed mounts leave no badge and invalid counts cannot alter the indicator', () => {
    const failed = harness();
    failed.window.LGUConnectWidget.mount = () => { throw new Error('failed mount'); };
    assert.doesNotThrow(() => failed.start({ connectWidget: config }));
    assert.equal(failed.calls.badges.length, 0);
    const h = harness();
    h.start({ connectWidget: config });
    const badge = h.calls.badges[0];
    const { onBadge } = h.calls.mounts[0];
    onBadge(5);
    for (const count of [-1, NaN, Infinity, 1.5, '6']) onBadge(count);
    assert.equal(badge.textContent, '5');
});
