"use client";

/**
 * Chat's small shared pieces: the API call (through /api/chat, the session's
 * token added there), the live-event bus (the topbar's chat button holds the
 * one socket subscription and re-broadcasts each event to the page), times
 * and sizes as WhatsApp writes them, and the system lines of a group.
 */

export async function chatApi(path, { method = "GET", body, form, signal } = {}) {
  const res = await fetch(`/api/chat/${path}`, {
    method,
    headers: form ? undefined : body !== undefined ? { "Content-Type": "application/json", Accept: "application/json" } : { Accept: "application/json" },
    body: form ?? (body !== undefined ? JSON.stringify(body) : undefined),
    cache: "no-store",
    signal,
  });
  const json = await res.json().catch(() => null);
  if (!res.ok) {
    const first = json?.errors ? Object.values(json.errors)[0]?.[0] : null;
    const error = new Error(first ?? json?.message ?? res.statusText);
    error.status = res.status;
    throw error;
  }
  return json?.data;
}

/** The live chat events, re-sent on the window by the topbar's chat button (it holds the socket). */
export const CHAT_EVENT = "annotech:chat-event";
export const CHAT_STATE = "annotech:chat-socket";

export const fileUrl = (messageId, download = false) => `/api/chat/messages/${messageId}/file${download ? "?download=1" : ""}`;
export const avatarUrl = (userId) => `/api/chat/avatars/${userId}`;
export const chatImageUrl = (chatId, v = "") => `/api/chat/chats/${chatId}/image${v ? `?v=${v}` : ""}`;

/** WhatsApp's default avatar colours, one per person (the server picks the index). */
export const TONES = ["#e57373", "#f06292", "#ba68c8", "#9575cd", "#7986cb", "#5c9ce6", "#26a69a", "#4db6ac", "#66bb6a", "#f2a33a", "#a1887f", "#78909c"];
/** A sender's name colour in a group bubble. */
export const NAME_TONES = ["#d32f2f", "#c2185b", "#7b1fa2", "#512da8", "#303f9f", "#1976d2", "#00796b", "#00897b", "#388e3c", "#e65100", "#6d4c41", "#455a64"];

const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

/** A message's clock in 12-hour time with AM/PM, e.g. "3:45 PM" (the user, 2026-11-23). */
export function clock(iso) {
  if (!iso) return "";
  const d = new Date(iso);
  const h = d.getHours();
  return `${h % 12 || 12}:${String(d.getMinutes()).padStart(2, "0")} ${h < 12 ? "AM" : "PM"}`;
}

/** The time a chat row shows: today's clock, "Yesterday", a weekday this week, else the date. */
export function listTime(iso, t, locale) {
  if (!iso) return "";
  const d = new Date(iso);
  const now = new Date();
  if (sameDay(d, now)) return clock(iso, locale);
  const y = new Date(now);
  y.setDate(now.getDate() - 1);
  if (sameDay(d, y)) return t("yesterday");
  if (now - d < 6 * 86400000) return d.toLocaleDateString(locale === "bn" ? "bn-BD" : "en-GB", { weekday: "long" });
  return d.toLocaleDateString(locale === "bn" ? "bn-BD" : "en-GB", { day: "2-digit", month: "2-digit", year: "numeric" });
}

/** The day pill between messages: Today, Yesterday, a weekday, or the date. */
export function dayLabel(iso, t, locale) {
  const d = new Date(iso);
  const now = new Date();
  if (sameDay(d, now)) return t("today");
  const y = new Date(now);
  y.setDate(now.getDate() - 1);
  if (sameDay(d, y)) return t("yesterday");
  if (now - d < 6 * 86400000) return d.toLocaleDateString(locale === "bn" ? "bn-BD" : "en-GB", { weekday: "long" });
  return d.toLocaleDateString(locale === "bn" ? "bn-BD" : "en-GB", { day: "numeric", month: "long", year: "numeric" });
}

export const dayKey = (iso) => {
  const d = new Date(iso);
  return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
};

export function lastSeen(person, t, locale) {
  if (!person) return "";
  if (person.online) return t("online");
  if (!person.last_seen_at) return "";
  const d = new Date(person.last_seen_at);
  const now = new Date();
  if (sameDay(d, now)) return t("last_seen_today", { time: clock(person.last_seen_at, locale) });
  const y = new Date(now);
  y.setDate(now.getDate() - 1);
  if (sameDay(d, y)) return t("last_seen_yesterday", { time: clock(person.last_seen_at, locale) });
  return t("last_seen", { when: d.toLocaleDateString(locale === "bn" ? "bn-BD" : "en-GB", { day: "numeric", month: "short", year: "numeric" }) });
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

/** A group's system line ("Karim added Rahim"), the names from the chat's people. */
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

export const QUICK_REACTIONS = ["👍", "❤️", "😂", "😮", "😢", "🙏"];
