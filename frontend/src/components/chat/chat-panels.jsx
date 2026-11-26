"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { ArrowLeft, ArrowRight, Bell, BellOff, Camera, Check, ChevronDown, Download, LogOut, Pencil, Pin, Search, Trash2, UserPlus, Users, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Modal } from "@/components/ui/modal";
import { ChatAvatar } from "./chat-avatar";
import { FloatMenu, MenuItem } from "./chat-message";
import { chatApi, fileKind, fileSize, fileUrl, lastSeen, listTime } from "./chat-lib";

/** The green slide-in header of the left pane's own screens (New chat, New group). */
function PaneHead({ title, onBack }) {
  return (
    <div className="flex h-[108px] shrink-0 items-end gap-6 bg-[#008069] px-6 pb-4 text-white">
      <button type="button" onClick={onBack} aria-label="back" className="rounded-full p-1 hover:bg-white/10"><ArrowLeft className="size-6" /></button>
      <span className="text-[19px] font-medium">{title}</span>
    </div>
  );
}

function SearchBox({ value, onChange, placeholder, autoFocus = false }) {
  return (
    <div className="shrink-0 bg-white px-3 py-2">
      <label className="flex h-[35px] items-center gap-4 rounded-lg bg-[#f0f2f5] px-3">
        <Search className="size-4 shrink-0 text-[#54656f]" />
        <input autoFocus={autoFocus} value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="min-w-0 flex-1 bg-transparent text-[14.5px] text-[#111b21] outline-none placeholder:text-[#667781]" />
        {value ? <button type="button" onClick={() => onChange("")} aria-label="clear"><X className="size-4 text-[#54656f]" /></button> : null}
      </label>
    </div>
  );
}

