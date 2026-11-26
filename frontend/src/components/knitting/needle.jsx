"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  AlertTriangle,
  Ban,
  BadgeCheck,
  CheckCheck,
  ClipboardCheck,
  DownloadCloud,
  Lock,
  PackageCheck,
  Pencil,
  Printer,
  RotateCcw,
  Save,
  Search,
  Send,
  ShieldCheck,
  Truck,
  Undo2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";
import { printDocument } from "@/lib/print-document";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import {
  PSelect,
  str,
  num,
  qtyFmt,
  moneyFmt,
  dayFmt,
  todayIso,
  messagesOf,
  card,
  Field,
  StatusChip,
  NoLines,
  orNull,
} from "./yarn-common";
import { ScTable } from "./prod-sc";
import { TONE, pick } from "./prod-yd";
import {
  useShowFilters,
  useMoves,
  ListShell,
  MoveButton,
  FormHead,
  FormFoot,
  RemoveCell,
} from "./ystore-yd";
import {
  enc,
  has,
  sum,
  clean,
  inRange,
  docCol,
  qtyCol,
  DateRange,
  useDoc,
  Errors,
  Locked,
  Existing,
  Cell,
  LineSelect,
  LineText,
  LineNum,
  GridHead,
  GridCard,
  PickPanel,
} from "./prod-cc";

/*
 * Knit Production → Needle Store (AnnotechERP 15/07): the needle and sinker
 * book — Needle Opening Balance and Needle Loan Opening Balance, Machine
 * Needle Receipt (with Item Wise Consumptions) and its Return Register,
 * Machine-Wise Needle Assignment, Broken Needle, Indent and Issue, Needle
 * Loan Indent and Needle Stock Closing. Items, categories and stores are
 * the General Store's. A paper travels by its number; nothing reloads.
 */

const BASE = {
  loanOpening: "/knit-production/mc-needle-loan-opening",
  loan: "/knit-production/mc-needle-loan-requisition",
  receipt: "/knit-production/mc-needle-receive",
  opening: "/knit-production/mc-needle-opening-balance",
  assign: "/knit-production/mc-wise-needle-assign",
  broken: "/knit-production/mc-wise-needle-broken",
  req: "/knit-production/mc-wise-needle-requisition",
  issue: "/knit-production/mc-wise-needle-issue",
  closing: "/knit-production/mc-needle-closing",
  ret: "/knit-production/mc-needle-receive-return-list",
};

function useNd() {
  const t = useT("knitting");
  // Requisition types are the yarn store's own list.
  return (k, p) =>
    k.startsWith("req_type.") ? t(`ystore.${k}`, p) : t(`nd.${k}`, p);
}

/** A loan indent screen with its direction fixed reads its words as lo (lent out) or lr (borrowed, going back). */
const dir = (kind) => (kind === "LOAN_RETURN" ? "lr" : "lo");
const listOpts = (c, list, group) =>
  list.map((s) => ({ value: s, label: c(`${group}.${s}`) }));
const itemOf = (options, id) => options.items.find((i) => i.value === id);
const itemsIn = (options, cat) =>
  options.items.filter((i) => !cat || i.category_id === cat);
const machinesOf = (options, office, type) =>
  options.machines.filter(
    (m) => (!office || m.office_id === office) && (!type || m.type_id === type),
  );

/* ---------------- prints ---------------- */

function linesSection(c, title, lines, cols, foot) {
  return {
    title,
    columns: [
      { label: "#", align: "center", width: "4%" },
      ...cols.map(([label, , right]) => ({
        label,
        align: right ? "right" : undefined,
      })),
    ],
    rows: lines.map((l, i) => [i + 1, ...cols.map(([, get]) => get(l) ?? "—")]),
    foot: foot ? [["", ...foot]] : undefined,
    empty: c("empty.lines"),
  };
}

const q3 = (n) => qtyFmt.format(n ?? 0);

