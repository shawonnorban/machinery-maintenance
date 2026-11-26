"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useLocale } from "next-intl";
import { Maximize2, MessageCircle, Minus, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Modal } from "@/components/ui/modal";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { ChatAvatar } from "./chat-avatar";
import { ChatConversation } from "./chat-conversation";
import { Ticks } from "./chat-message";
import { ForwardModal, ImageViewer, InfoModal, InfoPanel, SearchBox } from "./chat-panels";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { CHAT_EVENT, CHAT_STATE, chatApi, fileUrl, listTime, previewText } from "./chat-lib";

/**
 * The floating chat (user 2026-11-23): on every screen but /chat itself, a
 * green button at the bottom right (with the unread count) opens the chat
 * list; a chat picked there opens as a small window docked along the bottom
 * — up to three side by side (one on a phone), each minimisable to its bar,
 * and "open in Chat" takes it to the full screen. The windows stay open
 * while moving between screens (and over a refresh, for the tab's life).
 * A window is the full conversation (replies, reactions, mentions, files).
 */
const DOCK = "annotech.chat.dock";
const KEEP = "annotech.chat.open";
const WIDTH = 400;
const BAR = 280;
const GAP = 12;

const sortChats = (list) => [...list].sort((a, b) => (b.pinned ? 1 : 0) - (a.pinned ? 1 : 0) || String(b.sort_at ?? "").localeCompare(String(a.sort_at ?? "")));
const merge = (list, incoming) => {
  const byId = new Map(list.filter((m) => !String(m.id).startsWith("tmp-")).map((m) => [m.id, m]));
  incoming.forEach((m) => byId.set(m.id, m));
  const temps = list.filter((m) => String(m.id).startsWith("tmp-"));
  return [...[...byId.values()].sort((a, b) => (a.id < b.id ? -1 : 1)), ...temps];
};
const readDock = () => {
  try {
    return JSON.parse(sessionStorage.getItem(DOCK) ?? "[]").filter((w) => w && typeof w.id === "string");
  } catch {
    return [];
  }
};