/** Messages matching a search (2+ letters), in all my chats or one; asked 300 ms after the typing stops. */
function useMessageSearch(query, chatId = null) {
  const term = query.trim();
  const key = `${chatId ?? ""}|${term}`;
  const [state, setState] = useState({ key: "", results: [] });
  useEffect(() => {
    if (term.length < 2) return undefined;
    let stop = false;
    const timer = setTimeout(async () => {
      try {
        const d = await chatApi(`search?q=${encodeURIComponent(term)}${chatId ? `&chat=${encodeURIComponent(chatId)}` : ""}`);
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

/** The searched words marked in a line of text. */
function Highlight({ text, query }) {
  const term = query.trim();
  if (!text || term.length < 2) return text ?? "";
  const at = text.toLowerCase().indexOf(term.toLowerCase());
  if (at < 0) return text;
  const from = Math.max(0, at - 30);
  return (
    <>
      {from > 0 ? "…" : ""}
      {text.slice(from, at)}
      <mark className="rounded-sm bg-transparent font-semibold text-[#00a884]">{text.slice(at, at + term.length)}</mark>
      {text.slice(at + term.length)}
    </>
  );
}

/** A found message: (chat name,) time, then sender and the matching words. */
function MessageHit({ hit, chat, query, me, t, locale, showChat, onClick }) {
  const who = hit.user_id === me.id ? t("you") : chat?.is_group ? hit.user_name : null;
  const text = hit.body || hit.file_name || "";
  return (
    <button type="button" onClick={onClick} className="flex w-full flex-col gap-0.5 border-b border-[#e9edef] px-5 py-3 text-left hover:bg-[#f5f6f6]">
      <span className="flex items-baseline gap-2">
        <span className={cn("min-w-0 flex-1 truncate", showChat ? "text-[16px] text-[#111b21]" : "text-[12.5px] text-[#667781]")}>{showChat ? chat?.name ?? "—" : listTime(hit.created_at, t, locale)}</span>
        {showChat ? <span className="shrink-0 text-[12px] text-[#667781]">{listTime(hit.created_at, t, locale)}</span> : null}
      </span>
      <span className="line-clamp-2 text-[14px] text-[#667781]">
        {who ? <span className="text-[#111b21]">{who}: </span> : null}
        {hit.type !== "TEXT" && !hit.body ? <span className="mr-1">{hit.type === "IMAGE" ? "📷" : "📄"}</span> : null}
        <Highlight text={text} query={query} />
      </span>
    </button>
  );
}

/** Search messages of the open chat, WhatsApp's right drawer; a hit scrolls the chat to it. */
function MessageSearchPanel({ chat, me, t, locale, onClose, onJump }) {
  const [query, setQuery] = useState("");
  const { active, loading, results } = useMessageSearch(query, chat.id);
  return (
    <aside className="flex w-full shrink-0 flex-col border-l border-[#d1d7db] bg-white md:w-[360px] lg:w-[380px]">
      <div className="flex h-[60px] shrink-0 items-center gap-6 bg-[#f0f2f5] px-5">
        <button type="button" onClick={onClose} aria-label={t("cancel")} className="text-[#54656f]"><X className="size-5" /></button>
        <span className="text-[16px] text-[#111b21]">{t("search_messages")}</span>
      </div>
      <SearchBox value={query} onChange={setQuery} placeholder={t("search_in_chat")} autoFocus />
      <div className="min-h-0 flex-1 overflow-y-auto">
        {!active ? <p className="px-8 py-10 text-center text-[14px] text-[#667781]">{t("search_hint", { name: chat.name ?? "" })}</p> : null}
        {loading ? <p className="px-8 py-10 text-center text-[14px] text-[#667781]">{t("searching")}</p> : null}
        {active && !loading && !results.length ? <p className="px-8 py-10 text-center text-[14px] text-[#667781]">{t("no_messages_found")}</p> : null}
        {results.map((hit) => (
          <MessageHit key={hit.id} hit={hit} chat={chat} query={query} me={me} t={t} locale={locale} showChat={false} onClick={() => onJump(hit.id)} />
        ))}
      </div>
    </aside>
  );
}

function PersonRow({ person, t, locale, onClick, selected = null, right = null }) {
  return (
    <button type="button" onClick={onClick} className="flex w-full items-center gap-3.5 px-4 text-left hover:bg-[#f5f6f6]">
      <span className="relative py-2.5">
        <ChatAvatar person={person} size={46} online={person.online} />
        {selected ? <span className="absolute right-0 bottom-2 flex size-[18px] items-center justify-center rounded-full border-2 border-white bg-[#00a884]"><Check className="size-3 text-white" /></span> : null}
      </span>
      <span className="flex min-w-0 flex-1 items-center gap-2 self-stretch border-b border-[#e9edef] py-3">
        <span className="min-w-0 flex-1">
          <span className="block truncate text-[16px] text-[#111b21]">{person.name}</span>
          <span className="block truncate text-[13.5px] text-[#667781]">{person.about || lastSeen(person, t, locale) || person.email}</span>
        </span>
        {right}
      </span>
    </button>
  );
}

/** New chat: the company's people (search by name or designation); a click opens the chat. "New group" on top. */
function NewChatPane({ contacts, t, locale, onBack, onPick, onGroup }) {
  const [q, setQ] = useState("");
  const shown = (contacts ?? []).filter((p) => !q || `${p.name} ${p.about ?? ""} ${p.email ?? ""}`.toLowerCase().includes(q.toLowerCase()));
  return (
    <div className="flex min-h-0 flex-1 flex-col bg-white">
      <PaneHead title={t("new_chat")} onBack={onBack} />
      <SearchBox value={q} onChange={setQ} placeholder={t("search_people")} autoFocus />
      <div className="min-h-0 flex-1 overflow-y-auto">
        <button type="button" onClick={onGroup} className="flex w-full items-center gap-3.5 px-4 py-2.5 text-left hover:bg-[#f5f6f6]">
          <span className="flex size-[46px] items-center justify-center rounded-full bg-[#00a884] text-white"><Users className="size-6" /></span>
          <span className="text-[16px] text-[#111b21]">{t("new_group")}</span>
        </button>
        <p className="px-8 pt-5 pb-3 text-[15px] text-[#008069]">{t("people")}</p>
        {contacts === null ? <p className="px-8 py-4 text-[14px] text-[#667781]">{t("loading")}</p> : null}
        {shown.map((p) => (
          <PersonRow key={p.id} person={p} t={t} locale={locale} onClick={() => onPick(p)} />
        ))}
        {contacts && !shown.length ? <p className="px-8 py-4 text-[14px] text-[#667781]">{t("no_people")}</p> : null}
      </div>
    </div>
  );
}

/** New group: pick its people (chips on top), then its name. */
function NewGroupPane({ contacts, t, locale, onBack, onCreate, busy }) {
  const [step, setStep] = useState(1);
  const [q, setQ] = useState("");
  const [picked, setPicked] = useState([]);
  const [name, setName] = useState("");
  const toggle = (p) => setPicked((all) => (all.some((x) => x.id === p.id) ? all.filter((x) => x.id !== p.id) : [...all, p]));
  const shown = (contacts ?? []).filter((p) => !q || `${p.name} ${p.about ?? ""}`.toLowerCase().includes(q.toLowerCase()));
  if (step === 2) {
    return (
      <div className="flex min-h-0 flex-1 flex-col bg-[#f0f2f5]">
        <PaneHead title={t("new_group")} onBack={() => setStep(1)} />
        <div className="flex flex-col items-center gap-6 bg-white px-8 py-7">
          <span className="flex size-40 items-center justify-center rounded-full bg-[#dfe5e7] text-[#9eb3c2]"><Users className="size-20" /></span>
          <label className="w-full">
            <input autoFocus value={name} onChange={(e) => setName(e.target.value)} maxLength={120} placeholder={t("group_name")} className="w-full border-b-2 border-[#00a884] bg-transparent py-2 text-[16px] text-[#111b21] outline-none" />
          </label>
          <p className="self-start text-[13px] text-[#667781]">{t("selected", { n: picked.length })}: {picked.map((p) => p.name).join(", ")}</p>
        </div>
        <div className="flex flex-1 items-start justify-center pt-8">
          {name.trim() ? (
            <button type="button" disabled={busy} onClick={() => onCreate(name.trim(), picked.map((p) => p.id))} aria-label={t("create_group")} title={t("create_group")} className="flex size-12 items-center justify-center rounded-full bg-[#00a884] text-white shadow-md hover:bg-[#06cf9c] disabled:opacity-60">
              <Check className="size-6" />
            </button>
          ) : null}
        </div>
      </div>
    );
  }
  return (
    <div className="flex min-h-0 flex-1 flex-col bg-white">
      <PaneHead title={t("select_people")} onBack={onBack} />
      {picked.length ? (
        <div className="flex max-h-32 shrink-0 flex-wrap gap-2 overflow-y-auto px-4 pt-3">
          {picked.map((p) => (
            <span key={p.id} className="flex items-center gap-1.5 rounded-full bg-[#e9edef] py-0.5 pr-2 pl-0.5 text-[13.5px]">
              <ChatAvatar person={p} size={24} /> {p.name.split(" ")[0]}
              <button type="button" onClick={() => toggle(p)} aria-label="remove"><X className="size-3.5 text-[#54656f]" /></button>
            </span>
          ))}
        </div>
      ) : null}
      <SearchBox value={q} onChange={setQ} placeholder={t("search_people")} autoFocus />
      <div className="min-h-0 flex-1 overflow-y-auto">
        {shown.map((p) => (
          <PersonRow key={p.id} person={p} t={t} locale={locale} onClick={() => toggle(p)} selected={picked.some((x) => x.id === p.id)} />
        ))}
      </div>
      {picked.length ? (
        <div className="flex shrink-0 justify-center bg-[#f0f2f5] py-5">
          <button type="button" onClick={() => setStep(2)} aria-label={t("next")} title={t("next")} className="flex size-12 items-center justify-center rounded-full bg-[#00a884] text-white shadow-md hover:bg-[#06cf9c]"><ArrowRight className="size-6" /></button>
        </div>
      ) : null}
    </div>
  );
}

/** Contact / group info, the right-hand drawer: photo, name, about, media, a group's description and people. */
function InfoPanel({ chat, detail, me, contacts, t, locale, onClose, onRename, onDescribe, onImage, onAdd, onRole, onRemove, onMenu, onJump, onOpenMedia, className = "md:w-[360px] lg:w-[380px]" }) {
  const [renaming, setRenaming] = useState(false);
  const [name, setName] = useState(chat.name ?? "");
  const [describing, setDescribing] = useState(false);
  const [description, setDescription] = useState(detail?.description ?? "");
  const [adding, setAdding] = useState(false);
  const [menu, setMenu] = useState(null);
  const photo = useRef(null);
  const admin = chat.is_group && ["OWNER", "ADMIN"].includes(chat.role) && !chat.left;
  const other = detail?.other ?? chat.other;
  const memberIds = new Set((detail?.members ?? []).map((m) => m.id));

  return (
    <aside className={cn("flex w-full shrink-0 flex-col border-l border-[#d1d7db] bg-[#f0f2f5]", className)}>
      <div className="flex h-[60px] shrink-0 items-center gap-6 bg-[#f0f2f5] px-5">
        <button type="button" onClick={onClose} aria-label={t("cancel")} className="text-[#54656f]"><X className="size-5" /></button>
        <span className="text-[16px] text-[#111b21]">{chat.is_group ? t("group_info") : t("contact_info")}</span>
      </div>
      <div className="min-h-0 flex-1 overflow-y-auto">
        <section className="flex flex-col items-center bg-white px-6 pt-7 pb-5 shadow-[0_1px_3px_rgba(11,20,26,.08)]">
          <button type="button" disabled={!admin} onClick={() => photo.current?.click()} className="group relative rounded-full">
            <ChatAvatar chat={chat} person={chat.is_group ? null : other} size={180} />
            {admin ? (
              <span className="absolute inset-0 hidden flex-col items-center justify-center gap-1 rounded-full bg-black/45 text-[12px] text-white uppercase group-hover:flex"><Camera className="size-6" />{t("change_photo")}</span>
            ) : null}
          </button>
          <input ref={photo} type="file" accept="image/*" hidden onChange={(e) => { if (e.target.files?.[0]) onImage(e.target.files[0]); e.target.value = ""; }} />
          {renaming ? (
            <div className="mt-4 flex w-full items-center gap-2 border-b-2 border-[#00a884]">
              <input autoFocus value={name} onChange={(e) => setName(e.target.value)} maxLength={120} onKeyDown={(e) => { if (e.key === "Enter" && name.trim()) { onRename(name.trim()); setRenaming(false); } }} className="min-w-0 flex-1 bg-transparent py-1.5 text-[18px] outline-none" />
              <button type="button" onClick={() => { if (name.trim()) onRename(name.trim()); setRenaming(false); }} aria-label={t("save")}><Check className="size-5 text-[#54656f]" /></button>
            </div>
          ) : (
            <h2 className="mt-4 flex items-center gap-2 text-center text-[22px] text-[#111b21]">
              {chat.name}
              {admin ? <button type="button" onClick={() => { setName(chat.name ?? ""); setRenaming(true); }} aria-label={t("rename")}><Pencil className="size-4 text-[#54656f]" /></button> : null}
            </h2>
          )}
          <p className="mt-1 text-[15px] text-[#667781]">{chat.is_group ? `${t("group")} · ${t("participants", { n: detail?.members?.length ?? chat.member_count })}` : (other?.email ?? "")}</p>
          {chat.department ? <p className="mt-2 rounded-full bg-[#e7fce3] px-3 py-1 text-center text-[12.5px] text-[#008069]">{t("dept_badge")}</p> : null}
          {!chat.is_group && other ? <p className="mt-0.5 text-[13.5px] text-[#667781]">{lastSeen(other, t, locale)}</p> : null}
        </section>

        {chat.is_group ? (
          <section className="mt-2.5 bg-white px-7 py-4 shadow-[0_1px_3px_rgba(11,20,26,.08)]">
            {describing ? (
              <div className="flex items-start gap-2 border-b-2 border-[#00a884]">
                <textarea autoFocus value={description} onChange={(e) => setDescription(e.target.value)} maxLength={500} rows={3} className="min-w-0 flex-1 resize-none bg-transparent py-1 text-[14.5px] outline-none" />
                <button type="button" onClick={() => { onDescribe(description); setDescribing(false); }} aria-label={t("save")}><Check className="size-5 text-[#54656f]" /></button>
              </div>
            ) : detail?.description ? (
              <div className="flex items-start gap-2">
                <p className="flex-1 text-[14.5px] whitespace-pre-wrap text-[#111b21]">{detail.description}</p>
                {admin ? <button type="button" onClick={() => { setDescription(detail.description ?? ""); setDescribing(true); }} aria-label={t("description")}><Pencil className="size-4 text-[#54656f]" /></button> : null}
              </div>
            ) : admin ? (
              <button type="button" onClick={() => setDescribing(true)} className="text-[14.5px] text-[#008069]">{t("add_description")}</button>
            ) : null}
            {detail?.created_at ? <p className="mt-2 text-[13px] text-[#667781]">{t("created_on", { date: new Date(detail.created_at).toLocaleDateString(locale === "bn" ? "bn-BD" : "en-GB", { day: "numeric", month: "short", year: "numeric" }) })}</p> : null}
          </section>
        ) : other?.about ? (
          <section className="mt-2.5 bg-white px-7 py-4 shadow-[0_1px_3px_rgba(11,20,26,.08)]">
            <p className="text-[14px] text-[#667781]">{t("about")}</p>
            <p className="mt-1 text-[15px] text-[#111b21]">{other.about}</p>
          </section>
        ) : null}

        <section className="mt-2.5 bg-white px-7 py-4 shadow-[0_1px_3px_rgba(11,20,26,.08)]">
          <p className="mb-3 text-[14px] text-[#667781]">{t("media")}</p>
          {detail?.media?.length ? (
            <div className="grid grid-cols-3 gap-1.5">
              {detail.media.slice(0, 12).map((f) =>
                f.image ? (
                  <button key={f.id} type="button" onClick={() => onOpenMedia(f)} className="aspect-square overflow-hidden rounded">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={fileUrl(f.id)} alt={f.name} className="size-full object-cover" loading="lazy" />
                  </button>
                ) : (
                  <a key={f.id} href={fileUrl(f.id, true)} title={f.name} className="flex aspect-square flex-col items-center justify-center gap-1 rounded bg-[#f0f2f5] p-1 text-center">
                    <span className="rounded px-1 text-[10px] font-bold text-white" style={{ background: fileKind(f.name).color }}>{fileKind(f.name).label}</span>
                    <span className="line-clamp-2 text-[10.5px] text-[#54656f]">{f.name}</span>
                  </a>
                ),
              )}
            </div>
          ) : (
            <p className="text-[13.5px] text-[#8696a0]">{t("no_media")}</p>
          )}
        </section>

        {detail?.pinned_messages?.length ? (
          <section className="mt-2.5 bg-white py-2 shadow-[0_1px_3px_rgba(11,20,26,.08)]">
            {detail.pinned_messages.map((m) => (
              <button key={m.id} type="button" onClick={() => onJump(m.id)} className="flex w-full items-center gap-3 px-7 py-2.5 text-left hover:bg-[#f5f6f6]">
                <Pin className="size-4 shrink-0 rotate-45 text-[#54656f]" />
                <span className="truncate text-[14.5px] text-[#111b21]">{m.body || m.file?.name}</span>
              </button>
            ))}
          </section>
        ) : null}

        <section className="mt-2.5 bg-white py-1 shadow-[0_1px_3px_rgba(11,20,26,.08)]">
          <button type="button" onClick={() => onMenu("mute")} className="flex w-full items-center gap-6 px-7 py-3.5 text-left text-[15px] text-[#111b21] hover:bg-[#f5f6f6]">
            {chat.muted ? <BellOff className="size-5 text-[#54656f]" /> : <Bell className="size-5 text-[#54656f]" />}
            {chat.muted ? t("unmute") : t("mute")}
          </button>
          <button type="button" onClick={() => onMenu("pin")} className="flex w-full items-center gap-6 px-7 py-3.5 text-left text-[15px] text-[#111b21] hover:bg-[#f5f6f6]">
            <Pin className="size-5 text-[#54656f]" /> {chat.pinned ? t("unpin_chat") : t("pin_chat")}
          </button>
        </section>

        {chat.is_group ? (
          <section className="mt-2.5 bg-white py-2 shadow-[0_1px_3px_rgba(11,20,26,.08)]">
            <p className="px-7 py-2 text-[14px] text-[#667781]">{t("participants", { n: detail?.members?.length ?? chat.member_count })}</p>
            {admin ? (
              <button type="button" onClick={() => setAdding(true)} className="flex w-full items-center gap-4 px-5 py-2.5 text-left hover:bg-[#f5f6f6]">
                <span className="flex size-[46px] items-center justify-center rounded-full bg-[#00a884] text-white"><UserPlus className="size-5" /></span>
                <span className="text-[16px] text-[#111b21]">{t("add_participants")}</span>
              </button>
            ) : null}
            {(detail?.members ?? []).map((p) => (
              <div key={p.id} className="group flex items-center gap-4 px-5 hover:bg-[#f5f6f6]">
                <span className="py-2.5"><ChatAvatar person={p} size={46} online={p.online && p.id !== me.id} /></span>
                <span className="flex min-w-0 flex-1 items-center gap-2 self-stretch border-b border-[#e9edef] py-3">
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-[16px] text-[#111b21]">{p.id === me.id ? t("you") : p.name}</span>
                    <span className="block truncate text-[13.5px] text-[#667781]">{p.about ?? ""}</span>
                  </span>
                  {p.role !== "MEMBER" ? <span className="rounded bg-[#e7fce3] px-1.5 py-0.5 text-[11.5px] text-[#008069]">{p.role === "OWNER" ? t("owner") : t("admin")}</span> : null}
                  {admin && p.id !== me.id && p.role !== "OWNER" ? (
                    <button type="button" onClick={(e) => setMenu({ el: e.currentTarget, p })} className="invisible rounded-full p-1 text-[#8696a0] group-hover:visible"><ChevronDown className="size-5" /></button>
                  ) : null}
                </span>
              </div>
            ))}
            {menu ? (
              <FloatMenu anchor={menu.el} onClose={() => setMenu(null)}>
                <MenuItem onClick={() => { onRole(menu.p.id, menu.p.role === "ADMIN" ? "MEMBER" : "ADMIN"); setMenu(null); }}>{menu.p.role === "ADMIN" ? t("dismiss_admin") : t("make_admin")}</MenuItem>
                <MenuItem danger onClick={() => { onRemove(menu.p.id); setMenu(null); }}>{t("remove")}</MenuItem>
              </FloatMenu>
            ) : null}
          </section>
        ) : null}

        <section className="mt-2.5 mb-6 bg-white py-1 shadow-[0_1px_3px_rgba(11,20,26,.08)]">
          <button type="button" onClick={() => onMenu("clear")} className="flex w-full items-center gap-6 px-7 py-3.5 text-left text-[15px] text-[#ea0038] hover:bg-[#f5f6f6]">
            <Trash2 className="size-5" /> {t("clear")}
          </button>
          {chat.is_group && !chat.left ? (
            <button type="button" onClick={() => onMenu("exit")} className="flex w-full items-center gap-6 px-7 py-3.5 text-left text-[15px] text-[#ea0038] hover:bg-[#f5f6f6]">
              <LogOut className="size-5" /> {t("exit_group")}
            </button>
          ) : null}
        </section>
      </div>
      {adding ? (
        <PeoplePicker title={t("add_participants")} t={t} locale={locale} contacts={(contacts ?? []).filter((p) => !memberIds.has(p.id))} onClose={() => setAdding(false)} onDone={(ids) => { onAdd(ids); setAdding(false); }} />
      ) : null}
    </aside>
  );
}

/** Pick people (adding to a group). */
function PeoplePicker({ title, contacts, t, locale, onClose, onDone }) {
  const [q, setQ] = useState("");
  const [picked, setPicked] = useState([]);
  const shown = contacts.filter((p) => !q || `${p.name} ${p.about ?? ""}`.toLowerCase().includes(q.toLowerCase()));
  return (
    <Modal open onOpenChange={(o) => !o && onClose()} title={title} className="max-w-md">
      <div className="-mx-6 -mb-6 flex max-h-[65vh] flex-col">
        <SearchBox value={q} onChange={setQ} placeholder={t("search_people")} autoFocus />
        <div className="min-h-0 flex-1 overflow-y-auto">
          {shown.map((p) => (
            <PersonRow key={p.id} person={p} t={t} locale={locale} selected={picked.includes(p.id)} onClick={() => setPicked((all) => (all.includes(p.id) ? all.filter((x) => x !== p.id) : [...all, p.id]))} />
          ))}
          {!shown.length ? <p className="px-6 py-4 text-[14px] text-[#667781]">{t("no_people")}</p> : null}
        </div>
        <div className="flex items-center justify-between gap-3 bg-[#f0f2f5] px-6 py-3">
          <span className="text-[13.5px] text-[#667781]">{t("selected", { n: picked.length })}</span>
          <button type="button" disabled={!picked.length} onClick={() => onDone(picked)} className="flex size-11 items-center justify-center rounded-full bg-[#00a884] text-white disabled:opacity-40"><Check className="size-5" /></button>
        </div>
      </div>
    </Modal>
  );
}

/** Forward a message: pick chats (search), send. */
function ForwardModal({ chats, t, onClose, onSend }) {
  const [q, setQ] = useState("");
  const [picked, setPicked] = useState([]);
  const shown = chats.filter((c) => !c.left && (!q || (c.name ?? "").toLowerCase().includes(q.toLowerCase())));
  return (
    <Modal open onOpenChange={(o) => !o && onClose()} title={t("forward_to")} className="max-w-md">
      <div className="-mx-6 -mb-6 flex max-h-[65vh] flex-col">
        <SearchBox value={q} onChange={setQ} placeholder={t("search")} autoFocus />
        <div className="min-h-0 flex-1 overflow-y-auto">
          {shown.map((c) => (
            <button key={c.id} type="button" onClick={() => setPicked((all) => (all.includes(c.id) ? all.filter((x) => x !== c.id) : [...all, c.id].slice(0, 20)))} className="flex w-full items-center gap-3.5 px-5 py-2 text-left hover:bg-[#f5f6f6]">
              <span className={cn("flex size-5 items-center justify-center rounded border-2", picked.includes(c.id) ? "border-[#00a884] bg-[#00a884]" : "border-[#8696a0]")}>{picked.includes(c.id) ? <Check className="size-3.5 text-white" /> : null}</span>
              <ChatAvatar chat={c} size={40} />
              <span className="min-w-0 flex-1 truncate text-[15.5px] text-[#111b21]">{c.name}</span>
            </button>
          ))}
        </div>
        <div className="flex items-center justify-between gap-3 bg-[#f0f2f5] px-6 py-3">
          <span className="truncate text-[13.5px] text-[#667781]">{chats.filter((c) => picked.includes(c.id)).map((c) => c.name).join(", ")}</span>
          <button type="button" disabled={!picked.length} onClick={() => onSend(picked)} className="flex size-11 shrink-0 items-center justify-center rounded-full bg-[#00a884] text-white disabled:opacity-40"><ArrowRight className="size-5" /></button>
        </div>
      </div>
    </Modal>
  );
}

/** Message info: who has read it, who has it, who not yet. */
function InfoModal({ info, t, onClose }) {
  const block = (title, people, icon) =>
    people?.length ? (
      <section className="mb-3">
        <p className="mb-1 flex items-center gap-1.5 text-[13.5px] text-[#008069]">{icon} {title}</p>
        {people.map((p) => (
          <div key={p.id} className="flex items-center gap-3 py-1.5">
            <ChatAvatar person={p} size={36} />
            <span className="text-[15px] text-[#111b21]">{p.name}</span>
          </div>
        ))}
      </section>
    ) : null;
  return (
    <Modal open onOpenChange={(o) => !o && onClose()} title={t("info")} className="max-w-sm">
      {info ? (
        <div>
          {block(t("read_by"), info.read, <span className="text-[#53bdeb]">✓✓</span>)}
          {block(t("delivered_to"), info.delivered, <span className="text-[#8696a0]">✓✓</span>)}
          {block(t("waiting"), info.waiting, <span className="text-[#8696a0]">✓</span>)}
        </div>
      ) : (
        <p className="text-[14px] text-[#667781]">{t("loading")}</p>
      )}
    </Modal>
  );
}

/** A photo full size, with its download. */
function ImageViewer({ item, t, onClose }) {
  useEffect(() => {
    const esc = (e) => e.key === "Escape" && onClose();
    window.addEventListener("keydown", esc);
    return () => window.removeEventListener("keydown", esc);
  }, [onClose]);
  return (
    <div className="fixed inset-0 z-[80] flex flex-col bg-white/95" onClick={onClose}>
      <div className="flex h-[60px] shrink-0 items-center justify-between px-5" onClick={(e) => e.stopPropagation()}>
        <span className="truncate text-[15px] text-[#111b21]">{item.name}</span>
        <span className="flex items-center gap-2">
          <a href={fileUrl(item.id, true)} className="rounded-full p-2 text-[#54656f] hover:bg-black/5" aria-label={t("download")}><Download className="size-5" /></a>
          <button type="button" onClick={onClose} className="rounded-full p-2 text-[#54656f] hover:bg-black/5" aria-label={t("cancel")}><X className="size-5" /></button>
        </span>
      </div>
      <div className="flex min-h-0 flex-1 items-center justify-center p-6">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={fileUrl(item.id)} alt={item.name} className="max-h-full max-w-full object-contain shadow-lg" onClick={(e) => e.stopPropagation()} />
      </div>
    </div>
  );
}

export { NewChatPane, NewGroupPane, InfoPanel, ForwardModal, InfoModal, ImageViewer, SearchBox, MessageSearchPanel, MessageHit, useMessageSearch };
