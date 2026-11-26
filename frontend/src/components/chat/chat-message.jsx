"use client";

import { memo, useEffect, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { Ban, Check, CheckCheck, ChevronDown, Clock, Copy, Download, Forward, Info, Pencil, Pin, PinOff, Plus, Reply, SmilePlus, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { ChatAvatar } from "./chat-avatar";
import { RecordCards, RichText } from "./chat-mentions";
import { EmojiPicker } from "./chat-emoji";
import { NAME_TONES, QUICK_REACTIONS, clock, fileKind, fileSize, fileUrl, systemText } from "./chat-lib";

/** The ticks of one's own message: a clock while sending, ✓ sent, ✓✓ delivered, blue ✓✓ read. */
function Ticks({ status, className }) {
  if (status === "pending") return <Clock className={cn("size-3.5 text-[#8696a0]", className)} />;
  if (status === "failed") return <span className={cn("text-[11px] font-bold text-danger", className)}>!</span>;
  if (status === "read") return <CheckCheck className={cn("size-4 text-[#53bdeb]", className)} />;
  if (status === "delivered") return <CheckCheck className={cn("size-4 text-[#8696a0]", className)} />;
  if (status === "sent") return <Check className={cn("size-4 text-[#8696a0]", className)} />;
  return null;
}

/** The quoted message inside a reply bubble (click: go to it). */
function Quote({ reply, people, meId, t, onJump, mine }) {
  if (!reply) return null;
  const who = reply.user_id === meId ? t("you") : (people[reply.user_id]?.name ?? "—");
  const tone = NAME_TONES[(people[reply.user_id]?.tone ?? 5) % NAME_TONES.length];
  const text = reply.deleted ? t("deleted") : reply.body || (reply.image ? `📷 ${t("photo_msg")}` : reply.file_name ? `📄 ${reply.file_name}` : "");
  return (
    <button type="button" onClick={() => onJump(reply.id)} className={cn("mb-1 flex w-full overflow-hidden rounded-md border-l-4 text-left", mine ? "bg-[#d1f4cc]" : "bg-[#f5f6f6]")} style={{ borderLeftColor: tone }}>
      <span className="min-w-0 flex-1 px-2 py-1">
        <span className="block truncate text-[12.8px] font-semibold" style={{ color: tone }}>{who}</span>
        <span className="line-clamp-2 text-[13px] text-[#667781]">{text}</span>
      </span>
    </button>
  );
}

/** A small floating menu anchored to a button (portalled, so the scrolling list never cuts it). */
function FloatMenu({ anchor, onClose, children, align = "right" }) {
  const box = useRef(null);
  // Placed once, from where the button stood when it opened the menu.
  const [pos] = useState(() => {
    // A button not drawn (shown only on hover) has no box: the message it belongs to stands in.
    let r = anchor.getBoundingClientRect();
    if (!r.width && !r.height) r = (anchor.closest("[id^=msg-]") ?? anchor.parentElement ?? anchor).getBoundingClientRect();
    const below = window.innerHeight - r.bottom;
    const up = below < 320 && r.top > below;
    return { top: up ? undefined : r.bottom + 4, bottom: up ? window.innerHeight - r.top + 4 : undefined, left: align === "right" ? undefined : r.left, right: align === "right" ? window.innerWidth - r.right : undefined };
  });
  // Kept inside: inside its floating chat window when it opened there, else inside the browser window.
  useLayoutEffect(() => {
    const el = box.current;
    if (!el) return;
    const win = anchor.closest?.("[data-chat-window]");
    const b = win ? win.getBoundingClientRect() : { left: 0, top: 0, right: window.innerWidth, bottom: window.innerHeight };
    const r = el.getBoundingClientRect();
    const pad = 6;
    if (r.right > b.right - pad || r.left < b.left + pad) {
      el.style.right = "auto";
      el.style.left = `${Math.max(b.left + pad, Math.min(r.left, b.right - r.width - pad))}px`;
    }
    if (r.bottom > b.bottom - pad || r.top < b.top + pad) {
      el.style.bottom = "auto";
      el.style.top = `${Math.max(b.top + pad, Math.min(r.top, b.bottom - r.height - pad))}px`;
    }
  }, [anchor]);
  useEffect(() => {
    const off = (e) => !box.current?.contains(e.target) && !anchor.contains(e.target) && onClose();
    const esc = (e) => e.key === "Escape" && onClose();
    document.addEventListener("mousedown", off);
    document.addEventListener("keydown", esc);
    window.addEventListener("resize", onClose);
    return () => {
      document.removeEventListener("mousedown", off);
      document.removeEventListener("keydown", esc);
      window.removeEventListener("resize", onClose);
    };
  }, [anchor, onClose, align]);
  if (!pos) return null;
  return createPortal(
    <div ref={box} data-chat-menu className="chat-emoji fixed z-[70] min-w-48 overflow-hidden rounded-lg bg-white py-1.5 text-[14.5px] text-[#3b4a54] shadow-[0_2px_5px_rgba(11,20,26,.26),0_2px_10px_rgba(11,20,26,.16)]" style={pos}>
      {children}
    </div>,
    document.body,
  );
}

function MenuItem({ icon: Icon, children, onClick, danger }) {
  return (
    <button type="button" onClick={onClick} className={cn("flex w-full items-center gap-3 px-5 py-2 text-left hover:bg-[#f5f6f6]", danger && "text-[#ea0038]")}>
      {Icon ? <Icon className="size-[18px] opacity-80" /> : null}
      {children}
    </button>
  );
}

/**
 * One message bubble, WhatsApp's: green and on the right for mine, white on
 * the left for others (a group shows the sender's name in their colour and
 * their avatar on the first of a run); the reply quote, "Forwarded", the
 * photo or file, the text, then "Edited", the time and my ticks tucked into
 * the corner; reactions hang under it. The arrow on hover opens Reply,
 * React, Copy, Forward, Pin, Message info, Edit and Delete.
 */
const ChatMessage = memo(function ChatMessage({ m, mine, group, first, people, meId, t, locale, highlight, onAction, onJump, onOpenImage }) {
  const [menu, setMenu] = useState(null);
  const [react, setReact] = useState(null);
  const [reactAll, setReactAll] = useState(null);
  const sender = people[m.user_id];
  const tone = NAME_TONES[(sender?.tone ?? 0) % NAME_TONES.length];

  if (m.type === "SYSTEM") {
    return (
      <div className="my-1.5 flex justify-center px-[6%]" id={`msg-${m.id}`}>
        <span className="rounded-lg bg-white/90 px-3 py-1.5 text-center text-[12.5px] text-[#54656f] shadow-[0_1px_0.5px_rgba(11,20,26,.13)]">{systemText(m, people, meId, t)}</span>
      </div>
    );
  }

  const image = m.file?.image && !m.deleted;
  const act = (kind, extra) => {
    setMenu(null);
    setReact(null);
    onAction(kind, m, extra);
  };
  const meta = (
    <span className={cn("ml-2 inline-flex translate-y-1 items-center gap-1 align-bottom text-[11px] whitespace-nowrap", image && !m.body ? "absolute right-1.5 bottom-1 rounded-full bg-black/35 px-1.5 py-0.5 text-white" : "float-right mt-1 -mb-1 text-[#667781]")}>
      {m.edited && !m.deleted ? <span>{t("edited")}</span> : null}
      {clock(m.created_at, locale)}
      {mine && !m.deleted ? <Ticks status={m.status} className={image && !m.body ? "text-white" : undefined} /> : null}
    </span>
  );

  return (
    <div id={`msg-${m.id}`} className={cn("group/msg relative flex px-[5%] @xl:px-[7%]", mine ? "justify-end" : "justify-start", first ? "mt-2.5" : "mt-0.5", m.reactions?.length ? "mb-4" : "")}>
      {group && !mine ? (
        <span className="mr-1.5 w-7 shrink-0">{first ? <ChatAvatar person={sender ?? { id: m.user_id, initials: (m.user_name ?? "?").slice(0, 1), tone: 0 }} size={28} /> : null}</span>
      ) : null}
      <div className={cn("relative flex max-w-[85%] items-center gap-1.5 @xl:max-w-[65%]", mine ? "flex-row-reverse" : "flex-row")}>
        <div
          data-bubble
          onContextMenu={(e) => {
            if (m.deleted || m.status === "pending") return;
            e.preventDefault();
            setMenu(e.currentTarget);
          }}
          className={cn(
            "relative min-w-[90px] rounded-[7.5px] px-[9px] pt-[6px] pb-[8px] text-[14.2px] leading-[19px] text-[#111b21] shadow-[0_1px_0.5px_rgba(11,20,26,.13)] transition-colors",
            mine ? "bg-[#d9fdd3]" : "bg-white",
            first && (mine ? "rounded-tr-none" : "rounded-tl-none"),
            image && "p-[3px]",
            highlight && "ring-2 ring-[#53bdeb]",
          )}
        >
          {first ? <span className={cn("absolute top-0 h-[13px] w-2", mine ? "-right-2" : "-left-2")} style={{ background: mine ? "#d9fdd3" : "#fff", clipPath: mine ? "polygon(0 0, 100% 0, 0 100%)" : "polygon(0 0, 100% 0, 100% 100%)" }} /> : null}
          {group && !mine && first ? <span className={cn("block text-[12.8px] font-semibold", image && "px-1.5 pt-1")} style={{ color: tone }}>{m.user_name ?? sender?.name}</span> : null}
          {m.forwarded && !m.deleted ? (
            <span className={cn("mb-0.5 flex items-center gap-1 text-[12.5px] text-[#667781] italic", image && "px-1.5 pt-1")}>
              <Forward className="size-3.5" /> {t("forwarded")}
            </span>
          ) : null}
          {!m.deleted ? <Quote reply={m.reply} people={people} meId={meId} t={t} onJump={onJump} mine={mine} /> : null}
          {m.deleted ? (
            <span className="flex items-center gap-1.5 pr-14 text-[13.5px] text-[#8696a0] italic">
              <Ban className="size-4" /> {mine ? t("deleted_mine") : t("deleted")}
              {meta}
            </span>
          ) : (
            <>
              {image ? (
                <button type="button" onClick={() => onOpenImage(m)} className="relative block w-[330px] max-w-full overflow-hidden rounded-md">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={m.local_url ?? fileUrl(m.id)} alt={m.file.name} className="block max-h-[330px] w-full object-cover" loading="lazy" />
                  {!m.body ? meta : null}
                </button>
              ) : m.file ? (
                <a href={m.local_url ?? fileUrl(m.id, true)} className={cn("mb-1 flex min-w-[240px] items-center gap-3 rounded-md p-2.5", mine ? "bg-[#d1f4cc]" : "bg-[#f5f6f6]")} download={m.file.name}>
                  <span className="flex h-10 w-8 shrink-0 items-center justify-center rounded-sm text-[9px] font-bold text-white" style={{ background: fileKind(m.file.name).color }}>{fileKind(m.file.name).label}</span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-[14px] text-[#111b21]">{m.file.name}</span>
                    <span className="text-[12px] text-[#667781]">{fileSize(m.file.size)}</span>
                  </span>
                  <span className="flex size-8 items-center justify-center rounded-full border border-[#8696a0]/50 text-[#8696a0]"><Download className="size-4" /></span>
                </a>
              ) : null}
              {m.meta?.mentions?.refs?.length ? <RecordCards refs={m.meta.mentions.refs} mine={mine} t={t} /> : null}
              {m.body ? (
                <span className={cn("block break-words whitespace-pre-wrap", image && "px-1.5 pt-1 pb-0.5")}>
                  <RichText text={m.body} mentions={m.meta?.mentions} meId={meId} onUser={(u) => onAction("mention-user", m, u)} />
                  {meta}
                </span>
              ) : !image ? (
                <span className="block">{meta}</span>
              ) : null}
            </>
          )}
          {!m.deleted && m.status !== "pending" ? (
            <button
              type="button"
              aria-label="menu"
              onClick={(e) => setMenu(e.currentTarget)}
              className={cn("absolute top-0.5 right-0.5 hidden rounded-full p-0.5 text-[#8696a0] group-hover/msg:block [@media(hover:none)]:block", image ? "bg-black/30 text-white" : mine ? "bg-[#d9fdd3]" : "bg-white", menu && "block")}
            >
              <ChevronDown className="size-5" />
            </button>
          ) : null}
          {m.reactions?.length ? (
            <button type="button" onClick={(e) => setReact(e.currentTarget)} className={cn("absolute -bottom-4 flex items-center gap-0.5 rounded-full border border-[#e9edef] bg-white px-1.5 py-[1px] text-[13px] shadow-sm", mine ? "right-2" : "left-2")}>
              {m.reactions.slice(0, 3).map((r) => (
                <span key={r.emoji}>{r.emoji}</span>
              ))}
              {m.reactions.reduce((a, r) => a + r.count, 0) > 1 ? <span className="ml-0.5 text-[11.5px] text-[#667781]">{m.reactions.reduce((a, r) => a + r.count, 0)}</span> : null}
            </button>
          ) : null}
        </div>
        {!m.deleted && m.status !== "pending" ? (
          <button type="button" aria-label={t("react")} onClick={(e) => setReact(e.currentTarget)} className={cn("absolute top-1/2 hidden size-7 -translate-y-1/2 items-center justify-center rounded-full bg-black/5 text-[#8696a0] group-hover/msg:flex hover:bg-black/10 [@media(hover:none)]:flex", mine ? "right-full mr-1.5" : "left-full ml-1.5")}>
            <SmilePlus className="size-4" />
          </button>
        ) : null}
      </div>
      {menu ? (
        <FloatMenu anchor={menu} onClose={() => setMenu(null)} align={mine ? "right" : "left"}>
          <div className="flex items-center gap-1 border-b border-[#e9edef] px-3 pb-1.5">
            {QUICK_REACTIONS.map((e) => (
              <button key={e} type="button" onClick={() => act("react", e)} className="rounded-full p-1 text-xl transition-transform hover:scale-125">{e}</button>
            ))}
            <button type="button" aria-label={t("more_reactions")} title={t("more_reactions")} onClick={() => { const a = menu; setMenu(null); setReactAll(a); }} className="ml-0.5 flex size-8 items-center justify-center rounded-full bg-[#f0f2f5] text-[#54656f] hover:bg-[#e9edef]"><Plus className="size-4" /></button>
          </div>
          <MenuItem icon={Reply} onClick={() => act("reply")}>{t("reply")}</MenuItem>
          {m.body ? <MenuItem icon={Copy} onClick={() => act("copy")}>{t("copy")}</MenuItem> : null}
          <MenuItem icon={Forward} onClick={() => act("forward")}>{t("forward")}</MenuItem>
          <MenuItem icon={m.pinned ? PinOff : Pin} onClick={() => act("pin")}>{m.pinned ? t("unpin") : t("pin")}</MenuItem>
          {mine ? <MenuItem icon={Info} onClick={() => act("info")}>{t("info")}</MenuItem> : null}
          {mine && (m.type === "TEXT" || m.body) ? <MenuItem icon={Pencil} onClick={() => act("edit")}>{t("edit")}</MenuItem> : null}
          {m.file ? <MenuItem icon={Download} onClick={() => act("download")}>{t("download")}</MenuItem> : null}
          <MenuItem icon={Trash2} onClick={() => act("delete")} danger>{t("delete")}</MenuItem>
        </FloatMenu>
      ) : null}
      {reactAll ? (
        <FloatMenu anchor={reactAll} onClose={() => setReactAll(null)} align={mine ? "right" : "left"}>
          <EmojiPicker t={t} className="-my-1.5 w-[330px]" height="h-60" onPick={(e) => { setReactAll(null); act("react", e); }} />
        </FloatMenu>
      ) : null}
      {react ? (
        <FloatMenu anchor={react} onClose={() => setReact(null)} align={mine ? "right" : "left"}>
          <div className="flex items-center gap-1 px-2">
            {QUICK_REACTIONS.map((e) => {
              const minePick = m.reactions?.some((r) => r.emoji === e && r.mine);
              return (
                <button key={e} type="button" onClick={() => act("react", e)} className={cn("rounded-full p-1 text-2xl transition-transform hover:scale-125", minePick && "bg-[#e9edef]")}>{e}</button>
              );
            })}
            <button type="button" aria-label={t("more_reactions")} title={t("more_reactions")} onClick={() => { const a = react; setReact(null); setReactAll(a); }} className="ml-0.5 flex size-9 items-center justify-center rounded-full bg-[#f0f2f5] text-[#54656f] hover:bg-[#e9edef]"><Plus className="size-5" /></button>
          </div>
          {m.reactions?.length ? (
            <div className="mt-1.5 max-h-48 overflow-y-auto border-t border-[#e9edef] pt-1">
              {m.reactions.flatMap((r) => r.users.map((u) => (
                <div key={`${r.emoji}-${u}`} className="flex items-center gap-2.5 px-3 py-1.5 text-[14px]">
                  <ChatAvatar person={u === meId ? people[meId] : people[u]} size={28} />
                  <span className="min-w-0 flex-1 truncate">{u === meId ? t("you") : (people[u]?.name ?? "—")}</span>
                  <span className="text-lg">{r.emoji}</span>
                </div>
              )))}
            </div>
          ) : null}
        </FloatMenu>
      ) : null}
    </div>
  );
});

export { ChatMessage, Ticks, FloatMenu, MenuItem };