function ChatDock() {
  const pathname = usePathname();
  const router = useRouter();
  const t = useT("chat");
  const locale = useLocale();
  const toast = useToastManager();
  const [me, setMe] = useState(null);
  const [chats, setChats] = useState([]);
  const [contacts, setContacts] = useState(null);
  const [panel, setPanel] = useState(false);
  const [query, setQuery] = useState("");
  const [wins, setWins] = useState([]);
  const [socket, setSocket] = useState("initial");
  const [width, setWidth] = useState(1600);
  const live = useRef({ wins: [], chats: [], hidden: false });
  const timers = useRef({});
  const restored = useRef(false);
  const hidden = !pathname || pathname.startsWith("/chat");

  useEffect(() => {
    live.current = { wins, chats, hidden };
    // The topbar's toast stays quiet for a chat open here.
    window.__annotechChatDock = { shows: (id) => !hidden && wins.some((w) => w.id === id && !w.min) };
  });

  // The list and who I am, once; the open windows come back from this tab's storage.
  useEffect(() => {
    let alive = true;
    const size = () => setWidth(window.innerWidth);
    size();
    window.addEventListener("resize", size);
    chatApi("chats")
      .then((d) => {
        if (!alive || !d?.me) return;
        setMe(d.me);
        setChats(sortChats(d.chats ?? []));
        setWins(readDock().filter((w) => (d.chats ?? []).some((c) => c.id === w.id)));
        restored.current = true;
      })
      .catch(() => {});
    return () => {
      alive = false;
      window.removeEventListener("resize", size);
    };
  }, []);
  useEffect(() => {
    // Not before the stored windows came back (that first empty list would wipe them).
    if (!restored.current) return;
    try {
      sessionStorage.setItem(DOCK, JSON.stringify(wins));
    } catch {
      /* remembered only where storage works */
    }
  }, [wins]);

  const loadContacts = useCallback(async () => {
    try {
      setContacts(await chatApi("contacts"));
    } catch {
      setContacts([]);
    }
  }, []);
  useEffect(() => {
    if ((panel || wins.length) && contacts === null) queueMicrotask(loadContacts);
  }, [panel, wins.length, contacts, loadContacts]);

  const upsert = useCallback((row) => row && setChats((all) => sortChats([row, ...all.filter((c) => c.id !== row.id)])), []);
  const patch = useCallback((id, changes) => setChats((all) => sortChats(all.map((c) => (c.id === id ? { ...c, ...changes } : c)))), []);
  const refreshRow = useCallback(
    (id, delay = 0) => {
      clearTimeout(timers.current[id]);
      timers.current[id] = setTimeout(async () => {
        try {
          upsert(await chatApi(`chats/${id}/row`));
        } catch {
          /* not mine any more */
        }
      }, delay);
    },
    [upsert],
  );

  // The list follows the socket (the windows follow their own chat).
  useEffect(() => {
    const onState = (e) => setSocket(e.detail);
    const onEvent = (e) => {
      const { kind, chat_id: chatId, data } = e.detail ?? {};
      const { wins: open, chats: rows, hidden: away } = live.current;
      if (kind === "message") {
        const m = data.message;
        if (!rows.some((c) => c.id === chatId)) return refreshRow(chatId);
        const seen = !away && open.some((w) => w.id === chatId && !w.min) && document.visibilityState === "visible";
        setChats((all) =>
          sortChats(
            all.map((c) =>
              c.id === chatId
                ? {
                    ...c,
                    last: { id: m.id, user_id: m.user_id, user_name: m.user_name, type: m.type, deleted: m.deleted, body: m.body, meta: m.meta, file_name: m.file?.name, status: m.status, created_at: m.created_at },
                    sort_at: m.created_at,
                    unread: m.user_id !== live.current.meId && m.type !== "SYSTEM" && !seen ? c.unread + 1 : c.unread,
                    mentioned: c.mentioned || (!seen && (m.meta?.mentions?.users ?? []).some((u) => u.id === live.current.meId)),
                  }
                : c,
            ),
          ),
        );
        if (m.type === "SYSTEM") refreshRow(chatId, 200);
      } else if (kind === "chat" || kind === "read") refreshRow(chatId, kind === "read" ? 800 : 100);
    };
    window.addEventListener(CHAT_EVENT, onEvent);
    window.addEventListener(CHAT_STATE, onState);
    return () => {
      window.removeEventListener(CHAT_EVENT, onEvent);
      window.removeEventListener(CHAT_STATE, onState);
    };
  }, [refreshRow]);
  useEffect(() => {
    live.current.meId = me?.id;
  });

  const maxWins = width < 640 ? 1 : Math.min(3, Math.max(1, Math.floor((width - 110) / (WIDTH + GAP))));
  const openChat = useCallback(
    (id) => {
      setWins((all) => [{ id, min: false }, ...all.filter((w) => w.id !== id)].slice(0, maxWins));
      setPanel(false);
    },
    [maxWins],
  );
  const startDirect = useCallback(
    async (person) => {
      try {
        const row = await chatApi("chats/direct", { method: "POST", body: { user_id: person.id } });
        upsert(row);
        setQuery("");
        openChat(row.id);
      } catch (e) {
        toast.add({ title: e.message, type: "danger" });
      }
    },
    [upsert, openChat, toast],
  );
  const expand = useCallback(
    (id) => {
      try {
        sessionStorage.setItem(KEEP, id);
      } catch {
        /* the chat screen opens on its list then */
      }
      setPanel(false);
      router.push("/chat");
    },
    [router],
  );

  const people = useMemo(() => {
    if (!me) return {};
    const out = { [me.id]: me };
    (contacts ?? []).forEach((p) => (out[p.id] = p));
    chats.forEach((c) => c.other && (out[c.other.id] ??= c.other));
    return out;
  }, [me, contacts, chats]);

  if (hidden || !me) return null;
  const total = chats.reduce((a, c) => a + (c.muted ? 0 : c.unread), 0);
  const phone = width < 640;

  return (
    <>
      {wins.map((w, i) => {
        const chat = chats.find((c) => c.id === w.id);
        if (!chat) return null;
        // Side by side from the launcher leftwards, each taking only its own width (a minimised bar is narrower).
        const right = (phone ? 84 : 92) + wins.slice(0, i).reduce((sum, x) => sum + (x.min ? BAR : WIDTH) + GAP, 0);
        return (
          <div key={w.id} className={cn("fixed z-40", phone && !w.min ? "inset-x-0 top-16 bottom-10" : "bottom-10")} style={phone && !w.min ? undefined : { right }}>
            <MiniChat
              chat={chat}
              me={me}
              people={people}
              contacts={contacts}
              chats={chats}
              minimized={w.min}
              phone={phone}
              socket={socket}
              t={t}
              locale={locale}
              onMinimize={(min) => setWins((all) => all.map((x) => (x.id === w.id ? { ...x, min } : x)))}
              onClose={() => setWins((all) => all.filter((x) => x.id !== w.id))}
              onExpand={() => expand(w.id)}
              onPatch={patch}
              onRow={upsert}
              onPerson={startDirect}
            />
          </div>
        );
      })}

      {panel ? (
        <DockList
          chats={chats}
          contacts={contacts}
          me={me}
          people={people}
          query={query}
          setQuery={setQuery}
          t={t}
          locale={locale}
          phone={phone}
          onOpen={openChat}
          onPerson={startDirect}
          onClose={() => setPanel(false)}
          onFull={() => {
            setPanel(false);
            router.push("/chat");
          }}
        />
      ) : null}

      <DraggableLauncher onClick={() => setPanel((p) => !p)} label={t("title")}>
        {panel ? <X className="size-6" /> : <MessageCircle className="size-7" />}
        {total && !panel ? <span className="absolute -top-1 -right-1 flex h-6 min-w-6 items-center justify-center rounded-full border-2 border-white bg-[#e53935] px-1 text-[12px] font-bold">{total > 99 ? "99+" : total}</span> : null}
      </DraggableLauncher>
    </>
  );
}

