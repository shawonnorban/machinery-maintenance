"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  Ban,
  BadgeCheck,
  Download,
  Eye,
  Lock,
  PackageCheck,
  Pencil,
  Play,
  Plus,
  Printer,
  RotateCcw,
  Save,
  Search,
  Send,
  Trash2,
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
import { Modal } from "@/components/ui/modal";
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
  th,
  Field,
  StatusChip,
  NoLines,
  orNull,
} from "./yarn-common";
import { ScTable } from "./prod-sc";
import { TONE, pick, named } from "./prod-yd";
import {
  useShowFilters,
  useMoves,
  ListShell,
  MoveButton,
  FormHead,
  FormFoot,
  RemoveCell,
} from "./ystore-yd";

/*
 * Knit Production → Collar/Cuff (AnnotechERP 15/06): Collar & Cuff Output
 * Entry (the flat knit requirements, their production desk and the form
 * that raises one), Out-House Collar & Cuff Programme, Solid / Yarn-Dyed
 * Collar & Cuff Indent (with the store's Issue Against Requisition desk)
 * and Bulk / Sample Collar & Cuff Store Receipt. A document travels by its
 * number (?ref=CCR-…, CCP-…, CYS-…, CCB-…); nothing reloads.
 */

const BASE = {
  output: "/knit-production/collar-cuff-production",
  program: "/knit-production/collar-cuff-program-sc-out-house",
  SOLID: "/knit-production/yarn-req-collar-cuff-solid",
  YD: "/knit-production/yarn-req-collar-cuff-yd",
  BULK: "/knit-production/collar-cuff-trans-recv-store-bulk",
  SAMPLE: "/knit-production/collar-cuff-trans-recv-store-sample",
};
const SLUG = { SOLID: "solid", YD: "yd", BULK: "bulk", SAMPLE: "sample" };
const enc = encodeURIComponent;
const numIn = { type: "number", min: "0", step: "any" };
let seq = 0;
const lineKey = () => `c${++seq}`;
const has = (v, q) => (v ?? "").toLowerCase().includes(q.toLowerCase());
const sum = (rows, f) => rows.reduce((a, r) => a + f(r), 0);
const monthFmt = (ym) =>
  ym
    ? new Date(`${ym}-01T00:00:00`).toLocaleDateString("en-GB", {
        month: "short",
        year: "numeric",
      })
    : null;
const clean = (o) =>
  Object.fromEntries(
    Object.entries(o).map(([k, v]) => [k, v === "" ? null : v]),
  );

function useCc() {
  const t = useT("knitting");
  // Requisition types are the yarn store's own list.
  return (k, p) =>
    k.startsWith("req_type.") ? t(`ystore.${k}`, p) : t(`cc.${k}`, p);
}

const itemOpts = (c, options) =>
  options.items.map((i) => ({ value: i, label: c(`item.${i}`) }));
const listOpts = (c, list, group) =>
  list.map((s) => ({ value: s, label: c(`${group}.${s}`) }));

/** The months a shipment can fall in: a year back to eighteen months ahead, and any an order names. */
function shipMonths(options) {
  const now = new Date();
  const out = new Set(options.orders.map((o) => o.ship_month).filter(Boolean));
  for (let i = -12; i <= 18; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() + i, 1);
    out.add(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`);
  }
  return [...out].sort().map((m) => ({ value: m, label: monthFmt(m) }));
}

/** The work styles the orders carry (a buyer's, when one is picked). */
const styleOpts = (options, buyer) =>
  [
    ...new Set(
      options.orders
        .filter((o) => !buyer || o.buyer_id === buyer)
        .map((o) => o.style)
        .filter(Boolean),
    ),
  ].map((s) => ({ value: s, label: s }));

/* ---------------- prints ---------------- */

function printProgram(c, d) {
  printDocument({
    title: c("p.program"),
    docNo: d.program_no,
    orientation: "landscape",
    meta: [
      [c("f.program_date"), dayFmt(d.program_date)],
      [c("f.delivery_date"), d.delivery_date ? dayFmt(d.delivery_date) : null],
      [c("f.supplier"), d.party],
      [c("p.address"), d.party_address],
      [c("f.company"), d.company],
      [c("f.buyer"), d.buyer],
      [c("f.work_style_no"), d.work_style_no],
      [c("f.order"), d.order],
      [c("f.cpi"), d.cpi],
      [c("f.status"), { v: c(`program_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 4,
    sections: [
      {
        title: c("p.parts_placed"),
        columns: [
          { label: "#", align: "center", width: "4%" },
          { label: c("col.item") },
          { label: c("col.description") },
          { label: c("col.colour") },
          { label: c("col.size") },
          { label: c("col.gauge") },
          { label: c("col.qty_pcs"), align: "right" },
          { label: c("col.received"), align: "right" },
          { label: c("col.uom") },
          { label: c("col.rate"), align: "right" },
          { label: c("col.amount"), align: "right" },
        ],
        rows: d.lines.map((l, i) => [
          i + 1,
          { v: c(`item.${l.item}`), bold: true },
          l.description ?? "—",
          l.colour ?? "—",
          l.size_desc ?? "—",
          l.gauge ?? "—",
          qtyFmt.format(l.qty),
          qtyFmt.format(l.received),
          l.uom,
          moneyFmt.format(l.rate),
          { v: moneyFmt.format(l.amount), bold: true },
        ]),
        foot: [
          [
            "",
            "",
            "",
            "",
            "",
            c("f.total"),
            qtyFmt.format(sum(d.lines, (l) => l.qty)),
            qtyFmt.format(sum(d.lines, (l) => l.received)),
            "",
            "",
            moneyFmt.format(sum(d.lines, (l) => l.amount)),
          ],
        ],
        empty: c("empty.lines"),
      },
    ],
    notes: [d.remarks],
    signatures: [
      c("p.prepared"),
      c("p.checked"),
      c("p.approved"),
      c("p.party_ack"),
    ],
  });
}

