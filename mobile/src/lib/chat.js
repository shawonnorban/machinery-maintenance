/**
 * Chat on the phone — the web's chat, all of it (user, 2026-11-27): one
 * store for the chat screens (the list, a conversation, its info and its
 * search share it), fed by the web's own API (/chat/...) and kept live by
 * the web's own socket (lib/chat-socket.js) — new messages, edits,
 * reactions, read and delivered ticks, typing, pins and the chats
 * themselves — or, without a socket, by asking every few seconds. Every
 * doing the web has is here: send (text, photo, file, reply, mentions),
 * edit, delete for me / everyone, react, pin, forward, message info, copy,
 * download; chats pinned, muted, archived, cleared, left; direct chats and
 * groups (name, description, photo, members, admins); search of messages
 * and of the `#` records.
 */
import { useCallback, useEffect, useRef, useState, useSyncExternalStore } from "react";
import { useFocusEffect } from "expo-router";
import { File } from "expo-file-system";
import { useSession } from "./session";
import { holdSocket, onChatEvent, onSocketState, socketState } from "./chat-socket";

/* ── Helpers the web shares (chat-lib.js) ─────────────────────────── */

/** WhatsApp's default avatar colours, one per person (the server picks the index). */
export const TONES = ["#e57373", "#f06292", "#ba68c8", "#9575cd", "#7986cb", "#5c9ce6", "#26a69a", "#4db6ac", "#66bb6a", "#f2a33a", "#a1887f", "#78909c"];
/** A sender's name colour in a group bubble. */
export const NAME_TONES = ["#d32f2f", "#c2185b", "#7b1fa2", "#512da8", "#303f9f", "#1976d2", "#00796b", "#00897b", "#388e3c", "#e65100", "#6d4c41", "#455a64"];
export const QUICK_REACTIONS = ["👍", "❤️", "😂", "😮", "😢", "🙏"];
export const MAX_FILE = 25 * 1024 * 1024;

