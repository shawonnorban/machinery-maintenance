"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Ban,
  Check,
  CheckCheck,
  Eraser,
  Plus,
  Printer,
  RotateCcw,
  Save,
  Search,
  Send,
  Trash2,
  X,
} from "lucide-react";
import { cn } from "@/lib/utils";
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
import { useYd, TONE, pick, named, yarnOf, statusOptions } from "./prod-yd";

/*
 * Knit Production → Yarn Store (AnnotechERP 15/03), the dye house's side of
 * a Y/D programme: In-House Dyed Yarn Receipt (its list, where Confirm takes
 * the cones onto the store, and the form beside the programmes still owed),
 * Yarn Dyeing Loss Adjustment (memos approved on their list), the two YD
 * Re-process screens (in-house stock, or yarn recovered from a sub-contract
 * knitter, issued / accepted / rejected on their list), In-House
 * Sub-Contract Yarn Receipt (a party's challan against its programme
 * receipt, accepted onto the store) and the Knitting Y/D Statement. A
 * document opened travels by its number; nothing reloads.
 */

const BASE = {
  receipt: "/knit-production/dyed-yarn-receive-in-house",
  memo: "/knit-production/process-loss-adjustment-yd",
  IN_HOUSE: "/knit-production/yd-reprocess-in-house-to-yd-factory",
  SUB_CONTRACT: "/knit-production/yd-reprocess-sc-knitting-to-yd-factory",
  challan: "/knit-production/yarn-receive-challan-sc-in-house",
};
const SLUG = { IN_HOUSE: "in-house", SUB_CONTRACT: "sc-knitting" };
const enc = encodeURIComponent;
const numIn = { type: "number", min: "0", step: "any" };
const groups = (options, c) =>
  pick(
    options.colour_groups.map((g) => ({ value: g, label: g })),
    c("ph.group"),
  );
let seq = 0;
const lineKey = () => `d${++seq}`;

/** A list's filters: nothing narrows until Show is pressed; Reset clears both. */
function useShowFilters(blank) {
  const [draft, setDraft] = useState(blank);
  const [applied, setApplied] = useState(blank);
  return {
    f: draft,
    on: applied,
    set: (k, v) => setDraft((x) => ({ ...x, [k]: v ?? "" })),
    show: () => setApplied(draft),
    reset: () => (setDraft(blank), setApplied(blank)),
  };
}

const has = (v, q) => (v ?? "").toLowerCase().includes(q.toLowerCase());

/** Status moves from a list row; the row takes the document's new status. */
function useMoves(c, path, idKey, setRows, setError, onDone) {
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  function move(no, action, act) {
    startTransition(async () => {
      const res = await act(`${path}/${enc(no)}/${action}`, "POST");
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows((all) =>
        all.map((r) =>
          r[idKey] === no ? { ...r, status: res.data.status } : r,
        ),
      );
      setError(null);
      onDone?.(res.data);
      toastManager.add({ title: c(`done.${action}`, { no }), type: "success" });
    });
  }
  return [move, pending, startTransition];
}

function ListShell({ c, filters, onShow, onReset, extra, error, children }) {
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-[13px] font-semibold tracking-wide text-foreground-muted uppercase">
          {c("sec.search")}
        </h2>
        <div className="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {filters}
        </div>
        <div className="mt-4 flex flex-wrap items-center justify-end gap-2">
          {extra}
          <Button variant="outline" size="sm" onClick={onReset}>
            <RotateCcw /> {c("btn.reset")}
          </Button>
          <Button size="sm" onClick={onShow}>
            <Search /> {c("btn.show")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>{children}</section>
    </div>
  );
}

function MoveButton({ icon: Icon, label, no, onClick, disabled, danger }) {
  return (
    <Button
      variant="outline"
      size="sm"
      className={danger ? "text-danger" : undefined}
      onClick={onClick}
      disabled={disabled}
      aria-label={`${label} ${no}`}
    >
      <Icon /> {label}
    </Button>
  );
}

