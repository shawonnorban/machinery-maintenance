"use client";

import { Fragment, useCallback, useEffect, useLayoutEffect, useRef, useState } from "react";
import { ArrowLeft, ChevronDown, FileText, Image as ImageIcon, Loader2, MoreVertical, Paperclip, Pencil, Pin, Search, SendHorizontal, Smile, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { ChatAvatar } from "./chat-avatar";
import { MentionPicker, mentionPeople, triggerAt, useRecordSearch } from "./chat-mentions";
import { ChatMessage, FloatMenu, MenuItem } from "./chat-message";
import { NAME_TONES, dayKey, dayLabel, fileKind, fileSize, lastSeen } from "./chat-lib";
import { EmojiPicker } from "./chat-emoji";

const MAX_FILE = 25 * 1024 * 1024;
/** WhatsApp's wallpaper: the beige with a faint doodle of dots. */
const WALLPAPER = {
  // An original doodle tile (public/chat-wallpaper.svg): chat things among garment-factory ones, faint on beige.
  backgroundColor: "#efeae2",
  backgroundImage: "url(/chat-wallpaper.svg)",
  backgroundSize: "360px 360px",
  backgroundRepeat: "repeat",
};

/** What is about to be sent: the picked photos / files, each with a caption. */
function AttachPreview({ files, onClose, onSend, t }) {
  const [caption, setCaption] = useState("");
  const [index, setIndex] = useState(0);
  const urls = useRef([]);
  const [shown, setShown] = useState([]);
  useEffect(() => {
    urls.current = files.map((f) => (f.type.startsWith("image/") ? URL.createObjectURL(f) : null));
    queueMicrotask(() => setShown(urls.current));
    return () => urls.current.forEach((u) => u && URL.revokeObjectURL(u));
  }, [files]);
  const f = files[Math.min(index, files.length - 1)];
  if (!f) return null;
  const src = shown[Math.min(index, files.length - 1)];
  return (
    <div className="absolute inset-0 z-30 flex flex-col bg-[#e9edef]">
      <div className="flex h-[60px] items-center gap-3 bg-[#008069] px-4 text-white">
        <button type="button" onClick={onClose} aria-label={t("cancel")} className="rounded-full p-1.5 hover:bg-white/10"><X className="size-5" /></button>
        <span className="truncate text-[15px] font-medium">{f.name}</span>
      </div>
      <div className="flex min-h-0 flex-1 items-center justify-center p-6">
        {src ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={src} alt={f.name} className="max-h-full max-w-full rounded object-contain shadow" />
        ) : (
          <div className="flex flex-col items-center gap-3 rounded-lg bg-white px-10 py-8 shadow">
            <span className="flex h-16 w-12 items-center justify-center rounded text-xs font-bold text-white" style={{ background: fileKind(f.name).color }}>{fileKind(f.name).label}</span>
            <span className="max-w-72 truncate text-[15px] text-[#111b21]">{f.name}</span>
            <span className="text-[13px] text-[#667781]">{fileSize(f.size)}</span>
          </div>
        )}
      </div>
      <div className="flex items-center gap-3 bg-[#f0f2f5] px-4 py-3">
        <input autoFocus value={caption} onChange={(e) => setCaption(e.target.value)} onKeyDown={(e) => e.key === "Enter" && onSend(caption)} placeholder={t("caption")} className="h-10 min-w-0 flex-1 rounded-lg bg-white px-3 text-[15px] outline-none" />
        <button type="button" onClick={() => onSend(caption)} aria-label={t("send")} className="flex size-12 items-center justify-center rounded-full bg-[#00a884] text-white shadow hover:bg-[#06cf9c]"><SendHorizontal className="size-5" /></button>
      </div>
      {files.length > 1 ? (
        <div className="flex justify-center gap-2 bg-[#f0f2f5] pb-3">
          {files.map((x, i) => (
            <button key={i} type="button" onClick={() => setIndex(i)} className={cn("flex size-14 items-center justify-center overflow-hidden rounded border-2 bg-white text-[10px] font-bold", i === index ? "border-[#00a884]" : "border-transparent")}>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              {shown[i] ? <img src={shown[i]} alt="" className="size-full object-cover" /> : fileKind(x.name).label}
            </button>
          ))}
        </div>
      ) : null}
    </div>
  );
}

