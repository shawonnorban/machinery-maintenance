import Link from "next/link";
import { ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";
import { ampm, clockFormat } from "@/lib/clock";

/*
 * The platform console's pieces, in the AnnotechERP look (user, 2026-11-24:
 * the console was the old design): the page header with its icon tile, the
 * panel, the brand-headed table, KPI cards, a facts grid and dates written
 * the ERP way (17-Oct-2026) on the factory's clock, the same on the server
 * render and in the browser.
 */

const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
const STAMP = clockFormat({ day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka" });
const DAY = new Intl.DateTimeFormat("en-CA", { year: "numeric", month: "2-digit", day: "2-digit", timeZone: "Asia/Dhaka" });

/** 2026-10-17 or an ISO time → 17-Oct-2026 (a bare date is never moved by a zone). */
function fmtDate(v) {
  if (!v) return "—";
  const s = String(v);
  const ymd = /^\d{4}-\d{2}-\d{2}$/.test(s) ? s : DAY.format(new Date(s));
  const [y, m, d] = ymd.split("-");
  return `${d}-${MONTHS[Number(m) - 1]}-${y}`;
}

function fmtStamp(v) {
  return v ? STAMP.format(new Date(v)) : "—";
}

const TILE = {
  brand: "bg-brand-subtle text-brand",
  success: "bg-success-subtle text-success",
  warning: "bg-warning-subtle text-warning",
  danger: "bg-danger-subtle text-danger",
  info: "bg-info-subtle text-info",
};

/** Platform › … › Page, the icon on its tile, the title, one line of description. */
function PlatformHeader({ breadcrumb = [], title, description, icon, tone = "brand", badge, actions }) {
  const crumbs = [{ label: "Platform", href: "/platform" }, ...breadcrumb];
  return (
    <div className="flex flex-col gap-3 pb-5">
      <nav aria-label="Breadcrumb" className="flex flex-wrap items-center gap-1.5 text-[13px] text-foreground-muted">
        {crumbs.map((crumb, index) => (
          <span key={`${crumb.label}-${index}`} className="flex items-center gap-1.5">
            {index > 0 ? <ChevronRight className="size-3.5 text-foreground-subtle" /> : null}
            {crumb.href && index < crumbs.length - 1 ? (
              <Link href={crumb.href} className="hover:text-foreground">
                {crumb.label}
              </Link>
            ) : (
              <span className={index === crumbs.length - 1 ? "text-foreground" : undefined}>{crumb.label}</span>
            )}
          </span>
        ))}
      </nav>
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex min-w-0 items-start gap-4">
          {icon ? <span className={cn("flex size-11 shrink-0 items-center justify-center rounded-lg [&_svg]:size-5", TILE[tone] ?? TILE.brand)}>{icon}</span> : null}
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2.5">
              <h1 className="text-[26px] leading-tight font-bold tracking-tight text-foreground">{title}</h1>
              {badge}
            </div>
            {description ? <p className="mt-1 text-sm text-foreground-muted">{description}</p> : null}
          </div>
        </div>
        {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
      </div>
    </div>
  );
}

function Panel({ title, subtitle, icon: Icon, action, children, className, bodyClassName }) {
  return (
    <section className={cn("rounded-lg border border-border bg-surface", className)}>
      {title ? (
        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4">
          <div className="flex min-w-0 items-start gap-2.5">
            {Icon ? <Icon className="mt-0.5 size-4 shrink-0 text-foreground-muted" aria-hidden /> : null}
            <div className="min-w-0">
              <h2 className="text-[15px] font-semibold text-foreground">{title}</h2>
              {subtitle ? <p className="mt-0.5 text-[13px] text-foreground-muted">{subtitle}</p> : null}
            </div>
          </div>
          {action ? <div className="flex flex-wrap items-center gap-2">{action}</div> : null}
        </div>
      ) : null}
      <div className={bodyClassName}>{children}</div>
    </section>
  );
}

/** The ERP's brand-headed table. `columns`: { key, label, align?, render?, className? }. */
function PTable({ columns, rows, empty = "Nothing here yet.", rowKey = (r, i) => r.id ?? i, dense = false }) {
  const cell = dense ? "px-4 py-2" : "px-4 py-2.5";
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
                  <td key={c.key} className={cn(cell, "align-middle", c.align === "right" && "text-right tabular-nums", c.align === "center" && "text-center", c.className)}>
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

function Kpi({ label, value, sub, tone = "brand", icon }) {
  const bar = { brand: "border-l-brand", success: "border-l-success", danger: "border-l-danger", warning: "border-l-warning", info: "border-l-info", muted: "border-l-border-strong" };
  return (
    <div className={cn("rounded-lg border border-l-4 border-border bg-surface p-4", bar[tone] ?? bar.brand)}>
      <div className="flex items-start justify-between gap-2">
        <p className="text-xs font-semibold tracking-wide text-foreground-muted uppercase">{label}</p>
        {icon ? <span className={cn("flex size-8 items-center justify-center rounded-md [&_svg]:size-4", TILE[tone] ?? TILE.brand)}>{icon}</span> : null}
      </div>
      <p className="mt-2 text-2xl font-bold text-foreground tabular-nums">{value}</p>
      {sub ? <p className="mt-1 text-[13px] text-foreground-muted">{sub}</p> : null}
    </div>
  );
}

/** Label-over-value cells in a bordered grid (the ERP's facts box). */
function Facts({ items, columns = 4 }) {
  const cols = { 2: "sm:grid-cols-2", 3: "sm:grid-cols-3", 4: "sm:grid-cols-2 lg:grid-cols-4" };
  return (
    <dl className={cn("grid grid-cols-1 gap-px overflow-hidden rounded-md border border-border bg-border", cols[columns] ?? cols[4])}>
      {items.map(([label, value], i) => (
        <div key={`${label}-${i}`} className="bg-surface px-4 py-3">
          <dt className="text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">{label}</dt>
          <dd className="mt-1 text-sm font-medium text-foreground">{value ?? "—"}</dd>
        </div>
      ))}
    </dl>
  );
}

/** A panel's head inside an existing Card (the same look as Panel's). */
function PanelHead({ title, subtitle, icon: Icon, action, tone }) {
  return (
    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4">
      <div className="flex min-w-0 items-start gap-2.5">
        {Icon ? <Icon className={cn("mt-0.5 size-4 shrink-0", tone === "danger" ? "text-danger" : "text-foreground-muted")} aria-hidden /> : null}
        <div className="min-w-0">
          <h2 className={cn("text-[15px] font-semibold", tone === "danger" ? "text-danger" : "text-foreground")}>{title}</h2>
          {subtitle ? <p className="mt-0.5 text-[13px] text-foreground-muted">{subtitle}</p> : null}
        </div>
      </div>
      {action ? <div className="flex flex-wrap items-center gap-2">{action}</div> : null}
    </div>
  );
}

function Empty({ children }) {
  return <p className="px-5 py-8 text-center text-sm text-foreground-muted">{children}</p>;
}

export { fmtDate, fmtStamp, PlatformHeader, Panel, PanelHead, PTable, Kpi, Facts, Empty };
