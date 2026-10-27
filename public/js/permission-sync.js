/**
 * Keeps the three permission screens in step: Roles (list), a role's Menu Access, and the Menu List with its "Access" drawer.
 *
 * They all write the same table (role_menu) through the same service, so the server is always right — what went stale was what each open
 * screen was showing. Two ways it is brought back up to date:
 *
 *   1. PUSH   A screen that saved tells every other open screen (other tabs and windows of this browser) what changed:
 *             PermissionSync.publish({ roleIds: [...], menuIds: [...], all: false })
 *   2. PULL   A screen that was in the background, or restored from the back button, asks the server again when it comes back:
 *             PermissionSync.onStale(() => reload())
 *
 * Each screen decides how to refresh itself and never throws away what the person has not saved yet.
 */
(function () {
    const NAME = 'ecosystem-permissions';
    const me = Math.random().toString(36).slice(2);
    const subscribers = [];
    const seen = new Set();
    let channel = null;

    try { channel = new BroadcastChannel(NAME); } catch (e) { /* older browser: the storage event below carries it */ }

    function deliver(message) {
        if (!message || message.from === me) return;
        const key = message.from + ':' + message.at;
        if (seen.has(key)) return;               // the channel and the storage event can both carry the same message
        seen.add(key);
        if (seen.size > 50) seen.delete(seen.values().next().value);
        subscribers.forEach((fn) => { try { fn(message); } catch (e) { console.error('PermissionSync subscriber failed', e); } });
    }

    if (channel) channel.onmessage = (event) => deliver(event.data);
    window.addEventListener('storage', (event) => {
        if (event.key !== NAME || !event.newValue) return;
        try { deliver(JSON.parse(event.newValue)); } catch (e) { /* not ours */ }
    });

    window.PermissionSync = {
        /** @param {{roleIds?: number[], menuIds?: number[], all?: boolean}} change */
        publish(change) {
            const message = Object.assign({ roleIds: [], menuIds: [], all: false }, change, { from: me, at: Date.now() });
            if (channel) channel.postMessage(message);
            try { localStorage.setItem(NAME, JSON.stringify(message)); } catch (e) { /* private mode */ }
        },

        /** @param {(message: object) => void} fn called when ANOTHER screen saved a change */
        subscribe(fn) { subscribers.push(fn); },

        /**
         * Calls fn when this page comes back to the foreground or is restored from the back/forward cache — at most every
         * `minSeconds` seconds — so a screen that sat in a background tab does not keep showing yesterday's permissions.
         */
        onStale(fn, minSeconds) {
            let last = Date.now();
            const wait = (minSeconds || 15) * 1000;
            const run = (force) => { if (force || Date.now() - last >= wait) { last = Date.now(); fn(); } };

            document.addEventListener('visibilitychange', () => { if (!document.hidden) run(false); });
            window.addEventListener('focus', () => run(false));
            window.addEventListener('pageshow', (event) => { if (event.persisted) run(true); });
        },
    };
})();
