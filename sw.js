/* Care Connect SL — Service Worker for notifications */
const CACHE = 'cc-notify-v1';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

// Show notification from page (via postMessage) or push payload
self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (data.type === 'SHOW_NOTIFICATION') {
    const title = data.title || 'Care Connect SL';
    const options = {
      body: data.body || '',
      icon: data.icon || '/images/icon-192.png',
      badge: '/images/icon-192.png',
      tag: data.tag || 'care-connect',
      renotify: !!data.renotify,
      data: { url: data.url || '/dashboard/messages.php' },
      vibrate: [120, 60, 120],
      requireInteraction: !!data.requireInteraction
    };
    event.waitUntil(self.registration.showNotification(title, options));
  }
});

self.addEventListener('push', (event) => {
  let payload = { title: 'Care Connect SL', body: 'You have a new update', url: '/dashboard/messages.php' };
  try {
    if (event.data) payload = Object.assign(payload, event.data.json());
  } catch (e) {
    try {
      payload.body = event.data.text();
    } catch (e2) {}
  }
  event.waitUntil(
    self.registration.showNotification(payload.title || 'Care Connect SL', {
      body: payload.body || '',
      icon: '/images/icon-192.png',
      badge: '/images/icon-192.png',
      tag: payload.tag || 'care-connect-push',
      data: { url: payload.url || '/dashboard/messages.php' },
      vibrate: [120, 60, 120]
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || '/dashboard/messages.php';
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if ('focus' in client) {
          client.navigate(url);
          return client.focus();
        }
      }
      if (self.clients.openWindow) return self.clients.openWindow(url);
    })
  );
});
