"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  Ban,
  BadgeCheck,
  Calculator,
  CheckCheck,
  CheckSquare,
  Lock,
  Pencil,
  RotateCcw,
  Save,
  Search,
  Send,
  ShieldCheck,
  Truck,
  Undo2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { printDocument } from "@/lib/print-document";
import { Input } from "@/components/ui/input";
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
import { pick } from "./prod-yd";
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
} from "./prod-cc";
import {
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
  PrintButton,
} from "./needle";

/*
 * Knit Production → Oil Store (AnnotechERP 15/10): the knitting store's oil
 * book — Knit Machine Oil Indent, Machine Oil Opening Balance, Daily Machine
 * Oil Consumption, Machine Oil Receipt and its Return Register (Oil Loan
 * Opening Balance is the needle screen on the oil book). Items, categories
 * and stores are the General Store's. A paper travels by its number;
 * nothing reloads.
 */

const OIL = {
  req: "/knit-production/requisition-for-knitting-mc-oil",
  opening: "/knit-production/mc-oil-opening-blance",
  cons: "/knit-production/daily-macine-wise-oil-consumption",
  receipt: "/knit-production/mc-oil-receive",
  ret: "/knit-production/mc-oil-receive-return-list",
};

const yesNo = (c, v) => c(v ? "yes" : "no");
const YN = (c) => [
  { value: "Y", label: c("yes") },
  { value: "N", label: c("no") },
];
const ynMatch = (want, v) => !want || (want === "Y") === Boolean(v);

/** A list row's step: shown while `when` holds, labelled by its own button word. */
function makeOilStep(c, canManage, move, pending, idKey, act) {
  return function step(r, when, action, icon, danger = false) {
    return canManage && when ? (
      <MoveButton
        icon={icon}
        label={c(`btn.oil_${action}`)}
        no={r[idKey]}
        onClick={() => move(r[idKey], action, act)}
        disabled={pending}
        danger={danger}
      />
    ) : null;
  };
}

/* ---------------- prints ---------------- */

function printOilReq(c, d) {
  printDocument({
    title: c("p.oil_req"),
    docNo: d.req_no,
    orientation: "landscape",
    meta: [
      [c("f.req_date"), dayFmt(d.req_date)],
      [c("f.req_type"), d.req_type ? c(`req_type.${d.req_type}`) : null],
      [c("f.company"), d.company],
      [c("f.office"), d.office],
      [c("f.department"), d.department],
      [c("f.from_store"), d.from_store],
      [c("f.to_store"), d.to_store],
      [c("f.reason"), d.reason],
      [c("f.status"), { v: c(`oil_req_status.${d.status}`), tone: "info" }],
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
      c("p.received_by"),
    ],
  });
}

function printOilOpening(c, o) {
  printDocument({
    title: c("p.oil_opening"),
    docNo: `${o.store_code ?? ""} · ${o.item_code ?? ""}`,
    meta: [
      [c("f.company"), o.company],
      [c("f.store"), o.store],
      [c("f.ob_date"), dayFmt(o.ob_date)],
      [c("f.finalized"), yesNo(c, o.finalized)],
    ],
    metaColumns: 4,
    sections: [
      linesSection(
        c,
        c("sec.oil_opening_line"),
        [o],
        [
          [c("col.item"), (l) => ({ v: l.item, bold: true })],
          [c("col.uom"), (l) => l.uom],
          [c("col.opening_qty"), (l) => q3(l.qty), true],
          [c("col.unit_price"), (l) => moneyFmt.format(l.unit_price), true],
          [c("col.currency"), (l) => l.currency],
          [c("col.conv_rate"), (l) => l.conv_rate, true],
          [c("col.cost_price"), (l) => moneyFmt.format(l.cost_price), true],
          [c("col.value"), (l) => moneyFmt.format(l.value), true],
        ],
      ),
    ],
    notes: [o.remarks],
    signatures: [c("p.store_in_charge"), c("p.checked"), c("p.accounts")],
  });
}