function printChallan(c, d) {
  printDocument({
    title: c(`p.challan_${d.kind}`, {
      type: c(`challan_type.${d.challan_type}`),
    }),
    docNo: d.challan_no,
    orientation: "landscape",
    meta: [
      [c("f.challan_date"), dayFmt(d.challan_date)],
      [c("f.rcv_date"), d.rcv_date ? dayFmt(d.rcv_date) : null],
      [c("f.company"), d.company],
      [c("f.from_floor"), d.from],
      [c("p.party"), d.party],
      [c("p.to_store"), d.store],
      [c("p.program_no"), d.program_no],
      [c("f.gate_pass"), d.gate_pass_no],
      [c("f.vehicle"), d.vehicle_no],
      [c("f.status"), { v: c(`challan_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 4,
    sections: [
      {
        title: c("p.parts_on_challan"),
        columns: [
          { label: "#", align: "center", width: "4%" },
          { label: c("col.work_style") },
          { label: c("col.item") },
          { label: c("col.description") },
          { label: c("col.colour") },
          { label: c("col.size") },
          { label: c("col.qty_pcs"), align: "right" },
          { label: c("col.received"), align: "right" },
          { label: c("col.uom") },
          { label: c("col.remarks") },
        ],
        rows: d.lines.map((l, i) => [
          i + 1,
          l.work_style_no ?? l.order ?? "—",
          { v: c(`item.${l.item}`), bold: true },
          l.description ?? "—",
          l.colour ?? "—",
          l.size_desc ?? "—",
          qtyFmt.format(l.qty),
          qtyFmt.format(l.received),
          l.uom,
          l.remarks ?? "—",
        ]),
        foot: [
          [
            "",
            "",
            "",
            "",
            "",
            c("f.total"),
            qtyFmt.format(sum(d.lines, (l) => l.qty)),
            qtyFmt.format(sum(d.lines, (l) => l.received)),
            "",
            "",
          ],
        ],
        empty: c("empty.lines"),
      },
    ],
    notes: [d.remarks],
    signatures: [
      c("p.issued_by"),
      c("p.carried_by"),
      c("p.security"),
      c("p.received_by"),
    ],
  });
}

function printYarnReq(c, d) {
  printDocument({
    title: c(`p.yarn_${d.kind}`),
    docNo: d.req_no,
    orientation: "landscape",
    meta: [
      [c("f.req_date"), dayFmt(d.req_date)],
      [c("f.knit_plan"), d.knit_plan_no],
      [c("f.program"), d.program_no],
      [c("f.company"), d.company],
      [c("f.buyer"), d.buyer],
      [c("f.style_order"), d.order],
      [c("f.store"), d.store],
      [c("f.req_type"), d.req_type ? c(`req_type.${d.req_type}`) : null],
      [c("f.status"), { v: c(`yarn_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 3,
    sections: [
      {
        title: c("sec.yarn_list"),
        columns: [
          { label: "#", align: "center", width: "4%" },
          { label: c("col.style_order") },
          { label: c("col.fab_type") },
          { label: c("col.colour") },
          { label: c("col.yarn") },
          { label: c("col.count") },
          { label: c("col.brand") },
          { label: c("col.lot") },
          { label: c("col.req_qty"), align: "right" },
          { label: c("col.issued"), align: "right" },
          { label: c("col.remarks") },
        ],
        rows: d.lines.map((l, i) => [
          i + 1,
          l.order ?? "—",
          l.fabric_type ?? "—",
          l.colour ?? "—",
          { v: l.yarn, bold: true },
          l.count ?? "—",
          l.brand ?? "—",
          l.lot_no ?? "—",
          `${qtyFmt.format(l.req_qty)} ${l.uom}`,
          `${qtyFmt.format(l.issued)} ${l.uom}`,
          l.remarks ?? "—",
        ]),
        empty: c("empty.lines"),
      },
    ],
    notes: [d.remarks],
    signatures: [
      c("p.requested_by"),
      c("p.checked"),
      c("p.approved"),
      c("p.store_keeper"),
    ],
  });
}

/* ---------------- shared bits ---------------- */

function docCol(c, idKey, dateKey, base, group, tab = "entry") {
  return [
    {
      key: idKey,
      label: c(`col.${idKey}`),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${base}?tab=${tab}&ref=${enc(r[idKey])}`}
            className="font-medium text-brand hover:underline"
          >
            {r[idKey]}
          </Link>
          <StatusChip
            status={TONE[r.status]}
            label={c(`${group}.${r.status}`)}
          />
        </span>
      ),
      text: (r) => r[idKey],
    },
    {
      key: dateKey,
      label: c(`col.${dateKey}`),
      nowrap: true,
      text: (r) => dayFmt(r[dateKey]),
    },
  ];
}

const qtyCol = (c, key, label = `col.${key}`) => ({
  key,
  label: c(label),
  num: true,
  text: (r) => qtyFmt.format(r[key]),
});

function DateRange({ c, flt }) {
  return (
    <>
      <Field label={c("f.from")}>
        <Input
          type="date"
          value={flt.f.from}
          onChange={(e) => flt.set("from", e.target.value)}
          aria-label={c("f.from")}
        />
      </Field>
      <Field label={c("f.to")}>
        <Input
          type="date"
          value={flt.f.to}
          onChange={(e) => flt.set("to", e.target.value)}
          aria-label={c("f.to")}
        />
      </Field>
    </>
  );
}

function inRange(o, date) {
  return (!o.from || date >= o.from) && (!o.to || date <= o.to);
}

/** A form's state: what is opened (by number), its head and lines, and save. */
function useDoc({
  path,
  idKey,
  initial,
  asHead,
  asLine,
  canManage,
  act,
  c: given,
}) {
  const own = useCc();
  const c = given ?? own;
  const toastManager = useToastManager();
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() =>
    (initial?.lines ?? []).map((l) => ({ key: lineKey(), ...asLine(l) })),
  );
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && (!doc || doc.status === "DRAFT");
  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map((l) => ({ key: lineKey(), ...asLine(l) })));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${enc(d[idKey])}` : "?tab=entry",
    );
  }
  function load(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`${path}/${enc(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function save(body, after) {
    startTransition(async () => {
      const res = await act(
        doc ? `${path}/${enc(doc[idKey])}` : path,
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      after?.(res.data);
      toastManager.add({
        title: c("done.saved", { no: res.data[idKey] }),
        type: "success",
      });
    });
  }
  return {
    c,
    doc,
    head,
    setH: (patch) => setHead((h) => ({ ...h, ...patch })),
    lines,
    setLines,
    setL: (k, patch) =>
      setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l))),
    add: (l = {}) =>
      setLines((all) => [...all, { key: lineKey(), ...asLine(l) }]),
    remove: (k) => setLines((all) => all.filter((x) => x.key !== k)),
    errors,
    pending,
    open,
    show,
    load,
    save,
    canManage,
  };
}

/** A radio group's field: a plain box, since a label around it would hand every click to its first radio. */
function RadioField({ label, required, className, children }) {
  return (
    <div className={cn("flex flex-col gap-1.5", className)}>
      <span className="text-[13px] font-semibold text-foreground">
        {label}
        {required ? <span className="text-danger">*</span> : null}
      </span>
      <div
        role="radiogroup"
        aria-label={label}
        className="flex h-10 items-center gap-5 rounded-lg border border-border px-3 text-sm"
      >
        {children}
      </div>
    </div>
  );
}

function Errors({ errors }) {
  return errors ? (
    <Alert variant="danger" className="mt-3">
      {errors.join(" · ")}
    </Alert>
  ) : null;
}

function Locked({ d, idKey, group }) {
  const { c, doc, open, canManage } = d;
  return doc && !open && canManage ? (
    <Alert variant="info" className="mt-3">
      {c("hint.locked", {
        no: doc[idKey],
        status: c(`${group}.${doc.status}`),
      })}
    </Alert>
  ) : null;
}

function Existing({ d, idKey, docs, label, ph }) {
  const { c } = d;
  return (
    <Field label={c(label)} hint={c("hint.existing")} className="mt-4">
      <PSelect
        value={d.doc?.[idKey] ?? ""}
        onValueChange={(v) => d.load(v ?? "")}
        options={pick(docs, c(ph))}
        disabled={d.pending}
        aria-label={c(label)}
      />
    </Field>
  );
}

function Cell({ children, className }) {
  return <td className={cn("px-2 py-1.5", className)}>{children}</td>;
}

function LineSelect({
  value,
  onChange,
  options,
  ph = "—",
  label,
  disabled,
  width = "min-w-[150px]",
}) {
  return (
    <Cell className={width}>
      <PSelect
        value={value}
        onValueChange={(v) => onChange(v ?? "")}
        options={pick(options, ph)}
        disabled={disabled}
        aria-label={label}
      />
    </Cell>
  );
}

function LineText({
  value,
  onChange,
  label,
  disabled,
  max = 200,
  width = "w-32",
}) {
  return (
    <Cell>
      <Input
        value={value}
        onChange={(e) => onChange(e.target.value)}
        maxLength={max}
        disabled={disabled}
        className={width}
        aria-label={label}
      />
    </Cell>
  );
}

function LineNum({ value, onChange, label, disabled }) {
  return (
    <Cell>
      <Input
        {...numIn}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        disabled={disabled}
        className="ml-auto w-24 text-right"
        aria-label={label}
      />
    </Cell>
  );
}

function GridHead({ c, cols }) {
  return (
    <thead className="bg-brand text-brand-foreground">
      <tr>
        {cols.map(([k, right]) => (
          <th key={k} className={cn(th, right && "text-right")}>
            {k ? c(`col.${k}`) : ""}
          </th>
        ))}
      </tr>
    </thead>
  );
}

function GridCard({
  c,
  title,
  hint,
  button,
  onAdd,
  open,
  children,
  empty,
  count,
}) {
  return (
    <section className={cn(card, "p-5")}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-base font-semibold">{title}</h3>
          <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
            {hint}
          </p>
        </div>
        {open && onAdd ? (
          <Button variant="outline" size="sm" onClick={onAdd}>
            <Plus /> {button}
          </Button>
        ) : null}
      </div>
      <div className="mt-4 overflow-x-auto rounded-lg border border-border">
        {count ? children : <NoLines title={c("empty.lines")} hint={empty} />}
      </div>
    </section>
  );
}

/** A left-hand picker: a filter, a search box and a few rows at a time. */
function PickPanel({ c, title, filter, rows, columns, action, placeholder }) {
  const PER = 6;
  const [q, setQ] = useState("");
  const [page, setPage] = useState(0);
  const shown = rows.filter(
    (r) => !q || has(columns.map(([, cell]) => cell(r)).join(" "), q),
  );
  const pages = Math.max(1, Math.ceil(shown.length / PER));
  const at = Math.min(page, pages - 1);
  return (
    <section className={cn(card, "min-w-0 self-start p-5")}>
      <h2 className="text-base font-semibold text-foreground">{title}</h2>
      {filter}
      <div className="relative mt-3">
        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
        <Input
          value={q}
          onChange={(e) => (setQ(e.target.value), setPage(0))}
          placeholder={placeholder}
          className="pl-9"
          aria-label={`${c("btn.search")} ${title}`}
        />
      </div>
      <div className="mt-3 overflow-x-auto rounded-lg border border-border">
        <table className="w-full text-sm">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {columns.map(([label, , right]) => (
                <th key={label} className={cn(th, right && "text-right")}>
                  {label}
                </th>
              ))}
              <th className={th} />
            </tr>
          </thead>
          <tbody>
            {shown.length === 0 ? (
              <tr>
                <td
                  colSpan={columns.length + 1}
                  className="px-3 py-8 text-center text-foreground-muted"
                >
                  {c("empty.side")}
                </td>
              </tr>
            ) : (
              shown.slice(at * PER, at * PER + PER).map((r) => (
                <tr key={r.key} className="border-t border-border">
                  {columns.map(([label, cell, right]) => (
                    <td
                      key={label}
                      className={cn(
                        "px-2 py-2",
                        right && "text-right whitespace-nowrap tabular-nums",
                      )}
                    >
                      {cell(r) ?? "—"}
                    </td>
                  ))}
                  <td className="px-2 py-2 text-right">{action(r)}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      <div className="mt-3 flex items-center justify-between text-[13px] text-foreground-muted">
        {c("showing", {
          from: shown.length ? at * PER + 1 : 0,
          to: Math.min(shown.length, at * PER + PER),
          count: shown.length,
        })}
        <div className="flex items-center gap-1">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage(Math.max(0, at - 1))}
            disabled={at === 0}
          >
            ‹
          </Button>
          {at + 1} / {pages}
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage(Math.min(pages - 1, at + 1))}
            disabled={at >= pages - 1}
          >
            ›
          </Button>
        </div>
      </div>
    </section>
  );
}

/* ---------------- flat knit requirements ---------------- */

const reqTitle = (c, r) => `${c(`item.${r.item}`)} · ${r.description}`;
const reqAsked = (r) => num(r.req_in_house) + num(r.req_sc);

function ReqList({ options, initialRows, canManage, act }) {
  const c = useCc();
  const flt = useShowFilters({
    type: "BULK",
    buyer: "",
    ship: "",
    style: "",
    order: "",
    item: "",
    closed: false,
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending] = useMoves(
    c,
    "cc/requirements",
    "req_no",
    setRows,
    setError,
  );
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.type || r.req_type === o.type) &&
      (!o.buyer || r.buyer_id === o.buyer) &&
      (!o.ship || r.ship_month === o.ship) &&
      (!o.style || has(r.work_style_no, o.style) || has(r.order, o.style)) &&
      (!o.order || r.order_id === o.order) &&
      (!o.item || r.item === o.item) &&
      (o.closed || r.status === "OPEN"),
  );
  const columns = [
    {
      key: "category_label",
      label: c("col.prod_catg"),
      text: (r) => r.category_label ?? "—",
    },
    { key: "buyer", label: c("col.buyer") },
    {
      key: "work_style_no",
      label: c("col.work_style"),
      render: (r) => (
        <span className="flex flex-col">
          <span className="font-medium">
            {r.work_style_no ?? r.order ?? c("no_order")}
          </span>
          <span className="text-xs text-foreground-muted">{r.req_no}</span>
        </span>
      ),
      text: (r) => `${r.work_style_no ?? r.order ?? ""} ${r.req_no}`,
    },
    {
      key: "item",
      label: c("col.item"),
      text: (r) => `${c(`item.${r.item}`)} — ${r.description}`,
    },
    qtyCol(c, "req_in_house"),
    qtyCol(c, "prod_in"),
    qtyCol(c, "req_sc"),
    qtyCol(c, "prod_sc"),
    {
      key: "ship_month",
      label: c("col.shipment"),
      text: (r) => monthFmt(r.ship_month) ?? "—",
    },
    { key: "colour", label: c("col.colour"), text: (r) => r.colour ?? "—" },
    {
      key: "status",
      label: c("col.status"),
      render: (r) => (
        <span className="flex flex-wrap gap-1">
          <StatusChip status="PLACED" label={c(`type.${r.req_type}`)} />
          {r.status === "CLOSED" ? (
            <StatusChip status="CLOSED" label={c("req_status.CLOSED")} />
          ) : null}
        </span>
      ),
      text: (r) => `${c(`type.${r.req_type}`)} ${c(`req_status.${r.status}`)}`,
    },
  ];
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <Field label={c("f.type")}>
            <PSelect
              value={flt.f.type}
              onValueChange={(v) => flt.set("type", v)}
              options={pick(listOpts(c, options.types, "type"), c("ph.all"))}
              aria-label={c("f.type")}
            />
          </Field>
          <Field label={c("f.buyer")}>
            <PSelect
              value={flt.f.buyer}
              onValueChange={(v) => flt.set("buyer", v)}
              options={pick(options.buyers, c("ph.select"))}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.shipment")}>
            <PSelect
              value={flt.f.ship}
              onValueChange={(v) => flt.set("ship", v)}
              options={pick(shipMonths(options), c("ph.month"))}
              aria-label={c("f.shipment")}
            />
          </Field>
          <Field label={c("f.work_style")}>
            <PSelect
              value={flt.f.style}
              onValueChange={(v) => flt.set("style", v)}
              options={pick(styleOpts(options, flt.f.buyer), c("ph.style"))}
              aria-label={c("f.work_style")}
            />
          </Field>
          <Field label={c("f.order")}>
            <PSelect
              value={flt.f.order}
              onValueChange={(v) => flt.set("order", v)}
              options={pick(
                options.orders.filter(
                  (x) => !flt.f.buyer || x.buyer_id === flt.f.buyer,
                ),
                c("ph.order"),
              )}
              aria-label={c("f.order")}
            />
          </Field>
          <Field label={c("f.item")}>
            <PSelect
              value={flt.f.item}
              onValueChange={(v) => flt.set("item", v)}
              options={pick(itemOpts(c, options), c("ph.all_parts"))}
              aria-label={c("f.item")}
            />
          </Field>
          <label className="flex items-center gap-2 self-end pb-2 text-sm font-medium">
            <input
              type="checkbox"
              checked={Boolean(flt.f.closed)}
              onChange={(e) => flt.set("closed", e.target.checked)}
              className="size-4 accent-brand"
            />
            {c("f.with_closed")}
          </label>
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.req_list")}
      </p>
      <ScTable
        rows={shown}
        columns={columns}
        exportName="flat-knit-requirements"
        addHref={canManage ? `${BASE.output}?tab=requirement` : null}
        empty={<NoLines title={c("empty.reqs")} hint={c("empty.hint")} />}
        pending={pending}
        actions={(r) =>
          canManage && r.status === "OPEN" ? (
            <>
              <MoveButton
                icon={Lock}
                label={c("btn.close")}
                no={r.req_no}
                onClick={() => move(r.req_no, "close", act)}
                disabled={pending}
                danger
              />
              <Link
                href={`${BASE.output}?tab=requirement&ref=${enc(r.req_no)}`}
                className={buttonVariants({ variant: "outline", size: "sm" })}
                aria-label={`${c("btn.edit")} ${r.req_no}`}
              >
                <Pencil /> {c("btn.edit")}
              </Link>
            </>
          ) : null
        }
      />
    </ListShell>
  );
}

const REQ_HEAD = [
  "req_type",
  "category",
  "buyer_id",
  "order_id",
  "work_style_no",
  "ship_month",
  "item",
  "description",
  "colour_id",
  "size_desc",
  "gauge",
  "req_in_house",
  "req_sc",
  "uom",
  "remarks",
];

function ReqForm({ options, initial, canManage, act }) {
  const c = useCc();
  const toastManager = useToastManager();
  const blank = (d) => ({
    ...Object.fromEntries(REQ_HEAD.map((k) => [k, str(d?.[k])])),
    req_type: str(d?.req_type) || "BULK",
    uom: str(d?.uom) || "PCS",
  });
  const [doc, setDoc] = useState(initial);
  const [h, setHead] = useState(() => blank(initial));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && (!doc || doc.status === "OPEN");
  const setH = (patch) => setHead((x) => ({ ...x, ...patch }));
  function pickOrder(id) {
    const o = options.orders.find((x) => x.value === id);
    setH({
      order_id: id,
      buyer_id: o?.buyer_id ?? h.buyer_id,
      work_style_no: h.work_style_no || str(o?.style),
      ship_month: h.ship_month || str(o?.ship_month),
    });
  }
  function reset() {
    setDoc(null);
    setHead(blank(null));
    setErrors(null);
    window.history.replaceState(null, "", "?tab=requirement");
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...clean(h),
        req_in_house: orNull(h.req_in_house),
        req_sc: orNull(h.req_sc),
      };
      const res = await act(
        doc ? `cc/requirements/${enc(doc.req_no)}` : "cc/requirements",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      toastManager.add({
        title: c("done.req_saved", { no: res.data.req_no }),
        type: "success",
      });
      if (doc) {
        setDoc(res.data);
        setHead(blank(res.data));
        setErrors(null);
      } else reset();
    });
  }
  const text = (k, label, max, placeholder) => (
    <Field label={c(label)}>
      <Input
        value={h[k]}
        onChange={(e) => setH({ [k]: e.target.value })}
        maxLength={max}
        placeholder={placeholder}
        disabled={!open}
        aria-label={c(label)}
      />
    </Field>
  );
  const qty = (k, label) => (
    <Field label={c(label)}>
      <div className="relative">
        <Input
          {...numIn}
          value={h[k]}
          onChange={(e) => setH({ [k]: e.target.value })}
          placeholder="0"
          disabled={!open}
          className="pr-12"
          aria-label={c(label)}
        />
        <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-foreground-muted">
          {c("pcs")}
        </span>
      </div>
    </Field>
  );
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={doc ? doc.req_no : c("tabs.requirement")}
          hint={c("hint.req_form")}
          doc={doc}
          status={doc?.status}
          group="req_status"
        />
        <Errors errors={errors} />
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <RadioField label={c("f.type")} required>
            {options.types.map((t) => (
              <label key={t} className="flex items-center gap-2">
                <input
                  type="radio"
                  name="cc-req-type"
                  value={t}
                  checked={h.req_type === t}
                  onChange={() => setH({ req_type: t })}
                  disabled={!open}
                  className="size-4 accent-brand"
                />
                {c(`type.${t}`)}
              </label>
            ))}
          </RadioField>
          <Field label={c("f.company")} required>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={c("f.company")}
            />
          </Field>
          <Field label={c("f.prod_category")}>
            <PSelect
              value={h.category}
              onValueChange={(v) => setH({ category: v ?? "" })}
              options={pick(options.categories, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.prod_category")}
            />
          </Field>
          <Field label={c("f.buyer")}>
            <PSelect
              value={h.buyer_id}
              onValueChange={(v) => setH({ buyer_id: v ?? "" })}
              options={pick(options.buyers, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.order")} hint={c("hint.req_order")}>
            <PSelect
              value={h.order_id}
              onValueChange={(v) => pickOrder(v ?? "")}
              options={pick(
                options.orders.filter(
                  (x) => !h.buyer_id || x.buyer_id === h.buyer_id,
                ),
                c("ph.order"),
              )}
              disabled={!open}
              aria-label={c("f.order")}
            />
          </Field>
          <Field label={c("f.work_style")}>
            <PSelect
              value={h.work_style_no}
              onValueChange={(v) => setH({ work_style_no: v ?? "" })}
              options={pick(
                [
                  ...styleOpts(options, h.buyer_id),
                  ...(h.work_style_no &&
                  !styleOpts(options, h.buyer_id).some(
                    (s) => s.value === h.work_style_no,
                  )
                    ? [{ value: h.work_style_no, label: h.work_style_no }]
                    : []),
                ],
                c("ph.style"),
              )}
              disabled={!open}
              aria-label={c("f.work_style")}
            />
          </Field>
          {text("work_style_no", "f.work_style_no", 60, c("ph.work_style_no"))}
          <Field label={c("f.shipment")}>
            <PSelect
              value={h.ship_month}
              onValueChange={(v) => setH({ ship_month: v ?? "" })}
              options={pick(shipMonths(options), c("ph.month"))}
              disabled={!open}
              aria-label={c("f.shipment")}
            />
          </Field>
          <Field label={c("f.item")} required>
            <PSelect
              value={h.item}
              onValueChange={(v) => setH({ item: v ?? "" })}
              options={pick(itemOpts(c, options), c("ph.select_item"))}
              disabled={!open}
              aria-label={c("f.item")}
            />
          </Field>
        </div>
        <Field label={c("f.description")} required className="mt-4">
          <Input
            value={h.description}
            onChange={(e) => setH({ description: e.target.value })}
            maxLength={200}
            placeholder={c("ph.description")}
            disabled={!open}
            aria-label={c("f.description")}
          />
        </Field>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Field label={c("f.colour")}>
            <PSelect
              value={h.colour_id}
              onValueChange={(v) => setH({ colour_id: v ?? "" })}
              options={pick(options.colours, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.colour")}
            />
          </Field>
          {text("size_desc", "f.size", 80)}
          {text("gauge", "f.gauge", 40)}
          {qty("req_in_house", "f.req_in_house")}
          {qty("req_sc", "f.req_sc")}
          <Field label={c("f.uom")}>
            <PSelect
              value={h.uom}
              onValueChange={(v) => setH({ uom: v ?? "" })}
              options={pick(options.uoms, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.uom")}
            />
          </Field>
        </div>
        <Field label={c("f.remarks")} className="mt-4">
          <Textarea
            rows={3}
            value={h.remarks}
            onChange={(e) => setH({ remarks: e.target.value })}
            maxLength={2000}
            disabled={!open}
            aria-label={c("f.remarks")}
          />
        </Field>
      </section>
      <section
        className={cn(
          card,
          "flex flex-wrap items-center justify-between gap-3 p-4",
        )}
      >
        <p className="text-[13px] text-foreground-muted">{c("hint.saved")}</p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={reset} disabled={pending}>
            <RotateCcw /> {c("btn.reset")}
          </Button>
          {open ? (
            <Button onClick={save} loading={pending}>
              <Save /> {c("btn.save_req")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

/* ---------------- production desk ---------------- */

const PROD_KEYS = [
  "prod_date",
  "source",
  "floor_id",
  "machine_id",
  "shift_id",
  "operator_id",
  "program_no",
  "qty",
  "reject_qty",
  "remarks",
];
const asProd = (l = {}) => ({
  id: l.id ?? null,
  ...Object.fromEntries(PROD_KEYS.map((k) => [k, str(l[k])])),
});
const csvCell = (v) => `"${String(v ?? "").replaceAll('"', '""')}"`;

function ProdDesk({ options, initialRows, initial, canManage, act }) {
  const c = useCc();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initialRows);
  const [q, setQ] = useState("");
  const [req, setReq] = useState(initial);
  const [saved, setSaved] = useState(() => (initial?.lines ?? []).map(asProd));
  const [lines, setLines] = useState(saved);
  const [dialog, setDialog] = useState(false);
  const [today] = useState(todayIso);
  const blankForm = () => asProd({ prod_date: today, source: "IN_HOUSE" });
  const [form, setForm] = useState(blankForm);
  const [errors, setErrors] = useState(null);
  const [formErrors, setFormErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && req?.status === "OPEN";
  const dirty = JSON.stringify(lines) !== JSON.stringify(saved);
  const programs = options.programs.map((p) => ({
    value: p.value,
    label: p.label,
  }));
  function take(d) {
    const ls = d.lines.map(asProd);
    setReq(d);
    setSaved(ls);
    setLines(ls);
    setErrors(null);
    setRows((all) =>
      all.map((r) =>
        r.req_no === d.req_no ? { ...r, prod_lines: d.lines.length } : r,
      ),
    );
  }
  function select(no) {
    startTransition(async () => {
      const res = await act(`cc/requirements/${enc(no)}/productions`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      window.history.replaceState(null, "", `?tab=production&ref=${enc(no)}`);
    });
  }
  const payload = (l) => ({
    ...clean(l),
    qty: orNull(l.qty),
    reject_qty: orNull(l.reject_qty),
  });
  function add() {
    startTransition(async () => {
      const { id: _id, ...body } = payload(form);
      const res = await act(
        `cc/requirements/${enc(req.req_no)}/productions`,
        "POST",
        body,
      );
      if (res?.status !== "success") return setFormErrors(messagesOf(res));
      take(res.data);
      setDialog(false);
      setFormErrors(null);
      toastManager.add({
        title: c("done.prod_added", {
          qty: qtyFmt.format(num(form.qty)),
          source: c(`source.${form.source}`),
          req: req.description,
        }),
        type: "success",
      });
    });
  }
  function save() {
    startTransition(async () => {
      const res = await act(
        `cc/requirements/${enc(req.req_no)}/productions`,
        "PUT",
        { lines: lines.map(payload) },
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      toastManager.add({ title: c("done.prod_saved"), type: "success" });
    });
  }
  function remove(id) {
    startTransition(async () => {
      const res = await act(
        `cc/requirements/${enc(req.req_no)}/productions/${enc(id)}`,
        "DELETE",
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      toastManager.add({ title: c("done.prod_removed"), type: "success" });
    });
  }
  const setL = (i, patch) =>
    setLines((all) => all.map((l, n) => (n === i ? { ...l, ...patch } : l)));
  function exportCsv() {
    const head = [
      "col.date",
      "col.source",
      "col.floor",
      "col.machine",
      "col.shift",
      "col.operator",
      "col.sc_program",
      "col.qty_pcs",
      "col.reject",
      "col.remarks",
    ].map((k) => c(k));
    const body = saved.map((l) => [
      l.prod_date,
      c(`source.${l.source}`),
      named(options.floors, l.floor_id),
      named(options.machines, l.machine_id),
      named(options.shifts, l.shift_id),
      named(options.employees, l.operator_id),
      l.program_no,
      l.qty,
      l.reject_qty,
      l.remarks,
    ]);
    const csv = [head, ...body].map((r) => r.map(csvCell).join(",")).join("\n");
    const a = document.createElement("a");
    a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
    a.download = `collar-cuff-production-${req.req_no}.csv`;
    a.click();
  }
  const shown = rows.filter(
    (r) =>
      r.status === "OPEN" &&
      (!q ||
        has(
          `${reqTitle(c, r)} ${r.order ?? ""} ${r.work_style_no ?? ""} ${r.req_no}`,
          q,
        )),
  );
  const set = (patch) => setForm((x) => ({ ...x, ...patch }));
  return (
    <div className="grid gap-4 lg:grid-cols-[320px_1fr]">
      <section className={cn(card, "self-start p-5")}>
        <div className="flex items-center justify-between">
          <h2 className="text-base font-semibold">{c("sec.requirements")}</h2>
          <StatusChip status="PLACED" label={String(shown.length)} />
        </div>
        <div className="relative mt-3">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder={c("ph.search")}
            className="pl-9"
            aria-label={c("ph.search_reqs")}
          />
        </div>
        <div className="mt-3 flex max-h-[560px] flex-col gap-1 overflow-y-auto">
          {shown.length === 0 ? (
            <p className="px-2 py-6 text-center text-[13px] text-foreground-muted">
              {c("empty.req_search")}
            </p>
          ) : (
            shown.map((r) => (
              <button
                key={r.req_no}
                type="button"
                onClick={() => select(r.req_no)}
                className={cn(
                  "flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-left hover:bg-surface-muted",
                  req?.req_no === r.req_no && "bg-brand-subtle",
                )}
              >
                <span className="min-w-0">
                  <span className="block truncate text-sm font-medium">
                    {reqTitle(c, r)}
                  </span>
                  <span className="block truncate text-xs text-foreground-muted">
                    {c("req_sub", {
                      order: r.order ?? r.work_style_no ?? c("no_order"),
                      qty: qtyFmt.format(reqAsked(r)),
                    })}
                  </span>
                </span>
                <span className="text-xs font-semibold text-foreground-muted">
                  {r.prod_lines}
                </span>
              </button>
            ))
          )}
        </div>
      </section>
      <section className={cn(card, "min-w-0")}>
        <div className="flex flex-wrap items-start justify-between gap-3 p-5">
          <div>
            <h2 className="text-base font-semibold">
              {req
                ? c("prod_title", { req: reqTitle(c, req) })
                : c("sec.production")}
            </h2>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {c("hint.prod_desk")}
            </p>
          </div>
          {req ? (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={exportCsv}>
                <Download /> {c("btn.export")}
              </Button>
              {open ? (
                <Button
                  size="sm"
                  onClick={() => (
                    setForm(blankForm()),
                    setFormErrors(null),
                    setDialog(true)
                  )}
                >
                  <Plus /> {c("btn.add_production")}
                </Button>
              ) : null}
            </div>
          ) : null}
        </div>
        {errors ? (
          <div className="px-5 pb-3">
            <Alert variant="danger">{errors.join(" · ")}</Alert>
          </div>
        ) : null}
        {!req ? (
          <NoLines
            title={c("empty.select_req")}
            hint={c("empty.select_req_hint")}
          />
        ) : (
          <>
            <div className="grid gap-3 px-5 pb-4 sm:grid-cols-3 lg:grid-cols-6">
              {[
                ["req_in_house", req.req_in_house],
                ["prod_in", req.prod_in],
                ["req_sc", req.req_sc],
                ["prod_sc", req.prod_sc],
                ["reject", req.reject],
                ["open", req.open],
              ].map(([k, v]) => (
                <div
                  key={k}
                  className="rounded-lg border border-border px-3 py-2"
                >
                  <div className="text-xs text-foreground-muted">
                    {c(`col.${k}`)}
                  </div>
                  <div className="text-base font-semibold tabular-nums">
                    {qtyFmt.format(v)}
                  </div>
                </div>
              ))}
            </div>
            {lines.length ? (
              <div className="overflow-x-auto border-y border-border">
                <table className="w-full min-w-[1500px] text-sm">
                  <GridHead
                    c={c}
                    cols={[
                      ["date"],
                      ["source"],
                      ["floor"],
                      ["machine"],
                      ["shift"],
                      ["operator"],
                      ["sc_program"],
                      ["qty_pcs", true],
                      ["reject", true],
                      ["remarks"],
                      ["status"],
                      ["action", true],
                    ]}
                  />
                  <tbody>
                    {lines.map((l, i) => {
                      const n = i + 1;
                      return (
                        <tr key={l.id} className="border-t border-border">
                          <Cell>
                            <Input
                              type="date"
                              value={l.prod_date}
                              onChange={(e) =>
                                setL(i, { prod_date: e.target.value })
                              }
                              disabled={!open}
                              className="w-36"
                              aria-label={`${c("col.date")} ${n}`}
                            />
                          </Cell>
                          <LineSelect
                            value={l.source}
                            onChange={(v) =>
                              setL(i, {
                                source: v,
                                program_no:
                                  v === "IN_HOUSE" ? "" : l.program_no,
                              })
                            }
                            options={listOpts(c, options.sources, "source")}
                            label={`${c("col.source")} ${n}`}
                            disabled={!open}
                          />
                          <LineSelect
                            value={l.floor_id}
                            onChange={(v) => setL(i, { floor_id: v })}
                            options={options.floors}
                            label={`${c("col.floor")} ${n}`}
                            disabled={!open}
                          />
                          <LineSelect
                            value={l.machine_id}
                            onChange={(v) => setL(i, { machine_id: v })}
                            options={options.machines}
                            label={`${c("col.machine")} ${n}`}
                            disabled={!open}
                            width="min-w-[120px]"
                          />
                          <LineSelect
                            value={l.shift_id}
                            onChange={(v) => setL(i, { shift_id: v })}
                            options={options.shifts}
                            label={`${c("col.shift")} ${n}`}
                            disabled={!open}
                          />
                          <LineSelect
                            value={l.operator_id}
                            onChange={(v) => setL(i, { operator_id: v })}
                            options={options.employees}
                            label={`${c("col.operator")} ${n}`}
                            disabled={!open}
                            width="min-w-[190px]"
                          />
                          <LineSelect
                            value={l.program_no}
                            onChange={(v) => setL(i, { program_no: v })}
                            options={programs}
                            label={`${c("col.sc_program")} ${n}`}
                            disabled={!open || l.source !== "SC_OUT_HOUSE"}
                            width="min-w-[180px]"
                          />
                          <LineNum
                            value={l.qty}
                            onChange={(v) => setL(i, { qty: v })}
                            label={`${c("col.qty_pcs")} ${n}`}
                            disabled={!open}
                          />
                          <LineNum
                            value={l.reject_qty}
                            onChange={(v) => setL(i, { reject_qty: v })}
                            label={`${c("col.reject")} ${n}`}
                            disabled={!open}
                          />
                          <LineText
                            value={l.remarks}
                            onChange={(v) => setL(i, { remarks: v })}
                            label={`${c("col.remarks")} ${n}`}
                            disabled={!open}
                            max={300}
                          />
                          <Cell>
                            <StatusChip
                              status={
                                l.source === "IN_HOUSE" ? "PLACED" : "APPROVED"
                              }
                              label={c(`source.${l.source}`)}
                            />
                          </Cell>
                          <Cell className="text-right">
                            {open ? (
                              <Button
                                variant="outline"
                                size="sm"
                                className="text-danger"
                                onClick={() => remove(l.id)}
                                disabled={pending}
                                aria-label={`${c("btn.remove")} ${n}`}
                              >
                                <Trash2 /> {c("btn.remove")}
                              </Button>
                            ) : null}
                          </Cell>
                        </tr>
                      );
                    })}
                  </tbody>
                  <tfoot className="border-t border-border bg-surface-muted font-semibold">
                    <tr>
                      <td className="px-2 py-2" colSpan={7}>
                        {c("total_lines", { n: lines.length })}
                      </td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {qtyFmt.format(sum(lines, (l) => num(l.qty)))}
                      </td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {qtyFmt.format(sum(lines, (l) => num(l.reject_qty)))}
                      </td>
                      <td colSpan={3} />
                    </tr>
                  </tfoot>
                </table>
              </div>
            ) : (
              <NoLines
                title={c("empty.prod_lines", { req: reqTitle(c, req) })}
                hint={c("empty.prod_lines_hint")}
              />
            )}
            <div className="flex flex-wrap items-center justify-between gap-3 p-5">
              <p className="max-w-3xl text-[13px] text-foreground-muted">
                {c("prod_foot", { count: lines.length })}
              </p>
              {open ? (
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    onClick={() => setLines(saved)}
                    disabled={!dirty || pending}
                  >
                    <Undo2 /> {c("btn.revert")}
                  </Button>
                  <Button onClick={save} loading={pending} disabled={!dirty}>
                    <Save /> {c("btn.save")}
                  </Button>
                </div>
              ) : null}
            </div>
          </>
        )}
      </section>
      <Modal
        open={dialog}
        onOpenChange={setDialog}
        title={req ? c("add_prod_title", { req: reqTitle(c, req) }) : ""}
        description={c("hint.prod_rule")}
        className="max-w-3xl"
        footer={
          <div className="flex gap-2">
            <Button
              variant="outline"
              onClick={() => setForm(blankForm())}
              disabled={pending}
            >
              <RotateCcw /> {c("btn.reset")}
            </Button>
            <Button onClick={add} loading={pending}>
              <Plus /> {c("btn.add_production")}
            </Button>
          </div>
        }
      >
        {formErrors ? (
          <Alert variant="danger" className="mb-3">
            {formErrors.join(" · ")}
          </Alert>
        ) : null}
        <div className="grid gap-4 sm:grid-cols-3">
          <Field label={c("f.date")} required>
            <Input
              type="date"
              value={form.prod_date}
              onChange={(e) => set({ prod_date: e.target.value })}
              aria-label={c("f.date")}
            />
          </Field>
          <RadioField label={c("f.source")} required className="sm:col-span-2">
            {options.sources.map((s) => (
              <label key={s} className="flex items-center gap-2">
                <input
                  type="radio"
                  name="cc-prod-source"
                  value={s}
                  checked={form.source === s}
                  onChange={() =>
                    set({
                      source: s,
                      program_no: s === "IN_HOUSE" ? "" : form.program_no,
                    })
                  }
                  className="size-4 accent-brand"
                />
                {c(`source.${s}`)}
              </label>
            ))}
          </RadioField>
          <Field label={c("f.floor")}>
            <PSelect
              value={form.floor_id}
              onValueChange={(v) => set({ floor_id: v ?? "" })}
              options={pick(options.floors, c("ph.floor"))}
              aria-label={c("f.floor")}
            />
          </Field>
          <Field label={c("f.machine")}>
            <PSelect
              value={form.machine_id}
              onValueChange={(v) => set({ machine_id: v ?? "" })}
              options={pick(
                options.machines.filter(
                  (m) =>
                    !form.floor_id ||
                    !m.floor_id ||
                    m.floor_id === form.floor_id,
                ),
                c("ph.machine"),
              )}
              aria-label={c("f.machine")}
            />
          </Field>
          <Field label={c("f.shift")}>
            <PSelect
              value={form.shift_id}
              onValueChange={(v) => set({ shift_id: v ?? "" })}
              options={pick(options.shifts, c("ph.shift"))}
              aria-label={c("f.shift")}
            />
          </Field>
          <Field label={c("f.operator")}>
            <PSelect
              value={form.operator_id}
              onValueChange={(v) => set({ operator_id: v ?? "" })}
              options={pick(options.employees, c("ph.operator"))}
              aria-label={c("f.operator")}
            />
          </Field>
          <Field label={c("f.sc_program")} hint={c("hint.sc_program")}>
            <PSelect
              value={form.program_no}
              onValueChange={(v) => set({ program_no: v ?? "" })}
              options={pick(programs, "—")}
              disabled={form.source !== "SC_OUT_HOUSE"}
              aria-label={c("f.sc_program")}
            />
          </Field>
          <div />
          <Field label={c("f.qty")} required>
            <Input
              {...numIn}
              value={form.qty}
              onChange={(e) => set({ qty: e.target.value })}
              aria-label={c("f.qty")}
            />
          </Field>
          <Field label={c("f.reject")}>
            <Input
              {...numIn}
              value={form.reject_qty}
              onChange={(e) => set({ reject_qty: e.target.value })}
              placeholder="0"
              aria-label={c("f.reject")}
            />
          </Field>
          <Field label={c("f.remarks")} className="sm:col-span-3">
            <Textarea
              rows={2}
              value={form.remarks}
              onChange={(e) => set({ remarks: e.target.value })}
              maxLength={300}
              aria-label={c("f.remarks")}
            />
          </Field>
        </div>
      </Modal>
    </div>
  );
}

/* ---------------- out-house programmes ---------------- */

function ProgList({ options, initialRows, canManage, act }) {
  const c = useCc();
  const flt = useShowFilters({ no: "", status: "", from: "", to: "" });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "cc/programs",
    "program_no",
    setRows,
    setError,
  );
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.no || has(r.program_no, o.no)) &&
      (!o.status || r.status === o.status) &&
      inRange(o, r.program_date),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`cc/programs/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printProgram(c, res.data);
    });
  }
  const step = (r, from, action, icon, danger = false) =>
    canManage && from.includes(r.status) ? (
      <MoveButton
        icon={icon}
        label={c(`btn.${action}`)}
        no={r.program_no}
        onClick={() => move(r.program_no, action, act)}
        disabled={pending}
        danger={danger}
      />
    ) : null;
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <Field label={c("f.program_no")}>
            <Input
              value={flt.f.no}
              onChange={(e) => flt.set("no", e.target.value)}
              placeholder={c("ph.search_program")}
              aria-label={c("f.program_no")}
            />
          </Field>
          <Field label={c("f.program_status")}>
            <PSelect
              value={flt.f.status}
              onValueChange={(v) => flt.set("status", v)}
              options={pick(
                listOpts(c, options.program_statuses, "program_status"),
                c("ph.select"),
              )}
              aria-label={c("f.program_status")}
            />
          </Field>
          <DateRange c={c} flt={flt} />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.program_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(
            c,
            "program_no",
            "program_date",
            BASE.program,
            "program_status",
          ),
          { key: "party", label: c("col.supplier") },
          { key: "cpi", label: c("col.cpi"), text: (r) => r.cpi ?? "—" },
          { key: "parts", label: c("col.parts"), num: true },
          qtyCol(c, "qty", "col.qty_pcs"),
          qtyCol(c, "received"),
          {
            key: "remarks",
            label: c("col.remarks"),
            text: (r) => r.remarks ?? "—",
          },
        ]}
        exportName="collar-cuff-programmes"
        addHref={canManage ? `${BASE.program}?tab=entry` : null}
        empty={<NoLines title={c("empty.programs")} hint={c("empty.hint")} />}
        pending={pending}
        actions={(r) => (
          <>
            {step(r, ["DRAFT"], "submit", Send)}
            {step(r, ["SUBMITTED"], "approve", BadgeCheck)}
            {step(r, ["SUBMITTED"], "send-back", Undo2)}
            {step(r, ["APPROVED"], "start", Play)}
            {step(r, ["APPROVED", "IN_PRODUCTION"], "close", Lock)}
            {step(r, ["DRAFT", "SUBMITTED", "APPROVED"], "cancel", Ban, true)}
            <MoveButton
              icon={Printer}
              label={c("btn.print")}
              no={r.program_no}
              onClick={() => print(r.program_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

const PROG_HEAD = [
  "program_date",
  "delivery_date",
  "party_id",
  "cpi",
  "buyer_id",
  "order_id",
  "work_style_no",
  "remarks",
];
const PROG_LINE = [
  "item",
  "description",
  "colour_id",
  "size_desc",
  "gauge",
  "qty",
  "rate",
  "uom",
  "remarks",
];

function ProgEntry({ options, initial, canManage, act }) {
  const [today] = useState(todayIso);
  const d = useDoc({
    path: "cc/programs",
    idKey: "program_no",
    initial,
    asHead: (x) => ({
      ...Object.fromEntries(PROG_HEAD.map((k) => [k, str(x?.[k])])),
      program_date: str(x?.program_date) || today,
    }),
    asLine: (l) => ({
      ...Object.fromEntries(PROG_LINE.map((k) => [k, str(l[k])])),
      uom: str(l.uom) || "PCS",
    }),
    canManage,
    act,
  });
  const { c, head, setH, open, lines, setL } = d;
  function pickOrder(id) {
    const o = options.orders.find((x) => x.value === id);
    setH({
      order_id: id,
      buyer_id: o?.buyer_id ?? head.buyer_id,
      work_style_no: head.work_style_no || str(o?.style),
    });
  }
  function save() {
    d.save({
      ...clean(Object.fromEntries(PROG_HEAD.map((k) => [k, head[k]]))),
      lines: lines.map((l) => ({
        ...clean(Object.fromEntries(PROG_LINE.map((k) => [k, l[k]]))),
        qty: orNull(l.qty),
        rate: orNull(l.rate),
      })),
    });
  }
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={d.doc ? d.doc.program_no : c("tabs.program_entry")}
          hint={c("hint.program_form")}
          doc={d.doc}
          status={d.doc?.status}
          group="program_status"
        />
        <Locked d={d} idKey="program_no" group="program_status" />
        <Errors errors={d.errors} />
        <Existing
          d={d}
          idKey="program_no"
          docs={options.docs}
          label="f.existing_program"
          ph="ph.new_program"
        />
        <h3 className="mt-5 text-sm font-semibold">{c("sec.program_info")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.program_no")} hint={c("hint.issued_on_save")}>
            <Input
              value={d.doc?.program_no ?? ""}
              placeholder={c("ph.auto")}
              disabled
              aria-label={c("f.program_no")}
            />
          </Field>
          <Field label={c("f.date")} required>
            <Input
              type="date"
              value={head.program_date}
              onChange={(e) => setH({ program_date: e.target.value })}
              disabled={!open}
              aria-label={c("f.date")}
            />
          </Field>
          <Field label={c("f.delivery_date")} hint={c("hint.delivery_date")}>
            <Input
              type="date"
              value={head.delivery_date}
              onChange={(e) => setH({ delivery_date: e.target.value })}
              disabled={!open}
              aria-label={c("f.delivery_date")}
            />
          </Field>
          <Field label={c("f.supplier")} required>
            <PSelect
              value={head.party_id}
              onValueChange={(v) => setH({ party_id: v ?? "" })}
              options={pick(options.parties, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.supplier")}
            />
          </Field>
          <Field label={c("f.cpi")} hint={c("hint.cpi")}>
            <Input
              value={head.cpi}
              onChange={(e) => setH({ cpi: e.target.value })}
              maxLength={24}
              placeholder={c("ph.cpi")}
              disabled={!open}
              aria-label={c("f.cpi")}
            />
          </Field>
          <Field label={c("f.company")} required>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={c("f.company")}
            />
          </Field>
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.against")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.buyer")}>
            <PSelect
              value={head.buyer_id}
              onValueChange={(v) => setH({ buyer_id: v ?? "" })}
              options={pick(options.buyers, c("ph.buyer"))}
              disabled={!open}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.order")}>
            <PSelect
              value={head.order_id}
              onValueChange={(v) => pickOrder(v ?? "")}
              options={pick(
                options.orders.filter(
                  (x) => !head.buyer_id || x.buyer_id === head.buyer_id,
                ),
                c("ph.order"),
              )}
              disabled={!open}
              aria-label={c("f.order")}
            />
          </Field>
          <Field label={c("f.work_style")}>
            <PSelect
              value={head.work_style_no}
              onValueChange={(v) => setH({ work_style_no: v ?? "" })}
              options={pick(
                [
                  ...styleOpts(options, head.buyer_id),
                  ...(head.work_style_no &&
                  !styleOpts(options, head.buyer_id).some(
                    (s) => s.value === head.work_style_no,
                  )
                    ? [{ value: head.work_style_no, label: head.work_style_no }]
                    : []),
                ],
                c("ph.style"),
              )}
              disabled={!open}
              aria-label={c("f.work_style")}
            />
          </Field>
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.notes")}</h3>
        <Field label={c("f.remarks")} className="mt-3">
          <Textarea
            rows={2}
            value={head.remarks}
            onChange={(e) => setH({ remarks: e.target.value })}
            maxLength={2000}
            disabled={!open}
            aria-label={c("f.remarks")}
          />
        </Field>
      </section>
      <GridCard
        c={c}
        title={c("sec.program_details")}
        hint={c("hint.program_lines")}
        button={c("btn.add_part")}
        onAdd={() => d.add()}
        open={open}
        empty={c("empty.program_lines")}
        count={lines.length}
      >
        <table className="w-full min-w-[1400px] text-sm">
          <GridHead
            c={c}
            cols={[
              ["item"],
              ["description"],
              ["colour"],
              ["size"],
              ["gauge"],
              ["qty_pcs", true],
              ["rate", true],
              ["amount", true],
              ["uom"],
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
                    value={l.item}
                    onChange={(v) => setL(l.key, { item: v })}
                    options={itemOpts(c, options)}
                    label={`${c("col.item")} ${n}`}
                    disabled={!open}
                    width="min-w-[130px]"
                  />
                  <LineText
                    value={l.description}
                    onChange={(v) => setL(l.key, { description: v })}
                    label={`${c("col.description")} ${n}`}
                    disabled={!open}
                    width="w-48"
                  />
                  <LineSelect
                    value={l.colour_id}
                    onChange={(v) => setL(l.key, { colour_id: v })}
                    options={options.colours}
                    label={`${c("col.colour")} ${n}`}
                    disabled={!open}
                  />
                  <LineText
                    value={l.size_desc}
                    onChange={(v) => setL(l.key, { size_desc: v })}
                    label={`${c("col.size")} ${n}`}
                    disabled={!open}
                    max={80}
                    width="w-24"
                  />
                  <LineText
                    value={l.gauge}
                    onChange={(v) => setL(l.key, { gauge: v })}
                    label={`${c("col.gauge")} ${n}`}
                    disabled={!open}
                    max={40}
                    width="w-20"
                  />
                  <LineNum
                    value={l.qty}
                    onChange={(v) => setL(l.key, { qty: v })}
                    label={`${c("col.qty_pcs")} ${n}`}
                    disabled={!open}
                  />
                  <LineNum
                    value={l.rate}
                    onChange={(v) => setL(l.key, { rate: v })}
                    label={`${c("col.rate")} ${n}`}
                    disabled={!open}
                  />
                  <Cell className="text-right font-medium tabular-nums">
                    {moneyFmt.format(num(l.qty) * num(l.rate))}
                  </Cell>
                  <LineSelect
                    value={l.uom}
                    onChange={(v) => setL(l.key, { uom: v })}
                    options={options.uoms}
                    label={`${c("col.uom")} ${n}`}
                    disabled={!open}
                    width="min-w-[90px]"
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
              <td className="px-2 py-2" colSpan={5}>
                {c("total_lines", { n: lines.length })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {qtyFmt.format(sum(lines, (l) => num(l.qty)))}
              </td>
              <td />
              <td className="px-2 py-2 text-right tabular-nums">
                {moneyFmt.format(sum(lines, (l) => num(l.qty) * num(l.rate)))}
              </td>
              <td colSpan={3} />
            </tr>
          </tfoot>
        </table>
      </GridCard>
      <FormFoot
        c={c}
        hint={c("hint.program_foot")}
        back={BASE.program}
        onClear={() => d.show(null)}
        onSave={save}
        saveLabel={c("btn.save_program")}
        pending={d.pending}
        open={open}
      />
    </div>
  );
}

/* ---------------- yarn indents ---------------- */

function YarnList({ kind, options, initialRows, canManage, act }) {
  const c = useCc();
  const path = `cc/yarn/${SLUG[kind]}`;
  const flt = useShowFilters({
    no: "",
    order: "",
    status: "",
    from: "",
    to: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    path,
    "req_no",
    setRows,
    setError,
  );
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.no || has(r.req_no, o.no)) &&
      (!o.order || r.order_id === o.order) &&
      (!o.status || r.status === o.status) &&
      inRange(o, r.req_date),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`${path}/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printYarnReq(c, res.data);
    });
  }
  const step = (r, from, action, icon, danger = false) =>
    canManage && from.includes(r.status) ? (
      <MoveButton
        icon={icon}
        label={c(`btn.${action}`)}
        no={r.req_no}
        onClick={() => move(r.req_no, action, act)}
        disabled={pending}
        danger={danger}
      />
    ) : null;
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <Field label={c("f.req_no")}>
            <Input
              value={flt.f.no}
              onChange={(e) => flt.set("no", e.target.value)}
              aria-label={c("f.req_no")}
            />
          </Field>
          <Field label={c("f.style_order")}>
            <PSelect
              value={flt.f.order}
              onValueChange={(v) => flt.set("order", v)}
              options={pick(options.orders, c("ph.order"))}
              aria-label={c("f.style_order")}
            />
          </Field>
          <Field label={c("f.status")}>
            <PSelect
              value={flt.f.status}
              onValueChange={(v) => flt.set("status", v)}
              options={pick(
                listOpts(c, options.yarn_statuses, "yarn_status"),
                c("ph.select"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <DateRange c={c} flt={flt} />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.yarn_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(c, "req_no", "req_date", BASE[kind], "yarn_status"),
          {
            key: "order",
            label: c("col.style_order"),
            text: (r) => r.order ?? "—",
          },
          {
            key: "knit_plan_no",
            label: c("col.knit_plan"),
            text: (r) => r.knit_plan_no ?? "—",
          },
          {
            key: "program_no",
            label: c("col.program"),
            text: (r) => r.program_no ?? "—",
          },
          { key: "store", label: c("col.store"), text: (r) => r.store ?? "—" },
          { key: "lines", label: c("col.lines"), num: true },
          qtyCol(c, "req_qty"),
          qtyCol(c, "issued"),
          qtyCol(c, "balance"),
        ]}
        exportName={`collar-cuff-yarn-${SLUG[kind]}`}
        addHref={canManage ? `${BASE[kind]}?tab=entry` : null}
        empty={
          <NoLines title={c("empty.yarn_reqs")} hint={c("empty.yarn_hint")} />
        }
        pending={pending}
        actions={(r) => (
          <>
            {step(r, ["DRAFT"], "submit", Send)}
            {step(r, ["SUBMITTED"], "approve", BadgeCheck)}
            {step(r, ["APPROVED", "PARTLY_ISSUED"], "close", Lock)}
            {step(r, ["DRAFT", "SUBMITTED", "APPROVED"], "cancel", Ban, true)}
            <MoveButton
              icon={Printer}
              label={c("btn.print")}
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

const YARN_HEAD = [
  "req_date",
  "knit_plan_no",
  "program_no",
  "buyer_id",
  "order_id",
  "work_style_no",
  "store_id",
  "req_type",
  "remarks",
];
const YARN_LINE = [
  "req_no",
  "buyer_id",
  "order_id",
  "fabric_type_id",
  "colour_id",
  "composition_id",
  "yarn_count_id",
  "brand",
  "lot_no",
  "req_qty",
  "uom",
  "remarks",
];

function YarnEntry({ kind, options, initial, canManage, act }) {
  const [today] = useState(todayIso);
  const [buyer, setBuyer] = useState("");
  const d = useDoc({
    path: `cc/yarn/${SLUG[kind]}`,
    idKey: "req_no",
    initial,
    asHead: (x) => ({
      ...Object.fromEntries(YARN_HEAD.map((k) => [k, str(x?.[k])])),
      req_date: str(x?.req_date) || today,
    }),
    asLine: (l) => ({
      ...Object.fromEntries(YARN_LINE.map((k) => [k, str(l[k])])),
      uom: str(l.uom) || "KG",
    }),
    canManage,
    act,
  });
  const { c, head, setH, open, lines, setL } = d;
  function loadPlan(p) {
    setH({
      order_id: p.order_id,
      buyer_id: str(p.buyer_id) || head.buyer_id,
      knit_plan_no: head.knit_plan_no || str(p.style),
      work_style_no: head.work_style_no || str(p.style),
    });
    p.lines.forEach((r) =>
      d.add({
        req_no: r.req_no,
        buyer_id: r.buyer_id,
        order_id: r.order_id,
        colour_id: r.colour_id,
        remarks: `${c(`item.${r.item}`)} — ${r.description}`,
      }),
    );
  }
  function save() {
    d.save({
      ...clean(Object.fromEntries(YARN_HEAD.map((k) => [k, head[k]]))),
      lines: lines.map((l) => ({
        ...clean(Object.fromEntries(YARN_LINE.map((k) => [k, l[k]]))),
        req_qty: orNull(l.req_qty),
      })),
    });
  }
  const plans = options.plans
    .filter((p) => !buyer || p.buyer_id === buyer)
    .map((p) => ({ ...p, key: p.order_id }));
  return (
    <div className="grid gap-4 xl:grid-cols-[360px_1fr]">
      <PickPanel
        c={c}
        title={c("sec.new_order_kp")}
        filter={
          <Field label={c("f.buyer")} className="mt-3">
            <PSelect
              value={buyer}
              onValueChange={(v) => setBuyer(v ?? "")}
              options={pick(options.buyers, c("ph.buyer"))}
              aria-label={`${c("f.buyer")} (${c("sec.new_order_kp")})`}
            />
          </Field>
        }
        placeholder={c("ph.search_order")}
        rows={plans}
        columns={[
          [c("col.work_style"), (r) => r.style],
          [c("col.order_no"), (r) => r.order_no],
          [c("col.parts"), (r) => r.parts, true],
          [c("col.req_pcs"), (r) => qtyFmt.format(r.req), true],
        ]}
        action={(r) => (
          <Button
            variant="outline"
            size="sm"
            onClick={() => loadPlan(r)}
            disabled={!open}
            aria-label={`${c("btn.load_plan")} ${r.order_no}`}
          >
            {c("btn.load_plan")}
          </Button>
        )}
      />
      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <FormHead
            c={c}
            title={d.doc ? d.doc.req_no : c(`tabs.yarn_${kind}_entry`)}
            hint={c("hint.yarn_form")}
            doc={d.doc}
            status={d.doc?.status}
            group="yarn_status"
          />
          <Locked d={d} idKey="req_no" group="yarn_status" />
          <Errors errors={d.errors} />
          <Existing
            d={d}
            idKey="req_no"
            docs={options.docs}
            label="f.existing_req"
            ph="ph.new_req"
          />
          <h3 className="mt-5 text-sm font-semibold">
            {c("sec.knit_plan_info")}
          </h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <Field label={c("f.ref_no")} hint={c("hint.issued_on_save")}>
              <Input
                value={d.doc?.req_no ?? ""}
                placeholder={c("ph.auto")}
                disabled
                aria-label={c("f.ref_no")}
              />
            </Field>
            <Field label={c("f.req_date")} required>
              <Input
                type="date"
                value={head.req_date}
                onChange={(e) => setH({ req_date: e.target.value })}
                disabled={!open}
                aria-label={c("f.req_date")}
              />
            </Field>
            <Field label={c("f.knit_plan")}>
              <Input
                value={head.knit_plan_no}
                onChange={(e) => setH({ knit_plan_no: e.target.value })}
                maxLength={60}
                placeholder={c("ph.knit_plan")}
                disabled={!open}
                aria-label={c("f.knit_plan")}
              />
            </Field>
            <Field label={c("f.program")} hint={c("hint.yarn_program")}>
              <PSelect
                value={head.program_no}
                onValueChange={(v) => setH({ program_no: v ?? "" })}
                options={pick(
                  options.programs.map((p) => ({
                    value: p.value,
                    label: p.label,
                  })),
                  c("ph.program"),
                )}
                disabled={!open}
                aria-label={c("f.program")}
              />
            </Field>
          </div>
          <h3 className="mt-5 text-sm font-semibold">{c("sec.against")}</h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <Field label={c("f.buyer")}>
              <PSelect
                value={head.buyer_id}
                onValueChange={(v) => setH({ buyer_id: v ?? "" })}
                options={pick(options.buyers, c("ph.buyer"))}
                disabled={!open}
                aria-label={c("f.buyer")}
              />
            </Field>
            <Field label={c("f.style_order")}>
              <PSelect
                value={head.order_id}
                onValueChange={(v) => {
                  const o = options.orders.find((x) => x.value === v);
                  setH({
                    order_id: v ?? "",
                    buyer_id: o?.buyer_id ?? head.buyer_id,
                    work_style_no: head.work_style_no || str(o?.style),
                  });
                }}
                options={pick(
                  options.orders.filter(
                    (x) => !head.buyer_id || x.buyer_id === head.buyer_id,
                  ),
                  c("ph.order"),
                )}
                disabled={!open}
                aria-label={c("f.style_order")}
              />
            </Field>
            <Field label={c("f.work_style")}>
              <PSelect
                value={head.work_style_no}
                onValueChange={(v) => setH({ work_style_no: v ?? "" })}
                options={pick(
                  [
                    ...styleOpts(options, head.buyer_id),
                    ...(head.work_style_no &&
                    !styleOpts(options, head.buyer_id).some(
                      (s) => s.value === head.work_style_no,
                    )
                      ? [
                          {
                            value: head.work_style_no,
                            label: head.work_style_no,
                          },
                        ]
                      : []),
                  ],
                  c("ph.style"),
                )}
                disabled={!open}
                aria-label={c("f.work_style")}
              />
            </Field>
          </div>
          <h3 className="mt-5 text-sm font-semibold">{c("sec.draw_from")}</h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <Field label={c("f.company")} required>
              <PSelect
                value={options.company.value}
                options={[options.company]}
                disabled
                aria-label={c("f.company")}
              />
            </Field>
            <Field label={c("f.store")}>
              <PSelect
                value={head.store_id}
                onValueChange={(v) => setH({ store_id: v ?? "" })}
                options={pick(options.stores, c("ph.store"))}
                disabled={!open}
                aria-label={c("f.store")}
              />
            </Field>
            <Field label={c("f.req_type")}>
              <PSelect
                value={head.req_type}
                onValueChange={(v) => setH({ req_type: v ?? "" })}
                options={pick(
                  listOpts(c, options.req_types, "req_type"),
                  c("ph.select"),
                )}
                disabled={!open}
                aria-label={c("f.req_type")}
              />
            </Field>
          </div>
          <Field label={c("f.remarks")} className="mt-4">
            <Textarea
              rows={2}
              value={head.remarks}
              onChange={(e) => setH({ remarks: e.target.value })}
              maxLength={2000}
              disabled={!open}
              aria-label={c("f.remarks")}
            />
          </Field>
        </section>
        <GridCard
          c={c}
          title={c("sec.yarn_list")}
          hint={c(`hint.yarn_lines_${kind}`)}
          button={c("btn.add_yarn")}
          onAdd={() => d.add()}
          open={open}
          empty={c("empty.yarn_lines")}
          count={lines.length}
        >
          <table className="w-full min-w-[1700px] text-sm">
            <GridHead
              c={c}
              cols={[
                ["buyer"],
                ["style_order"],
                ["fab_type"],
                ["colour"],
                ["yarn"],
                ["count"],
                ["brand"],
                ["lot"],
                ["req_qty", true],
                ["uom"],
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
                      value={l.buyer_id}
                      onChange={(v) => setL(l.key, { buyer_id: v })}
                      options={options.buyers}
                      label={`${c("col.buyer")} ${n}`}
                      disabled={!open}
                      width="min-w-[130px]"
                    />
                    <LineSelect
                      value={l.order_id}
                      onChange={(v) => setL(l.key, { order_id: v })}
                      options={options.orders}
                      label={`${c("col.style_order")} ${n}`}
                      disabled={!open}
                      width="min-w-[190px]"
                    />
                    <LineSelect
                      value={l.fabric_type_id}
                      onChange={(v) => setL(l.key, { fabric_type_id: v })}
                      options={options.fabric_types}
                      label={`${c("col.fab_type")} ${n}`}
                      disabled={!open}
                    />
                    <LineSelect
                      value={l.colour_id}
                      onChange={(v) => setL(l.key, { colour_id: v })}
                      options={options.colours}
                      label={`${c("col.colour")} ${n}`}
                      disabled={!open}
                    />
                    <LineSelect
                      value={l.composition_id}
                      onChange={(v) => setL(l.key, { composition_id: v })}
                      options={options.compositions}
                      label={`${c("col.yarn")} ${n}`}
                      disabled={!open}
                      width="min-w-[200px]"
                    />
                    <LineSelect
                      value={l.yarn_count_id}
                      onChange={(v) => setL(l.key, { yarn_count_id: v })}
                      options={options.counts}
                      label={`${c("col.count")} ${n}`}
                      disabled={!open}
                      width="min-w-[100px]"
                    />
                    <LineText
                      value={l.brand}
                      onChange={(v) => setL(l.key, { brand: v })}
                      label={`${c("col.brand")} ${n}`}
                      disabled={!open}
                      max={80}
                      width="w-28"
                    />
                    <LineText
                      value={l.lot_no}
                      onChange={(v) => setL(l.key, { lot_no: v })}
                      label={`${c("col.lot")} ${n}`}
                      disabled={!open}
                      max={40}
                      width="w-28"
                    />
                    <LineNum
                      value={l.req_qty}
                      onChange={(v) => setL(l.key, { req_qty: v })}
                      label={`${c("col.req_qty")} ${n}`}
                      disabled={!open}
                    />
                    <LineSelect
                      value={l.uom}
                      onChange={(v) => setL(l.key, { uom: v })}
                      options={options.uoms}
                      label={`${c("col.uom")} ${n}`}
                      disabled={!open}
                      width="min-w-[90px]"
                    />
                    <LineText
                      value={l.remarks}
                      onChange={(v) => setL(l.key, { remarks: v })}
                      label={`${c("col.remarks")} ${n}`}
                      disabled={!open}
                      max={300}
                      width="w-48"
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
                <td className="px-2 py-2" colSpan={8}>
                  {c("total_lines", { n: lines.length })}
                </td>
                <td className="px-2 py-2 text-right tabular-nums">
                  {qtyFmt.format(sum(lines, (l) => num(l.req_qty)))}
                </td>
                <td colSpan={3} />
              </tr>
            </tfoot>
          </table>
        </GridCard>
        <FormFoot
          c={c}
          hint={c("hint.yarn_foot")}
          back={BASE[kind]}
          onClear={() => d.show(null)}
          onSave={save}
          saveLabel={c("btn.save_yarn_req")}
          pending={d.pending}
          open={open}
        />
      </div>
    </div>
  );
}

/** Issue Against Requisition: approved indents on the left, what the store releases on the right. */
function YarnIssue({ kind, initialRows, canIssue, act }) {
  const c = useCc();
  const toastManager = useToastManager();
  const path = `cc/yarn/${SLUG[kind]}`;
  const [rows, setRows] = useState(initialRows);
  const [req, setReq] = useState(null);
  const [lots, setLots] = useState([]);
  const [take, setTake] = useState({});
  const [today] = useState(todayIso);
  const [date, setDate] = useState(today);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const live = rows
    .filter(
      (r) =>
        ["APPROVED", "PARTLY_ISSUED"].includes(r.status) ||
        r.req_no === req?.req_no,
    )
    .map((r) => ({ ...r, key: r.req_no }));
  const open =
    canIssue && req && ["APPROVED", "PARTLY_ISSUED"].includes(req.status);
  function select(no) {
    startTransition(async () => {
      const res = await act(`${path}/${enc(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      const stock = await act(
        `cc/yarn/lots${res.data.store_id ? `?store=${enc(res.data.store_id)}` : ""}`,
      );
      setReq(res.data);
      setLots(stock?.status === "success" ? stock.data : []);
      setTake({});
      setErrors(null);
    });
  }
  const lotsFor = (l) =>
    lots
      .filter(
        (x) =>
          x.composition_id === l.composition_id &&
          (!l.yarn_count_id || x.yarn_count_id === l.yarn_count_id) &&
          (!l.lot_no || (x.lot_no ?? "").toUpperCase() === l.lot_no),
      )
      .map((x) => ({
        value: x.lot_id,
        label: `${x.label} · ${qtyFmt.format(x.balance)} ${x.uom}`,
      }));
  const setT = (id, patch) =>
    setTake((t) => ({
      ...t,
      [id]: { ...(t[id] ?? { lot_id: "", qty: "" }), ...patch },
    }));
  function issue() {
    startTransition(async () => {
      const res = await act(`${path}/${enc(req.req_no)}/issue`, "POST", {
        issue_date: date,
        lines: Object.entries(take)
          .filter(([, t]) => t.qty !== "" || t.lot_id)
          .map(([id, t]) => ({
            req_line_id: id,
            lot_id: t.lot_id || null,
            qty: orNull(t.qty),
          })),
      });
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setReq(res.data);
      setTake({});
      setErrors(null);
      setRows((all) =>
        all.map((r) =>
          r.req_no === res.data.req_no
            ? {
                ...r,
                status: res.data.status,
                issued: sum(res.data.lines, (l) => l.issued),
                balance: Math.max(
                  0,
                  sum(res.data.lines, (l) => l.req_qty - l.issued),
                ),
              }
            : r,
        ),
      );
      const stock = await act(
        `cc/yarn/lots${res.data.store_id ? `?store=${enc(res.data.store_id)}` : ""}`,
      );
      if (stock?.status === "success") setLots(stock.data);
      toastManager.add({
        title: c("done.issued", { no: res.data.req_no }),
        type: "success",
      });
    });
  }
  return (
    <div className="grid gap-4 xl:grid-cols-[360px_1fr]">
      <PickPanel
        c={c}
        title={c("sec.approved_reqs")}
        placeholder={c("ph.search")}
        rows={live}
        columns={[
          [c("col.req_no"), (r) => r.req_no],
          [c("col.style_order"), (r) => r.order],
          [c("col.balance"), (r) => qtyFmt.format(r.balance), true],
        ]}
        action={(r) => (
          <Button
            variant={req?.req_no === r.req_no ? "default" : "outline"}
            size="sm"
            onClick={() => select(r.req_no)}
            aria-label={`${c("btn.select")} ${r.req_no}`}
          >
            {c("btn.select")}
          </Button>
        )}
      />
      <section className={cn(card, "min-w-0 p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold">
              {req
                ? c("issue_title", { no: req.req_no })
                : c("sec.yarn_issued")}
            </h2>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {c("hint.issue_desk")}
            </p>
          </div>
          {req ? (
            <StatusChip
              status={TONE[req.status]}
              label={c(`yarn_status.${req.status}`)}
            />
          ) : null}
        </div>
        <Errors errors={errors} />
        {!req ? (
          <NoLines
            title={c("empty.select_req_yarn")}
            hint={c("empty.select_req_yarn_hint")}
          />
        ) : (
          <>
            <div className="mt-4 grid gap-4 md:grid-cols-4">
              <Field label={c("f.issue_date")} required>
                <Input
                  type="date"
                  value={date}
                  onChange={(e) => setDate(e.target.value)}
                  disabled={!open}
                  aria-label={c("f.issue_date")}
                />
              </Field>
              <Field label={c("f.store")}>
                <Input
                  value={req.store ?? c("ph.any_store")}
                  disabled
                  aria-label={c("f.store")}
                />
              </Field>
              <Field label={c("f.style_order")}>
                <Input
                  value={req.order ?? "—"}
                  disabled
                  aria-label={c("f.style_order")}
                />
              </Field>
              <Field label={c("f.program")}>
                <Input
                  value={req.program_no ?? "—"}
                  disabled
                  aria-label={c("f.program")}
                />
              </Field>
            </div>
            <div className="mt-4 overflow-x-auto rounded-lg border border-border">
              <table className="w-full min-w-[1300px] text-sm">
                <GridHead
                  c={c}
                  cols={[
                    ["sl"],
                    ["yarn"],
                    ["count"],
                    ["colour"],
                    ["brand"],
                    ["lot"],
                    ["req_qty", true],
                    ["issued", true],
                    ["balance", true],
                    ["stock_line"],
                    ["issue_qty", true],
                  ]}
                />
                <tbody>
                  {req.lines.map((l, i) => {
                    const n = i + 1;
                    const t = take[l.id] ?? { lot_id: "", qty: "" };
                    const left = Math.max(0, l.req_qty - l.issued);
                    return (
                      <tr key={l.id} className="border-t border-border">
                        <Cell className="text-center">{n}</Cell>
                        <Cell className="font-medium">{l.yarn}</Cell>
                        <Cell>{l.count ?? "—"}</Cell>
                        <Cell>{l.colour ?? "—"}</Cell>
                        <Cell>{l.brand ?? "—"}</Cell>
                        <Cell>{l.lot_no ?? "—"}</Cell>
                        <Cell className="text-right tabular-nums">
                          {qtyFmt.format(l.req_qty)} {l.uom}
                        </Cell>
                        <Cell className="text-right tabular-nums">
                          {qtyFmt.format(l.issued)}
                        </Cell>
                        <Cell className="text-right font-medium tabular-nums">
                          {qtyFmt.format(left)}
                        </Cell>
                        <LineSelect
                          value={t.lot_id}
                          onChange={(v) => setT(l.id, { lot_id: v })}
                          options={lotsFor(l)}
                          ph={
                            lotsFor(l).length
                              ? c("ph.stock_line")
                              : c("ph.no_stock")
                          }
                          label={`${c("col.stock_line")} ${n}`}
                          disabled={!open || left <= 0}
                          width="min-w-[300px]"
                        />
                        <LineNum
                          value={t.qty}
                          onChange={(v) => setT(l.id, { qty: v })}
                          label={`${c("col.issue_qty")} ${n}`}
                          disabled={!open || left <= 0}
                        />
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
            {open ? (
              <div className="mt-4 flex justify-end">
                <Button onClick={issue} loading={pending}>
                  <Truck /> {c("btn.issue_yarn")}
                </Button>
              </div>
            ) : null}
            <h3 className="mt-6 text-sm font-semibold">
              {c("sec.yarn_issued")}
            </h3>
            <div className="mt-3 overflow-x-auto rounded-lg border border-border">
              {req.issues.length ? (
                <table className="w-full text-sm">
                  <GridHead
                    c={c}
                    cols={[
                      ["issue_no"],
                      ["date"],
                      ["sl"],
                      ["stock_line"],
                      ["qty", true],
                    ]}
                  />
                  <tbody>
                    {req.issues.map((x, i) => (
                      <tr
                        key={`${x.issue_no}-${i}`}
                        className="border-t border-border"
                      >
                        <Cell className="font-medium">{x.issue_no}</Cell>
                        <Cell>{dayFmt(x.issue_date)}</Cell>
                        <Cell>{x.line}</Cell>
                        <Cell>{x.lot}</Cell>
                        <Cell className="text-right tabular-nums">
                          {qtyFmt.format(x.qty)}
                        </Cell>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : (
                <NoLines
                  title={c("empty.issues")}
                  hint={c("empty.issues_hint")}
                />
              )}
            </div>
          </>
        )}
      </section>
    </div>
  );
}

/* ---------------- store receipts ---------------- */

function ChallanList({ kind, options, initialRows, canManage, act }) {
  const c = useCc();
  const path = `cc/challans/${SLUG[kind]}`;
  const flt = useShowFilters({
    no: "",
    type: "",
    status: "",
    from: "",
    to: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    path,
    "challan_no",
    setRows,
    setError,
    // Receiving dates the challan today; re-opening clears it.
    (d) =>
      setRows((all) =>
        all.map((r) =>
          r.challan_no === d.challan_no ? { ...r, rcv_date: d.rcv_date } : r,
        ),
      ),
  );
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.no || has(r.challan_no, o.no)) &&
      (!o.type || r.challan_type === o.type) &&
      (!o.status || r.status === o.status) &&
      inRange(o, r.challan_date),
  );
  function view(no) {
    startTransition(async () => {
      const res = await act(`${path}/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printChallan(c, res.data);
    });
  }
  const step = (r, from, action, icon, danger = false) =>
    canManage && from.includes(r.status) ? (
      <MoveButton
        icon={icon}
        label={c(`btn.${action}`)}
        no={r.challan_no}
        onClick={() => move(r.challan_no, action, act)}
        disabled={pending}
        danger={danger}
      />
    ) : null;
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <Field label={c("f.challan_no")}>
            <Input
              value={flt.f.no}
              onChange={(e) => flt.set("no", e.target.value)}
              placeholder={c("ph.search_challan")}
              aria-label={c("f.challan_no")}
            />
          </Field>
          <Field label={c("f.challan_type")}>
            <PSelect
              value={flt.f.type}
              onValueChange={(v) => flt.set("type", v)}
              options={pick(
                listOpts(c, options.challan_types, "challan_type"),
                c("ph.select"),
              )}
              aria-label={c("f.challan_type")}
            />
          </Field>
          <Field label={c("f.status")}>
            <PSelect
              value={flt.f.status}
              onValueChange={(v) => flt.set("status", v)}
              options={pick(
                listOpts(c, options.challan_statuses, "challan_status"),
                c("ph.select"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <DateRange c={c} flt={flt} />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.challan_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          {
            key: "rcv_date",
            label: c("col.rcv_date"),
            nowrap: true,
            text: (r) => (r.rcv_date ? dayFmt(r.rcv_date) : "—"),
          },
          {
            key: "challan_type",
            label: c("col.challan_type"),
            text: (r) => c(`challan_type.${r.challan_type}`),
          },
          ...docCol(
            c,
            "challan_no",
            "challan_date",
            BASE[kind],
            "challan_status",
          ),
          { key: "from", label: c("col.from"), text: (r) => r.from ?? "—" },
          { key: "lines", label: c("col.lines"), num: true },
          qtyCol(c, "qty", "col.qty_pcs"),
        ]}
        exportName={`collar-cuff-challans-${SLUG[kind]}`}
        addHref={canManage ? `${BASE[kind]}?tab=entry` : null}
        empty={
          <NoLines title={c("empty.challans")} hint={c("empty.challan_hint")} />
        }
        pending={pending}
        actions={(r) => (
          <>
            {step(r, ["DRAFT"], "issue", Send)}
            {step(r, ["ISSUED"], "receive", PackageCheck)}
            {step(r, ["ISSUED", "RECEIVED"], "reopen", Undo2)}
            {step(r, ["DRAFT", "ISSUED"], "cancel", Ban, true)}
            <MoveButton
              icon={Eye}
              label={c("btn.view")}
              no={r.challan_no}
              onClick={() => view(r.challan_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

const CH_HEAD = [
  "challan_type",
  "challan_date",
  "gate_pass_no",
  "vehicle_no",
  "from_floor_id",
  "store_id",
  "remarks",
];
const CH_LINE = [
  "req_no",
  "item",
  "description",
  "colour_id",
  "size_desc",
  "order_id",
  "qty",
  "uom",
  "remarks",
  "program_line_id",
];

function ChallanEntry({ kind, options, initial, initialOpen, canManage, act }) {
  const [today] = useState(todayIso);
  const [part, setPart] = useState("");
  const [openReqs, setOpenReqs] = useState(initialOpen);
  const d = useDoc({
    path: `cc/challans/${SLUG[kind]}`,
    idKey: "challan_no",
    initial,
    asHead: (x) => ({
      ...Object.fromEntries(CH_HEAD.map((k) => [k, str(x?.[k])])),
      challan_date: str(x?.challan_date) || today,
    }),
    asLine: (l) => ({
      ...Object.fromEntries(CH_LINE.map((k) => [k, str(l[k])])),
      uom: str(l.uom) || "PCS",
    }),
    canManage,
    act,
  });
  const { c, head, setH, open, lines, setL } = d;
  const reqOpts = openReqs.map((r) => ({ value: r.req_no, label: r.label }));
  for (const l of lines)
    if (l.req_no && !reqOpts.some((x) => x.value === l.req_no))
      reqOpts.push({ value: l.req_no, label: l.req_no });
  const partOpts = options.programs.flatMap((p) =>
    p.lines.map((x) => ({
      value: x.value,
      label: `${x.label} · ${qtyFmt.format(x.received)} / ${qtyFmt.format(x.qty)}`,
    })),
  );
  // The panel groups what is still open by job, part by part.
  const groups = Object.values(
    openReqs
      .filter((r) => r.open > 0 && (!part || r.item === part))
      .reduce((a, r) => {
        const g = (a[r.group] ??= { key: r.group, group: r.group, reqs: [] });
        g.reqs.push(r);
        return a;
      }, {}),
  ).map((g) => ({
    ...g,
    parts: g.reqs.length,
    open: sum(g.reqs, (r) => r.open),
  }));
  function include(g) {
    for (const r of g.reqs)
      if (!lines.some((l) => l.req_no === r.req_no))
        d.add({
          req_no: r.req_no,
          item: r.item,
          description: r.description,
          colour_id: r.colour_id,
          size_desc: r.size_desc,
          order_id: r.order_id,
          qty: String(r.open),
          uom: r.uom,
        });
  }
  function pickReq(l, no) {
    const r = openReqs.find((x) => x.req_no === no);
    setL(
      l.key,
      r
        ? {
            req_no: no,
            item: r.item,
            description: l.description || str(r.description),
            colour_id: l.colour_id || str(r.colour_id),
            size_desc: l.size_desc || str(r.size_desc),
            order_id: l.order_id || str(r.order_id),
          }
        : { req_no: "" },
    );
  }
  function save() {
    d.save(
      {
        ...clean(Object.fromEntries(CH_HEAD.map((k) => [k, head[k]]))),
        lines: lines.map((l) => ({
          ...clean(Object.fromEntries(CH_LINE.map((k) => [k, l[k]]))),
          qty: orNull(l.qty),
        })),
      },
      async (saved) => {
        const res = await act(
          `cc/challans/${SLUG[kind]}/open?challan=${enc(saved.challan_no)}`,
        );
        if (res?.status === "success") setOpenReqs(res.data);
      },
    );
  }
  return (
    <div className="grid gap-4 xl:grid-cols-[360px_1fr]">
      <PickPanel
        c={c}
        title={c("sec.open_reqs")}
        filter={
          <Field label={c("f.part")} className="mt-3">
            <PSelect
              value={part}
              onValueChange={(v) => setPart(v ?? "")}
              options={pick(itemOpts(c, options), c("ph.all_parts"))}
              aria-label={`${c("f.part")} (${c("sec.open_reqs")})`}
            />
          </Field>
        }
        placeholder={c("ph.search_req")}
        rows={groups}
        columns={[
          [c("col.work_style"), (g) => g.group],
          [c("col.parts"), (g) => g.parts, true],
          [c("col.open_pcs"), (g) => qtyFmt.format(g.open), true],
        ]}
        action={(g) => (
          <Button
            variant="outline"
            size="sm"
            onClick={() => include(g)}
            disabled={!open}
            aria-label={`${c("btn.include")} ${g.group}`}
          >
            {c("btn.include")}
          </Button>
        )}
      />
      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <FormHead
            c={c}
            title={d.doc ? d.doc.challan_no : c(`tabs.challan_${kind}_entry`)}
            hint={c("hint.challan_form")}
            doc={d.doc}
            status={d.doc?.status}
            group="challan_status"
          />
          <Locked d={d} idKey="challan_no" group="challan_status" />
          <Errors errors={d.errors} />
          <Existing
            d={d}
            idKey="challan_no"
            docs={options.docs}
            label="f.existing_challan"
            ph="ph.new_challan"
          />
          <h3 className="mt-5 text-sm font-semibold">
            {c("sec.challan_info")}
          </h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <Field label={c("f.challan_no")} hint={c("hint.issued_on_save")}>
              <Input
                value={d.doc?.challan_no ?? ""}
                placeholder={c("ph.auto")}
                disabled
                aria-label={c("f.challan_no")}
              />
            </Field>
            <Field label={c("f.challan_type")} required>
              <PSelect
                value={head.challan_type}
                onValueChange={(v) => setH({ challan_type: v ?? "" })}
                options={pick(
                  listOpts(c, options.challan_types, "challan_type"),
                  c("ph.select_item"),
                )}
                disabled={!open}
                aria-label={c("f.challan_type")}
              />
            </Field>
            <Field label={c("f.challan_date")} required>
              <Input
                type="date"
                value={head.challan_date}
                onChange={(e) => setH({ challan_date: e.target.value })}
                disabled={!open}
                aria-label={c("f.challan_date")}
              />
            </Field>
            <Field label={c("f.gate_pass")}>
              <Input
                value={head.gate_pass_no}
                onChange={(e) => setH({ gate_pass_no: e.target.value })}
                maxLength={40}
                disabled={!open}
                aria-label={c("f.gate_pass")}
              />
            </Field>
            <Field label={c("f.vehicle")}>
              <Input
                value={head.vehicle_no}
                onChange={(e) => setH({ vehicle_no: e.target.value })}
                maxLength={40}
                disabled={!open}
                aria-label={c("f.vehicle")}
              />
            </Field>
          </div>
          <h3 className="mt-5 text-sm font-semibold">{c("sec.from")}</h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <Field label={c("f.from_floor")} hint={c("hint.from_floor")}>
              <PSelect
                value={head.from_floor_id}
                onValueChange={(v) => setH({ from_floor_id: v ?? "" })}
                options={pick(options.floors, c("ph.floor"))}
                disabled={!open}
                aria-label={c("f.from_floor")}
              />
            </Field>
          </div>
          <h3 className="mt-5 text-sm font-semibold">{c("sec.to")}</h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <Field label={c("f.company")} required>
              <PSelect
                value={options.company.value}
                options={[options.company]}
                disabled
                aria-label={c("f.company")}
              />
            </Field>
            <Field label={c("f.store")}>
              <PSelect
                value={head.store_id}
                onValueChange={(v) => setH({ store_id: v ?? "" })}
                options={pick(options.stores, c("ph.store"))}
                disabled={!open}
                aria-label={c("f.store")}
              />
            </Field>
          </div>
          <Field label={c("f.remarks")} className="mt-4">
            <Textarea
              rows={2}
              value={head.remarks}
              onChange={(e) => setH({ remarks: e.target.value })}
              maxLength={2000}
              disabled={!open}
              aria-label={c("f.remarks")}
            />
          </Field>
        </section>
        <GridCard
          c={c}
          title={c("sec.challan_details")}
          hint={c("hint.challan_lines")}
          button={c("btn.add_part")}
          onAdd={() => d.add()}
          open={open}
          empty={c("empty.challan_lines")}
          count={lines.length}
        >
          <table className="w-full min-w-[1700px] text-sm">
            <GridHead
              c={c}
              cols={[
                ["requirement"],
                ["item"],
                ["description"],
                ["colour"],
                ["size"],
                ["style_order"],
                ["qty_pcs", true],
                ["uom"],
                ["remarks"],
                ["program_part"],
                [""],
              ]}
            />
            <tbody>
              {lines.map((l, i) => {
                const n = i + 1;
                return (
                  <tr key={l.key} className="border-t border-border">
                    <LineSelect
                      value={l.req_no}
                      onChange={(v) => pickReq(l, v)}
                      options={reqOpts}
                      label={`${c("col.requirement")} ${n}`}
                      disabled={!open}
                      width="min-w-[240px]"
                    />
                    <LineSelect
                      value={l.item}
                      onChange={(v) => setL(l.key, { item: v })}
                      options={itemOpts(c, options)}
                      label={`${c("col.item")} ${n}`}
                      disabled={!open}
                      width="min-w-[120px]"
                    />
                    <LineText
                      value={l.description}
                      onChange={(v) => setL(l.key, { description: v })}
                      label={`${c("col.description")} ${n}`}
                      disabled={!open}
                      width="w-44"
                    />
                    <LineSelect
                      value={l.colour_id}
                      onChange={(v) => setL(l.key, { colour_id: v })}
                      options={options.colours}
                      label={`${c("col.colour")} ${n}`}
                      disabled={!open}
                    />
                    <LineText
                      value={l.size_desc}
                      onChange={(v) => setL(l.key, { size_desc: v })}
                      label={`${c("col.size")} ${n}`}
                      disabled={!open}
                      max={80}
                      width="w-20"
                    />
                    <LineSelect
                      value={l.order_id}
                      onChange={(v) => setL(l.key, { order_id: v })}
                      options={options.orders}
                      label={`${c("col.style_order")} ${n}`}
                      disabled={!open}
                      width="min-w-[190px]"
                    />
                    <LineNum
                      value={l.qty}
                      onChange={(v) => setL(l.key, { qty: v })}
                      label={`${c("col.qty_pcs")} ${n}`}
                      disabled={!open}
                    />
                    <LineSelect
                      value={l.uom}
                      onChange={(v) => setL(l.key, { uom: v })}
                      options={options.uoms}
                      label={`${c("col.uom")} ${n}`}
                      disabled={!open}
                      width="min-w-[90px]"
                    />
                    <LineText
                      value={l.remarks}
                      onChange={(v) => setL(l.key, { remarks: v })}
                      label={`${c("col.remarks")} ${n}`}
                      disabled={!open}
                      max={300}
                    />
                    <LineSelect
                      value={l.program_line_id}
                      onChange={(v) => setL(l.key, { program_line_id: v })}
                      options={partOpts}
                      label={`${c("col.program_part")} ${n}`}
                      disabled={!open || head.challan_type !== "SC_RECEIVE"}
                      width="min-w-[260px]"
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
                <td className="px-2 py-2" colSpan={6}>
                  {c("total_lines", { n: lines.length })}
                </td>
                <td className="px-2 py-2 text-right tabular-nums">
                  {qtyFmt.format(sum(lines, (l) => num(l.qty)))}
                </td>
                <td colSpan={4} />
              </tr>
            </tfoot>
          </table>
        </GridCard>
        <FormFoot
          c={c}
          hint={c("hint.challan_foot")}
          back={BASE[kind]}
          onClear={() => d.show(null)}
          onSave={save}
          saveLabel={c("btn.save_challan")}
          pending={d.pending}
          open={open}
        />
      </div>
    </div>
  );
}

export {
  BASE,
  enc,
  numIn,
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
  RadioField,
  ReqList,
  ReqForm,
  ProdDesk,
  ProgList,
  ProgEntry,
  YarnList,
  YarnEntry,
  YarnIssue,
  ChallanList,
  ChallanEntry,
};