const LAUNCHER_POS = "chat.launcher.pos.v1";
const LAUNCHER_SIZE = 56;

/**
 * The chat bubble, which can be dragged anywhere on the screen (user, 2026-11-25: it covered
 * buttons). Mouse or touch; it stays inside the window and keeps its place in this browser. A
 * press that does not move is a click. The position is set on the element itself, never in React
 * state, so the server render and the first browser render stay the same.
 */
function DraggableLauncher({ onClick, label, children }) {
  const ref = useRef(null);
  const drag = useRef(null);

  const place = (right, bottom) => {
    const el = ref.current;
    if (!el) return null;
    const r = Math.min(Math.max(8, right), window.innerWidth - LAUNCHER_SIZE - 8);
    const b = Math.min(Math.max(8, bottom), window.innerHeight - LAUNCHER_SIZE - 8);
    el.style.right = `${r}px`;
    el.style.bottom = `${b}px`;
    return { right: r, bottom: b };
  };

  useEffect(() => {
    try {
      const saved = JSON.parse(localStorage.getItem(LAUNCHER_POS) ?? "null");
      if (saved) place(saved.right, saved.bottom);
    } catch {
      /* storage blocked: the default corner */
    }
    const keepInside = () => {
      const el = ref.current;
      if (el) place(parseFloat(el.style.right) || 20, parseFloat(el.style.bottom) || 56);
    };
    window.addEventListener("resize", keepInside);
    return () => window.removeEventListener("resize", keepInside);
  }, []);

  const down = (e) => {
    const el = ref.current;
    const rect = el.getBoundingClientRect();
    drag.current = { x: e.clientX, y: e.clientY, right: window.innerWidth - rect.right, bottom: window.innerHeight - rect.bottom, moved: false };
    el.setPointerCapture(e.pointerId);
  };
  const move = (e) => {
    const d = drag.current;
    if (!d) return;
    const dx = e.clientX - d.x;
    const dy = e.clientY - d.y;
    if (!d.moved && Math.hypot(dx, dy) < 6) return;
    d.moved = true;
    d.last = place(d.right - dx, d.bottom - dy);
  };
  const up = (e) => {
    const d = drag.current;
    drag.current = null;
    ref.current?.releasePointerCapture?.(e.pointerId);
    if (!d) return;
    if (d.moved) {
      try {
        if (d.last) localStorage.setItem(LAUNCHER_POS, JSON.stringify(d.last));
      } catch {
        /* storage blocked: it stays where it was put until the page reloads */
      }
      return;
    }
    onClick();
  };

  return (
    <button
      ref={ref}
      type="button"
      data-chat-launcher
      onPointerDown={down}
      onPointerMove={move}
      onPointerUp={up}
      onPointerCancel={() => (drag.current = null)}
      onKeyDown={(e) => (e.key === "Enter" || e.key === " ") && (e.preventDefault(), onClick())}
      aria-label={label}
      title={label}
      style={{ right: 20, bottom: 56, touchAction: "none" }}
      className="fixed z-40 flex size-14 cursor-grab items-center justify-center rounded-full bg-[#00a884] text-white shadow-[0_4px_14px_rgba(0,0,0,.25)] transition-transform select-none hover:scale-105 hover:bg-[#06cf9c] active:cursor-grabbing"
    >
      {children}
    </button>
  );
}