function printReceipt(c, d) {
  printDocument({
    title: c("p.receipt"),
    docNo: d.mrr_no,
    orientation: "landscape",
    meta: [
      [c("f.mrr_date"), dayFmt(d.mrr_date)],
      [c("f.company"), d.company],
      [c("f.store"), d.store],
      [c("f.supplier"), d.supplier],
      [c("p.address"), d.supplier_address],
      [c("f.challan_no"), d.challan_no],
      [c("f.challan_date"), d.challan_date ? dayFmt(d.challan_date) : null],
      [c("f.pay_method"), d.pay_method ? c(`pay.${d.pay_method}`) : null],
      [c("f.lc_no"), d.lc_no],
      [c("f.status"), { v: c(`receipt_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 4,
    sections: [
      linesSection(
        c,
        c("sec.item_list"),
        d.lines,
        [
          [c("col.item"), (l) => ({ v: l.item, bold: true })],
          [c("col.uom"), (l) => l.uom],
          [c("col.challan_qty"), (l) => q3(l.challan_qty), true],
          [c("col.received_qty"), (l) => q3(l.received_qty), true],
          [c("col.rate"), (l) => moneyFmt.format(l.rate), true],
          [c("col.amount"), (l) => moneyFmt.format(l.amount), true],
          [c("col.remarks"), (l) => l.remarks],
        ],
        [
          "",
          c("f.total"),
          q3(sum(d.lines, (l) => l.challan_qty)),
          q3(sum(d.lines, (l) => l.received_qty)),
          "",
          moneyFmt.format(sum(d.lines, (l) => l.amount)),
          "",
        ],
      ),
    ],
    notes: [d.remarks],
    signatures: [
      c("p.received_by"),
      c("p.checked"),
      c("p.store_in_charge"),
      c("p.accounts"),
    ],
  });
}

function printReturn(c, d) {
  printDocument({
    title: c("p.return"),
    docNo: d.challan_no,
    orientation: "landscape",
    meta: [
      [c("f.challan_date"), dayFmt(d.challan_date)],
      [c("f.mrr_no"), d.mrr_no],
      [c("f.return_type"), c(`return_type.${d.challan_type}`)],
      [c("f.company"), d.company],
      [c("f.store"), d.store],
      [c("f.supplier"), d.supplier],
      [c("p.address"), d.supplier_address],
      [c("f.gate_pass"), d.gate_pass_no],
      [c("f.vehicle"), d.vehicle_no],
      [c("f.status"), { v: c(`return_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 4,
    sections: [
      linesSection(
        c,
        c("sec.item_list"),
        d.lines,
        [
          [c("col.item"), (l) => ({ v: l.item, bold: true })],
          [c("col.uom"), (l) => l.uom],
          [c("col.return_qty"), (l) => q3(l.qty), true],
          [c("col.rate"), (l) => moneyFmt.format(l.rate), true],
          [c("col.amount"), (l) => moneyFmt.format(l.amount), true],
          [c("col.remarks"), (l) => l.remarks],
        ],
        [
          "",
          c("f.total"),
          q3(sum(d.lines, (l) => l.qty)),
          "",
          moneyFmt.format(sum(d.lines, (l) => l.amount)),
          "",
        ],
      ),
    ],
    notes: [d.reason],
    signatures: [
      c("p.issued_by"),
      c("p.carried_by"),
      c("p.security"),
      c("p.party_ack"),
    ],
  });
}

function printLoan(c, d) {
  printDocument({
    title: c(`p.loan_${d.book === "OIL" ? "OIL_" : ""}${d.kind}`),
    docNo: d.req_no,
    orientation: "landscape",
    meta: [
      [c("f.req_date"), dayFmt(d.req_date)],
      [c("f.company"), d.company],
      [c("f.office"), d.office],
      [c("f.department"), d.department],
      [c("f.party"), d.party],
      [c("p.address"), d.party_address],
      [c("f.store"), d.store],
      [c("f.reason"), d.reason],
      [c("f.status"), { v: c(`req_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 3,
    sections: [
      linesSection(
        c,
        c("sec.item_list"),
        d.lines,
        [
          [c("col.item"), (l) => ({ v: l.item, bold: true })],
          [c("col.uom"), (l) => l.uom],
          [c("col.required_qty"), (l) => q3(l.required_qty), true],
          [c("col.issue_qty"), (l) => q3(l.issue_qty), true],
          [c("col.rate"), (l) => moneyFmt.format(l.rate), true],
          [c("col.remarks"), (l) => l.remarks],
        ],
        [
          "",
          c("f.total"),
          q3(sum(d.lines, (l) => l.required_qty)),
          q3(sum(d.lines, (l) => l.issue_qty)),
          "",
          "",
        ],
      ),
    ],
    notes: [d.remarks],
    signatures: [
      c("p.requested_by"),
      c("p.verified_by"),
      c("p.store_in_charge"),
      c("p.party_ack"),
    ],
  });
}

function printReq(c, d) {
  printDocument({
    title: c("p.req"),
    docNo: d.req_no,
    orientation: "landscape",
    meta: [
      [c("f.req_date"), dayFmt(d.req_date)],
      [c("f.reason"), c(`reason.${d.reason}`)],
      [c("f.broken"), d.broken_no],
      [c("f.company"), d.company],
      [c("f.office"), d.office],
      [c("f.from_store"), d.from_store],
      [c("f.to_store"), d.to_store],
      [c("f.status"), { v: c(`req_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 4,
    sections: [
      linesSection(
        c,
        c("sec.item_list"),
        d.lines,
        [
          [c("col.machine"), (l) => l.machine],
          [c("col.item"), (l) => ({ v: l.item, bold: true })],
          [c("col.uom"), (l) => l.uom],
          [c("col.req_qty"), (l) => q3(l.req_qty), true],
          [c("col.issued"), (l) => q3(l.issued), true],
          [c("col.rate"), (l) => moneyFmt.format(l.rate), true],
          [c("col.remarks"), (l) => l.remarks],
        ],
        [
          "",
          "",
          c("f.total"),
          q3(sum(d.lines, (l) => l.req_qty)),
          q3(sum(d.lines, (l) => l.issued)),
          "",
          "",
        ],
      ),
    ],
    notes: [d.remarks],
    signatures: [
      c("p.requested_by"),
      c("p.verified_by"),
      c("p.store_in_charge"),
    ],
  });
}

function printIssue(c, d) {
  printDocument({
    title: c("p.issue"),
    docNo: d.issue_no,
    orientation: "landscape",
    meta: [
      [c("f.issue_date"), dayFmt(d.issue_date)],
      [c("f.req_no"), d.req_no],
      [c("f.company"), d.company],
      [c("p.released_by_store"), d.to_store],
      [c("p.received_by_store"), d.from_store],
      [c("f.status"), { v: c(`issue_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 3,
    sections: [
      linesSection(
        c,
        c("sec.item_list"),
        d.lines,
        [
          [c("col.machine"), (l) => l.machine],
          [c("col.item"), (l) => ({ v: l.item, bold: true })],
          [c("col.uom"), (l) => l.uom],
          [c("col.req_qty"), (l) => q3(l.req_qty), true],
          [c("col.iss_qty"), (l) => q3(l.qty), true],
          [c("col.remarks"), (l) => l.remarks],
        ],
        [
          "",
          "",
          c("f.total"),
          q3(sum(d.lines, (l) => l.req_qty)),
          q3(sum(d.lines, (l) => l.qty)),
          "",
        ],
      ),
    ],
    notes: [d.remarks],
    signatures: [c("p.issued_by"), c("p.received_by"), c("p.store_in_charge")],
  });
}

function printBroken(c, d) {
  printDocument({
    title: c("p.broken"),
    docNo: d.ref_no,
    orientation: "landscape",
    meta: [
      [c("f.date"), dayFmt(d.broken_date)],
      [c("f.shift"), d.shift],
      [c("f.company"), d.company],
      [c("f.office"), d.office],
      [c("f.machine_type"), d.machine_type],
      [c("f.status"), { v: c(`broken_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 3,
    sections: [
      linesSection(
        c,
        c("sec.machine_broken"),
        d.lines,
        [
          [c("col.machine"), (l) => ({ v: l.machine, bold: true })],
          [c("col.item"), (l) => l.item],
          [c("col.uom"), (l) => l.uom],
          [c("col.qty_pcs"), (l) => q3(l.qty), true],
          [c("col.cost_price"), (l) => moneyFmt.format(l.cost_price), true],
          [c("col.amount"), (l) => moneyFmt.format(l.amount), true],
          [c("col.remarks"), (l) => l.remarks],
        ],
        [
          "",
          "",
          c("f.total"),
          q3(sum(d.lines, (l) => l.qty)),
          "",
          moneyFmt.format(sum(d.lines, (l) => l.amount)),
          "",
        ],
      ),
    ],
    notes: [d.remarks],
    signatures: [c("p.operator"), c("p.supervisor"), c("p.maintenance")],
  });
}

/* ---------------- shared cells ---------------- */

/** Category → Item → MoU: picking an item brings its unit and price. */
function ItemCells({ d, l, n, options, priceKey = "rate" }) {
  const { c, setL, open } = d;
  return (
    <>
      <LineSelect
        value={l.category_id}
        onChange={(v) => setL(l.key, { category_id: v, item_id: "" })}
        options={options.categories}
        label={`${c("col.category")} ${n}`}
        disabled={!open}
        width="min-w-[170px]"
      />
      <LineSelect
        value={l.item_id}
        onChange={(v) => {
          const it = itemOf(options, v);
          setL(l.key, {
            item_id: v,
            category_id: l.category_id || str(it?.category_id),
            uom: it?.uom || l.uom,
            ...(priceKey && !l[priceKey] && it?.price
              ? { [priceKey]: String(it.price) }
              : {}),
          });
        }}
        options={itemsIn(options, l.category_id)}
        label={`${c("col.item")} ${n}`}
        disabled={!open}
        width="min-w-[230px]"
      />
      <LineSelect
        value={l.uom}
        onChange={(v) => setL(l.key, { uom: v })}
        options={options.uoms}
        label={`${c("col.uom")} ${n}`}
        disabled={!open}
        width="min-w-[90px]"
      />
    </>
  );
}

const withCategory = (options) => (l) => ({
  ...l,
  category_id:
    str(l.category_id) || str(itemOf(options, l.item_id)?.category_id),
});

function Select({
  c,
  label,
  value,
  onChange,
  options,
  ph = "ph.select",
  disabled,
  required,
  hint,
}) {
  return (
    <Field label={c(label)} required={required} hint={hint}>
      <PSelect
        value={value}
        onValueChange={(v) => onChange(v ?? "")}
        options={pick(options, c(ph))}
        disabled={disabled}
        aria-label={c(label)}
      />
    </Field>
  );
}

function Text({
  c,
  label,
  value,
  onChange,
  disabled,
  max = 60,
  placeholder,
  type = "text",
  required,
  hint,
}) {
  return (
    <Field label={c(label)} required={required} hint={hint}>
      <Input
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        maxLength={type === "text" ? max : undefined}
        placeholder={placeholder}
        disabled={disabled}
        aria-label={c(label)}
      />
    </Field>
  );
}

function CompanyField({ c, options }) {
  return (
    <Field label={c("f.company")} required>
      <PSelect
        value={options.company.value}
        options={[options.company]}
        disabled
        aria-label={c("f.company")}
      />
    </Field>
  );
}

function Remarks({ c, value, onChange, disabled, label = "f.remarks" }) {
  return (
    <Field label={c(label)} className="mt-4">
      <Textarea
        rows={2}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        maxLength={2000}
        disabled={disabled}
        aria-label={c(label)}
      />
    </Field>
  );
}

function NoField({ c, label, value }) {
  return (
    <Field label={c(label)} hint={c("hint.issued_on_save")}>
      <Input
        value={value ?? ""}
        placeholder={c("ph.auto")}
        disabled
        aria-label={c(label)}
      />
    </Field>
  );
}

function Status({ c, foot }) {
  return (
    <p className="mt-3 text-[13px] text-foreground-muted">
      <span className="font-semibold text-foreground">
        {c("action_status")}
      </span>{" "}
      {foot}
    </p>
  );
}

/** List rows: a step button shown while the row's status allows it. */
function makeStep(c, canManage, move, pending, idKey, act) {
  return function step(r, from, action, icon, danger = false) {
    return canManage && from.includes(r.status) ? (
      <MoveButton
        icon={icon}
        label={c(`btn.${action}`)}
        no={r[idKey]}
        onClick={() => move(r[idKey], action, act)}
        disabled={pending}
        danger={danger}
      />
    ) : null;
  };
}

function PrintButton({ c, no, onClick, disabled }) {
  return (
    <MoveButton
      icon={Printer}
      label={c("btn.print")}
      no={no}
      onClick={onClick}
      disabled={disabled}
    />
  );
}

/* ---------------- loan opening ---------------- */

function LoanOpeningList({
  options,
  initialRows,
  canManage,
  book = "NEEDLE",
  base = BASE.loanOpening,
}) {
  const c = useNd();
  const flt = useShowFilters({ party: "", item: "", store: "" });
  const o = flt.on;
  const shown = initialRows.filter(
    (r) =>
      (!o.party || r.party_id === o.party) &&
      (!o.item || r.item_id === o.item) &&
      (!o.store || r.store_id === o.store),
  );
  return (
    <ListShell
      c={c}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <Select
            c={c}
            label="f.party"
            value={flt.f.party}
            onChange={(v) => flt.set("party", v)}
            options={options.parties}
          />
          <Select
            c={c}
            label="f.item"
            value={flt.f.item}
            onChange={(v) => flt.set("item", v)}
            options={options.items}
          />
          <Select
            c={c}
            label="f.store"
            value={flt.f.store}
            onChange={(v) => flt.set("store", v)}
            options={options.stores}
          />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.loan_opening_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          {
            key: "ob_date",
            label: c("col.ob_date"),
            nowrap: true,
            text: (r) => dayFmt(r.ob_date),
          },
          { key: "party", label: c("col.party") },
          { key: "item", label: c("col.item") },
          { key: "uom", label: c("col.uom"), text: (r) => r.uom ?? "—" },
          qtyCol(c, "loan_received"),
          qtyCol(c, "loan_issued"),
          qtyCol(c, "net"),
          { key: "store", label: c("col.store"), text: (r) => r.store ?? "—" },
          {
            key: "cost_price",
            label: c("col.cost_price"),
            num: true,
            text: (r) => moneyFmt.format(r.cost_price),
          },
          {
            key: "is_final",
            label: c("col.status"),
            render: (r) => (
              <StatusChip
                status={r.is_final ? "APPROVED" : "DRAFT"}
                label={c(r.is_final ? "final" : "open_position")}
              />
            ),
            text: (r) => c(r.is_final ? "final" : "open_position"),
          },
        ]}
        exportName={`${book.toLowerCase()}-loan-opening`}
        addHref={canManage ? `${base}?tab=entry` : null}
        empty={
          <NoLines title={c("empty.loan_openings")} hint={c("empty.hint")} />
        }
        actions={(r) =>
          canManage && !r.is_final ? (
            <Link
              href={`${base}?tab=entry&party=${enc(r.party_code)}&item=${enc(r.item_code)}`}
              className={buttonVariants({ variant: "outline", size: "sm" })}
              aria-label={`${c("btn.edit")} ${r.item}`}
            >
              <Pencil /> {c("btn.edit")}
            </Link>
          ) : null
        }
      />
    </ListShell>
  );
}

const LOAN_OB = [
  "ob_date",
  "party_id",
  "category_id",
  "item_id",
  "uom",
  "loan_received",
  "loan_issued",
  "store_id",
  "cost_price",
  "remarks",
];

function LoanOpeningForm({
  options,
  initial,
  canManage,
  act,
  book = "NEEDLE",
}) {
  const c = useNd();
  // The oil book's position reads its own words where the needle's would not fit.
  const w = (k) => c(book === "OIL" ? `${k}_OIL` : k);
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const blank = (d) => ({
    ...Object.fromEntries(LOAN_OB.map((k) => [k, str(d?.[k])])),
    ob_date: str(d?.ob_date) || today,
    is_final: Boolean(d?.is_final),
  });
  const [id, setId] = useState(initial?.id ?? null);
  const [h, setHead] = useState(() => blank(initial));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && !initial?.is_final;
  const setH = (patch) => setHead((x) => ({ ...x, ...patch }));
  function clear() {
    setId(null);
    setHead(blank(null));
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }
  function save() {
    startTransition(async () => {
      const { category_id: _c, ...rest } = h;
      const res = await act(
        id ? `loan-openings/${enc(id)}` : "loan-openings",
        id ? "PUT" : "POST",
        {
          ...clean(rest),
          book,
          is_final: h.is_final,
          loan_received: orNull(h.loan_received),
          loan_issued: orNull(h.loan_issued),
          cost_price: orNull(h.cost_price),
        },
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      toastManager.add({ title: c("done.loan_opening"), type: "success" });
      clear();
    });
  }
  const it = itemOf(options, h.item_id);
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={c("sec.loan_position")}
          hint={w("hint.loan_opening")}
          doc={null}
        />
        <Errors errors={errors} />
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Text
            c={c}
            label="f.ob_date"
            type="date"
            value={h.ob_date}
            onChange={(v) => setH({ ob_date: v })}
            disabled={!open}
            required
          />
          <Select
            c={c}
            label="f.supplier_name"
            value={h.party_id}
            onChange={(v) => setH({ party_id: v })}
            options={options.parties}
            ph="ph.supplier"
            disabled={!open}
            required
          />
          <Select
            c={c}
            label="f.category"
            value={h.category_id || str(it?.category_id)}
            onChange={(v) => setH({ category_id: v, item_id: "" })}
            options={options.categories}
            ph="ph.category"
            disabled={!open}
            required
            hint={c("hint.category")}
          />
          <Select
            c={c}
            label="f.item_name"
            value={h.item_id}
            onChange={(v) => {
              const x = itemOf(options, v);
              setH({
                item_id: v,
                uom: x?.uom || h.uom,
                cost_price: h.cost_price || (x?.price ? String(x.price) : ""),
              });
            }}
            options={itemsIn(options, h.category_id || it?.category_id)}
            ph="ph.item"
            disabled={!open}
            required
          />
          <Select
            c={c}
            label="f.uom"
            value={h.uom}
            onChange={(v) => setH({ uom: v })}
            options={options.uoms}
            ph="ph.uom"
            disabled={!open}
          />
          <Text
            c={c}
            label="f.loan_received"
            type="number"
            value={h.loan_received}
            onChange={(v) => setH({ loan_received: v })}
            disabled={!open}
            hint={c("hint.loan_received")}
          />
          <Text
            c={c}
            label="f.loan_issued"
            type="number"
            value={h.loan_issued}
            onChange={(v) => setH({ loan_issued: v })}
            disabled={!open}
            hint={c("hint.loan_issued")}
          />
          <CompanyField c={c} options={options} />
          <Select
            c={c}
            label="f.store"
            value={h.store_id}
            onChange={(v) => setH({ store_id: v })}
            options={options.stores}
            ph="ph.store"
            disabled={!open}
          />
          <Text
            c={c}
            label="f.cost_price"
            type="number"
            value={h.cost_price}
            onChange={(v) => setH({ cost_price: v })}
            disabled={!open}
            hint={w("hint.cost_price")}
          />
        </div>
        <label className="mt-4 flex items-start gap-2 text-sm">
          <input
            type="checkbox"
            checked={h.is_final}
            onChange={(e) => setH({ is_final: e.target.checked })}
            disabled={!open}
            className="mt-0.5 size-4 accent-brand"
          />
          <span>
            <span className="font-semibold">{c("f.is_final")}</span>
            <span className="block text-xs text-foreground-muted">
              {c("hint.is_final")}
            </span>
          </span>
        </label>
        <Remarks
          c={c}
          value={h.remarks}
          onChange={(v) => setH({ remarks: v })}
          disabled={!open}
        />
      </section>
      <section
        className={cn(
          card,
          "flex flex-wrap items-center justify-between gap-3 p-4",
        )}
      >
        <p className="text-[13px] text-foreground-muted">{c("hint.saved")}</p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={clear} disabled={pending}>
            <RotateCcw /> {c("btn.clear")}
          </Button>
          {open ? (
            <Button onClick={save} loading={pending}>
              <Save /> {c("btn.save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

/* ---------------- opening balance ---------------- */

function OpeningList({ initialRows, canManage }) {
  const c = useNd();
  return (
    <section className={card}>
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.opening_list")}
      </p>
      <ScTable
        rows={initialRows}
        columns={[
          {
            key: "ob_date",
            label: c("col.ob_date"),
            nowrap: true,
            render: (r) => (
              <Link
                href={`${BASE.opening}?tab=sheet&store=${enc(r.store_code)}&date=${r.ob_date}`}
                className="font-medium text-brand hover:underline"
              >
                {dayFmt(r.ob_date)}
              </Link>
            ),
            text: (r) => r.ob_date,
          },
          { key: "store", label: c("col.store") },
          { key: "items", label: c("col.items"), num: true },
          qtyCol(c, "qty"),
          {
            key: "value",
            label: c("col.value"),
            num: true,
            text: (r) => moneyFmt.format(r.value),
          },
          {
            key: "finalized",
            label: c("col.status"),
            render: (r) => (
              <StatusChip
                status={r.finalized ? "APPROVED" : "DRAFT"}
                label={c(r.finalized ? "finalized" : "not_finalized")}
              />
            ),
            text: (r) => c(r.finalized ? "finalized" : "not_finalized"),
          },
        ]}
        exportName="needle-opening-balance"
        addHref={canManage ? `${BASE.opening}?tab=sheet` : null}
        empty={
          <NoLines title={c("empty.openings")} hint={c("empty.opening_hint")} />
        }
      />
    </section>
  );
}

function OpeningSheet({ options, initial, canManage, act }) {
  const c = useNd();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [f, setF] = useState({
    store_id: str(initial?.store_id),
    ob_date: str(initial?.ob_date) || today,
    category_id: "",
  });
  const [sheet, setSheet] = useState(initial);
  const [lines, setLines] = useState(() =>
    (initial?.lines ?? []).map((l) => ({
      ...l,
      qty: str(l.qty || ""),
      rate: str(l.rate),
    })),
  );
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && sheet && !sheet.finalized;
  function take(d) {
    setSheet(d);
    setLines(
      d.lines.map((l) => ({
        ...l,
        qty: l.qty ? String(l.qty) : "",
        rate: str(l.rate),
      })),
    );
    setErrors(null);
  }
  function load() {
    startTransition(async () => {
      const qs = new URLSearchParams(
        Object.entries(f).filter(([, v]) => v),
      ).toString();
      const res = await act(`openings/sheet?${qs}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
    });
  }
  function save(finalize) {
    startTransition(async () => {
      const res = await act("openings/sheet", "PUT", {
        ...f,
        category_id: f.category_id || null,
        finalize,
        lines: lines.map((l) => ({
          item_id: l.item_id,
          qty: orNull(l.qty),
          rate: orNull(l.rate),
        })),
      });
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      toastManager.add({
        title: c(finalize ? "done.ob_final" : "done.ob_saved"),
        type: "success",
      });
    });
  }
  const setL = (i, patch) =>
    setLines((all) => all.map((l, n) => (n === i ? { ...l, ...patch } : l)));
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={c("sec.opening")}
          hint={c("hint.opening")}
          doc={sheet?.finalized ? { x: 1 } : null}
          status="APPROVED"
          group="ob_badge"
        />
        <Errors errors={errors} />
        <div className="mt-4 grid gap-4 md:grid-cols-4">
          <CompanyField c={c} options={options} />
          <Select
            c={c}
            label="f.store"
            value={f.store_id}
            onChange={(v) => setF((x) => ({ ...x, store_id: v }))}
            options={options.stores}
            ph="ph.store"
          />
          <Text
            c={c}
            label="f.ob_date"
            type="date"
            value={f.ob_date}
            onChange={(v) => setF((x) => ({ ...x, ob_date: v }))}
          />
          <Select
            c={c}
            label="f.category"
            value={f.category_id}
            onChange={(v) => setF((x) => ({ ...x, category_id: v }))}
            options={options.categories}
            ph="ph.all_categories"
            hint={c("hint.ob_category")}
          />
        </div>
        <div className="mt-4 flex justify-end">
          <Button variant="outline" onClick={load} loading={pending}>
            <DownloadCloud /> {c("btn.load")}
          </Button>
        </div>
      </section>
      <section className={cn(card, "p-5")}>
        {!sheet ? (
          <NoLines title={c("empty.load_first")} hint={c("hint.ob_load")} />
        ) : (
          <>
            {sheet.finalized ? (
              <Alert variant="info">{c("hint.ob_finalized")}</Alert>
            ) : null}
            <div className="mt-3 overflow-x-auto rounded-lg border border-border">
              <table className="w-full min-w-[900px] text-sm">
                <GridHead
                  c={c}
                  cols={[
                    ["item_code"],
                    ["item"],
                    ["category"],
                    ["uom"],
                    ["qty", true],
                    ["rate", true],
                    ["value", true],
                  ]}
                />
                <tbody>
                  {lines.map((l, i) => (
                    <tr key={l.item_id} className="border-t border-border">
                      <Cell className="font-medium">{l.item_code}</Cell>
                      <Cell>{l.item}</Cell>
                      <Cell>{l.category ?? "—"}</Cell>
                      <Cell>{l.uom ?? "—"}</Cell>
                      <LineNum
                        value={l.qty}
                        onChange={(v) => setL(i, { qty: v })}
                        label={`${c("col.qty")} ${l.item_code}`}
                        disabled={!open}
                      />
                      <LineNum
                        value={l.rate}
                        onChange={(v) => setL(i, { rate: v })}
                        label={`${c("col.rate")} ${l.item_code}`}
                        disabled={!open}
                      />
                      <Cell className="text-right tabular-nums">
                        {moneyFmt.format(num(l.qty) * num(l.rate))}
                      </Cell>
                    </tr>
                  ))}
                </tbody>
                <tfoot className="border-t border-border bg-surface-muted font-semibold">
                  <tr>
                    <td className="px-2 py-2" colSpan={4}>
                      {c("total_lines", {
                        n: lines.filter((l) => num(l.qty) > 0).length,
                      })}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {q3(sum(lines, (l) => num(l.qty)))}
                    </td>
                    <td />
                    <td className="px-2 py-2 text-right tabular-nums">
                      {moneyFmt.format(
                        sum(lines, (l) => num(l.qty) * num(l.rate)),
                      )}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <p className="mt-3 text-[13px] text-foreground-muted">
              {c("hint.ob_zero")}
            </p>
            {open ? (
              <div className="mt-4 flex justify-end gap-2">
                <Button
                  variant="outline"
                  onClick={() => save(false)}
                  loading={pending}
                >
                  <Save /> {c("btn.save")}
                </Button>
                <Button onClick={() => save(true)} loading={pending}>
                  <CheckCheck /> {c("btn.save_finalize")}
                </Button>
              </div>
            ) : null}
          </>
        )}
      </section>
    </div>
  );
}

/* ---------------- assignment ---------------- */

function AssignList({ initialRows, canManage }) {
  const c = useNd();
  return (
    <section className={card}>
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.assign_list")}
      </p>
      <ScTable
        rows={initialRows}
        columns={[
          { key: "office", label: c("col.office") },
          { key: "category", label: c("col.category") },
          { key: "machines", label: c("col.machines"), num: true },
          qtyCol(c, "qty"),
          {
            key: "finalized",
            label: c("col.status"),
            render: (r) => (
              <StatusChip
                status={r.finalized ? "APPROVED" : "DRAFT"}
                label={c(r.finalized ? "finalized" : "not_finalized")}
              />
            ),
            text: (r) => c(r.finalized ? "finalized" : "not_finalized"),
          },
        ]}
        exportName="needle-assignment"
        addHref={canManage ? `${BASE.assign}?tab=sheet` : null}
        empty={
          <NoLines
            title={c("empty.assignments")}
            hint={c("empty.assign_hint")}
          />
        }
      />
    </section>
  );
}

function AssignSheet({ options, canManage, act }) {
  const c = useNd();
  const toastManager = useToastManager();
  const [f, setF] = useState({ office_id: "", category_id: "" });
  const [sheet, setSheet] = useState(null);
  const [rows, setRows] = useState([]);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && sheet && !sheet.finalized;
  function take(d) {
    setSheet(d);
    setRows(
      d.rows.map((r) => ({
        ...r,
        item_id: str(r.item_id),
        qty: r.qty ? String(r.qty) : "",
      })),
    );
    setErrors(null);
  }
  function load() {
    startTransition(async () => {
      const res = await act(
        `assignments/sheet?office_id=${enc(f.office_id)}&category_id=${enc(f.category_id)}`,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
    });
  }
  function save(finalize) {
    startTransition(async () => {
      const res = await act("assignments/sheet", "PUT", {
        ...f,
        finalize,
        rows: rows.map((r) => ({
          machine_id: r.machine_id,
          item_id: r.item_id || null,
          qty: orNull(r.qty),
        })),
      });
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      toastManager.add({
        title: c(finalize ? "done.fit_final" : "done.fit_saved"),
        type: "success",
      });
    });
  }
  const setR = (i, patch) =>
    setRows((all) => all.map((r, n) => (n === i ? { ...r, ...patch } : r)));
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={c("sec.assign")}
          hint={c("hint.assign")}
          doc={sheet?.finalized ? { x: 1 } : null}
          status="APPROVED"
          group="ob_badge"
        />
        <Errors errors={errors} />
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Select
            c={c}
            label="f.office"
            value={f.office_id}
            onChange={(v) => setF((x) => ({ ...x, office_id: v }))}
            options={options.offices}
            ph="ph.office"
          />
          <Select
            c={c}
            label="f.category"
            value={f.category_id}
            onChange={(v) => setF((x) => ({ ...x, category_id: v }))}
            options={options.categories}
            ph="ph.category"
          />
        </div>
        <div className="mt-4 flex justify-end">
          <Button variant="outline" onClick={load} loading={pending}>
            <DownloadCloud /> {c("btn.load")}
          </Button>
        </div>
      </section>
      <section className={cn(card, "p-5")}>
        {!sheet ? (
          <NoLines title={c("empty.load_first")} hint={c("hint.assign_load")} />
        ) : rows.length === 0 ? (
          <NoLines
            title={c("empty.no_machines")}
            hint={c("empty.no_machines_hint")}
          />
        ) : (
          <>
            {sheet.finalized ? (
              <Alert variant="info">{c("hint.fit_finalized")}</Alert>
            ) : null}
            <div className="mt-3 overflow-x-auto rounded-lg border border-border">
              <table className="w-full min-w-[900px] text-sm">
                <GridHead
                  c={c}
                  cols={[
                    ["machine"],
                    ["machine_type"],
                    ["dia"],
                    ["gauge"],
                    ["item"],
                    ["qty", true],
                  ]}
                />
                <tbody>
                  {rows.map((r, i) => (
                    <tr key={r.machine_id} className="border-t border-border">
                      <Cell className="font-medium">{r.machine_no}</Cell>
                      <Cell>{r.machine_type ?? "—"}</Cell>
                      <Cell>{r.dia ?? "—"}</Cell>
                      <Cell>{r.gauge ?? "—"}</Cell>
                      <LineSelect
                        value={r.item_id}
                        onChange={(v) => setR(i, { item_id: v })}
                        options={itemsIn(options, sheet.category_id)}
                        label={`${c("col.item")} ${r.machine_no}`}
                        disabled={!open}
                        width="min-w-[260px]"
                      />
                      <LineNum
                        value={r.qty}
                        onChange={(v) => setR(i, { qty: v })}
                        label={`${c("col.qty")} ${r.machine_no}`}
                        disabled={!open}
                      />
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="mt-3 text-[13px] text-foreground-muted">
              {c("hint.fit_zero")}
            </p>
            {open ? (
              <div className="mt-4 flex justify-end gap-2">
                <Button
                  variant="outline"
                  onClick={() => save(false)}
                  loading={pending}
                >
                  <Save /> {c("btn.save")}
                </Button>
                <Button onClick={() => save(true)} loading={pending}>
                  <CheckCheck /> {c("btn.save_finalize")}
                </Button>
              </div>
            ) : null}
          </>
        )}
      </section>
    </div>
  );
}

/* ---------------- closing ---------------- */

function ClosingDesk({ options, initialRows, canManage, act }) {
  const c = useNd();
  const toastManager = useToastManager();
  const [f, setF] = useState({ store_id: "", date: "" });
  const [rows, setRows] = useState(initialRows);
  const [shown, setShown] = useState(initialRows);
  const [exceptions, setExceptions] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const qs = () =>
    new URLSearchParams(Object.entries(f).filter(([, v]) => v)).toString();
  function search() {
    setShown(rows.filter((r) => !f.store_id || r.store_id === f.store_id));
  }
  function showExceptions() {
    startTransition(async () => {
      const res = await act(`closings/exceptions?${qs()}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setExceptions(res.data);
      setErrors(null);
    });
  }
  function close(storeId) {
    startTransition(async () => {
      const res = await act("closings", "POST", {
        store_id: storeId ?? (f.store_id || null),
        date: f.date || null,
      });
      if (res?.status !== "success") return setErrors(messagesOf(res));
      const fresh = await act("closings");
      if (fresh?.status === "success") {
        setRows(fresh.data);
        setShown(
          fresh.data.filter((r) => !f.store_id || r.store_id === f.store_id),
        );
      }
      setErrors(
        res.data.skipped.length
          ? res.data.skipped.map((s) => `${s.store} (${s.period}): ${s.reason}`)
          : null,
      );
      toastManager.add({
        title: c("done.closed", {
          list: res.data.closed.map((x) => `${x.store} ${x.period}`).join(", "),
        }),
        type: "success",
      });
    });
  }
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold">{c("sec.closing")}</h2>
        <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
          {c("hint.closing")}
        </p>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <CompanyField c={c} options={options} />
          <Select
            c={c}
            label="f.store"
            value={f.store_id}
            onChange={(v) => setF((x) => ({ ...x, store_id: v }))}
            options={options.stores}
            ph="ph.store"
          />
          <Text
            c={c}
            label="f.closing_date"
            type="date"
            value={f.date}
            onChange={(v) => setF((x) => ({ ...x, date: v }))}
            hint={c("hint.closing_date")}
          />
        </div>
        <div className="mt-4 flex flex-wrap justify-end gap-2">
          <Button
            variant="outline"
            onClick={() => (
              setF({ store_id: "", date: "" }),
              setShown(rows),
              setExceptions(null),
              setErrors(null)
            )}
          >
            <RotateCcw /> {c("btn.reset")}
          </Button>
          <Button variant="outline" onClick={showExceptions} loading={pending}>
            <AlertTriangle /> {c("btn.show_exception")}
          </Button>
          <Button variant="outline" onClick={search}>
            <Search /> {c("btn.search")}
          </Button>
          {canManage ? (
            <Button onClick={() => close(null)} loading={pending}>
              <Lock /> {c("btn.start_closing")}
            </Button>
          ) : null}
        </div>
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      {exceptions ? (
        <section className={cn(card, "p-5")}>
          <h3 className="text-sm font-semibold">{c("sec.exceptions")}</h3>
          {exceptions.length === 0 ? (
            <p className="mt-2 text-[13px] text-success">
              {c("hint.no_exceptions")}
            </p>
          ) : (
            <div className="mt-3 overflow-x-auto rounded-lg border border-border">
              <table className="w-full text-sm">
                <GridHead
                  c={c}
                  cols={[
                    ["store"],
                    ["period"],
                    ["doc"],
                    ["doc_no"],
                    ["date"],
                    ["status"],
                  ]}
                />
                <tbody>
                  {exceptions.map((e) => (
                    <tr
                      key={`${e.doc}-${e.no}`}
                      className="border-t border-border"
                    >
                      <Cell>{e.store}</Cell>
                      <Cell>{e.period}</Cell>
                      <Cell>{c(`doc.${e.doc}`)}</Cell>
                      <Cell className="font-medium">{e.no}</Cell>
                      <Cell>{dayFmt(e.date)}</Cell>
                      <Cell>{e.status}</Cell>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      ) : null}
      <section className={card}>
        <ScTable
          rows={shown}
          columns={[
            { key: "company", label: c("col.company") },
            { key: "store", label: c("col.store") },
            {
              key: "last_closed_by",
              label: c("col.last_closed_by"),
              text: (r) => r.last_closed_by ?? "—",
            },
            {
              key: "last_period",
              label: c("col.last_period"),
              text: (r) => r.last_period ?? "—",
            },
            { key: "next_period", label: c("col.next_period") },
            {
              key: "lines_struck",
              label: c("col.lines_struck"),
              num: true,
              text: (r) => r.lines_struck ?? "—",
            },
            {
              key: "closed_at",
              label: c("col.closed_at"),
              text: (r) =>
                r.closed_at ? dayFmt(r.closed_at.slice(0, 10)) : "—",
            },
            {
              key: "status",
              label: c("col.status"),
              render: (r) => (
                <StatusChip
                  status={r.status === "CLOSED" ? "APPROVED" : "DRAFT"}
                  label={c(`closing_status.${r.status}`)}
                />
              ),
              text: (r) => c(`closing_status.${r.status}`),
            },
          ]}
          exportName="needle-closing"
          empty={<NoLines title={c("empty.stores")} hint={c("empty.hint")} />}
          pending={pending}
          actions={(r) =>
            canManage ? (
              <MoveButton
                icon={Lock}
                label={c("btn.start_closing")}
                no={r.store}
                onClick={() => close(r.store_id)}
                disabled={pending}
              />
            ) : null
          }
        />
      </section>
    </div>
  );
}

/* ---------------- receipts ---------------- */

function ReceiptList({ options, initialRows, canManage, act }) {
  const c = useNd();
  const flt = useShowFilters({
    no: "",
    from: "",
    to: "",
    challan: "",
    store: "",
    supplier: "",
    item: "",
    status: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "receipts",
    "mrr_no",
    setRows,
    setError,
  );
  const step = makeStep(c, canManage, move, pending, "mrr_no", act);
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.no || has(r.mrr_no, o.no)) &&
      inRange(o, r.mrr_date) &&
      (!o.challan || has(r.challan_no, o.challan)) &&
      (!o.store || r.store_id === o.store) &&
      (!o.supplier || r.supplier_id === o.supplier) &&
      (!o.item || has(r.items, o.item)) &&
      (!o.status || r.status === o.status),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`receipts/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printReceipt(c, res.data);
    });
  }
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <Text
            c={c}
            label="f.mrr_no"
            value={flt.f.no}
            onChange={(v) => flt.set("no", v)}
            placeholder="NMR-…"
          />
          <Text
            c={c}
            label="f.mrr_from"
            type="date"
            value={flt.f.from}
            onChange={(v) => flt.set("from", v)}
          />
          <Text
            c={c}
            label="f.mrr_to"
            type="date"
            value={flt.f.to}
            onChange={(v) => flt.set("to", v)}
          />
          <Text
            c={c}
            label="f.challan_no"
            value={flt.f.challan}
            onChange={(v) => flt.set("challan", v)}
            placeholder={c("ph.supplier_challan")}
          />
          <Select
            c={c}
            label="f.store"
            value={flt.f.store}
            onChange={(v) => flt.set("store", v)}
            options={options.stores}
            ph="ph.store"
          />
          <Select
            c={c}
            label="f.supplier"
            value={flt.f.supplier}
            onChange={(v) => flt.set("supplier", v)}
            options={options.parties}
            ph="ph.supplier"
          />
          <Text
            c={c}
            label="f.item_name"
            value={flt.f.item}
            onChange={(v) => flt.set("item", v)}
            placeholder={c("ph.item_search")}
          />
          <Select
            c={c}
            label="f.status"
            value={flt.f.status}
            onChange={(v) => flt.set("status", v)}
            options={listOpts(c, options.receipt_statuses, "receipt_status")}
          />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.receipt_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(c, "mrr_no", "mrr_date", BASE.receipt, "receipt_status"),
          { key: "store", label: c("col.store") },
          { key: "supplier", label: c("col.supplier") },
          {
            key: "challan_no",
            label: c("col.challan_no"),
            text: (r) => r.challan_no ?? "—",
          },
          { key: "items", label: c("col.items_list") },
          qtyCol(c, "challan_qty"),
          qtyCol(c, "received_qty"),
          qtyCol(c, "returned"),
          {
            key: "amount",
            label: c("col.amount"),
            num: true,
            text: (r) => moneyFmt.format(r.amount),
          },
        ]}
        exportName="needle-receipts"
        addHref={canManage ? `${BASE.receipt}?tab=entry` : null}
        empty={<NoLines title={c("empty.receipts")} hint={c("empty.hint")} />}
        pending={pending}
        actions={(r) => (
          <>
            {step(r, ["DRAFT"], "submit", Send)}
            {step(r, ["WAITING"], "verify", ShieldCheck)}
            {step(r, ["VERIFIED"], "accounts", Send)}
            {step(r, ["WAIT_ACCOUNTS"], "approve", BadgeCheck)}
            {step(r, ["DRAFT", "WAITING"], "cancel", Ban, true)}
            {canManage &&
            ["VERIFIED", "WAIT_ACCOUNTS", "APPROVED"].includes(r.status) &&
            r.outstanding > 0 ? (
              <Link
                href={`${BASE.ret}?tab=entry&mrr=${enc(r.mrr_no)}`}
                className={buttonVariants({ variant: "outline", size: "sm" })}
                aria-label={`${c("btn.raise_return")} ${r.mrr_no}`}
              >
                <Undo2 /> {c("btn.raise_return")}
              </Link>
            ) : null}
            <PrintButton
              c={c}
              no={r.mrr_no}
              onClick={() => print(r.mrr_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

const REC_HEAD = [
  "mrr_date",
  "store_id",
  "req_type",
  "supplier_id",
  "challan_no",
  "challan_date",
  "source",
  "pay_method",
  "lc_no",
  "remarks",
];
const REC_LINE = [
  "category_id",
  "item_id",
  "uom",
  "challan_qty",
  "received_qty",
  "rate",
  "remarks",
];

function ReceiptEntry({ options, initial, canManage, act }) {
  const c = useNd();
  const [today] = useState(todayIso);
  const d = useDoc({
    c,
    path: "receipts",
    idKey: "mrr_no",
    initial,
    asHead: (x) => ({
      ...Object.fromEntries(REC_HEAD.map((k) => [k, str(x?.[k])])),
      mrr_date: str(x?.mrr_date) || today,
    }),
    asLine: (l) =>
      withCategory(options)(
        Object.fromEntries(REC_LINE.map((k) => [k, str(l[k])])),
      ),
    canManage,
    act,
  });
  const { head, setH, open, lines, setL } = d;
  function save() {
    d.save({
      ...clean(Object.fromEntries(REC_HEAD.map((k) => [k, head[k]]))),
      lines: lines.map((l) => ({
        item_id: l.item_id || null,
        uom: l.uom || null,
        remarks: l.remarks || null,
        challan_qty: orNull(l.challan_qty),
        received_qty: orNull(l.received_qty),
        rate: orNull(l.rate),
      })),
    });
  }
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={d.doc ? d.doc.mrr_no : c("tabs.receipt_entry")}
          hint={c("hint.receipt_form")}
          doc={d.doc}
          status={d.doc?.status}
          group="receipt_status"
        />
        <Locked d={d} idKey="mrr_no" group="receipt_status" />
        <Errors errors={d.errors} />
        <Existing
          d={d}
          idKey="mrr_no"
          docs={options.docs}
          label="f.existing_receipt"
          ph="ph.new_receipt"
        />
        <h3 className="mt-5 text-sm font-semibold">{c("sec.receive_info")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-4">
          <NoField c={c} label="f.mrr_no" value={d.doc?.mrr_no} />
          <Text
            c={c}
            label="f.mrr_date"
            type="date"
            value={head.mrr_date}
            onChange={(v) => setH({ mrr_date: v })}
            disabled={!open}
            required
          />
          <CompanyField c={c} options={options} />
          <Select
            c={c}
            label="f.store"
            value={head.store_id}
            onChange={(v) => setH({ store_id: v })}
            options={options.stores}
            ph="ph.store"
            disabled={!open}
            required
            hint={c("hint.receipt_store")}
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">
          {c("sec.supplier_paper")}
        </h3>
        <div className="mt-3 grid gap-4 md:grid-cols-4">
          <Select
            c={c}
            label="f.req_type"
            value={head.req_type}
            onChange={(v) => setH({ req_type: v })}
            options={listOpts(c, options.req_types, "req_type")}
            ph="ph.req_type"
            disabled={!open}
          />
          <Select
            c={c}
            label="f.supplier"
            value={head.supplier_id}
            onChange={(v) => setH({ supplier_id: v })}
            options={options.parties}
            ph="ph.supplier"
            disabled={!open}
            required
          />
          <Text
            c={c}
            label="f.challan_no"
            value={head.challan_no}
            onChange={(v) => setH({ challan_no: v })}
            disabled={!open}
          />
          <Text
            c={c}
            label="f.challan_date"
            type="date"
            value={head.challan_date}
            onChange={(v) => setH({ challan_date: v })}
            disabled={!open}
          />
          <Text
            c={c}
            label="f.source"
            value={head.source}
            onChange={(v) => setH({ source: v })}
            disabled={!open}
            max={80}
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.terms")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-4">
          <Select
            c={c}
            label="f.pay_method"
            value={head.pay_method}
            onChange={(v) =>
              setH({ pay_method: v, lc_no: v === "LC" ? head.lc_no : "" })
            }
            options={listOpts(c, options.pay_methods, "pay")}
            ph="ph.select_item"
            disabled={!open}
          />
          <Text
            c={c}
            label="f.lc_no"
            value={head.lc_no}
            onChange={(v) => setH({ lc_no: v })}
            disabled={!open || head.pay_method !== "LC"}
            hint={c("hint.lc_no")}
          />
        </div>
        <Remarks
          c={c}
          value={head.remarks}
          onChange={(v) => setH({ remarks: v })}
          disabled={!open}
        />
      </section>
      <GridCard
        c={c}
        title={c("sec.item_list")}
        hint={c("hint.receipt_lines")}
        button={c("btn.add_item")}
        onAdd={() => d.add()}
        open={open}
        empty={c("empty.receipt_lines")}
        count={lines.length}
      >
        <table className="w-full min-w-[1400px] text-sm">
          <GridHead
            c={c}
            cols={[
              ["category"],
              ["item"],
              ["uom"],
              ["challan_qty", true],
              ["received_qty", true],
              ["rate", true],
              ["amount", true],
              ["remarks"],
              [""],
            ]}
          />
          <tbody>
            {lines.map((l, i) => {
              const n = i + 1;
              return (
                <tr key={l.key} className="border-t border-border">
                  <ItemCells d={d} l={l} n={n} options={options} />
                  <LineNum
                    value={l.challan_qty}
                    onChange={(v) => setL(l.key, { challan_qty: v })}
                    label={`${c("col.challan_qty")} ${n}`}
                    disabled={!open}
                  />
                  <LineNum
                    value={l.received_qty}
                    onChange={(v) => setL(l.key, { received_qty: v })}
                    label={`${c("col.received_qty")} ${n}`}
                    disabled={!open}
                  />
                  <LineNum
                    value={l.rate}
                    onChange={(v) => setL(l.key, { rate: v })}
                    label={`${c("col.rate")} ${n}`}
                    disabled={!open}
                  />
                  <Cell className="text-right font-medium tabular-nums">
                    {moneyFmt.format(num(l.received_qty) * num(l.rate))}
                  </Cell>
                  <LineText
                    value={l.remarks}
                    onChange={(v) => setL(l.key, { remarks: v })}
                    label={`${c("col.remarks")} ${n}`}
                    disabled={!open}
                    max={300}
                  />
                  <RemoveCell
                    c={c}
                    n={n}
                    open={open}
                    onClick={() => d.remove(l.key)}
                  />
                </tr>
              );
            })}
          </tbody>
          <tfoot className="border-t border-border bg-surface-muted font-semibold">
            <tr>
              <td className="px-2 py-2" colSpan={3}>
                {c("total_lines", { n: lines.length })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.challan_qty))) })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.received_qty))) })}
              </td>
              <td />
              <td className="px-2 py-2 text-right tabular-nums">
                {moneyFmt.format(
                  sum(lines, (l) => num(l.received_qty) * num(l.rate)),
                )}
              </td>
              <td colSpan={2} />
            </tr>
          </tfoot>
        </table>
        <div className="px-3 pb-3">
          <Status c={c} foot={c("hint.receipt_status")} />
        </div>
      </GridCard>
      <FormFoot
        c={c}
        hint={c("hint.receipt_foot")}
        back={BASE.receipt}
        onClear={() => d.show(null)}
        onSave={save}
        saveLabel={c("btn.save_receipt")}
        pending={d.pending}
        open={open}
      />
    </div>
  );
}

function ConsumptionBoard({ options, act }) {
  const c = useNd();
  const [today] = useState(todayIso);
  const [f, setF] = useState({
    store_id: "",
    from: today.slice(0, 8) + "01",
    to: today,
  });
  const [rows, setRows] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  function show() {
    startTransition(async () => {
      const res = await act(`consumption?${new URLSearchParams(f).toString()}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setRows(res.data);
      setErrors(null);
    });
  }
  const cols = [
    "opening",
    "received",
    "returned",
    "issued_in",
    "issued_out",
    "loan_out",
    "closing",
  ];
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold">{c("sec.consumption")}</h2>
        <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
          {c("hint.consumption")}
        </p>
        <Errors errors={errors} />
        <div className="mt-4 grid gap-4 md:grid-cols-4">
          <CompanyField c={c} options={options} />
          <Select
            c={c}
            label="f.store"
            value={f.store_id}
            onChange={(v) => setF((x) => ({ ...x, store_id: v }))}
            options={options.stores}
            ph="ph.store"
          />
          <Text
            c={c}
            label="f.from"
            type="date"
            value={f.from}
            onChange={(v) => setF((x) => ({ ...x, from: v }))}
          />
          <Text
            c={c}
            label="f.to"
            type="date"
            value={f.to}
            onChange={(v) => setF((x) => ({ ...x, to: v }))}
          />
        </div>
        <div className="mt-4 flex justify-end">
          <Button onClick={show} loading={pending}>
            <Search /> {c("btn.show")}
          </Button>
        </div>
      </section>
      <section className={card}>
        {!rows ? (
          <NoLines
            title={c("empty.fill_show")}
            hint={c("hint.consumption_store")}
          />
        ) : (
          <ScTable
            rows={rows}
            columns={[
              { key: "item", label: c("col.item") },
              {
                key: "category",
                label: c("col.category"),
                text: (r) => r.category ?? "—",
              },
              { key: "uom", label: c("col.uom"), text: (r) => r.uom ?? "—" },
              ...cols.map((k) => qtyCol(c, k)),
            ]}
            exportName="needle-consumption"
            empty={
              <NoLines title={c("empty.consumption")} hint={c("empty.hint")} />
            }
          />
        )}
      </section>
    </div>
  );
}

/* ---------------- receipt returns ---------------- */

function ReturnList({ options, initialRows, canManage, act }) {
  const c = useNd();
  const flt = useShowFilters({
    no: "",
    mrr: "",
    type: "",
    store: "",
    supplier: "",
    from: "",
    to: "",
    status: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "returns",
    "challan_no",
    setRows,
    setError,
  );
  const step = makeStep(c, canManage, move, pending, "challan_no", act);
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.no || has(r.challan_no, o.no)) &&
      (!o.mrr || has(r.mrr_no, o.mrr)) &&
      (!o.type || r.challan_type === o.type) &&
      (!o.store || r.store_id === o.store) &&
      (!o.supplier || r.supplier_id === o.supplier) &&
      inRange(o, r.challan_date) &&
      (!o.status || r.status === o.status),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`returns/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printReturn(c, res.data);
    });
  }
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <Text
            c={c}
            label="f.challan_no"
            value={flt.f.no}
            onChange={(v) => flt.set("no", v)}
            placeholder={c("ph.return_challan")}
          />
          <Text
            c={c}
            label="f.mrr_no"
            value={flt.f.mrr}
            onChange={(v) => flt.set("mrr", v)}
            placeholder="NMR-…"
          />
          <Select
            c={c}
            label="f.return_type"
            value={flt.f.type}
            onChange={(v) => flt.set("type", v)}
            options={listOpts(c, options.return_types, "return_type")}
          />
          <Select
            c={c}
            label="f.store"
            value={flt.f.store}
            onChange={(v) => flt.set("store", v)}
            options={options.stores}
            ph="ph.store"
          />
          <Select
            c={c}
            label="f.supplier"
            value={flt.f.supplier}
            onChange={(v) => flt.set("supplier", v)}
            options={options.parties}
            ph="ph.supplier"
          />
          <Text
            c={c}
            label="f.challan_from"
            type="date"
            value={flt.f.from}
            onChange={(v) => flt.set("from", v)}
          />
          <Text
            c={c}
            label="f.challan_to"
            type="date"
            value={flt.f.to}
            onChange={(v) => flt.set("to", v)}
          />
          <Select
            c={c}
            label="f.status"
            value={flt.f.status}
            onChange={(v) => flt.set("status", v)}
            options={listOpts(c, options.return_statuses, "return_status")}
          />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.return_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(c, "challan_no", "challan_date", BASE.ret, "return_status"),
          { key: "mrr_no", label: c("col.mrr_no") },
          {
            key: "challan_type",
            label: c("col.return_type"),
            text: (r) => c(`return_type.${r.challan_type}`),
          },
          { key: "store", label: c("col.store") },
          { key: "supplier", label: c("col.supplier") },
          { key: "lines", label: c("col.lines"), num: true },
          qtyCol(c, "qty", "col.return_qty"),
          {
            key: "amount",
            label: c("col.amount"),
            num: true,
            text: (r) => moneyFmt.format(r.amount),
          },
        ]}
        exportName="needle-returns"
        empty={
          <NoLines title={c("empty.returns")} hint={c("empty.return_hint")} />
        }
        pending={pending}
        actions={(r) => (
          <>
            {step(r, ["DRAFT"], "issue", Truck)}
            {step(r, ["ISSUED"], "finalize", BadgeCheck)}
            {step(r, ["DRAFT", "ISSUED"], "cancel", Ban, true)}
            <PrintButton
              c={c}
              no={r.challan_no}
              onClick={() => print(r.challan_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

function ReturnEntry({
  options,
  initial,
  initialSource,
  receipts,
  canManage,
  act,
}) {
  const c = useNd();
  const [today] = useState(todayIso);
  const [supplier, setSupplier] = useState("");
  const d = useDoc({
    c,
    path: "returns",
    idKey: "challan_no",
    initial,
    asHead: (x) => ({
      mrr_no: str(x?.mrr_no),
      challan_no: str(x?.challan_no),
      challan_date: str(x?.challan_date) || today,
      challan_type: str(x?.challan_type),
      gate_pass_no: str(x?.gate_pass_no),
      vehicle_no: str(x?.vehicle_no),
      reason: str(x?.reason),
    }),
    asLine: (l) => ({ ...l, qty: str(l.qty), remarks: str(l.remarks) }),
    canManage,
    act,
  });
  const { head, setH, open, lines, setL } = d;
  const isNew = !d.doc;
  const take = (src) => {
    setH({ mrr_no: src.mrr_no });
    d.setLines(
      src.lines
        .filter((l) => l.outstanding > 0)
        .map((l) => ({
          key: `r${l.receipt_line_id}`,
          ...l,
          qty: String(l.outstanding),
          remarks: "",
        })),
    );
  };
  // Raise Return on the receive list arrives with the receipt's outstanding lines.
  const [seeded, setSeeded] = useState(false);
  if (!seeded && !initial && initialSource) {
    setSeeded(true);
    take(initialSource);
  }
  function include(r) {
    act(`receipts/${enc(r.mrr_no)}/source`).then(
      (res) => res?.status === "success" && take(res.data),
    );
  }
  function save() {
    d.save({
      ...clean(head),
      lines: lines.map((l) => ({
        receipt_line_id: l.receipt_line_id,
        qty: orNull(l.qty),
        remarks: l.remarks || null,
      })),
    });
  }
  const rec = receipts.find((r) => r.mrr_no === head.mrr_no);
  const rows = receipts
    .filter(
      (r) => r.outstanding > 0 && (!supplier || r.supplier_id === supplier),
    )
    .map((r) => ({ ...r, key: r.mrr_no }));
  return (
    <div className="grid gap-4 xl:grid-cols-[380px_1fr]">
      <PickPanel
        c={c}
        title={c("sec.receive_list")}
        filter={
          <Field label={c("f.supplier")} className="mt-3">
            <PSelect
              value={supplier}
              onValueChange={(v) => setSupplier(v ?? "")}
              options={pick(options.parties, c("ph.supplier"))}
              aria-label={`${c("f.supplier")} (${c("sec.receive_list")})`}
            />
          </Field>
        }
        placeholder={c("ph.search")}
        rows={rows}
        columns={[
          [c("col.mrr_no"), (r) => r.mrr_no],
          [c("col.mrr_date"), (r) => dayFmt(r.mrr_date)],
          [c("col.supplier"), (r) => r.supplier],
          [c("col.outstanding"), (r) => q3(r.outstanding), true],
        ]}
        action={(r) => (
          <Button
            variant="outline"
            size="sm"
            onClick={() => include(r)}
            disabled={!open || !isNew}
            aria-label={`${c("btn.include_challan")} ${r.mrr_no}`}
          >
            {c("btn.include_challan")}
          </Button>
        )}
      />
      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <FormHead
            c={c}
            title={!isNew ? d.doc.challan_no : c("tabs.return_entry")}
            hint={c("hint.return_form")}
            doc={d.doc}
            status={d.doc?.status}
            group="return_status"
          />
          <Locked d={d} idKey="challan_no" group="return_status" />
          <Errors errors={d.errors} />
          <Existing
            d={d}
            idKey="challan_no"
            docs={options.docs}
            label="f.existing_return"
            ph="ph.new_return"
          />
          <h3 className="mt-5 text-sm font-semibold">{c("sec.return_info")}</h3>
          <div className="mt-3 grid gap-4 md:grid-cols-4">
            <Field label={c("f.mrr_no")} hint={c("hint.return_mrr")}>
              <Input value={head.mrr_no} disabled aria-label={c("f.mrr_no")} />
            </Field>
            <Text
              c={c}
              label="f.rtn_challan"
              value={head.challan_no}
              onChange={(v) => setH({ challan_no: v })}
              disabled={!open || !isNew}
              max={32}
              hint={c("hint.rtn_challan")}
            />
            <Text
              c={c}
              label="f.challan_date"
              type="date"
              value={head.challan_date}
              onChange={(v) => setH({ challan_date: v })}
              disabled={!open}
              required
            />
            <Select
              c={c}
              label="f.return_type"
              value={head.challan_type}
              onChange={(v) => setH({ challan_type: v })}
              options={listOpts(c, options.return_types, "return_type")}
              ph="ph.select_item"
              disabled={!open}
              required
              hint={c("hint.return_type")}
            />
          </div>
          <h3 className="mt-5 text-sm font-semibold">{c("sec.against")}</h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <CompanyField c={c} options={options} />
            <Field label={c("f.store")} required>
              <Input
                value={d.doc?.store ?? rec?.store ?? ""}
                disabled
                aria-label={c("f.store")}
              />
            </Field>
            <Field label={c("f.supplier")} required>
              <Input
                value={d.doc?.supplier ?? rec?.supplier ?? ""}
                disabled
                aria-label={c("f.supplier")}
              />
            </Field>
          </div>
          <h3 className="mt-5 text-sm font-semibold">{c("sec.gate")}</h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <Text
              c={c}
              label="f.gate_pass"
              value={head.gate_pass_no}
              onChange={(v) => setH({ gate_pass_no: v })}
              disabled={!open}
              max={40}
            />
            <Text
              c={c}
              label="f.vehicle"
              value={head.vehicle_no}
              onChange={(v) => setH({ vehicle_no: v })}
              disabled={!open}
              max={40}
            />
          </div>
          <Remarks
            c={c}
            label="f.reason"
            value={head.reason}
            onChange={(v) => setH({ reason: v })}
            disabled={!open}
          />
        </section>
        <GridCard
          c={c}
          title={c("sec.item_list")}
          hint={c("hint.return_lines")}
          open={false}
          empty={c("empty.return_lines")}
          count={lines.length}
        >
          <table className="w-full min-w-[1100px] text-sm">
            <GridHead
              c={c}
              cols={[
                ["item"],
                ["uom"],
                ["received", true],
                ["already_back", true],
                ["return_qty", true],
                ["rate", true],
                ["amount", true],
                ["remarks"],
                [""],
              ]}
            />
            <tbody>
              {lines.map((l, i) => {
                const n = i + 1;
                return (
                  <tr key={l.key} className="border-t border-border">
                    <Cell className="font-medium">{l.item}</Cell>
                    <Cell>{l.uom ?? "—"}</Cell>
                    <Cell className="text-right tabular-nums">
                      {q3(l.received)}
                    </Cell>
                    <Cell className="text-right tabular-nums">
                      {q3(l.back)}
                    </Cell>
                    <LineNum
                      value={l.qty}
                      onChange={(v) => setL(l.key, { qty: v })}
                      label={`${c("col.return_qty")} ${n}`}
                      disabled={!open}
                    />
                    <Cell className="text-right tabular-nums">
                      {moneyFmt.format(l.rate)}
                    </Cell>
                    <Cell className="text-right font-medium tabular-nums">
                      {moneyFmt.format(num(l.qty) * num(l.rate))}
                    </Cell>
                    <LineText
                      value={l.remarks}
                      onChange={(v) => setL(l.key, { remarks: v })}
                      label={`${c("col.remarks")} ${n}`}
                      disabled={!open}
                      max={300}
                    />
                    <RemoveCell
                      c={c}
                      n={n}
                      open={open}
                      onClick={() => d.remove(l.key)}
                    />
                  </tr>
                );
              })}
            </tbody>
            <tfoot className="border-t border-border bg-surface-muted font-semibold">
              <tr>
                <td className="px-2 py-2" colSpan={4}>
                  {c("total_lines", { n: lines.length })}
                </td>
                <td className="px-2 py-2 text-right tabular-nums">
                  {c("ttl", { qty: q3(sum(lines, (l) => num(l.qty))) })}
                </td>
                <td />
                <td className="px-2 py-2 text-right tabular-nums">
                  {moneyFmt.format(sum(lines, (l) => num(l.qty) * num(l.rate)))}
                </td>
                <td colSpan={2} />
              </tr>
            </tfoot>
          </table>
          <div className="px-3 pb-3">
            <Status c={c} foot={c("hint.return_status")} />
          </div>
        </GridCard>
        <FormFoot
          c={c}
          hint={c("hint.return_foot")}
          back={BASE.ret}
          onClear={() => d.show(null)}
          onSave={save}
          saveLabel={c("btn.save_return")}
          pending={d.pending}
          open={open}
        />
      </div>
    </div>
  );
}

/* ---------------- broken needles ---------------- */

function BrokenList({ options, initialRows, canManage, act }) {
  const c = useNd();
  const flt = useShowFilters({
    no: "",
    office: "",
    shift: "",
    type: "",
    from: "",
    to: "",
    status: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "brokens",
    "ref_no",
    setRows,
    setError,
  );
  const step = makeStep(c, canManage, move, pending, "ref_no", act);
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.no || has(r.ref_no, o.no)) &&
      (!o.office || r.office_id === o.office) &&
      (!o.shift || r.shift_id === o.shift) &&
      (!o.type || r.machine_type_id === o.type) &&
      inRange(o, r.broken_date) &&
      (!o.status || r.status === o.status),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`brokens/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printBroken(c, res.data);
    });
  }
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <Text
            c={c}
            label="f.ref_no"
            value={flt.f.no}
            onChange={(v) => flt.set("no", v)}
            placeholder="NBR-…"
          />
          <Select
            c={c}
            label="f.office"
            value={flt.f.office}
            onChange={(v) => flt.set("office", v)}
            options={options.offices}
            ph="ph.office"
          />
          <Select
            c={c}
            label="f.shift"
            value={flt.f.shift}
            onChange={(v) => flt.set("shift", v)}
            options={options.shifts}
            ph="ph.shift"
          />
          <Select
            c={c}
            label="f.machine_type"
            value={flt.f.type}
            onChange={(v) => flt.set("type", v)}
            options={options.machine_types}
            ph="ph.type"
          />
          <DateRange c={c} flt={flt} />
          <Select
            c={c}
            label="f.status"
            value={flt.f.status}
            onChange={(v) => flt.set("status", v)}
            options={listOpts(c, options.broken_statuses, "broken_status")}
          />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.broken_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(c, "ref_no", "broken_date", BASE.broken, "broken_status"),
          { key: "shift", label: c("col.shift") },
          {
            key: "office",
            label: c("col.office"),
            text: (r) => r.office ?? "—",
          },
          {
            key: "machine_type",
            label: c("col.machine_type"),
            text: (r) => r.machine_type ?? "—",
          },
          { key: "machines", label: c("col.machines"), num: true },
          qtyCol(c, "qty", "col.qty_pcs"),
          {
            key: "amount",
            label: c("col.cost_price"),
            num: true,
            text: (r) => moneyFmt.format(r.amount),
          },
        ]}
        exportName="needle-broken"
        addHref={canManage ? `${BASE.broken}?tab=entry` : null}
        empty={<NoLines title={c("empty.brokens")} hint={c("empty.hint")} />}
        pending={pending}
        actions={(r) => (
          <>
            {step(r, ["DRAFT"], "confirm", ClipboardCheck)}
            {step(r, ["DRAFT", "CONFIRMED"], "cancel", Ban, true)}
            <PrintButton
              c={c}
              no={r.ref_no}
              onClick={() => print(r.ref_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

const BRK_HEAD = [
  "broken_date",
  "shift_id",
  "office_id",
  "machine_type_id",
  "remarks",
];
const BRK_LINE = [
  "machine_id",
  "category_id",
  "item_id",
  "uom",
  "qty",
  "cost_price",
  "remarks",
];

function BrokenEntry({ options, initial, canManage, act }) {
  const c = useNd();
  const [today] = useState(todayIso);
  const d = useDoc({
    c,
    path: "brokens",
    idKey: "ref_no",
    initial,
    asHead: (x) => ({
      ...Object.fromEntries(BRK_HEAD.map((k) => [k, str(x?.[k])])),
      broken_date: str(x?.broken_date) || today,
    }),
    asLine: (l) =>
      withCategory(options)(
        Object.fromEntries(BRK_LINE.map((k) => [k, str(l[k])])),
      ),
    canManage,
    act,
  });
  const { head, setH, open, lines, setL } = d;
  const machines = machinesOf(options, head.office_id, head.machine_type_id);
  function save() {
    d.save({
      ...clean(Object.fromEntries(BRK_HEAD.map((k) => [k, head[k]]))),
      lines: lines.map((l) => ({
        machine_id: l.machine_id || null,
        item_id: l.item_id || null,
        uom: l.uom || null,
        qty: orNull(l.qty),
        cost_price: orNull(l.cost_price),
        remarks: l.remarks || null,
      })),
    });
  }
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={d.doc ? d.doc.ref_no : c("tabs.broken_entry")}
          hint={c("hint.broken_form")}
          doc={d.doc}
          status={d.doc?.status}
          group="broken_status"
        />
        <Locked d={d} idKey="ref_no" group="broken_status" />
        <Errors errors={d.errors} />
        <Existing
          d={d}
          idKey="ref_no"
          docs={options.docs}
          label="f.existing_broken"
          ph="ph.new_broken"
        />
        <h3 className="mt-5 text-sm font-semibold">{c("sec.shift")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <NoField c={c} label="f.ref_no" value={d.doc?.ref_no} />
          <Text
            c={c}
            label="f.date"
            type="date"
            value={head.broken_date}
            onChange={(v) => setH({ broken_date: v })}
            disabled={!open}
            required
          />
          <Select
            c={c}
            label="f.shift"
            value={head.shift_id}
            onChange={(v) => setH({ shift_id: v })}
            options={options.shifts}
            ph="ph.shift"
            disabled={!open}
            required
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.where")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <CompanyField c={c} options={options} />
          <Select
            c={c}
            label="f.office"
            value={head.office_id}
            onChange={(v) => setH({ office_id: v })}
            options={options.offices}
            ph="ph.office"
            disabled={!open}
            hint={c("hint.office_machines")}
          />
          <Select
            c={c}
            label="f.machine_type"
            value={head.machine_type_id}
            onChange={(v) => setH({ machine_type_id: v })}
            options={options.machine_types}
            ph="ph.type"
            disabled={!open}
            hint={c("hint.machine_type")}
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.notes")}</h3>
        <Remarks
          c={c}
          value={head.remarks}
          onChange={(v) => setH({ remarks: v })}
          disabled={!open}
        />
      </section>
      <GridCard
        c={c}
        title={c("sec.machine_broken")}
        hint={c("hint.broken_lines")}
        button={c("btn.add_machine")}
        onAdd={() => d.add()}
        open={open}
        empty={c("empty.broken_lines")}
        count={lines.length}
      >
        <table className="w-full min-w-[1400px] text-sm">
          <GridHead
            c={c}
            cols={[
              ["machine"],
              ["category"],
              ["item"],
              ["uom"],
              ["qty_pcs", true],
              ["cost_price", true],
              ["amount", true],
              ["remarks"],
              [""],
            ]}
          />
          <tbody>
            {lines.map((l, i) => {
              const n = i + 1;
              return (
                <tr key={l.key} className="border-t border-border">
                  <LineSelect
                    value={l.machine_id}
                    onChange={(v) => setL(l.key, { machine_id: v })}
                    options={machines}
                    label={`${c("col.machine")} ${n}`}
                    disabled={!open}
                    width="min-w-[120px]"
                  />
                  <ItemCells
                    d={d}
                    l={l}
                    n={n}
                    options={options}
                    priceKey="cost_price"
                  />
                  <LineNum
                    value={l.qty}
                    onChange={(v) => setL(l.key, { qty: v })}
                    label={`${c("col.qty_pcs")} ${n}`}
                    disabled={!open}
                  />
                  <LineNum
                    value={l.cost_price}
                    onChange={(v) => setL(l.key, { cost_price: v })}
                    label={`${c("col.cost_price")} ${n}`}
                    disabled={!open}
                  />
                  <Cell className="text-right font-medium tabular-nums">
                    {moneyFmt.format(num(l.qty) * num(l.cost_price))}
                  </Cell>
                  <LineText
                    value={l.remarks}
                    onChange={(v) => setL(l.key, { remarks: v })}
                    label={`${c("col.remarks")} ${n}`}
                    disabled={!open}
                    max={300}
                  />
                  <RemoveCell
                    c={c}
                    n={n}
                    open={open}
                    onClick={() => d.remove(l.key)}
                  />
                </tr>
              );
            })}
          </tbody>
          <tfoot className="border-t border-border bg-surface-muted font-semibold">
            <tr>
              <td className="px-2 py-2" colSpan={4}>
                {c("total_lines", { n: lines.length })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.qty))) })}
              </td>
              <td />
              <td className="px-2 py-2 text-right tabular-nums">
                {moneyFmt.format(
                  sum(lines, (l) => num(l.qty) * num(l.cost_price)),
                )}
              </td>
              <td colSpan={2} />
            </tr>
          </tfoot>
        </table>
        <div className="px-3 pb-3">
          <Status c={c} foot={c("hint.broken_status")} />
        </div>
      </GridCard>
      <FormFoot
        c={c}
        hint={c("hint.broken_foot")}
        back={BASE.broken}
        onClear={() => d.show(null)}
        onSave={save}
        saveLabel={c("btn.save_broken")}
        pending={d.pending}
        open={open}
      />
    </div>
  );
}

/* ---------------- indents (machine-wise and loan) ---------------- */

function standingSteps(step, r) {
  return (
    <>
      {step(r, ["DRAFT"], "submit", Send)}
      {step(r, ["DRAFT"], "submit-verify", CheckCheck)}
      {step(r, ["WAITING"], "verify", ShieldCheck)}
      {step(r, ["VERIFIED", "PARTLY_ISSUED"], "close", Lock)}
      {step(r, ["DRAFT", "WAITING", "VERIFIED"], "cancel", Ban, true)}
    </>
  );
}

function ReqList({ options, initialRows, canManage, act }) {
  const c = useNd();
  const flt = useShowFilters({
    no: "",
    from: "",
    to: "",
    office: "",
    fromStore: "",
    toStore: "",
    reason: "",
    status: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "reqs",
    "req_no",
    setRows,
    setError,
  );
  const step = makeStep(c, canManage, move, pending, "req_no", act);
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.no || has(r.req_no, o.no)) &&
      inRange(o, r.req_date) &&
      (!o.office || r.office_id === o.office) &&
      (!o.fromStore || r.from_store_id === o.fromStore) &&
      (!o.toStore || r.to_store_id === o.toStore) &&
      (!o.reason || r.reason === o.reason) &&
      (!o.status || r.status === o.status),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`reqs/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printReq(c, res.data);
    });
  }
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <Text
            c={c}
            label="f.req_no"
            value={flt.f.no}
            onChange={(v) => flt.set("no", v)}
            placeholder="NRQ-…"
          />
          <DateRange c={c} flt={flt} />
          <Select
            c={c}
            label="f.office"
            value={flt.f.office}
            onChange={(v) => flt.set("office", v)}
            options={options.offices}
            ph="ph.office"
          />
          <Select
            c={c}
            label="f.from_store"
            value={flt.f.fromStore}
            onChange={(v) => flt.set("fromStore", v)}
            options={options.stores}
            ph="ph.store"
          />
          <Select
            c={c}
            label="f.to_store"
            value={flt.f.toStore}
            onChange={(v) => flt.set("toStore", v)}
            options={options.stores}
            ph="ph.store"
          />
          <Select
            c={c}
            label="f.reason"
            value={flt.f.reason}
            onChange={(v) => flt.set("reason", v)}
            options={listOpts(c, options.reasons, "reason")}
          />
          <Select
            c={c}
            label="f.status"
            value={flt.f.status}
            onChange={(v) => flt.set("status", v)}
            options={listOpts(c, options.req_statuses, "req_status")}
          />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.req_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(c, "req_no", "req_date", BASE.req, "req_status"),
          {
            key: "reason",
            label: c("col.reason"),
            text: (r) => c(`reason.${r.reason}`),
          },
          {
            key: "broken_no",
            label: c("col.broken"),
            text: (r) => r.broken_no ?? "—",
          },
          { key: "from_store", label: c("col.from_store") },
          { key: "to_store", label: c("col.to_store") },
          qtyCol(c, "req_qty", "col.rqd"),
          qtyCol(c, "issued", "col.iss"),
          qtyCol(c, "balance"),
        ]}
        exportName="needle-indents"
        addHref={canManage ? `${BASE.req}?tab=entry` : null}
        empty={<NoLines title={c("empty.reqs")} hint={c("empty.hint")} />}
        pending={pending}
        actions={(r) => (
          <>
            {standingSteps(step, r)}
            <PrintButton
              c={c}
              no={r.req_no}
              onClick={() => print(r.req_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

const REQ_HEAD = [
  "req_date",
  "reason",
  "broken_no",
  "office_id",
  "from_store_id",
  "to_store_id",
  "remarks",
];
const REQ_LINE = [
  "machine_id",
  "category_id",
  "item_id",
  "uom",
  "req_qty",
  "rate",
  "remarks",
];

function ReqEntry({ options, initial, canManage, act }) {
  const c = useNd();
  const [today] = useState(todayIso);
  const d = useDoc({
    c,
    path: "reqs",
    idKey: "req_no",
    initial,
    asHead: (x) => ({
      ...Object.fromEntries(REQ_HEAD.map((k) => [k, str(x?.[k])])),
      req_date: str(x?.req_date) || today,
    }),
    asLine: (l) =>
      withCategory(options)(
        Object.fromEntries(REQ_LINE.map((k) => [k, str(l[k])])),
      ),
    canManage,
    act,
  });
  const { head, setH, open, lines, setL } = d;
  const machines = machinesOf(options, head.office_id);
  // Picking a breakage record brings its machines and needles across, when the grid is still empty.
  function pickBroken(no) {
    setH({ broken_no: no });
    if (!no || lines.length) return;
    act(`brokens/${enc(no)}`).then((res) => {
      if (res?.status !== "success") return;
      setH({
        broken_no: no,
        office_id: head.office_id || str(res.data.office_id),
      });
      res.data.lines.forEach((l) =>
        d.add({
          machine_id: l.machine_id,
          item_id: l.item_id,
          uom: l.uom,
          req_qty: String(l.qty),
          rate: String(l.cost_price || ""),
        }),
      );
    });
  }
  function save() {
    d.save({
      ...clean(Object.fromEntries(REQ_HEAD.map((k) => [k, head[k]]))),
      lines: lines.map((l) => ({
        machine_id: l.machine_id || null,
        item_id: l.item_id || null,
        uom: l.uom || null,
        req_qty: orNull(l.req_qty),
        rate: orNull(l.rate),
        remarks: l.remarks || null,
      })),
    });
  }
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={d.doc ? d.doc.req_no : c("tabs.req_entry")}
          hint={c("hint.req_form")}
          doc={d.doc}
          status={d.doc?.status}
          group="req_status"
        />
        <Locked d={d} idKey="req_no" group="req_status" />
        <Errors errors={d.errors} />
        <Existing
          d={d}
          idKey="req_no"
          docs={options.docs}
          label="f.existing_req"
          ph="ph.new_req"
        />
        <h3 className="mt-5 text-sm font-semibold">{c("sec.req_info")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-4">
          <NoField c={c} label="f.req_no" value={d.doc?.req_no} />
          <Text
            c={c}
            label="f.req_date"
            type="date"
            value={head.req_date}
            onChange={(v) => setH({ req_date: v })}
            disabled={!open}
            required
          />
          <Select
            c={c}
            label="f.reason"
            value={head.reason}
            onChange={(v) => setH({ reason: v })}
            options={listOpts(c, options.reasons, "reason")}
            ph="ph.select_item"
            disabled={!open}
            required
          />
          <Select
            c={c}
            label="f.broken"
            value={head.broken_no}
            onChange={pickBroken}
            options={options.confirmed.filter(
              (b) =>
                !head.office_id ||
                !b.office_id ||
                b.office_id === head.office_id,
            )}
            ph="ph.broken"
            disabled={!open}
            hint={c("hint.broken_confirmed")}
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.raised_by")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <CompanyField c={c} options={options} />
          <Select
            c={c}
            label="f.office"
            value={head.office_id}
            onChange={(v) => setH({ office_id: v })}
            options={options.offices}
            ph="ph.office"
            disabled={!open}
            hint={c("hint.office_machines")}
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.stores")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Select
            c={c}
            label="f.from_store"
            value={head.from_store_id}
            onChange={(v) => setH({ from_store_id: v })}
            options={options.stores}
            ph="ph.store"
            disabled={!open}
            required
            hint={c("hint.from_store")}
          />
          <Select
            c={c}
            label="f.to_store"
            value={head.to_store_id}
            onChange={(v) => setH({ to_store_id: v })}
            options={options.stores}
            ph="ph.store"
            disabled={!open}
            required
            hint={c("hint.to_store")}
          />
        </div>
        <Remarks
          c={c}
          value={head.remarks}
          onChange={(v) => setH({ remarks: v })}
          disabled={!open}
        />
      </section>
      <GridCard
        c={c}
        title={c("sec.item_list")}
        hint={c("hint.req_lines")}
        button={c("btn.add_item")}
        onAdd={() => d.add()}
        open={open}
        empty={c("empty.req_lines")}
        count={lines.length}
      >
        <table className="w-full min-w-[1300px] text-sm">
          <GridHead
            c={c}
            cols={[
              ["machine"],
              ["category"],
              ["item"],
              ["uom"],
              ["rqd", true],
              ["rate", true],
              ["remarks"],
              [""],
            ]}
          />
          <tbody>
            {lines.map((l, i) => {
              const n = i + 1;
              return (
                <tr key={l.key} className="border-t border-border">
                  <LineSelect
                    value={l.machine_id}
                    onChange={(v) => setL(l.key, { machine_id: v })}
                    options={machines}
                    label={`${c("col.machine")} ${n}`}
                    disabled={!open}
                    width="min-w-[120px]"
                  />
                  <ItemCells d={d} l={l} n={n} options={options} />
                  <LineNum
                    value={l.req_qty}
                    onChange={(v) => setL(l.key, { req_qty: v })}
                    label={`${c("col.rqd")} ${n}`}
                    disabled={!open}
                  />
                  <LineNum
                    value={l.rate}
                    onChange={(v) => setL(l.key, { rate: v })}
                    label={`${c("col.rate")} ${n}`}
                    disabled={!open}
                  />
                  <LineText
                    value={l.remarks}
                    onChange={(v) => setL(l.key, { remarks: v })}
                    label={`${c("col.remarks")} ${n}`}
                    disabled={!open}
                    max={300}
                  />
                  <RemoveCell
                    c={c}
                    n={n}
                    open={open}
                    onClick={() => d.remove(l.key)}
                  />
                </tr>
              );
            })}
          </tbody>
          <tfoot className="border-t border-border bg-surface-muted font-semibold">
            <tr>
              <td className="px-2 py-2" colSpan={4}>
                {c("total_lines", { n: lines.length })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.req_qty))) })}
              </td>
              <td colSpan={3} />
            </tr>
          </tfoot>
        </table>
        <div className="px-3 pb-3">
          <Status c={c} foot={c("hint.req_status")} />
        </div>
      </GridCard>
      <FormFoot
        c={c}
        hint={c("hint.req_foot")}
        back={BASE.req}
        onClear={() => d.show(null)}
        onSave={save}
        saveLabel={c("btn.save_req")}
        pending={d.pending}
        open={open}
      />
    </div>
  );
}

/* ---------------- machine-wise issues ---------------- */

function IssueList({ options, initialRows, canManage, act }) {
  const c = useNd();
  const flt = useShowFilters({
    req: "",
    no: "",
    from: "",
    to: "",
    fromStore: "",
    toStore: "",
    closed: "",
    status: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.req || has(r.req_no, o.req)) &&
      (!o.no || has(r.issue_no, o.no)) &&
      inRange(o, r.issue_date) &&
      (!o.fromStore || r.from_store_id === o.fromStore) &&
      (!o.toStore || r.to_store_id === o.toStore) &&
      (!o.closed || (o.closed === "Y") === (r.req_status === "CLOSED")) &&
      (!o.status || r.req_status === o.status),
  );
  function withdraw(no) {
    startTransition(async () => {
      const res = await act(`issues/${enc(no)}/withdraw`, "POST");
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows((all) =>
        all.map((r) =>
          r.issue_no === no
            ? { ...r, status: res.data.status, req_status: res.data.req_status }
            : r,
        ),
      );
      setError(null);
      toastManager.add({ title: c("done.withdraw", { no }), type: "success" });
    });
  }
  function print(no) {
    startTransition(async () => {
      const res = await act(`issues/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printIssue(c, res.data);
    });
  }
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <Text
            c={c}
            label="f.req_no"
            value={flt.f.req}
            onChange={(v) => flt.set("req", v)}
            placeholder="NRQ-…"
          />
          <Text
            c={c}
            label="f.issue_no"
            value={flt.f.no}
            onChange={(v) => flt.set("no", v)}
            placeholder="NIS-…"
          />
          <DateRange c={c} flt={flt} />
          <Select
            c={c}
            label="f.from_store"
            value={flt.f.fromStore}
            onChange={(v) => flt.set("fromStore", v)}
            options={options.stores}
            ph="ph.store"
          />
          <Select
            c={c}
            label="f.to_store"
            value={flt.f.toStore}
            onChange={(v) => flt.set("toStore", v)}
            options={options.stores}
            ph="ph.store"
          />
          <Select
            c={c}
            label="f.closed"
            value={flt.f.closed}
            onChange={(v) => flt.set("closed", v)}
            options={[
              { value: "Y", label: c("yes") },
              { value: "N", label: c("no") },
            ]}
          />
          <Select
            c={c}
            label="f.status"
            value={flt.f.status}
            onChange={(v) => flt.set("status", v)}
            options={listOpts(c, options.req_statuses, "req_status")}
          />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.issue_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(c, "issue_no", "issue_date", BASE.issue, "issue_status"),
          {
            key: "req_no",
            label: c("col.req_no"),
            render: (r) => (
              <span className="flex flex-col items-start gap-1">
                <span>{r.req_no}</span>
                <StatusChip
                  status={TONE[r.req_status]}
                  label={c(`req_status.${r.req_status}`)}
                />
              </span>
            ),
            text: (r) => r.req_no,
          },
          { key: "to_store", label: c("col.released_from") },
          { key: "from_store", label: c("col.released_to") },
          { key: "lines", label: c("col.lines"), num: true },
          qtyCol(c, "qty", "col.iss_qty"),
        ]}
        exportName="needle-issues"
        addHref={canManage ? `${BASE.issue}?tab=entry` : null}
        empty={
          <NoLines title={c("empty.issues")} hint={c("empty.issue_hint")} />
        }
        pending={pending}
        actions={(r) => (
          <>
            {canManage && r.status === "ISSUED" ? (
              <MoveButton
                icon={Undo2}
                label={c("btn.withdraw")}
                no={r.issue_no}
                onClick={() => withdraw(r.issue_no)}
                disabled={pending}
                danger
              />
            ) : null}
            <PrintButton
              c={c}
              no={r.issue_no}
              onClick={() => print(r.issue_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

function IssueEntry({ options, initial, owing, canManage, act }) {
  const c = useNd();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [doc, setDoc] = useState(initial);
  const [store, setStore] = useState("");
  const [req, setReq] = useState(null);
  const [date, setDate] = useState(today);
  const [remarks, setRemarks] = useState("");
  const [lines, setLines] = useState([]);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && !doc;
  function answer(r) {
    setReq(r);
    setLines(
      r.owing_lines
        .filter((l) => l.owing > 0)
        .map((l) => ({ ...l, qty: String(l.owing), remarks: "" })),
    );
    setErrors(null);
  }
  function load(no) {
    if (!no) {
      setDoc(null);
      setReq(null);
      setLines([]);
      return window.history.replaceState(null, "", "?tab=entry");
    }
    startTransition(async () => {
      const res = await act(`issues/${enc(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setDoc(res.data);
      window.history.replaceState(null, "", `?tab=entry&ref=${enc(no)}`);
    });
  }
  function save() {
    startTransition(async () => {
      const res = await act("issues", "POST", {
        issue_date: date,
        req_no: req?.req_no ?? null,
        remarks: remarks || null,
        lines: lines.map((l) => ({
          req_line_id: l.req_line_id,
          qty: orNull(l.qty),
          remarks: l.remarks || null,
        })),
      });
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setDoc(res.data);
      setErrors(null);
      window.history.replaceState(
        null,
        "",
        `?tab=entry&ref=${enc(res.data.issue_no)}`,
      );
      toastManager.add({
        title: c("done.issued", { no: res.data.issue_no }),
        type: "success",
      });
    });
  }
  const shownLines = doc ? doc.lines : lines;
  const rows = owing
    .filter((r) => !store || r.to_store_id === store)
    .map((r) => ({ ...r, key: r.req_no }));
  return (
    <div className="grid gap-4 xl:grid-cols-[420px_1fr]">
      <PickPanel
        c={c}
        title={c("sec.req_list")}
        filter={
          <Field label={c("f.from_store")} className="mt-3">
            <PSelect
              value={store}
              onValueChange={(v) => setStore(v ?? "")}
              options={pick(options.stores, c("ph.store"))}
              aria-label={`${c("f.from_store")} (${c("sec.req_list")})`}
            />
          </Field>
        }
        placeholder={c("ph.req_search")}
        rows={rows}
        columns={[
          [c("col.req_no"), (r) => r.req_no],
          [c("col.date"), (r) => dayFmt(r.req_date)],
          [c("col.reason"), (r) => c(`reason.${r.reason}`)],
          [c("col.owing"), (r) => q3(r.balance), true],
        ]}
        action={(r) => (
          <Button
            variant={req?.req_no === r.req_no ? "default" : "outline"}
            size="sm"
            onClick={() => answer(r)}
            disabled={!open}
            aria-label={`${c("btn.answer")} ${r.req_no}`}
          >
            {c("btn.answer")}
          </Button>
        )}
      />
      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <FormHead
            c={c}
            title={doc ? doc.issue_no : c("tabs.issue_entry")}
            hint={c("hint.issue_form")}
            doc={doc}
            status={doc?.status}
            group="issue_status"
          />
          <Errors errors={errors} />
          <Field
            label={c("f.existing_issue")}
            hint={c("hint.existing")}
            className="mt-4"
          >
            <PSelect
              value={doc?.issue_no ?? ""}
              onValueChange={(v) => load(v ?? "")}
              options={pick(options.docs, c("ph.new_issue"))}
              disabled={pending}
              aria-label={c("f.existing_issue")}
            />
          </Field>
          <h3 className="mt-5 text-sm font-semibold">{c("sec.issue_info")}</h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <NoField c={c} label="f.issue_no" value={doc?.issue_no} />
            <Text
              c={c}
              label="f.issue_date"
              type="date"
              value={doc ? doc.issue_date : date}
              onChange={setDate}
              disabled={!open}
              required
            />
            <Field label={c("f.req_no")} hint={c("hint.issue_req")}>
              <Input
                value={doc?.req_no ?? req?.req_no ?? ""}
                placeholder={c("ph.pick_req")}
                disabled
                aria-label={c("f.req_no")}
              />
            </Field>
          </div>
          <h3 className="mt-5 text-sm font-semibold">{c("sec.stores")}</h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <Field label={c("f.released_from")} required>
              <Input
                value={doc?.to_store ?? req?.to_store ?? ""}
                disabled
                aria-label={c("f.released_from")}
              />
            </Field>
            <Field label={c("f.released_to")} required>
              <Input
                value={doc?.from_store ?? req?.from_store ?? ""}
                disabled
                aria-label={c("f.released_to")}
              />
            </Field>
          </div>
          <Remarks
            c={c}
            value={doc ? str(doc.remarks) : remarks}
            onChange={setRemarks}
            disabled={!open}
          />
        </section>
        <section className={cn(card, "p-5")}>
          <h3 className="text-base font-semibold">{c("sec.item_list")}</h3>
          <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
            {c("hint.issue_lines")}
          </p>
          <div className="mt-4 overflow-x-auto rounded-lg border border-border">
            {shownLines.length ? (
              <table className="w-full min-w-[1000px] text-sm">
                <GridHead
                  c={c}
                  cols={[
                    ["machine"],
                    ["item"],
                    ["uom"],
                    ["rqd", true],
                    ["already_issued", true],
                    ["iss_qty", true],
                    ["remarks"],
                    [""],
                  ]}
                />
                <tbody>
                  {shownLines.map((l, i) => {
                    const n = i + 1;
                    return (
                      <tr
                        key={l.req_line_id}
                        className="border-t border-border"
                      >
                        <Cell>{l.machine ?? "—"}</Cell>
                        <Cell className="font-medium">{l.item}</Cell>
                        <Cell>{l.uom ?? "—"}</Cell>
                        <Cell className="text-right tabular-nums">
                          {q3(l.req_qty)}
                        </Cell>
                        <Cell className="text-right tabular-nums">
                          {q3(l.issued)}
                        </Cell>
                        {doc ? (
                          <Cell className="text-right font-medium tabular-nums">
                            {q3(l.qty)}
                          </Cell>
                        ) : (
                          <LineNum
                            value={l.qty}
                            onChange={(v) =>
                              setLines((all) =>
                                all.map((x, k) =>
                                  k === i ? { ...x, qty: v } : x,
                                ),
                              )
                            }
                            label={`${c("col.iss_qty")} ${n}`}
                            disabled={!open}
                          />
                        )}
                        {doc ? (
                          <Cell>{l.remarks ?? "—"}</Cell>
                        ) : (
                          <LineText
                            value={l.remarks}
                            onChange={(v) =>
                              setLines((all) =>
                                all.map((x, k) =>
                                  k === i ? { ...x, remarks: v } : x,
                                ),
                              )
                            }
                            label={`${c("col.remarks")} ${n}`}
                            disabled={!open}
                            max={300}
                          />
                        )}
                        <RemoveCell
                          c={c}
                          n={n}
                          open={open}
                          onClick={() =>
                            setLines((all) => all.filter((_, k) => k !== i))
                          }
                        />
                      </tr>
                    );
                  })}
                </tbody>
                <tfoot className="border-t border-border bg-surface-muted font-semibold">
                  <tr>
                    <td className="px-2 py-2" colSpan={5}>
                      {c("total_lines", { n: shownLines.length })}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {q3(sum(shownLines, (l) => num(l.qty)))}
                    </td>
                    <td colSpan={2} />
                  </tr>
                </tfoot>
              </table>
            ) : (
              <NoLines title={c("empty.lines")} hint={c("empty.issue_lines")} />
            )}
          </div>
          <Status c={c} foot={c("hint.issue_status")} />
        </section>
        <FormFoot
          c={c}
          hint={c("hint.issue_foot")}
          back={BASE.issue}
          onClear={() => load("")}
          onSave={save}
          saveLabel={c("btn.save_issue")}
          pending={pending}
          open={open}
        />
      </div>
    </div>
  );
}

/* ---------------- loan indents ---------------- */

function LoanList({
  options,
  initialRows,
  canManage,
  act,
  book = "NEEDLE",
  fixedKind = null,
  base = BASE.loan,
}) {
  const c = useNd();
  const flt = useShowFilters({
    no: "",
    store: "",
    party: "",
    from: "",
    to: "",
    status: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "loan-reqs",
    "req_no",
    setRows,
    setError,
  );
  const step = makeStep(c, canManage, move, pending, "req_no", act);
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.no || has(r.req_no, o.no)) &&
      (!o.store || r.store_id === o.store) &&
      (!o.party || r.party_id === o.party) &&
      inRange(o, r.req_date) &&
      (!o.status || r.status === o.status),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`loan-reqs/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printLoan(c, res.data);
    });
  }
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <Text
            c={c}
            label="f.req_no"
            value={flt.f.no}
            onChange={(v) => flt.set("no", v)}
            placeholder={book === "OIL" ? "OLR-…" : "NLR-…"}
          />
          <Select
            c={c}
            label="f.store"
            value={flt.f.store}
            onChange={(v) => flt.set("store", v)}
            options={options.stores}
            ph="ph.store"
          />
          <Select
            c={c}
            label={fixedKind ? "f.supplier" : "f.party"}
            value={flt.f.party}
            onChange={(v) => flt.set("party", v)}
            options={options.parties}
            ph="ph.party"
          />
          <DateRange c={c} flt={flt} />
          <Select
            c={c}
            label="f.status"
            value={flt.f.status}
            onChange={(v) => flt.set("status", v)}
            options={listOpts(c, options.req_statuses, "req_status")}
          />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c(
          fixedKind ? `hint.${dir(fixedKind)}_list_${book}` : "hint.loan_list",
        )}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(c, "req_no", "req_date", base, "req_status"),
          ...(fixedKind
            ? []
            : [
                {
                  key: "kind",
                  label: c("col.req_type"),
                  text: (r) => c(`kind.${r.kind}`),
                },
              ]),
          { key: "party", label: c(fixedKind ? "col.supplier" : "col.party") },
          { key: "store", label: c("col.store"), text: (r) => r.store ?? "—" },
          qtyCol(c, "required"),
          qtyCol(c, "issued"),
          qtyCol(c, "balance"),
          // What the party has handed back, and still owes.
          ...(fixedKind === "LOAN_ISSUE"
            ? [qtyCol(c, "returned"), qtyCol(c, "owes")]
            : []),
        ]}
        exportName={`${book.toLowerCase()}-loan-indents`}
        addHref={canManage ? `${base}?tab=entry` : null}
        empty={<NoLines title={c("empty.loans")} hint={c("empty.hint")} />}
        pending={pending}
        actions={(r) => (
          <>
            {standingSteps(step, r)}
            <PrintButton
              c={c}
              no={r.req_no}
              onClick={() => print(r.req_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

const LOAN_HEAD = [
  "req_date",
  "kind",
  "req_type",
  "office_id",
  "department_id",
  "party_id",
  "store_id",
  "reason",
  "remarks",
];
const LOAN_LINE = [
  "id",
  "category_id",
  "item_id",
  "uom",
  "required_qty",
  "issue_qty",
  "rate",
  "remarks",
];

function LoanEntry({
  options,
  initial,
  canManage,
  act,
  book = "NEEDLE",
  fixedKind = null,
  base = BASE.loan,
}) {
  const c = useNd();
  const [today] = useState(todayIso);
  const d = useDoc({
    c,
    path: "loan-reqs",
    idKey: "req_no",
    initial,
    asHead: (x) => ({
      ...Object.fromEntries(LOAN_HEAD.map((k) => [k, str(x?.[k])])),
      req_date: str(x?.req_date) || today,
      kind: str(x?.kind) || (fixedKind ?? ""),
    }),
    asLine: (l) =>
      withCategory(options)(
        Object.fromEntries(LOAN_LINE.map((k) => [k, str(l[k])])),
      ),
    canManage,
    act,
  });
  const { head, setH, lines, setL } = d;
  // Once verified, only Issue Qty may be changed here.
  const issuing =
    canManage &&
    ["VERIFIED", "PARTLY_ISSUED", "ISSUED"].includes(d.doc?.status);
  const { open } = d;
  function save() {
    if (issuing) {
      return d.save({
        lines: lines.map((l) => ({ id: l.id, issue_qty: orNull(l.issue_qty) })),
      });
    }
    d.save({
      ...clean(Object.fromEntries(LOAN_HEAD.map((k) => [k, head[k]]))),
      book,
      lines: lines.map((l) => ({
        item_id: l.item_id || null,
        uom: l.uom || null,
        required_qty: orNull(l.required_qty),
        rate: orNull(l.rate),
        remarks: l.remarks || null,
      })),
    });
  }
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={
            d.doc
              ? d.doc.req_no
              : c(
                  fixedKind
                    ? `tabs.${dir(fixedKind)}_entry`
                    : "tabs.loan_entry",
                )
          }
          hint={c(
            fixedKind
              ? `hint.${dir(fixedKind)}_form_${book}`
              : "hint.loan_form",
          )}
          doc={d.doc}
          status={d.doc?.status}
          group="req_status"
        />
        {issuing ? (
          <Alert variant="info" className="mt-3">
            {c("hint.loan_issuing")}
          </Alert>
        ) : (
          <Locked d={d} idKey="req_no" group="req_status" />
        )}
        <Errors errors={d.errors} />
        <Existing
          d={d}
          idKey="req_no"
          docs={options.docs}
          label="f.existing_req"
          ph="ph.new_req"
        />
        <h3 className="mt-5 text-sm font-semibold">{c("sec.req_info")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-4">
          <NoField c={c} label="f.req_no" value={d.doc?.req_no} />
          <Text
            c={c}
            label="f.req_date"
            type="date"
            value={head.req_date}
            onChange={(v) => setH({ req_date: v })}
            disabled={!open}
            required
          />
          {fixedKind ? null : (
            <Select
              c={c}
              label="f.kind"
              value={head.kind}
              onChange={(v) => setH({ kind: v })}
              options={listOpts(c, options.loan_kinds, "kind")}
              ph="ph.kind"
              disabled={!open || Boolean(d.doc)}
              required
              hint={c("hint.kind")}
            />
          )}
          <Select
            c={c}
            label="f.req_type"
            value={head.req_type}
            onChange={(v) => setH({ req_type: v })}
            options={listOpts(c, options.req_types, "req_type")}
            disabled={!open}
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.raised_by")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <CompanyField c={c} options={options} />
          <Select
            c={c}
            label="f.office"
            value={head.office_id}
            onChange={(v) => setH({ office_id: v })}
            options={options.offices}
            ph="ph.office"
            disabled={!open}
          />
          <Select
            c={c}
            label="f.department"
            value={head.department_id}
            onChange={(v) => setH({ department_id: v })}
            options={options.departments}
            ph="ph.department"
            disabled={!open}
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.against")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Select
            c={c}
            label={fixedKind ? "f.supplier" : "f.party"}
            value={head.party_id}
            onChange={(v) => setH({ party_id: v })}
            options={options.parties}
            ph={fixedKind ? "ph.supplier" : "ph.party"}
            disabled={!open}
            required
          />
          <Select
            c={c}
            label="f.store"
            value={head.store_id}
            onChange={(v) => setH({ store_id: v })}
            options={options.stores}
            ph="ph.store"
            disabled={!open}
            hint={c(book === "OIL" ? "hint.lo_store_OIL" : "hint.loan_store")}
          />
          <Text
            c={c}
            label="f.reason"
            value={head.reason}
            onChange={(v) => setH({ reason: v })}
            disabled={!open}
            max={300}
            placeholder={c(book === "OIL" ? "ph.oil_reason" : "ph.loan_reason")}
          />
        </div>
        <Remarks
          c={c}
          value={head.remarks}
          onChange={(v) => setH({ remarks: v })}
          disabled={!open}
        />
      </section>
      <GridCard
        c={c}
        title={c("sec.item_list")}
        hint={c(book === "OIL" ? "hint.lo_lines_OIL" : "hint.loan_lines")}
        button={c("btn.add_item")}
        onAdd={() => d.add()}
        open={open}
        empty={c(book === "OIL" ? "empty.oil_lines" : "empty.loan_lines")}
        count={lines.length}
      >
        <table className="w-full min-w-[1300px] text-sm">
          <GridHead
            c={c}
            cols={[
              ["category"],
              ["item"],
              ["uom"],
              ["required_qty", true],
              ["issue_qty", true],
              ["rate", true],
              ["remarks"],
              [""],
            ]}
          />
          <tbody>
            {lines.map((l, i) => {
              const n = i + 1;
              return (
                <tr key={l.key} className="border-t border-border">
                  <ItemCells d={d} l={l} n={n} options={options} />
                  <LineNum
                    value={l.required_qty}
                    onChange={(v) => setL(l.key, { required_qty: v })}
                    label={`${c("col.required_qty")} ${n}`}
                    disabled={!open}
                  />
                  <LineNum
                    value={l.issue_qty}
                    onChange={(v) => setL(l.key, { issue_qty: v })}
                    label={`${c("col.issue_qty")} ${n}`}
                    disabled={!issuing}
                  />
                  <LineNum
                    value={l.rate}
                    onChange={(v) => setL(l.key, { rate: v })}
                    label={`${c("col.rate")} ${n}`}
                    disabled={!open}
                  />
                  <LineText
                    value={l.remarks}
                    onChange={(v) => setL(l.key, { remarks: v })}
                    label={`${c("col.remarks")} ${n}`}
                    disabled={!open}
                    max={300}
                  />
                  <RemoveCell
                    c={c}
                    n={n}
                    open={open}
                    onClick={() => d.remove(l.key)}
                  />
                </tr>
              );
            })}
          </tbody>
          <tfoot className="border-t border-border bg-surface-muted font-semibold">
            <tr>
              <td className="px-2 py-2" colSpan={3}>
                {c("total_lines", { n: lines.length })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.required_qty))) })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.issue_qty))) })}
              </td>
              <td colSpan={3} />
            </tr>
          </tfoot>
        </table>
        <div className="px-3 pb-3">
          <Status c={c} foot={c("hint.loan_status")} />
        </div>
      </GridCard>
      <FormFoot
        c={c}
        hint={c("hint.loan_foot")}
        back={base}
        onClear={() => d.show(null)}
        onSave={save}
        saveLabel={c("btn.save_req")}
        pending={d.pending}
        open={open || issuing}
      />
    </div>
  );
}

/* ---------------- loan returns from a party ---------------- */

const BACK = {
  NEEDLE: "/knit-production/needle-loan-return-by-party",
  OIL: "/knit-production/oil-loan-return-by-party",
};
const bookSlug = (book) => book.toLowerCase();

function printLoanReturn(c, d) {
  printDocument({
    title: c(`p.loan_return_${d.book}`),
    docNo: d.return_no,
    orientation: "landscape",
    meta: [
      [c("f.ret_challan_no"), d.ret_challan_no],
      [c("f.ret_challan_date"), dayFmt(d.ret_challan_date)],
      [c("f.rcv_date"), d.rcv_date ? dayFmt(d.rcv_date) : null],
      [c("f.against_req"), d.req_no],
      [c("f.supplier_name"), d.party],
      [c("p.address"), d.party_address],
      [c("f.company"), d.company],
      [c("f.receiving_store"), d.store],
      [c("f.received_by"), d.receiver],
      [c("f.gate_pass"), d.gate_pass_no],
      [c("f.vehicle"), d.vehicle_no],
      [c("f.status"), { v: c(`loan_return_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 4,
    sections: [
      linesSection(
        c,
        c("sec.item_list"),
        d.lines,
        [
          [c("col.item"), (l) => ({ v: l.item, bold: true })],
          [c("col.uom"), (l) => l.uom],
          [c("col.return_qty"), (l) => q3(l.return_qty), true],
          [c("col.received_qty"), (l) => q3(l.received_qty), true],
          [c("col.rate"), (l) => moneyFmt.format(l.rate), true],
          [c("col.remarks"), (l) => l.remarks],
        ],
        [
          "",
          c("f.total"),
          q3(sum(d.lines, (l) => l.return_qty)),
          q3(sum(d.lines, (l) => l.received_qty)),
          "",
          "",
        ],
      ),
    ],
    notes: [d.remarks],
    signatures: [
      c("p.party_rep"),
      c("p.security"),
      c("p.received_by"),
      c("p.store_in_charge"),
    ],
  });
}

function LoanReturnList({ book, options, initialRows, canManage, act }) {
  const c = useNd();
  const path = `loan-returns/${bookSlug(book)}`;
  const flt = useShowFilters({
    no: "",
    from: "",
    to: "",
    store: "",
    party: "",
    final: "",
    status: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    path,
    "return_no",
    setRows,
    setError,
    // Receiving dates the challan; cancelling a received one clears it.
    (d) =>
      setRows((all) =>
        all.map((r) =>
          r.return_no === d.return_no
            ? {
                ...r,
                rcv_date: d.rcv_date,
                received_qty: sum(d.lines, (l) => l.received_qty),
              }
            : r,
        ),
      ),
  );
  const step = makeStep(c, canManage, move, pending, "return_no", act);
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.no || has(r.ret_challan_no, o.no) || has(r.return_no, o.no)) &&
      inRange(o, r.ret_challan_date) &&
      (!o.store || r.store_id === o.store) &&
      (!o.party || r.party_id === o.party) &&
      (!o.final || (o.final === "Y") === (r.status === "FINALIZED")) &&
      (!o.status || r.status === o.status),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`${path}/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printLoanReturn(c, res.data);
    });
  }
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <Text
            c={c}
            label="f.ret_challan_no"
            value={flt.f.no}
            onChange={(v) => flt.set("no", v)}
            placeholder={c("ph.search_challan")}
          />
          <DateRange c={c} flt={flt} />
          <Select
            c={c}
            label="f.receiving_store"
            value={flt.f.store}
            onChange={(v) => flt.set("store", v)}
            options={options.stores}
            ph="ph.store"
          />
          <Select
            c={c}
            label="f.supplier"
            value={flt.f.party}
            onChange={(v) => flt.set("party", v)}
            options={options.parties}
            ph="ph.supplier"
          />
          <Select
            c={c}
            label="f.finalized"
            value={flt.f.final}
            onChange={(v) => flt.set("final", v)}
            options={[
              { value: "Y", label: c("finalised") },
              { value: "N", label: c("still_open") },
            ]}
          />
          <Select
            c={c}
            label="f.status"
            value={flt.f.status}
            onChange={(v) => flt.set("status", v)}
            options={listOpts(
              c,
              options.loan_return_statuses,
              "loan_return_status",
            )}
          />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c(`hint.lb_list_${book}`)}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(
            c,
            "return_no",
            "ret_challan_date",
            BACK[book],
            "loan_return_status",
          ),
          { key: "ret_challan_no", label: c("col.ret_challan_no") },
          {
            key: "rcv_date",
            label: c("col.rcv_date"),
            nowrap: true,
            text: (r) => (r.rcv_date ? dayFmt(r.rcv_date) : "—"),
          },
          {
            key: "req_no",
            label: c("col.against_req"),
            text: (r) => r.req_no ?? "—",
          },
          { key: "party", label: c("col.supplier") },
          { key: "store", label: c("col.receiving_store") },
          { key: "lines", label: c("col.lines"), num: true },
          qtyCol(c, "return_qty"),
          qtyCol(c, "received_qty"),
        ]}
        exportName={`${bookSlug(book)}-loan-returns`}
        addHref={canManage ? `${BACK[book]}?tab=entry` : null}
        empty={
          <NoLines
            title={c("empty.loan_returns")}
            hint={c(`empty.lb_hint_${book}`)}
          />
        }
        pending={pending}
        actions={(r) => (
          <>
            {step(r, ["DRAFT"], "receive", PackageCheck)}
            {step(r, ["RECEIVED"], "finalize", BadgeCheck)}
            {step(r, ["DRAFT", "RECEIVED"], "cancel", Ban, true)}
            <PrintButton
              c={c}
              no={r.return_no}
              onClick={() => print(r.return_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

const LB_HEAD = [
  "ret_challan_no",
  "ret_challan_date",
  "req_no",
  "party_id",
  "store_id",
  "received_by",
  "gate_pass_no",
  "vehicle_no",
  "remarks",
];
const LB_LINE = [
  "category_id",
  "item_id",
  "uom",
  "return_qty",
  "received_qty",
  "rate",
  "remarks",
];

function LoanReturnEntry({ book, options, initial, owing, canManage, act }) {
  const c = useNd();
  const [today] = useState(todayIso);
  const d = useDoc({
    c,
    path: `loan-returns/${bookSlug(book)}`,
    idKey: "return_no",
    initial,
    asHead: (x) => ({
      ...Object.fromEntries(LB_HEAD.map((k) => [k, str(x?.[k])])),
      ret_challan_date: str(x?.ret_challan_date) || today,
    }),
    asLine: (l) =>
      withCategory(options)(
        Object.fromEntries(LB_LINE.map((k) => [k, str(l[k])])),
      ),
    canManage,
    act,
  });
  const { head, setH, open, lines, setL } = d;
  const reqs = owing.filter(
    (r) => !head.party_id || r.party_id === head.party_id,
  );
  if (head.req_no && !reqs.some((r) => r.value === head.req_no))
    reqs.unshift({ value: head.req_no, label: head.req_no, lines: [] });
  // Picking the loan issue brings its party, store and what is still owed, when the grid is empty.
  function pickReq(no) {
    const r = owing.find((x) => x.value === no);
    setH({
      req_no: no,
      party_id: head.party_id || str(r?.party_id),
      store_id: head.store_id || str(r?.store_id),
    });
    if (r && !lines.length)
      r.lines.forEach((l) =>
        d.add({
          category_id: l.category_id,
          item_id: l.item_id,
          uom: l.uom,
          return_qty: String(l.owes),
          rate: String(l.rate || ""),
        }),
      );
  }
  function save() {
    d.save({
      ...clean(Object.fromEntries(LB_HEAD.map((k) => [k, head[k]]))),
      lines: lines.map((l) => ({
        item_id: l.item_id || null,
        uom: l.uom || null,
        return_qty: orNull(l.return_qty),
        received_qty: orNull(l.received_qty),
        rate: orNull(l.rate),
        remarks: l.remarks || null,
      })),
    });
  }
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={d.doc ? d.doc.return_no : c(`tabs.lb_entry_${book}`)}
          hint={c("hint.lb_form")}
          doc={d.doc}
          status={d.doc?.status}
          group="loan_return_status"
        />
        <Locked d={d} idKey="return_no" group="loan_return_status" />
        <Errors errors={d.errors} />
        <Existing
          d={d}
          idKey="return_no"
          docs={options.docs}
          label="f.existing_return"
          ph="ph.new_return"
        />
        <h3 className="mt-5 text-sm font-semibold">
          {c("sec.return_challan")}
        </h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Text
            c={c}
            label="f.ret_challan_no"
            value={head.ret_challan_no}
            onChange={(v) => setH({ ret_challan_no: v })}
            disabled={!open}
            required
            placeholder={c("ph.party_challan")}
          />
          <Text
            c={c}
            label="f.ret_challan_date"
            type="date"
            value={head.ret_challan_date}
            onChange={(v) => setH({ ret_challan_date: v })}
            disabled={!open}
            required
          />
          <Select
            c={c}
            label="f.against_req"
            value={head.req_no}
            onChange={pickReq}
            options={reqs}
            ph="ph.req"
            disabled={!open}
            hint={c(`hint.lb_req_${book}`)}
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.from")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-4">
          <Select
            c={c}
            label="f.supplier_name"
            value={head.party_id}
            onChange={(v) => setH({ party_id: v, req_no: "" })}
            options={options.parties}
            ph="ph.supplier"
            disabled={!open}
            required
          />
          <CompanyField c={c} options={options} />
          <Select
            c={c}
            label="f.receiving_store"
            value={head.store_id}
            onChange={(v) => setH({ store_id: v })}
            options={options.stores}
            ph="ph.store"
            disabled={!open}
            required
          />
          <Select
            c={c}
            label="f.received_by"
            value={head.received_by}
            onChange={(v) => setH({ received_by: v })}
            options={options.employees}
            ph="ph.select"
            disabled={!open}
            required
            hint={c(`hint.lb_receiver_${book}`)}
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.gate")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Text
            c={c}
            label="f.gate_pass"
            value={head.gate_pass_no}
            onChange={(v) => setH({ gate_pass_no: v })}
            disabled={!open}
            max={40}
          />
          <Text
            c={c}
            label="f.vehicle"
            value={head.vehicle_no}
            onChange={(v) => setH({ vehicle_no: v })}
            disabled={!open}
            max={40}
          />
        </div>
        <Remarks
          c={c}
          value={head.remarks}
          onChange={(v) => setH({ remarks: v })}
          disabled={!open}
        />
      </section>
      <GridCard
        c={c}
        title={c("sec.item_list")}
        hint={c(`hint.lb_lines_${book}`)}
        button={c("btn.add_item")}
        onAdd={() => d.add()}
        open={open}
        empty={c(`empty.lb_lines_${book}`)}
        count={lines.length}
      >
        <table className="w-full min-w-[1300px] text-sm">
          <GridHead
            c={c}
            cols={[
              ["category"],
              ["item"],
              ["uom"],
              ["return_qty", true],
              ["received_qty", true],
              ["rate", true],
              ["remarks"],
              [""],
            ]}
          />
          <tbody>
            {lines.map((l, i) => {
              const n = i + 1;
              return (
                <tr key={l.key} className="border-t border-border">
                  <ItemCells d={d} l={l} n={n} options={options} />
                  <LineNum
                    value={l.return_qty}
                    onChange={(v) => setL(l.key, { return_qty: v })}
                    label={`${c("col.return_qty")} ${n}`}
                    disabled={!open}
                  />
                  <LineNum
                    value={l.received_qty}
                    onChange={(v) => setL(l.key, { received_qty: v })}
                    label={`${c("col.received_qty")} ${n}`}
                    disabled={!open}
                  />
                  <LineNum
                    value={l.rate}
                    onChange={(v) => setL(l.key, { rate: v })}
                    label={`${c("col.rate")} ${n}`}
                    disabled={!open}
                  />
                  <LineText
                    value={l.remarks}
                    onChange={(v) => setL(l.key, { remarks: v })}
                    label={`${c("col.remarks")} ${n}`}
                    disabled={!open}
                    max={300}
                  />
                  <RemoveCell
                    c={c}
                    n={n}
                    open={open}
                    onClick={() => d.remove(l.key)}
                  />
                </tr>
              );
            })}
          </tbody>
          <tfoot className="border-t border-border bg-surface-muted font-semibold">
            <tr>
              <td className="px-2 py-2" colSpan={3}>
                {c("total_lines", { n: lines.length })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.return_qty))) })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.received_qty))) })}
              </td>
              <td colSpan={3} />
            </tr>
          </tfoot>
        </table>
        <div className="px-3 pb-3">
          <Status c={c} foot={c("hint.lb_status")} />
        </div>
      </GridCard>
      <FormFoot
        c={c}
        hint={c(`hint.lb_foot_${book}`)}
        back={BACK[book]}
        onClear={() => d.show(null)}
        onSave={save}
        saveLabel={c("btn.save_return")}
        pending={d.pending}
        open={open}
      />
    </div>
  );
}