function printOilCons(c, d) {
  printDocument({
    title: c("p.oil_cons"),
    docNo: d.cons_no,
    orientation: "landscape",
    meta: [
      [c("f.cons_date"), dayFmt(d.cons_date)],
      [c("f.shift"), d.shift],
      [c("f.company"), d.company],
      [c("f.office"), d.office],
      [c("f.store"), d.store],
      [c("f.issue_by"), d.issuer],
      [c("f.status"), { v: c(`cons_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 4,
    sections: [
      linesSection(
        c,
        c("sec.machine_list"),
        d.lines,
        [
          [c("col.machine"), (l) => ({ v: l.machine, bold: true })],
          [c("col.item"), (l) => l.item],
          [c("col.uom"), (l) => l.uom],
          [c("col.qty"), (l) => q3(l.qty), true],
          [c("col.running_hrs"), (l) => qtyFmt.format(l.running_hrs), true],
          [c("col.cost_price"), (l) => moneyFmt.format(l.cost_price), true],
          [c("col.remarks"), (l) => l.remarks],
        ],
        [
          "",
          "",
          c("f.total"),
          q3(sum(d.lines, (l) => l.qty)),
          qtyFmt.format(sum(d.lines, (l) => l.running_hrs)),
          "",
          "",
        ],
      ),
    ],
    notes: [d.remarks],
    signatures: [c("p.issued_by"), c("p.operator"), c("p.supervisor")],
  });
}

function printOilReceipt(c, d) {
  printDocument({
    title: c("p.oil_receipt"),
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
      [c("f.source"), d.source],
      [c("f.lc_no"), d.lc_no],
      [c("col.is_finalized"), yesNo(c, d.finalized)],
      [c("f.status"), { v: c(`oil_receipt_status.${d.status}`), tone: "info" }],
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
          [c("col.rcv_qty"), (l) => q3(l.received_qty), true],
          [c("col.cond_qty"), (l) => q3(l.cond_qty), true],
          [c("col.rate"), (l) => moneyFmt.format(l.rate), true],
          [c("col.amount"), (l) => moneyFmt.format(l.amount), true],
          [c("col.remarks"), (l) => l.remarks],
        ],
        [
          "",
          c("f.total"),
          q3(sum(d.lines, (l) => l.challan_qty)),
          q3(sum(d.lines, (l) => l.received_qty)),
          q3(sum(d.lines, (l) => l.cond_qty)),
          "",
          moneyFmt.format(sum(d.lines, (l) => l.amount)),
          "",
        ],
      ),
    ],
    notes: [d.remarks],
    signatures: [
      c("p.received_by"),
      c("p.qc"),
      c("p.store_in_charge"),
      c("p.accounts"),
    ],
  });
}

function printOilReturn(c, d) {
  printDocument({
    title: c("p.oil_return"),
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

/* ---------------- indent ---------------- */

function OilReqList({ options, initialRows, canManage, act }) {
  const c = useNd();
  const flt = useShowFilters({
    no: "",
    from: "",
    to: "",
    fromStore: "",
    toStore: "",
    status: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  // A move can change what is issued (Issue in Full, Re-open): the row takes the paper's sums.
  const [move, pending, startTransition] = useMoves(
    c,
    "oil/reqs",
    "req_no",
    setRows,
    setError,
    (d) =>
      setRows((all) =>
        all.map((r) => {
          if (r.req_no !== d.req_no) return r;
          const issued = sum(d.lines, (l) => l.issue_qty);
          return {
            ...r,
            issued,
            balance: r.required - issued,
            outstanding: issued,
          };
        }),
      ),
  );
  const step = makeOilStep(c, canManage, move, pending, "req_no", act);
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.no || has(r.req_no, o.no)) &&
      inRange(o, r.req_date) &&
      (!o.fromStore || r.from_store_id === o.fromStore) &&
      (!o.toStore || r.to_store_id === o.toStore) &&
      (!o.status || r.status === o.status),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`oil/reqs/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printOilReq(c, res.data);
    });
  }
  const is = (r, ...s) => s.includes(r.status);
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
            placeholder="ORQ-…"
          />
          <Text
            c={c}
            label="f.from"
            type="date"
            value={flt.f.from}
            onChange={(v) => flt.set("from", v)}
          />
          <Text
            c={c}
            label="f.to"
            type="date"
            value={flt.f.to}
            onChange={(v) => flt.set("to", v)}
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
            label="f.status"
            value={flt.f.status}
            onChange={(v) => flt.set("status", v)}
            options={listOpts(c, options.req_statuses, "oil_req_status")}
          />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.oil_req_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(c, "req_no", "req_date", OIL.req, "oil_req_status"),
          { key: "from_store", label: c("col.from") },
          { key: "to_store", label: c("col.to") },
          qtyCol(c, "required", "col.rqd"),
          qtyCol(c, "issued", "col.iss"),
          qtyCol(c, "balance", "col.balance_qty"),
          qtyCol(c, "returned"),
          qtyCol(c, "outstanding"),
        ]}
        exportName="oil-indents"
        addHref={canManage ? `${OIL.req}?tab=entry` : null}
        empty={<NoLines title={c("empty.reqs")} hint={c("empty.hint")} />}
        pending={pending}
        actions={(r) => (
          <>
            {step(r, is(r, "DRAFT"), "submit", Send)}
            {step(r, is(r, "DRAFT"), "submit-verify", CheckCheck)}
            {step(r, is(r, "WAITING"), "verify", ShieldCheck)}
            {step(r, is(r, "VERIFIED", "PARTLY_ISSUED"), "issue-full", Truck)}
            {step(r, is(r, "PARTLY_ISSUED"), "close", Lock)}
            {step(
              r,
              is(r, "VERIFIED", "PARTLY_ISSUED", "ISSUED"),
              "reopen",
              Undo2,
            )}
            {step(
              r,
              is(r, "DRAFT", "WAITING", "VERIFIED"),
              "cancel",
              Ban,
              true,
            )}
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
  "req_type",
  "office_id",
  "department_id",
  "from_store_id",
  "to_store_id",
  "reason",
  "remarks",
];
const REQ_LINE = [
  "id",
  "category_id",
  "item_id",
  "uom",
  "required_qty",
  "issue_qty",
  "rate",
  "remarks",
];

function OilReqEntry({ options, initial, canManage, act }) {
  const c = useNd();
  const [today] = useState(todayIso);
  const d = useDoc({
    c,
    path: "oil/reqs",
    idKey: "req_no",
    initial,
    asHead: (x) => ({
      ...Object.fromEntries(REQ_HEAD.map((k) => [k, str(x?.[k])])),
      req_date: str(x?.req_date) || today,
      req_type: x ? str(x.req_type) : "KNIT_MC_OIL",
    }),
    asLine: (l) =>
      withCategory(options)(
        Object.fromEntries(REQ_LINE.map((k) => [k, str(l[k])])),
      ),
    canManage,
    act,
  });
  const { head, setH, lines, setL, open } = d;
  // Once verified, only Issue Qty may be changed here.
  const issuing =
    canManage &&
    ["VERIFIED", "PARTLY_ISSUED", "ISSUED"].includes(d.doc?.status);
  function save() {
    if (issuing) {
      return d.save({
        lines: lines.map((l) => ({ id: l.id, issue_qty: orNull(l.issue_qty) })),
      });
    }
    d.save({
      ...clean(Object.fromEntries(REQ_HEAD.map((k) => [k, head[k]]))),
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
          title={d.doc ? d.doc.req_no : c("tabs.oil_req_entry")}
          hint={c("hint.oil_req_form")}
          doc={d.doc}
          status={d.doc?.status}
          group="oil_req_status"
        />
        {issuing ? (
          <Alert variant="info" className="mt-3">
            {c("hint.oil_issuing")}
          </Alert>
        ) : (
          <Locked d={d} idKey="req_no" group="oil_req_status" />
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
        <div className="mt-3 grid gap-4 md:grid-cols-3">
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
            label="f.from_store"
            value={head.from_store_id}
            onChange={(v) => setH({ from_store_id: v })}
            options={options.stores}
            ph="ph.store"
            disabled={!open}
            required
            hint={c("hint.oil_from_store")}
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
            hint={c("hint.oil_to_store")}
          />
          <Text
            c={c}
            label="f.reason"
            value={head.reason}
            onChange={(v) => setH({ reason: v })}
            disabled={!open}
            max={300}
            placeholder={c("ph.oil_reason")}
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
        hint={c("hint.oil_req_lines")}
        button={c("btn.add_item")}
        onAdd={() => d.add()}
        open={open}
        empty={c("empty.oil_req_lines")}
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
          <Status c={c} foot={c("hint.oil_req_status")} />
        </div>
      </GridCard>
      <FormFoot
        c={c}
        hint={c("hint.oil_req_foot")}
        back={OIL.req}
        onClear={() => d.show(null)}
        onSave={save}
        saveLabel={c("btn.save_req")}
        pending={d.pending}
        open={open || issuing}
      />
    </div>
  );
}

/* ---------------- opening balance ---------------- */

const unitPrice = (r) =>
  `${moneyFmt.format(r.unit_price)}${r.currency ? ` ${r.currency}` : ""}`;

/** Finalize one line or a ticked batch: the rows take the result. */
function useFinalize(c, act, setRows, setError) {
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  function finalize(path, body, after) {
    startTransition(async () => {
      const res = await act(path, "POST", body);
      if (res?.status !== "success") return setError(messagesOf(res));
      const done = new Map(res.data.map((o) => [o.id, o]));
      setRows((all) => all.map((r) => done.get(r.id) ?? r));
      setError(null);
      after?.();
      toastManager.add({
        title: c("done.oil_ob_final", { n: res.data.length }),
        type: "success",
      });
    });
  }
  return [finalize, pending, startTransition];
}

const editHref = (r) =>
  `${OIL.opening}?tab=entry&store=${enc(r.store_code)}&date=${r.ob_date}&item=${enc(r.item_code)}`;

function OilOpeningList({ options, initialRows, canManage, act }) {
  const c = useNd();
  const flt = useShowFilters({ store: "", item: "", final: "" });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [finalize, pending] = useFinalize(c, act, setRows, setError);
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.store || r.store_id === o.store) &&
      (!o.item || has(r.item, o.item)) &&
      ynMatch(o.final, r.finalized),
  );
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      filters={
        <>
          <CompanyField c={c} options={options} />
          <Select
            c={c}
            label="f.store"
            value={flt.f.store}
            onChange={(v) => flt.set("store", v)}
            options={options.stores}
            ph="ph.store"
          />
          <Text
            c={c}
            label="f.item"
            value={flt.f.item}
            onChange={(v) => flt.set("item", v)}
            placeholder={c("ph.search_item")}
          />
          <Select
            c={c}
            label="f.finalized"
            value={flt.f.final}
            onChange={(v) => flt.set("final", v)}
            options={YN(c)}
            ph="ph.all"
          />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.oil_opening_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          { key: "company", label: c("col.company") },
          { key: "store", label: c("col.store") },
          { key: "item", label: c("col.item_short") },
          {
            key: "ob_date",
            label: c("col.date"),
            nowrap: true,
            text: (r) => dayFmt(r.ob_date),
          },
          qtyCol(c, "qty", "col.opening_qty"),
          { key: "uom", label: c("col.uom"), text: (r) => r.uom ?? "—" },
          {
            key: "unit_price",
            label: c("col.unit_price"),
            num: true,
            text: unitPrice,
          },
          {
            key: "cost_price",
            label: c("col.cost_price"),
            num: true,
            text: (r) => moneyFmt.format(r.cost_price),
          },
          {
            key: "value",
            label: c("col.value"),
            num: true,
            text: (r) => moneyFmt.format(r.value),
          },
          {
            key: "finalized",
            label: c("col.finalized"),
            render: (r) => (
              <StatusChip
                status={r.finalized ? "APPROVED" : "DRAFT"}
                label={c(r.finalized ? "finalized" : "ob_open")}
              />
            ),
            text: (r) => yesNo(c, r.finalized),
          },
        ]}
        exportName="oil-opening-balance"
        addHref={canManage ? `${OIL.opening}?tab=entry` : null}
        empty={
          <NoLines
            title={c("empty.oil_openings")}
            hint={c("empty.oil_opening_hint")}
          />
        }
        pending={pending}
        actions={(r) => (
          <>
            {canManage && !r.finalized ? (
              <MoveButton
                icon={BadgeCheck}
                label={c("btn.finalize")}
                no={r.item}
                onClick={() => finalize(`oil/openings/${enc(r.id)}/finalize`)}
                disabled={pending}
              />
            ) : null}
            <PrintButton
              c={c}
              no={r.item}
              onClick={() => printOilOpening(c, r)}
            />
            <Link
              href={editHref(r)}
              className={buttonVariants({ variant: "outline", size: "sm" })}
              aria-label={`${c(canManage && !r.finalized ? "btn.edit" : "btn.view")} ${r.item}`}
            >
              <Pencil />{" "}
              {c(canManage && !r.finalized ? "btn.edit" : "btn.view")}
            </Link>
          </>
        )}
      />
    </ListShell>
  );
}

/** Load the store's opening lines, tick the ones the count agrees with, Finalize All signs them off. */
function OilOpeningSheet({ options, initialRows, canManage, act }) {
  const c = useNd();
  const blank = { store: "", date: "", category: "", item: "", final: "" };
  const [f, setF] = useState(blank);
  const [on, setOn] = useState(null);
  const [rows, setRows] = useState(initialRows);
  const [ticked, setTicked] = useState(() => new Set());
  const [error, setError] = useState(null);
  const [finalize, pending] = useFinalize(c, act, setRows, setError);
  const set = (k, v) => setF((x) => ({ ...x, [k]: v ?? "" }));
  const shown = on
    ? rows.filter(
        (r) =>
          (!on.store || r.store_id === on.store) &&
          (!on.date || r.ob_date === on.date) &&
          (!on.category || r.category_id === on.category) &&
          (!on.item || has(r.item, on.item)) &&
          ynMatch(on.final, r.finalized),
      )
    : [];
  const open = shown.filter((r) => !r.finalized);
  const tick = (id, yes) =>
    setTicked((s) => {
      const n = new Set(s);
      yes ? n.add(id) : n.delete(id);
      return n;
    });
  const mine = [...ticked].filter((id) => open.some((r) => r.id === id));
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold">{c("sec.oil_opening")}</h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {c("hint.oil_sheet")}
        </p>
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <CompanyField c={c} options={options} />
          <Select
            c={c}
            label="f.store"
            value={f.store}
            onChange={(v) => set("store", v)}
            options={options.stores}
            ph="ph.store"
          />
          <Text
            c={c}
            label="f.ob_date"
            type="date"
            value={f.date}
            onChange={(v) => set("date", v)}
          />
          <Select
            c={c}
            label="f.category"
            value={f.category}
            onChange={(v) => set("category", v)}
            options={options.categories}
            ph="ph.category"
          />
          <Text
            c={c}
            label="f.item"
            value={f.item}
            onChange={(v) => set("item", v)}
            placeholder={c("ph.search_item")}
          />
          <Select
            c={c}
            label="f.finalized"
            value={f.final}
            onChange={(v) => set("final", v)}
            options={YN(c)}
            ph="ph.all"
          />
        </div>
        <div className="mt-4 flex flex-wrap justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => (setF(blank), setOn(null), setTicked(new Set()))}
          >
            <RotateCcw /> {c("btn.reset")}
          </Button>
          {canManage ? (
            <Button
              variant="outline"
              size="sm"
              onClick={() =>
                finalize("oil/openings/finalize", { ids: mine }, () =>
                  setTicked(new Set()),
                )
              }
              disabled={pending || !on}
            >
              <CheckSquare /> {c("btn.finalize_all")}
            </Button>
          ) : null}
          <Button size="sm" onClick={() => setOn(f)}>
            <Search /> {c("btn.load")}
          </Button>
        </div>
        {error ? (
          <Alert variant="danger" className="mt-3">
            {error.join(" · ")}
          </Alert>
        ) : null}
      </section>
      <section className={cn(card, "p-5")}>
        <div className="overflow-x-auto rounded-lg border border-border">
          {!on ? (
            <NoLines title={c("empty.load_first")} hint={c("hint.oil_sheet")} />
          ) : shown.length === 0 ? (
            <NoLines
              title={c("empty.oil_sheet")}
              hint={c("empty.oil_opening_hint")}
            />
          ) : (
            <table className="w-full min-w-[1300px] text-sm">
              <GridHead
                c={c}
                cols={[
                  [""],
                  ["company"],
                  ["store"],
                  ["item_short"],
                  ["date"],
                  ["opening_qty", true],
                  ["uom"],
                  ["unit_price", true],
                  ["currency"],
                  ["conv_rate", true],
                  ["cost_price", true],
                  ["value", true],
                  ["finalized"],
                ]}
              />
              <tbody>
                {shown.map((r) => (
                  <tr key={r.id} className="border-t border-border">
                    <Cell>
                      <input
                        type="checkbox"
                        checked={ticked.has(r.id)}
                        onChange={(e) => tick(r.id, e.target.checked)}
                        disabled={!canManage || r.finalized || pending}
                        className="size-4 accent-brand"
                        aria-label={`${c("btn.tick")} ${r.item}`}
                      />
                    </Cell>
                    <Cell>{r.company}</Cell>
                    <Cell>{r.store}</Cell>
                    <Cell className="font-medium">{r.item}</Cell>
                    <Cell className="whitespace-nowrap">
                      {dayFmt(r.ob_date)}
                    </Cell>
                    <Cell className="text-right tabular-nums">{q3(r.qty)}</Cell>
                    <Cell>{r.uom ?? "—"}</Cell>
                    <Cell className="text-right tabular-nums">
                      {moneyFmt.format(r.unit_price)}
                    </Cell>
                    <Cell>{r.currency ?? "—"}</Cell>
                    <Cell className="text-right tabular-nums">
                      {r.conv_rate}
                    </Cell>
                    <Cell className="text-right tabular-nums">
                      {moneyFmt.format(r.cost_price)}
                    </Cell>
                    <Cell className="text-right tabular-nums">
                      {moneyFmt.format(r.value)}
                    </Cell>
                    <Cell>{yesNo(c, r.finalized)}</Cell>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
        {on ? (
          <p className="mt-3 text-[13px] text-foreground-muted">
            {c("oil_sheet_foot", {
              n: shown.length,
              qty: q3(sum(shown, (r) => r.qty)),
              value: moneyFmt.format(sum(shown, (r) => r.value)),
              open: open.length,
              ticked: mine.length,
            })}
          </p>
        ) : null}
      </section>
    </div>
  );
}

function OilOpeningForm({ options, initial, canManage, act }) {
  const c = useNd();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const blank = (o) => ({
    store_id: str(o?.store_id),
    ob_date: str(o?.ob_date) || today,
    category_id: str(o?.category_id),
    item_id: str(o?.item_id),
    uom: str(o?.uom),
    qty: str(o?.qty),
    unit_price: str(o?.unit_price),
    currency_id: str(o?.currency_id),
    conv_rate: o ? str(o.conv_rate) : "1",
    remarks: str(o?.remarks),
  });
  const [row, setRow] = useState(initial);
  const [h, setHead] = useState(() => blank(initial));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && !row?.finalized;
  const setH = (patch) => setHead((x) => ({ ...x, ...patch }));
  function clear() {
    setRow(null);
    setHead(blank(null));
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }
  function save() {
    startTransition(async () => {
      const { category_id: _c, ...rest } = h;
      const res = await act(
        row ? `oil/openings/${enc(row.id)}` : "oil/openings",
        row ? "PUT" : "POST",
        {
          ...clean(rest),
          qty: orNull(h.qty),
          unit_price: orNull(h.unit_price),
          conv_rate: orNull(h.conv_rate),
        },
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      toastManager.add({ title: c("done.oil_ob_saved"), type: "success" });
      clear();
    });
  }
  const it = itemOf(options, h.item_id);
  const cost = num(h.unit_price) * (num(h.conv_rate) || 0);
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={c("sec.oil_ob_line")}
          hint={c("hint.oil_ob_form")}
          doc={null}
        />
        {row && !open && canManage ? (
          <Alert variant="info" className="mt-3">
            {c("hint.oil_ob_final")}
          </Alert>
        ) : null}
        <Errors errors={errors} />
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <CompanyField c={c} options={options} />
          <Select
            c={c}
            label="f.store"
            value={h.store_id}
            onChange={(v) => setH({ store_id: v })}
            options={options.stores}
            ph="ph.store"
            disabled={!open}
            required
          />
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
            label="f.category"
            value={h.category_id || str(it?.category_id)}
            onChange={(v) => setH({ category_id: v, item_id: "" })}
            options={options.categories}
            ph="ph.category"
            disabled={!open}
            required
            hint={c("hint.oil_ob_category")}
          />
          <Select
            c={c}
            label="f.item"
            value={h.item_id}
            onChange={(v) => {
              const x = itemOf(options, v);
              setH({
                item_id: v,
                uom: x?.uom || h.uom,
                unit_price: h.unit_price || (x?.price ? String(x.price) : ""),
              });
            }}
            options={itemsIn(options, h.category_id || it?.category_id)}
            ph="ph.item"
            disabled={!open}
            required
          />
          <Text
            c={c}
            label="f.opening_qty"
            type="number"
            value={h.qty}
            onChange={(v) => setH({ qty: v })}
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
            label="f.unit_price"
            type="number"
            value={h.unit_price}
            onChange={(v) => setH({ unit_price: v })}
            disabled={!open}
            hint={c("hint.oil_unit_price")}
          />
          <Select
            c={c}
            label="f.currency"
            value={h.currency_id}
            onChange={(v) => {
              const cur = options.currencies.find((x) => x.value === v);
              setH({
                currency_id: v,
                conv_rate: cur?.rate ? String(cur.rate) : h.conv_rate,
              });
            }}
            options={options.currencies}
            ph="ph.currency"
            disabled={!open}
          />
          <Text
            c={c}
            label="f.conv_rate"
            type="number"
            value={h.conv_rate}
            onChange={(v) => setH({ conv_rate: v })}
            disabled={!open}
            hint={c("hint.oil_conv_rate")}
          />
          <Field label={c("f.cost_price")} hint={c("hint.oil_cost_price")}>
            <Input
              value={moneyFmt.format(cost)}
              placeholder={c("ph.cost_price")}
              disabled
              aria-label={c("f.cost_price")}
            />
          </Field>
        </div>
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

/* ---------------- daily consumption ---------------- */

function OilConsList({ options, initialRows, canManage, act }) {
  const c = useNd();
  const flt = useShowFilters({
    no: "",
    on: "",
    from: "",
    to: "",
    store: "",
    office: "",
    shift: "",
    final: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "oil/consumptions",
    "cons_no",
    setRows,
    setError,
  );
  const step = makeOilStep(c, canManage, move, pending, "cons_no", act);
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.no || has(r.cons_no, o.no)) &&
      (!o.on || r.cons_date === o.on) &&
      inRange(o, r.cons_date) &&
      (!o.store || r.store_id === o.store) &&
      (!o.office || r.office_id === o.office) &&
      (!o.shift || r.shift_id === o.shift) &&
      ynMatch(o.final, r.status === "FINALIZED"),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`oil/consumptions/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printOilCons(c, res.data);
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
            label="f.cons_no"
            value={flt.f.no}
            onChange={(v) => flt.set("no", v)}
            placeholder="OCS-…"
          />
          <Text
            c={c}
            label="f.cons_date"
            type="date"
            value={flt.f.on}
            onChange={(v) => flt.set("on", v)}
          />
          <Text
            c={c}
            label="f.from"
            type="date"
            value={flt.f.from}
            onChange={(v) => flt.set("from", v)}
          />
          <Text
            c={c}
            label="f.to"
            type="date"
            value={flt.f.to}
            onChange={(v) => flt.set("to", v)}
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
            ph="ph.all"
          />
          <Select
            c={c}
            label="f.is_finalized"
            value={flt.f.final}
            onChange={(v) => flt.set("final", v)}
            options={YN(c)}
            ph="ph.all"
          />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.oil_cons_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(c, "cons_no", "cons_date", OIL.cons, "cons_status"),
          { key: "shift", label: c("col.shift"), text: (r) => r.shift ?? "—" },
          { key: "store", label: c("col.store") },
          {
            key: "office",
            label: c("col.office"),
            text: (r) => r.office ?? "—",
          },
          {
            key: "issued_by",
            label: c("col.issue_by"),
            text: (r) => r.issued_by ?? "—",
          },
          { key: "lines", label: c("col.machines"), num: true },
          qtyCol(c, "qty"),
          {
            key: "running_hrs",
            label: c("col.running_hrs"),
            num: true,
            text: (r) => qtyFmt.format(r.running_hrs),
          },
          {
            key: "value",
            label: c("col.value"),
            num: true,
            text: (r) => moneyFmt.format(r.value),
          },
        ]}
        exportName="oil-consumption"
        addHref={canManage ? `${OIL.cons}?tab=entry` : null}
        empty={
          <NoLines
            title={c("empty.oil_cons")}
            hint={c("empty.oil_cons_hint")}
          />
        }
        pending={pending}
        actions={(r) => (
          <>
            {step(r, r.status === "DRAFT", "finalize", BadgeCheck)}
            <PrintButton
              c={c}
              no={r.cons_no}
              onClick={() => print(r.cons_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

const CONS_HEAD = [
  "cons_date",
  "shift_id",
  "office_id",
  "store_id",
  "issued_by",
  "remarks",
];
const CONS_LINE = [
  "machine_id",
  "category_id",
  "item_id",
  "uom",
  "qty",
  "running_hrs",
  "cost_price",
  "remarks",
];

function OilConsEntry({ options, initial, canManage, act }) {
  const c = useNd();
  const [today] = useState(todayIso);
  const d = useDoc({
    c,
    path: "oil/consumptions",
    idKey: "cons_no",
    initial,
    asHead: (x) => ({
      ...Object.fromEntries(CONS_HEAD.map((k) => [k, str(x?.[k])])),
      cons_date: str(x?.cons_date) || today,
    }),
    asLine: (l) =>
      withCategory(options)(
        Object.fromEntries(CONS_LINE.map((k) => [k, str(l[k])])),
      ),
    canManage,
    act,
  });
  const { head, setH, open, lines, setL } = d;
  function save() {
    d.save({
      ...clean(Object.fromEntries(CONS_HEAD.map((k) => [k, head[k]]))),
      lines: lines.map((l) => ({
        machine_id: l.machine_id || null,
        item_id: l.item_id || null,
        uom: l.uom || null,
        qty: orNull(l.qty),
        running_hrs: orNull(l.running_hrs),
        cost_price: orNull(l.cost_price),
        remarks: l.remarks || null,
      })),
    });
  }
  const machines = machinesOf(options, head.office_id);
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={d.doc ? d.doc.cons_no : c("tabs.oil_cons_entry")}
          hint={c("hint.oil_cons_form")}
          doc={d.doc}
          status={d.doc?.status}
          group="cons_status"
        />
        <Locked d={d} idKey="cons_no" group="cons_status" />
        <Errors errors={d.errors} />
        <Existing
          d={d}
          idKey="cons_no"
          docs={options.docs}
          label="f.existing_cons"
          ph="ph.new_cons"
        />
        <h3 className="mt-5 text-sm font-semibold">{c("sec.req_info")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <NoField c={c} label="f.cons_no" value={d.doc?.cons_no} />
          <Text
            c={c}
            label="f.cons_date"
            type="date"
            value={head.cons_date}
            onChange={(v) => setH({ cons_date: v })}
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
            label="f.store"
            value={head.store_id}
            onChange={(v) => setH({ store_id: v })}
            options={options.stores}
            ph="ph.store"
            disabled={!open}
            required
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.issued")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Select
            c={c}
            label="f.issue_by"
            value={head.issued_by}
            onChange={(v) => setH({ issued_by: v })}
            options={options.employees}
            disabled={!open}
            required
            hint={c("hint.oil_issue_by")}
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
        title={c("sec.machine_list")}
        hint={c("hint.oil_cons_lines")}
        button={c("btn.add_machine")}
        onAdd={() => d.add()}
        open={open}
        empty={c("empty.oil_cons_lines")}
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
              ["qty", true],
              ["running_hrs", true],
              ["cost_price", true],
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
                    width="min-w-[130px]"
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
                    label={`${c("col.qty")} ${n}`}
                    disabled={!open}
                  />
                  <LineNum
                    value={l.running_hrs}
                    onChange={(v) => setL(l.key, { running_hrs: v })}
                    label={`${c("col.running_hrs")} ${n}`}
                    disabled={!open}
                  />
                  <LineNum
                    value={l.cost_price}
                    onChange={(v) => setL(l.key, { cost_price: v })}
                    label={`${c("col.cost_price")} ${n}`}
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
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.qty))) })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {c("ttl_hrs", {
                  hrs: qtyFmt.format(sum(lines, (l) => num(l.running_hrs))),
                })}
              </td>
              <td colSpan={3} />
            </tr>
          </tfoot>
        </table>
        <div className="px-3 pb-3">
          <Status c={c} foot={c("hint.oil_cons_status")} />
        </div>
      </GridCard>
      <FormFoot
        c={c}
        hint={c("hint.oil_cons_foot")}
        back={OIL.cons}
        onClear={() => d.show(null)}
        onSave={save}
        saveLabel={c("btn.save_cons")}
        pending={d.pending}
        open={open}
      />
    </div>
  );
}

/* ---------------- receipts ---------------- */

const DESKS = ["DRAFT", "WAIT_QC", "WAITING", "WAIT_ACCOUNTS", "APPROVED"];

function OilReceiptList({ options, initialRows, canManage, act }) {
  const c = useNd();
  const flt = useShowFilters({
    no: "",
    from: "",
    to: "",
    challan: "",
    store: "",
    supplier: "",
    reqType: "",
    final: "",
    acc: "",
    item: "",
    status: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  // Finalize and Accounting Done change the row's flags, not only its status.
  const [move, pending, startTransition] = useMoves(
    c,
    "oil/receipts",
    "mrr_no",
    setRows,
    setError,
    (d) =>
      setRows((all) =>
        all.map((r) =>
          r.mrr_no === d.mrr_no
            ? { ...r, finalized: d.finalized, accounted: d.accounted }
            : r,
        ),
      ),
  );
  const step = makeOilStep(c, canManage, move, pending, "mrr_no", act);
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.no || has(r.mrr_no, o.no)) &&
      inRange(o, r.mrr_date) &&
      (!o.challan || has(r.challan_no, o.challan)) &&
      (!o.store || r.store_id === o.store) &&
      (!o.supplier || r.supplier_id === o.supplier) &&
      (!o.reqType || r.req_type === o.reqType) &&
      ynMatch(o.final, r.finalized) &&
      ynMatch(o.acc, r.accounted) &&
      (!o.item || has(r.items, o.item)) &&
      (!o.status || r.status === o.status),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`oil/receipts/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printOilReceipt(c, res.data);
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
            placeholder="OMR-…"
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
          <Select
            c={c}
            label="f.req_type"
            value={flt.f.reqType}
            onChange={(v) => flt.set("reqType", v)}
            options={listOpts(c, options.req_types, "req_type")}
            ph="ph.req_type"
          />
          <Select
            c={c}
            label="f.with_finalized"
            value={flt.f.final}
            onChange={(v) => flt.set("final", v)}
            options={YN(c)}
            ph="ph.all"
          />
          <Select
            c={c}
            label="f.with_accounting"
            value={flt.f.acc}
            onChange={(v) => flt.set("acc", v)}
            options={YN(c)}
            ph="ph.all"
          />
          <Text
            c={c}
            label="f.item_name"
            value={flt.f.item}
            onChange={(v) => flt.set("item", v)}
            placeholder={c("ph.oil_item_search")}
          />
          <Select
            c={c}
            label="f.status"
            value={flt.f.status}
            onChange={(v) => flt.set("status", v)}
            options={listOpts(
              c,
              options.receipt_statuses,
              "oil_receipt_status",
            )}
          />
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.oil_receipt_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(c, "mrr_no", "mrr_date", OIL.receipt, "oil_receipt_status"),
          {
            key: "finalized",
            label: c("col.is_finalized"),
            text: (r) => yesNo(c, r.finalized),
          },
          {
            key: "accounted",
            label: c("col.is_accounting"),
            text: (r) => yesNo(c, r.accounted),
          },
          {
            key: "challan_no",
            label: c("col.challan_no"),
            text: (r) => r.challan_no ?? "—",
          },
          { key: "supplier", label: c("col.supplier") },
          { key: "store", label: c("col.store") },
          qtyCol(c, "received_qty", "col.rcv_qty"),
          qtyCol(c, "cond_qty"),
          qtyCol(c, "returnable"),
        ]}
        exportName="oil-receipts"
        addHref={canManage ? `${OIL.receipt}?tab=entry` : null}
        empty={
          <NoLines title={c("empty.oil_receipts")} hint={c("empty.hint")} />
        }
        pending={pending}
        actions={(r) => (
          <>
            {step(r, DESKS.includes(r.status), "next", Send)}
            {step(
              r,
              !r.finalized && ["WAITING", "WAIT_ACCOUNTS"].includes(r.status),
              "finalize",
              BadgeCheck,
            )}
            {step(
              r,
              r.finalized &&
                !r.accounted &&
                ["WAIT_ACCOUNTS", "APPROVED"].includes(r.status),
              "accounting",
              Calculator,
            )}
            {step(
              r,
              !r.finalized && DESKS.slice(0, 4).includes(r.status),
              "cancel",
              Ban,
              true,
            )}
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
  "req_type",
  "supplier_id",
  "store_id",
  "challan_no",
  "challan_date",
  "pay_method",
  "source",
  "lc_no",
  "remarks",
];
const REC_LINE = [
  "category_id",
  "item_id",
  "uom",
  "challan_qty",
  "received_qty",
  "cond_qty",
  "rate",
  "remarks",
];

function OilReceiptEntry({ options, initial, canManage, act }) {
  const c = useNd();
  const [today] = useState(todayIso);
  const d = useDoc({
    c,
    path: "oil/receipts",
    idKey: "mrr_no",
    initial,
    asHead: (x) => ({
      ...Object.fromEntries(REC_HEAD.map((k) => [k, str(x?.[k])])),
      mrr_date: str(x?.mrr_date) || today,
      pay_method: x ? str(x.pay_method) : "CREDIT",
      source: x ? str(x.source) : "LOCAL",
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
        challan_qty: orNull(l.challan_qty),
        received_qty: orNull(l.received_qty),
        cond_qty: orNull(l.cond_qty),
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
          title={d.doc ? d.doc.mrr_no : c("tabs.oil_receipt_entry")}
          hint={c("hint.oil_receipt_form")}
          doc={d.doc}
          status={d.doc?.status}
          group="oil_receipt_status"
        />
        <Locked d={d} idKey="mrr_no" group="oil_receipt_status" />
        <Errors errors={d.errors} />
        <Existing
          d={d}
          idKey="mrr_no"
          docs={options.docs}
          label="f.existing_oil_receipt"
          ph="ph.new_oil_receipt"
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
            ph="ph.select"
            disabled={!open}
            hint={c("hint.oil_receipt_req_type")}
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.from")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
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
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.against")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Text
            c={c}
            label="f.challan_no"
            value={head.challan_no}
            onChange={(v) => setH({ challan_no: v })}
            disabled={!open}
            placeholder={c("ph.party_challan_oil")}
          />
          <Text
            c={c}
            label="f.challan_date"
            type="date"
            value={head.challan_date}
            onChange={(v) => setH({ challan_date: v })}
            disabled={!open}
          />
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.terms")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
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
        hint={c("hint.oil_receipt_lines")}
        button={c("btn.add_item")}
        onAdd={() => d.add()}
        open={open}
        empty={c("empty.oil_receipt_lines")}
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
              ["rcv_qty", true],
              ["cond_qty", true],
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
                    value={l.challan_qty}
                    onChange={(v) => setL(l.key, { challan_qty: v })}
                    label={`${c("col.challan_qty")} ${n}`}
                    disabled={!open}
                  />
                  <LineNum
                    value={l.received_qty}
                    onChange={(v) => setL(l.key, { received_qty: v })}
                    label={`${c("col.rcv_qty")} ${n}`}
                    disabled={!open}
                  />
                  <LineNum
                    value={l.cond_qty}
                    onChange={(v) => setL(l.key, { cond_qty: v })}
                    label={`${c("col.cond_qty")} ${n}`}
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
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.challan_qty))) })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.received_qty))) })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.cond_qty))) })}
              </td>
              <td colSpan={3} />
            </tr>
          </tfoot>
        </table>
        <div className="px-3 pb-3">
          <Status c={c} foot={c("hint.oil_receipt_status")} />
        </div>
      </GridCard>
      <FormFoot
        c={c}
        hint={c("hint.oil_receipt_foot")}
        back={OIL.receipt}
        onClear={() => d.show(null)}
        onSave={save}
        saveLabel={c("btn.save_oil_receipt")}
        pending={d.pending}
        open={open}
      />
    </div>
  );
}

