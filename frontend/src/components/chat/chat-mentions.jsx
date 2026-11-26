"use client";

import { Fragment, useEffect, useState } from "react";
import { ClipboardList, ExternalLink, FileSpreadsheet, FlaskConical, Package, Ship } from "lucide-react";
import { cn } from "@/lib/utils";
import { ChatAvatar } from "./chat-avatar";
import { chatApi } from "./chat-lib";

/**
 * Mentions in chat (user, 2026-11-23): `@` names a person of the chat, `#` a
 * record — an Order Summary job, a Pre-Budget budget or requisition, a dev
 * sample, an export statement. The text keeps the plain token (@Name,
 * #26NCL00001); the message's `meta.mentions` says what each token is, so
 * the bubble draws it as a chip and, for a record, a small card under the
 * text — with what the reader may see (the server answers per reader).
 */
export const RECORD_ICONS = { os: Package, pb: FileSpreadsheet, req: ClipboardList, ds: FlaskConical, es: Ship };

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

/** Records matching what follows `#`, of one kind; the kinds this person may mention come back too. */
export function useRecordSearch(open, type, query) {
  const [state, setState] = useState({ key: null, types: null, results: [] });
  const key = `${type ?? ""}|${query}`;
  useEffect(() => {
    if (!open) return undefined;
    let stop = false;
    const timer = setTimeout(async () => {
      try {
        const d = await chatApi(`records?${new URLSearchParams({ ...(type ? { type } : {}), q: query })}`);
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

/** The popup over the composer: people for `@`, records (with kind tabs) for `#`. */
export function MentionPicker({ trigger, people, records, recordType, onRecordType, index, onHover, onPick, t }) {
  if (!trigger) return null;
  const items = trigger.kind === "@" ? people : records.results;
  return (
    <div data-chat-picker className="absolute bottom-full left-3 z-30 mb-1 w-[min(440px,calc(100%-24px))] overflow-hidden rounded-lg bg-white shadow-[0_2px_12px_rgba(11,20,26,.18)]" onMouseDown={(e) => e.preventDefault()}>
      <div className="flex items-center gap-2 border-b border-[#e9edef] px-3 py-2 text-[12.5px] text-[#667781]">
        <span className="font-medium text-[#008069]">{trigger.kind === "@" ? t("mention_people") : t("records_title")}</span>
        {trigger.kind === "#" && records.types?.length ? (
          <span className="ml-auto flex flex-wrap gap-1">
            {["all", ...records.types].map((k) => (
              <button key={k} type="button" onClick={() => onRecordType(k)} className={cn("rounded-full px-2.5 py-0.5 text-[12px]", (recordType ?? records.type ?? "all") === k ? "bg-[#e7fce3] text-[#008069]" : "bg-[#f0f2f5] text-[#54656f] hover:bg-[#e9edef]")}>
                {t(`rec.${k}`)}
              </button>
            ))}
          </span>
        ) : null}
      </div>
      <div className="max-h-64 overflow-y-auto py-1">
        {trigger.kind === "#" && records.loading && !items.length ? <p className="px-4 py-3 text-[13px] text-[#667781]">{t("searching")}</p> : null}
        {!items.length && !(trigger.kind === "#" && records.loading) ? (
          <p className="px-4 py-3 text-[13px] text-[#667781]">{trigger.kind === "@" ? t("no_people") : t("no_records")}</p>
        ) : null}
        {items.map((it, i) => {
          const Icon = RECORD_ICONS[it.type];
          return (
            <button
              key={trigger.kind === "@" ? it.id : `${it.type}:${it.ref}:${i}`}
              type="button"
              onMouseEnter={() => onHover(i)}
              onClick={() => onPick(it)}
              className={cn("flex w-full items-center gap-3 px-3 py-2 text-left", i === index ? "bg-[#f0f2f5]" : "hover:bg-[#f5f6f6]")}
            >
              {trigger.kind === "@" ? (
                <ChatAvatar person={it} size={32} />
              ) : (
                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#e7fce3] text-[#008069]"><Icon className="size-4" /></span>
              )}
              <span className="min-w-0 flex-1">
                <span className="flex items-center gap-2 truncate text-[14.5px] text-[#111b21]">
                  {trigger.kind === "@" ? it.name : it.title}
                  {trigger.kind === "#" ? <span className="rounded bg-[#f0f2f5] px-1.5 text-[11px] text-[#54656f]">{t(`rec.${it.type}`)}</span> : null}
                </span>
                <span className="block truncate text-[12.5px] text-[#667781]">{trigger.kind === "@" ? it.about ?? "" : it.sub}</span>
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
}

/* ── Cards, fetched once per record for the whole screen, a few at a time ── */
const cardCache = new Map();
const waiting = new Map();
let batch = null;

function loadCard(key) {
  if (cardCache.has(key)) return Promise.resolve(cardCache.get(key));
  if (waiting.has(key)) return waiting.get(key).promise;
  let resolve;
  const promise = new Promise((r) => (resolve = r));
  waiting.set(key, { promise, resolve });
  batch ??= setTimeout(async () => {
    const keys = [...waiting.keys()].filter((k) => !cardCache.has(k));
    const settle = waiting;
    batch = null;
    let cards = {};
    try {
      cards = (await chatApi(`records/cards?keys=${encodeURIComponent(keys.join(","))}`))?.cards ?? {};
    } catch {
      cards = {};
    }
    for (const k of keys) {
      cardCache.set(k, cards[k] ?? null);
      settle.get(k)?.resolve(cards[k] ?? null);
      settle.delete(k);
    }
  }, 60);
  return promise;
}

function useCards(keys) {
  const joined = keys.join(",");
  const [cards, setCards] = useState({});
  useEffect(() => {
    if (!joined) return undefined;
    let alive = true;
    Promise.all(joined.split(",").map((k) => loadCard(k).then((c) => [k, c]))).then((pairs) => alive && setCards(Object.fromEntries(pairs)));
    return () => {
      alive = false;
    };
  }, [joined]);
  return cards;
}

/** A message's text with its mentions drawn: @people in blue, #records as chips (links where the reader may open them). */
export function RichText({ text, mentions, meId, onUser }) {
  const users = mentions?.users ?? [];
  const refs = mentions?.refs ?? [];
  const cards = useCards(refs.map((r) => `${r.type}:${r.ref}`));
  const tokens = [
    ...users.map((u) => ({ token: `@${u.name}`, user: u })),
    ...refs.map((r) => ({ token: r.token, ref: r })),
  ].sort((a, b) => b.token.length - a.token.length);
  const escape = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const pattern = new RegExp(`(https?:\\/\\/[^\\s]+${tokens.map((x) => `|${escape(x.token)}`).join("")})`, "g");
  const parts = String(text).split(pattern);

  return parts.map((p, i) => {
    if (!p) return null;
    if (/^https?:\/\//.test(p)) {
      return <a key={i} href={p} target="_blank" rel="noreferrer" className="text-[#027eb5] hover:underline">{p}</a>;
    }
    const hit = tokens.find((x) => x.token === p);
    if (hit?.user) {
      return (
        <button key={i} type="button" onClick={() => hit.user.id !== meId && onUser?.(hit.user)} className={cn("font-medium text-[#027eb5]", hit.user.id !== meId && "hover:underline")}>
          {p}
        </button>
      );
    }
    if (hit?.ref) {
      const card = cards[`${hit.ref.type}:${hit.ref.ref}`];
      return card ? (
        <a key={i} href={card.url} target="_blank" rel="noreferrer" className="rounded bg-[#027eb5]/10 px-1 font-medium text-[#027eb5] hover:underline">{p}</a>
      ) : (
        <span key={i} className="rounded bg-black/5 px-1 font-medium text-[#54656f]">{p}</span>
      );
    }
    return <Fragment key={i}>{p}</Fragment>;
  });
}

/** Under the text: a small card per mentioned record the reader may see (buyer, style, qty …, status, Open). */
export function RecordCards({ refs, mine, t }) {
  const list = (refs ?? []).slice(0, 3);
  const cards = useCards(list.map((r) => `${r.type}:${r.ref}`));
  const shown = list.map((r) => cards[`${r.type}:${r.ref}`]).filter(Boolean);
  if (!shown.length) return null;
  return (
    <span className="mb-1 flex flex-col gap-1">
      {shown.map((c) => {
        const Icon = RECORD_ICONS[c.type] ?? Package;
        return (
          <a key={`${c.type}:${c.ref}`} href={c.url} target="_blank" rel="noreferrer" className={cn("block min-w-[200px] max-w-full rounded-md border-l-4 border-[#00a884] px-2.5 py-1.5 hover:brightness-[0.97]", mine ? "bg-[#d1f4cc]" : "bg-[#f5f6f6]")}>
            <span className="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[12.8px] font-semibold text-[#008069]">
              <Icon className="size-3.5" /> {c.title} · {c.token.replace(/^#/, "")}
              {c.status ? <span className="ml-auto rounded-full bg-white/80 px-1.5 text-[11px] font-medium text-[#54656f]">{c.status}</span> : null}
            </span>
            <span className="mt-0.5 block text-[12.5px] leading-[17px] text-[#3b4a54]">
              {c.fields.map((f) => (
                <span key={f.label} className="mr-2.5 inline-block">
                  <span className="text-[#667781]">{f.label}:</span> {f.value}
                </span>
              ))}
            </span>
            <span className="mt-0.5 flex items-center gap-1 text-[12px] text-[#027eb5]"><ExternalLink className="size-3" /> {t("open_record")}</span>
          </a>
        );
      })}
    </span>
  );
}
