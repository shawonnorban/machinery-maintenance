"use client";

import { useTransition } from "react";
import { usePathname, useRouter } from "next/navigation";
import { CircleAlert } from "lucide-react";
import { cn } from "@/lib/utils";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { ampm, clockFormat } from "@/lib/clock";

/*
 * Quality Tally's shared pieces (AnnotechERP 36): words, numbers, the panel,
 * the brand-headed table, labelled fields and selects, KPI cards, section and
 * action pills, filters written to the address, and one way to run a write.
 */

const SECTIONS = ["CUTTING", "SEWING", "QUALITY"];
const ACTIONS = ["RFT", "DEFECT", "RECTIFY", "REJECT"];

function useQt() {
  const t = useT("qtally");
  return (k, p) => t(k, p);
}

const num = (v) => (v === null || v === undefined ? "—" : Number(v).toLocaleString("en-US"));
const pct = (v) => (v === null || v === undefined ? "—" : `${Number(v).toLocaleString("en-US", { maximumFractionDigits: 2 })}%`);
const STAMP = clockFormat({ day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka" });
const stamp = (v) => (v ? STAMP.format(new Date(v)) : "");

/** Brickland writes dates 17-Oct-2026. */
function day(v) {
  if (!v) return "—";
  const [y, m, d] = v.slice(0, 10).split("-");
  const month = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"][Number(m) - 1];
  return `${d}-${month}-${y}`;
}

/** DHU bands the floor reads at a glance: ≤ 5 good, ≤ 10 watch, above that bad. */
function dhuTone(v) {
  if (v === null || v === undefined) return "text-foreground-muted";
  if (v <= 5) return "text-success";
  if (v <= 10) return "text-warning";
  return "text-danger";
}

function achievementTone(v) {
  if (v === null || v === undefined) return "text-foreground-muted";
  if (v >= 90) return "text-success";
  if (v >= 75) return "text-warning";
  return "text-danger";
}

/** Writes the filters to the address (the server re-reads the screen). */
function useFilters(query) {
  const router = useRouter();
  const pathname = usePathname();
  const [pending, start] = useTransition();
  const apply = (next) => {
    const merged = Object.fromEntries(Object.entries({ ...query, ...next }).filter(([, v]) => v !== "" && v !== null && v !== undefined));
    start(() => router.replace(`${pathname}${Object.keys(merged).length ? `?${new URLSearchParams(merged)}` : ""}`, { scroll: false }));
  };
  return { apply, pending };
}

/** Runs a write: toast, then refresh the screen. */
function useRun() {
  const router = useRouter();
  const toast = useToastManager();
  const p = useQt();
  const [pending, start] = useTransition();
  const run = (fn, success, after) =>
    start(async () => {
      const res = await fn();
      if (res?.status === "success") {
        if (success) toast.add({ title: typeof success === "function" ? success(res.data) : success, type: "success" });
        after?.(res);
        router.refresh();
      } else {
        toast.add({ title: res?.message ?? p("error"), type: "danger" });
        after?.(res);
      }
    });
  return { run, pending };
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

function Table({ columns, rows, empty, foot, rowKey = (r, i) => r.id ?? r.key ?? i, dense = false }) {
  const cell = dense ? "px-3 py-2" : "px-4 py-2.5";
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-max text-sm">
        <thead>
          <tr className="bg-brand text-left text-brand-foreground">
            {columns.map((c) => (
              <th key={c.key} className={cn(cell, "text-[13px] font-semibold whitespace-nowrap", c.align === "right" && "text-right", c.align === "center" && "text-center")}>
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
                  <td key={c.key} className={cn(cell, "align-top", c.align === "right" && "text-right tabular-nums", c.align === "center" && "text-center tabular-nums")}>
                    {c.render ? c.render(r) : (ampm(r[c.key]) ?? "—")}
                  </td>
                ))}
              </tr>
            ))
          )}
        </tbody>
        {foot && rows.length ? (
          <tfoot>
            <tr className="border-t-2 border-border-strong bg-surface-muted font-semibold">
              {columns.map((c) => (
                <td key={c.key} className={cn(cell, c.align === "right" && "text-right tabular-nums", c.align === "center" && "text-center tabular-nums")}>
                  {foot[c.key] ?? ""}
                </td>
              ))}
            </tr>
          </tfoot>
        ) : null}
      </table>
    </div>
  );
}