/** The programmes (or S/C programmes) a desk works against, beside its form. */
function SideList({ c, title, rows, columns, placeholder, onPick, active }) {
  const PER = 6;
  const [party, setParty] = useState("");
  const [q, setQ] = useState("");
  const [page, setPage] = useState(0);
  const parties = [
    ...new Map(rows.map((r) => [r.party_id, r.party])).entries(),
  ].map(([value, label]) => ({ value, label }));
  const shown = rows.filter(
    (r) =>
      (!party || r.party_id === party) &&
      (!q || has(columns.map(([, cell]) => cell(r)).join(" "), q)),
  );
  const pages = Math.max(1, Math.ceil(shown.length / PER));
  const at = Math.min(page, pages - 1);
  return (
    <section className={cn(card, "min-w-0 p-5")}>
      <h2 className="text-base font-semibold text-foreground">{title}</h2>
      <Field label={c("f.party")} className="mt-3">
        <PSelect
          value={party}
          onValueChange={(v) => (setParty(v ?? ""), setPage(0))}
          options={pick(parties, c("ph.party"))}
          aria-label={`${c("f.party")} (${title})`}
        />
      </Field>
      <div className="relative mt-3">
        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
        <Input
          value={q}
          onChange={(e) => (setQ(e.target.value), setPage(0))}
          placeholder={placeholder}
          className="pl-9"
          aria-label={`${c("btn.show")} ${title}`}
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
            </tr>
          </thead>
          <tbody>
            {shown.length === 0 ? (
              <tr>
                <td
                  colSpan={columns.length}
                  className="px-3 py-8 text-center text-foreground-muted"
                >
                  {c("empty.side")}
                </td>
              </tr>
            ) : (
              shown.slice(at * PER, at * PER + PER).map((r) => (
                <tr
                  key={r.value}
                  onClick={() => onPick(r.value)}
                  className={cn(
                    "cursor-pointer border-t border-border hover:bg-surface-muted",
                    active === r.value && "bg-brand-subtle",
                  )}
                >
                  {columns.map(([label, cell, right]) => (
                    <td
                      key={label}
                      className={cn(
                        "px-2 py-2 whitespace-nowrap",
                        right && "text-right tabular-nums",
                      )}
                    >
                      {cell(r) ?? "—"}
                    </td>
                  ))}
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

function FormHead({ c, title, hint, doc, status, group }) {
  return (
    <div className="flex items-start justify-between gap-3">
      <div>
        <h2 className="text-base font-semibold text-foreground">{title}</h2>
        <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
          {hint}
        </p>
      </div>
      <StatusChip
        status={doc ? TONE[status] : "PLACED"}
        label={doc ? c(`${group}.${status}`) : c("new_badge")}
      />
    </div>
  );
}

function FormFoot({
  c,
  hint,
  back,
  onClear,
  onPrint,
  onSave,
  saveLabel,
  pending,
  open,
}) {
  return (
    <section
      className={cn(
        card,
        "flex flex-wrap items-center justify-between gap-3 p-4",
      )}
    >
      <p className="max-w-2xl text-[13px] text-foreground-muted">{hint}</p>
      <div className="flex flex-wrap gap-2">
        <Link href={back} className={buttonVariants({ variant: "outline" })}>
          <ArrowLeft /> {c("btn.back")}
        </Link>
        <Button variant="outline" onClick={onClear} disabled={pending}>
          <Eraser /> {c("btn.clear")}
        </Button>
        {onPrint ? (
          <Button variant="outline" onClick={onPrint}>
            <Printer /> {c("btn.print")}
          </Button>
        ) : null}
        {open ? (
          <Button onClick={onSave} loading={pending}>
            <Save /> {saveLabel}
          </Button>
        ) : null}
      </div>
    </section>
  );
}

function RemoveCell({ c, n, onClick, open }) {
  return (
    <td className="px-2 py-1.5">
      {open ? (
        <Button
          variant="ghost"
          size="sm"
          className="text-danger"
          onClick={onClick}
          aria-label={`${c("btn.remove")} ${n}`}
        >
          <Trash2 /> {c("btn.remove")}
        </Button>
      ) : null}
    </td>
  );
}

/* ---------------- prints ---------------- */

function printReceipt(c, d) {
  const qty = d.lines.reduce((a, l) => a + num(l.qty), 0);
  const cones = d.lines.reduce((a, l) => a + num(l.cones), 0);
  printDocument({
    title: c("p.receipt"),
    docNo: d.receive_no,
    meta: [
      [c("f.receive_date"), dayFmt(d.receive_date)],
      [c("f.program"), d.program_no],
      [c("f.party"), d.party],
      [c("p.address"), d.party_address],
      [c("f.challan_no"), d.challan_no],
      [c("f.challan_dt"), d.challan_date ? dayFmt(d.challan_date) : null],
      [c("f.working_ref"), d.working_ref],
      [c("f.store"), d.store],
      [c("f.buyer"), d.buyer],
      [c("f.order"), d.order],
      [c("f.status"), { v: c(`receipt_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 3,
    sections: [
      {
        title: c("sec.dyed_lines"),
        columns: [
          { label: "#", align: "center", width: "5%" },
          { label: c("col.shade") },
          { label: c("col.lot"), width: "16%" },
          { label: c("col.qty"), align: "right", width: "14%" },
          { label: c("col.cones"), align: "right", width: "11%" },
          { label: c("col.remarks"), width: "20%" },
        ],
        rows: d.lines.map((l, i) => [
          i + 1,
          { v: l.shade, bold: true },
          l.lot_no,
          `${qtyFmt.format(l.qty)} ${l.uom}`,
          l.cones ?? "—",
          l.remarks,
        ]),
        foot: [
          [
            "",
            { v: c("total"), bold: true },
            "",
            { v: qtyFmt.format(qty), bold: true },
            { v: cones || "—", bold: true },
            "",
          ],
        ],
        empty: c("p.no_lines"),
      },
    ],
    notes: [d.remarks],
    signatures: [c("p.received_by"), c("p.store"), c("p.approved")],
  });
}

function printMemo(c, d) {
  const i = d.lines.reduce((a, l) => a + num(l.issued_qty), 0);
  const r = d.lines.reduce((a, l) => a + num(l.received_qty), 0);
  printDocument({
    title: c("p.memo"),
    docNo: d.memo_no,
    meta: [
      [c("f.date"), dayFmt(d.memo_date)],
      [c("f.program_hash"), d.program_no],
      [c("f.party"), d.party],
      [c("f.buyer"), d.buyer],
      [c("f.order"), d.order],
      [c("f.status"), { v: c(`memo_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 3,
    sections: [
      {
        title: c("sec.loss_lines"),
        columns: [
          { label: "#", align: "center", width: "5%" },
          { label: c("col.shade") },
          { label: c("col.colour_group"), width: "13%" },
          { label: c("col.lot"), width: "12%", nowrap: true },
          { label: c("col.issued"), align: "right" },
          { label: c("col.received"), align: "right" },
          { label: c("col.loss"), align: "right" },
          { label: c("col.loss_pct"), align: "right" },
        ],
        rows: d.lines.map((l, n) => [
          n + 1,
          { v: l.shade, bold: true, sub: l.remarks || undefined },
          l.colour_group,
          l.lot_no,
          qtyFmt.format(l.issued_qty),
          qtyFmt.format(l.received_qty),
          { v: qtyFmt.format(l.loss), bold: true },
          l.loss_pct === null ? "—" : `${Number(l.loss_pct).toFixed(2)}%`,
        ]),
        foot: [
          [
            "",
            { v: c("total"), bold: true },
            "",
            "",
            { v: qtyFmt.format(i), bold: true },
            { v: qtyFmt.format(r), bold: true },
            { v: qtyFmt.format(i - r), bold: true },
            {
              v: i > 0 ? `${((100 * (i - r)) / i).toFixed(2)}%` : "—",
              bold: true,
            },
          ],
        ],
        empty: c("p.no_lines"),
      },
    ],
    notes: [d.remarks],
    signatures: [c("p.prepared"), c("p.store"), c("p.approved")],
  });
}

function printReprocess(c, d) {
  const qty = d.lines.reduce((a, l) => a + num(l.qty), 0);
  printDocument({
    title: c("p.rp"),
    docNo: d.ref_no,
    subtitle: c(
      d.kind === "IN_HOUSE" ? "tabs.rp_ih_entry" : "tabs.rp_sc_entry",
    ),
    meta: [
      [c("f.date"), dayFmt(d.ref_date)],
      [c("f.factory"), d.factory],
      [c("p.address"), d.factory_address],
      ...(d.kind === "SUB_CONTRACT" ? [[c("f.party"), d.party]] : []),
      [c("f.bill_no"), d.bill_no],
      [c("f.challan_hash"), d.challan_no],
      [c("f.chln_dt"), d.challan_date ? dayFmt(d.challan_date) : null],
      [c("f.program"), d.program_no],
      [c("f.store"), d.store],
      [c("f.status"), { v: c(`reprocess_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 3,
    sections: [
      {
        title: c("sec.going_back"),
        columns: [
          { label: "#", align: "center", width: "5%" },
          { label: c("col.yarn") },
          { label: c("col.shade_band") },
          { label: c("col.lot") },
          { label: c("col.qty"), align: "right" },
          { label: c("col.reason") },
          { label: c("col.remarks") },
        ],
        rows: d.lines.map((l, i) => [
          i + 1,
          { v: l.yarn ?? "—", bold: true },
          l.colour_group,
          l.lot_no,
          `${qtyFmt.format(l.qty)} ${l.uom}`,
          l.reason,
          l.remarks,
        ]),
        foot: [
          [
            "",
            { v: c("total"), bold: true },
            "",
            "",
            { v: qtyFmt.format(qty), bold: true },
            "",
            "",
          ],
        ],
        empty: c("p.no_lines"),
      },
    ],
    notes: [d.remarks],
    signatures: [c("p.prepared"), c("p.driver"), c("p.store")],
  });
}

function printChallan(c, d) {
  const qty = d.lines.reduce((a, l) => a + num(l.qty), 0);
  const cones = d.lines.reduce((a, l) => a + num(l.cones), 0);
  printDocument({
    title: c("p.challan"),
    docNo: d.challan_no,
    meta: [
      [c("f.date"), dayFmt(d.challan_date)],
      [c("f.program_plain"), d.program_no],
      [c("f.order_no"), d.order_no],
      [c("f.style_no"), d.style_no],
      [c("f.party"), d.party],
      [c("p.address"), d.party_address],
      [c("f.store"), d.store],
      [c("f.status"), { v: c(`challan_status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 4,
    sections: [
      {
        title: c("sec.yarn_challan"),
        columns: [
          { label: "#", align: "center", width: "5%" },
          { label: c("col.yarn") },
          { label: c("col.colour_group") },
          { label: c("col.brand") },
          { label: c("col.lot") },
          { label: c("col.qty"), align: "right" },
          { label: c("col.cones"), align: "right" },
          { label: c("col.remarks") },
        ],
        rows: d.lines.map((l, i) => [
          i + 1,
          { v: l.yarn ?? "—", bold: true },
          l.colour_group,
          l.brand,
          l.lot_no,
          `${qtyFmt.format(l.qty)} ${l.uom}`,
          l.cones ?? "—",
          l.remarks,
        ]),
        foot: [
          [
            "",
            { v: c("total"), bold: true },
            "",
            "",
            "",
            { v: qtyFmt.format(qty), bold: true },
            { v: cones || "—", bold: true },
            "",
          ],
        ],
        empty: c("p.no_lines"),
      },
    ],
    notes: [d.remarks],
    signatures: [c("p.received_by"), c("p.store"), c("p.approved")],
  });
}

/* ---------------- In-House Dyed Yarn Receipt ---------------- */

function YdReceiptList({ options, initialRows, canManage, act }) {
  const c = useYd();
  const flt = useShowFilters({
    buyer: "",
    order: "",
    party: "",
    ref: "",
    challan: "",
    status: "",
  });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "yd/receipts",
    "receive_no",
    setRows,
    setError,
  );
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.buyer || r.buyer_id === o.buyer) &&
      (!o.order || r.order_id === o.order) &&
      (!o.party || r.supplier_id === o.party) &&
      (!o.ref || has(r.program_no, o.ref)) &&
      (!o.challan || has(r.challan_no, o.challan)) &&
      (!o.status || r.status === o.status),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`yd/receipts/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printReceipt(c, res.data);
    });
  }
  const columns = [
    {
      key: "receive_no",
      label: c("col.receive_no"),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${BASE.receipt}?tab=entry&ref=${enc(r.receive_no)}`}
            className="font-medium text-brand hover:underline"
          >
            {r.receive_no}
          </Link>
          <StatusChip
            status={TONE[r.status]}
            label={c(`receipt_status.${r.status}`)}
          />
        </span>
      ),
    },
    {
      key: "receive_date",
      label: c("col.receive_date"),
      nowrap: true,
      text: (r) => dayFmt(r.receive_date),
    },
    { key: "program_no", label: c("col.program_no"), nowrap: true },
    { key: "party", label: c("col.party") },
    { key: "buyer", label: c("col.buyer") },
    { key: "order", label: c("col.order") },
    { key: "challan_no", label: c("col.challan") },
    { key: "store", label: c("col.store") },
    {
      key: "qty",
      label: c("col.qty"),
      num: true,
      text: (r) => qtyFmt.format(r.qty),
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
          <Field label={c("f.buyer")}>
            <PSelect
              value={flt.f.buyer}
              onValueChange={(v) => flt.set("buyer", v)}
              options={pick(options.buyers, c("ph.select"))}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.style_order")}>
            <PSelect
              value={flt.f.order}
              onValueChange={(v) => flt.set("order", v)}
              options={pick(options.orders, c("ph.select"))}
              aria-label={c("f.style_order")}
            />
          </Field>
          <Field label={c("f.party")}>
            <PSelect
              value={flt.f.party}
              onValueChange={(v) => flt.set("party", v)}
              options={pick(options.suppliers, c("ph.party"))}
              aria-label={c("f.party")}
            />
          </Field>
          <Field label={c("f.program_ref")}>
            <Input
              value={flt.f.ref}
              onChange={(e) => flt.set("ref", e.target.value)}
              aria-label={c("f.program_ref")}
            />
          </Field>
          <Field label={c("f.challan_no")}>
            <Input
              value={flt.f.challan}
              onChange={(e) => flt.set("challan", e.target.value)}
              aria-label={c("f.challan_no")}
            />
          </Field>
          <Field label={c("f.receive_status")}>
            <PSelect
              value={flt.f.status}
              onValueChange={(v) => flt.set("status", v)}
              options={pick(
                statusOptions(c, options.receipt_statuses, "receipt_status"),
                c("ph.select"),
              )}
              aria-label={c("f.receive_status")}
            />
          </Field>
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.receipt_list")}
      </p>
      <ScTable
        rows={shown}
        columns={columns}
        exportName="dyed-yarn-receipts"
        addHref={canManage ? `${BASE.receipt}?tab=entry` : null}
        empty={
          <NoLines title={c("empty.receipts")} hint={c("empty.list_hint")} />
        }
        pending={pending}
        actions={(r) => (
          <>
            {canManage && r.status === "DRAFT" ? (
              <MoveButton
                icon={CheckCheck}
                label={c("btn.confirm")}
                no={r.receive_no}
                onClick={() => move(r.receive_no, "confirm", act)}
                disabled={pending}
              />
            ) : null}
            {canManage && r.status !== "CANCELLED" ? (
              <MoveButton
                icon={Ban}
                label={c("btn.cancel_doc")}
                no={r.receive_no}
                onClick={() => move(r.receive_no, "cancel", act)}
                disabled={pending}
                danger
              />
            ) : null}
            <MoveButton
              icon={Printer}
              label={c("btn.print")}
              no={r.receive_no}
              onClick={() => print(r.receive_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

const shadesOf = (options, programNo) =>
  options.programs.find((p) => p.value === programNo)?.lines ?? [];

function YdReceiptEntry({ options, initial, canManage, act }) {
  const c = useYd();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const yarnStore =
    options.stores.find((s) => /yarn/i.test(s.label))?.value ?? "";
  const asHead = (d) => ({
    receive_date: str(d?.receive_date) || today,
    program_no: str(d?.program_no),
    working_ref: str(d?.working_ref),
    challan_no: str(d?.challan_no),
    challan_date: str(d?.challan_date),
    store_id: str(d?.store_id) || yarnStore,
    remarks: str(d?.remarks),
  });
  const asLine = (l = {}) => ({
    key: lineKey(),
    program_line_id: str(l.program_line_id),
    lot_no: str(l.lot_no),
    qty: str(l.qty),
    uom: str(l.uom) || "kg",
    cones: str(l.cones),
    remarks: str(l.remarks),
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && (!doc || doc.status === "DRAFT");
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setL = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  const program = options.programs.find((p) => p.value === head.program_no);
  const shades = shadesOf(options, head.program_no).map((s) => ({
    value: s.value,
    label: s.label,
  }));
  const owed = options.programs
    .filter((p) => p.pending > 0 && !["CLOSED", "CANCELLED"].includes(p.status))
    .map((p) => ({ ...p, party_id: p.supplier_id, party: p.supplier }));
  const total = lines.reduce((a, l) => a + num(l.qty), 0);

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${enc(d.receive_no)}` : "?tab=entry",
    );
  }
  function load(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`yd/receipts/${enc(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  /** Picking a programme brings its shades with what each still has out. */
  function choose(no) {
    if (!open) return;
    setH({ program_no: no });
    setLines(
      shadesOf(options, no)
        .filter((s) => s.pending > 0)
        .map((s) =>
          asLine({ program_line_id: s.value, qty: s.pending, uom: s.uom }),
        ),
    );
    setErrors(null);
  }
  function save() {
    const miss = [
      !head.program_no && c("line.need_program"),
      lines.some((l) => !l.program_line_id) && c("line.need_shade"),
      lines.some((l) => !l.lot_no.trim()) && c("line.need_lot"),
      lines.some((l) => !(num(l.qty) > 0)) && c("line.need_qty"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          Object.entries(head).map(([k, v]) => [k, v === "" ? null : v]),
        ),
        lines: lines.map((l) => ({
          program_line_id: l.program_line_id,
          lot_no: l.lot_no,
          qty: orNull(l.qty),
          uom: l.uom,
          cones: orNull(l.cones),
          remarks: l.remarks || null,
        })),
      };
      const res = await act(
        doc ? `yd/receipts/${enc(doc.receive_no)}` : "yd/receipts",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.saved", { no: res.data.receive_no }),
        type: "success",
      });
    });
  }

  return (
    <div className="grid gap-4 2xl:grid-cols-[400px_minmax(0,1fr)]">
      <SideList
        c={c}
        title={c("sec.receive_list")}
        rows={owed}
        placeholder={c("ph.search_side")}
        active={head.program_no}
        onPick={choose}
        columns={[
          [c("col.program_no"), (r) => r.value],
          [c("col.supplier"), (r) => r.supplier],
          [c("col.order"), (r) => r.order],
          [c("col.pending"), (r) => qtyFmt.format(r.pending), true],
        ]}
      />
      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <FormHead
            c={c}
            title={doc ? doc.receive_no : c("tabs.receipt_entry")}
            hint={c("hint.receipt_form")}
            doc={doc}
            status={doc?.status}
            group="receipt_status"
          />
          {doc && doc.status !== "DRAFT" ? (
            <Alert variant="info" className="mt-3">
              {c("hint.locked", {
                no: doc.receive_no,
                status: c(`receipt_status.${doc.status}`),
              })}
            </Alert>
          ) : null}
          <Field
            label={c("f.existing_receipt")}
            hint={c("hint.existing")}
            className="mt-4"
          >
            <PSelect
              value={doc?.receive_no ?? ""}
              onValueChange={(v) => load(v ?? "")}
              options={pick(options.receipts ?? [], c("ph.new_receipt"))}
              disabled={pending}
              aria-label={c("f.existing_receipt")}
            />
          </Field>
          <h3 className="mt-5 text-sm font-semibold text-foreground">
            {c("sec.key")}
          </h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <Field label={c("f.receive_no")} hint={c("hint.receive_no")}>
              <Input
                value={doc?.receive_no ?? ""}
                placeholder={c("ph.auto")}
                disabled
                aria-label={c("f.receive_no")}
              />
            </Field>
            <Field label={c("f.receive_date")} required>
              <Input
                type="date"
                value={head.receive_date}
                onChange={(e) => setH({ receive_date: e.target.value })}
                disabled={!open}
                aria-label={c("f.receive_date")}
              />
            </Field>
            <Field label={c("f.program")} required>
              <PSelect
                value={head.program_no}
                onValueChange={(v) => choose(v ?? "")}
                options={pick(
                  options.programs.filter(
                    (p) =>
                      !["CLOSED", "CANCELLED"].includes(p.status) ||
                      p.value === head.program_no,
                  ),
                  c("ph.select"),
                )}
                disabled={!open}
                aria-label={c("f.program")}
              />
            </Field>
            <Field label={c("f.party")} required hint={c("hint.party_receipt")}>
              <PSelect
                value={program?.supplier_id ?? ""}
                options={pick(options.suppliers, c("ph.party"))}
                disabled
                aria-label={c("f.party")}
              />
            </Field>
            <Field label={c("f.working_ref")}>
              <Input
                value={head.working_ref}
                onChange={(e) => setH({ working_ref: e.target.value })}
                placeholder={c("ph.working_ref")}
                maxLength={40}
                disabled={!open}
                aria-label={c("f.working_ref")}
              />
            </Field>
          </div>
          <h3 className="mt-5 text-sm font-semibold text-foreground">
            {c("sec.challan")}
          </h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <Field label={c("f.challan_no")}>
              <Input
                value={head.challan_no}
                onChange={(e) => setH({ challan_no: e.target.value })}
                maxLength={40}
                disabled={!open}
                aria-label={c("f.challan_no")}
              />
            </Field>
            <Field label={c("f.challan_dt")}>
              <Input
                type="date"
                value={head.challan_date}
                onChange={(e) => setH({ challan_date: e.target.value })}
                disabled={!open}
                aria-label={c("f.challan_dt")}
              />
            </Field>
          </div>
          <h3 className="mt-5 text-sm font-semibold text-foreground">
            {c("sec.booked")}
          </h3>
          <div className="mt-3 grid gap-4 md:grid-cols-3">
            <Field label={c("f.company")} required>
              <PSelect
                value={options.company.value}
                options={[options.company]}
                disabled
                aria-label={c("f.company")}
              />
            </Field>
            <Field label={c("f.store")} required>
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
        <section className={cn(card, "p-5")}>
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 className="text-base font-semibold text-foreground">
                {c("sec.dyed_lines")}
              </h3>
              <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
                {c("hint.receipt_lines")}
              </p>
            </div>
            {open ? (
              <Button
                variant="outline"
                size="sm"
                onClick={() => setLines((all) => [...all, asLine()])}
              >
                <Plus /> {c("btn.add_shade")}
              </Button>
            ) : null}
          </div>
          <div className="mt-4 overflow-x-auto rounded-lg border border-border">
            {lines.length === 0 ? (
              <NoLines title={c("empty.lines")} hint={c("empty.shade_lines")} />
            ) : (
              <table className="w-full min-w-[900px] text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    {[
                      ["shade"],
                      ["lot"],
                      ["qty", true],
                      ["uom"],
                      ["cones", true],
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
                      <td className="min-w-[280px] px-2 py-1.5">
                        <PSelect
                          value={l.program_line_id}
                          onValueChange={(v) =>
                            setL(l.key, { program_line_id: v ?? "" })
                          }
                          options={pick(shades, c("ph.select"))}
                          disabled={!open}
                          aria-label={`${c("f.shade")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          value={l.lot_no}
                          onChange={(e) =>
                            setL(l.key, { lot_no: e.target.value })
                          }
                          maxLength={40}
                          disabled={!open}
                          className="w-32"
                          aria-label={`${c("f.lot")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          {...numIn}
                          value={l.qty}
                          onChange={(e) => setL(l.key, { qty: e.target.value })}
                          disabled={!open}
                          className="w-28 text-right"
                          aria-label={`${c("f.qty")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <PSelect
                          value={l.uom}
                          onValueChange={(v) => setL(l.key, { uom: v ?? "" })}
                          options={options.uoms}
                          disabled={!open}
                          aria-label={`${c("f.uom")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          {...numIn}
                          step="1"
                          value={l.cones}
                          onChange={(e) =>
                            setL(l.key, { cones: e.target.value })
                          }
                          disabled={!open}
                          className="w-24 text-right"
                          aria-label={`${c("f.cones")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          value={l.remarks}
                          onChange={(e) =>
                            setL(l.key, { remarks: e.target.value })
                          }
                          maxLength={300}
                          disabled={!open}
                          aria-label={`${c("f.remarks")} ${i + 1}`}
                        />
                      </td>
                      <RemoveCell
                        c={c}
                        n={i + 1}
                        open={open}
                        onClick={() =>
                          setLines((all) => all.filter((x) => x.key !== l.key))
                        }
                      />
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr className="border-t border-border font-semibold">
                    <td className="px-2 py-2" colSpan={2}>
                      {c("total")}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(total)}
                    </td>
                    <td colSpan={4} />
                  </tr>
                </tfoot>
              </table>
            )}
          </div>
        </section>
        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
        <FormFoot
          c={c}
          hint={c("hint.receipt_foot")}
          back={BASE.receipt}
          onClear={() => show(null)}
          onPrint={doc ? () => printReceipt(c, doc) : null}
          onSave={save}
          saveLabel={c("btn.save_receipt")}
          pending={pending}
          open={open}
        />
      </div>
    </div>
  );
}

/* ---------------- Yarn Dyeing Loss Adjustment ---------------- */

function LossList({ options, initialRows, canManage, act }) {
  const c = useYd();
  const flt = useShowFilters({ program: "", status: "" });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "yd/loss-memos",
    "memo_no",
    setRows,
    setError,
  );
  const shown = rows.filter(
    (r) =>
      (!flt.on.program || r.program_no === flt.on.program) &&
      (!flt.on.status || r.status === flt.on.status),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`yd/loss-memos/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printMemo(c, res.data);
    });
  }
  const columns = [
    {
      key: "memo_no",
      label: c("col.memo_no"),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${BASE.memo}?tab=entry&ref=${enc(r.memo_no)}`}
            className="font-medium text-brand hover:underline"
          >
            {r.memo_no}
          </Link>
          <StatusChip
            status={TONE[r.status]}
            label={c(`memo_status.${r.status}`)}
          />
        </span>
      ),
    },
    {
      key: "memo_date",
      label: c("col.date"),
      nowrap: true,
      text: (r) => dayFmt(r.memo_date),
    },
    { key: "program_no", label: c("col.program_no"), nowrap: true },
    { key: "party", label: c("col.party") },
    { key: "buyer", label: c("col.buyer") },
    { key: "order", label: c("col.order") },
    {
      key: "issued",
      label: c("col.issued"),
      num: true,
      text: (r) => qtyFmt.format(r.issued),
    },
    {
      key: "received",
      label: c("col.received"),
      num: true,
      text: (r) => qtyFmt.format(r.received),
    },
    {
      key: "loss",
      label: c("col.loss"),
      num: true,
      text: (r) => qtyFmt.format(r.loss),
    },
    {
      key: "loss_pct",
      label: c("col.loss_pct"),
      num: true,
      text: (r) =>
        r.loss_pct === null ? "—" : `${Number(r.loss_pct).toFixed(2)}%`,
    },
  ];
  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      extra={
        canManage ? (
          <Link
            href={`${BASE.memo}?tab=entry`}
            className={buttonVariants({ variant: "outline", size: "sm" })}
          >
            <Plus /> {c("btn.add_new")}
          </Link>
        ) : null
      }
      filters={
        <>
          <Field label={c("f.program_hash")}>
            <PSelect
              value={flt.f.program}
              onValueChange={(v) => flt.set("program", v)}
              options={pick(options.programs, c("ph.select"))}
              aria-label={c("f.program_hash")}
            />
          </Field>
          <Field label={c("f.status")}>
            <PSelect
              value={flt.f.status}
              onValueChange={(v) => flt.set("status", v)}
              options={pick(
                statusOptions(c, options.memo_statuses, "memo_status"),
                c("ph.select"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.loss_list")}
      </p>
      <ScTable
        rows={shown}
        columns={columns}
        exportName="yd-process-loss"
        empty={<NoLines title={c("empty.memos")} hint={c("empty.list_hint")} />}
        pending={pending}
        actions={(r) => (
          <>
            {canManage && r.status === "DRAFT" ? (
              <MoveButton
                icon={Check}
                label={c("btn.approve")}
                no={r.memo_no}
                onClick={() => move(r.memo_no, "approve", act)}
                disabled={pending}
              />
            ) : null}
            {canManage && r.status !== "CANCELLED" ? (
              <MoveButton
                icon={Ban}
                label={c("btn.cancel_doc")}
                no={r.memo_no}
                onClick={() => move(r.memo_no, "cancel", act)}
                disabled={pending}
                danger
              />
            ) : null}
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

const MLINE = [
  "program_line_id",
  "colour_group",
  "lot_no",
  "issued_qty",
  "received_qty",
  "remarks",
];

function LossEntry({ options, initial, canManage, act }) {
  const c = useYd();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asLine = (l = {}) => ({
    key: lineKey(),
    ...Object.fromEntries(MLINE.map((k) => [k, str(l[k])])),
  });
  const asHead = (d) => ({
    memo_date: str(d?.memo_date) || today,
    program_no: str(d?.program_no),
    remarks: str(d?.remarks),
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [draft, setDraft] = useState(() => asLine());
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && (!doc || doc.status === "DRAFT");
  const setD = (patch) => setDraft((d) => ({ ...d, ...patch }));
  const program = options.programs.find((p) => p.value === head.program_no);
  const shades = shadesOf(options, head.program_no);
  const shadeName = (id) => named(shades, id) ?? "—";
  const sums = lines.reduce(
    (a, l) => [a[0] + num(l.issued_qty), a[1] + num(l.received_qty)],
    [0, 0],
  );

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setDraft(asLine());
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${enc(d.memo_no)}` : "?tab=entry",
    );
  }
  function load(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`yd/loss-memos/${enc(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  /** A shade picked opens on its share of the issue and what came back on it. */
  function pickShade(id) {
    const s = shades.find((x) => x.value === id);
    setD(
      s
        ? {
            program_line_id: id,
            colour_group: str(s.colour_group),
            lot_no: str(s.lot_no),
            issued_qty: str(s.issued_share),
            received_qty: str(s.delivered),
          }
        : { program_line_id: "" },
    );
  }
  function addLine() {
    const miss = [
      !draft.program_line_id && c("line.need_shade"),
      !(num(draft.issued_qty) > 0) && c("line.need_issued"),
      draft.received_qty === "" && c("line.need_received"),
      num(draft.received_qty) > num(draft.issued_qty) &&
        c("line.received_over"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    setLines((all) => [...all, draft]);
    setDraft(asLine());
    setErrors(null);
  }
  function save() {
    startTransition(async () => {
      const body = {
        memo_date: head.memo_date || null,
        program_no: head.program_no || null,
        remarks: head.remarks || null,
        lines: lines.map((l) => ({
          ...Object.fromEntries(
            MLINE.map((k) => [k, l[k] === "" ? null : l[k]]),
          ),
          issued_qty: orNull(l.issued_qty),
          received_qty: orNull(l.received_qty),
        })),
      };
      const res = await act(
        doc ? `yd/loss-memos/${enc(doc.memo_no)}` : "yd/loss-memos",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.saved", { no: res.data.memo_no }),
        type: "success",
      });
    });
  }
  const loss = (l) => num(l.issued_qty) - num(l.received_qty);

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={doc ? doc.memo_no : c("tabs.loss_entry")}
          hint={c("hint.loss_form")}
          doc={doc}
          status={doc?.status}
          group="memo_status"
        />
        {doc && doc.status !== "DRAFT" ? (
          <Alert variant="info" className="mt-3">
            {c("hint.locked", {
              no: doc.memo_no,
              status: c(`memo_status.${doc.status}`),
            })}
          </Alert>
        ) : null}
        <Field
          label={c("f.existing_memo")}
          hint={c("hint.existing")}
          className="mt-4"
        >
          <PSelect
            value={doc?.memo_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={pick(options.memos ?? [], c("ph.new_memo"))}
            disabled={pending}
            aria-label={c("f.existing_memo")}
          />
        </Field>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Field label={c("f.date")} required>
            <Input
              type="date"
              value={head.memo_date}
              onChange={(e) =>
                setHead((h) => ({ ...h, memo_date: e.target.value }))
              }
              disabled={!open}
              aria-label={c("f.date")}
            />
          </Field>
          <Field label={c("f.party")} required>
            <PSelect
              value={program?.supplier_id ?? ""}
              options={pick(options.suppliers, c("ph.party"))}
              disabled
              aria-label={c("f.party")}
            />
          </Field>
          <Field
            label={c("f.program_hash")}
            required
            hint={c("hint.loss_program")}
          >
            <PSelect
              value={head.program_no}
              onValueChange={(v) => (
                setHead((h) => ({ ...h, program_no: v ?? "" })),
                setLines([]),
                setDraft(asLine())
              )}
              options={pick(
                options.programs.filter(
                  (p) =>
                    !["CLOSED", "CANCELLED"].includes(p.status) ||
                    p.value === head.program_no,
                ),
                c("ph.select"),
              )}
              disabled={!open}
              aria-label={c("f.program_hash")}
            />
          </Field>
        </div>
        <Field label={c("f.remarks")} className="mt-4">
          <Textarea
            rows={2}
            value={head.remarks}
            onChange={(e) =>
              setHead((h) => ({ ...h, remarks: e.target.value }))
            }
            maxLength={2000}
            disabled={!open}
            aria-label={c("f.remarks")}
          />
        </Field>
      </section>
      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold text-foreground">
              {c("sec.loss_lines")}
            </h3>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {c("hint.loss_lines")}
            </p>
          </div>
          {open ? (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={addLine}>
                <Plus /> {c("btn.add_line")}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setDraft(asLine())}
              >
                <X /> {c("btn.cancel")}
              </Button>
            </div>
          ) : null}
        </div>
        {open ? (
          <div className="mt-4 grid gap-4 md:grid-cols-3">
            <Field label={c("f.shade")}>
              <PSelect
                value={draft.program_line_id}
                onValueChange={(v) => pickShade(v ?? "")}
                options={pick(
                  shades.map((s) => ({ value: s.value, label: s.label })),
                  c("ph.select"),
                )}
                aria-label={c("f.shade")}
              />
            </Field>
            <Field label={c("f.colour_group")}>
              <PSelect
                value={draft.colour_group}
                onValueChange={(v) => setD({ colour_group: v ?? "" })}
                options={groups(options, c)}
                aria-label={c("f.colour_group")}
              />
            </Field>
            <Field label={c("f.lot")}>
              <Input
                value={draft.lot_no}
                onChange={(e) => setD({ lot_no: e.target.value })}
                maxLength={40}
                aria-label={c("f.lot")}
              />
            </Field>
            <Field label={c("f.issued_qty")}>
              <Input
                {...numIn}
                value={draft.issued_qty}
                onChange={(e) => setD({ issued_qty: e.target.value })}
                aria-label={c("f.issued_qty")}
              />
            </Field>
            <Field label={c("f.received_qty")}>
              <Input
                {...numIn}
                value={draft.received_qty}
                onChange={(e) => setD({ received_qty: e.target.value })}
                aria-label={c("f.received_qty")}
              />
            </Field>
            <Field label={c("f.remarks")}>
              <Input
                value={draft.remarks}
                onChange={(e) => setD({ remarks: e.target.value })}
                maxLength={300}
                aria-label={`${c("f.remarks")} (${c("f.shade")})`}
              />
            </Field>
          </div>
        ) : null}
        <div className="mt-5 overflow-x-auto rounded-lg border border-border">
          {lines.length === 0 ? (
            <NoLines title={c("empty.lines")} hint={c("empty.memo_lines")} />
          ) : (
            <table className="w-full min-w-[900px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    ["shade"],
                    ["colour_group"],
                    ["lot"],
                    ["issued", true],
                    ["received", true],
                    ["loss", true],
                    ["loss_pct", true],
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
                    <td className="px-2 py-2 font-medium">
                      {shadeName(l.program_line_id)}
                    </td>
                    <td className="px-2 py-2">{l.colour_group || "—"}</td>
                    <td className="px-2 py-2">{l.lot_no || "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(num(l.issued_qty))}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(num(l.received_qty))}
                    </td>
                    <td className="px-2 py-2 text-right font-semibold tabular-nums">
                      {qtyFmt.format(loss(l))}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {num(l.issued_qty) > 0
                        ? `${((100 * loss(l)) / num(l.issued_qty)).toFixed(2)}%`
                        : "—"}
                    </td>
                    <td className="px-2 py-2">{l.remarks || "—"}</td>
                    <RemoveCell
                      c={c}
                      n={i + 1}
                      open={open}
                      onClick={() =>
                        setLines((all) => all.filter((x) => x.key !== l.key))
                      }
                    />
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="border-t border-border font-semibold">
                  <td className="px-2 py-2" colSpan={3}>
                    {c("total")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(sums[0])}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(sums[1])}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(sums[0] - sums[1])}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {sums[0] > 0
                      ? `${((100 * (sums[0] - sums[1])) / sums[0]).toFixed(2)}%`
                      : "—"}
                  </td>
                  <td colSpan={2} />
                </tr>
              </tfoot>
            </table>
          )}
        </div>
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <FormFoot
        c={c}
        hint={c("hint.loss_foot")}
        back={BASE.memo}
        onClear={() => show(null)}
        onPrint={doc ? () => printMemo(c, doc) : null}
        onSave={save}
        saveLabel={c("btn.save_memo")}
        pending={pending}
        open={open}
      />
    </div>
  );
}

/* ---------------- YD Re-process ---------------- */

function RpList({ kind, options, initialRows, canManage, act }) {
  const c = useYd();
  const sc = kind === "SUB_CONTRACT";
  const flt = useShowFilters({ party: "", status: "", challan: "" });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const path = `yd/reprocess/${SLUG[kind]}`;
  const [move, pending, startTransition] = useMoves(
    c,
    path,
    "ref_no",
    setRows,
    setError,
  );
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.party || r.supplier_id === o.party || r.party_id === o.party) &&
      (!o.status || r.status === o.status) &&
      (!o.challan || has(r.challan_no, o.challan)),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`${path}/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printReprocess(c, res.data);
    });
  }
  const columns = [
    {
      key: "ref_no",
      label: c("col.ref_no"),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${BASE[kind]}?tab=entry&ref=${enc(r.ref_no)}`}
            className="font-medium text-brand hover:underline"
          >
            {r.ref_no}
          </Link>
          <StatusChip
            status={TONE[r.status]}
            label={c(`reprocess_status.${r.status}`)}
          />
        </span>
      ),
    },
    {
      key: "ref_date",
      label: c("col.date"),
      nowrap: true,
      text: (r) => dayFmt(r.ref_date),
    },
    { key: "factory", label: c("col.factory") },
    ...(sc ? [{ key: "party", label: c("col.party") }] : []),
    { key: "bill_no", label: c("col.bill") },
    { key: "challan_no", label: c("col.challan_hash") },
    { key: "program_no", label: c("col.program_no"), nowrap: true },
    { key: "store", label: c("col.store") },
    {
      key: "qty",
      label: c("col.qty"),
      num: true,
      text: (r) => qtyFmt.format(r.qty),
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
          <Field label={c("f.party")}>
            <PSelect
              value={flt.f.party}
              onValueChange={(v) => flt.set("party", v)}
              options={pick(options.suppliers, c("ph.select"))}
              aria-label={c("f.party")}
            />
          </Field>
          <Field label={c("f.status")}>
            <PSelect
              value={flt.f.status}
              onValueChange={(v) => flt.set("status", v)}
              options={pick(
                statusOptions(
                  c,
                  options.reprocess_statuses,
                  "reprocess_status",
                ),
                c("ph.select"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <Field label={c("f.challan_hash")}>
            <Input
              value={flt.f.challan}
              onChange={(e) => flt.set("challan", e.target.value)}
              aria-label={c("f.challan_hash")}
            />
          </Field>
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.rp_list")}
      </p>
      <ScTable
        rows={shown}
        columns={columns}
        exportName={`yd-reprocess-${SLUG[kind]}`}
        addHref={canManage ? `${BASE[kind]}?tab=entry` : null}
        empty={<NoLines title={c("empty.rps")} hint={c("empty.list_hint")} />}
        pending={pending}
        actions={(r) => (
          <>
            {canManage && r.status === "DRAFT" ? (
              <MoveButton
                icon={Send}
                label={c("btn.issue")}
                no={r.ref_no}
                onClick={() => move(r.ref_no, "issue", act)}
                disabled={pending}
              />
            ) : null}
            {canManage && r.status === "ISSUED" ? (
              <>
                <MoveButton
                  icon={Check}
                  label={c("btn.accept")}
                  no={r.ref_no}
                  onClick={() => move(r.ref_no, "accept", act)}
                  disabled={pending}
                />
                <MoveButton
                  icon={X}
                  label={c("btn.reject")}
                  no={r.ref_no}
                  onClick={() => move(r.ref_no, "reject", act)}
                  disabled={pending}
                  danger
                />
              </>
            ) : null}
            {canManage && ["DRAFT", "ISSUED"].includes(r.status) ? (
              <MoveButton
                icon={Ban}
                label={c("btn.cancel_doc")}
                no={r.ref_no}
                onClick={() => move(r.ref_no, "cancel", act)}
                disabled={pending}
                danger
              />
            ) : null}
            <MoveButton
              icon={Printer}
              label={c("btn.print")}
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

const RLINE = [
  "lot_id",
  "composition_id",
  "yarn_count_id",
  "colour_group",
  "lot_no",
  "qty",
  "uom",
  "reason",
  "remarks",
];

function RpEntry({ kind, options, initial, canManage, act }) {
  const c = useYd();
  const toastManager = useToastManager();
  const sc = kind === "SUB_CONTRACT";
  const [today] = useState(todayIso);
  const yarnStore =
    options.stores.find((s) => /yarn/i.test(s.label))?.value ?? "";
  const asHead = (d) => ({
    ref_date: str(d?.ref_date) || today,
    supplier_id: str(d?.supplier_id),
    party_id: str(d?.party_id),
    bill_no: str(d?.bill_no),
    challan_no: str(d?.challan_no),
    challan_date: str(d?.challan_date),
    program_no: str(d?.program_no),
    store_id: str(d?.store_id) || yarnStore,
    remarks: str(d?.remarks),
  });
  const asLine = (l = {}) => ({
    key: lineKey(),
    ...Object.fromEntries(RLINE.map((k) => [k, str(l[k])])),
    uom: str(l.uom) || "kg",
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && (!doc || doc.status === "DRAFT");
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setL = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  const lots = options.lots.filter(
    (l) =>
      l.store_id === head.store_id &&
      (l.balance > 0 || lines.some((x) => x.lot_id === l.lot_id)),
  );
  const lotOptions = lots.map((l) => ({
    value: l.lot_id,
    label: `${l.label} · ${qtyFmt.format(l.balance)} ${l.uom}`,
  }));
  const programs = options.programs.filter(
    (p) =>
      p.yd_type === kind &&
      (!["CLOSED", "CANCELLED"].includes(p.status) ||
        p.value === head.program_no),
  );
  const total = lines.reduce((a, l) => a + num(l.qty), 0);

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${enc(d.ref_no)}` : "?tab=entry",
    );
  }
  function load(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`yd/reprocess/${SLUG[kind]}/${enc(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function pickLot(k, id) {
    const l = options.lots.find((x) => x.lot_id === id);
    setL(
      k,
      l
        ? {
            lot_id: id,
            lot_no: str(l.lot_no),
            uom: l.uom || "kg",
            colour_group: options.colour_groups.includes(l.colour)
              ? l.colour
              : "",
          }
        : { lot_id: "" },
    );
  }
  function save() {
    const miss = [
      lines.some((l) =>
        sc ? !l.composition_id && !l.yarn_count_id : !l.lot_id,
      ) && c("line.need_yarn"),
      lines.some((l) => !(num(l.qty) > 0)) && c("line.need_qty"),
      lines.some((l) => !l.reason.trim()) && c("line.need_reason"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          Object.entries(head).map(([k, v]) => [k, v === "" ? null : v]),
        ),
        lines: lines.map((l) => ({
          ...Object.fromEntries(
            RLINE.map((k) => [k, l[k] === "" ? null : l[k]]),
          ),
          qty: orNull(l.qty),
        })),
      };
      const path = `yd/reprocess/${SLUG[kind]}`;
      const res = await act(
        doc ? `${path}/${enc(doc.ref_no)}` : path,
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.saved", { no: res.data.ref_no }),
        type: "success",
      });
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={
            doc ? doc.ref_no : c(sc ? "tabs.rp_sc_entry" : "tabs.rp_ih_entry")
          }
          hint={c(sc ? "hint.rp_sc_form" : "hint.rp_ih_form")}
          doc={doc}
          status={doc?.status}
          group="reprocess_status"
        />
        {doc && doc.status !== "DRAFT" ? (
          <Alert variant="info" className="mt-3">
            {c("hint.locked", {
              no: doc.ref_no,
              status: c(`reprocess_status.${doc.status}`),
            })}
          </Alert>
        ) : null}
        <Field
          label={c("f.existing_rp")}
          hint={c("hint.existing")}
          className="mt-4"
        >
          <PSelect
            value={doc?.ref_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={pick(options.reprocesses ?? [], c("ph.new_rp"))}
            disabled={pending}
            aria-label={c("f.existing_rp")}
          />
        </Field>
        <h3 className="mt-5 text-sm font-semibold text-foreground">
          {c("sec.key")}
        </h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.ref_no")} hint={c("hint.ref_no")}>
            <Input
              value={doc?.ref_no ?? ""}
              placeholder={c("ph.auto")}
              disabled
              aria-label={c("f.ref_no")}
            />
          </Field>
          <Field label={c("f.date")} required>
            <Input
              type="date"
              value={head.ref_date}
              onChange={(e) => setH({ ref_date: e.target.value })}
              disabled={!open}
              aria-label={c("f.date")}
            />
          </Field>
          <Field label={c("f.factory")} required hint={c("hint.factory")}>
            <PSelect
              value={head.supplier_id}
              onValueChange={(v) => setH({ supplier_id: v ?? "" })}
              options={pick(options.suppliers, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.factory")}
            />
          </Field>
          {sc ? (
            <Field label={c("f.party")} required hint={c("hint.sc_party")}>
              <PSelect
                value={head.party_id}
                onValueChange={(v) => setH({ party_id: v ?? "" })}
                options={pick(options.suppliers, c("ph.select"))}
                disabled={!open}
                aria-label={c("f.party")}
              />
            </Field>
          ) : null}
        </div>
        <h3 className="mt-5 text-sm font-semibold text-foreground">
          {c("sec.paperwork")}
        </h3>
        <div className="mt-3 grid gap-4 md:grid-cols-4">
          <Field label={c("f.bill_no")}>
            <Input
              value={head.bill_no}
              onChange={(e) => setH({ bill_no: e.target.value })}
              maxLength={40}
              disabled={!open}
              aria-label={c("f.bill_no")}
            />
          </Field>
          <Field label={c("f.challan_hash")}>
            <Input
              value={head.challan_no}
              onChange={(e) => setH({ challan_no: e.target.value })}
              maxLength={40}
              disabled={!open}
              aria-label={c("f.challan_hash")}
            />
          </Field>
          <Field label={c("f.chln_dt")}>
            <Input
              type="date"
              value={head.challan_date}
              onChange={(e) => setH({ challan_date: e.target.value })}
              disabled={!open}
              aria-label={c("f.chln_dt")}
            />
          </Field>
          <Field label={c("f.program")} hint={c("hint.rp_program")}>
            <PSelect
              value={head.program_no}
              onValueChange={(v) => setH({ program_no: v ?? "" })}
              options={pick(programs, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.program")}
            />
          </Field>
        </div>
        <h3 className="mt-5 text-sm font-semibold text-foreground">
          {c("sec.booked")}
        </h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.company")} required>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={c("f.company")}
            />
          </Field>
          <Field label={c("f.store")} required>
            <PSelect
              value={head.store_id}
              onValueChange={(v) => (
                setH({ store_id: v ?? "" }),
                !sc &&
                  setLines((all) =>
                    all.map((l) => ({ ...l, lot_id: "", lot_no: "" })),
                  )
              )}
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
      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold text-foreground">
              {c("sec.going_back")}
            </h3>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {c("hint.rp_lines")}
            </p>
          </div>
          {open ? (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setLines((all) => [...all, asLine()])}
            >
              <Plus /> {c("btn.add_yarn")}
            </Button>
          ) : null}
        </div>
        <div className="mt-4 overflow-x-auto rounded-lg border border-border">
          {lines.length === 0 ? (
            <NoLines title={c("empty.lines")} hint={c("empty.rp_lines")} />
          ) : (
            <table className="w-full min-w-[1100px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    ["yarn"],
                    ["shade_band"],
                    ["lot"],
                    ["qty", true],
                    ["uom"],
                    ["reason"],
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
                    <td className="min-w-[300px] px-2 py-1.5">
                      {sc ? (
                        <div className="flex gap-2">
                          <PSelect
                            value={l.composition_id}
                            onValueChange={(v) =>
                              setL(l.key, { composition_id: v ?? "" })
                            }
                            options={pick(options.compositions, c("ph.yarn"))}
                            disabled={!open}
                            aria-label={`${c("f.yarn")} ${i + 1}`}
                          />
                          <PSelect
                            value={l.yarn_count_id}
                            onValueChange={(v) =>
                              setL(l.key, { yarn_count_id: v ?? "" })
                            }
                            options={pick(options.counts, c("ph.count"))}
                            disabled={!open}
                            aria-label={`${c("f.count")} ${i + 1}`}
                          />
                        </div>
                      ) : open ? (
                        <PSelect
                          value={l.lot_id}
                          onValueChange={(v) => pickLot(l.key, v ?? "")}
                          options={pick(lotOptions, c("ph.lot"))}
                          aria-label={`${c("f.yarn_line")} ${i + 1}`}
                        />
                      ) : (
                        <span className="font-medium">
                          {yarnOf(options, l) || "—"}
                        </span>
                      )}
                    </td>
                    <td className="px-2 py-1.5">
                      <PSelect
                        value={l.colour_group}
                        onValueChange={(v) =>
                          setL(l.key, { colour_group: v ?? "" })
                        }
                        options={groups(options, c)}
                        disabled={!open}
                        aria-label={`${c("f.shade_band")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={l.lot_no}
                        onChange={(e) =>
                          setL(l.key, { lot_no: e.target.value })
                        }
                        maxLength={40}
                        disabled={!open || !sc}
                        className="w-28"
                        aria-label={`${c("f.lot")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        {...numIn}
                        value={l.qty}
                        onChange={(e) => setL(l.key, { qty: e.target.value })}
                        disabled={!open}
                        className="w-24 text-right"
                        aria-label={`${c("f.qty")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <PSelect
                        value={l.uom}
                        onValueChange={(v) => setL(l.key, { uom: v ?? "" })}
                        options={options.uoms}
                        disabled={!open || !sc}
                        aria-label={`${c("f.uom")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={l.reason}
                        onChange={(e) =>
                          setL(l.key, { reason: e.target.value })
                        }
                        placeholder={c("ph.reason")}
                        maxLength={120}
                        disabled={!open}
                        aria-label={`${c("f.reason")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={l.remarks}
                        onChange={(e) =>
                          setL(l.key, { remarks: e.target.value })
                        }
                        maxLength={300}
                        disabled={!open}
                        aria-label={`${c("f.remarks")} ${i + 1}`}
                      />
                    </td>
                    <RemoveCell
                      c={c}
                      n={i + 1}
                      open={open}
                      onClick={() =>
                        setLines((all) => all.filter((x) => x.key !== l.key))
                      }
                    />
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="border-t border-border font-semibold">
                  <td className="px-2 py-2" colSpan={3}>
                    {c("total")}: {lines.length}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(total)}
                  </td>
                  <td colSpan={4} />
                </tr>
              </tfoot>
            </table>
          )}
        </div>
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <FormFoot
        c={c}
        hint={c("hint.rp_foot")}
        back={BASE[kind]}
        onClear={() => show(null)}
        onPrint={doc ? () => printReprocess(c, doc) : null}
        onSave={save}
        saveLabel={c("btn.save_rp")}
        pending={pending}
        open={open}
      />
    </div>
  );
}

/* ---------------- In-House Sub-Contract Yarn Receipt ---------------- */

function ScChallanList({ options, initialRows, canManage, act }) {
  const c = useYd();
  const [f, setF] = useState({ from: "", to: "", status: "" });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "sc-yarn-challans",
    "challan_no",
    setRows,
    setError,
  );
  const shown = rows.filter(
    (r) =>
      (!f.from || r.challan_date >= f.from) &&
      (!f.to || r.challan_date <= f.to) &&
      (!f.status || r.status === f.status),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`sc-yarn-challans/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printChallan(c, res.data);
    });
  }
  const columns = [
    {
      key: "challan_no",
      label: c("col.challan_hash"),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${BASE.challan}?tab=entry&ref=${enc(r.challan_no)}`}
            className="font-medium text-brand hover:underline"
          >
            {r.challan_no}
          </Link>
          <StatusChip
            status={TONE[r.status]}
            label={c(`challan_status.${r.status}`)}
          />
        </span>
      ),
      text: (r) => r.challan_no,
    },
    {
      key: "challan_date",
      label: c("col.date"),
      nowrap: true,
      text: (r) => dayFmt(r.challan_date),
    },
    { key: "program_no", label: c("col.program_plain"), nowrap: true },
    { key: "order_no", label: c("col.order_no") },
    { key: "style_no", label: c("col.style_no") },
    { key: "supplier", label: c("col.supplier_plain") },
    { key: "store", label: c("col.store") },
    {
      key: "qty",
      label: c("col.qty"),
      num: true,
      text: (r) => qtyFmt.format(r.qty),
    },
    { key: "remarks", label: c("col.remarks") },
  ];
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 sm:grid-cols-3">
          <Field label={c("f.from")}>
            <Input
              type="date"
              value={f.from}
              onChange={(e) => setF((x) => ({ ...x, from: e.target.value }))}
              aria-label={c("f.from")}
            />
          </Field>
          <Field label={c("f.to")}>
            <Input
              type="date"
              value={f.to}
              onChange={(e) => setF((x) => ({ ...x, to: e.target.value }))}
              aria-label={c("f.to")}
            />
          </Field>
          <Field label={c("f.status")}>
            <PSelect
              value={f.status}
              onValueChange={(v) => setF((x) => ({ ...x, status: v ?? "" }))}
              options={pick(
                statusOptions(c, options.challan_statuses, "challan_status"),
                c("ph.all"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
        </div>
        <div className="mt-4 flex justify-end">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setF({ from: "", to: "", status: "" })}
          >
            <Eraser /> {c("btn.clear")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>
        <p className="px-5 pt-4 text-[13px] text-foreground-muted">
          {c("hint.syc_list")}
        </p>
        <ScTable
          rows={shown}
          columns={columns}
          colFilters
          exportName="sc-yarn-receive-challans"
          addHref={canManage ? `${BASE.challan}?tab=entry` : null}
          empty={<NoLines title={c("empty.challans")} hint="" />}
          pending={pending}
          actions={(r) => (
            <>
              {canManage && r.status === "DRAFT" ? (
                <MoveButton
                  icon={Send}
                  label={c("btn.issue")}
                  no={r.challan_no}
                  onClick={() => move(r.challan_no, "issue", act)}
                  disabled={pending}
                />
              ) : null}
              {canManage && r.status === "ISSUED" ? (
                <>
                  <MoveButton
                    icon={Check}
                    label={c("btn.accept")}
                    no={r.challan_no}
                    onClick={() => move(r.challan_no, "accept", act)}
                    disabled={pending}
                  />
                  <MoveButton
                    icon={X}
                    label={c("btn.reject")}
                    no={r.challan_no}
                    onClick={() => move(r.challan_no, "reject", act)}
                    disabled={pending}
                    danger
                  />
                </>
              ) : null}
              {canManage && ["DRAFT", "ISSUED"].includes(r.status) ? (
                <MoveButton
                  icon={Ban}
                  label={c("btn.cancel_doc")}
                  no={r.challan_no}
                  onClick={() => move(r.challan_no, "cancel", act)}
                  disabled={pending}
                  danger
                />
              ) : null}
              <MoveButton
                icon={Printer}
                label={c("btn.print")}
                no={r.challan_no}
                onClick={() => print(r.challan_no)}
                disabled={pending}
              />
            </>
          )}
        />
      </section>
    </div>
  );
}

const CLINE = [
  "composition_id",
  "yarn_count_id",
  "colour_group",
  "brand",
  "lot_no",
  "qty",
  "uom",
  "cones",
  "remarks",
];

function ScChallanEntry({ options, initial, canManage, act }) {
  const c = useYd();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asLine = (l = {}) => ({
    key: lineKey(),
    ...Object.fromEntries(CLINE.map((k) => [k, str(l[k])])),
    uom: str(l.uom) || "kg",
  });
  const asHead = (d) => ({
    program_no: str(d?.program_no),
    challan_no: str(d?.challan_no),
    challan_date: str(d?.challan_date) || today,
    store_id: str(d?.store_id),
    remarks: str(d?.remarks),
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [draft, setDraft] = useState(() => asLine());
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const open = canManage && (!doc || doc.status === "DRAFT");
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setD = (patch) => setDraft((d) => ({ ...d, ...patch }));
  const program = options.sc_programs.find((p) => p.value === head.program_no);
  const owed = options.sc_programs.filter(
    (p) => p.pending > 0 || p.value === head.program_no,
  );
  const total = lines.reduce((a, l) => a + num(l.qty), 0);

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setDraft(asLine());
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${enc(d.challan_no)}` : "?tab=entry",
    );
  }
  function load(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`sc-yarn-challans/${enc(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function choose(no) {
    if (!open) return;
    const p = options.sc_programs.find((x) => x.value === no);
    setH({ program_no: no, store_id: head.store_id || p?.store_id || "" });
    setErrors(null);
  }
  function addLine() {
    const miss = [
      !draft.composition_id && !draft.yarn_count_id && c("line.need_yarn"),
      !draft.lot_no.trim() && c("line.need_lot"),
      !(num(draft.qty) > 0) && c("line.need_qty"),
      !draft.uom && c("line.need_uom"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    setLines((all) => [...all, draft]);
    setDraft(
      asLine({
        composition_id: draft.composition_id,
        yarn_count_id: draft.yarn_count_id,
        colour_group: draft.colour_group,
        brand: draft.brand,
        uom: draft.uom,
      }),
    );
    setErrors(null);
  }
  function save() {
    if (!head.program_no) return setErrors([c("line.need_sc")]);
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          Object.entries(head).map(([k, v]) => [k, v === "" ? null : v]),
        ),
        lines: lines.map((l) => ({
          ...Object.fromEntries(
            CLINE.map((k) => [k, l[k] === "" ? null : l[k]]),
          ),
          qty: orNull(l.qty),
          cones: orNull(l.cones),
        })),
      };
      const res = await act(
        doc ? `sc-yarn-challans/${enc(doc.challan_no)}` : "sc-yarn-challans",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.saved", { no: res.data.challan_no }),
        type: "success",
      });
    });
  }

  return (
    <div className="grid gap-4 2xl:grid-cols-[420px_minmax(0,1fr)]">
      <SideList
        c={c}
        title={c("sec.sc_programs")}
        rows={owed}
        placeholder={c("ph.search_sc")}
        active={head.program_no}
        onPick={choose}
        columns={[
          [c("col.order_no"), (r) => r.order_no],
          [c("col.style_no"), (r) => r.style_no],
          [c("col.date"), (r) => dayFmt(r.prog_date)],
          [c("col.program_plain"), (r) => r.value],
          [c("col.supplier_plain"), (r) => r.party],
        ]}
      />
      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <FormHead
            c={c}
            title={doc ? doc.challan_no : c("tabs.syc_entry")}
            hint={c("hint.syc_form")}
            doc={doc}
            status={doc?.status}
            group="challan_status"
          />
          {doc && doc.status !== "DRAFT" ? (
            <Alert variant="info" className="mt-3">
              {c("hint.locked", {
                no: doc.challan_no,
                status: c(`challan_status.${doc.status}`),
              })}
            </Alert>
          ) : null}
          <Field
            label={c("f.existing_challan")}
            hint={c("hint.existing")}
            className="mt-4"
          >
            <PSelect
              value={doc?.challan_no ?? ""}
              onValueChange={(v) => load(v ?? "")}
              options={pick(options.challans ?? [], c("ph.new_challan"))}
              disabled={pending}
              aria-label={c("f.existing_challan")}
            />
          </Field>
          <div className="mt-4 grid gap-4 md:grid-cols-3">
            <Field label={c("f.program_plain")}>
              <Input
                value={head.program_no}
                disabled
                aria-label={c("f.program_plain")}
              />
            </Field>
            <Field label={c("f.order_no")}>
              <Input
                value={program?.order_no ?? doc?.order_no ?? ""}
                disabled
                aria-label={c("f.order_no")}
              />
            </Field>
            <Field label={c("f.style_no")}>
              <Input
                value={program?.style_no ?? doc?.style_no ?? ""}
                disabled
                aria-label={c("f.style_no")}
              />
            </Field>
            <Field label={c("f.challan_hash")} hint={c("hint.syc_challan")}>
              <Input
                value={head.challan_no}
                onChange={(e) => setH({ challan_no: e.target.value })}
                placeholder={c("ph.challan")}
                maxLength={40}
                disabled={!open || Boolean(doc)}
                aria-label={c("f.challan_hash")}
              />
            </Field>
            <Field label={c("f.date")} required>
              <Input
                type="date"
                value={head.challan_date}
                onChange={(e) => setH({ challan_date: e.target.value })}
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
            <Field label={c("f.store")} required>
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
        <section className={cn(card, "p-5")}>
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 className="text-base font-semibold text-foreground">
                {c("sec.yarn_challan")}
              </h3>
              <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
                {c("hint.syc_lines")}
              </p>
            </div>
            {open ? (
              <div className="flex gap-2">
                <Button variant="outline" size="sm" onClick={addLine}>
                  <Plus /> {c("btn.add")}
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setDraft(asLine())}
                >
                  <X /> {c("btn.cancel")}
                </Button>
              </div>
            ) : null}
          </div>
          {open ? (
            <div className="mt-4 grid gap-4 md:grid-cols-4">
              <Field label={c("f.yarn")}>
                <PSelect
                  value={draft.composition_id}
                  onValueChange={(v) => setD({ composition_id: v ?? "" })}
                  options={pick(options.compositions, c("ph.yarn"))}
                  aria-label={c("f.yarn")}
                />
              </Field>
              <Field label={c("f.count")}>
                <PSelect
                  value={draft.yarn_count_id}
                  onValueChange={(v) => setD({ yarn_count_id: v ?? "" })}
                  options={pick(options.counts, c("ph.count"))}
                  aria-label={c("f.count")}
                />
              </Field>
              <Field label={c("f.colour_group")}>
                <PSelect
                  value={draft.colour_group}
                  onValueChange={(v) => setD({ colour_group: v ?? "" })}
                  options={groups(options, c)}
                  aria-label={c("f.colour_group")}
                />
              </Field>
              <Field label={c("f.brand")}>
                <Input
                  value={draft.brand}
                  onChange={(e) => setD({ brand: e.target.value })}
                  maxLength={80}
                  aria-label={c("f.brand")}
                />
              </Field>
              <Field label={c("f.lot")}>
                <Input
                  value={draft.lot_no}
                  onChange={(e) => setD({ lot_no: e.target.value })}
                  maxLength={40}
                  aria-label={c("f.lot")}
                />
              </Field>
              <Field label={c("f.qty")}>
                <Input
                  {...numIn}
                  value={draft.qty}
                  onChange={(e) => setD({ qty: e.target.value })}
                  aria-label={c("f.qty")}
                />
              </Field>
              <Field label={c("f.uom")}>
                <PSelect
                  value={draft.uom}
                  onValueChange={(v) => setD({ uom: v ?? "" })}
                  options={options.uoms}
                  aria-label={c("f.uom")}
                />
              </Field>
              <Field label={c("f.cones")}>
                <Input
                  {...numIn}
                  step="1"
                  value={draft.cones}
                  onChange={(e) => setD({ cones: e.target.value })}
                  aria-label={c("f.cones")}
                />
              </Field>
              <Field label={c("f.remarks")} className="md:col-span-4">
                <Input
                  value={draft.remarks}
                  onChange={(e) => setD({ remarks: e.target.value })}
                  maxLength={300}
                  aria-label={`${c("f.remarks")} (${c("f.lot")})`}
                />
              </Field>
            </div>
          ) : null}
          <div className="mt-5 overflow-x-auto rounded-lg border border-border">
            {lines.length === 0 ? (
              <NoLines title={c("empty.lines")} hint={c("empty.syc_lines")} />
            ) : (
              <table className="w-full min-w-[900px] text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    {[
                      ["yarn"],
                      ["colour_group"],
                      ["brand"],
                      ["lot"],
                      ["qty", true],
                      ["uom"],
                      ["cones", true],
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
                      <td className="px-2 py-2 font-medium">
                        {yarnOf(options, l) || "—"}
                      </td>
                      <td className="px-2 py-2">{l.colour_group || "—"}</td>
                      <td className="px-2 py-2">{l.brand || "—"}</td>
                      <td className="px-2 py-2">{l.lot_no}</td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {qtyFmt.format(num(l.qty))}
                      </td>
                      <td className="px-2 py-2">{l.uom}</td>
                      <td className="px-2 py-2 text-right tabular-nums">
                        {l.cones || "—"}
                      </td>
                      <td className="px-2 py-2">{l.remarks || "—"}</td>
                      <RemoveCell
                        c={c}
                        n={i + 1}
                        open={open}
                        onClick={() =>
                          setLines((all) => all.filter((x) => x.key !== l.key))
                        }
                      />
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr className="border-t border-border font-semibold">
                    <td className="px-2 py-2" colSpan={4}>
                      {c("total")}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(total)}
                    </td>
                    <td colSpan={4} />
                  </tr>
                </tfoot>
              </table>
            )}
          </div>
        </section>
        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
        <FormFoot
          c={c}
          hint={c("hint.syc_foot")}
          back={BASE.challan}
          onClear={() => show(null)}
          onPrint={doc ? () => printChallan(c, doc) : null}
          onSave={save}
          saveLabel={c("btn.save_challan")}
          pending={pending}
          open={open}
        />
      </div>
    </div>
  );
}

/* ---------------- Knitting Y/D Statement ---------------- */

function YdStatement({ options, initialRows, act }) {
  const c = useYd();
  const blank = {
    month: "",
    buyer: "",
    order: "",
    program: "",
    party: "",
    status: "",
  };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const months = [
    ...new Set(options.orders.map((o) => o.ship_month).filter(Boolean)),
  ]
    .sort()
    .reverse()
    .map((m) => ({ value: m, label: m }));
  const sum = (k) => rows.reduce((a, r) => a + num(r[k]), 0);

  function load(filters) {
    startTransition(async () => {
      const q = new URLSearchParams(
        Object.entries(filters).filter(([, v]) => v),
      ).toString();
      const res = await act(`yd/statement${q ? `?${q}` : ""}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  function print() {
    printDocument({
      title: c("p.statement"),
      orientation: "landscape",
      meta: [
        [c("f.ship_month"), f.month || null],
        [c("f.buyer"), named(options.buyers, f.buyer)],
        [c("f.style_order_hash"), named(options.orders, f.order)],
        [c("f.party_supplier"), named(options.suppliers, f.party)],
        [c("f.program_status"), f.status ? c(`status.${f.status}`) : null],
      ],
      metaColumns: 5,
      sections: [
        {
          columns: [
            { label: c("col.program_no"), nowrap: true },
            { label: c("col.type") },
            { label: c("col.party") },
            { label: c("col.buyer") },
            { label: c("col.order") },
            { label: c("col.planned"), align: "right" },
            { label: c("col.greige_issue"), align: "right" },
            { label: c("col.dyed_recv"), align: "right" },
            { label: c("col.process_loss"), align: "right" },
            { label: c("col.with_party"), align: "right" },
            { label: c("col.status") },
          ],
          rows: rows.map((r) => [
            { v: r.program_no, bold: true },
            c(`type.${r.yd_type}`),
            r.supplier,
            r.buyer,
            r.order_no ? `${r.order_no}${r.style ? ` (${r.style})` : ""}` : "—",
            qtyFmt.format(r.qty),
            qtyFmt.format(r.issue),
            qtyFmt.format(r.delivery),
            qtyFmt.format(r.loss),
            {
              v: qtyFmt.format(r.balance),
              bold: true,
              tone: r.balance < 0 ? "bad" : undefined,
            },
            c(`status.${r.status}`),
          ]),
          foot: [
            [
              "",
              "",
              "",
              "",
              { v: c("total"), bold: true },
              ...["qty", "issue", "delivery", "loss", "balance"].map((k) => ({
                v: qtyFmt.format(sum(k)),
                bold: true,
              })),
              "",
            ],
          ],
          empty: c("empty.statement"),
        },
      ],
      notes: [c("hint.statement")],
    });
  }
  const columns = [
    {
      key: "program_no",
      label: c("col.program_no"),
      nowrap: true,
      render: (r) => <span className="font-medium">{r.program_no}</span>,
      text: (r) => r.program_no,
    },
    {
      key: "yd_type",
      label: c("col.type"),
      text: (r) => c(`type.${r.yd_type}`),
    },
    { key: "supplier", label: c("col.party") },
    { key: "buyer", label: c("col.buyer") },
    {
      key: "order",
      label: c("col.order"),
      text: (r) =>
        r.order_no ? `${r.order_no}${r.style ? ` (${r.style})` : ""}` : "—",
    },
    {
      key: "program_date",
      label: c("col.prg_date"),
      nowrap: true,
      text: (r) => dayFmt(r.program_date),
    },
    {
      key: "qty",
      label: c("col.planned"),
      num: true,
      text: (r) => qtyFmt.format(r.qty),
    },
    {
      key: "issue",
      label: c("col.greige_issue"),
      num: true,
      text: (r) => qtyFmt.format(r.issue),
    },
    {
      key: "delivery",
      label: c("col.dyed_recv"),
      num: true,
      text: (r) => qtyFmt.format(r.delivery),
    },
    {
      key: "loss",
      label: c("col.process_loss"),
      num: true,
      text: (r) => qtyFmt.format(r.loss),
    },
    {
      key: "balance",
      label: c("col.with_party"),
      num: true,
      render: (r) => (
        <span className={cn("font-semibold", r.balance < 0 && "text-danger")}>
          {qtyFmt.format(r.balance)}
        </span>
      ),
      text: (r) => qtyFmt.format(r.balance),
    },
    {
      key: "status",
      label: c("col.status"),
      render: (r) => (
        <StatusChip status={TONE[r.status]} label={c(`status.${r.status}`)} />
      ),
      text: (r) => c(`status.${r.status}`),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Field label={c("f.ship_month")}>
            <PSelect
              value={f.month}
              onValueChange={(v) => setF("month", v)}
              options={pick(months, c("ph.month"))}
              aria-label={c("f.ship_month")}
            />
          </Field>
          <Field label={c("f.buyer")}>
            <PSelect
              value={f.buyer}
              onValueChange={(v) => (setF("buyer", v), setF("order", ""))}
              options={pick(options.buyers, c("ph.select"))}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.style_order_hash")}>
            <PSelect
              value={f.order}
              onValueChange={(v) => setF("order", v)}
              options={pick(
                options.orders.filter(
                  (o) => !f.buyer || o.buyer_id === f.buyer,
                ),
                c("ph.order"),
              )}
              aria-label={c("f.style_order_hash")}
            />
          </Field>
          <Field label={c("f.program_hash")}>
            <PSelect
              value={f.program}
              onValueChange={(v) => setF("program", v)}
              options={pick(options.programs, c("ph.select"))}
              aria-label={c("f.program_hash")}
            />
          </Field>
          <Field label={c("f.party_supplier")}>
            <PSelect
              value={f.party}
              onValueChange={(v) => setF("party", v)}
              options={pick(options.suppliers, c("ph.select"))}
              aria-label={c("f.party_supplier")}
            />
          </Field>
          <Field label={c("f.program_status")}>
            <PSelect
              value={f.status}
              onValueChange={(v) => setF("status", v)}
              options={pick(statusOptions(c, options.statuses), c("ph.select"))}
              aria-label={c("f.program_status")}
            />
          </Field>
        </div>
        <div className="mt-4 flex flex-wrap justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => (setFilters(blank), load(blank))}
            disabled={pending}
          >
            <RotateCcw /> {c("btn.reset")}
          </Button>
          <Button variant="outline" size="sm" onClick={print}>
            <Printer /> {c("btn.print")}
          </Button>
          <Button size="sm" onClick={() => load(f)} loading={pending}>
            <Search /> {c("btn.show")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>
        <div className="px-5 pt-4">
          <h2 className="text-base font-semibold text-foreground">
            {c("sec.statement")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {c("hint.statement")}
          </p>
        </div>
        <ScTable
          rows={rows}
          columns={columns}
          exportName="knitting-yd-statement"
          empty={
            <NoLines title={c("empty.statement")} hint={c("empty.list_hint")} />
          }
          pending={pending}
        />
        {rows.length ? (
          <div className="flex flex-wrap justify-end gap-6 border-t border-border px-5 py-3 text-sm">
            {[
              ["col.greige_issue", "issue"],
              ["col.dyed_recv", "delivery"],
              ["col.process_loss", "loss"],
              ["col.with_party", "balance"],
            ].map(([label, k]) => (
              <span key={k}>
                <span className="text-foreground-muted">{c(label)}: </span>
                <span className="font-semibold tabular-nums">
                  {qtyFmt.format(sum(k))}
                </span>
              </span>
            ))}
          </div>
        ) : null}
      </section>
    </div>
  );
}

export {
  useShowFilters,
  SideList,
  useMoves,
  ListShell,
  MoveButton,
  FormHead,
  FormFoot,
  RemoveCell,
  YdReceiptList,
  YdReceiptEntry,
  LossList,
  LossEntry,
  RpList,
  RpEntry,
  ScChallanList,
  ScChallanEntry,
  YdStatement,
};
