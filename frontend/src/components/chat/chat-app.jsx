"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useLocale } from "next-intl";
import { Archive, BellOff, BellRing, ChevronDown, Lock, MessageCircle, MessageSquarePlus, MoreVertical, Pin, Users } from "lucide-react";
import { cn } from "@/lib/utils";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Modal } from "@/components/ui/modal";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { ChatAvatar } from "./chat-avatar";
import { ChatConversation } from "./chat-conversation";
import { FloatMenu, MenuItem, Ticks } from "./chat-message";
import { ForwardModal, ImageViewer, InfoModal, InfoPanel, MessageHit, MessageSearchPanel, NewChatPane, NewGroupPane, SearchBox, useMessageSearch } from "./chat-panels";
import { CHAT_EVENT, CHAT_STATE, chatApi, fileUrl, listTime, previewText } from "./chat-lib";
import { listenForChatOpen, pushActive, pushOff, pushOn, pushPermission } from "./chat-push";

const KEEP = "annotech.chat.open";
const NOTIFY_LATER = "annotech.chat.notify.later";
const sortChats = (list) => [...list].sort((a, b) => (b.pinned ? 1 : 0) - (a.pinned ? 1 : 0) || String(b.sort_at ?? "").localeCompare(String(a.sort_at ?? "")));

/**
 * Chat (user 2026-11-22: Norban's chat module, WhatsApp style). The left pane:
 * my avatar, New chat / New group, search (chats and the company's people),
 * All / Unread / Groups, the archived chats, and the chat rows (photo, name,
 * last message with my ticks or "typing…", time, unread badge, pinned /
 * muted). The right: the open chat, its info drawer. Live through the
 * topbar's socket (window events): messages, edits, reactions, read ticks,
 * typing and the chats themselves. No chat id ever goes in the address.
 */
