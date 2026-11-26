"use client";

import { Copy, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { useT } from "@/lib/i18n";

/*
 * What Knit Production → Yarn Procurement's sheets (AnnotechERP 15/02) share:
 * the card and field frames Brickland's desks are built of, a status chip,
 * and the yarn columns every line carries — Yarn Item, Count, Color, Brand,
 * UOM — with each line's Copy and Remove.
 */

const str = (v) => (v === null || v === undefined ? "" : String(v));
const num = (v) => (v === "" || v === null || v === undefined ? 0 : Number(v));
const qtyFmt = new Intl.NumberFormat("en-US", {
  minimumFractionDigits: 3,
  maximumFractionDigits: 3,
});
const moneyFmt = new Intl.NumberFormat("en-US", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});
const dayFmt = (d) =>
  d
    ? new Date(`${String(d).slice(0, 10)}T00:00:00Z`).toLocaleDateString(
        "en-GB",
        { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" },
      )
    : "—";
const todayIso = () =>
  new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(
    new Date(),
  );
// An error's field messages, or its message when it names no field.
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
let seq = 0;
const key = () => `l${++seq}`;

function useYarn() {
  const t = useT("knitting");
  return (k, p) => t(`yarn.${k}`, p);
}

/** Yarn Store's (15/03) own words. */
function useYs() {
  const t = useT("knitting");
  return (k, p) => t(`ystore.${k}`, p);
}

const card = "rounded-xl border border-border bg-surface";
const th = "px-2 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";

/** A select whose leading blank option ("-- Select Store --") is also what it shows while empty, as Brickland's do. */
function PSelect({ options, placeholder, ...rest }) {
  const first = options?.[0];
  return (
    <Select
      options={options}
      placeholder={
        placeholder ?? (first && first.value === "" ? first.label : undefined)
      }
      {...rest}
    />
  );
}

function Field({ label, required, hint, className, children }) {
  return (
    <label className={cn("flex flex-col gap-1.5", className)}>
      <span className="text-[13px] font-semibold text-foreground">
        {label}
        {required ? <span className="text-danger">*</span> : null}
      </span>
      {children}
      {hint ? (
        <span className="text-xs text-foreground-muted">{hint}</span>
      ) : null}
    </label>
  );
}

const TONES = {
  DRAFT: "bg-surface-muted text-foreground",
  WAITING_APPROVAL: "bg-warning-subtle text-warning",
  APPROVED: "bg-success-subtle text-success",
  FINALIZED: "bg-success-subtle text-success",
  PLACED: "bg-brand-subtle text-brand",
  ORDERED: "bg-brand-subtle text-brand",
  PART_RECEIVED: "bg-warning-subtle text-warning",
  RECEIVED: "bg-success-subtle text-success",
  REJECTED: "bg-danger-subtle text-danger",
  CANCELLED: "bg-danger-subtle text-danger",
  CLOSED: "bg-surface-muted text-foreground-muted",
  STORE_RECEIVE: "bg-surface-muted text-foreground",
  STORE_VERIFY: "bg-brand-subtle text-brand",
  AUDIT_VERIFY: "bg-warning-subtle text-warning",
  ACCOUNTS_APPROVE: "bg-success-subtle text-success",
  ACTIVE: "bg-success-subtle text-success",
  YES: "bg-success-subtle text-success",
  NO: "bg-surface-muted text-foreground-muted",
};

function StatusChip({ status, label }) {
  return (
    <span
      className={cn(
        "rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap",
        TONES[status] ?? TONES.DRAFT,
      )}
    >
      {label}
    </span>
  );
}

/** The empty grid's two lines. */
function NoLines({ title, hint }) {
  return (
    <div className="px-5 py-10 text-center">
      <p className="text-sm font-semibold text-foreground">{title}</p>
      <p className="mt-1 text-xs text-foreground-muted">{hint}</p>
    </div>
  );
}

const YARN_HEADS = ["composition", "count", "colour", "brand", "uom"];

/** A line's Yarn Item, Count, Color, Brand and UOM cells. */
/** `lot` adds the Lot # box between Brand and UOM, as the purchase order carries it. */
function YarnCells({ line, set, options, disabled, n, lot = false }) {
  const y = useYarn();
  const pick = (list) => [{ value: "", label: "—" }, ...list];
  return (
    <>
      <td className="px-2 py-1.5">
        <div className="w-44">
          <PSelect
            value={str(line.composition_id)}
            onValueChange={(v) => set({ composition_id: v ?? "" })}
            options={pick(options.compositions)}
            disabled={disabled}
            aria-label={`${y("col.composition")} ${n}`}
          />
        </div>
      </td>
      <td className="px-2 py-1.5">
        <div className="w-28">
          <PSelect
            value={str(line.yarn_count_id)}
            onValueChange={(v) => set({ yarn_count_id: v ?? "" })}
            options={pick(options.counts)}
            disabled={disabled}
            aria-label={`${y("col.count")} ${n}`}
          />
        </div>
      </td>
      <td className="px-2 py-1.5">
        <div className="w-40">
          <PSelect
            value={str(line.colour)}
            onValueChange={(v) => set({ colour: v ?? "" })}
            options={pick(options.colours.map((c) => ({ value: c, label: c })))}
            disabled={disabled}
            aria-label={`${y("col.colour")} ${n}`}
          />
        </div>
      </td>
      <td className="px-2 py-1.5">
        <Input
          value={str(line.brand)}
          onChange={(e) => set({ brand: e.target.value })}
          maxLength={80}
          className="h-8 w-28"
          disabled={disabled}
          aria-label={`${y("col.brand")} ${n}`}
        />
      </td>
      {lot ? (
        <td className="px-2 py-1.5">
          <Input
            value={str(line.lot_no)}
            onChange={(e) => set({ lot_no: e.target.value })}
            maxLength={40}
            className="h-8 w-24"
            disabled={disabled}
            aria-label={`${y("col.lot")} ${n}`}
          />
        </td>
      ) : null}
      <td className="px-2 py-1.5">
        <div className="w-24">
          <PSelect
            value={str(line.uom).toLowerCase()}
            onValueChange={(v) => set({ uom: v ?? "" })}
            options={pick(
              options.uoms.map((u) => ({
                value: u.value.toLowerCase(),
                label: u.label.toLowerCase(),
              })),
            )}
            disabled={disabled}
            aria-label={`${y("col.uom")} ${n}`}
          />
        </div>
      </td>
    </>
  );
}

/** A number typed into a line. */
function QtyInput({ value, onChange, disabled, label, w = "w-24" }) {
  return (
    <Input
      type="number"
      min="0"
      step="any"
      value={str(value)}
      onChange={(e) => onChange(e.target.value)}
      className={cn("h-8 text-right tabular-nums", w)}
      disabled={disabled}
      aria-label={label}
    />
  );
}

function DateInput({ value, onChange, disabled, label }) {
  return (
    <Input
      type="date"
      value={str(value)}
      onChange={(e) => onChange(e.target.value)}
      className="h-8 w-36"
      disabled={disabled}
      aria-label={label}
    />
  );
}

/** A line's Copy and Remove buttons. */
function LineButtons({ onCopy, onRemove, disabled, n }) {
  const y = useYarn();
  return (
    <>
      <td className="px-1 py-1.5">
        <Button
          variant="outline"
          size="icon"
          className="size-8"
          onClick={onCopy}
          disabled={disabled}
          aria-label={`${y("copy_line")} ${n}`}
        >
          <Copy />
        </Button>
      </td>
      <td className="px-1 py-1.5">
        <Button
          variant="outline"
          size="icon"
          className="size-8 text-danger"
          onClick={onRemove}
          disabled={disabled}
          aria-label={`${y("remove_line")} ${n}`}
        >
          <Trash2 />
        </Button>
      </td>
    </>
  );
}

/** The yarn fields a line sends, blanks as nothing. */
const yarnPayload = (l) => ({
  composition_id: l.composition_id || null,
  yarn_count_id: l.yarn_count_id || null,
  colour: l.colour || null,
  brand: l.brand || null,
  uom: l.uom || null,
  knit_start_date: l.knit_start_date || null,
  ship_date: l.ship_date || null,
  remarks: l.remarks || null,
});
const orNull = (v) =>
  v === "" || v === null || v === undefined ? null : Number(v);

export {
  PSelect,
  str,
  num,
  qtyFmt,
  moneyFmt,
  dayFmt,
  todayIso,
  messagesOf,
  key,
  useYarn,
  useYs,
  card,
  th,
  Field,
  StatusChip,
  NoLines,
  YARN_HEADS,
  YarnCells,
  QtyInput,
  DateInput,
  LineButtons,
  yarnPayload,
  orNull,
};