/* ---------------- receipt returns ---------------- */

function OilReturnList({ options, initialRows, canManage, act }) {
  const c = useNd();
  const flt = useShowFilters({
    no: "",
    mrr: "",
    type: "",
    supplier: "",
    store: "",
    from: "",
    to: "",
    status: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "oil/returns",
    "challan_no",
    setRows,
    setError,
  );
  const step = makeOilStep(c, canManage, move, pending, "challan_no", act);
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.no || has(r.challan_no, o.no)) &&
      (!o.mrr || has(r.mrr_no, o.mrr)) &&
      (!o.type || r.challan_type === o.type) &&
      (!o.supplier || r.supplier_id === o.supplier) &&
      (!o.store || r.store_id === o.store) &&
      inRange(o, r.challan_date) &&
      (!o.status || r.status === o.status),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`oil/returns/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printOilReturn(c, res.data);
    });
  }
  const is = (r, ...s) => s.includes(r.status);
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
            placeholder="OMR-…"
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
            label="f.supplier"
            value={flt.f.supplier}
            onChange={(v) => flt.set("supplier", v)}
            options={options.parties}
            ph="ph.supplier"
          />
          <Select
            c={c}
            label="f.store"
            value={flt.f.store}
            onChange={(v) => flt.set("store", v)}
            options={options.stores}
            ph="ph.store"
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
        {c("hint.oil_return_list")}
      </p>
      <ScTable
        rows={shown}
        columns={[
          ...docCol(c, "challan_no", "challan_date", OIL.ret, "return_status"),
          {
            key: "finalized",
            label: c("col.is_finalized"),
            text: (r) => yesNo(c, r.status === "FINALIZED"),
          },
          { key: "mrr_no", label: c("col.mrr_no") },
          {
            key: "challan_type",
            label: c("col.return_type"),
            text: (r) => c(`return_type.${r.challan_type}`),
          },
          { key: "supplier", label: c("col.supplier") },
          { key: "store", label: c("col.store") },
          qtyCol(c, "qty", "col.returned"),
        ]}
        exportName="oil-receipt-returns"
        addHref={canManage ? `${OIL.ret}?tab=entry` : null}
        empty={
          <NoLines
            title={c("empty.returns")}
            hint={c("empty.oil_return_hint")}
          />
        }
        pending={pending}
        actions={(r) => (
          <>
            {step(r, is(r, "DRAFT"), "issue", Truck)}
            {step(r, is(r, "ISSUED"), "finalize", BadgeCheck)}
            {step(r, is(r, "ISSUED", "FINALIZED"), "reopen", Undo2)}
            {step(r, is(r, "DRAFT"), "cancel", Ban, true)}
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

function OilReturnEntry({
  options,
  initial,
  initialSource,
  receipts,
  canManage,
  act,
}) {
  const c = useNd();
  const [today] = useState(todayIso);
  const [src, setSrc] = useState(initialSource);
  const d = useDoc({
    c,
    path: "oil/returns",
    idKey: "challan_no",
    initial,
    asHead: (x) => ({
      mrr_no: str(x?.mrr_no),
      challan_no: str(x?.challan_no),
      challan_date: str(x?.challan_date) || today,
      challan_type: x ? str(x.challan_type) : "NON_RETURNABLE",
      gate_pass_no: str(x?.gate_pass_no),
      vehicle_no: str(x?.vehicle_no),
      reason: str(x?.reason),
    }),
    asLine: (l) => ({
      receipt_line_id: str(l.receipt_line_id),
      qty: str(l.qty),
      remarks: str(l.remarks),
    }),
    canManage,
    act,
  });
  const { head, setH, open, lines, setL } = d;
  const isNew = !d.doc;
  function pickMrr(no) {
    setH({ mrr_no: no });
    d.setLines([]);
    setSrc(null);
    if (no)
      act(`oil/receipts/${enc(no)}/source`).then(
        (res) => res?.status === "success" && setSrc(res.data),
      );
  }
  function save() {
    d.save({
      ...clean(head),
      lines: lines.map((l) => ({
        receipt_line_id: l.receipt_line_id || null,
        qty: orNull(l.qty),
        remarks: l.remarks || null,
      })),
    });
  }
  const srcLines = src?.lines ?? [];
  const lineOpts = srcLines.map((l) => ({
    value: l.receipt_line_id,
    label: c("oil_rtn_line", {
      mrr: src.mrr_no,
      item: l.item,
      left: q3(l.left),
    }),
  }));
  const lineOf = (id) => srcLines.find((l) => l.receipt_line_id === id);
  const mrrOpts = receipts.map((r) => ({
    value: r.mrr_no,
    label: `${r.mrr_no} · ${r.supplier} · ${dayFmt(r.mrr_date)}`,
  }));
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={!isNew ? d.doc.challan_no : c("tabs.oil_return_entry")}
          hint={c("hint.oil_return_form")}
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
        <h3 className="mt-5 text-sm font-semibold">
          {c("sec.return_challan")}
        </h3>
        <div className="mt-3 grid gap-4 md:grid-cols-4">
          <Field label={c("f.mrr_no")} required hint={c("hint.oil_return_mrr")}>
            <PSelect
              value={head.mrr_no}
              onValueChange={(v) => pickMrr(v ?? "")}
              options={pick(
                head.mrr_no && !mrrOpts.some((m) => m.value === head.mrr_no)
                  ? [...mrrOpts, { value: head.mrr_no, label: head.mrr_no }]
                  : mrrOpts,
                c("ph.mrr"),
              )}
              disabled={!open || !isNew}
              aria-label={c("f.mrr_no")}
            />
          </Field>
          <Text
            c={c}
            label="f.rtn_challan"
            value={head.challan_no}
            onChange={(v) => setH({ challan_no: v })}
            disabled={!open || !isNew}
            max={32}
            required
            placeholder={c("ph.own_return_challan")}
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
            hint={c("hint.oil_return_type")}
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
          label="f.reason"
          value={head.reason}
          onChange={(v) => setH({ reason: v })}
          disabled={!open}
        />
      </section>
      <GridCard
        c={c}
        title={c("sec.item_list")}
        hint={c("hint.oil_return_lines")}
        button={c("btn.add_line")}
        onAdd={() => d.add()}
        open={open && Boolean(src)}
        empty={c("empty.oil_return_lines")}
        count={lines.length}
      >
        <table className="w-full min-w-[900px] text-sm">
          <GridHead
            c={c}
            cols={[
              ["receipt_line"],
              ["uom"],
              ["return_qty", true],
              ["amount", true],
              ["remarks"],
              [""],
            ]}
          />
          <tbody>
            {lines.map((l, i) => {
              const n = i + 1;
              const s = lineOf(l.receipt_line_id);
              return (
                <tr key={l.key} className="border-t border-border">
                  <LineSelect
                    value={l.receipt_line_id}
                    onChange={(v) =>
                      setL(l.key, {
                        receipt_line_id: v,
                        qty: l.qty || str(lineOf(v)?.left),
                      })
                    }
                    options={lineOpts}
                    label={`${c("col.receipt_line")} ${n}`}
                    disabled={!open}
                    width="min-w-[380px]"
                  />
                  <Cell>{s?.uom ?? "—"}</Cell>
                  <LineNum
                    value={l.qty}
                    onChange={(v) => setL(l.key, { qty: v })}
                    label={`${c("col.return_qty")} ${n}`}
                    disabled={!open}
                  />
                  <Cell className="text-right font-medium tabular-nums">
                    {moneyFmt.format(num(l.qty) * num(s?.rate))}
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
              <td className="px-2 py-2" colSpan={2}>
                {c("total_lines", { n: lines.length })}
              </td>
              <td className="px-2 py-2 text-right tabular-nums">
                {c("ttl", { qty: q3(sum(lines, (l) => num(l.qty))) })}
              </td>
              <td colSpan={3} />
            </tr>
          </tfoot>
        </table>
        <div className="px-3 pb-3">
          <Status c={c} foot={c("hint.oil_return_status")} />
        </div>
      </GridCard>
      <FormFoot
        c={c}
        hint={c("hint.oil_return_foot")}
        back={OIL.ret}
        onClear={() => (d.show(null), setSrc(null))}
        onSave={save}
        saveLabel={c("btn.save_return")}
        pending={d.pending}
        open={open}
      />
    </div>
  );
}

export {
  OilReqList,
  OilReqEntry,
  OilOpeningList,
  OilOpeningSheet,
  OilOpeningForm,
  OilConsList,
  OilConsEntry,
  OilReceiptList,
  OilReceiptEntry,
  OilReturnList,
  OilReturnEntry,
};