function ChatApp({ initial }) {
  const t = useT("chat");
  const locale = useLocale();
  const toast = useToastManager();
  const me = initial.me;
  const [chats, setChats] = useState(() => sortChats(initial.chats));
  const [activeId, setActiveId] = useState(null);
  const [pane, setPane] = useState("list");
  const [filter, setFilter] = useState("all");
  const [query, setQuery] = useState("");
  const [contacts, setContacts] = useState(null);
  const [threads, setThreads] = useState({});
  const [details, setDetails] = useState({});
  const [typing, setTyping] = useState({});
  const [info, setInfo] = useState(false);
  const [searching, setSearching] = useState(false);
  const [socket, setSocket] = useState("initial");
  const [forward, setForward] = useState(null);
  const [msgInfo, setMsgInfo] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [confirm, setConfirm] = useState(null);
  const [viewer, setViewer] = useState(null);
  const [jumpTo, setJumpTo] = useState(null);
  const [menu, setMenu] = useState(null);
  const [busy, setBusy] = useState(false);
  const live = useRef({});
  const [mounted, setMounted] = useState(false);
  useEffect(() => {
    queueMicrotask(() => setMounted(true));
  }, []);
  // Desktop notifications (Web Push): this browser's permission and whether it is subscribed.
  const [notify, setNotify] = useState({ permission: "unknown", active: false, later: false });
  const readNotify = useCallback(async () => {
    let later = false;
    try {
      later = localStorage.getItem(NOTIFY_LATER) === "1";
    } catch {
      later = false;
    }
    setNotify({ permission: pushPermission(), active: await pushActive().catch(() => false), later });
  }, []);
  useEffect(() => {
    queueMicrotask(readNotify);
  }, [readNotify]);
  const readTimer = useRef(null);
  const refreshTimers = useRef({});

  const active = chats.find((c) => c.id === activeId) ?? null;
  // Everyone this screen knows, for names, colours and photos (system lines, quotes, reactions).
  const people = useMemo(() => {
    const out = { [me.id]: me };
    (contacts ?? []).forEach((p) => (out[p.id] = p));
    chats.forEach((c) => c.other && (out[c.other.id] ??= c.other));
    Object.values(details).forEach((d) => (d.members ?? []).forEach((p) => (out[p.id] = { ...out[p.id], ...p })));
    return out;
  }, [contacts, chats, details, me]);

  useEffect(() => {
    live.current = { activeId, threads, chats };
  });

  // The topbar badge follows this screen's own count.
  const total = chats.reduce((a, c) => a + (c.muted ? 0 : c.unread), 0);
  useEffect(() => {
    window.dispatchEvent(new CustomEvent("annotech:chat-unread", { detail: total }));
  }, [total]);

  const loadContacts = useCallback(async () => {
    try {
      setContacts(await chatApi("contacts"));
    } catch {
      setContacts([]);
    }
  }, []);
  useEffect(() => {
    queueMicrotask(loadContacts);
    const id = setInterval(loadContacts, 60000);
    return () => clearInterval(id);
  }, [loadContacts]);

  const upsertChat = useCallback((row) => {
    if (!row) return;
    setChats((all) => sortChats([row, ...all.filter((c) => c.id !== row.id)]));
  }, []);
  const refreshRow = useCallback(
    (id, delay = 0) => {
      clearTimeout(refreshTimers.current[id]);
      refreshTimers.current[id] = setTimeout(async () => {
        try {
          upsertChat(await chatApi(`chats/${id}/row`));
        } catch {
          /* gone */
        }
      }, delay);
    },
    [upsertChat],
  );
  const loadDetail = useCallback(async (id) => {
    try {
      const d = await chatApi(`chats/${id}`);
      setDetails((all) => ({ ...all, [id]: d }));
    } catch {
      /* not mine any more */
    }
  }, []);

  const setThread = (id, fn) => setThreads((all) => ({ ...all, [id]: fn(all[id] ?? { messages: [], loaded: false }) }));
  const merge = (list, incoming) => {
    const byId = new Map(list.filter((m) => !String(m.id).startsWith("tmp-")).map((m) => [m.id, m]));
    incoming.forEach((m) => byId.set(m.id, m));
    const temps = list.filter((m) => String(m.id).startsWith("tmp-"));
    return [...[...byId.values()].sort((a, b) => (a.id < b.id ? -1 : 1)), ...temps];
  };

  const loadMessages = useCallback(async (id, params = "") => {
    setThread(id, (th) => ({ ...th, loading: true }));
    try {
      const d = await chatApi(`chats/${id}/messages${params}`);
      setThread(id, (th) => {
        if (params.startsWith("?before")) return { ...th, messages: merge(th.messages, d.messages), has_older: d.has_older, loading: false };
        if (params.startsWith("?after")) return { ...th, messages: merge(th.messages, d.messages), has_newer: d.has_newer, loading: false };
        if (params.startsWith("?around")) return { ...th, messages: d.messages, has_older: d.has_older, has_newer: d.has_newer, loading: false, loaded: true };
        // The "N unread messages" line marks what was unread when the chat opened — not what arrives while it is open.
        const unreadFrom = th.loaded ? th.unread_from : (d.messages.find((m) => m.user_id !== initial.me.id && m.type !== "SYSTEM" && (d.read_upto === null || m.id > d.read_upto))?.id ?? null);
        return { messages: d.messages, has_older: d.has_older, has_newer: false, loading: false, loaded: true, unread_from: unreadFrom };
      });
      if (!params) setChats((all) => all.map((c) => (c.id === id ? { ...c, unread: 0 } : c)));
      return d;
    } catch (e) {
      setThread(id, (th) => ({ ...th, loading: false, loaded: true }));
      toast.add({ title: e.message, type: "danger" });
      return null;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [toast]);

  const open = useCallback(
    (id) => {
      setActiveId(id);
      setInfo(false);
      setSearching(false);
      setJumpTo(null);
      try {
        sessionStorage.setItem(KEEP, id);
      } catch {
        /* the open chat is only remembered where storage works */
      }
      setThreads((all) => ({ ...all, [id]: undefined }));
      loadMessages(id);
      loadDetail(id);
    },
    [loadMessages, loadDetail],
  );

  // A found message: its chat opens on it (the messages around it), the message marked.
  const openAt = async (id, messageId) => {
    if (id !== activeId) {
      setActiveId(id);
      setInfo(false);
      setJumpTo(null);
      try {
        sessionStorage.setItem(KEEP, id);
      } catch {
        /* the open chat is only remembered where storage works */
      }
      setThreads((all) => ({ ...all, [id]: undefined }));
      loadDetail(id);
    }
    if (!threads[id]?.messages?.some((m) => m.id === messageId) || id !== activeId) await loadMessages(id, `?around=${encodeURIComponent(messageId)}`);
    setJumpTo((j) => ({ id: messageId, at: (j?.at ?? 0) + 1 }));
  };

  // A click on a desktop notification opens its chat here.
  useEffect(
    () =>
      listenForChatOpen((id) => {
        if (!live.current.chats.some((c) => c.id === id)) refreshRow(id);
        open(id);
      }),
    [open, refreshRow],
  );

  const toggleNotify = async () => {
    try {
      if (notify.active) {
        await pushOff();
      } else {
        const result = await pushOn({ ask: true });
        if (result === "granted") toast.add({ title: t("notify_enabled"), type: "success" });
        else if (result === "denied") toast.add({ title: t("notify_denied"), type: "warning" });
        else if (result === "unsupported") toast.add({ title: t("notify_unsupported"), type: "warning" });
      }
    } catch (e) {
      toast.add({ title: e.message, type: "danger" });
    }
    readNotify();
  };
  const notifyLater = () => {
    try {
      localStorage.setItem(NOTIFY_LATER, "1");
    } catch {
      /* asked again next time */
    }
    setNotify((n) => ({ ...n, later: true }));
  };

  // The chat open before a refresh opens again.
  useEffect(() => {
    let id = null;
    try {
      id = sessionStorage.getItem(KEEP);
    } catch {
      id = null;
    }
    if (id && initial.chats.some((c) => c.id === id)) queueMicrotask(() => open(id));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const markRead = useCallback((id) => {
    clearTimeout(readTimer.current);
    readTimer.current = setTimeout(() => {
      chatApi(`chats/${id}/read`, { method: "POST" }).catch(() => {});
      setChats((all) => all.map((c) => (c.id === id ? { ...c, unread: 0 } : c)));
    }, 400);
  }, []);

  /* ── Live ── */
  useEffect(() => {
    const onState = (e) => setSocket(e.detail);
    const onEvent = (e) => {
      const { kind, chat_id: chatId, data } = e.detail ?? {};
      const { activeId: openId, threads: th, chats: rows } = live.current;
      const known = rows.some((c) => c.id === chatId);
      if (kind === "message") {
        const m = data.message;
        if (th[chatId]?.loaded) setThread(chatId, (x) => ({ ...x, messages: merge(x.messages, [m]) }));
        if (!known) refreshRow(chatId);
        else
          setChats((all) =>
            sortChats(
              all.map((c) =>
                c.id === chatId
                  ? {
                      ...c,
                      last: { id: m.id, user_id: m.user_id, user_name: m.user_name, type: m.type, deleted: m.deleted, body: m.body, meta: m.meta, file_name: m.file?.name, status: m.status, created_at: m.created_at },
                      sort_at: m.created_at,
                      archived: c.muted ? c.archived : false,
                      unread: m.user_id !== me.id && m.type !== "SYSTEM" && !(openId === chatId && document.visibilityState === "visible") ? c.unread + 1 : c.unread,
                      mentioned: c.mentioned || ((m.meta?.mentions?.users ?? []).some((u) => u.id === me.id) && !(openId === chatId && document.visibilityState === "visible")),
                    }
                  : c,
              ),
            ),
          );
        setTyping((all) => {
          if (!all[chatId]?.[m.user_id]) return all;
          const next = { ...all[chatId] };
          delete next[m.user_id];
          return { ...all, [chatId]: next };
        });
        if (openId === chatId && m.user_id !== me.id && document.visibilityState === "visible") markRead(chatId);
        if (m.type === "SYSTEM") {
          refreshRow(chatId, 200);
          if (openId === chatId) loadDetail(chatId);
        }
      } else if (kind === "update") {
        const m = data.message;
        if (th[chatId]?.loaded) setThread(chatId, (x) => ({ ...x, messages: x.messages.map((y) => (y.id === m.id ? m : y)) }));
        setChats((all) => all.map((c) => (c.id === chatId && c.last?.id === m.id ? { ...c, last: { ...c.last, body: m.body, deleted: m.deleted } } : c)));
        if (openId === chatId) setDetails((all) => (all[chatId] ? { ...all, [chatId]: { ...all[chatId], pinned_messages: (all[chatId].pinned_messages ?? []).map((p) => (p.id === m.id ? m : p)).filter((p) => !p.deleted) } } : all));
      } else if (kind === "read" || kind === "delivered") {
        const row = rows.find((c) => c.id === chatId);
        const level = kind === "read" ? "read" : "delivered";
        const rank = { pending: 0, sent: 1, delivered: 2, read: 3 };
        if (row && !row.is_group) {
          const up = (m) => (m.user_id === me.id && m.status && m.id <= data.upto && rank[m.status] < rank[level] ? { ...m, status: level } : m);
          if (th[chatId]?.loaded) setThread(chatId, (x) => ({ ...x, messages: x.messages.map(up) }));
          setChats((all) => all.map((c) => (c.id === chatId && c.last ? { ...c, last: up(c.last) } : c)));
        } else if (row) {
          // A group's ticks need every member: ask again for the newest page's ticks.
          refreshRow(chatId, 600);
          if (openId === chatId) {
            clearTimeout(refreshTimers.current[`t-${chatId}`]);
            refreshTimers.current[`t-${chatId}`] = setTimeout(async () => {
              try {
                const d = await chatApi(`chats/${chatId}/messages?after=${encodeURIComponent((live.current.threads[chatId]?.messages ?? []).find((m) => m.user_id === me.id && m.status !== "read")?.id ?? "")}`);
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
        setTyping((all) => ({ ...all, [chatId]: { ...(all[chatId] ?? {}), [data.user_id]: { name: data.name, until } } }));
        setTimeout(() => setTyping((all) => {
          const cur = all[chatId]?.[data.user_id];
          if (!cur || cur.until > Date.now()) return all;
          const next = { ...all[chatId] };
          delete next[data.user_id];
          return { ...all, [chatId]: next };
        }), 4700);
      } else if (kind === "pins") {
        if (openId === chatId) loadDetail(chatId);
      } else if (kind === "chat") {
        refreshRow(chatId, 100);
        if (openId === chatId) loadDetail(chatId);
      }
    };
    window.addEventListener(CHAT_EVENT, onEvent);
    window.addEventListener(CHAT_STATE, onState);
    const onVisible = () => {
      const id = live.current.activeId;
      if (document.visibilityState === "visible" && id) markRead(id);
    };
    document.addEventListener("visibilitychange", onVisible);
    return () => {
      window.removeEventListener(CHAT_EVENT, onEvent);
      window.removeEventListener(CHAT_STATE, onState);
      document.removeEventListener("visibilitychange", onVisible);
    };
  }, [me.id, markRead, refreshRow, loadDetail]);

  /* ── Doing ── */
  const startDirect = async (person) => {
    try {
      const row = await chatApi("chats/direct", { method: "POST", body: { user_id: person.id } });
      upsertChat(row);
      setPane("list");
      setQuery("");
      open(row.id);
    } catch (e) {
      toast.add({ title: e.message, type: "danger" });
    }
  };

  const createGroup = async (name, ids) => {
    setBusy(true);
    try {
      const row = await chatApi("chats/group", { method: "POST", body: { name, user_ids: ids } });
      upsertChat(row);
      setPane("list");
      open(row.id);
    } catch (e) {
      toast.add({ title: e.message, type: "danger" });
    } finally {
      setBusy(false);
    }
  };

  const send = async ({ body, file, reply, mentions = null }) => {
    const id = activeId;
    if (!id) return;
    const tmp = `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
    const localUrl = file && file.type.startsWith("image/") ? URL.createObjectURL(file) : null;
    const draft = {
      id: tmp, chat_id: id, user_id: me.id, user_name: me.name, type: file ? (localUrl ? "IMAGE" : "FILE") : "TEXT", body: body || null,
      file: file ? { name: file.name, size: file.size, mime: file.type, image: !!localUrl } : null, local_url: localUrl,
      reply: reply ? { id: reply.id, user_id: reply.user_id, type: reply.type, body: reply.body, file_name: reply.file?.name, image: reply.file?.image } : null,
      reactions: [], status: "pending", created_at: new Date().toISOString(), meta: mentions ? { mentions } : null,
    };
    setThread(id, (x) => ({ ...x, messages: [...x.messages, draft] }));
    try {
      let m;
      if (file) {
        const form = new FormData();
        form.append("file", file);
        if (body) form.append("body", body);
        if (reply) form.append("reply_to_id", reply.id);
        if (mentions) form.append("mentions", JSON.stringify(mentions));
        m = await chatApi(`chats/${id}/messages`, { method: "POST", form });
      } else {
        m = await chatApi(`chats/${id}/messages`, { method: "POST", body: { body, reply_to_id: reply?.id ?? null, mentions } });
      }
      setThread(id, (x) => ({ ...x, messages: merge(x.messages.filter((y) => y.id !== tmp), [m]) }));
      setChats((all) => sortChats(all.map((c) => (c.id === id ? { ...c, last: { ...m, file_name: m.file?.name }, sort_at: m.created_at, archived: false } : c))));
    } catch (e) {
      setThread(id, (x) => ({ ...x, messages: x.messages.map((y) => (y.id === tmp ? { ...y, status: "failed" } : y)) }));
      toast.add({ title: t("send_failed"), description: e.message, type: "danger" });
    } finally {
      if (localUrl) setTimeout(() => URL.revokeObjectURL(localUrl), 60000);
    }
  };

  const replaceMessage = (m) => setThread(m.chat_id, (x) => ({ ...x, messages: x.messages.map((y) => (y.id === m.id ? m : y)) }));

  const onAction = async (kind, m, extra) => {
    try {
      if (kind === "toast") toast.add({ title: extra, type: "warning" });
      else if (kind === "mention-user") await startDirect(extra);
      else if (kind === "copy") {
        await navigator.clipboard?.writeText(m.body ?? "");
        toast.add({ title: t("copied"), type: "info" });
      } else if (kind === "react") replaceMessage(await chatApi(`messages/${m.id}/react`, { method: "POST", body: { emoji: extra } }));
      else if (kind === "pin") {
        await chatApi(`messages/${m.id}/pin`, { method: "POST" });
        loadDetail(m.chat_id);
      } else if (kind === "forward") setForward(m);
      else if (kind === "info") {
        setMsgInfo({ id: m.id, data: null });
        const d = await chatApi(`messages/${m.id}/info`);
        setMsgInfo({ id: m.id, data: d });
      } else if (kind === "delete") setDeleting(m);
      else if (kind === "download") window.location.href = fileUrl(m.id, true);
    } catch (e) {
      toast.add({ title: e.message, type: "danger" });
    }
  };

  const editSave = async (m, body, mentions = null) => {
    try {
      replaceMessage(await chatApi(`messages/${m.id}`, { method: "PATCH", body: { body, mentions } }));
    } catch (e) {
      toast.add({ title: e.message, type: "danger" });
    }
  };

  const remove = async (scope) => {
    const m = deleting;
    setDeleting(null);
    try {
      await chatApi(`messages/${m.id}?scope=${scope}`, { method: "DELETE" });
      if (scope === "me") setThread(m.chat_id, (x) => ({ ...x, messages: x.messages.filter((y) => y.id !== m.id) }));
      else replaceMessage({ ...m, deleted: true, body: null, file: null, reactions: [] });
    } catch (e) {
      toast.add({ title: e.message, type: "danger" });
    }
  };

  const chatMenu = async (kind, chat = active) => {
    if (!chat) return;
    try {
      if (kind === "pin") upsertChat(await chatApi(`chats/${chat.id}/settings`, { method: "PATCH", body: { pinned: !chat.pinned } }));
      else if (kind === "mute") upsertChat(await chatApi(`chats/${chat.id}/settings`, { method: "PATCH", body: { muted: !chat.muted } }));
      else if (kind === "archive") {
        upsertChat(await chatApi(`chats/${chat.id}/settings`, { method: "PATCH", body: { archived: !chat.archived } }));
        if (!chat.archived && chat.id === activeId) setActiveId(null);
      } else if (kind === "clear") setConfirm({ kind: "clear", chat });
      else if (kind === "exit") setConfirm({ kind: "exit", chat });
    } catch (e) {
      toast.add({ title: e.message, type: "danger" });
    }
  };

  const confirmed = async () => {
    const { kind, chat } = confirm;
    setConfirm(null);
    try {
      if (kind === "clear") {
        upsertChat(await chatApi(`chats/${chat.id}/clear`, { method: "POST" }));
        if (chat.id === activeId) loadMessages(chat.id);
      } else if (kind === "exit") {
        await chatApi(`chats/${chat.id}/members/${me.id}`, { method: "DELETE" });
        refreshRow(chat.id);
        loadDetail(chat.id);
        loadMessages(chat.id);
      }
    } catch (e) {
      toast.add({ title: e.message, type: "danger" });
    }
  };

  const groupCall = async (fn) => {
    try {
      const row = await fn();
      if (row?.id) upsertChat(row);
      if (activeId) loadDetail(activeId);
    } catch (e) {
      toast.add({ title: e.message, type: "danger" });
    }
  };

  const jump = async (messageId) => {
    if (!activeId) return;
    if (messageId === null) {
      await loadMessages(activeId);
      return;
    }
    const loaded = threads[activeId]?.messages?.some((m) => m.id === messageId);
    if (!loaded) await loadMessages(activeId, `?around=${encodeURIComponent(messageId)}`);
    setJumpTo({ id: messageId, at: Date.now() });
  };

  /* ── The list ── */
  const q = query.trim().toLowerCase();
  const found = useMessageSearch(pane === "archived" ? "" : query);
  const archivedCount = chats.filter((c) => c.archived).length;
  const shownChats = chats.filter((c) => {
    if (pane === "archived") return c.archived;
    if (c.archived && !q) return false;
    if (q) return (c.name ?? "").toLowerCase().includes(q) || (c.last?.body ?? "").toLowerCase().includes(q);
    if (filter === "unread") return c.unread > 0;
    if (filter === "groups") return c.is_group;
    return true;
  });
  const directIds = new Set(chats.filter((c) => !c.is_group && c.other).map((c) => c.other.id));
  const matchedPeople = q ? (contacts ?? []).filter((p) => !directIds.has(p.id) && `${p.name} ${p.about ?? ""}`.toLowerCase().includes(q)) : [];

  // Times are written in the viewer's own clock: drawn only in the browser (the server's clock is not theirs).
  if (!mounted) return <div className="rounded-lg border border-[#d1d7db] bg-[#f0f2f5]" style={{ height: "calc(100dvh - 9.75rem)", minHeight: 480 }} />;

  const rowLine = (c) => {
    const names = Object.values(typing[c.id] ?? {}).map((x) => x.name);
    if (names.length) return <span className="text-[#1fa855]">{c.is_group ? t("typing_name", { name: names.join(", ") }) : t("typing")}</span>;
    const mine = c.last && c.last.user_id === me.id && c.last.type !== "SYSTEM" && !c.last.deleted;
    return (
      <span className="flex min-w-0 items-center gap-1">
        {mine ? <Ticks status={c.last.status} className="shrink-0" /> : null}
        <span className="truncate">{previewText(c.last, c, people, me.id, t)}</span>
      </span>
    );
  };

  return (
    <div className="chat-emoji flex overflow-hidden rounded-lg border border-[#d1d7db] bg-white shadow-sm" style={{ height: "calc(100dvh - 9.75rem)", minHeight: 480 }}>
      {/* Left pane */}
      <div className={cn("flex w-full min-w-0 flex-col md:w-[340px] lg:w-[400px] md:shrink-0", activeId && "hidden md:flex")}>
        {pane === "new-chat" ? (
          <NewChatPane contacts={contacts} t={t} locale={locale} onBack={() => setPane("list")} onPick={startDirect} onGroup={() => setPane("new-group")} />
        ) : pane === "new-group" ? (
          <NewGroupPane contacts={contacts} t={t} locale={locale} busy={busy} onBack={() => setPane("list")} onCreate={createGroup} />
        ) : (
          <>
            <div className="flex h-[60px] shrink-0 items-center justify-between bg-[#f0f2f5] px-4">
              {pane === "archived" ? (
                <button type="button" onClick={() => setPane("list")} className="flex items-center gap-4 text-[16px] text-[#111b21]">
                  <ChevronDown className="size-5 rotate-90 text-[#54656f]" /> {t("tabs.archived")}
                </button>
              ) : (
                <ChatAvatar person={me} size={40} />
              )}
              <span className="flex items-center gap-1 text-[#54656f]">
                <button type="button" onClick={() => setPane("new-group")} aria-label={t("new_group")} title={t("new_group")} className="rounded-full p-2 hover:bg-black/5"><Users className="size-5" /></button>
                <button type="button" onClick={() => setPane("new-chat")} aria-label={t("new_chat")} title={t("new_chat")} className="rounded-full p-2 hover:bg-black/5"><MessageSquarePlus className="size-5" /></button>
                <button type="button" onClick={(e) => setMenu(e.currentTarget)} aria-label={t("menu")} className="rounded-full p-2 hover:bg-black/5"><MoreVertical className="size-5" /></button>
                {menu ? (
                  <FloatMenu anchor={menu} onClose={() => setMenu(null)}>
                    <MenuItem onClick={() => { setMenu(null); setPane("new-group"); }}>{t("new_group")}</MenuItem>
                    <MenuItem onClick={() => { setMenu(null); setPane("archived"); }}>{t("tabs.archived")}</MenuItem>
                    {notify.permission !== "unsupported" && notify.permission !== "unknown" ? (
                      <MenuItem onClick={() => { setMenu(null); toggleNotify(); }}>{notify.active ? t("notify_off") : t("notify_on")}</MenuItem>
                    ) : null}
                  </FloatMenu>
                ) : null}
              </span>
            </div>
            {pane === "list" && notify.permission === "default" && !notify.later ? (
              <div className="flex shrink-0 items-center gap-4 bg-[#53bdeb] px-4 py-3 text-white">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-white text-[#53bdeb]"><BellOff className="size-5" /></span>
                <button type="button" onClick={toggleNotify} className="min-w-0 flex-1 text-left">
                  <span className="block text-[16px]">{t("notify_title")}</span>
                  <span className="block text-[14px] text-white/90 underline-offset-2 hover:underline">{t("notify_text")} ›</span>
                </button>
                <button type="button" onClick={notifyLater} aria-label={t("notify_later")} title={t("notify_later")} className="rounded-full p-1 text-white/90 hover:bg-white/15">✕</button>
              </div>
            ) : null}
            {pane === "list" && notify.permission === "granted" && !notify.active && !notify.later ? (
              <button type="button" onClick={toggleNotify} className="flex shrink-0 items-center gap-3 bg-[#e7fce3] px-4 py-2 text-left text-[13px] text-[#008069]">
                <BellRing className="size-4 shrink-0" /> {t("notify_on")}
              </button>
            ) : null}
            <SearchBox value={query} onChange={setQuery} placeholder={t("search")} />
            {pane === "list" ? (
              <div className="flex shrink-0 gap-2 border-b border-[#e9edef] bg-white px-3 pb-2">
                {["all", "unread", "groups"].map((k) => (
                  <button key={k} type="button" onClick={() => setFilter(k)} className={cn("rounded-full px-3 py-1 text-[14px]", filter === k ? "bg-[#e7fce3] text-[#008069]" : "bg-[#f0f2f5] text-[#54656f] hover:bg-[#e9edef]")}>
                    {t(`tabs.${k}`)}
                    {k === "unread" && chats.some((c) => c.unread) ? ` ${chats.filter((c) => c.unread).length}` : ""}
                  </button>
                ))}
              </div>
            ) : null}
            <div className="min-h-0 flex-1 overflow-y-auto bg-white">
              {pane === "list" && !q && archivedCount ? (
                <button type="button" onClick={() => setPane("archived")} className="flex w-full items-center gap-6 px-6 py-3.5 text-left hover:bg-[#f5f6f6]">
                  <Archive className="size-5 text-[#00a884]" />
                  <span className="flex-1 text-[16px] text-[#111b21]">{t("tabs.archived")}</span>
                  <span className="text-[12px] text-[#00a884]">{archivedCount}</span>
                </button>
              ) : null}
              {shownChats.map((c) => (
                <button key={c.id} type="button" onClick={() => open(c.id)} className={cn("flex w-full items-center gap-3 pl-3 text-left", c.id === activeId ? "bg-[#f0f2f5]" : "hover:bg-[#f5f6f6]")}>
                  <span className="py-3"><ChatAvatar chat={c} size={49} online={!c.is_group && c.other?.online} /></span>
                  <span className="flex min-w-0 flex-1 flex-col justify-center self-stretch border-b border-[#e9edef] py-2.5 pr-3.5">
                    <span className="flex items-baseline gap-2">
                      <span className="min-w-0 flex-1 truncate text-[17px] text-[#111b21]">{c.name}</span>
                      <span className={cn("shrink-0 text-[12px]", c.unread && !c.muted ? "font-medium text-[#1fa855]" : "text-[#667781]")}>{listTime(c.last?.created_at ?? c.sort_at, t, locale)}</span>
                    </span>
                    <span className="mt-0.5 flex items-center gap-1.5 text-[14px] text-[#667781]">
                      <span className="min-w-0 flex-1">{rowLine(c)}</span>
                      {c.mentioned && c.unread ? <span title={t("mentioned_you")} className="flex size-5 shrink-0 items-center justify-center rounded-full bg-[#25d366] text-[12px] font-bold text-white">@</span> : null}
                      {c.muted ? <BellOff className="size-4 shrink-0 text-[#8696a0]" /> : null}
                      {c.pinned ? <Pin className="size-4 shrink-0 rotate-45 text-[#8696a0]" /> : null}
                      {c.unread ? <span className={cn("flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full px-1.5 text-[12px] font-medium text-white", c.muted ? "bg-[#8696a0]" : "bg-[#25d366]")}>{c.unread}</span> : null}
                    </span>
                  </span>
                </button>
              ))}
              {matchedPeople.length ? <p className="px-6 pt-5 pb-2 text-[15px] text-[#008069]">{t("people")}</p> : null}
              {matchedPeople.map((p) => (
                <button key={p.id} type="button" onClick={() => startDirect(p)} className="flex w-full items-center gap-3 pl-3 text-left hover:bg-[#f5f6f6]">
                  <span className="py-2.5"><ChatAvatar person={p} size={49} online={p.online} /></span>
                  <span className="min-w-0 flex-1 self-stretch border-b border-[#e9edef] py-3 pr-3.5">
                    <span className="block truncate text-[17px] text-[#111b21]">{p.name}</span>
                    <span className="block truncate text-[14px] text-[#667781]">{p.about ?? p.email}</span>
                  </span>
                </button>
              ))}
              {found.active ? <p className="px-6 pt-5 pb-2 text-[15px] text-[#008069]">{t("messages")}</p> : null}
              {found.loading ? <p className="px-6 py-2 text-[13px] text-[#667781]">{t("searching")}</p> : null}
              {found.active && !found.loading && !found.results.length ? <p className="px-6 py-2 text-[13px] text-[#667781]">{t("no_messages_found")}</p> : null}
              {found.results.map((hit) => (
                <MessageHit key={hit.id} hit={hit} chat={chats.find((c) => c.id === hit.chat_id)} query={query} me={me} t={t} locale={locale} showChat onClick={() => openAt(hit.chat_id, hit.id)} />
              ))}
              {!shownChats.length && !matchedPeople.length && !found.active ? (
                <div className="flex flex-col items-center gap-4 px-8 py-12 text-center">
                  <p className="text-[14px] text-[#667781]">{q ? t("no_match") : t("empty_list")}</p>
                  {!q ? <Button type="button" size="sm" onClick={() => setPane("new-chat")}><MessageSquarePlus /> {t("new_chat")}</Button> : null}
                </div>
              ) : null}
            </div>
          </>
        )}
      </div>

      {/* Right pane */}
      {active ? (
        <>
          <div className={cn("flex min-w-0 flex-1", (info || searching) && "hidden xl:flex")}>
            <ChatConversation
              chat={active}
              detail={details[active.id]}
              thread={threads[active.id]}
              me={me}
              people={people}
              typing={typing[active.id]}
              t={t}
              locale={locale}
              socket={socket}
              jumpTo={jumpTo}
              onBack={() => setActiveId(null)}
              onInfo={() => { setSearching(false); setInfo(true); }}
              onSearch={() => { setInfo(false); setSearching(true); }}
              onSend={send}
              onEditSave={editSave}
              onAction={onAction}
              onLoadOlder={() => {
                const first = threads[active.id]?.messages?.find((m) => !String(m.id).startsWith("tmp-"));
                if (first) loadMessages(active.id, `?before=${encodeURIComponent(first.id)}`);
              }}
              onLoadNewer={() => {
                const real = (threads[active.id]?.messages ?? []).filter((m) => !String(m.id).startsWith("tmp-"));
                if (real.length) loadMessages(active.id, `?after=${encodeURIComponent(real[real.length - 1].id)}`);
              }}
              onJump={jump}
              onTyping={() => chatApi(`chats/${active.id}/typing`, { method: "POST" }).catch(() => {})}
              onMenu={(k) => chatMenu(k)}
              onOpenImage={(m) => setViewer({ id: m.id, name: m.file?.name })}
            />
          </div>
          {searching ? (
            <MessageSearchPanel
              key={`s-${active.id}`}
              chat={active}
              me={me}
              t={t}
              locale={locale}
              onClose={() => setSearching(false)}
              onJump={(id) => {
                // Beside the chat on a wide screen the drawer stays; on a narrow one the chat comes back.
                if (!window.matchMedia("(min-width: 1280px)").matches) setSearching(false);
                jump(id);
              }}
            />
          ) : null}
          {info ? (
            <InfoPanel
              key={active.id}
              chat={active}
              detail={details[active.id]}
              me={me}
              contacts={contacts}
              t={t}
              locale={locale}
              onClose={() => setInfo(false)}
              onRename={(name) => groupCall(() => chatApi(`chats/${active.id}`, { method: "PATCH", body: { name } }))}
              onDescribe={(description) => groupCall(() => chatApi(`chats/${active.id}`, { method: "PATCH", body: { description } }))}
              onImage={(file) => {
                const form = new FormData();
                form.append("file", file);
                groupCall(async () => {
                  const row = await chatApi(`chats/${active.id}/image`, { method: "POST", form });
                  return row ? { ...row, image_v: Date.now() } : row;
                });
              }}
              onAdd={(ids) => groupCall(() => chatApi(`chats/${active.id}/members`, { method: "POST", body: { user_ids: ids } }))}
              onRole={(uid, role) => groupCall(() => chatApi(`chats/${active.id}/members/${uid}`, { method: "PATCH", body: { role } }))}
              onRemove={(uid) => groupCall(() => chatApi(`chats/${active.id}/members/${uid}`, { method: "DELETE" }))}
              onMenu={(k) => chatMenu(k)}
              onJump={(id) => { setInfo(false); jump(id); }}
              onOpenMedia={(f) => setViewer({ id: f.id, name: f.name })}
            />
          ) : null}
        </>
      ) : (
        <div className="hidden min-w-0 flex-1 flex-col items-center justify-center border-l border-[#d1d7db] bg-[#f0f2f5] px-10 text-center md:flex">
          <span className="flex size-36 items-center justify-center rounded-full bg-[#dff6ee] text-[#00a884]"><MessageCircle className="size-20" /></span>
          <h2 className="mt-8 text-[30px] font-light text-[#41525d]">{t("welcome_title")}</h2>
          <p className="mt-3 max-w-md text-[14px] leading-6 text-[#667781]">{t("welcome_text")}</p>
          <p className="mt-10 flex items-center gap-1.5 text-[13px] text-[#8696a0]"><Lock className="size-3.5" /> {t("welcome_lock")}</p>
        </div>
      )}

      {forward ? (
        <ForwardModal
          chats={chats}
          t={t}
          onClose={() => setForward(null)}
          onSend={async (ids) => {
            const m = forward;
            setForward(null);
            try {
              const d = await chatApi("messages/forward", { method: "POST", body: { message_id: m.id, chat_ids: ids } });
              toast.add({ title: t("forwarded_ok", { n: d.chat_ids.length }), type: "success" });
            } catch (e) {
              toast.add({ title: e.message, type: "danger" });
            }
          }}
        />
      ) : null}
      {msgInfo ? <InfoModal info={msgInfo.data} t={t} onClose={() => setMsgInfo(null)} /> : null}
      {viewer ? <ImageViewer item={viewer} t={t} onClose={() => setViewer(null)} /> : null}
      {deleting ? (
        <Modal open onOpenChange={(o) => !o && setDeleting(null)} title={t("delete_title")} className="max-w-sm">
          <div className="flex flex-col items-end gap-2">
            {deleting.user_id === me.id ? <Button type="button" variant="outline" size="sm" className="text-[#008069]" onClick={() => remove("everyone")}>{t("delete_all")}</Button> : null}
            <Button type="button" variant="outline" size="sm" className="text-[#008069]" onClick={() => remove("me")}>{t("delete_me")}</Button>
            <Button type="button" variant="outline" size="sm" onClick={() => setDeleting(null)}>{t("cancel")}</Button>
          </div>
        </Modal>
      ) : null}
      <ConfirmDialog
        open={!!confirm}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={confirm?.kind === "exit" ? t("exit_group") : t("clear")}
        description={confirm?.kind === "exit" ? t("exit_confirm", { name: confirm?.chat?.name ?? "" }) : t("clear_confirm")}
        confirmLabel={confirm?.kind === "exit" ? t("exit_group") : t("clear")}
        cancelLabel={t("cancel")}
        destructive
        onConfirm={confirmed}
      />
    </div>
  );
}

export { ChatApp };
