"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { Ban, BadgeCheck, HandCoins, Plus, Printer, Send } from "lucide-react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";
import { printDocument } from "@/lib/print-document";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
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
 * Knit Production → Fabric Store (AnnotechERP 15/05), the sub-contract money
 * papers: In-House / Out-House Sub-Contract Bill and Out-House Yarn Dyeing
 * Bill (one form, its kind naming the programme a charge is booked to), and
 * Out-House Debit Memo. Saving writes a draft; the list submits, approves
 * (and settles a memo). A paper travels by its number; nothing reloads.
 */

const BILLS = {
  IN_HOUSE: { slug: "in-house", base: "/knit-production/sc-in-house-bill" },
  OUT_HOUSE: { slug: "out-house", base: "/knit-production/sc-out-house-bill" },
  YD: { slug: "yd", base: "/knit-production/yd-bill-sc-out-house" },
};
const MEMO_BASE = "/knit-production/debit-memo-sc-out-house";
const enc = encodeURIComponent;
const numIn = { type: "number", min: "0", step: "any" };
let seq = 0;
const lineKey = () => `b${++seq}`;
const has = (v, q) => (v ?? "").toLowerCase().includes(q.toLowerCase());

function useScb() {
  const t = useT("knitting");
  return (k, p) => t(`scb.${k}`, p);
}

const amountOf = (l) => num(l.qty) * num(l.rate);
/** Quantity per unit — kilograms and pieces never add up to one figure. */
const unitText = (byUnit) =>
  Object.entries(byUnit)
    .map(([u, q]) => `${qtyFmt.format(q)} ${u}`)
    .join(" · ") || "—";
const perUnit = (lines) =>
  unitText(
    lines.reduce(
      (a, l) => (l.uom ? { ...a, [l.uom]: (a[l.uom] ?? 0) + num(l.qty) } : a),
      {},
    ),
  );
const sum = (lines, f) => lines.reduce((a, l) => a + f(l), 0);

/* ---------------- prints ---------------- */

function moneyRows(c, title, lines, cols) {
  return {
    title,
    columns: [
      { label: "#", align: "center", width: "4%" },
      ...cols.map(([label]) => ({ label })),
      { label: c("col.qty"), align: "right" },
      { label: c("col.rate"), align: "right" },
      { label: c("col.amount"), align: "right" },
    ],
    rows: lines.map((l, i) => [
      i + 1,
      ...cols.map(([, get]) => get(l) ?? "—"),
      `${qtyFmt.format(l.qty)} ${l.uom}`,
      moneyFmt.format(l.rate),
      { v: moneyFmt.format(l.amount), bold: true },
    ]),
    empty: c("p.no_lines"),
  };
}

const footRow = (width, label, value, bold = true) => [
  ...Array(width).fill(""),
  { v: label, bold },
  { v: value, bold },
];

function printBill(c, d) {
  const cols = [
    [c(`col.program_${d.kind}`), (l) => ({ v: l.program_no, bold: true })],
    [c("col.order"), (l) => l.order],
    [c("col.description"), (l) => l.description],
    [c("col.colour"), (l) => l.colour_name],
    [c("col.shade"), (l) => l.shade],
    [c("col.challan_no"), (l) => l.challan_no],
  ];
  const section = moneyRows(c, c("sec.detail"), d.lines, cols);
  const w = cols.length + 2;
  const cur = d.currency ? ` (${d.currency})` : "";
  section.foot = [
    footRow(w, c("f.gross") + cur, moneyFmt.format(d.gross)),
    footRow(w, c("f.deduction"), `− ${moneyFmt.format(d.deduction)}`, false),
    footRow(w, c("f.vat"), `+ ${moneyFmt.format(d.vat)}`, false),
    footRow(w, c("f.net") + cur, moneyFmt.format(d.net)),
  ];
  printDocument({
    title: c(`p.bill_${d.kind}`),
    docNo: d.bill_no,
    orientation: "landscape",
    meta: [
      [c("f.date"), dayFmt(d.bill_date)],
      [c("f.party"), d.party],
      [c("p.address"), d.party_address],
      [c("f.status"), { v: c(`status.${d.status}`), tone: "info" }],
      [c("f.party_bill"), d.party_bill_no],
      [c("f.ref_bill"), d.ref_bill_no],
      [c("f.rev_descr"), d.rev_descr],
      [c("f.currency"), d.currency ?? c("ph.base_currency")],
    ],
    metaColumns: 4,
    sections: [section],
    notes: [d.remarks],
    signatures: [c("p.prepared"), c("p.checked"), c("p.approved")],
  });
}