const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
const WEEKDAYS = { en: ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"], bn: ["রবিবার", "সোমবার", "মঙ্গলবার", "বুধবার", "বৃহস্পতিবার", "শুক্রবার", "শনিবার"] };
const MONTHS = { en: ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"], bn: ["জানুয়ারি", "ফেব্রুয়ারি", "মার্চ", "এপ্রিল", "মে", "জুন", "জুলাই", "আগস্ট", "সেপ্টেম্বর", "অক্টোবর", "নভেম্বর", "ডিসেম্বর"] };
const L = (locale) => (locale === "bn" ? "bn" : "en");

/** A message's clock, "3:45 PM". */
export function clock(iso) {
  if (!iso) return "";
  const d = new Date(iso);
  const h = d.getHours();
  return `${h % 12 || 12}:${String(d.getMinutes()).padStart(2, "0")} ${h < 12 ? "AM" : "PM"}`;
}

/** A chat row's time: today's clock, "Yesterday", a weekday this week, else the date. */
export function listTime(iso, t, locale) {
  if (!iso) return "";
  const d = new Date(iso);
  const now = new Date();
  if (sameDay(d, now)) return clock(iso);
  const y = new Date(now);
  y.setDate(now.getDate() - 1);
  if (sameDay(d, y)) return t("yesterday");
  if (now - d < 6 * 86400000) return WEEKDAYS[L(locale)][d.getDay()];
  return `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}/${d.getFullYear()}`;
}

/** The day pill between messages: Today, Yesterday, a weekday, or the date. */
export function dayLabel(iso, t, locale) {
  const d = new Date(iso);
  const now = new Date();
  if (sameDay(d, now)) return t("today");
  const y = new Date(now);
  y.setDate(now.getDate() - 1);
  if (sameDay(d, y)) return t("yesterday");
  if (now - d < 6 * 86400000) return WEEKDAYS[L(locale)][d.getDay()];
  return `${d.getDate()} ${MONTHS[L(locale)][d.getMonth()]} ${d.getFullYear()}`;
}

export const dayKey = (iso) => {
  const d = new Date(iso);
  return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
};

export function shortDate(iso, locale) {
  const d = new Date(iso);
  return `${d.getDate()} ${MONTHS[L(locale)][d.getMonth()].slice(0, 3)} ${d.getFullYear()}`;
}

export function lastSeen(person, t, locale) {
  if (!person) return "";
  if (person.online) return t("online");
  if (!person.last_seen_at) return "";
  const d = new Date(person.last_seen_at);
  const now = new Date();
  if (sameDay(d, now)) return t("last_seen_today", { time: clock(person.last_seen_at) });
  const y = new Date(now);
  y.setDate(now.getDate() - 1);
  if (sameDay(d, y)) return t("last_seen_yesterday", { time: clock(person.last_seen_at) });
  return t("last_seen", { when: shortDate(person.last_seen_at, locale) });
}

export function fileSize(bytes) {
  if (!bytes && bytes !== 0) return "";
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function fileKind(name = "") {
  const ext = name.split(".").pop()?.toLowerCase() ?? "";
  if (ext === "pdf") return { label: "PDF", color: "#e53935" };
  if (["xls", "xlsx", "csv"].includes(ext)) return { label: ext.toUpperCase(), color: "#1e8e3e" };
  if (["doc", "docx"].includes(ext)) return { label: ext.toUpperCase(), color: "#1a73e8" };
  if (["ppt", "pptx"].includes(ext)) return { label: ext.toUpperCase(), color: "#e8710a" };
  if (["zip"].includes(ext)) return { label: "ZIP", color: "#795548" };
  if (["mp4", "mp3", "m4a", "ogg", "webm"].includes(ext)) return { label: ext.toUpperCase(), color: "#8e24aa" };
  return { label: (ext || "FILE").toUpperCase().slice(0, 4), color: "#607d8b" };
}

/** A group's system line ("Karim added Rahim"). */
export function systemText(m, people, meId, t) {
  const name = (id) => (id === meId ? t("you") : (people[id]?.name ?? m.user_name ?? "—"));
  const list = (ids = []) => ids.map((id) => (id === meId ? t("you_lower") : (people[id]?.name ?? "—"))).join(", ");
  const meta = m.meta ?? {};
  return t(`system.${meta.event}`, { user: name(m.user_id), users: list(meta.users), name: meta.name ?? "", to: meta.to ?? "" });
}

/** A chat row's one-line preview of its last message. */
export function previewText(last, chat, people, meId, t) {
  if (!last) return "";
  if (last.type === "SYSTEM") return systemText(last, people, meId, t);
  if (last.deleted) return last.user_id === meId ? t("deleted_mine") : t("deleted");
  const who = chat.is_group && last.user_id ? `${last.user_id === meId ? t("you") : (people[last.user_id]?.name ?? last.user_name ?? "").split(" ")[0]}: ` : "";
  const body = last.body || (last.type === "IMAGE" ? `📷 ${t("photo_msg")}` : last.file_name ? `📄 ${last.file_name}` : "");
  return `${who}${body}`;
}

export const sortChats = (list) => [...list].sort((a, b) => (b.pinned ? 1 : 0) - (a.pinned ? 1 : 0) || String(b.sort_at ?? "").localeCompare(String(a.sort_at ?? "")));

/* ── The store ────────────────────────────────────────────────────── */

let S = { me: null, chats: null, contacts: null, threads: {}, details: {}, typing: {}, socket: socketState(), error: null, activeId: null, jump: null };
const subs = new Set();
const set = (patch) => {
  S = { ...S, ...(typeof patch === "function" ? patch(S) : patch) };
  subs.forEach((f) => f());
};
const subscribe = (f) => {
  subs.add(f);
  return () => subs.delete(f);
};

/** The chat store, read by a screen (re-drawn on each change). */
export function useChatStore() {
  return useSyncExternalStore(subscribe, () => S);
}

let api = null; // the session's `call`, set by the screens that use the chat
const go = (path, options) => api(`/chat/${path}`, options);

const setThread = (id, fn) => set((s) => ({ threads: { ...s.threads, [id]: fn(s.threads[id] ?? { messages: [], loaded: false }) } }));
const merge = (list, incoming) => {
  const byId = new Map(list.filter((m) => !String(m.id).startsWith("tmp-")).map((m) => [m.id, m]));
  incoming.forEach((m) => byId.set(m.id, m));
  const temps = list.filter((m) => String(m.id).startsWith("tmp-"));
  return [...[...byId.values()].sort((a, b) => (a.id < b.id ? -1 : 1)), ...temps];
};
const upsert = (row) => row && set((s) => ({ chats: sortChats([row, ...(s.chats ?? []).filter((c) => c.id !== row.id)]) }));
const refreshTimers = {};
const refreshRow = (id, delay = 0) => {
  clearTimeout(refreshTimers[id]);
  refreshTimers[id] = setTimeout(() => go(`chats/${id}/row`).then(upsert, () => {}), delay);
};

/** Everyone the chat knows, for names, colours and photos. */
export function peopleOf(s) {
  const out = s.me ? { [s.me.id]: s.me } : {};
  (s.contacts ?? []).forEach((p) => (out[p.id] = p));
  (s.chats ?? []).forEach((c) => c.other && (out[c.other.id] ??= c.other));
  Object.values(s.details).forEach((d) => (d.members ?? []).forEach((p) => (out[p.id] = { ...out[p.id], ...p })));
  return out;
}

export async function loadChats() {
  try {
    const d = await go("chats");
    set({ me: d.me, chats: sortChats(d.chats ?? []), error: null });
    return d;
  } catch (e) {
    set({ error: e.message });
    return null;
  }
}

export async function loadContacts() {
  try {
    set({ contacts: await go("contacts") });
  } catch {
    if (S.contacts === null) set({ contacts: [] });
  }
}

export async function loadDetail(id) {
  try {
    const d = await go(`chats/${id}`);
    set((s) => ({ details: { ...s.details, [id]: d } }));
  } catch {
    /* not mine any more */
  }
}

export async function loadMessages(id, params = "") {
  setThread(id, (th) => ({ ...th, loading: true }));
  try {
    const d = await go(`chats/${id}/messages${params}`);
    setThread(id, (th) => {
      if (params.startsWith("?before")) return { ...th, messages: merge(th.messages, d.messages), has_older: d.has_older, loading: false };
      if (params.startsWith("?after")) return { ...th, messages: merge(th.messages, d.messages), has_newer: d.has_newer, loading: false };
      if (params.startsWith("?around")) return { ...th, messages: d.messages, has_older: d.has_older, has_newer: d.has_newer, loading: false, loaded: true };
      // The "N unread messages" line marks what was unread when the chat opened.
      const unreadFrom = th.loaded ? th.unread_from : (d.messages.find((m) => m.user_id !== S.me?.id && m.type !== "SYSTEM" && (d.read_upto === null || m.id > d.read_upto))?.id ?? null);
      return { messages: d.messages, has_older: d.has_older, has_newer: false, loading: false, loaded: true, unread_from: unreadFrom };
    });
    if (!params) set((s) => ({ chats: (s.chats ?? []).map((c) => (c.id === id ? { ...c, unread: 0, mentioned: false } : c)) }));
    return d;
  } catch (e) {
    setThread(id, (th) => ({ ...th, loading: false, loaded: true, error: e.message }));
    return null;
  }
}

let readTimer = null;
export function markRead(id) {
  clearTimeout(readTimer);
  readTimer = setTimeout(() => {
    go(`chats/${id}/read`, { method: "POST", body: {} }).catch(() => {});
    set((s) => ({ chats: (s.chats ?? []).map((c) => (c.id === id ? { ...c, unread: 0, mentioned: false } : c)) }));
  }, 400);
}

/** A chat opened: its messages (to the unread line), its details, read. */
export function openChat(id) {
  set((s) => ({ activeId: id, jump: null, threads: { ...s.threads, [id]: undefined } }));
  loadMessages(id).then(() => markRead(id));
  loadDetail(id);
}

export const closeChat = (id) => S.activeId === id && set({ activeId: null });

/** Go to a message (a pinned one, a quote, a search hit): load around it if needed, then mark it. */
export async function jumpTo(id, messageId) {
  if (messageId === null) {
    await loadMessages(id);
    return;
  }
  if (!S.threads[id]?.messages?.some((m) => m.id === messageId)) await loadMessages(id, `?around=${encodeURIComponent(messageId)}`);
  set({ jump: { id: messageId, at: Date.now() } });
}

export function loadOlder(id) {
  const first = S.threads[id]?.messages?.find((m) => !String(m.id).startsWith("tmp-"));
  if (first && S.threads[id]?.has_older && !S.threads[id]?.loading) loadMessages(id, `?before=${encodeURIComponent(first.id)}`);
}

export function loadNewer(id) {
  const real = (S.threads[id]?.messages ?? []).filter((m) => !String(m.id).startsWith("tmp-"));
  if (real.length && !S.threads[id]?.loading) loadMessages(id, `?after=${encodeURIComponent(real[real.length - 1].id)}`);
}

let lastTyping = 0;
export function sendTyping(id) {
  const now = Date.now();
  if (now - lastTyping < 3000) return;
  lastTyping = now;
  go(`chats/${id}/typing`, { method: "POST", body: {} }).catch(() => {});
}

/**
 * Send: text, or a file / photo (`file`: { uri, name, mime, size }), with a
 * reply and mentions. Shown at once (a clock), the real message replaces it.
 */
export async function send(id, { body, file, reply, mentions = null }) {
  const tmp = `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
  const image = Boolean(file && (file.mime ?? "").startsWith("image/"));
  const me = S.me;
  const draft = {
    id: tmp, chat_id: id, user_id: me.id, user_name: me.name, type: file ? (image ? "IMAGE" : "FILE") : "TEXT", body: body || null,
    file: file ? { name: file.name, size: file.size, mime: file.mime, image } : null, local_url: image ? file.uri : null,
    reply: reply ? { id: reply.id, user_id: reply.user_id, type: reply.type, body: reply.body, file_name: reply.file?.name, image: reply.file?.image } : null,
    reactions: [], status: "pending", created_at: new Date().toISOString(), meta: mentions ? { mentions } : null,
  };
  setThread(id, (x) => ({ ...x, messages: [...x.messages, draft] }));
  try {
    let m;
    if (file) {
      const form = new FormData();
      // Expo's fetch takes the file itself (expo-file-system's File), not RN's { uri } object.
      form.append("file", new File(file.uri), file.name);
      if (body) form.append("body", body);
      if (reply) form.append("reply_to_id", reply.id);
      if (mentions) form.append("mentions", JSON.stringify(mentions));
      m = await go(`chats/${id}/messages`, { method: "POST", body: form, deadline: 120000 });
    } else {
      m = await go(`chats/${id}/messages`, { method: "POST", body: { body, reply_to_id: reply?.id ?? null, mentions } });
    }
    setThread(id, (x) => ({ ...x, messages: merge(x.messages.filter((y) => y.id !== tmp), [m]) }));
    set((s) => ({ chats: sortChats((s.chats ?? []).map((c) => (c.id === id ? { ...c, last: { ...m, file_name: m.file?.name }, sort_at: m.created_at, archived: false } : c))) }));
    return m;
  } catch (e) {
    setThread(id, (x) => ({ ...x, messages: x.messages.map((y) => (y.id === tmp ? { ...y, status: "failed" } : y)) }));
    throw e;
  }
}

const replaceMessage = (m) => setThread(m.chat_id, (x) => ({ ...x, messages: x.messages.map((y) => (y.id === m.id ? m : y)) }));

export const editMessage = async (m, body, mentions = null) => replaceMessage(await go(`messages/${m.id}`, { method: "PATCH", body: { body, mentions } }));
export const reactTo = async (m, emoji) => replaceMessage(await go(`messages/${m.id}/react`, { method: "POST", body: { emoji } }));
export async function pinMessage(m) {
  await go(`messages/${m.id}/pin`, { method: "POST", body: {} });
  loadDetail(m.chat_id);
}
export const messageInfo = (m) => go(`messages/${m.id}/info`);
export const forwardMessage = (m, chatIds) => go("messages/forward", { method: "POST", body: { message_id: m.id, chat_ids: chatIds } });

export async function deleteMessage(m, scope) {
  await go(`messages/${m.id}?scope=${scope}`, { method: "DELETE" });
  if (scope === "me") setThread(m.chat_id, (x) => ({ ...x, messages: x.messages.filter((y) => y.id !== m.id) }));
  else replaceMessage({ ...m, deleted: true, body: null, file: null, reactions: [] });
}

/** A chat's own settings: pinned, muted, archived. */
export const chatSetting = async (chat, key) => upsert(await go(`chats/${chat.id}/settings`, { method: "PATCH", body: { [key]: !chat[key] } }));

export async function clearChat(chat) {
  upsert(await go(`chats/${chat.id}/clear`, { method: "POST", body: {} }));
  if (S.activeId === chat.id) loadMessages(chat.id);
}

export async function exitGroup(chat) {
  await go(`chats/${chat.id}/members/${S.me.id}`, { method: "DELETE" });
  refreshRow(chat.id);
  loadDetail(chat.id);
  loadMessages(chat.id);
}

export async function startDirect(person) {
  const row = await go("chats/direct", { method: "POST", body: { user_id: person.id } });
  upsert(row);
  return row;
}

export async function createGroup(name, userIds) {
  const row = await go("chats/group", { method: "POST", body: { name, user_ids: userIds } });
  upsert(row);
  return row;
}

/** A group's doings (rename, describe, photo, members, roles): the row and the details read again. */
async function groupCall(id, fn) {
  const row = await fn();
  if (row?.id) upsert(row);
  loadDetail(id);
}
export const renameGroup = (id, name) => groupCall(id, () => go(`chats/${id}`, { method: "PATCH", body: { name } }));
export const describeGroup = (id, description) => groupCall(id, () => go(`chats/${id}`, { method: "PATCH", body: { description } }));
export const addMembers = (id, userIds) => groupCall(id, () => go(`chats/${id}/members`, { method: "POST", body: { user_ids: userIds } }));
export const setRole = (id, userId, role) => groupCall(id, () => go(`chats/${id}/members/${userId}`, { method: "PATCH", body: { role } }));
export const removeMember = (id, userId) => groupCall(id, () => go(`chats/${id}/members/${userId}`, { method: "DELETE" }));
export function groupPhoto(id, file) {
  const form = new FormData();
  form.append("file", new File(file.uri), file.name);
  return groupCall(id, async () => {
    const row = await go(`chats/${id}/image`, { method: "POST", body: form, deadline: 120000 });
    return row ? { ...row, image_v: Date.now() } : row;
  });
}

export const searchMessages = (q, chatId = null) => go(`search?q=${encodeURIComponent(q)}${chatId ? `&chat=${encodeURIComponent(chatId)}` : ""}`);
export const searchRecords = (type, q) => go(`records?${new URLSearchParams({ ...(type && type !== "all" ? { type } : {}), q })}`);

/* Record cards, fetched once per record for every screen, a few at a time. */
const cardCache = new Map();
const waiting = new Map();
let batch = null;
export function loadCard(key) {
  if (cardCache.has(key)) return Promise.resolve(cardCache.get(key));
  if (waiting.has(key)) return waiting.get(key).promise;
  let resolve;
  const promise = new Promise((r) => (resolve = r));
  waiting.set(key, { promise, resolve });
  batch ??= setTimeout(async () => {
    const keys = [...waiting.keys()].filter((k) => !cardCache.has(k));
    const settle = new Map(waiting);
    waiting.clear();
    batch = null;
    let cards = {};
    try {
      cards = (await go(`records/cards?keys=${encodeURIComponent(keys.join(","))}`))?.cards ?? {};
    } catch {
      cards = {};
    }
    for (const k of keys) {
      cardCache.set(k, cards[k] ?? null);
      settle.get(k)?.resolve(cards[k] ?? null);
    }
  }, 60);
  return promise;
}

/* ── Live ─────────────────────────────────────────────────────────── */

function onEvent({ kind, chat_id: chatId, data }) {
  const s = S;
  const meId = s.me?.id;
  const known = (s.chats ?? []).some((c) => c.id === chatId);
  const open = s.activeId === chatId;
  if (kind === "message") {
    const m = data.message;
    if (s.threads[chatId]?.loaded) setThread(chatId, (x) => ({ ...x, messages: merge(x.messages, [m]) }));
    if (!known) refreshRow(chatId);
    else
      set((st) => ({
        chats: sortChats(
          (st.chats ?? []).map((c) =>
            c.id === chatId
              ? {
                  ...c,
                  last: { id: m.id, user_id: m.user_id, user_name: m.user_name, type: m.type, deleted: m.deleted, body: m.body, meta: m.meta, file_name: m.file?.name, status: m.status, created_at: m.created_at },
                  sort_at: m.created_at,
                  archived: c.muted ? c.archived : false,
                  unread: m.user_id !== meId && m.type !== "SYSTEM" && !open ? c.unread + 1 : c.unread,
                  mentioned: c.mentioned || ((m.meta?.mentions?.users ?? []).some((u) => u.id === meId) && !open),
                }
              : c,
          ),
        ),
      }));
    set((st) => {
      if (!st.typing[chatId]?.[m.user_id]) return {};
      const next = { ...st.typing[chatId] };
      delete next[m.user_id];
      return { typing: { ...st.typing, [chatId]: next } };
    });
    if (open && m.user_id !== meId) markRead(chatId);
    if (m.type === "SYSTEM") {
      refreshRow(chatId, 200);
      if (open) loadDetail(chatId);
    }
  } else if (kind === "update") {
    const m = data.message;
    if (s.threads[chatId]?.loaded) replaceMessage(m);
    set((st) => ({
      chats: (st.chats ?? []).map((c) => (c.id === chatId && c.last?.id === m.id ? { ...c, last: { ...c.last, body: m.body, deleted: m.deleted } } : c)),
      details: st.details[chatId] ? { ...st.details, [chatId]: { ...st.details[chatId], pinned_messages: (st.details[chatId].pinned_messages ?? []).map((p) => (p.id === m.id ? m : p)).filter((p) => !p.deleted) } } : st.details,
    }));
  } else if (kind === "read" || kind === "delivered") {
    const row = (s.chats ?? []).find((c) => c.id === chatId);
    const level = kind === "read" ? "read" : "delivered";
    const rank = { pending: 0, sent: 1, delivered: 2, read: 3 };
    if (row && !row.is_group) {
      const up = (m) => (m.user_id === meId && m.status && m.id <= data.upto && rank[m.status] < rank[level] ? { ...m, status: level } : m);
      if (s.threads[chatId]?.loaded) setThread(chatId, (x) => ({ ...x, messages: x.messages.map(up) }));
      set((st) => ({ chats: (st.chats ?? []).map((c) => (c.id === chatId && c.last ? { ...c, last: up(c.last) } : c)) }));
    } else if (row) {
      // A group's ticks need every member: ask again for its newest ticks.
      refreshRow(chatId, 600);
      if (open) {
        clearTimeout(refreshTimers[`t-${chatId}`]);
        refreshTimers[`t-${chatId}`] = setTimeout(async () => {
          try {
            const from = (S.threads[chatId]?.messages ?? []).find((m) => m.user_id === meId && m.status !== "read")?.id ?? "";
            const d = await go(`chats/${chatId}/messages?after=${encodeURIComponent(from)}`);
            const map = new Map(d.messages.map((m) => [m.id, m.status]));
            setThread(chatId, (x) => ({ ...x, messages: x.messages.map((m) => (map.has(m.id) ? { ...m, status: map.get(m.id) } : m)) }));
          } catch {
            /* next time */
          }
        }, 700);
      }
    }
  } else if (kind === "typing") {
    const until = Date.now() + 4500;
    set((st) => ({ typing: { ...st.typing, [chatId]: { ...(st.typing[chatId] ?? {}), [data.user_id]: { name: data.name, until } } } }));
    setTimeout(
      () =>
        set((st) => {
          const cur = st.typing[chatId]?.[data.user_id];
          if (!cur || cur.until > Date.now()) return {};
          const next = { ...st.typing[chatId] };
          delete next[data.user_id];
          return { typing: { ...st.typing, [chatId]: next } };
        }),
      4700,
    );
  } else if (kind === "pins") {
    if (open) loadDetail(chatId);
  } else if (kind === "chat") {
    refreshRow(chatId, 100);
    if (open) loadDetail(chatId);
  }
}

/**
 * Every chat screen calls this: the session's `call` for the store, the list
 * and people read, the socket held (live) — or, while it is not connected,
 * the list and the open conversation asked again every few seconds — and the
 * "I am here" ping each minute.
 */
export function useChatLive() {
  const { call, session } = useSession();
  const email = session?.kind === "EMAIL";
  api = call;
  const state = useChatStore();

  useEffect(() => {
    if (!email) return undefined;
    if (S.chats === null) loadChats();
    if (S.contacts === null) loadContacts();
    const offEvent = onChatEvent(onEvent);
    const offState = onSocketState((st) => {
      set({ socket: st });
      // Back on the line: catch up on what came while it was away.
      if (st === "connected") {
        loadChats();
        if (S.activeId) loadNewer(S.activeId);
      }
    });
    let release = () => {};
    const start = (meId) => (release = holdSocket(meId, (body) => call("/broadcasting/auth", { method: "POST", body })));
    if (S.me) start(S.me.id);
    else loadChats().then((d) => d?.me && start(d.me.id));
    const ping = () => call("/chat/ping", { method: "POST", body: {} }).catch(() => {});
    ping();
    const pinger = setInterval(ping, 60000);
    const people = setInterval(loadContacts, 60000);
    return () => {
      offEvent();
      offState();
      release();
      clearInterval(pinger);
      clearInterval(people);
    };
  }, [email, call]);

  // No socket: ask every few seconds (the list every ten, the open chat's new messages every four).
  const live = state.socket === "connected";
  useFocusEffect(
    useCallback(() => {
      if (live || !email) return undefined;
      const list = setInterval(loadChats, 10000);
      const open = setInterval(() => S.activeId && S.threads[S.activeId]?.loaded && loadNewer(S.activeId), 4000);
      return () => {
        clearInterval(list);
        clearInterval(open);
      };
    }, [live, email]),
  );

  return state;
}

/** How many messages wait unread (unmuted chats), for More's badge. */
export function useChatUnread(enabled = true) {
  const { call } = useSession();
  const [n, setN] = useState(0);

  useFocusEffect(
    useCallback(() => {
      if (!enabled) return;
      call("/chat/chats").then((d) => setN((d?.chats ?? []).reduce((a, c) => a + (c.muted ? 0 : c.unread), 0)), () => {});
    }, [call, enabled]),
  );

  return n;
}

/** Messages matching a search (2+ letters), in all chats or one; asked 300 ms after the typing stops. */
export function useMessageSearch(query, chatId = null) {
  const term = query.trim();
  const key = `${chatId ?? ""}|${term}`;
  const [state, setState] = useState({ key: "", results: [] });
  useEffect(() => {
    if (term.length < 2) return undefined;
    let stop = false;
    const timer = setTimeout(async () => {
      try {
        const d = await searchMessages(term, chatId);
        if (!stop) setState({ key, results: d?.results ?? [] });
      } catch {
        if (!stop) setState({ key, results: [] });
      }
    }, 300);
    return () => {
      stop = true;
      clearTimeout(timer);
    };
  }, [key, term, chatId]);
  const active = term.length >= 2;
  return { active, loading: active && state.key !== key, results: active && state.key === key ? state.results : [] };
}

/** Records matching what follows `#`, of one kind; the kinds this person may mention come back too. */
export function useRecordSearch(open, type, query) {
  const [state, setState] = useState({ key: null, types: null, results: [] });
  const key = `${type ?? ""}|${query}`;
  useEffect(() => {
    if (!open) return undefined;
    let stop = false;
    const timer = setTimeout(async () => {
      try {
        const d = await searchRecords(type, query);
        if (!stop) setState({ key, types: d?.types ?? [], type: d?.type, results: d?.results ?? [] });
      } catch {
        if (!stop) setState((s) => ({ ...s, key, results: [] }));
      }
    }, 250);
    return () => {
      stop = true;
      clearTimeout(timer);
    };
  }, [open, key, type, query]);
  return { types: state.types, type: state.type ?? type, loading: open && state.key !== key, results: state.key === key ? state.results : [] };
}

/** The cards of some records (`type:ref` keys). */
export function useCards(keys) {
  const joined = keys.join(",");
  const [cards, setCards] = useState({});
  const alive = useRef(true);
  useEffect(() => {
    alive.current = true;
    if (joined) Promise.all(joined.split(",").map((k) => loadCard(k).then((c) => [k, c]))).then((pairs) => alive.current && setCards(Object.fromEntries(pairs)));
    return () => {
      alive.current = false;
    };
  }, [joined]);
  return cards;
}

/* ── Mentions ─────────────────────────────────────────────────────── */

/** The `@…` or `#…` being typed right before the caret, if any. */
export function triggerAt(text, caret) {
  const before = text.slice(0, caret);
  const m = before.match(/(^|\s)([@#])([^\s@#]{0,40})$/u);
  if (!m) return null;
  return { kind: m[2], query: m[3], start: caret - m[3].length - 1, end: caret };
}

/** People of the chat matching what follows `@` (never me; in a direct chat, the other one). */
export function mentionPeople(chat, detail, me, people, query) {
  const ids = chat.is_group ? (detail?.members ?? []).filter((x) => !x.left_at).map((x) => x.id) : [detail?.other?.id ?? chat.other?.id].filter(Boolean);
  const q = query.toLowerCase();
  return ids
    .filter((id) => id !== me.id)
    .map((id) => people[id])
    .filter((p) => p && (!q || p.name.toLowerCase().includes(q) || p.name.toLowerCase().split(/\s+/).some((w) => w.startsWith(q))))
    .slice(0, 8);
}