function Field({ label, required, error, hint, children, className }) {
  return (
    <label className={cn("block text-[13px] font-semibold text-foreground", className)}>
      {label}
      {required ? <span className="text-danger"> *</span> : null}
      <span className="mt-1.5 block font-normal">{children}</span>
      {error ? <span className="mt-1 block text-xs font-normal text-danger">{error}</span> : hint ? <span className="mt-1 block text-xs font-normal text-foreground-muted">{hint}</span> : null}
    </label>
  );
}

function Select({ value, onChange, options, placeholder, className, disabled }) {
  return (
    <select
      value={value ?? ""}
      disabled={disabled}
      onChange={(e) => onChange(e.target.value)}
      className={cn("block h-9 w-full min-w-40 rounded-sm border border-border-strong bg-surface px-2.5 text-sm text-foreground disabled:opacity-60", className)}
    >
      {placeholder !== undefined ? <option value="">{placeholder}</option> : null}
      {options.map((o) => (
        <option key={o.value} value={o.value} disabled={o.disabled}>
          {o.label}
        </option>
      ))}
    </select>
  );
}

function Kpi({ label, value, sub, tone = "brand", valueClass }) {
  const tones = { brand: "border-l-brand", success: "border-l-success", danger: "border-l-danger", warning: "border-l-warning", info: "border-l-info", muted: "border-l-border-strong" };
  return (
    <div className={cn("rounded-lg border border-l-4 border-border bg-surface p-4", tones[tone])}>
      <p className="text-xs font-semibold tracking-wide text-foreground-muted uppercase">{label}</p>
      <p className={cn("mt-2 text-2xl font-bold text-foreground tabular-nums", valueClass)}>{value}</p>
      {sub ? <p className="mt-1 text-[13px] text-foreground-muted">{sub}</p> : null}
    </div>
  );
}

function Notice({ children, tone = "warning", className }) {
  const tones = { warning: "border-warning/40 bg-warning-subtle text-warning", info: "border-info/40 bg-info-subtle text-info", danger: "border-danger/40 bg-danger-subtle text-danger" };
  return (
    <p className={cn("flex items-center gap-2 rounded-md border px-3 py-2 text-[13px] font-medium", tones[tone], className)}>
      <CircleAlert className="size-4 shrink-0" aria-hidden /> {children}
    </p>
  );
}

const SECTION_TONE = { CUTTING: "bg-info-subtle text-info", SEWING: "bg-brand-subtle text-brand-hover", QUALITY: "bg-success-subtle text-success" };
const ACTION_TONE = { RFT: "bg-success-subtle text-success", DEFECT: "bg-warning-subtle text-warning", RECTIFY: "bg-info-subtle text-info", REJECT: "bg-danger-subtle text-danger" };

function Pill({ tone, title, children }) {
  return (
    <span title={title} className={cn("inline-block rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap", tone)}>
      {children}
    </span>
  );
}

function SectionPill({ section }) {
  const p = useQt();
  return <Pill tone={SECTION_TONE[section]}>{p(`section.${section}`)}</Pill>;
}

function ActionPill({ action }) {
  const p = useQt();
  return <Pill tone={ACTION_TONE[action]}>{p(`action.${action}`)}</Pill>;
}

/** Label-over-value cells in a bordered grid (a report's header facts). */
function Facts({ items }) {
  return (
    <dl className="grid grid-cols-1 gap-px overflow-hidden rounded-md border border-border bg-border sm:grid-cols-2 lg:grid-cols-4">
      {items.map(([label, value], i) => (
        <div key={`${label}-${i}`} className="bg-surface px-4 py-3">
          <dt className="text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">{label}</dt>
          <dd className="mt-1 text-sm font-medium text-foreground">{value ?? "—"}</dd>
        </div>
      ))}
    </dl>
  );
}

/** A filter card: children get the values, Apply writes them to the address. */
function FilterCard({ children, onApply, pending, applyLabel, extra }) {
  return (
    <div className="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4">
      {children}
      <button
        type="button"
        onClick={onApply}
        disabled={pending}
        className="inline-flex h-9 items-center rounded-sm bg-brand px-4 text-sm font-semibold text-brand-foreground hover:bg-brand-hover disabled:opacity-60"
      >
        {applyLabel}
      </button>
      {extra}
    </div>
  );
}

export { SECTIONS, ACTIONS, useQt, num, pct, stamp, day, dhuTone, achievementTone, useFilters, useRun, Panel, Table, Field, Select, Kpi, Notice, Pill, SectionPill, ActionPill, FilterCard, Facts };
