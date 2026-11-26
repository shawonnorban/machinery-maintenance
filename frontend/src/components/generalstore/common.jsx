"use client";

import { cn } from "@/lib/utils";
import { printDocument } from "@/lib/print-document";
import { clockFormat } from "@/lib/clock";

/*
 * What the General Store screens share (AnnotechERP 10): the card, a labelled
 * field, money and quantities as Brickland writes them (৳ with lakh
 * grouping), the status pill, a tile, and the print window a voucher or the
 * stock list is printed from.
 */

const card = "rounded-xl border border-border bg-surface";

const dateFmt = new Intl.DateTimeFormat("en-GB", {
  day: "2-digit",
  month: "short",
  year: "numeric",
  timeZone: "Asia/Dhaka",
});
const stampFmt = clockFormat({
  day: "2-digit",
  month: "short",
  year: "numeric",
  hour: "2-digit",
  minute: "2-digit",
  timeZone: "Asia/Dhaka",
});

const day = (iso) => (iso ? dateFmt.format(new Date(iso)) : "—");
const stamp = (iso) => (iso ? stampFmt.format(new Date(iso)) : "—");
const num = (n) =>
  n === null || n === undefined || n === ""
    ? "—"
    : Number(n).toLocaleString("en-IN", { maximumFractionDigits: 4 });
const money = (n) =>
  n === null || n === undefined
    ? "—"
    : `৳${Number(n).toLocaleString("en-IN", { maximumFractionDigits: 2 })}`;
const dash = (v) => (v === null || v === undefined || v === "" ? "—" : v);
const messagesOf = (result) => result?.errors ?? { _: [result?.message ?? ""] };
const today = () =>
  new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(
    new Date(),
  );

/** A corrected field drops its message. */
const dropError = (all, key) =>
  all[key]
    ? Object.fromEntries(Object.entries(all).filter(([k]) => k !== key))
    : all;

function Field({ label, required, error, hint, children, className }) {
  return (
    <label className={cn("flex min-w-0 flex-col gap-1.5", className)}>
      <span className="text-[13px] font-semibold text-foreground">
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {children}
      {error ? (
        <span className="text-xs text-danger">{error}</span>
      ) : hint ? (
        <span className="text-xs text-foreground-muted">{hint}</span>
      ) : null}
    </label>
  );
}

const PILL = {
  POSTED: "bg-success-subtle text-success",
  CANCELLED: "bg-danger-subtle text-danger",
  IN_STOCK: "bg-success-subtle text-success",
  LOW: "bg-warning-subtle text-warning",
  OUT: "bg-danger-subtle text-danger",
};

function Pill({ tone, children }) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap",
        PILL[tone] ?? "bg-info-subtle text-info",
      )}
    >
      {children}
    </span>
  );
}

function Tile({ label, value, tone }) {
  return (
    <div className={cn(card, "px-4 py-3")}>
      <p className="text-[11px] font-semibold tracking-wide text-foreground-muted uppercase">
        {label}
      </p>
      <p
        className={cn(
          "mt-1 text-xl font-semibold tabular-nums text-foreground",
          tone === "warning" && "text-warning",
          tone === "danger" && "text-danger",
          tone === "success" && "text-success",
        )}
      >
        {value}
      </p>
    </div>
  );
}

/**
 * A voucher on paper (lib/print-document): the voucher name (Settings) heads
 * it, its facts and lines follow, the totals row (or a line of text) under
 * them, its footer closes it, and the usual three sign it. A wide table turns
 * the page to landscape; a list (`signatures: false`) has nobody to sign.
 *
 * @param {{ title: string, docNo?: string, org?: string, footer?: string, facts?: [string, string][], columns: { label: string, right?: boolean, nowrap?: boolean }[], rows: any[][], foot?: string|any[], signatures?: boolean }} doc
 */
function printWindow({
  title,
  docNo,
  org,
  footer,
  facts = [],
  columns,
  rows,
  foot,
  signatures = true,
}) {
  printDocument({
    title,
    docNo,
    company: org,
    orientation: columns.length > 8 ? "landscape" : "portrait",
    meta: facts.map(([k, v]) => [k, v === "—" ? null : v]),
    sections: [
      {
        columns: columns.map((c) => ({
          label: c.label,
          align: c.right ? "right" : undefined,
          nowrap: c.nowrap,
        })),
        rows,
        foot: Array.isArray(foot) ? [foot] : [],
        note: Array.isArray(foot) ? undefined : foot,
      },
    ],
    notes: [footer],
    signatures,
  });
}

export {
  card,
  day,
  stamp,
  num,
  money,
  dash,
  messagesOf,
  today,
  dropError,
  Field,
  Pill,
  Tile,
  printWindow,
};