function printMemo(c, d) {
  const cols = [
    [c("col.part"), (l) => (l.part ? c(`part.${l.part}`) : null)],
    [c("col.order"), (l) => l.order],
    [c("col.colour"), (l) => l.colour_name],
    [c("col.description"), (l) => ({ v: l.description, bold: true })],
    [c("col.challan_no"), (l) => l.challan_no],
  ];
  const section = moneyRows(c, c("sec.memo_detail"), d.lines, cols);
  section.foot = [
    footRow(
      cols.length + 2,
      c("f.total") + (d.currency ? ` (${d.currency})` : ""),
      moneyFmt.format(d.total),
    ),
  ];
  printDocument({
    title: c("p.memo"),
    docNo: d.memo_no,
    orientation: "landscape",
    meta: [
      [c("f.memo_date"), dayFmt(d.memo_date)],
      [c("f.party"), d.party],
      [c("p.address"), d.party_address],
      [c("f.status"), { v: c(`memo_status.${d.status}`), tone: "info" }],
      [c("f.reason"), d.reason],
      [c("f.currency"), d.currency ?? c("ph.base_currency")],
    ],
    metaColumns: 4,
    sections: [section],
    notes: [d.remarks],
    signatures: [c("p.prepared"), c("p.approved"), c("p.party_ack")],
  });
}

/* ---------------- registers ---------------- */

function moneyCol(c, key, label) {
  return {
    key,
    label: c(label),
    num: true,
    text: (r) => moneyFmt.format(r[key]),
  };
}

