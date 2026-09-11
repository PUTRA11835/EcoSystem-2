/* EcoSystem Service Worker — Web Push Notifications */
/* Version: 1.4 */

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(clients.claim());
});

/* ---- Receive push from server ---- */
self.addEventListener('push', function (event) {
    console.log('[SW v1.2] push event received');

    var data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) {
        console.error('[SW] Failed to parse push data', e);
    }
    console.log('[SW] push data:', data);

    var title   = data.title || 'EcoSystem';
    var options = {
        body:             data.body || '',
        icon:             '/images/logo_nobg.png',
        badge:            '/images/logo_nobg.png',
        tag:              data.tag  || 'ecosystem-notif',
        data:             { url: data.url || '/', type: data.type || '' },
        requireInteraction: false,
        silent:           false,
    };

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            var hasVisibleClient = clientList.some(function (c) {
                return c.visibilityState === 'visible';
            });
            console.log('[SW] clients:', clientList.length, '| hasVisibleClient:', hasVisibleClient);

            /* Message open clients so they can play custom sound */
            clientList.forEach(function (c) {
                c.postMessage({ type: 'PUSH_RECEIVED', payload: data });
            });

            /* If a tab is open, postMessage handles the custom sound — mute OS sound.
               If no tab is open, let OS play its sound as the only audio cue. */
            options.silent = clientList.length > 0;
            console.log('[SW] calling showNotification');
            return self.registration.showNotification(title, options)
                .then(function () { console.log('[SW] showNotification OK'); })
                .catch(function (e) { console.error('[SW] showNotification FAILED:', e); });
        })
    );
});

/* ---- Notification click → focus or open the URL ---- */
self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var c = clientList[i];
                if (c.url.indexOf(url) !== -1 && 'focus' in c) {
                    return c.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