/* ---------------- borrowed receipts ---------------- */

const BORROW = {
  NEEDLE: "/knit-production/needle-loan-receive-from-party",
  OIL: "/knit-production/oil-loan-receive-from-party",
};

function printLoanReceipt(c, d) {
  printDocument({
    title: c(`p.loan_receipt_${d.book}`),
    docNo: d.mrr_no,
    orientation: "landscape",
    meta: [
      [c("f.mrr_date"), dayFmt(d.mrr_date)],
      [c("f.supplier_name"), d.party],
      [c("p.address"), d.party_address],
      [c("f.company"), d.company],
      [c("f.receiving_store"), d.store],
      [c("f.pay_method"), c(`pay.${d.pay_method}`)],
      [c("f.source"), d.source],
      [c("f.challan_hash"), d.challan_no],
      [c("f.challan_date"), d.challan_date ? dayFmt(d.challan_date) : null],
      [c("f.lc_no"), d.lc_no],
      [c("f.status"), { v: c(`receipt_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 4,
    sections: [
      linesSection(
        c,
        c("sec.item_list"),
        d.lines,
        [
          [c("col.item"), (l) => ({ v: l.item, bold: true })],
          [c("col.uom"), (l) => l.uom],
          [c("col.receive_qty"), (l) => q3(l.receive_qty), true],
          [c("col.returned_qty"), (l) => q3(l.returned_qty), true],
          [c("col.rate"), (l) => moneyFmt.format(l.rate), true],
          [c("col.remarks"), (l) => l.remarks],
        ],
        [
          "",
          c("f.total"),
          q3(sum(d.lines, (l) => l.receive_qty)),
          q3(sum(d.lines, (l) => l.returned_qty)),
          "",
          "",
        ],
      ),
    ],
    notes: [d.remarks],
    signatures: [
      c("p.received_by"),
      c("p.checked"),
      c("p.store_in_charge"),
      c("p.accounts"),
    ],
  });
}

function LoanReceiptList({ book, options, initialRows, canManage, act }) {
  const c = useNd();
  const path = `loan-receipts/${bookSlug(book)}`;
  const flt = useShowFilters({
    no: "",
    from: "",
    to: "",
    store: "",
    pay: "",
    party: "",
    status: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    path,
    "mrr_no",
    setRows,
    setError,
  );
  const step = makeStep(c, canManage, move, pending, "mrr_no", act);
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.no || has(r.mrr_no, o.no)) &&
      inRange(o, r.mrr_date) &&
      (!o.store || r.store_id === o.store) &&
      (!o.pay || r.pay_method === o.pay) &&
      (!o.party || r.party_id === o.party) &&
      (!o.status || r.status === o.status),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`${path}/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printLoanReceipt(c, res.data);
    });
  }
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <Text
            c={c}
            label="f.mrr_no"
            value={flt.f.no}
            onChange={(v) => flt.set("no", v)}
            placeholder={book === "OIL" ? "OLB-…" : "NLB-…"}
          />
          <DateRange c={c} flt={flt} />
          <Select
            c={c}
            label="f.receiving_store"
            value={flt.f.store}
            onChange={(v) => flt.set("store", v)}
            options={options.stores}
            ph="ph.store"
          />
          <Select
            c={c}
            label="f.pay_method"
            value={flt.f.pay}
            onChange={(v) => flt.set("pay", v)}
            options={listOpts(c, options.pay_methods, "pay")}
          />
          <Select
            c={c}
            label="f.supplier"
            value={flt.f.party}
            onChange={(v) => flt.set("party", v)}
            options={options.parties}
            ph="ph.supplier"
          />
          <Select
            c={c}
            label="f.status"
            value={flt.f.status}
            onChange={(v) => flt.set("status", v)}
            options={listOpts(c, options.receipt_statuses, "receipt_status")}
          />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.lrc_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(c, "mrr_no", "mrr_date", BORROW[book], "receipt_status"),
          { key: "party", label: c("col.supplier") },
          { key: "store", label: c("col.receiving_store") },
          {
            key: "challan_no",
            label: c("col.challan_no"),
            text: (r) => r.challan_no ?? "—",
          },
          {
            key: "pay_method",
            label: c("col.pay_method"),
            text: (r) => c(`pay.${r.pay_method}`),
          },
          qtyCol(c, "receive_qty"),
          qtyCol(c, "returned"),
          qtyCol(c, "outstanding"),
        ]}
        exportName={`${bookSlug(book)}-loan-receipts`}
        addHref={canManage ? `${BORROW[book]}?tab=entry` : null}
        empty={
          <NoLines
            title={c("empty.loan_receipts")}
            hint={c(`empty.lrc_hint_${book}`)}
          />
        }
        pending={pending}
        actions={(r) => (
          <>
            {step(r, ["DRAFT"], "submit", Send)}
            {step(r, ["WAITING"], "verify", ShieldCheck)}
            {step(r, ["VERIFIED"], "accounts", Send)}
            {step(r, ["WAIT_ACCOUNTS"], "approve", BadgeCheck)}
            {step(r, ["DRAFT", "WAITING"], "cancel", Ban, true)}
            <PrintButton
              c={c}
              no={r.mrr_no}
              onClick={() => print(r.mrr_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

const LRC_HEAD = [
  "mrr_date",
  "req_type",
  "party_id",
  "store_id",
  "pay_method",
  "source",
  "challan_no",
  "challan_date",
  "lc_no",
  "remarks",
];
const LRC_LINE = [
  "category_id",
  "item_id",
  "uom",
  "receive_qty",
  "returned_qty",
  "rate",
  "remarks",
];

function LoanReceiptEntry({ book, options, initial, canManage, act }) {
  const c = useNd();
  const [today] = useState(todayIso);
  const d = useDoc({
    c,
    path: `loan-receipts/${bookSlug(book)}`,
    idKey: "mrr_no",
    initial,
    asHead: (x) => ({
      ...Object.fromEntries(LRC_HEAD.map((k) => [k, str(x?.[k])])),
      mrr_date: str(x?.mrr_date) || today,
      pay_method: str(x?.pay_method) || "ADJUSTMENT",
    }),
    asLine: (l) =>
      withCategory(options)(
        Object.fromEntries(LRC_LINE.map((k) => [k, str(l[k])])),
      ),
    canManage,
    act,
  });
  const { head, setH, open, lines, setL } = d;
  function save() {
    d.save({
      ...clean(Object.fromEntries(LRC_HEAD.map((k) => [k, head[k]]))),
      lines: lines.map((l) => ({
        item_id: l.item_id || null,
        uom: l.uom || null,
        receive_qty: orNull(l.receive_qty),
        rate: orNull(l.rate),
        remarks: l.remarks || null,
      })),
    });
  }
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={d.doc ? d.doc.mrr_no : c(`tabs.lrc_entry_${book}`)}
          hint={c(`hint.lrc_form_${book}`)}
          doc={d.doc}
          status={d.doc?.status}
          group="receipt_status"
        />
        <Locked d={d} idKey="mrr_no" group="receipt_status" />
        <Errors errors={d.errors} />
        <Existing
          d={d}
          idKey="mrr_no"
          docs={options.docs}
          label="f.existing_loan_receipt"
          ph="ph.new_loan_receipt"
        />
        <h3 className="mt-5 text-sm font-semibold">{c("sec.receive_info")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <NoField c={c} label="f.mrr_no" value={d.doc?.mrr_no} />
          <Text
            c={c}
            label="f.mrr_date"
            type="date"
            value={head.mrr_date}
            onChange={(v) => setH({ mrr_date: v })}
            disabled={!open}
            required
          />
          <Select
            c={c}
            label="f.req_type"
            value={head.req_type}
            onChange={(v) => setH({ req_type: v })}
            options={listOpts(c, options.req_types, "req_type")}
            disabled={!open}
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.from")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Select
            c={c}
            label="f.supplier_name"
            value={head.party_id}
            onChange={(v) => setH({ party_id: v })}
            options={options.parties}
            ph="ph.supplier"
            disabled={!open}
            required
          />
          <CompanyField c={c} options={options} />
          <Select
            c={c}
            label="f.receiving_store"
            value={head.store_id}
            onChange={(v) => setH({ store_id: v })}
            options={options.stores}
            ph="ph.store"
            disabled={!open}
            required
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.against")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-4">
          <Select
            c={c}
            label="f.pay_method"
            value={head.pay_method}
            onChange={(v) =>
              setH({ pay_method: v, lc_no: v === "LC" ? head.lc_no : "" })
            }
            options={listOpts(c, options.pay_methods, "pay")}
            ph="ph.select_item"
            disabled={!open}
            required
            hint={c("hint.lrc_pay")}
          />
          <Text
            c={c}
            label="f.source"
            value={head.source}
            onChange={(v) => setH({ source: v })}
            disabled={!open}
            max={80}
            required
          />
          <Text
            c={c}
            label="f.challan_hash"
            value={head.challan_no}
            onChange={(v) => setH({ challan_no: v })}
            disabled={!open}
            placeholder={c("ph.party_challan")}
          />
          <Text
            c={c}
            label="f.challan_date"
            type="date"
            value={head.challan_date}
            onChange={(v) => setH({ challan_date: v })}
            disabled={!open}
          />
          <Text
            c={c}
            label="f.lc_no"
            value={head.lc_no}
            onChange={(v) => setH({ lc_no: v })}
            disabled={!open || head.pay_method !== "LC"}
            hint={c("hint.lc_no")}
          />
        </div>
        <Remarks
          c={c}
          value={head.remarks}
          onChange={(v) => setH({ remarks: v })}
          disabled={!open}
        />
      </section>
      <GridCard
        c={c}
        title={c("sec.item_list")}
        hint={c(`hint.lrc_lines_${book}`)}
        button={c("btn.add_item")}
        onAdd={() => d.add()}
        open={open}
        empty={c(`empty.lrc_lines_${book}`)}
        count={lines.length}
      >
        <table className="w-full min-w-[1300px] text-sm">
          <GridHead
            c={c}
            cols={[
              ["category"],
              ["item"],
              ["uom"],
              ["receive_qty", true],
              ["returned_qty", true],
              ["rate", true],
              ["remarks"],
              [""],
            ]}
          />
          <tbody>
            {lines.map((l, i) => {
              const n = i + 1;
              return (
                <tr key={l.key} className="border-t border-border">
                  <ItemCells d={d} l={l} n={n} options={options} />
                  <LineNum
                    value={l.receive_qty}
                    onChange={(v) => setL(l.key, { receive_qty: v })}
                    label={`${c("col.receive_qty")} ${n}`}
                    disabled={!open}
                  />
                  <Cell className="text-right tabular-nums">
                    {q3(num(l.returned_qty))}
                  </Cell>
                  <LineNum
                    value={l.rate}
                    onChange={(v) => setL(l.key, { rate: v })}
                    label={`${c("col.rate")} ${n}`}
                    disabled={!open}
                  />
                  <LineText
                    value={l.remarks}
                    onChange={(v) => setL(l.key, { remarks: v })}
                    label={`${c("col.remarks")} ${n}`}
                    disabled={!open}
                    max={300}
                  />
                  <RemoveCell
                    c={c}
                    n={n}
                    open={open}
                    onClick={() => d.remove(l.key)}
                  />
                </tr>
              );
            })}
          </tbody>
          <tfoot className="border-t border-border bg-surface-muted font-semibold">
            <tr>
              <td className="px-2 py-2" colSpan={3}>
                {c("total_lines", { n: lines.length })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.receive_qty))) })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.returned_qty))) })}
              </td>
              <td colSpan={3} />
            </tr>
          </tfoot>
        </table>
        <div className="px-3 pb-3">
          <Status c={c} foot={c("hint.lrc_status")} />
        </div>
      </GridCard>
      <FormFoot
        c={c}
        hint={c("hint.lrc_foot")}
        back={BORROW[book]}
        onClear={() => d.show(null)}
        onSave={save}
        saveLabel={c("btn.save_loan_receipt")}
        pending={d.pending}
        open={open}
      />
    </div>
  );
}

export {
  BASE,
  useNd,
  q3,
  listOpts,
  itemOf,
  itemsIn,
  machinesOf,
  linesSection,
  ItemCells,
  withCategory,
  Select,
  Text,
  CompanyField,
  Remarks,
  NoField,
  Status,
  makeStep,
  PrintButton,
  LoanOpeningList,
  LoanOpeningForm,
  OpeningList,
  OpeningSheet,
  AssignList,
  AssignSheet,
  ClosingDesk,
  ReceiptList,
  ReceiptEntry,
  ConsumptionBoard,
  ReturnList,
  ReturnEntry,
  BrokenList,
  BrokenEntry,
  ReqList,
  ReqEntry,
  IssueList,
  IssueEntry,
  LoanList,
  LoanEntry,
  LoanReturnList,
  LoanReturnEntry,
  LoanReceiptList,
  LoanReceiptEntry,
};