function docCol(c, idKey, dateKey, base, group) {
  return [
    {
      key: idKey,
      label: c(`col.${idKey}`),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${base}?tab=entry&ref=${enc(r[idKey])}`}
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
      label: c("col.date"),
      nowrap: true,
      text: (r) => dayFmt(r[dateKey]),
    },
  ];
}

function BillList({ kind, options, initialRows, canManage, act }) {
  const c = useScb();
  const b = BILLS[kind];
  const path = `bills/${b.slug}`;
  const flt = useShowFilters({ status: "", no: "" });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    path,
    "bill_no",
    setRows,
    setError,
    // Submitting a revision marks the bill it replaces Revised.
    (d) =>
      d.ref_bill_no &&
      act(path).then((res) => res?.status === "success" && setRows(res.data)),
  );
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.status || r.status === o.status) &&
      (!o.no || has(r.bill_no, o.no) || has(r.party_bill_no, o.no)),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`${path}/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printBill(c, res.data);
    });
  }
  const columns = [
    ...docCol(c, "bill_no", "bill_date", b.base, "status"),
    { key: "party", label: c("col.party") },
    { key: "party_bill_no", label: c("col.party_bill") },
    { key: "ref_bill_no", label: c("col.ref_bill"), nowrap: true },
    { key: "currency", label: c("col.currency") },
    { key: "lines", label: c("col.lines"), num: true },
    {
      key: "qty",
      label: c("col.qty"),
      num: true,
      text: (r) => unitText(r.qty),
    },
    moneyCol(c, "gross", "col.gross"),
    moneyCol(c, "deduction", "col.deduction"),
    moneyCol(c, "vat", "col.vat"),
    moneyCol(c, "net", "col.net"),
  ];
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <Field label={c("f.status")}>
            <PSelect
              value={flt.f.status}
              onValueChange={(v) => flt.set("status", v)}
              options={pick(
                options.statuses.map((s) => ({
                  value: s,
                  label: c(`status.${s}`),
                })),
                c("ph.select"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <Field label={c("f.bill_no")}>
            <Input
              value={flt.f.no}
              onChange={(e) => flt.set("no", e.target.value)}
              aria-label={c("f.bill_no")}
            />
          </Field>
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.bill_list")}
      </p>
      <ScTable
        rows={shown}
        columns={columns}
        exportName={`sc-bills-${b.slug}`}
        addHref={canManage ? `${b.base}?tab=entry` : null}
        empty={
          <NoLines title={c(`empty.bills_${kind}`)} hint={c("empty.hint")} />
        }
        pending={pending}
        actions={(r) => (
          <>
            {canManage && r.status === "DRAFT" ? (
              <MoveButton
                icon={Send}
                label={c("btn.submit")}
                no={r.bill_no}
                onClick={() => move(r.bill_no, "submit", act)}
                disabled={pending}
              />
            ) : null}
            {canManage && r.status === "SUBMITTED" ? (
              <MoveButton
                icon={BadgeCheck}
                label={c("btn.approve")}
                no={r.bill_no}
                onClick={() => move(r.bill_no, "approve", act)}
                disabled={pending}
              />
            ) : null}
            {canManage && ["DRAFT", "SUBMITTED"].includes(r.status) ? (
              <MoveButton
                icon={Ban}
                label={c("btn.cancel")}
                no={r.bill_no}
                onClick={() => move(r.bill_no, "cancel", act)}
                disabled={pending}
                danger
              />
            ) : null}
            <MoveButton
              icon={Printer}
              label={c("btn.print")}
              no={r.bill_no}
              onClick={() => print(r.bill_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

function MemoList({ options, initialRows, canManage, act }) {
  const c = useScb();
  const flt = useShowFilters({
    status: "",
    party: "",
    no: "",
    from: "",
    to: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "debit-memos",
    "memo_no",
    setRows,
    setError,
  );
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.status || r.status === o.status) &&
      (!o.party || r.party_id === o.party) &&
      (!o.no || has(r.memo_no, o.no)) &&
      (!o.from || r.memo_date >= o.from) &&
      (!o.to || r.memo_date <= o.to),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`debit-memos/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printMemo(c, res.data);
    });
  }
  const step = (r, status, action, icon, danger = false) =>
    canManage && status.includes(r.status) ? (
      <MoveButton
        icon={icon}
        label={c(`btn.${action}`)}
        no={r.memo_no}
        onClick={() => move(r.memo_no, action, act)}
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
          <Field label={c("f.status")}>
            <PSelect
              value={flt.f.status}
              onValueChange={(v) => flt.set("status", v)}
              options={pick(
                options.memo_statuses.map((s) => ({
                  value: s,
                  label: c(`memo_status.${s}`),
                })),
                c("ph.select"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <Field label={c("f.company")}>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={c("f.company")}
            />
          </Field>
          <Field label={c("f.party")}>
            <PSelect
              value={flt.f.party}
              onValueChange={(v) => flt.set("party", v)}
              options={pick(options.suppliers, c("ph.select"))}
              aria-label={c("f.party")}
            />
          </Field>
          <Field label={c("f.memo_no")}>
            <Input
              value={flt.f.no}
              onChange={(e) => flt.set("no", e.target.value)}
              aria-label={c("f.memo_no")}
            />
          </Field>
          {["from", "to"].map((k) => (
            <Field key={k} label={c(`f.${k}`)}>
              <Input
                type="date"
                value={flt.f[k]}
                onChange={(e) => flt.set(k, e.target.value)}
                aria-label={c(`f.${k}`)}
              />
            </Field>
          ))}
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.memo_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(c, "memo_no", "memo_date", MEMO_BASE, "memo_status"),
          { key: "party", label: c("col.party") },
          { key: "reason", label: c("col.reason") },
          { key: "currency", label: c("col.currency") },
          { key: "lines", label: c("col.lines"), num: true },
          {
            key: "qty",
            label: c("col.qty"),
            num: true,
            text: (r) => unitText(r.qty),
          },
          moneyCol(c, "total", "col.total"),
        ]}
        exportName="sc-debit-memos"
        addHref={canManage ? `${MEMO_BASE}?tab=entry` : null}
        empty={<NoLines title={c("empty.memos")} hint={c("empty.hint")} />}
        pending={pending}
        actions={(r) => (
          <>
            {step(r, ["DRAFT"], "submit", Send)}
            {step(r, ["SUBMITTED"], "approve", BadgeCheck)}
            {step(r, ["APPROVED"], "settle", HandCoins)}
            {step(r, ["DRAFT", "SUBMITTED"], "cancel", Ban, true)}
            <MoveButton
              icon={Printer}
              label={c("btn.print")}
              no={r.memo_no}
              onClick={() => print(r.memo_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

/* ---------------- desks ---------------- */

/** A paper's form state: what is opened (by number), head, lines and save. */
function useDesk({
  path,
  idKey,
  dateKey,
  initial,
  HEAD,
  LINE,
  canManage,
  act,
}) {
  const c = useScb();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, str(d?.[k])])),
    [dateKey]: str(d?.[dateKey]) || today,
  });
  const asLine = (l = {}) => ({
    key: lineKey(),
    ...Object.fromEntries(LINE.map((k) => [k, str(l[k])])),
    uom: str(l.uom) || "KG",
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && (!doc || doc.status === "DRAFT");
  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
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
  function save(numbers = []) {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          HEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        ...Object.fromEntries(numbers.map((k) => [k, orNull(head[k])])),
        lines: lines.map((l) => ({
          ...Object.fromEntries(
            LINE.map((k) => [k, l[k] === "" ? null : l[k]]),
          ),
          qty: orNull(l.qty),
          rate: orNull(l.rate),
        })),
      };
      const res = await act(
        doc ? `${path}/${enc(doc[idKey])}` : path,
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
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
    setL: (k, patch) =>
      setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l))),
    add: (l) => setLines((all) => [...all, asLine(l)]),
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

function TextIn({ d, k, label, max = 60, placeholder }) {
  const { c, head, setH, open } = d;
  return (
    <Field label={c(label)}>
      <Input
        value={head[k]}
        onChange={(e) => setH({ [k]: e.target.value })}
        maxLength={max}
        placeholder={placeholder}
        disabled={!open}
        aria-label={c(label)}
      />
    </Field>
  );
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

/** The charge grid both papers share; `first` renders the line's leading cells. */
function ChargeGrid({ d, options, heads, first, button, hint, empty }) {
  const { c, lines, setL, open } = d;
  const amount = sum(lines, amountOf);
  return (
    <section className={cn(card, "p-5")}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-base font-semibold">
            {c(heads === "memo" ? "sec.memo_detail" : "sec.detail")}
          </h3>
          <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
            {hint}
          </p>
        </div>
        {open ? (
          <Button variant="outline" size="sm" onClick={() => d.add()}>
            <Plus /> {button}
          </Button>
        ) : null}
      </div>
      <div className="mt-4 overflow-x-auto rounded-lg border border-border">
        {lines.length ? (
          <table className="w-full min-w-[1400px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  ...first.heads,
                  ["qty", true],
                  ["uom"],
                  ["rate", true],
                  ["amount", true],
                  ["remarks"],
                ].map(([k, right]) => (
                  <th key={k} className={cn(th, right && "text-right")}>
                    {c(`col.${k}`)}
                  </th>
                ))}
                <th className={th} />
              </tr>
            </thead>
            <tbody>
              {lines.map((l, i) => (
                <tr key={l.key} className="border-t border-border">
                  {first.cells(l, i + 1)}
                  <td className="px-2 py-1.5">
                    <Input
                      {...numIn}
                      value={l.qty}
                      onChange={(e) => setL(l.key, { qty: e.target.value })}
                      disabled={!open}
                      className="ml-auto w-24 text-right"
                      aria-label={`${c("col.qty")} ${i + 1}`}
                    />
                  </td>
                  <td className="min-w-[90px] px-2 py-1.5">
                    <PSelect
                      value={l.uom}
                      onValueChange={(v) => setL(l.key, { uom: v ?? "" })}
                      options={options.uoms}
                      disabled={!open}
                      aria-label={`${c("col.uom")} ${i + 1}`}
                    />
                  </td>
                  <td className="px-2 py-1.5">
                    <Input
                      {...numIn}
                      value={l.rate}
                      onChange={(e) => setL(l.key, { rate: e.target.value })}
                      disabled={!open}
                      className="ml-auto w-24 text-right"
                      aria-label={`${c("col.rate")} ${i + 1}`}
                    />
                  </td>
                  <td className="px-2 py-1.5 text-right font-medium tabular-nums">
                    {moneyFmt.format(amountOf(l))}
                  </td>
                  <td className="min-w-[140px] px-2 py-1.5">
                    <Input
                      value={l.remarks}
                      onChange={(e) => setL(l.key, { remarks: e.target.value })}
                      maxLength={300}
                      disabled={!open}
                      aria-label={`${c("col.remarks")} ${i + 1}`}
                    />
                  </td>
                  <RemoveCell
                    c={c}
                    n={i + 1}
                    open={open}
                    onClick={() => d.remove(l.key)}
                  />
                </tr>
              ))}
            </tbody>
            <tfoot className="border-t border-border bg-surface-muted font-semibold">
              <tr>
                <td className="px-2 py-2" colSpan={first.heads.length}>
                  {c("total_lines", { n: lines.length })}
                </td>
                <td className="px-2 py-2 text-right whitespace-nowrap tabular-nums">
                  {perUnit(lines)}
                </td>
                <td colSpan={2} />
                <td className="px-2 py-2 text-right tabular-nums">
                  {moneyFmt.format(amount)}
                </td>
                <td colSpan={2} />
              </tr>
            </tfoot>
          </table>
        ) : (
          <NoLines title={c("empty.lines")} hint={empty} />
        )}
      </div>
    </section>
  );
}

function ColourCell({ d, l, n, options }) {
  const { c, setL, open } = d;
  return (
    <td className="min-w-[160px] px-2 py-1.5">
      <PSelect
        value={l.colour_id}
        onValueChange={(v) =>
          setL(l.key, {
            colour_id: v ?? "",
            colour_name: v ? named(options.colours, v) : l.colour_name,
          })
        }
        options={pick(options.colours, l.colour_name || "—")}
        disabled={!open}
        aria-label={`${c("col.colour")} ${n}`}
      />
    </td>
  );
}

function OrderCell({ d, l, n, options }) {
  const { c, setL, open } = d;
  return (
    <td className="min-w-[190px] px-2 py-1.5">
      <PSelect
        value={l.order_id}
        onValueChange={(v) => setL(l.key, { order_id: v ?? "" })}
        options={pick(options.orders, "—")}
        disabled={!open}
        aria-label={`${c("col.order")} ${n}`}
      />
    </td>
  );
}

function TextCell({ d, l, n, k, label, max = 40, width = "w-28" }) {
  const { c, setL, open } = d;
  return (
    <td className="px-2 py-1.5">
      <Input
        value={l[k]}
        onChange={(e) => setL(l.key, { [k]: e.target.value })}
        maxLength={max}
        disabled={!open}
        className={width}
        aria-label={`${c(label)} ${n}`}
      />
    </td>
  );
}

/* ---------------- the bills ---------------- */

const BILL_HEAD = [
  "bill_date",
  "party_id",
  "party_bill_no",
  "ref_bill_no",
  "rev_descr",
  "currency_id",
  "deduction",
  "vat",
  "remarks",
];
const BILL_LINE = [
  "program_no",
  "order_id",
  "description",
  "colour_id",
  "colour_name",
  "shade",
  "challan_no",
  "qty",
  "uom",
  "rate",
  "remarks",
];

function BillEntry({ kind, options, initial, bills, canManage, act }) {
  const b = BILLS[kind];
  const d = useDesk({
    path: `bills/${b.slug}`,
    idKey: "bill_no",
    dateKey: "bill_date",
    initial,
    HEAD: BILL_HEAD,
    LINE: BILL_LINE,
    canManage,
    act,
  });
  const { c, head, setH, open, lines, setL } = d;
  const programs = options.programs[kind].filter(
    (p) => !head.party_id || p.party_id === head.party_id,
  );
  const progOpts = programs.map((p) => ({
    value: p.value,
    label: c("program_label", {
      no: p.value,
      billed: qtyFmt.format(p.billed),
      planned: qtyFmt.format(p.planned),
    }),
  }));
  // A revision replaces a submitted bill of the same party.
  const refOpts = (bills ?? [])
    .filter(
      (x) =>
        x.status === "SUBMITTED" &&
        x.party_id === head.party_id &&
        x.bill_no !== d.doc?.bill_no,
    )
    .map((x) => ({
      value: x.bill_no,
      label: `${x.bill_no} · ${moneyFmt.format(x.net)}`,
    }));
  if (head.ref_bill_no && !refOpts.some((x) => x.value === head.ref_bill_no))
    refOpts.unshift({ value: head.ref_bill_no, label: head.ref_bill_no });
  function pickProgram(l, no) {
    const p = programs.find((x) => x.value === no);
    setL(
      l.key,
      p
        ? {
            program_no: no,
            order_id: l.order_id || str(p.order_id),
            description: l.description || str(p.description),
            colour_id: l.colour_id || str(p.colour_id),
            colour_name: l.colour_name || str(p.colour_name),
            rate: l.rate || (p.rate ? String(p.rate) : ""),
            uom: p.uom ? p.uom.toUpperCase() : l.uom,
          }
        : { program_no: "" },
    );
  }
  const gross = sum(lines, amountOf);
  const net = gross - num(head.deduction) + num(head.vat);
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={d.doc ? d.doc.bill_no : c(`tabs.bill_${kind}_entry`)}
          hint={c(`hint.bill_form_${kind}`)}
          doc={d.doc}
          status={d.doc?.status}
          group="status"
        />
        <Locked d={d} idKey="bill_no" group="status" />
        {d.doc?.revised_by ? (
          <Alert variant="warning" className="mt-3">
            {c("hint.revised_by", { no: d.doc.revised_by })}
          </Alert>
        ) : null}
        <Field
          label={c("f.existing_bill")}
          hint={c("hint.existing")}
          className="mt-4"
        >
          <PSelect
            value={d.doc?.bill_no ?? ""}
            onValueChange={(v) => d.load(v ?? "")}
            options={pick(options.docs ?? [], c("ph.new_bill"))}
            disabled={d.pending}
            aria-label={c("f.existing_bill")}
          />
        </Field>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.key")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.bill_no")} hint={c("hint.bill_no")}>
            <Input
              value={d.doc?.bill_no ?? ""}
              placeholder={c("ph.auto")}
              disabled
              aria-label={c("f.bill_no")}
            />
          </Field>
          <Field label={c("f.date")} required>
            <Input
              type="date"
              value={head.bill_date}
              onChange={(e) => setH({ bill_date: e.target.value })}
              disabled={!open}
              aria-label={c("f.date")}
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
          <Field label={c("f.party")} required hint={c("hint.party")}>
            <PSelect
              value={head.party_id}
              onValueChange={(v) =>
                setH({ party_id: v ?? "", ref_bill_no: "", rev_descr: "" })
              }
              options={pick(options.suppliers, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.party")}
            />
          </Field>
          <TextIn
            d={d}
            k="party_bill_no"
            label="f.party_bill"
            placeholder={c("ph.party_bill")}
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.revision")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.ref_bill")} hint={c("hint.ref_bill")}>
            <PSelect
              value={head.ref_bill_no}
              onValueChange={(v) => setH({ ref_bill_no: v ?? "" })}
              options={pick(refOpts, c("ph.not_revision"))}
              disabled={!open || !head.party_id}
              aria-label={c("f.ref_bill")}
            />
          </Field>
          <TextIn
            d={d}
            k="rev_descr"
            label="f.rev_descr"
            max={300}
            placeholder={c("ph.rev_descr")}
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.settlement")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.currency")}>
            <PSelect
              value={head.currency_id}
              onValueChange={(v) => setH({ currency_id: v ?? "" })}
              options={pick(options.currencies, c("ph.base_currency"))}
              disabled={!open}
              aria-label={c("f.currency")}
            />
          </Field>
          <Field label={c("f.deduction")} hint={c("hint.deduction")}>
            <Input
              {...numIn}
              value={head.deduction}
              onChange={(e) => setH({ deduction: e.target.value })}
              placeholder="0"
              disabled={!open}
              aria-label={c("f.deduction")}
            />
          </Field>
          <Field label={c("f.vat")}>
            <Input
              {...numIn}
              value={head.vat}
              onChange={(e) => setH({ vat: e.target.value })}
              placeholder="0"
              disabled={!open}
              aria-label={c("f.vat")}
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
      <ChargeGrid
        d={d}
        options={options}
        button={c("btn.add_charge")}
        hint={c("hint.bill_lines")}
        empty={c("empty.bill_lines")}
        first={{
          heads: [
            [`program_${kind}`],
            ["order"],
            ["description"],
            ["colour"],
            ["shade"],
            ["challan_no"],
          ],
          cells: (l, n) => (
            <>
              <td className="min-w-[260px] px-2 py-1.5">
                <PSelect
                  value={l.program_no}
                  onValueChange={(v) => pickProgram(l, v ?? "")}
                  options={pick(progOpts, l.program_no || "—")}
                  disabled={!open || !head.party_id}
                  aria-label={`${c(`col.program_${kind}`)} ${n}`}
                />
              </td>
              <OrderCell d={d} l={l} n={n} options={options} />
              <TextCell
                d={d}
                l={l}
                n={n}
                k="description"
                label="col.description"
                max={200}
                width="w-48"
              />
              <ColourCell d={d} l={l} n={n} options={options} />
              <TextCell
                d={d}
                l={l}
                n={n}
                k="shade"
                label="col.shade"
                width="w-20"
              />
              <TextCell
                d={d}
                l={l}
                n={n}
                k="challan_no"
                label="col.challan_no"
              />
            </>
          ),
        }}
      />
      <section
        className={cn(card, "flex flex-wrap gap-x-6 gap-y-2 p-4 text-sm")}
      >
        {[
          ["total_qty", perUnit(lines)],
          ["f.gross", moneyFmt.format(gross)],
          ["f.deduction", moneyFmt.format(num(head.deduction))],
          ["f.vat", moneyFmt.format(num(head.vat))],
          ["f.net", moneyFmt.format(net)],
        ].map(([k, v]) => (
          <span key={k} className="flex items-center gap-2">
            <span className="text-foreground-muted">{c(k)}</span>
            <span className="rounded-md bg-surface-muted px-2 py-0.5 font-semibold tabular-nums">
              {v}
            </span>
          </span>
        ))}
      </section>
      {d.errors ? <Alert variant="danger">{d.errors.join(" · ")}</Alert> : null}
      <FormFoot
        c={c}
        hint={c("hint.bill_foot")}
        back={b.base}
        onClear={() => d.show(null)}
        onPrint={d.doc ? () => printBill(c, d.doc) : null}
        onSave={() => d.save(["deduction", "vat"])}
        saveLabel={c("btn.save_bill")}
        pending={d.pending}
        open={open}
      />
    </div>
  );
}

/* ---------------- the debit memo ---------------- */

const MEMO_HEAD = ["memo_date", "party_id", "currency_id", "reason", "remarks"];
const MEMO_LINE = [
  "part",
  "order_id",
  "colour_id",
  "colour_name",
  "description",
  "challan_no",
  "qty",
  "uom",
  "rate",
  "remarks",
];

function MemoEntry({ options, initial, canManage, act }) {
  const d = useDesk({
    path: "debit-memos",
    idKey: "memo_no",
    dateKey: "memo_date",
    initial,
    HEAD: MEMO_HEAD,
    LINE: MEMO_LINE,
    canManage,
    act,
  });
  const { c, head, setH, open, lines, setL } = d;
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={d.doc ? d.doc.memo_no : c("tabs.memo_entry")}
          hint={c("hint.memo_form")}
          doc={d.doc}
          status={d.doc?.status}
          group="memo_status"
        />
        <Locked d={d} idKey="memo_no" group="memo_status" />
        <Field
          label={c("f.existing_memo")}
          hint={c("hint.existing")}
          className="mt-4"
        >
          <PSelect
            value={d.doc?.memo_no ?? ""}
            onValueChange={(v) => d.load(v ?? "")}
            options={pick(options.docs ?? [], c("ph.new_memo"))}
            disabled={d.pending}
            aria-label={c("f.existing_memo")}
          />
        </Field>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.memo_info")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.memo_no")} hint={c("hint.memo_no")}>
            <Input
              value={d.doc?.memo_no ?? ""}
              placeholder={c("ph.auto")}
              disabled
              aria-label={c("f.memo_no")}
            />
          </Field>
          <Field label={c("f.memo_date")} required>
            <Input
              type="date"
              value={head.memo_date}
              onChange={(e) => setH({ memo_date: e.target.value })}
              disabled={!open}
              aria-label={c("f.memo_date")}
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
          <Field label={c("f.party")} required hint={c("hint.memo_party")}>
            <PSelect
              value={head.party_id}
              onValueChange={(v) => setH({ party_id: v ?? "" })}
              options={pick(options.suppliers, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.party")}
            />
          </Field>
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.charge")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.currency")}>
            <PSelect
              value={head.currency_id}
              onValueChange={(v) => setH({ currency_id: v ?? "" })}
              options={pick(options.currencies, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.currency")}
            />
          </Field>
        </div>
        <Field label={c("f.reason")} className="mt-4">
          <Input
            value={head.reason}
            onChange={(e) => setH({ reason: e.target.value })}
            maxLength={300}
            placeholder={c("ph.reason")}
            disabled={!open}
            aria-label={c("f.reason")}
          />
        </Field>
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
      <ChargeGrid
        d={d}
        options={options}
        heads="memo"
        button={c("btn.add_line")}
        hint={c("hint.memo_lines")}
        empty={c("empty.memo_lines")}
        first={{
          heads: [
            ["part"],
            ["order"],
            ["colour"],
            ["description"],
            ["challan_no"],
          ],
          cells: (l, n) => (
            <>
              <td className="min-w-[120px] px-2 py-1.5">
                <PSelect
                  value={l.part}
                  onValueChange={(v) => setL(l.key, { part: v ?? "" })}
                  options={pick(
                    options.parts.map((p) => ({
                      value: p,
                      label: c(`part.${p}`),
                    })),
                    "—",
                  )}
                  disabled={!open}
                  aria-label={`${c("col.part")} ${n}`}
                />
              </td>
              <OrderCell d={d} l={l} n={n} options={options} />
              <ColourCell d={d} l={l} n={n} options={options} />
              <TextCell
                d={d}
                l={l}
                n={n}
                k="description"
                label="col.description"
                max={200}
                width="w-56"
              />
              <TextCell
                d={d}
                l={l}
                n={n}
                k="challan_no"
                label="col.challan_no"
              />
            </>
          ),
        }}
      />
      <section
        className={cn(card, "flex flex-wrap gap-x-6 gap-y-2 p-4 text-sm")}
      >
        {[
          ["f.total", moneyFmt.format(sum(lines, amountOf))],
          ["col.qty", perUnit(lines)],
        ].map(([k, v]) => (
          <span key={k} className="flex items-center gap-2">
            <span className="text-foreground-muted">{c(k)}</span>
            <span className="rounded-md bg-surface-muted px-2 py-0.5 font-semibold tabular-nums">
              {v}
            </span>
          </span>
        ))}
      </section>
      {d.errors ? <Alert variant="danger">{d.errors.join(" · ")}</Alert> : null}
      <FormFoot
        c={c}
        hint={c("hint.memo_foot")}
        back={MEMO_BASE}
        onClear={() => d.show(null)}
        onPrint={d.doc ? () => printMemo(c, d.doc) : null}
        onSave={() => d.save()}
        saveLabel={c("btn.save_memo")}
        pending={d.pending}
        open={open}
      />
    </div>
  );
}

export { BillList, BillEntry, MemoList, MemoEntry };
