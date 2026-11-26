"use client";

import { useRef, useState, useTransition } from "react";
import Link from "next/link";
import {
  Ban,
  Check,
  CircleAlert,
  PackageCheck,
  Plus,
  Printer,
  Send,
  Undo2,
  X,
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

/*
 * Knit Production → Fabric Store (AnnotechERP 15/05), the sub-contract grey
 * challans: Out-House Grey Receipt Challan (and the Collar & Cuff QC desk
 * over it), the out-house and in-house Grey Return Challans, and In-House
 * Grey Delivery Challan (fabric, collar / cuff and the party's yarn on one
 * lorry). A challan travels by its number; nothing reloads.
 */

const BASE = {
  receipt: "/knit-production/grey-fabric-receive-challan-sc-out-house",
  OUT_HOUSE: "/knit-production/grey-fabric-return-challan-sc-out-house",
  IN_HOUSE: "/knit-production/grey-fabric-return-challan-sc-in-house",
  delivery: "/knit-production/grey-fabric-delivery-challan-sc-in-house",
};
const SLUG = { OUT_HOUSE: "out-house", IN_HOUSE: "in-house" };
const TONE = {
  DRAFT: "DRAFT",
  RECEIVED: "PLACED",
  QC_PASSED: "APPROVED",
  QC_HOLD: "WAITING_APPROVAL",
  QC_REJECTED: "REJECTED",
  CANCELLED: "CANCELLED",
  ISSUED: "PLACED",
  RECEIVED_BACK: "RECEIVED",
};
const enc = encodeURIComponent;
const numIn = { type: "number", min: "0", step: "any" };
let seq = 0;
const lineKey = () => `s${++seq}`;

function useScg() {
  const t = useT("knitting");
  return (k, p) => t(`scg.${k}`, p);
}

const statusOpts = (c, list, group) =>
  list.map((s) => ({ value: s, label: c(`${group}.${s}`) }));
const typeOpts = (c, options) =>
  options.types.map((x) => ({ value: x, label: c(`type.${x}`) }));

/** Meta pairs a challan prints with. */
function printChallan(c, title, docNo, meta, sections, notes, signatures) {
  printDocument({
    title,
    docNo,
    orientation: "landscape",
    meta,
    metaColumns: 4,
    sections,
    notes,
    signatures,
  });
}

/** Totals per unit — kilograms and pieces never add up to one figure. */
const perUnit = (lines) =>
  Object.entries(
    lines.reduce(
      (a, l) => ({ ...a, [l.uom]: (a[l.uom] ?? 0) + num(l.qty) }),
      {},
    ),
  )
    .map(([u, q]) => `${qtyFmt.format(q)} ${u}`)
    .join(" · ");

function lineSection(c, title, lines, cols) {
  return {
    title,
    columns: [
      { label: "#", align: "center", width: "4%" },
      ...cols.map(([label, , right]) => ({
        label,
        align: right ? "right" : undefined,
      })),
      { label: c("col.qty"), align: "right" },
    ],
    rows: lines.map((l, i) => [
      i + 1,
      ...cols.map(([, get]) => get(l) ?? "—"),
      `${qtyFmt.format(l.qty)} ${l.uom}`,
    ]),
    foot: [
      [
        "",
        { v: c("total"), bold: true },
        ...Array(cols.length - 1).fill(""),
        { v: perUnit(lines), bold: true },
      ],
    ],
    empty: c("p.no_lines"),
  };
}

/* ---------------- Out-House Grey Receipt Challan ---------------- */

function printReceipt(c, d) {
  printChallan(
    c,
    c("p.receipt"),
    d.rcv_no,
    [
      [c("f.rcv_date"), dayFmt(d.rcv_date)],
      [c("f.party"), d.party],
      [c("p.address"), d.party_address],
      [c("f.status"), { v: c(`receipt_status.${d.status}`), tone: "info" }],
      [c("f.challan_no"), d.challan_no],
      [c("f.challan_date"), dayFmt(d.challan_date)],
      [c("f.order"), d.order],
      [c("f.store"), d.store],
      [c("f.gate_pass"), d.gate_pass_no],
      [c("f.vehicle"), d.vehicle_no],
    ],
    [
      lineSection(c, c("sec.detail"), d.lines, [
        [c("col.type"), (l) => c(`type.${l.type}`)],
        [c("col.description"), (l) => ({ v: l.description, bold: true })],
        [c("col.colour"), (l) => l.colour_name],
        [c("col.shade"), (l) => l.shade],
        [c("col.roll_no"), (l) => l.roll_no],
        [c("col.dia"), (l) => l.finish_dia],
        [c("col.gsm"), (l) => l.gsm],
        [c("col.r_qty"), (l) => l.r_qty, true],
      ]),
    ],
    [d.note],
    [c("p.received_by"), c("p.qc"), c("p.store")],
  );
}

function ScReceiptList({ options, initialRows, canManage, act, qc = false }) {
  const c = useScg();
  const flt = useShowFilters({ from: "", to: "", status: "" });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "sc/receipts",
    "rcv_no",
    setRows,
    setError,
  );
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.from || r.rcv_date >= o.from) &&
      (!o.to || r.rcv_date <= o.to) &&
      (!o.status || r.status === o.status),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`sc/receipts/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printReceipt(c, res.data);
    });
  }
  const columns = [
    {
      key: "rcv_no",
      label: c("col.rcv_no"),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          {qc ? (
            <span className="font-medium">{r.rcv_no}</span>
          ) : (
            <Link
              href={`${BASE.receipt}?tab=entry&ref=${enc(r.rcv_no)}`}
              className="font-medium text-brand hover:underline"
            >
              {r.rcv_no}
            </Link>
          )}
          <StatusChip
            status={TONE[r.status]}
            label={c(`receipt_status.${r.status}`)}
          />
        </span>
      ),
      text: (r) => r.rcv_no,
    },
    {
      key: "rcv_date",
      label: c("col.rcv_date"),
      nowrap: true,
      text: (r) => dayFmt(r.rcv_date),
    },
    { key: "party", label: c("col.party") },
    { key: "challan_no", label: c("col.challan_no") },
    {
      key: "challan_date",
      label: c("col.challan_date"),
      nowrap: true,
      text: (r) => dayFmt(r.challan_date),
    },
    { key: "order", label: c("col.order") },
    { key: "store", label: c("col.store") },
    { key: "rolls", label: c("col.rolls"), num: true },
    {
      key: "fabric",
      label: c("col.fabric_kg"),
      num: true,
      text: (r) => qtyFmt.format(r.fabric),
    },
    {
      key: "pieces",
      label: c("col.pieces"),
      num: true,
      text: (r) => qtyFmt.format(r.pieces),
    },
  ];
  const qcOpen = (s) => ["RECEIVED", "QC_HOLD"].includes(s);

  return (
    <ListShell
      c={c}
      error={error}
      onShow={flt.show}
      onReset={flt.reset}
      extra={
        canManage && !qc ? (
          <Link
            href={`${BASE.receipt}?tab=entry`}
            className={buttonVariants({ variant: "outline", size: "sm" })}
          >
            <Plus /> {c("btn.add_new")}
          </Link>
        ) : null
      }
      filters={
        <>
          {qc ? null : (
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
          )}
          <Field label={c("f.status")}>
            <PSelect
              value={flt.f.status}
              onValueChange={(v) => flt.set("status", v)}
              options={pick(
                statusOpts(c, options.receipt_statuses, "receipt_status"),
                c("ph.select"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c(qc ? "hint.qc" : "hint.receipt_list")}
      </p>
      <ScTable
        rows={shown}
        columns={columns}
        exportName={qc ? "collar-cuff-qc" : "sc-grey-receipts"}
        addHref={canManage && !qc ? `${BASE.receipt}?tab=entry` : null}
        empty={
          <NoLines
            title={c(qc ? "empty.qc" : "empty.receipts")}
            hint={c("empty.hint")}
          />
        }
        pending={pending}
        actions={(r) => (
          <>
            {canManage && !qc && r.status === "DRAFT" ? (
              <MoveButton
                icon={PackageCheck}
                label={c("btn.receive")}
                no={r.rcv_no}
                onClick={() => move(r.rcv_no, "receive", act)}
                disabled={pending}
              />
            ) : null}
            {canManage && qc && qcOpen(r.status) ? (
              <>
                <MoveButton
                  icon={Check}
                  label={c("btn.qc-pass")}
                  no={r.rcv_no}
                  onClick={() => move(r.rcv_no, "qc-pass", act)}
                  disabled={pending}
                />
                {r.status === "RECEIVED" ? (
                  <MoveButton
                    icon={CircleAlert}
                    label={c("btn.qc-hold")}
                    no={r.rcv_no}
                    onClick={() => move(r.rcv_no, "qc-hold", act)}
                    disabled={pending}
                  />
                ) : null}
                <MoveButton
                  icon={X}
                  label={c("btn.qc-reject")}
                  no={r.rcv_no}
                  onClick={() => move(r.rcv_no, "qc-reject", act)}
                  disabled={pending}
                  danger
                />
              </>
            ) : null}
            {canManage && !qc && ["DRAFT", "RECEIVED"].includes(r.status) ? (
              <MoveButton
                icon={Ban}
                label={c("btn.cancel")}
                no={r.rcv_no}
                onClick={() => move(r.rcv_no, "cancel", act)}
                disabled={pending}
                danger
              />
            ) : null}
            <MoveButton
              icon={Printer}
              label={c("btn.print")}
              no={r.rcv_no}
              onClick={() => print(r.rcv_no)}
              disabled={pending}
            />
          </>
        )}
      />
    </ListShell>
  );
}

const RLINE = [
  "type",
  "fabric_type_id",
  "description",
  "colour_id",
  "colour_name",
  "shade",
  "roll_no",
  "finish_dia",
  "gsm",
  "r_qty",
  "qty",
  "uom",
  "remarks",
];
const RHEAD = [
  "rcv_date",
  "party_id",
  "store_id",
  "challan_no",
  "challan_date",
  "order_id",
  "gate_pass_no",
  "vehicle_no",
  "note",
];

function ColourCell({ c, options, l, set, open, n }) {
  return (
    <div className="flex gap-1">
      <PSelect
        value={l.colour_id}
        onValueChange={(v) =>
          set({
            colour_id: v ?? "",
            colour_name:
              options.colours.find((x) => x.value === v)?.name ?? l.colour_name,
          })
        }
        options={pick(options.colours, c("ph.colour"))}
        disabled={!open}
        aria-label={`${c("col.colour")} ${n}`}
      />
    </div>
  );
}

function ScReceiptEntry({ options, initial, canManage, act }) {
  const c = useScg();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    ...Object.fromEntries(RHEAD.map((k) => [k, str(d?.[k])])),
    rcv_date: str(d?.rcv_date) || today,
    challan_date: str(d?.challan_date) || today,
  });
  const asLine = (l = {}) => ({
    key: lineKey(),
    ...Object.fromEntries(RLINE.map((k) => [k, str(l[k])])),
    type: str(l.type) || "FABRIC",
    uom: str(l.uom) || "KG",
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

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${enc(d.rcv_no)}` : "?tab=entry",
    );
  }
  function load(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`sc/receipts/${enc(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          RHEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        lines: lines.map((l) => ({
          ...Object.fromEntries(
            RLINE.map((k) => [k, l[k] === "" ? null : l[k]]),
          ),
          qty: orNull(l.qty),
          gsm: orNull(l.gsm),
          r_qty: orNull(l.r_qty),
        })),
      };
      const res = await act(
        doc ? `sc/receipts/${enc(doc.rcv_no)}` : "sc/receipts",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.saved", { no: res.data.rcv_no }),
        type: "success",
      });
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={doc ? doc.rcv_no : c("tabs.receipt_entry")}
          hint={c("hint.receipt_form")}
          doc={doc}
          status={doc?.status}
          group="receipt_status"
        />
        {doc && !open && canManage ? (
          <Alert variant="info" className="mt-3">
            {c("hint.locked", {
              no: doc.rcv_no,
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
            value={doc?.rcv_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={pick(options.docs ?? [], c("ph.new_receipt"))}
            disabled={pending}
            aria-label={c("f.existing_receipt")}
          />
        </Field>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.key")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.rcv_no")} hint={c("hint.rcv_no")}>
            <Input
              value={doc?.rcv_no ?? ""}
              placeholder={c("ph.auto")}
              disabled
              aria-label={c("f.rcv_no")}
            />
          </Field>
          <Field label={c("f.rcv_date")} required>
            <Input
              type="date"
              value={head.rcv_date}
              onChange={(e) => setH({ rcv_date: e.target.value })}
              disabled={!open}
              aria-label={c("f.rcv_date")}
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
          <Field label={c("f.party")} required hint={c("hint.party_out")}>
            <PSelect
              value={head.party_id}
              onValueChange={(v) => setH({ party_id: v ?? "" })}
              options={pick(options.suppliers, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.party")}
            />
          </Field>
          <Field label={c("f.store")} hint={c("hint.store_rcv")}>
            <PSelect
              value={head.store_id}
              onValueChange={(v) => setH({ store_id: v ?? "" })}
              options={pick(options.stores, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.store")}
            />
          </Field>
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.party_challan")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.challan_no")} required>
            <Input
              value={head.challan_no}
              onChange={(e) => setH({ challan_no: e.target.value })}
              placeholder={c("ph.challan")}
              maxLength={40}
              disabled={!open}
              aria-label={c("f.challan_no")}
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
          <Field label={c("f.order")}>
            <PSelect
              value={head.order_id}
              onValueChange={(v) => setH({ order_id: v ?? "" })}
              options={pick(options.orders, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.order")}
            />
          </Field>
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.gate")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
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
        <Field label={c("f.note")} className="mt-4">
          <Textarea
            rows={2}
            value={head.note}
            onChange={(e) => setH({ note: e.target.value })}
            maxLength={2000}
            disabled={!open}
            aria-label={c("f.note")}
          />
        </Field>
      </section>
      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold">{c("sec.detail")}</h3>
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
              <Plus /> {c("btn.add_line")}
            </Button>
          ) : null}
        </div>
        <div className="mt-4 overflow-x-auto rounded-lg border border-border">
          {lines.length === 0 ? (
            <NoLines title={c("empty.lines")} hint={c("empty.receipt_lines")} />
          ) : (
            <table className="w-full min-w-[1600px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "type",
                    "fab_type",
                    "description",
                    "colour",
                    "shade",
                    "roll_no",
                    "dia",
                    "gsm",
                    "r_qty",
                    "qty",
                    "uom",
                    "remarks",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        ["r_qty", "qty"].includes(k) && "text-right",
                      )}
                    >
                      {c(`col.${k}`)}
                    </th>
                  ))}
                  <th className={th} />
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={l.key} className="border-t border-border">
                    <td className="min-w-[110px] px-2 py-1.5">
                      <PSelect
                        value={l.type}
                        onValueChange={(v) =>
                          setL(l.key, {
                            type: v ?? "FABRIC",
                            uom: v && v !== "FABRIC" ? "PCS" : "KG",
                          })
                        }
                        options={typeOpts(c, options)}
                        disabled={!open}
                        aria-label={`${c("col.type")} ${i + 1}`}
                      />
                    </td>
                    <td className="min-w-[150px] px-2 py-1.5">
                      <PSelect
                        value={l.fabric_type_id}
                        onValueChange={(v) =>
                          setL(l.key, { fabric_type_id: v ?? "" })
                        }
                        options={pick(options.fabric_types, "—")}
                        disabled={!open}
                        aria-label={`${c("col.fab_type")} ${i + 1}`}
                      />
                    </td>
                    <td className="min-w-[200px] px-2 py-1.5">
                      <Input
                        value={l.description}
                        onChange={(e) =>
                          setL(l.key, { description: e.target.value })
                        }
                        maxLength={160}
                        disabled={!open}
                        aria-label={`${c("col.description")} ${i + 1}`}
                      />
                    </td>
                    <td className="min-w-[150px] px-2 py-1.5">
                      <ColourCell
                        c={c}
                        options={options}
                        l={l}
                        set={(p) => setL(l.key, p)}
                        open={open}
                        n={i + 1}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={l.shade}
                        onChange={(e) => setL(l.key, { shade: e.target.value })}
                        maxLength={40}
                        disabled={!open}
                        className="w-20"
                        aria-label={`${c("col.shade")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={l.roll_no}
                        onChange={(e) =>
                          setL(l.key, { roll_no: e.target.value })
                        }
                        maxLength={40}
                        disabled={!open}
                        className="w-28"
                        aria-label={`${c("col.roll_no")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={l.finish_dia}
                        onChange={(e) =>
                          setL(l.key, { finish_dia: e.target.value })
                        }
                        maxLength={24}
                        disabled={!open}
                        className="w-20"
                        aria-label={`${c("col.dia")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        {...numIn}
                        value={l.gsm}
                        onChange={(e) => setL(l.key, { gsm: e.target.value })}
                        disabled={!open}
                        className="w-20"
                        aria-label={`${c("col.gsm")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        {...numIn}
                        step="1"
                        value={l.r_qty}
                        onChange={(e) => setL(l.key, { r_qty: e.target.value })}
                        disabled={!open}
                        className="w-20 text-right"
                        aria-label={`${c("col.r_qty")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        {...numIn}
                        value={l.qty}
                        onChange={(e) => setL(l.key, { qty: e.target.value })}
                        disabled={!open}
                        className="w-24 text-right"
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
                    <td className="min-w-[140px] px-2 py-1.5">
                      <Input
                        value={l.remarks}
                        onChange={(e) =>
                          setL(l.key, { remarks: e.target.value })
                        }
                        maxLength={300}
                        disabled={!open}
                        aria-label={`${c("col.remarks")} ${i + 1}`}
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
                  <td className="px-2 py-2" colSpan={8}>
                    {c("total")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {lines.reduce((a, l) => a + num(l.r_qty), 0) || "—"}
                  </td>
                  <td className="px-2 py-2 text-right whitespace-nowrap tabular-nums">
                    {perUnit(lines)}
                  </td>
                  <td colSpan={3} />
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
  );
}

/* ---------------- Grey Return Challan ---------------- */

function printReturn(c, d) {
  printChallan(
    c,
    c("p.return"),
    d.challan_no,
    [
      [c("f.challan_date"), dayFmt(d.challan_date)],
      [c("f.party"), d.party],
      [c("p.address"), d.party_address],
      [c("f.status"), { v: c(`return_status.${d.status}`), tone: "info" }],
      [c("f.store"), d.store],
      [c("f.order"), d.order],
      [c("f.against"), d.against],
      [c("f.reason"), d.reason],
      [c("f.gate_pass"), d.gate_pass_no],
      [c("f.vehicle"), d.vehicle_no],
      [c("f.driver"), d.driver],
    ],
    [
      lineSection(c, c("sec.return_detail"), d.lines, [
        [c("col.type"), (l) => c(`type.${l.type}`)],
        [c("col.description"), (l) => ({ v: l.description, bold: true })],
        [c("col.colour"), (l) => l.colour_name],
        [c("col.shade"), (l) => l.shade],
        [c("col.roll_no"), (l) => l.roll_no],
        [c("col.r_qty"), (l) => l.r_qty, true],
      ]),
    ],
    [d.note],
    [c("p.store"), c("p.driver"), c("p.received_by")],
  );
}

function ScReturnList({ kind, options, initialRows, canManage, act }) {
  const c = useScg();
  const flt = useShowFilters({ status: "", challan: "" });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const path = `sc/returns/${SLUG[kind]}`;
  const [move, pending, startTransition] = useMoves(
    c,
    path,
    "challan_no",
    setRows,
    setError,
  );
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.status || r.status === o.status) &&
      (!o.challan ||
        r.challan_no.toLowerCase().includes(o.challan.toLowerCase())),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`${path}/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printReturn(c, res.data);
    });
  }
  const columns = [
    {
      key: "challan_no",
      label: c("col.challan_no"),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${BASE[kind]}?tab=entry&ref=${enc(r.challan_no)}`}
            className="font-medium text-brand hover:underline"
          >
            {r.challan_no}
          </Link>
          <StatusChip
            status={TONE[r.status]}
            label={c(`return_status.${r.status}`)}
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
    { key: "party", label: c("col.party") },
    { key: "store", label: c("col.store") },
    { key: "order", label: c("col.order") },
    { key: "against", label: c("col.against") },
    { key: "reason", label: c("col.reason") },
    { key: "rolls", label: c("col.rolls"), num: true },
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
      extra={
        canManage ? (
          <Link
            href={`${BASE[kind]}?tab=entry`}
            className={buttonVariants({ variant: "outline", size: "sm" })}
          >
            <Plus /> {c("btn.add_new")}
          </Link>
        ) : null
      }
      filters={
        <>
          <Field label={c("f.status")}>
            <PSelect
              value={flt.f.status}
              onValueChange={(v) => flt.set("status", v)}
              options={pick(
                statusOpts(c, options.return_statuses, "return_status"),
                c("ph.select"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <Field label={c("f.challan_no")}>
            <Input
              value={flt.f.challan}
              onChange={(e) => flt.set("challan", e.target.value)}
              aria-label={c("f.challan_no")}
            />
          </Field>
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.ret_list")}
      </p>
      <ScTable
        rows={shown}
        columns={columns}
        exportName={`grey-return-${SLUG[kind]}`}
        addHref={canManage ? `${BASE[kind]}?tab=entry` : null}
        empty={<NoLines title={c("empty.returns")} hint={c("empty.hint")} />}
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
              <MoveButton
                icon={Undo2}
                label={c("btn.received-back")}
                no={r.challan_no}
                onClick={() => move(r.challan_no, "received-back", act)}
                disabled={pending}
              />
            ) : null}
            {canManage && ["DRAFT", "ISSUED"].includes(r.status) ? (
              <MoveButton
                icon={Ban}
                label={c("btn.cancel")}
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
    </ListShell>
  );
}

const TLINE = [
  "type",
  "lot_id",
  "description",
  "colour_id",
  "colour_name",
  "shade",
  "roll_no",
  "r_qty",
  "qty",
  "uom",
  "remarks",
];
const THEAD = [
  "challan_date",
  "party_id",
  "store_id",
  "order_id",
  "against",
  "reason",
  "gate_pass_no",
  "vehicle_no",
  "driver",
  "note",
];

/** Only the latest of two quick fetches counts (a store and a party picked together). */
function useLatest(act, setter) {
  const ticket = useRef(0);
  const [, startTransition] = useTransition();
  return (path) => {
    const mine = ++ticket.current;
    if (!path) return setter([]);
    startTransition(async () => {
      const res = await act(path);
      if (res?.status === "success" && mine === ticket.current)
        setter(res.data);
    });
  };
}

function ScReturnEntry({
  kind,
  options,
  initial,
  initialLots,
  canManage,
  act,
}) {
  const c = useScg();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    ...Object.fromEntries(THEAD.map((k) => [k, str(d?.[k])])),
    challan_date: str(d?.challan_date) || today,
  });
  const asLine = (l = {}) => ({
    key: lineKey(),
    ...Object.fromEntries(TLINE.map((k) => [k, str(l[k])])),
    type: str(l.type) || "FABRIC",
    uom: str(l.uom) || "KG",
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [lots, setLots] = useState(initialLots ?? []);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const fetchLots = useLatest(act, setLots);
  const open = canManage && (!doc || doc.status === "DRAFT");
  const setL = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  function setH(patch) {
    const next = { ...head, ...patch };
    setHead(next);
    if ("store_id" in patch) {
      fetchLots(next.store_id ? `lots?store=${enc(next.store_id)}` : null);
      setLines((all) => all.map((l) => ({ ...l, lot_id: "" })));
    }
  }
  const against = kind === "OUT_HOUSE" ? options.receipts : options.sc_receipts;
  const lotOpts = lots.map((l) => ({
    value: l.lot_id,
    label: `${l.label} · ${qtyFmt.format(l.balance)}`,
  }));

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setErrors(null);
    if (d?.store_id) fetchLots(`lots?store=${enc(d.store_id)}`);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${enc(d.challan_no)}` : "?tab=entry",
    );
  }
  function load(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`sc/returns/${SLUG[kind]}/${enc(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function pickLot(k, id) {
    const l = lots.find((x) => x.lot_id === id);
    setL(
      k,
      l
        ? {
            lot_id: id,
            description: l.fabric,
            colour_id: str(l.colour_id),
            colour_name: str(l.colour),
            roll_no: str(l.roll_no),
            r_qty: l.roll_id ? "1" : "",
            qty: String(l.balance),
          }
        : { lot_id: "" },
    );
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          THEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        lines: lines.map((l) => ({
          ...Object.fromEntries(
            TLINE.map((k) => [k, l[k] === "" ? null : l[k]]),
          ),
          qty: orNull(l.qty),
          r_qty: orNull(l.r_qty),
        })),
      };
      const path = `sc/returns/${SLUG[kind]}`;
      const res = await act(
        doc ? `${path}/${enc(doc.challan_no)}` : path,
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
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={doc ? doc.challan_no : c("tabs.ret_entry")}
          hint={c(`hint.ret_form.${kind}`)}
          doc={doc}
          status={doc?.status}
          group="return_status"
        />
        {doc && !open && canManage ? (
          <Alert variant="info" className="mt-3">
            {c("hint.locked", {
              no: doc.challan_no,
              status: c(`return_status.${doc.status}`),
            })}
          </Alert>
        ) : null}
        <Field
          label={c("f.existing_return")}
          hint={c("hint.existing")}
          className="mt-4"
        >
          <PSelect
            value={doc?.challan_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={pick(options.docs ?? [], c("ph.new_return"))}
            disabled={pending}
            aria-label={c("f.existing_return")}
          />
        </Field>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.challan_info")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.challan_no")} hint={c("hint.rcv_no")}>
            <Input
              value={doc?.challan_no ?? ""}
              placeholder={c("ph.auto")}
              disabled
              aria-label={c("f.challan_no")}
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
          <Field label={c("f.company")} required>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={c("f.company")}
            />
          </Field>
          <Field label={c("f.party")} required hint={c("hint.party_ret")}>
            <PSelect
              value={head.party_id}
              onValueChange={(v) => setH({ party_id: v ?? "", against: "" })}
              options={pick(options.suppliers, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.party")}
            />
          </Field>
          <Field label={c("f.store")} hint={c("hint.store_ret")}>
            <PSelect
              value={head.store_id}
              onValueChange={(v) => setH({ store_id: v ?? "" })}
              options={pick(options.stores, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.store")}
            />
          </Field>
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.against")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          <Field label={c("f.order")}>
            <PSelect
              value={head.order_id}
              onValueChange={(v) => setH({ order_id: v ?? "" })}
              options={pick(options.orders, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.order")}
            />
          </Field>
          <Field label={c("f.against")} hint={c(`hint.against.${kind}`)}>
            <PSelect
              value={head.against}
              onValueChange={(v) => setH({ against: v ?? "" })}
              options={pick(
                against.filter(
                  (a) => !head.party_id || a.party_id === head.party_id,
                ),
                c("ph.select"),
              )}
              disabled={!open}
              aria-label={c("f.against")}
            />
          </Field>
          <Field label={c("f.reason")}>
            <Input
              value={head.reason}
              onChange={(e) => setH({ reason: e.target.value })}
              placeholder={c("ph.reason")}
              maxLength={255}
              disabled={!open}
              aria-label={c("f.reason")}
            />
          </Field>
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.gate")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
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
          <Field label={c("f.driver")}>
            <Input
              value={head.driver}
              onChange={(e) => setH({ driver: e.target.value })}
              maxLength={80}
              disabled={!open}
              aria-label={c("f.driver")}
            />
          </Field>
        </div>
        <Field label={c("f.note")} className="mt-4">
          <Textarea
            rows={2}
            value={head.note}
            onChange={(e) => setH({ note: e.target.value })}
            maxLength={2000}
            disabled={!open}
            aria-label={c("f.note")}
          />
        </Field>
      </section>
      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold">
              {c("sec.return_detail")}
            </h3>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {c("hint.ret_lines")}
            </p>
          </div>
          {open ? (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setLines((all) => [...all, asLine()])}
            >
              <Plus /> {c("btn.add_line")}
            </Button>
          ) : null}
        </div>
        <div className="mt-4 overflow-x-auto rounded-lg border border-border">
          {lines.length === 0 ? (
            <NoLines title={c("empty.lines")} hint={c("empty.return_lines")} />
          ) : (
            <table className="w-full min-w-[1500px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "type",
                    "roll",
                    "description",
                    "colour",
                    "shade",
                    "roll_no",
                    "r_qty",
                    "qty",
                    "uom",
                    "remarks",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        ["r_qty", "qty"].includes(k) && "text-right",
                      )}
                    >
                      {c(`col.${k}`)}
                    </th>
                  ))}
                  <th className={th} />
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => {
                  const fixed = Boolean(l.lot_id);
                  return (
                    <tr key={l.key} className="border-t border-border">
                      <td className="min-w-[110px] px-2 py-1.5">
                        <PSelect
                          value={l.type}
                          onValueChange={(v) =>
                            setL(l.key, { type: v ?? "FABRIC" })
                          }
                          options={typeOpts(c, options)}
                          disabled={!open}
                          aria-label={`${c("col.type")} ${i + 1}`}
                        />
                      </td>
                      <td className="min-w-[280px] px-2 py-1.5">
                        <PSelect
                          value={l.lot_id}
                          onValueChange={(v) => pickLot(l.key, v ?? "")}
                          options={pick(lotOpts, c("ph.no_roll"))}
                          disabled={!open || !head.store_id}
                          aria-label={`${c("col.roll")} ${i + 1}`}
                        />
                      </td>
                      <td className="min-w-[180px] px-2 py-1.5">
                        <Input
                          value={l.description}
                          onChange={(e) =>
                            setL(l.key, { description: e.target.value })
                          }
                          maxLength={160}
                          disabled={!open}
                          aria-label={`${c("col.description")} ${i + 1}`}
                        />
                      </td>
                      <td className="min-w-[150px] px-2 py-1.5">
                        <ColourCell
                          c={c}
                          options={options}
                          l={l}
                          set={(p) => setL(l.key, p)}
                          open={open && !fixed}
                          n={i + 1}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          value={l.shade}
                          onChange={(e) =>
                            setL(l.key, { shade: e.target.value })
                          }
                          maxLength={40}
                          disabled={!open}
                          className="w-20"
                          aria-label={`${c("col.shade")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          value={l.roll_no}
                          onChange={(e) =>
                            setL(l.key, { roll_no: e.target.value })
                          }
                          maxLength={40}
                          disabled={!open || fixed}
                          className="w-32"
                          aria-label={`${c("col.roll_no")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          {...numIn}
                          step="1"
                          value={l.r_qty}
                          onChange={(e) =>
                            setL(l.key, { r_qty: e.target.value })
                          }
                          disabled={!open || fixed}
                          className="w-20 text-right"
                          aria-label={`${c("col.r_qty")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          {...numIn}
                          value={l.qty}
                          onChange={(e) => setL(l.key, { qty: e.target.value })}
                          disabled={!open}
                          className="w-24 text-right"
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
                      <td className="min-w-[140px] px-2 py-1.5">
                        <Input
                          value={l.remarks}
                          onChange={(e) =>
                            setL(l.key, { remarks: e.target.value })
                          }
                          maxLength={300}
                          disabled={!open}
                          aria-label={`${c("col.remarks")} ${i + 1}`}
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
                  );
                })}
              </tbody>
            </table>
          )}
        </div>
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <FormFoot
        c={c}
        hint={c("hint.ret_foot")}
        back={BASE[kind]}
        onClear={() => show(null)}
        onPrint={doc ? () => printReturn(c, doc) : null}
        onSave={save}
        saveLabel={c("btn.save_return")}
        pending={pending}
        open={open}
      />
    </div>
  );
}

/* ---------------- In-House Grey Delivery Challan ---------------- */

function printDelivery(c, d) {
  const of = (s) => d.lines.filter((l) => l.section === s);
  const grey = [
    [c("col.roll_no"), (l) => ({ v: l.roll_no, bold: true })],
    [c("col.description"), (l) => l.description],
    [c("col.colour"), (l) => l.colour_name],
    [c("col.shade"), (l) => l.shade],
    [c("col.dia"), (l) => l.finish_dia],
    [c("col.mc_gg"), (l) => l.mc_gauge],
    [c("col.r_qty"), (l) => l.r_qty, true],
  ];
  printChallan(
    c,
    c("p.delivery"),
    d.challan_no,
    [
      [c("f.chln_date"), dayFmt(d.challan_date)],
      [c("f.party"), d.party],
      [c("p.address"), d.party_address],
      [c("f.status"), { v: c(`delivery_status.${d.status}`), tone: "info" }],
      [c("f.store"), d.store],
      [c("f.gate_pass"), d.gate_pass_no],
      [c("f.vehicle"), d.vehicle_no],
      [c("f.driver"), d.driver],
      [c("f.receiver"), d.receiver],
      [c("f.receiver_mobile"), d.receiver_mobile],
    ],
    [
      ...(of("FABRIC").length
        ? [lineSection(c, c("sec.fabric"), of("FABRIC"), grey)]
        : []),
      ...(of("COLLAR_CUFF").length
        ? [
            lineSection(c, c("sec.collar"), of("COLLAR_CUFF"), [
              [c("col.part"), (l) => c(`type.${l.part}`)],
              ...grey,
            ]),
          ]
        : []),
      ...(of("YARN").length
        ? [
            lineSection(c, c("sec.yarn"), of("YARN"), [
              [c("col.yarn"), (l) => ({ v: l.description, bold: true })],
              [c("col.colour"), (l) => l.colour_name],
              [c("col.shade"), (l) => l.shade],
              [c("col.lot"), (l) => l.lot_no],
              [c("col.bags"), (l) => l.bags, true],
            ]),
          ]
        : []),
    ],
    [d.note],
    [c("p.store"), c("p.security"), c("p.received_by")],
  );
}

function ScDeliveryList({ options, initialRows, canManage, act }) {
  const c = useScg();
  const flt = useShowFilters({ party: "", status: "", challan: "" });
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [move, pending, startTransition] = useMoves(
    c,
    "sc/deliveries",
    "challan_no",
    setRows,
    setError,
  );
  const o = flt.on;
  const shown = rows.filter(
    (r) =>
      (!o.party || r.party_id === o.party) &&
      (!o.status || r.status === o.status) &&
      (!o.challan ||
        r.challan_no.toLowerCase().includes(o.challan.toLowerCase())),
  );
  function print(no) {
    startTransition(async () => {
      const res = await act(`sc/deliveries/${enc(no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printDelivery(c, res.data);
    });
  }
  const columns = [
    {
      key: "challan_no",
      label: c("col.challan_no"),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${BASE.delivery}?tab=entry&ref=${enc(r.challan_no)}`}
            className="font-medium text-brand hover:underline"
          >
            {r.challan_no}
          </Link>
          <StatusChip
            status={TONE[r.status]}
            label={c(`delivery_status.${r.status}`)}
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
    { key: "party", label: c("col.party") },
    { key: "store", label: c("col.store") },
    { key: "rolls", label: c("col.rolls"), num: true },
    {
      key: "fabric",
      label: c("col.fabric_kg"),
      num: true,
      text: (r) => qtyFmt.format(r.fabric),
    },
    {
      key: "pieces",
      label: c("col.pieces"),
      num: true,
      text: (r) => qtyFmt.format(r.pieces),
    },
    {
      key: "yarn",
      label: c("col.yarn_kg"),
      num: true,
      text: (r) => qtyFmt.format(r.yarn),
    },
    { key: "receiver", label: c("col.receiver") },
    { key: "gate_pass_no", label: c("col.gate_pass") },
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
                statusOpts(c, options.delivery_statuses, "delivery_status"),
                c("ph.select"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <Field label={c("f.challan_no")}>
            <Input
              value={flt.f.challan}
              onChange={(e) => flt.set("challan", e.target.value)}
              aria-label={c("f.challan_no")}
            />
          </Field>
        </>
      }
    >
      <p className="px-5 pt-4 text-[13px] text-foreground-muted">
        {c("hint.delivery_list")}
      </p>
      <ScTable
        rows={shown}
        columns={columns}
        exportName="grey-deliveries"
        addHref={canManage ? `${BASE.delivery}?tab=entry` : null}
        empty={<NoLines title={c("empty.deliveries")} hint={c("empty.hint")} />}
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
            {canManage && r.status !== "CANCELLED" ? (
              <MoveButton
                icon={Ban}
                label={c("btn.cancel")}
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
    </ListShell>
  );
}

const DLINE = [
  "section",
  "part",
  "lot_id",
  "yarn_lot_id",
  "shade",
  "mc_gauge",
  "bags",
  "qty",
  "uom",
  "remarks",
];
const DHEAD = [
  "challan_date",
  "party_id",
  "store_id",
  "gate_pass_no",
  "vehicle_no",
  "driver",
  "receiver",
  "receiver_mobile",
  "note",
];

function ScDeliveryEntry({
  options,
  initial,
  initialLots,
  initialYarn,
  canManage,
  act,
}) {
  const c = useScg();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    ...Object.fromEntries(DHEAD.map((k) => [k, str(d?.[k])])),
    challan_date: str(d?.challan_date) || today,
  });
  const asLine = (l = {}) => ({
    key: lineKey(),
    ...Object.fromEntries(DLINE.map((k) => [k, str(l[k])])),
    uom: str(l.uom) || (l.section === "COLLAR_CUFF" ? "PCS" : "KG"),
    info: l,
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [lots, setLots] = useState(initialLots ?? []);
  const [yarn, setYarn] = useState(initialYarn ?? []);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const fetchLots = useLatest(act, setLots);
  const fetchYarn = useLatest(act, setYarn);
  const open = canManage && (!doc || doc.status === "DRAFT");
  const setL = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  function refetch(h) {
    fetchLots(h.store_id ? `lots?store=${enc(h.store_id)}` : null);
    fetchYarn(
      h.store_id && h.party_id
        ? `sc/party-yarn?party=${enc(h.party_id)}&store=${enc(h.store_id)}`
        : null,
    );
  }
  function setH(patch) {
    const next = { ...head, ...patch };
    setHead(next);
    if ("store_id" in patch || "party_id" in patch) {
      refetch(next);
      setLines([]);
    }
  }
  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setErrors(null);
    if (d) refetch(asHead(d));
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${enc(d.challan_no)}` : "?tab=entry",
    );
  }
  function load(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`sc/deliveries/${enc(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  const greyOpts = (section) =>
    lots
      .filter((l) =>
        section === "FABRIC"
          ? l.kind !== "COLLAR_CUFF"
          : l.kind === "COLLAR_CUFF",
      )
      .map((l) => ({
        value: l.lot_id,
        label: `${l.label} · ${qtyFmt.format(l.balance)}`,
      }));
  const yarnOpts = yarn.map((y) => ({
    value: y.lot_id,
    label: `${y.label} · ${qtyFmt.format(y.balance)} ${y.uom}`,
  }));
  function pickGrey(k, id) {
    const l = lots.find((x) => x.lot_id === id);
    setL(
      k,
      l
        ? {
            lot_id: id,
            qty: String(l.balance),
            info: {
              ...l,
              roll_no: l.roll_no,
              description: l.fabric,
              colour_name: l.colour,
            },
          }
        : { lot_id: "" },
    );
  }
  function pickYarn(k, id) {
    const y = yarn.find((x) => x.lot_id === id);
    setL(
      k,
      y
        ? {
            yarn_lot_id: id,
            uom: y.uom || "KG",
            info: {
              description: y.yarn,
              colour_name: y.colour,
              lot_no: y.lot_no,
              balance: y.balance,
            },
          }
        : { yarn_lot_id: "" },
    );
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          DHEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        lines: lines.map((l) => ({
          ...Object.fromEntries(
            DLINE.map((k) => [k, l[k] === "" ? null : l[k]]),
          ),
          qty: orNull(l.qty),
          bags: orNull(l.bags),
        })),
      };
      const res = await act(
        doc ? `sc/deliveries/${enc(doc.challan_no)}` : "sc/deliveries",
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
  const add = (section) =>
    setLines((all) => [
      ...all,
      asLine({ section, part: section === "COLLAR_CUFF" ? "COLLAR" : "" }),
    ]);
  const remove = (k) => setLines((all) => all.filter((x) => x.key !== k));
  const greyRows = (section) => {
    const mine = lines.filter((l) => l.section === section);
    return (
      <table className="w-full min-w-[1300px] text-sm">
        <thead className="bg-brand text-brand-foreground">
          <tr>
            {[
              ...(section === "COLLAR_CUFF" ? ["part"] : []),
              "fabric_roll",
              "description",
              "colour",
              "shade",
              "roll_no",
              "dia",
              "mc_gg",
              "r_qty",
              "roll_wt",
              "uom",
              "remarks",
            ].map((k) => (
              <th
                key={k}
                className={cn(
                  th,
                  ["r_qty", "roll_wt"].includes(k) && "text-right",
                )}
              >
                {c(`col.${k}`)}
              </th>
            ))}
            <th className={th} />
          </tr>
        </thead>
        <tbody>
          {mine.map((l, i) => (
            <tr key={l.key} className="border-t border-border">
              {section === "COLLAR_CUFF" ? (
                <td className="min-w-[100px] px-2 py-1.5">
                  <PSelect
                    value={l.part}
                    onValueChange={(v) => setL(l.key, { part: v ?? "COLLAR" })}
                    options={["COLLAR", "CUFF"].map((x) => ({
                      value: x,
                      label: c(`type.${x}`),
                    }))}
                    disabled={!open}
                    aria-label={`${c("col.part")} ${i + 1}`}
                  />
                </td>
              ) : null}
              <td className="min-w-[260px] px-2 py-1.5">
                <PSelect
                  value={l.lot_id}
                  onValueChange={(v) => pickGrey(l.key, v ?? "")}
                  options={pick(greyOpts(section), c("ph.roll"))}
                  disabled={!open || !head.store_id}
                  aria-label={`${c("col.fabric_roll")} ${section} ${i + 1}`}
                />
              </td>
              <td className="px-2 py-1.5">{l.info?.description ?? "—"}</td>
              <td className="px-2 py-1.5">{l.info?.colour_name ?? "—"}</td>
              <td className="px-2 py-1.5">
                <Input
                  value={l.shade}
                  onChange={(e) => setL(l.key, { shade: e.target.value })}
                  maxLength={40}
                  disabled={!open}
                  className="w-20"
                  aria-label={`${c("col.shade")} ${section} ${i + 1}`}
                />
              </td>
              <td className="px-2 py-1.5 font-mono whitespace-nowrap">
                {l.info?.roll_no ?? "—"}
              </td>
              <td className="px-2 py-1.5">{l.info?.finish_dia ?? "—"}</td>
              <td className="px-2 py-1.5">
                <Input
                  value={l.mc_gauge}
                  onChange={(e) => setL(l.key, { mc_gauge: e.target.value })}
                  maxLength={24}
                  disabled={!open}
                  className="w-20"
                  aria-label={`${c("col.mc_gg")} ${section} ${i + 1}`}
                />
              </td>
              <td className="px-2 py-1.5 text-right tabular-nums">
                {l.info?.roll_id || l.info?.r_qty ? 1 : "—"}
              </td>
              <td className="px-2 py-1.5">
                <Input
                  {...numIn}
                  value={l.qty}
                  onChange={(e) => setL(l.key, { qty: e.target.value })}
                  disabled={!open}
                  className="ml-auto w-24 text-right"
                  aria-label={`${c("col.roll_wt")} ${section} ${i + 1}`}
                />
              </td>
              <td className="min-w-[90px] px-2 py-1.5">
                <PSelect
                  value={l.uom}
                  onValueChange={(v) => setL(l.key, { uom: v ?? "" })}
                  options={options.uoms}
                  disabled={!open}
                  aria-label={`${c("col.uom")} ${section} ${i + 1}`}
                />
              </td>
              <td className="min-w-[140px] px-2 py-1.5">
                <Input
                  value={l.remarks}
                  onChange={(e) => setL(l.key, { remarks: e.target.value })}
                  maxLength={300}
                  disabled={!open}
                  aria-label={`${c("col.remarks")} ${section} ${i + 1}`}
                />
              </td>
              <RemoveCell
                c={c}
                n={i + 1}
                open={open}
                onClick={() => remove(l.key)}
              />
            </tr>
          ))}
        </tbody>
      </table>
    );
  };
  const block = (section, title, hint, emptyKey, button, body) => (
    <section className={cn(card, "p-5")}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="text-base font-semibold">{title}</h3>
          <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
            {hint}
          </p>
        </div>
        {open ? (
          <Button variant="outline" size="sm" onClick={() => add(section)}>
            <Plus /> {button}
          </Button>
        ) : null}
      </div>
      <div className="mt-4 overflow-x-auto rounded-lg border border-border">
        {lines.some((l) => l.section === section) ? (
          body
        ) : (
          <NoLines title={c("empty.lines")} hint={c(emptyKey)} />
        )}
      </div>
    </section>
  );
  const yarnLines = lines.filter((l) => l.section === "YARN");

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <FormHead
          c={c}
          title={doc ? doc.challan_no : c("tabs.delivery_entry")}
          hint={c("hint.delivery_form")}
          doc={doc}
          status={doc?.status}
          group="delivery_status"
        />
        {doc && !open && canManage ? (
          <Alert variant="info" className="mt-3">
            {c("hint.locked", {
              no: doc.challan_no,
              status: c(`delivery_status.${doc.status}`),
            })}
          </Alert>
        ) : null}
        <Field
          label={c("f.existing_delivery")}
          hint={c("hint.existing")}
          className="mt-4"
        >
          <PSelect
            value={doc?.challan_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={pick(options.docs ?? [], c("ph.new_delivery"))}
            disabled={pending}
            aria-label={c("f.existing_delivery")}
          />
        </Field>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.challan_info")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-4">
          <Field label={c("f.party")} required hint={c("hint.party_delivery")}>
            <PSelect
              value={head.party_id}
              onValueChange={(v) => setH({ party_id: v ?? "" })}
              options={pick(options.suppliers, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.party")}
            />
          </Field>
          <Field label={c("f.store")} required hint={c("hint.store_ret")}>
            <PSelect
              value={head.store_id}
              onValueChange={(v) => setH({ store_id: v ?? "" })}
              options={pick(options.stores, c("ph.select"))}
              disabled={!open}
              aria-label={c("f.store")}
            />
          </Field>
          <Field label={c("f.challan_no")} hint={c("hint.rcv_no")}>
            <Input
              value={doc?.challan_no ?? ""}
              placeholder={c("ph.auto")}
              disabled
              aria-label={c("f.challan_no")}
            />
          </Field>
          <Field label={c("f.chln_date")} required>
            <Input
              type="date"
              value={head.challan_date}
              onChange={(e) => setH({ challan_date: e.target.value })}
              disabled={!open}
              aria-label={c("f.chln_date")}
            />
          </Field>
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.gate")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          {[
            ["gate_pass_no", "f.gate_pass", 40],
            ["vehicle_no", "f.vehicle", 40],
            ["driver", "f.driver", 80],
          ].map(([k, label, max]) => (
            <Field key={k} label={c(label)}>
              <Input
                value={head[k]}
                onChange={(e) => setH({ [k]: e.target.value })}
                maxLength={max}
                disabled={!open}
                aria-label={c(label)}
              />
            </Field>
          ))}
        </div>
        <h3 className="mt-5 text-sm font-semibold">{c("sec.receiver")}</h3>
        <div className="mt-3 grid gap-4 md:grid-cols-3">
          {[
            ["receiver", "f.receiver", 80],
            ["receiver_mobile", "f.receiver_mobile", 24],
          ].map(([k, label, max]) => (
            <Field key={k} label={c(label)}>
              <Input
                value={head[k]}
                onChange={(e) => setH({ [k]: e.target.value })}
                maxLength={max}
                disabled={!open}
                aria-label={c(label)}
              />
            </Field>
          ))}
        </div>
        <Field label={c("f.note")} className="mt-4">
          <Textarea
            rows={2}
            value={head.note}
            onChange={(e) => setH({ note: e.target.value })}
            maxLength={2000}
            disabled={!open}
            aria-label={c("f.note")}
          />
        </Field>
      </section>
      {block(
        "FABRIC",
        c("sec.fabric"),
        c("hint.fabric_lines"),
        "empty.fabric",
        c("btn.add_roll"),
        greyRows("FABRIC"),
      )}
      {block(
        "COLLAR_CUFF",
        c("sec.collar"),
        c("hint.collar_lines"),
        "empty.collar",
        c("btn.add_collar"),
        greyRows("COLLAR_CUFF"),
      )}
      {block(
        "YARN",
        c("sec.yarn"),
        c("hint.yarn_lines"),
        "empty.yarn",
        c("btn.add_yarn"),
        <table className="w-full min-w-[1000px] text-sm">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {[
                "yarn",
                "colour",
                "shade",
                "lot",
                "bags",
                "qty",
                "uom",
                "remarks",
              ].map((k) => (
                <th
                  key={k}
                  className={cn(
                    th,
                    ["bags", "qty"].includes(k) && "text-right",
                  )}
                >
                  {c(`col.${k}`)}
                </th>
              ))}
              <th className={th} />
            </tr>
          </thead>
          <tbody>
            {yarnLines.map((l, i) => (
              <tr key={l.key} className="border-t border-border">
                <td className="min-w-[300px] px-2 py-1.5">
                  <PSelect
                    value={l.yarn_lot_id}
                    onValueChange={(v) => pickYarn(l.key, v ?? "")}
                    options={pick(yarnOpts, c("ph.yarn"))}
                    disabled={!open || !head.store_id || !head.party_id}
                    aria-label={`${c("col.yarn")} ${i + 1}`}
                  />
                </td>
                <td className="px-2 py-1.5">{l.info?.colour_name ?? "—"}</td>
                <td className="px-2 py-1.5">
                  <Input
                    value={l.shade}
                    onChange={(e) => setL(l.key, { shade: e.target.value })}
                    maxLength={40}
                    disabled={!open}
                    className="w-20"
                    aria-label={`${c("col.shade")} YARN ${i + 1}`}
                  />
                </td>
                <td className="px-2 py-1.5">{l.info?.lot_no ?? "—"}</td>
                <td className="px-2 py-1.5">
                  <Input
                    {...numIn}
                    step="1"
                    value={l.bags}
                    onChange={(e) => setL(l.key, { bags: e.target.value })}
                    disabled={!open}
                    className="ml-auto w-20 text-right"
                    aria-label={`${c("col.bags")} ${i + 1}`}
                  />
                </td>
                <td className="px-2 py-1.5">
                  <Input
                    {...numIn}
                    value={l.qty}
                    onChange={(e) => setL(l.key, { qty: e.target.value })}
                    disabled={!open}
                    className="ml-auto w-24 text-right"
                    aria-label={`${c("col.qty")} YARN ${i + 1}`}
                  />
                </td>
                <td className="min-w-[90px] px-2 py-1.5">
                  <PSelect
                    value={l.uom}
                    onValueChange={(v) => setL(l.key, { uom: v ?? "" })}
                    options={options.uoms}
                    disabled={!open}
                    aria-label={`${c("col.uom")} YARN ${i + 1}`}
                  />
                </td>
                <td className="min-w-[140px] px-2 py-1.5">
                  <Input
                    value={l.remarks}
                    onChange={(e) => setL(l.key, { remarks: e.target.value })}
                    maxLength={300}
                    disabled={!open}
                    aria-label={`${c("col.remarks")} YARN ${i + 1}`}
                  />
                </td>
                <RemoveCell
                  c={c}
                  n={i + 1}
                  open={open}
                  onClick={() => remove(l.key)}
                />
              </tr>
            ))}
          </tbody>
        </table>,
      )}
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <FormFoot
        c={c}
        hint={c("hint.delivery_foot")}
        back={BASE.delivery}
        onClear={() => show(null)}
        onPrint={doc ? () => printDelivery(c, doc) : null}
        onSave={save}
        saveLabel={c("btn.save_delivery")}
        pending={pending}
        open={open}
      />
    </div>
  );
}

export {
  printChallan,
  lineSection,
  ScReceiptList,
  ScReceiptEntry,
  ScReturnList,
  ScReturnEntry,
  ScDeliveryList,
  ScDeliveryEntry,
};