/** The list over the launcher: search, the chats, people to start one with, and the way to the full screen. */
function DockList({ chats, contacts, me, people, query, setQuery, t, locale, phone, onOpen, onPerson, onClose, onFull }) {
  const q = query.trim().toLowerCase();
  const shown = chats.filter((c) => (q ? (c.name ?? "").toLowerCase().includes(q) : !c.archived)).slice(0, 60);
  const direct = new Set(chats.filter((c) => !c.is_group && c.other).map((c) => c.other.id));
  const matched = q ? (contacts ?? []).filter((p) => !direct.has(p.id) && `${p.name} ${p.about ?? ""}`.toLowerCase().includes(q)).slice(0, 8) : [];
  return (
    <div data-chat-dock-list className={cn("chat-emoji fixed z-40 flex flex-col overflow-hidden bg-white shadow-[0_6px_28px_rgba(11,20,26,.28)]", phone ? "inset-x-0 top-16 bottom-10" : "right-5 bottom-[128px] h-[min(600px,calc(100vh-200px))] w-[400px] rounded-xl")}>
      <div className="flex h-[54px] shrink-0 items-center gap-2 bg-[#008069] px-4 text-white">
        <MessageCircle className="size-5" />
        <span className="flex-1 text-[16px] font-medium">{t("title")}</span>
        <button type="button" onClick={onFull} aria-label={t("open_full")} title={t("open_full")} className="rounded-full p-1.5 hover:bg-white/15"><Maximize2 className="size-[18px]" /></button>
        <button type="button" onClick={onClose} aria-label={t("close")} title={t("close")} className="rounded-full p-1.5 hover:bg-white/15"><X className="size-5" /></button>
      </div>
      <SearchBox value={query} onChange={setQuery} placeholder={t("search")} autoFocus={!phone} />
      <div className="min-h-0 flex-1 overflow-y-auto">
        {shown.map((c) => {
          const mine = c.last && c.last.user_id === me.id && c.last.type !== "SYSTEM" && !c.last.deleted;
          return (
            <button key={c.id} type="button" data-chat-row={c.name} onClick={() => onOpen(c.id)} className="flex w-full items-center gap-3 pl-3 text-left hover:bg-[#f5f6f6]">
              <span className="py-2.5"><ChatAvatar chat={c} size={44} online={!c.is_group && c.other?.online} /></span>
              <span className="flex min-w-0 flex-1 flex-col justify-center self-stretch border-b border-[#e9edef] py-2 pr-3">
                <span className="flex items-baseline gap-2">
                  <span className="min-w-0 flex-1 truncate text-[15.5px] text-[#111b21]">{c.name}</span>
                  <span className={cn("shrink-0 text-[11.5px]", c.unread && !c.muted ? "font-medium text-[#1fa855]" : "text-[#667781]")}>{listTime(c.last?.created_at ?? c.sort_at, t, locale)}</span>
                </span>
                <span className="mt-0.5 flex items-center gap-1.5 text-[13px] text-[#667781]">
                  <span className="flex min-w-0 flex-1 items-center gap-1">
                    {mine ? <Ticks status={c.last.status} className="shrink-0" /> : null}
                    <span className="truncate">{previewText(c.last, c, people, me.id, t)}</span>
                  </span>
                  {c.mentioned && c.unread ? <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-[#25d366] text-[11px] font-bold text-white">@</span> : null}
                  {c.unread ? <span className={cn("flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full px-1.5 text-[11px] font-medium text-white", c.muted ? "bg-[#8696a0]" : "bg-[#25d366]")}>{c.unread}</span> : null}
                </span>
              </span>
            </button>
          );
        })}
        {matched.length ? <p className="px-5 pt-4 pb-1.5 text-[14px] text-[#008069]">{t("people")}</p> : null}
        {matched.map((p) => (
          <button key={p.id} type="button" onClick={() => onPerson(p)} className="flex w-full items-center gap-3 pl-3 text-left hover:bg-[#f5f6f6]">
            <span className="py-2"><ChatAvatar person={p} size={40} online={p.online} /></span>
            <span className="min-w-0 flex-1 self-stretch border-b border-[#e9edef] py-2.5 pr-3">
              <span className="block truncate text-[15px] text-[#111b21]">{p.name}</span>
              <span className="block truncate text-[12.5px] text-[#667781]">{p.about ?? p.email}</span>
            </span>
          </button>
        ))}
        {!shown.length && !matched.length ? <p className="px-8 py-10 text-center text-[13.5px] text-[#667781]">{q ? t("no_match") : t("empty_list")}</p> : null}
      </div>
    </div>
  );
}

/** One docked chat: its bar when minimised, else the whole conversation in a small window. */
function MiniChat({ chat, me, people, contacts, chats, minimized, phone, socket, t, locale, onMinimize, onClose, onExpand, onPatch, onRow, onPerson }) {
  const toast = useToastManager();
  const id = chat.id;
  const [thread, setThread] = useState({ messages: [], loaded: false });
  const [detail, setDetail] = useState(null);
  const [typing, setTyping] = useState({});
  const [jumpTo, setJumpTo] = useState(null);
  const [forward, setForward] = useState(null);
  const [msgInfo, setMsgInfo] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [viewer, setViewer] = useState(null);
  const [info, setInfo] = useState(false);
  const [confirm, setConfirm] = useState(null);
  const live = useRef({ thread, minimized });
  const readTimer = useRef(null);
  const words = useRef({ t, toast });
  useEffect(() => {
    live.current = { thread, minimized };
    words.current = { t, toast };
  });

  const loadDetail = useCallback(async () => {
    try {
      setDetail(await chatApi(`chats/${id}`));
    } catch {
      /* not mine any more */
    }
  }, [id]);
  const load = useCallback(
    async (params = "") => {
      setThread((th) => ({ ...th, loading: true }));
      try {
        const d = await chatApi(`chats/${id}/messages${params}`);
        setThread((th) => {
          if (params.startsWith("?before")) return { ...th, messages: merge(th.messages, d.messages), has_older: d.has_older, loading: false };
          if (params.startsWith("?after")) return { ...th, messages: merge(th.messages, d.messages), has_newer: d.has_newer, loading: false };
          if (params.startsWith("?around")) return { ...th, messages: d.messages, has_older: d.has_older, has_newer: d.has_newer, loading: false, loaded: true };
          const unreadFrom = th.loaded ? th.unread_from : (d.messages.find((m) => m.user_id !== me.id && m.type !== "SYSTEM" && (d.read_upto === null || m.id > d.read_upto))?.id ?? null);
          return { messages: d.messages, has_older: d.has_older, has_newer: false, loading: false, loaded: true, unread_from: unreadFrom };
        });
        if (!params) onPatch(id, { unread: 0, mentioned: false });
      } catch (e) {
        setThread((th) => ({ ...th, loading: false, loaded: true }));
        words.current.toast.add({ title: e.message, type: "danger" });
      }
    },
    [id, me.id, onPatch],
  );
  const markRead = useCallback(() => {
    clearTimeout(readTimer.current);
    readTimer.current = setTimeout(() => {
      chatApi(`chats/${id}/read`, { method: "POST" }).catch(() => {});
      onPatch(id, { unread: 0, mentioned: false });
    }, 400);
  }, [id, onPatch]);

  // Opened (or brought up from its bar): read it; the people and pins once.
  useEffect(() => {
    if (minimized) return;
    if (!live.current.thread.loaded) queueMicrotask(() => load());
    else markRead();
  }, [minimized, load, markRead]);
  useEffect(() => {
    queueMicrotask(loadDetail);
  }, [loadDetail]);

  useEffect(() => {
    const onEvent = (e) => {
      const { kind, chat_id: chatId, data } = e.detail ?? {};
      if (chatId !== id) return;
      const { thread: th, minimized: min } = live.current;
      if (kind === "message") {
        const m = data.message;
        if (th.loaded) setThread((x) => ({ ...x, messages: merge(x.messages, [m]) }));
        setTyping((all) => {
          if (!all[m.user_id]) return all;
          const next = { ...all };
          delete next[m.user_id];
          return next;
        });
        if (!min && th.loaded && m.user_id !== me.id && document.visibilityState === "visible") markRead();
        if (m.type === "SYSTEM") loadDetail();
      } else if (kind === "update") {
        const m = data.message;
        setThread((x) => ({ ...x, messages: x.messages.map((y) => (y.id === m.id ? m : y)) }));
      } else if (kind === "read" || kind === "delivered") {
        const level = kind;
        const rank = { pending: 0, sent: 1, delivered: 2, read: 3 };
        if (!chat.is_group) setThread((x) => ({ ...x, messages: x.messages.map((m) => (m.user_id === me.id && m.status && m.id <= data.upto && rank[m.status] < rank[level] ? { ...m, status: level } : m)) }));
        else if (th.loaded) {
          const from = th.messages.find((m) => m.user_id === me.id && m.status && m.status !== "read" && !String(m.id).startsWith("tmp-"));
          if (from) {
            chatApi(`chats/${id}/messages?after=${encodeURIComponent(from.id)}`)
              .then((d) => {
                const map = new Map(d.messages.map((m) => [m.id, m.status]));
                setThread((x) => ({ ...x, messages: x.messages.map((m) => (map.has(m.id) ? { ...m, status: map.get(m.id) } : m)) }));
              })
              .catch(() => {});
          }
        }
      } else if (kind === "typing") {
        const until = Date.now() + 4500;
        setTyping((all) => ({ ...all, [data.user_id]: { name: data.name, until } }));
        setTimeout(() => setTyping((all) => {
          if (!all[data.user_id] || all[data.user_id].until > Date.now()) return all;
          const next = { ...all };
          delete next[data.user_id];
          return next;
        }), 4700);
      } else if (kind === "pins" || kind === "chat") loadDetail();
    };
    window.addEventListener(CHAT_EVENT, onEvent);
    const onVisible = () => document.visibilityState === "visible" && !live.current.minimized && live.current.thread.loaded && markRead();
    document.addEventListener("visibilitychange", onVisible);
    return () => {
      window.removeEventListener(CHAT_EVENT, onEvent);
      document.removeEventListener("visibilitychange", onVisible);
    };
  }, [id, me.id, chat.is_group, markRead, loadDetail]);

  const replace = (m) => setThread((x) => ({ ...x, messages: x.messages.map((y) => (y.id === m.id ? m : y)) }));

  const send = async ({ body, file, reply, mentions = null }) => {
    const tmp = `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
    const localUrl = file && file.type.startsWith("image/") ? URL.createObjectURL(file) : null;
    const draft = {
      id: tmp, chat_id: id, user_id: me.id, user_name: me.name, type: file ? (localUrl ? "IMAGE" : "FILE") : "TEXT", body: body || null,
      file: file ? { name: file.name, size: file.size, mime: file.type, image: !!localUrl } : null, local_url: localUrl,
      reply: reply ? { id: reply.id, user_id: reply.user_id, type: reply.type, body: reply.body, file_name: reply.file?.name, image: reply.file?.image } : null,
      reactions: [], status: "pending", created_at: new Date().toISOString(), meta: mentions ? { mentions } : null,
    };
    setThread((x) => ({ ...x, messages: [...x.messages, draft] }));
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
      setThread((x) => ({ ...x, messages: merge(x.messages.filter((y) => y.id !== tmp), [m]) }));
      onPatch(id, { last: { ...m, file_name: m.file?.name }, sort_at: m.created_at, archived: false });
    } catch (e) {
      setThread((x) => ({ ...x, messages: x.messages.map((y) => (y.id === tmp ? { ...y, status: "failed" } : y)) }));
      toast.add({ title: t("send_failed"), description: e.message, type: "danger" });
    } finally {
      if (localUrl) setTimeout(() => URL.revokeObjectURL(localUrl), 60000);
    }
  };

  const onAction = async (kind, m, extra) => {
    try {
      if (kind === "toast") toast.add({ title: extra, type: "warning" });
      else if (kind === "mention-user") await onPerson(extra);
      else if (kind === "copy") {
        await navigator.clipboard?.writeText(m.body ?? "");
        toast.add({ title: t("copied"), type: "info" });
      } else if (kind === "react") replace(await chatApi(`messages/${m.id}/react`, { method: "POST", body: { emoji: extra } }));
      else if (kind === "pin") {
        await chatApi(`messages/${m.id}/pin`, { method: "POST" });
        loadDetail();
      } else if (kind === "forward") setForward(m);
      else if (kind === "info") {
        setMsgInfo({ id: m.id, data: null });
        setMsgInfo({ id: m.id, data: await chatApi(`messages/${m.id}/info`) });
      } else if (kind === "delete") setDeleting(m);
      else if (kind === "download") window.location.href = fileUrl(m.id, true);
    } catch (e) {
      toast.add({ title: e.message, type: "danger" });
    }
  };

  const remove = async (scope) => {
    const m = deleting;
    setDeleting(null);
    try {
      await chatApi(`messages/${m.id}?scope=${scope}`, { method: "DELETE" });
      if (scope === "me") setThread((x) => ({ ...x, messages: x.messages.filter((y) => y.id !== m.id) }));
      else replace({ ...m, deleted: true, body: null, file: null, reactions: [] });
    } catch (e) {
      toast.add({ title: e.message, type: "danger" });
    }
  };

  const jump = async (messageId) => {
    if (messageId === null) return load();
    if (!thread.messages.some((m) => m.id === messageId)) await load(`?around=${encodeURIComponent(messageId)}`);
    setJumpTo((j) => ({ id: messageId, at: (j?.at ?? 0) + 1 }));
  };

  const menu = async (kind) => {
    try {
      if (kind === "pin") onRow(await chatApi(`chats/${id}/settings`, { method: "PATCH", body: { pinned: !chat.pinned } }));
      else if (kind === "mute") onRow(await chatApi(`chats/${id}/settings`, { method: "PATCH", body: { muted: !chat.muted } }));
      else if (kind === "archive") {
        onRow(await chatApi(`chats/${id}/settings`, { method: "PATCH", body: { archived: !chat.archived } }));
        if (!chat.archived) onClose();
      } else if (kind === "clear" || kind === "exit") setConfirm(kind);
    } catch (e) {
      toast.add({ title: e.message, type: "danger" });
    }
  };

  const confirmed = async () => {
    const kind = confirm;
    setConfirm(null);
    try {
      if (kind === "clear") {
        onRow(await chatApi(`chats/${id}/clear`, { method: "POST" }));
        load();
      } else if (kind === "exit") {
        await chatApi(`chats/${id}/members/${me.id}`, { method: "DELETE" });
        onRow(await chatApi(`chats/${id}/row`));
        loadDetail();
        load();
      }
    } catch (e) {
      toast.add({ title: e.message, type: "danger" });
    }
  };

  // The info drawer's changes (name, photo, people, roles): the row and the people again.
  const groupCall = async (fn) => {
    try {
      const row = await fn();
      if (row?.id) onRow(row);
      loadDetail();
    } catch (e) {
      toast.add({ title: e.message, type: "danger" });
    }
  };

  // The people of this chat too (a group's members), for names, colours and @.
  const everyone = useMemo(() => {
    const out = { ...people };
    (detail?.members ?? []).forEach((p) => (out[p.id] = { ...out[p.id], ...p }));
    return out;
  }, [people, detail]);

  if (minimized) {
    return (
      <div data-chat-window={id} style={{ width: BAR }} className="flex h-12 cursor-pointer items-center gap-2 rounded-t-lg bg-[#008069] pr-1.5 pl-2.5 text-white shadow-[0_-2px_14px_rgba(11,20,26,.25)]" onClick={() => onMinimize(false)}>
        <ChatAvatar chat={chat} size={30} />
        <span className="min-w-0 flex-1 truncate text-[14px] font-medium">{chat.name}</span>
        {chat.unread ? <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-[#25d366] px-1.5 text-[11px] font-bold">{chat.unread}</span> : null}
        <button type="button" onClick={(e) => { e.stopPropagation(); onClose(); }} aria-label={t("close")} title={t("close")} className="rounded-full p-1 hover:bg-white/15"><X className="size-4" /></button>
      </div>
    );
  }

  const actions = (
    <>
      <button type="button" onClick={() => onMinimize(true)} aria-label={t("minimize")} title={t("minimize")} className="rounded-full p-1.5 text-white hover:bg-white/15"><Minus className="size-[18px]" /></button>
      {!phone ? <button type="button" onClick={onExpand} aria-label={t("open_full")} title={t("open_full")} className="rounded-full p-1.5 text-white hover:bg-white/15"><Maximize2 className="size-4" /></button> : null}
      <button type="button" onClick={onClose} aria-label={t("close")} title={t("close")} className="rounded-full p-1.5 text-white hover:bg-white/15"><X className="size-[18px]" /></button>
    </>
  );

  return (
    <div data-chat-window={id} className={cn("relative flex flex-col overflow-hidden bg-[#efeae2] shadow-[0_-2px_24px_rgba(11,20,26,.3)]", phone ? "h-full w-full" : "h-[min(600px,calc(100vh-130px))] rounded-t-xl")} style={phone ? undefined : { width: WIDTH }}>
      <ChatConversation
        compact
        headerActions={actions}
        chat={chat}
        detail={detail}
        thread={thread}
        me={me}
        people={everyone}
        typing={typing}
        t={t}
        locale={locale}
        socket={socket}
        jumpTo={jumpTo}
        onBack={() => onMinimize(true)}
        onInfo={() => setInfo(true)}
        onSend={send}
        onEditSave={async (m, body, mentions = null) => {
          try {
            replace(await chatApi(`messages/${m.id}`, { method: "PATCH", body: { body, mentions } }));
          } catch (e) {
            toast.add({ title: e.message, type: "danger" });
          }
        }}
        onAction={onAction}
        onLoadOlder={() => {
          const first = thread.messages.find((m) => !String(m.id).startsWith("tmp-"));
          if (first) load(`?before=${encodeURIComponent(first.id)}`);
        }}
        onLoadNewer={() => {
          const real = thread.messages.filter((m) => !String(m.id).startsWith("tmp-"));
          if (real.length) load(`?after=${encodeURIComponent(real[real.length - 1].id)}`);
        }}
        onJump={jump}
        onTyping={() => chatApi(`chats/${id}/typing`, { method: "POST" }).catch(() => {})}
        onMenu={menu}
        onOpenImage={(m) => setViewer({ id: m.id, name: m.file?.name })}
      />
      {info ? (
        <div className="absolute inset-0 z-30 flex">
          <InfoPanel
            key={id}
            className="w-full border-l-0"
            chat={chat}
            detail={detail}
            me={me}
            contacts={contacts}
            t={t}
            locale={locale}
            onClose={() => setInfo(false)}
            onRename={(name) => groupCall(() => chatApi(`chats/${id}`, { method: "PATCH", body: { name } }))}
            onDescribe={(description) => groupCall(() => chatApi(`chats/${id}`, { method: "PATCH", body: { description } }))}
            onImage={(file) => {
              const form = new FormData();
              form.append("file", file);
              groupCall(async () => {
                const row = await chatApi(`chats/${id}/image`, { method: "POST", form });
                return row ? { ...row, image_v: Date.now() } : row;
              });
            }}
            onAdd={(ids) => groupCall(() => chatApi(`chats/${id}/members`, { method: "POST", body: { user_ids: ids } }))}
            onRole={(uid, role) => groupCall(() => chatApi(`chats/${id}/members/${uid}`, { method: "PATCH", body: { role } }))}
            onRemove={(uid) => groupCall(() => chatApi(`chats/${id}/members/${uid}`, { method: "DELETE" }))}
            onMenu={menu}
            onJump={(mid) => {
              setInfo(false);
              jump(mid);
            }}
            onOpenMedia={(f) => setViewer({ id: f.id, name: f.name })}
          />
        </div>
      ) : null}
      <ConfirmDialog
        open={!!confirm}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={confirm === "exit" ? t("exit_group") : t("clear")}
        description={confirm === "exit" ? t("exit_confirm", { name: chat.name ?? "" }) : t("clear_confirm")}
        confirmLabel={confirm === "exit" ? t("exit_group") : t("clear")}
        cancelLabel={t("cancel")}
        destructive
        onConfirm={confirmed}
      />
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
    </div>
  );
}

export { ChatDock };
