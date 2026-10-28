/* Simplify web app service worker (version __VERSION__).
   Pages always come from the network; nothing is cached except one small offline page, shown when a page
   cannot be loaded at all. Navigation preload keeps the worker from slowing page loads down.
   App notifications (announcements, updates) arrive as push messages; a tap opens the panel on that page. */
const OFFLINE = 'simplify-offline-__VERSION__';
const ICON = '__ICON__';
const BADGE = '__BADGE__';
const OFFLINE_URL = new URL('./?simplify-offline=__VERSION__', self.registration.scope).href;
const PAGE = `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>__NAME__</title><style>
:root { color-scheme: light dark; font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
body { display: grid; place-items: center; min-height: 100dvh; margin: 0; padding: 24px; box-sizing: border-box; background: #f4f4f5; color: #26262e; text-align: center; }
@media (prefers-color-scheme: dark) { body { background: #101012; color: #ececf1; } p { color: #a1a1aa !important; } }
main { display: grid; gap: 12px; max-width: 340px; }
h1 { margin: 0; font-size: 20px; }
p { margin: 0; color: #666672; line-height: 1.5; }
button { justify-self: center; min-height: 44px; margin-top: 8px; padding: 0 22px; border: 0; border-radius: 10px; background: #26262e; color: #fafafa; font-family: inherit; font-size: 15px; font-weight: 600; }
</style></head><body><main><h1>No connection</h1><p>__NAME__ cannot be reached right now. Check your internet connection and try again.</p>
<button type="button" onclick="location.reload()">Try again</button></main>
<script>addEventListener('online', () => location.reload());</script></body></html>`;

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(OFFLINE).then((cache) => cache.put(OFFLINE_URL, new Response(PAGE, { headers: { 'Content-Type': 'text/html; charset=utf-8' } }))).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    for (const key of await caches.keys()) if (key.startsWith('simplify-offline-') && key !== OFFLINE) await caches.delete(key);
    if (self.registration.navigationPreload) await self.registration.navigationPreload.enable();
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  if (event.request.mode !== 'navigate' || event.request.method !== 'GET') return;
  event.respondWith((async () => {
    try {
      return (await event.preloadResponse) || (await fetch(event.request));
    } catch {
      return (await caches.match(OFFLINE_URL)) || Response.error();
    }
  })());
});

self.addEventListener('push', (event) => {
  let message = {};
  try {
    message = event.data ? event.data.json() : {};
  } catch {
    message = { body: event.data ? event.data.text() : '' };
  }
  const scope = self.registration.scope;
  // Links and icons from the message only when they point at this panel.
  const local = (value, fallback) => {
    try {
      const target = new URL(value || fallback, scope);
      return target.origin === new URL(scope).origin ? target.href : fallback;
    } catch {
      return fallback;
    }
  };
  const url = local(message.url, scope);
  event.waitUntil(self.registration.showNotification(String(message.title || '__NAME__'), {
    body: String(message.body || ''),
    icon: local(message.icon, ICON),
    badge: local(message.badge, BADGE),
    tag: message.tag ? String(message.tag) : undefined,
    data: { url },
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || self.registration.scope;
  event.waitUntil((async () => {
    for (const client of await self.clients.matchAll({ type: 'window', includeUncontrolled: true })) {
      if (!client.url.startsWith(self.registration.scope) || !('focus' in client)) continue;
      await client.focus();
      if ('navigate' in client) await client.navigate(url).catch(() => {});
      return;
    }
    await self.clients.openWindow(url);
  })());
});
