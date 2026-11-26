/*
 * Order Summary (AnnotechERP 32): how a sheet cell reads — numbers with their
 * unit (2,840 Tkt), money ($1.69), dates the way the factory's Excel shows
 * them (26-Nov-26), days (-2 days), lists one per line — and the colour a
 * column's rules give it. Shared by the sheet, the reports and Column Setup.
 */

import { blank, num, text } from "@/lib/formula";

const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

/** 2026-11-26 → 26-Nov-26 */
function shortDate(value) {
  if (!value) return "";
  const [y, m, d] = String(value).slice(0, 10).split("-");
  return y && m && d ? `${d}-${MONTHS[Number(m) - 1]}-${y.slice(2)}` : String(value);
}

function grouped(n, decimals) {
  return Number(n).toLocaleString("en-US", { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

/** A pairs line's number with the column's unit: $2.48, 60 Pcs. */
function pairNumber(column, value) {
  const n = num(value);
  if (n === null) return "";
  const s = grouped(n, column.decimals ?? 0);
  return `${column.prefix ?? ""}${s}${column.suffix ? ` ${column.suffix}` : ""}`;
}

/** A pairs cell's lines as text: "Navy: 60 Pcs, White: 40 Pcs" / "BIO: $2.48, CONV: $2.30" (`plain`: numbers only, for Excel). */
function pairsText(column, value, plain = false) {
  if (!Array.isArray(value)) return text(value);
  return value
    .map((l) => {
      const n = plain ? (num(l?.value) === null ? "" : String(num(l.value))) : pairNumber(column, l?.value);
      const label = String(l?.label ?? "").trim();
      return label && n ? `${label}: ${n}` : label || n;
    })
    .filter(Boolean)
    .join(", ");
}

/** An Order Color cell's lines as text: "Navy (S, M, L), White" (`sep` between colours). */
function colorSizeText(value, sep = ", ") {
  if (!Array.isArray(value)) return text(value);
  return value
    .map((l) => {
      const color = String(l?.color ?? "").trim();
      const sizes = Array.isArray(l?.sizes) ? l.sizes.filter(Boolean) : [];
      return sizes.length ? `${color} (${sizes.join(", ")})` : color;
    })
    .filter(Boolean)
    .join(sep);
}

/** A value as its column shows it. */
function display(column, value) {
  if (blank(value)) return "";
  switch (column.type) {
    case "pairs":
      return pairsText(column, value);
    case "colorsize":
      return colorSizeText(value);
    case "money": {
      const n = num(value);
      return n === null ? text(value) : `${n < 0 ? "-" : ""}${column.prefix ?? "$"}${grouped(Math.abs(n), column.decimals ?? 2)}`;
    }
    case "number":
    case "percent": {
      const n = num(value);
      if (n === null) return text(value);
      const s = grouped(n, column.decimals ?? (column.type === "percent" ? 2 : 0));
      const suffix = column.suffix ?? (column.type === "percent" ? "%" : "");
      return `${column.prefix ?? ""}${s}${suffix === "%" ? "%" : suffix ? ` ${suffix}` : ""}`;
    }
    case "days": {
      const n = num(value);
      return n === null ? text(value) : `${grouped(n, 0)} days`;
    }
    case "date":
    case "system":
      return /^\d{4}-\d{2}-\d{2}/.test(String(value)) ? shortDate(value) : text(value);
    case "multi":
      return Array.isArray(value) ? value.join(", ") : text(value);
    default:
      return text(value);
  }
}

/** Plain text for copying out (Excel), Raw values not units. */
function copyText(column, value) {
  if (blank(value)) return "";
  if (column.type === "pairs") return pairsText(column, value, true);
  if (column.type === "colorsize") return colorSizeText(value, "; ");
  if (Array.isArray(value)) return value.join(", ");
  return text(value);
}

/** Cell colours — Excel's own conditional-format styles, as in the user's Order Summary sheet. */
const TONE = {
  green: "bg-[#c6efce] text-[#006100]", // Excel "Green Fill with Dark Green Text"
  yellow: "bg-[#ffeb9c] text-[#9c5700]", // "Yellow Fill with Dark Yellow Text"
  lightred: "bg-[#ffc7ce] text-[#9c0006]", // "Light Red Fill with Dark Red Text"
  red: "bg-[#ff0000] font-bold text-white",
  brightgreen: "bg-[#00b050] font-bold text-white",
  highlight: "bg-[#ffff00] font-bold text-[#ff0000]",
  peach: "bg-[#f6c6ad] text-[#111827]",
  blue: "bg-[#dceaf7] font-bold text-[#002060]",
  orange: "bg-[#f97316] text-white",
  pink: "bg-[#fce7f3] text-[#9d174d]",
  purple: "bg-[#a02b93] font-bold text-white",
  gray: "bg-[#e5e7eb] text-[#374151]",
  cyan: "bg-[#00b0f0] text-[#111827]",
};

/** A column's fixed text colour (Excel's font colours: TP Rcvd / Last Update orange, Reprint / Shipout dates green). */
const TEXT_TONE = {
  orange: "text-[#c04f15]",
  green: "text-[#275417]",
  navy: "font-bold text-[#002060]",
  red: "text-[#ff0000]",
};

/** The first colour rule a value meets, or null. */
function toneOf(column, value) {
  for (const r of column.color_rules ?? []) {
    if (matches(r, value)) return r.tone;
  }
  return null;
}

function matches(rule, value) {
  if (rule.op === "any") return !blank(value);
  if (rule.op === "blank") return blank(value);
  if (blank(value)) return false;
  const s = (Array.isArray(value) ? value.join(", ") : String(value)).toLowerCase();
  const want = String(rule.value ?? "").toLowerCase();
  const n = num(Array.isArray(value) ? null : value);
  const w = num(rule.value);
  const w2 = num(rule.value2);
  switch (rule.op) {
    case "eq":
      return n !== null && w !== null ? n === w : s === want;
    case "neq":
      return n !== null && w !== null ? n !== w : s !== want;
    case "contains":
      return s.includes(want);
    case "lt":
      return n !== null && w !== null && n < w;
    case "lte":
      return n !== null && w !== null && n <= w;
    case "gt":
      return n !== null && w !== null && n > w;
    case "gte":
      return n !== null && w !== null && n >= w;
    case "between":
      return n !== null && w !== null && w2 !== null && n >= w && n <= w2;
    default:
      return false;
  }
}

const GROUP_TONE = {
  green: "bg-[#bbf7d0]", yellow: "bg-[#fef08a]", lightred: "bg-[#ffc7ce]", red: "bg-[#fecaca]", brightgreen: "bg-[#86efac]", highlight: "bg-[#ffff00]", peach: "bg-[#f6c6ad]",
  blue: "bg-[#bfdbfe]", orange: "bg-[#fed7aa]", pink: "bg-[#ffdcff]", purple: "bg-[#ddd6fe]", gray: "bg-[#e5e7eb]", cyan: "bg-[#00b0f0]",
};

/** Norban's default view (its index page's starting columns). */
const DEFAULT_COLUMNS = [
  "serial_no", "buyer", "department", "order_type", "tp_rcvd_date", "artwork", "item", "style_number_name", "job_no", "last_update", "colors", "quantity_ticket", "quantity_pcs",
  "po_wise_ldd_etd", "po_wise_rfi_sd", "days_left_in_hand", "shipment_mode", "shipout_date", "shipout_condition", "shipment_status", "fabric_category", "fabric_type", "gsm",
  "fabric_details", "sustainability", "actual_leadtime_vs_confirm", "actual_po_issue_date", "act_leadtime_vs_po", "fob", "fob_value", "price_status", "short_order_status",
  "yarn_booking_date", "yarn_status", "fabric_status", "trims_status", "ffp_status", "prod_file_status", "garment_production_status", "rfi_plan_date",
  "reason_of_discount_penalty", "remarks", "budget_status",
];

const EDITABLE_TYPES = ["text", "longtext", "number", "money", "percent", "days", "date", "dropdown", "multi", "pairs", "colorsize", "image"];

export { display, copyText, colorSizeText, pairsText, pairNumber, toneOf, shortDate, TONE, GROUP_TONE, TEXT_TONE, DEFAULT_COLUMNS, EDITABLE_TYPES };
