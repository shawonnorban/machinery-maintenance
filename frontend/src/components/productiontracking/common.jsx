"use client";

import { useEffect, useState, useTransition } from "react";
import { usePathname, useRouter } from "next/navigation";
import { CircleAlert, Download, RefreshCw, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useT } from "@/lib/i18n";
import { ampm, clockFormat } from "@/lib/clock";

/*
 * Production Tracking's shared dashboard pieces (AnnotechERP 30): the period
 * bar (Today / Last 3 Days / Last 7 Days / Custom, with the screen's own
 * filters and Apply), the live bar (last update, next update, Live and
 * Refresh now — the page re-reads itself every refresh interval and turns
 * Stale past the stale-data limit), KPI cards, line cards and CSV export.
 */

const BAND = {
  ON_TRACK: { dot: "bg-success", text: "text-success", ring: "success" },
  ATTENTION: { dot: "bg-warning", text: "text-warning", ring: "warning" },
  BELOW: { dot: "bg-danger", text: "text-danger", ring: "danger" },
  NO_TARGET: { dot: "bg-foreground-subtle", text: "text-foreground-muted", ring: "muted" },
};

function usePt() {
  const t = useT("productiontracking");
  return (k, p) => t(k, p);
}

const num = (v) => (v === null || v === undefined ? "—" : Number(v).toLocaleString("en-US"));
// The factory's clock, the same on the server render and in the browser (no hydration mismatch).
const CLOCK = new Intl.DateTimeFormat("en-US", { hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka" });
const STAMP = clockFormat({ day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka" });
const clock = (v) => (v === null || v === undefined ? "—" : CLOCK.format(new Date(v)));
const stamp = (v) => (v ? STAMP.format(new Date(v)) : "");
const pct = (v) => (v === null || v === undefined ? "N/A" : `${Number(v).toLocaleString("en-US", { maximumFractionDigits: 1 })}%`);

/** Writes the filters to the address (the server re-reads the board). */
function useFilters(query) {
  const router = useRouter();
  const pathname = usePathname();
  const [pending, start] = useTransition();
  const apply = (next) => {
    const merged = Object.fromEntries(Object.entries({ ...query, ...next }).filter(([, v]) => v !== "" && v !== null && v !== undefined));
    if (merged.range !== "CUSTOM") {
      delete merged.from;
      delete merged.to;
    }
    start(() => router.replace(`${pathname}${Object.keys(merged).length ? `?${new URLSearchParams(merged)}` : ""}`, { scroll: false }));
  };
  return { apply, pending };
}

function Panel({ title, subtitle, icon: Icon, action, children, className }) {
  return (
    <section className={cn("rounded-lg border border-border bg-surface", className)}>
      {title ? (
        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4">
          <div className="flex items-start gap-2.5">
            {Icon ? <Icon className="mt-0.5 size-4 text-foreground-muted" aria-hidden /> : null}
            <div>
              <h2 className="text-[15px] font-semibold text-foreground">{title}</h2>
              {subtitle ? <p className="mt-0.5 text-[13px] text-foreground-muted">{subtitle}</p> : null}
            </div>
          </div>
          {action}
        </div>
      ) : null}
      {children}
    </section>
  );
}

/** Today / Last 3 Days / Last 7 Days / Custom, the screen's own filters, and Apply. */
function PeriodBar({ query, children, extra = {}, noPeriod = false }) {
  const p = usePt();
  const { apply, pending } = useFilters(query);
  const [range, setRange] = useState(query.range ?? "TODAY");
  const [from, setFrom] = useState(query.from ?? "");
  const [to, setTo] = useState(query.to ?? "");
  const [values, setValues] = useState(extra);
  const set = (k) => (v) => setValues((all) => ({ ...all, [k]: v }));

  return (
    <div className="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4">
      {noPeriod ? null : (
        <div>
          <p className="mb-1.5 text-[13px] font-semibold text-foreground">{p("f.date")}</p>
          <div className="flex rounded-md border border-border-strong p-0.5">
            {["TODAY", "3D", "7D", "CUSTOM"].map((r) => (
              <button
                key={r}
                type="button"
                onClick={() => setRange(r)}
                className={cn("rounded px-3 py-1.5 text-[13px] font-semibold", range === r ? "bg-brand text-brand-foreground" : "text-foreground-muted hover:text-foreground")}
              >
                {p(`range.${r}`)}
              </button>
            ))}
          </div>
        </div>
      )}
      {range === "CUSTOM" && !noPeriod ? (
        <>
          <label className="text-[13px] font-semibold text-foreground">
            {p("f.from")}
            <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="mt-1.5 w-40" />
          </label>
          <label className="text-[13px] font-semibold text-foreground">
            {p("f.to")}
            <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="mt-1.5 w-40" />
          </label>
        </>
      ) : null}
      {children?.({ values, set })}
      <Button type="button" loading={pending} onClick={() => apply({ range: noPeriod ? undefined : range, from, to, ...values })}>
        {p("apply")}
      </Button>
    </div>
  );
}

/** A labelled native select (filters are short lists). */
function FilterSelect({ label, value, onChange, options, all, className }) {
  return (
    <label className={cn("text-[13px] font-semibold text-foreground", className)}>
      {label}
      <select
        value={value ?? ""}
        onChange={(e) => onChange(e.target.value)}
        className="mt-1.5 block h-9 w-full min-w-40 rounded-sm border border-border-strong bg-surface px-2.5 text-sm font-normal text-foreground"
      >
        <option value="">{all}</option>
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </label>
  );
}

function FilterSearch({ label, value, onChange, placeholder }) {
  return (
    <label className="min-w-56 flex-1 text-[13px] font-semibold text-foreground">
      {label}
      <span className="relative mt-1.5 block">
        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-foreground-subtle" aria-hidden />
        <Input value={value ?? ""} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="pl-8" />
      </span>
    </label>
  );
}

/** Last update · Next · Live/Stale · Refresh now; re-reads the page every refresh interval. */
function LiveBar({ meta }) {
  const p = usePt();
  const router = useRouter();
  const [now, setNow] = useState(() => Date.now());
  const refreshed = new Date(meta.refreshed_at).getTime();
  useEffect(() => {
    const tick = setInterval(() => setNow(Date.now()), 5000);
    const refresh = setInterval(() => router.refresh(), Math.max(15, meta.refresh_seconds ?? 30) * 1000);
    return () => {
      clearInterval(tick);
      clearInterval(refresh);
    };
  }, [router, meta.refresh_seconds]);
  const stale = now - refreshed > (meta.stale_seconds ?? 180) * 1000;
  const time = clock;

  return (
    <div className="mb-4 flex flex-wrap items-center justify-end gap-3 text-[13px] text-foreground-muted">
      <span className="inline-flex items-center gap-1.5">
        <RefreshCw className="size-3.5" aria-hidden /> {p("live.last")} <b className="text-foreground">{time(refreshed)}</b>
      </span>
      <span>
        {p("live.next")} {time(refreshed + (meta.refresh_seconds ?? 30) * 1000)}
      </span>
      <span className={cn("inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 font-semibold", stale ? "bg-warning-subtle text-warning" : "bg-success-subtle text-success")}>
        <span className={cn("size-1.5 rounded-full", stale ? "bg-warning" : "bg-success")} /> {p(stale ? "live.stale" : "live.live")}
      </span>
      <button type="button" onClick={() => router.refresh()} className="font-semibold text-brand hover:underline">
        {p("live.refresh")}
      </button>
    </div>
  );
}

function Kpi({ label, value, sub, tone = "brand", icon: Icon }) {
  const tones = { brand: "border-l-brand", success: "border-l-success", danger: "border-l-danger", warning: "border-l-warning", info: "border-l-info", muted: "border-l-border-strong" };
  return (
    <div className={cn("rounded-lg border border-l-4 border-border bg-surface p-4", tones[tone])}>
      <div className="flex items-start justify-between gap-2">
        <p className="text-xs font-semibold tracking-wide text-foreground-muted uppercase">{label}</p>
        {Icon ? <Icon className="size-4 text-foreground-subtle" aria-hidden /> : null}
      </div>
      <p className="mt-2 text-3xl font-bold text-foreground tabular-nums">{value}</p>
      {sub ? <p className="mt-1 text-[13px] text-foreground-muted">{sub}</p> : null}
    </div>
  );
}

function Notice({ children, tone = "warning" }) {
  const tones = { warning: "border-warning/40 bg-warning-subtle text-warning", info: "border-info/40 bg-info-subtle text-info" };
  return (
    <p className={cn("mb-4 flex items-center gap-2 rounded-md border px-3 py-2 text-[13px] font-medium", tones[tone])}>
      <CircleAlert className="size-4 shrink-0" aria-hidden /> {children}
    </p>
  );
}

/** The ring on a line card: the achievement %, coloured by band. */
function Ring({ value, band }) {
  const size = 52;
  const stroke = 5;
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const v = Math.max(0, Math.min(100, value ?? 0));
  const color = { ON_TRACK: "var(--color-success)", ATTENTION: "var(--color-warning)", BELOW: "var(--color-danger)", NO_TARGET: "var(--color-border-strong)" }[band];
  return (
    <svg width={size} height={size} className="shrink-0" role="img" aria-label={pct(value)}>
      <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke="var(--color-border)" strokeWidth={stroke} />
      <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke={color} strokeWidth={stroke} strokeDasharray={c} strokeDashoffset={c - (v / 100) * c} strokeLinecap="round" transform={`rotate(-90 ${size / 2} ${size / 2})`} />
      <text x="50%" y="54%" textAnchor="middle" dominantBaseline="middle" className="fill-foreground text-[11px] font-bold">
        {value === null || value === undefined ? "N/A" : `${Math.round(value)}%`}
      </text>
    </svg>
  );
}

/** One line's card: code, style, ring, target / actual / balance, reject and rework. */
function LineCard({ line, showDefects = true, big = false }) {
  const p = usePt();
  const band = BAND[line.band] ?? BAND.NO_TARGET;
  return (
    <div className={cn("rounded-lg border border-border bg-surface p-4", line.band === "BELOW" && "border-danger/50", line.band === "ON_TRACK" && "border-success/40")}>
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className={cn("font-bold text-foreground", big ? "text-2xl" : "text-lg")}>{line.code}</p>
          <p className="truncate text-[13px] text-foreground-muted">
            {p("card.style")}: <span className="font-semibold text-foreground">{line.style || "—"}</span>
          </p>
        </div>
        <Ring value={line.pct} band={line.band} />
      </div>
      <div className="mt-3 grid grid-cols-3 gap-2">
        {[
          ["target", line.target, "text-brand"],
          ["actual", line.actual, "text-success"],
          ["balance", line.balance, line.balance > 0 ? "text-danger" : "text-foreground"],
        ].map(([k, v, cls]) => (
          <div key={k} className="rounded-md bg-surface-muted px-2 py-2 text-center">
            <p className="text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">{p(`card.${k}`)}</p>
            <p className={cn("font-bold tabular-nums", big ? "text-3xl" : "text-xl", cls)}>{num(v)}</p>
          </div>
        ))}
      </div>
      {line.target === null ? <p className="mt-2 rounded-md bg-warning-subtle px-2 py-1 text-center text-xs font-semibold text-warning">{p("card.no_target")}</p> : null}
      <div className="mt-2 flex items-center justify-between text-xs">
        {showDefects ? (
          <span>
            <span className="text-danger">
              {p("card.reject")} {num(line.reject)}
            </span>{" "}
            |{" "}
            <span className="text-warning">
              {p("card.rework")} {num(line.rework)}
            </span>
          </span>
        ) : (
          <span />
        )}
        <span className={cn("font-semibold", band.text)}>{p(`band.${line.band}`)}</span>
      </div>
    </div>
  );
}

function Legend({ meta }) {
  const p = usePt();
  return (
    <span className="flex flex-wrap items-center gap-3 text-xs text-foreground-muted">
      {["ON_TRACK", "ATTENTION", "BELOW", "NO_TARGET"].map((b) => (
        <span key={b} className="inline-flex items-center gap-1.5">
          <span className={cn("size-2 rounded-full", BAND[b].dot)} />
          {p(`legend.${b}`, { on: meta?.on_track_pct ?? 90, below: meta?.below_pct ?? 75 })}
        </span>
      ))}
    </span>
  );
}

/** Rows to a CSV file the browser saves. */
function CsvButton({ rows, columns, name }) {
  const p = usePt();
  const save = () => {
    const esc = (v) => `"${String(v ?? "").replaceAll('"', '""')}"`;
    const lines = [columns.map((c) => esc(c.label)).join(","), ...rows.map((r) => columns.map((c) => esc(r[c.key])).join(","))];
    const url = URL.createObjectURL(new Blob([`﻿${lines.join("\n")}`], { type: "text/csv;charset=utf-8" }));
    const a = document.createElement("a");
    a.href = url;
    a.download = `${name}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };
  return (
    <Button type="button" variant="outline" size="sm" onClick={save} disabled={rows.length === 0}>
      <Download /> {p("csv")}
    </Button>
  );
}

/** A plain table with Brickland's indigo head. */
function Table({ columns, rows, empty, rowKey = (r, i) => r.id ?? r.key ?? i }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-max text-sm">
        <thead>
          <tr className="bg-brand text-left text-brand-foreground">
            {columns.map((c) => (
              <th key={c.key} className={cn("px-4 py-2.5 text-[13px] font-semibold whitespace-nowrap", c.align === "right" && "text-right")}>
                {c.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr>
              <td colSpan={columns.length} className="px-4 py-10 text-center text-foreground-muted">
                {empty}
              </td>
            </tr>
          ) : (
            rows.map((r, i) => (
              <tr key={rowKey(r, i)} className="border-b border-border last:border-0 hover:bg-surface-muted/50">
                {columns.map((c) => (
                  <td key={c.key} className={cn("px-4 py-2.5 align-top", c.align === "right" && "text-right tabular-nums")}>
                    {c.render ? c.render(r) : (ampm(r[c.key]) ?? "—")}
                  </td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}

export { BAND, usePt, num, pct, clock, stamp, useFilters, Panel, PeriodBar, FilterSelect, FilterSearch, LiveBar, Kpi, Notice, Ring, LineCard, Legend, CsvButton, Table };
