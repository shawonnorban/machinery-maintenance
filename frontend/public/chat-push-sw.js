/**
 * Chat notifications while the browser is closed (Web Push, user 2026-11-23).
 *
 * Its own small worker, registered under the unused scope /chat-push/ so it
 * controls no page and caches nothing — unlike /sw.js it is safe in `next
 * dev` too (that one is production-only for its asset cache). It only:
 *  - shows a pushed chat message as a notification (one per chat: `tag`),
 *    unless an app tab is in front — its own toast and list show it there;
 *  - on a click, brings the chat tab forward (or opens /chat) and tells the
 *    page which chat to open — by message, never in the address (no ids in
 *    URLs).
 */
let pending = null;
let release = null;

self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));

self.addEventListener("push", (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch {
    data = { title: "AnnotechERP", body: event.data ? event.data.text() : "" };
  }
  event.waitUntil(
    (async () => {
      const windows = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
      if (windows.some((w) => w.focused && w.visibilityState === "visible")) return;
      await self.registration.showNotification(data.title || "AnnotechERP", {
        body: data.body || "",
        icon: data.icon || "/chat-icon-192.png",
        badge: data.badge || "/chat-badge-96.png",
        tag: data.tag || undefined,
        renotify: Boolean(data.tag),
        data: { chat_id: data.chat_id || null, url: data.url || "/chat" },
      });
    })(),
  );
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const { chat_id: chatId = null, url = "/chat" } = event.notification.data || {};
  event.waitUntil(
    (async () => {
      const windows = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
      const chatTab = windows.find((w) => new URL(w.url).pathname.startsWith("/chat"));
      if (chatTab) {
        await chatTab.focus();
        chatTab.postMessage({ type: "chat-open", chat_id: chatId });
        return;
      }
      // A fresh /chat tab asks for its chat once it is up ("chat-ready").
      pending = chatId;
      await self.clients.openWindow(url);
      await new Promise((resolve) => {
        release = resolve;
        setTimeout(resolve, 20000);
      });
      pending = null;
    })(),
  );
});

self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "chat-ready" && event.source) {
    if (pending) event.source.postMessage({ type: "chat-open", chat_id: pending });
    pending = null;
    if (release) release();
    release = null;
  }
});