/**
 * One open chat, WhatsApp style: the header (photo, name, online / last seen
 * / typing, or a group's members; info and the chat's menu), the pinned
 * message bar, the messages on the wallpaper with day pills and the unread
 * line (older ones load on scrolling up; a pinned or quoted message is jumped
 * to), and the composer — emoji, attach (photos / documents, also dropped or
 * pasted), reply and edit, Enter to send.
 */
function ChatConversation({ chat, detail, thread, me, people, typing, t, locale, socket, onBack, onInfo, onSend, onEditSave, onAction, onLoadOlder, onLoadNewer, onJump, jumpTo, onTyping, onMenu, onOpenImage, onSearch, compact = false, headerActions = null }) {
  const list = useRef(null);
  const input = useRef(null);
  const fileInput = useRef(null);
  const imageInput = useRef(null);
  const [text, setText] = useState("");
  const [reply, setReply] = useState(null);
  const [editing, setEditing] = useState(null);
  const [emoji, setEmoji] = useState(false);
  const [attach, setAttach] = useState(null);
  const [files, setFiles] = useState(null);
  const [menu, setMenu] = useState(null);
  const [drag, setDrag] = useState(false);
  const [atBottom, setAtBottom] = useState(true);
  const [highlight, setHighlight] = useState(null);
  // Mentions: the caret (where `@` / `#` is being typed), what was picked, the record kind, the row lit in the picker.
  const [caret, setCaret] = useState(0);
  const [picked, setPicked] = useState([]);
  const [recordType, setRecordType] = useState(null);
  const [pickIndex, setPickIndex] = useState(0);
  const [dismissed, setDismissed] = useState(null);
  const lastTyping = useRef(0);
  const content = useRef(null);
  const stick = useRef(true);
  const prevHeight = useRef(0);
  const firstOpen = useRef(true);
  const messages = thread?.messages ?? [];
  const lastId = messages[messages.length - 1]?.id;
  const firstUnread = thread?.unread_from ? messages.find((m) => m.id === thread.unread_from) : null;
  const unreadCount = firstUnread ? messages.filter((m) => m.id >= firstUnread.id && m.user_id !== me.id && m.type !== "SYSTEM").length : 0;
  const typingNames = Object.values(typing ?? {}).map((x) => x.name);
  const canWrite = !chat.left;

  // A chat opened: to the unread line, else the bottom; the composer takes the typing.
  useLayoutEffect(() => {
    firstOpen.current = true;
    stick.current = true;
    queueMicrotask(() => {
      setReply(null);
      setEditing(null);
      setText("");
      setEmoji(false);
      setPicked([]);
    });
  }, [chat.id]);
  useLayoutEffect(() => {
    const el = list.current;
    if (!el || !messages.length) return;
    if (firstOpen.current && thread?.loaded) {
      firstOpen.current = false;
      const line = firstUnread ? document.getElementById("unread-line") : null;
      if (line) line.scrollIntoView({ block: "center" });
      else el.scrollTop = el.scrollHeight;
      input.current?.focus();
      return;
    }
    if (prevHeight.current && el.dataset.older === "1") {
      el.scrollTop = el.scrollHeight - prevHeight.current;
      el.dataset.older = "0";
      prevHeight.current = 0;
    }
  }, [messages.length, thread?.loaded, firstUnread]);
  // A new last message: follow it when at the bottom (or when it is mine).
  useEffect(() => {
    const el = list.current;
    if (!el || !lastId) return;
    const mine = messages[messages.length - 1]?.user_id === me.id;
    if (atBottom || mine) el.scrollTo({ top: el.scrollHeight, behavior: "smooth" });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lastId]);
  // Jump to a message (a pinned one, a quote): scroll to it and light it up.
  useEffect(() => {
    if (!jumpTo) return;
    const el = document.getElementById(`msg-${jumpTo.id}`);
    if (!el) return;
    el.scrollIntoView({ block: "center", behavior: "smooth" });
    queueMicrotask(() => setHighlight(jumpTo.id));
    const id = setTimeout(() => setHighlight(null), 1800);
    return () => clearTimeout(id);
  }, [jumpTo, messages.length]);

  // Content that grows after it is drawn (a record's card, a photo) keeps the bottom in view when one was there.
  useEffect(() => {
    const el = list.current;
    if (!el || !content.current || typeof ResizeObserver === "undefined") return undefined;
    const watch = new ResizeObserver(() => {
      if (stick.current) el.scrollTop = el.scrollHeight;
    });
    watch.observe(content.current);
    return () => watch.disconnect();
  }, []);

  const onScroll = () => {
    const el = list.current;
    if (!el) return;
    const bottom = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
    stick.current = bottom;
    if (bottom !== atBottom) setAtBottom(bottom);
    if (el.scrollTop < 120 && thread?.has_older && !thread?.loading) {
      prevHeight.current = el.scrollHeight;
      el.dataset.older = "1";
      onLoadOlder();
    }
    if (bottom && thread?.has_newer && !thread?.loading) onLoadNewer();
  };

  const pick = (list) => {
    const ok = [...list].filter((f) => f.size <= MAX_FILE);
    if (ok.length < list.length) onAction("toast", null, t("file_too_big"));
    if (ok.length) setFiles(ok.slice(0, 10));
  };

  // What the text still names of the picked (and, on an edit, the message's own) mentions.
  const mentionsOf = (body, extra = null) => {
    const users = new Map();
    const refs = new Map();
    for (const u of [...(extra?.users ?? []), ...picked.filter((x) => x.user).map((x) => x.user)]) if (body.includes(`@${u.name}`)) users.set(u.id, { id: u.id, name: u.name });
    for (const r of [...(extra?.refs ?? []), ...picked.filter((x) => x.ref).map((x) => x.ref)]) if (body.includes(r.token)) refs.set(`${r.type}:${r.ref}`, { type: r.type, ref: r.ref, token: r.token });
    return users.size || refs.size ? { ...(users.size ? { users: [...users.values()] } : {}), ...(refs.size ? { refs: [...refs.values()] } : {}) } : null;
  };

  const send = () => {
    const body = text.trim();
    if (editing) {
      if (body) onEditSave(editing, body, mentionsOf(body, editing.meta?.mentions));
      setEditing(null);
      setText("");
      setPicked([]);
      return;
    }
    if (!body) return;
    onSend({ body, reply, mentions: mentionsOf(body) });
    setText("");
    setPicked([]);
    setReply(null);
    setEmoji(false);
    requestAnimationFrame(() => input.current?.focus());
  };

  // The `@…` / `#…` before the caret opens the picker (until Escape closes it for that one).
  const found = canWrite ? triggerAt(text, caret) : null;
  const trigger = found && found.start !== dismissed ? found : null;
  const mentionList = trigger?.kind === "@" ? mentionPeople(chat, detail, me, people, trigger.query) : [];
  const records = useRecordSearch(trigger?.kind === "#", recordType, trigger?.kind === "#" ? trigger.query : "");
  const pickItems = trigger?.kind === "@" ? mentionList : trigger?.kind === "#" ? records.results : [];
  const pickAt = Math.min(pickIndex, Math.max(0, pickItems.length - 1));
  const pickItem = (it) => {
    if (!trigger) return;
    const token = trigger.kind === "@" ? `@${it.name}` : it.token;
    const next = `${text.slice(0, trigger.start)}${token} ${text.slice(trigger.end)}`;
    const at = trigger.start + token.length + 1;
    setText(next);
    setCaret(at);
    setPickIndex(0);
    setPicked((all) => [...all, trigger.kind === "@" ? { user: { id: it.id, name: it.name } } : { ref: { type: it.type, ref: it.ref, token: it.token } }]);
    requestAnimationFrame(() => {
      input.current?.focus();
      input.current?.setSelectionRange(at, at);
    });
  };

  const typed = (v, at = v.length) => {
    setText(v);
    setCaret(at);
    setPickIndex(0);
    const now = Date.now();
    if (v && now - lastTyping.current > 3000) {
      lastTyping.current = now;
      onTyping();
    }
  };

  const messageAction = useCallback(
    (kind, m, extra) => {
      if (kind === "reply") {
        setReply(m);
        setEditing(null);
        input.current?.focus();
      } else if (kind === "edit") {
        setEditing(m);
        setReply(null);
        setText(m.body ?? "");
        setCaret((m.body ?? "").length);
        requestAnimationFrame(() => input.current?.focus());
      } else onAction(kind, m, extra);
    },
    [onAction],
  );

  const subtitle = typingNames.length
    ? chat.is_group
      ? t("typing_name", { name: typingNames.join(", ") })
      : t("typing")
    : chat.is_group
      ? (detail?.members ?? []).map((p) => (p.id === me.id ? t("you") : p.name.split(" ")[0])).join(", ") || t("group_hint")
      : lastSeen(detail?.other ?? chat.other, t, locale);

  const pinned = detail?.pinned_messages?.[0];

  return (
    <div
      className="chat-emoji relative flex min-h-0 min-w-0 flex-1 flex-col"
      onDragEnter={(e) => {
        if (canWrite && e.dataTransfer?.types?.includes("Files")) setDrag(true);
      }}
    >
      {/* Header */}
      <div className={cn("flex shrink-0 items-center bg-[#f0f2f5]", compact ? "h-[52px] gap-2 bg-[#008069] px-2.5 text-white" : "h-[60px] gap-3 border-l border-[#d1d7db] px-4")}>
        {!compact ? <button type="button" onClick={onBack} className="-ml-2 rounded-full p-1.5 text-[#54656f] hover:bg-black/5 md:hidden" aria-label="back"><ArrowLeft className="size-5" /></button> : null}
        <button type="button" onClick={onInfo} className={cn("flex min-w-0 flex-1 items-center text-left", compact ? "gap-2" : "gap-3")}>
          <ChatAvatar chat={chat} size={compact ? 34 : 40} />
          <span className="min-w-0 flex-1">
            <span className={cn("block truncate", compact ? "text-[14.5px] font-medium text-white" : "text-[16px] text-[#111b21]")}>{chat.name}</span>
            <span className={cn("block truncate", compact ? "text-[11.5px] text-white/80" : "text-[13px]", !compact && (typingNames.length ? "text-[#008069]" : "text-[#667781]"))}>{subtitle}</span>
          </span>
        </button>
        {onSearch ? <button type="button" onClick={onSearch} aria-label={t("search_messages")} title={t("search_messages")} className="rounded-full p-2 text-[#54656f] hover:bg-black/5"><Search className="size-5" /></button> : null}
        <button type="button" onClick={(e) => setMenu(e.currentTarget)} aria-label={t("menu")} className={cn("rounded-full hover:bg-black/5", compact ? "p-1.5 text-white hover:bg-white/15" : "p-2 text-[#54656f]")}><MoreVertical className={compact ? "size-[18px]" : "size-5"} /></button>
        {headerActions}
        {menu ? (
          <FloatMenu anchor={menu} onClose={() => setMenu(null)}>
            <MenuItem onClick={() => { setMenu(null); onInfo(); }}>{chat.is_group ? t("group_info") : t("contact_info")}</MenuItem>
            {onSearch ? <MenuItem onClick={() => { setMenu(null); onSearch(); }}>{t("search_messages")}</MenuItem> : null}
            <MenuItem onClick={() => { setMenu(null); onMenu("pin"); }}>{chat.pinned ? t("unpin_chat") : t("pin_chat")}</MenuItem>
            <MenuItem onClick={() => { setMenu(null); onMenu("mute"); }}>{chat.muted ? t("unmute") : t("mute")}</MenuItem>
            <MenuItem onClick={() => { setMenu(null); onMenu("archive"); }}>{chat.archived ? t("unarchive") : t("archive")}</MenuItem>
            <MenuItem onClick={() => { setMenu(null); onMenu("clear"); }}>{t("clear")}</MenuItem>
            {chat.is_group && !chat.left ? <MenuItem danger onClick={() => { setMenu(null); onMenu("exit"); }}>{t("exit_group")}</MenuItem> : null}
          </FloatMenu>
        ) : null}
      </div>

      {/* Pinned */}
      {pinned ? (
        <button type="button" onClick={() => onJump(pinned.id)} className="flex shrink-0 items-center gap-3 border-b border-l border-[#e9edef] bg-white px-4 py-2 text-left hover:bg-[#f5f6f6]">
          <Pin className="size-4 shrink-0 rotate-45 text-[#54656f]" />
          <span className="min-w-0 flex-1">
            <span className="block text-[12px] text-[#008069]">{t("pinned")}{detail.pinned_messages.length > 1 ? ` · ${detail.pinned_messages.length}` : ""}</span>
            <span className="block truncate text-[13.5px] text-[#111b21]">{pinned.body || (pinned.file?.image ? `📷 ${t("photo_msg")}` : `📄 ${pinned.file?.name ?? ""}`)}</span>
          </span>
        </button>
      ) : null}
      {socket === "unavailable" || socket === "failed" ? <div className="shrink-0 border-l border-[#e9edef] bg-[#fff8e1] px-4 py-1.5 text-[12.5px] text-[#8a6d00]">{t("offline")}</div> : null}

      {/* Messages */}
      <div ref={list} onScroll={onScroll} className="relative min-h-0 flex-1 overflow-x-hidden overflow-y-auto border-l border-[#d1d7db] pb-3" style={WALLPAPER}>
        <div ref={content} className="@container min-h-full">
        {thread?.loading && thread?.has_older ? <div className="flex justify-center py-3"><Loader2 className="size-5 animate-spin text-[#8696a0]" /></div> : null}
        {!thread?.loaded ? <div className="flex h-full items-center justify-center"><Loader2 className="size-6 animate-spin text-[#8696a0]" /></div> : null}
        {messages.map((m, i) => {
          const prev = messages[i - 1];
          const newDay = !prev || dayKey(prev.created_at) !== dayKey(m.created_at);
          const first = newDay || !prev || prev.user_id !== m.user_id || prev.type === "SYSTEM" || new Date(m.created_at) - new Date(prev.created_at) > 5 * 60000 || m.id === firstUnread?.id;
          return (
            <Fragment key={m.id}>
              {newDay ? (
                <div className="sticky top-1.5 z-10 my-2 flex justify-center">
                  <span className="rounded-lg bg-white px-3 py-1.5 text-[12.5px] text-[#54656f] uppercase shadow-[0_1px_0.5px_rgba(11,20,26,.13)]">{dayLabel(m.created_at, t, locale)}</span>
                </div>
              ) : null}
              {m.id === firstUnread?.id ? (
                <div id="unread-line" className="my-2 flex justify-center bg-white/40 py-1">
                  <span className="rounded-full bg-white px-4 py-1 text-[12.5px] text-[#54656f] shadow-sm">{t("unread_line", { n: unreadCount })}</span>
                </div>
              ) : null}
              <ChatMessage m={m} mine={m.user_id === me.id} group={chat.is_group} first={first} people={people} meId={me.id} t={t} locale={locale} highlight={highlight === m.id} onAction={messageAction} onJump={onJump} onOpenImage={onOpenImage} />
            </Fragment>
          );
        })}
        {typingNames.length ? (
          <div className="mt-2 flex px-[5%] @xl:px-[7%]">
            <span className="flex items-center gap-1 rounded-[7.5px] rounded-tl-none bg-white px-3 py-2.5 shadow-[0_1px_0.5px_rgba(11,20,26,.13)]">
              {[0, 1, 2].map((i) => (
                <span key={i} className="size-2 animate-bounce rounded-full bg-[#8696a0]" style={{ animationDelay: `${i * 0.15}s` }} />
              ))}
            </span>
          </div>
        ) : null}
        </div>
      </div>
      {!atBottom ? (
        <button type="button" onClick={() => (thread?.has_newer ? onJump(null) : list.current?.scrollTo({ top: list.current.scrollHeight, behavior: "smooth" }))} aria-label={t("jump_latest")} className="absolute right-5 bottom-24 z-20 flex size-11 items-center justify-center rounded-full bg-white text-[#54656f] shadow-md hover:bg-[#f5f6f6]">
          <ChevronDown className="size-6" />
        </button>
      ) : null}

      {/* Composer */}
      {emoji && canWrite ? <EmojiPicker t={t} className="shrink-0 border-l border-[#d1d7db]" height={compact ? "h-52" : "h-64"} onPick={(e) => { setText((x) => x + e); input.current?.focus(); }} /> : null}
      {canWrite ? (
        <div className="relative shrink-0 border-l border-[#d1d7db] bg-[#f0f2f5]">
          <MentionPicker
            trigger={trigger}
            people={mentionList}
            records={records}
            recordType={recordType}
            onRecordType={(k) => { setRecordType(k); setPickIndex(0); input.current?.focus(); }}
            index={pickAt}
            onHover={setPickIndex}
            onPick={pickItem}
            t={t}
          />
          {reply || editing ? (
            <div className="flex items-center gap-2 px-4 pt-2">
              <div className="flex min-w-0 flex-1 overflow-hidden rounded-lg border-l-4 bg-white/80" style={{ borderLeftColor: editing ? "#00a884" : NAME_TONES[(people[reply.user_id]?.tone ?? 5) % NAME_TONES.length] }}>
                <span className="min-w-0 flex-1 px-3 py-1.5">
                  <span className="flex items-center gap-1 text-[12.8px] font-semibold" style={{ color: editing ? "#00a884" : NAME_TONES[(people[reply?.user_id]?.tone ?? 5) % NAME_TONES.length] }}>
                    {editing ? <><Pencil className="size-3.5" /> {t("editing")}</> : reply.user_id === me.id ? t("you") : (people[reply.user_id]?.name ?? reply.user_name)}
                  </span>
                  <span className="block truncate text-[13px] text-[#667781]">{(editing ?? reply).body || (reply?.file?.image ? `📷 ${t("photo_msg")}` : `📄 ${reply?.file?.name ?? ""}`)}</span>
                </span>
              </div>
              <button type="button" onClick={() => { setReply(null); setEditing(null); if (editing) setText(""); }} aria-label={t("cancel")} className="rounded-full p-1.5 text-[#54656f] hover:bg-black/5"><X className="size-5" /></button>
            </div>
          ) : null}
          <div className="flex items-end gap-1 px-3 py-2.5">
            <button type="button" onClick={() => setEmoji((x) => !x)} aria-label={t("emoji")} className={cn("rounded-full p-2 hover:bg-black/5", emoji ? "text-[#00a884]" : "text-[#54656f]")}><Smile className="size-6" /></button>
            {!editing ? (
              <button type="button" onClick={(e) => setAttach(e.currentTarget)} aria-label={t("attach")} className="rounded-full p-2 text-[#54656f] hover:bg-black/5"><Paperclip className="size-[22px] -rotate-45" /></button>
            ) : null}
            {attach ? (
              <FloatMenu anchor={attach} onClose={() => setAttach(null)} align="left">
                <MenuItem icon={FileText} onClick={() => { setAttach(null); fileInput.current?.click(); }}><span className="text-[#7f66ff]">●</span> {t("document")}</MenuItem>
                <MenuItem icon={ImageIcon} onClick={() => { setAttach(null); imageInput.current?.click(); }}><span className="text-[#007bfc]">●</span> {t("photo")}</MenuItem>
              </FloatMenu>
            ) : null}
            <input ref={fileInput} type="file" multiple hidden onChange={(e) => { pick(e.target.files ?? []); e.target.value = ""; }} />
            <input ref={imageInput} type="file" accept="image/*" multiple hidden onChange={(e) => { pick(e.target.files ?? []); e.target.value = ""; }} />
            <textarea
              ref={input}
              rows={1}
              value={text}
              onChange={(e) => typed(e.target.value, e.target.selectionStart ?? e.target.value.length)}
              onSelect={(e) => setCaret(e.currentTarget.selectionStart ?? 0)}
              onKeyDown={(e) => {
                if (trigger && (pickItems.length || e.key === "Escape")) {
                  if (e.key === "ArrowDown" || e.key === "ArrowUp") {
                    e.preventDefault();
                    setPickIndex((pickAt + (e.key === "ArrowDown" ? 1 : pickItems.length - 1)) % pickItems.length);
                    return;
                  }
                  if ((e.key === "Enter" && !e.shiftKey) || e.key === "Tab") {
                    e.preventDefault();
                    pickItem(pickItems[pickAt]);
                    return;
                  }
                  if (e.key === "Escape") {
                    e.preventDefault();
                    setDismissed(trigger.start);
                    return;
                  }
                }
                if (e.key === "Enter" && !e.shiftKey) {
                  e.preventDefault();
                  send();
                } else if (e.key === "Escape") {
                  setReply(null);
                  if (editing) { setEditing(null); setText(""); }
                }
              }}
              onPaste={(e) => {
                const pasted = [...(e.clipboardData?.files ?? [])];
                if (pasted.length) {
                  e.preventDefault();
                  pick(pasted);
                }
              }}
              placeholder={t("composer")}
              className="max-h-32 min-h-[42px] min-w-0 flex-1 resize-none rounded-lg bg-white px-3 py-[10px] text-[15px] leading-[21px] text-[#111b21] outline-none placeholder:text-[#8696a0]"
              style={{ height: Math.min(128, 42 + Math.max(0, text.split("\n").length - 1) * 21) }}
            />
            <button type="button" onClick={send} disabled={!text.trim()} aria-label={t("send")} className={cn("rounded-full p-2 transition-colors", text.trim() ? "text-[#54656f] hover:bg-black/5" : "text-[#8696a0]/60")}>
              <SendHorizontal className="size-6" />
            </button>
          </div>
        </div>
      ) : (
        <div className="shrink-0 border-l border-[#d1d7db] bg-[#f0f2f5] px-4 py-4 text-center text-[14px] text-[#667781]">{t("left_note")}</div>
      )}

      {drag ? (
        <div
          className="absolute inset-0 z-40 flex items-center justify-center border-4 border-dashed border-[#00a884] bg-white/85 text-[18px] text-[#008069]"
          onDragOver={(e) => e.preventDefault()}
          onDragLeave={() => setDrag(false)}
          onDrop={(e) => {
            e.preventDefault();
            setDrag(false);
            pick(e.dataTransfer?.files ?? []);
          }}
        >
          {t("drop")}
        </div>
      ) : null}
      {files ? (
        <AttachPreview
          files={files}
          t={t}
          onClose={() => setFiles(null)}
          onSend={(caption) => {
            files.forEach((f, i) => onSend({ body: i === 0 ? caption : "", file: f, reply: i === 0 ? reply : null }));
            setFiles(null);
            setReply(null);
          }}
        />
      ) : null}
    </div>
  );
}

export { ChatConversation };
