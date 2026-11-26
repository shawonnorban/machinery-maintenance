"use client";

import { chatApi } from "./chat-lib";

/**
 * Desktop notifications for chat (Web Push): this browser subscribes with the
 * server's VAPID key through public/chat-push-sw.js, and the server pushes a
 * new message to it even while every tab is closed. One subscription per
 * browser, moved to whoever signs in on it; dropped again on sign-out.
 */
const SCRIPT = "/chat-push-sw.js";
const SCOPE = "/chat-push/";

export const pushSupported = () =>
  typeof window !== "undefined" && window.isSecureContext && "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;

/** "granted" | "denied" | "default" | "unsupported" */
export const pushPermission = () => (pushSupported() ? Notification.permission : "unsupported");

function keyBytes(base64) {
  const pad = "=".repeat((4 - (base64.length % 4)) % 4);
  const raw = atob((base64 + pad).replace(/-/g, "+").replace(/_/g, "/"));
  return Uint8Array.from(raw, (c) => c.charCodeAt(0));
}

const sameKey = (buffer, bytes) => {
  if (!buffer) return false;
  const a = new Uint8Array(buffer);
  return a.length === bytes.length && a.every((v, i) => v === bytes[i]);
};

async function registration() {
  const reg = (await navigator.serviceWorker.getRegistration(SCOPE)) ?? (await navigator.serviceWorker.register(SCRIPT, { scope: SCOPE }));
  if (reg.active) return reg;
  const worker = reg.installing ?? reg.waiting;
  await new Promise((resolve) => {
    if (!worker) return resolve();
    worker.addEventListener("statechange", () => worker.state === "activated" && resolve());
    setTimeout(resolve, 8000);
  });
  return reg;
}

let queue = Promise.resolve();

/** Subscribe this browser (asking permission first when `ask`) and tell the server it is mine now. One at a time. */
export function pushOn(options = {}) {
  const run = queue.then(() => subscribe(options));
  queue = run.catch(() => {});
  return run;
}

async function subscribe({ ask = true } = {}) {
  if (!pushSupported()) return "unsupported";
  let permission = Notification.permission;
  if (permission === "default" && ask) permission = await Notification.requestPermission();
  if (permission !== "granted") return permission;
  const { public_key: key } = (await chatApi("push/key")) ?? {};
  if (!key) return "unsupported";
  const bytes = keyBytes(key);
  const reg = await registration();
  let sub = await reg.pushManager.getSubscription();
  if (sub && !sameKey(sub.options?.applicationServerKey, bytes)) {
    await sub.unsubscribe().catch(() => {});
    sub = null;
  }
  sub ??= await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: bytes });
  await chatApi("push/subscribe", { method: "POST", body: sub.toJSON() });
  return "granted";
}

/** Stop notifications on this browser (turned off, or signing out). Never throws, never waits long. */
export async function pushOff() {
  if (!pushSupported()) return;
  const work = (async () => {
    const reg = await navigator.serviceWorker.getRegistration(SCOPE);
    const sub = await reg?.pushManager.getSubscription();
    if (!sub) return;
    await chatApi("push/unsubscribe", { method: "POST", body: { endpoint: sub.endpoint } }).catch(() => {});
    await sub.unsubscribe().catch(() => {});
  })().catch(() => {});
  await Promise.race([work, new Promise((r) => setTimeout(r, 2500))]);
}

/** Whether this browser holds a subscription now. */
export async function pushActive() {
  if (!pushSupported() || Notification.permission !== "granted") return false;
  const reg = await navigator.serviceWorker.getRegistration(SCOPE);
  return Boolean(await reg?.pushManager.getSubscription());
}

/** A click on a notification asks the page to open a chat; the page says when it is ready to be told. */
export function listenForChatOpen(onOpen) {
  if (!pushSupported()) return () => {};
  const handler = (e) => {
    if (e.data?.type === "chat-open" && e.data.chat_id) onOpen(e.data.chat_id);
  };
  navigator.serviceWorker.addEventListener("message", handler);
  navigator.serviceWorker.startMessages?.();
  navigator.serviceWorker.getRegistration(SCOPE).then((reg) => reg?.active?.postMessage({ type: "chat-ready" })).catch(() => {});
  return () => navigator.serviceWorker.removeEventListener("message", handler);
}
