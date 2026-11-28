/**
 * Chat's live line on the phone (2026-11-27): the same Reverb socket and the
 * same private `user.{id}` channel the web's topbar holds, spoken in the
 * Pusher protocol over React Native's own WebSocket — no extra library, so
 * no new build. The server tells the app where the socket is (`/health` →
 * `mobile.reverb`: the public key, host, port, TLS); the channel is
 * authorised through `/broadcasting/auth` with the person's own token. Each
 * `chat.event` (message, update, read, delivered, typing, pins, chat) goes
 * to the listeners; when the socket cannot be had the state says so, and the
 * chat falls back to asking every few seconds.
 */
import { AppState } from "react-native";
import { request } from "./api";
import { currentServer } from "./server";

let ws = null;
let state = "initial";
let config;
let me = null;
let authorise = null;
let retry = 0;
let timer = null;
let wanted = 0;
const listeners = new Set();
const stateListeners = new Set();

const setState = (next) => {
  state = next;
  stateListeners.forEach((l) => l(next));
};

export const socketState = () => state;
export function onSocketState(fn) {
  stateListeners.add(fn);
  return () => stateListeners.delete(fn);
}
export function onChatEvent(fn) {
  listeners.add(fn);
  return () => listeners.delete(fn);
}

/** Where the socket is, once per server (null: this server runs none). */
async function where() {
  if (config !== undefined) return config;
  try {
    const h = await request("/health");
    config = h?.mobile?.reverb ?? null;
  } catch {
    return null;
  }
  return config;
}

function schedule() {
  clearTimeout(timer);
  if (!wanted) return;
  const wait = Math.min(30000, 2000 * 2 ** retry);
  retry += 1;
  timer = setTimeout(open, wait);
}

async function open() {
  if (!wanted || !me || ws) return;
  const c = await where();
  const server = currentServer();
  if (!c?.key || !server) {
    setState("unavailable");
    return;
  }
  setState("connecting");
  const host = c.host || server.host;
  const url = `${c.tls ? "wss" : "ws"}://${host}${c.port ? `:${c.port}` : ""}/app/${c.key}?protocol=7&client=js&version=8.4.0&flash=false`;
  let socket;
  try {
    socket = new WebSocket(url);
  } catch {
    setState("failed");
    schedule();
    return;
  }
  ws = socket;
  socket.onmessage = async (e) => {
    let msg;
    try {
      msg = JSON.parse(e.data);
    } catch {
      return;
    }
    const data = typeof msg.data === "string" ? safeJson(msg.data) : msg.data;
    if (msg.event === "pusher:connection_established") {
      try {
        const channel = `private-user.${me}`;
        const a = await authorise({ socket_id: data.socket_id, channel_name: channel });
        socket.send(JSON.stringify({ event: "pusher:subscribe", data: { auth: a.auth, channel } }));
      } catch {
        setState("failed");
        socket.close();
      }
    } else if (msg.event === "pusher_internal:subscription_succeeded") {
      retry = 0;
      setState("connected");
    } else if (msg.event === "pusher:ping") {
      socket.send(JSON.stringify({ event: "pusher:pong", data: {} }));
    } else if (msg.event === "chat.event" && data) {
      listeners.forEach((l) => l(data));
    }
  };
  socket.onclose = () => {
    if (ws === socket) ws = null;
    if (wanted) {
      setState("failed");
      schedule();
    }
  };
  socket.onerror = () => {};
}

function safeJson(s) {
  try {
    return JSON.parse(s);
  } catch {
    return null;
  }
}

/**
 * Holds the socket while a chat screen shows (counted: the list and a
 * conversation together hold it once). `auth` posts to /broadcasting/auth
 * with the person's token.
 */
export function holdSocket(userId, auth) {
  me = userId;
  authorise = auth;
  wanted += 1;
  if (!ws) {
    retry = 0;
    open();
  }
  const sub = AppState.addEventListener("change", (s) => {
    if (s === "active" && wanted && !ws) {
      retry = 0;
      open();
    }
  });

  return () => {
    sub.remove();
    wanted = Math.max(0, wanted - 1);
    if (!wanted) {
      clearTimeout(timer);
      ws?.close();
      ws = null;
      setState("initial");
    }
  };
}

/** The server changed or a new sign-in: ask /health again. */
export function forgetSocketConfig() {
  config = undefined;
}
