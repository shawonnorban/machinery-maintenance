"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Eraser,
  FilterX,
  History,
  Plus,
  Printer,
  Save,
  Trash2,
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

/*
 * Knit Production → Production (AnnotechERP 15/04) → Yarn Dyeing Programme
 * Register: Records (each Y/D programme with what the store issued against
 * it, what came back dyed and what is still with the party), the Y/D
 * Program form (a dye house, a buyer's order, one line per shade to come
 * back) and Y/D Program Revisions (numbered in order, each with why).
 * Nothing reloads. The yarn store's Y/D screens (15/03) share the helpers
 * at the foot.
 */

const BASE = "/knit-production/yd-program-list";
const TONE = {
  RUNNING: "PLACED",
  COMPLETED: "RECEIVED",
  CLOSED: "CLOSED",
  CANCELLED: "CANCELLED",
  DRAFT: "DRAFT",
  CONFIRMED: "APPROVED",
  APPROVED: "APPROVED",
  ISSUED: "PLACED",
  ACCEPTED: "RECEIVED",
  REJECTED: "REJECTED",
  SUBMITTED: "WAITING_APPROVAL",
  RECEIVED: "RECEIVED",
  VERIFIED: "PLACED",
  FINALIZED: "FINALIZED",
  REVISED: "CLOSED",
  SETTLED: "RECEIVED",
  OPEN: "PLACED",
  IN_PRODUCTION: "PLACED",
  PARTLY_ISSUED: "PART_RECEIVED",
};

function useYd() {
  const t = useT("knitting");
  return (k, p) => t(`yd.${k}`, p);
}

const pick = (list, ph) => [{ value: "", label: ph }, ...list];
const named = (list, v) => list.find((x) => x.value === v)?.label ?? null;
const yarnOf = (options, l) =>
  [
    options.yarn_names.compositions[l.composition_id],
    options.yarn_names.counts[l.yarn_count_id],
  ]
    .filter(Boolean)
    .join(" ");
const statusOptions = (c, list, group = "status") =>
  list.map((s) => ({ value: s, label: c(`${group}.${s}`) }));

function printProgram(c, d) {
  const value = d.lines.reduce((a, l) => a + num(l.qty) * num(l.rate), 0);
  printDocument({
    title: c("p.program"),
    docNo: d.rev_no ? `${d.program_no} · Rev ${d.rev_no}` : d.program_no,
    orientation: "landscape",
    meta: [
      [c("f.prg_date"), dayFmt(d.program_date)],
      [c("f.type"), c(`type.${d.yd_type}`)],
      [c("f.delivery_date"), d.delivery_date ? dayFmt(d.delivery_date) : null],
      [c("f.status"), { v: c(`status.${d.status}`), tone: "info" }],
      [c("f.party"), d.supplier],
      [c("p.address"), d.supplier_address],
      [c("f.buyer"), d.buyer],
      [c("f.order"), d.order],
      [c("col.issue"), qtyFmt.format(d.issue)],
      [c("col.delivery"), qtyFmt.format(d.delivery)],
      [c("col.process_loss"), qtyFmt.format(d.loss)],
      [c("col.balance"), { v: qtyFmt.format(d.balance), bold: true }],
    ],
    metaColumns: 4,
    sections: [
      {
        title: c("p.shades"),
        columns: [
          { label: "#", align: "center", width: "4%" },
          { label: c("col.colour") },
          { label: c("col.colour_group") },
          { label: c("col.yarn") },
          { label: c("col.greige_lot") },
          { label: c("col.qty"), align: "right" },
          { label: c("col.delivered"), align: "right" },
          { label: c("col.pending"), align: "right" },
          { label: c("col.rate"), align: "right" },
          { label: c("p.value"), align: "right" },
        ],
        rows: d.lines.map((l, i) => [
          i + 1,
          { v: l.colour_name, bold: true, sub: l.remarks || undefined },
          l.colour_group,
          l.yarn,
          l.lot_no,
          `${qtyFmt.format(l.qty)} ${l.uom}`,
          qtyFmt.format(l.delivered),
          qtyFmt.format(l.pending),
          l.rate === null ? "—" : moneyFmt.format(l.rate),
          l.rate === null ? "—" : moneyFmt.format(num(l.qty) * num(l.rate)),
        ]),
        foot: [
          [
            "",
            { v: c("total"), bold: true },
            "",
            "",
            "",
            { v: qtyFmt.format(d.qty), bold: true },
            { v: qtyFmt.format(d.delivery), bold: true },
            { v: qtyFmt.format(d.pending), bold: true },
            "",
            { v: moneyFmt.format(value), bold: true },
          ],
        ],
        empty: c("p.no_lines"),
      },
      ...(d.revisions.length
        ? [
            {
              title: c("p.revisions"),
              columns: [
                { label: c("col.rev"), align: "center", width: "8%" },
                { label: c("col.revised_on"), width: "14%" },
                { label: c("col.reason") },
                { label: c("col.by"), width: "18%" },
              ],
              rows: d.revisions.map((r) => [
                r.rev_no,
                dayFmt(r.revised_on),
                r.reason,
                r.by,
              ]),
            },
          ]
        : []),
    ],
    notes: [d.remarks],
    signatures: [c("p.prepared"), c("p.store"), c("p.approved")],
  });
}

/* ---------------- Records ---------------- */

function YdRegister({ options, initialRows, canManage, act }) {
  const c = useYd();
  const blank = {
    no: "",
    buyer: "",
    style: "",
    party: "",
    status: "",
    from: "",
    to: "",
  };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const has = (v, q) => (v ?? "").toLowerCase().includes(q.toLowerCase());
  const shown = rows.filter(
    (r) =>
      (!f.no || has(r.program_no, f.no)) &&
      (!f.buyer || r.buyer_id === f.buyer) &&
      (!f.style || has(`${r.style} ${r.order_no}`, f.style)) &&
      (!f.party || r.supplier_id === f.party) &&
      (!f.status || r.status === f.status) &&
      (!f.from || r.program_date >= f.from) &&
      (!f.to || r.program_date <= f.to),
  );

  function refresh() {
    startTransition(async () => {
      const res = await act("yd/programs");
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  function print(r) {
    startTransition(async () => {
      const res = await act(`yd/programs/${encodeURIComponent(r.program_no)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      printProgram(c, res.data);
    });
  }
  const columns = [
    {
      key: "program_no",
      label: c("col.program_no"),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${BASE}?tab=program&ref=${encodeURIComponent(r.program_no)}`}
            className="font-medium text-brand hover:underline"
          >
            {r.program_no}
          </Link>
          <StatusChip status={TONE[r.status]} label={c(`status.${r.status}`)} />
        </span>
      ),
    },
    {
      key: "yd_type",
      label: c("col.type"),
      nowrap: true,
      text: (r) => c(`type.${r.yd_type}`),
    },
    { key: "rev_no", label: c("col.rev"), num: true },
    { key: "buyer", label: c("col.buyer") },
    {
      key: "style",
      label: c("col.order"),
      text: (r) =>
        r.order_no ? `${r.order_no}${r.style ? ` (${r.style})` : ""}` : "—",
    },
    { key: "supplier", label: c("col.supplier") },
    {
      key: "issue",
      label: c("col.issue"),
      num: true,
      text: (r) => qtyFmt.format(r.issue),
    },
    {
      key: "delivery",
      label: c("col.delivery"),
      num: true,
      text: (r) => qtyFmt.format(r.delivery),
    },
    {
      key: "balance",
      label: c("col.balance"),
      num: true,
      text: (r) => qtyFmt.format(r.balance),
    },
    {
      key: "program_date",
      label: c("col.prg_date"),
      nowrap: true,
      text: (r) => dayFmt(r.program_date),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Field label={c("f.program_no")}>
            <Input
              value={f.no}
              onChange={(e) => setF("no", e.target.value)}
              placeholder={c("ph.program_no")}
              aria-label={c("f.program_no")}
            />
          </Field>
          <Field label={c("f.buyer")}>
            <PSelect
              value={f.buyer}
              onValueChange={(v) => setF("buyer", v)}
              options={pick(options.buyers, c("ph.all"))}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.work_style")}>
            <Input
              value={f.style}
              onChange={(e) => setF("style", e.target.value)}
              aria-label={c("f.work_style")}
            />
          </Field>
          <Field label={c("f.party")}>
            <PSelect
              value={f.party}
              onValueChange={(v) => setF("party", v)}
              options={pick(options.suppliers, c("ph.all"))}
              aria-label={c("f.party")}
            />
          </Field>
          <Field label={c("f.status")}>
            <PSelect
              value={f.status}
              onValueChange={(v) => setF("status", v)}
              options={pick(statusOptions(c, options.statuses), c("ph.all"))}
              aria-label={c("f.status")}
            />
          </Field>
          <Field label={c("f.prg_date")} className="lg:col-span-2">
            <div className="flex items-center gap-2">
              <Input
                type="date"
                value={f.from}
                onChange={(e) => setF("from", e.target.value)}
                aria-label={c("f.from")}
              />
              <span className="text-sm text-foreground-muted">
                {c("f.to_word")}
              </span>
              <Input
                type="date"
                value={f.to}
                onChange={(e) => setF("to", e.target.value)}
                aria-label={c("f.to")}
              />
            </div>
          </Field>
        </div>
        <div className="mt-4 flex items-center justify-end gap-3 text-[13px] text-foreground-muted">
          {c("shown", { shown: shown.length, total: rows.length })}
          <Button variant="outline" size="sm" onClick={() => setFilters(blank)}>
            <FilterX /> {c("btn.clear")}
          </Button>
          {canManage ? (
            <Link
              href={`${BASE}?tab=program`}
              className={buttonVariants({ size: "sm" })}
            >
              <Plus /> {c("btn.add_new")}
            </Link>
          ) : null}
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>
        <p className="px-5 pt-4 text-[13px] text-foreground-muted">
          {c("hint.register")}
        </p>
        <ScTable
          rows={shown}
          columns={columns}
          exportName="yd-programmes"
          addHref={canManage ? `${BASE}?tab=program` : null}
          empty={
            <NoLines title={c("empty.list")} hint={c("empty.list_hint")} />
          }
          onRefresh={refresh}
          pending={pending}
          actions={(r) => (
            <>
              <Button
                variant="outline"
                size="sm"
                onClick={() => print(r)}
                disabled={pending}
                aria-label={`${c("btn.print")} ${r.program_no}`}
              >
                <Printer /> {c("btn.print")}
              </Button>
              {canManage ? (
                <Link
                  href={`${BASE}?tab=revisions&ref=${encodeURIComponent(r.program_no)}`}
                  className={buttonVariants({ variant: "outline", size: "sm" })}
                  aria-label={`${c("btn.revise")} ${r.program_no}`}
                >
                  <History /> {c("btn.revise")}
                </Link>
              ) : null}
            </>
          )}
        />
      </section>
    </div>
  );
}

/* ---------------- Y/D Program form ---------------- */

const LINE = [
  "id",
  "colour_id",
  "colour_name",
  "colour_group",
  "composition_id",
  "yarn_count_id",
  "lot_no",
  "qty",
  "uom",
  "rate",
  "remarks",
];
const HEAD = [
  "program_date",
  "yd_type",
  "supplier_id",
  "buyer_id",
  "order_id",
  "delivery_date",
  "status",
  "remarks",
];
let seq = 0;
const asLine = (l = {}) => ({
  key: `y${++seq}`,
  ...Object.fromEntries(LINE.map((k) => [k, str(l[k])])),
  delivered: l.delivered ?? 0,
  pending: l.pending ?? null,
});

function YdProgram({ options, initial, canManage, act }) {
  const c = useYd();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, str(d?.[k])])),
    program_date: str(d?.program_date) || today,
    yd_type: str(d?.yd_type) || "IN_HOUSE",
    status: str(d?.status) || "RUNNING",
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [draft, setDraft] = useState(() => asLine({ uom: "kg" }));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setD = (patch) => setDraft((d) => ({ ...d, ...patch }));
  const total = lines.reduce((a, l) => a + num(l.qty), 0);
  const orders = options.orders.filter(
    (o) => !head.buyer_id || o.buyer_id === head.buyer_id,
  );

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setDraft(asLine({ uom: "kg" }));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d
        ? `?tab=program&ref=${encodeURIComponent(d.program_no)}`
        : "?tab=program",
    );
  }
  function open(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`yd/programs/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function addLine() {
    const miss = [
      !draft.colour_name && c("line.need_colour"),
      !(num(draft.qty) > 0) && c("line.need_qty"),
      !draft.uom && c("line.need_uom"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    setLines((all) => [...all, draft]);
    setDraft(
      asLine({
        uom: draft.uom,
        composition_id: draft.composition_id,
        yarn_count_id: draft.yarn_count_id,
        lot_no: draft.lot_no,
      }),
    );
    setErrors(null);
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          HEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        lines: lines.map((l) => ({
          ...Object.fromEntries(
            LINE.map((k) => [k, l[k] === "" ? null : l[k]]),
          ),
          qty: orNull(l.qty),
          rate: orNull(l.rate),
        })),
      };
      const res = await act(
        doc
          ? `yd/programs/${encodeURIComponent(doc.program_no)}`
          : "yd/programs",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.program", { no: res.data.program_no }),
        type: "success",
      });
    });
  }
  const numIn = { type: "number", min: "0", step: "any" };

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {doc
                ? `${doc.program_no}${doc.rev_no ? ` · Rev ${doc.rev_no}` : ""}`
                : c("tabs.program")}
            </h2>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {c("hint.program_form")}
            </p>
          </div>
          <StatusChip
            status={doc ? TONE[doc.status] : "PLACED"}
            label={doc ? c(`status.${doc.status}`) : c("new_badge")}
          />
        </div>
        <Field
          label={c("f.existing_program")}
          hint={c("hint.existing")}
          className="mt-4"
        >
          <PSelect
            value={doc?.program_no ?? ""}
            onValueChange={(v) => open(v ?? "")}
            options={pick(options.programs, c("ph.new_program"))}
            disabled={pending}
            aria-label={c("f.existing_program")}
          />
        </Field>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Field label={c("f.program_no")}>
            <Input
              value={doc?.program_no ?? ""}
              placeholder={c("ph.auto")}
              disabled
              aria-label={c("f.program_no")}
            />
          </Field>
          <Field label={c("f.prg_date")} required>
            <Input
              type="date"
              value={head.program_date}
              onChange={(e) => setH({ program_date: e.target.value })}
              disabled={!canManage}
              aria-label={c("f.prg_date")}
            />
          </Field>
          <Field label={c("f.type")} required>
            <PSelect
              value={head.yd_type}
              onValueChange={(v) => setH({ yd_type: v ?? "" })}
              options={pick(
                statusOptions(c, options.types, "type"),
                c("ph.select"),
              )}
              disabled={!canManage}
              aria-label={c("f.type")}
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
          <Field label={c("f.party")} required hint={c("hint.factory")}>
            <PSelect
              value={head.supplier_id}
              onValueChange={(v) => setH({ supplier_id: v ?? "" })}
              options={pick(options.suppliers, c("ph.party"))}
              disabled={!canManage}
              aria-label={c("f.party")}
            />
          </Field>
          <Field label={c("f.buyer")}>
            <PSelect
              value={head.buyer_id}
              onValueChange={(v) => setH({ buyer_id: v ?? "", order_id: "" })}
              options={pick(options.buyers, c("ph.select"))}
              disabled={!canManage}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.order")} hint={c("hint.order")}>
            <PSelect
              value={head.order_id}
              onValueChange={(v) =>
                setH({
                  order_id: v ?? "",
                  buyer_id:
                    options.orders.find((o) => o.value === v)?.buyer_id ??
                    head.buyer_id,
                })
              }
              options={pick(orders, c("ph.order"))}
              disabled={!canManage}
              aria-label={c("f.order")}
            />
          </Field>
          <Field label={c("f.delivery_date")}>
            <Input
              type="date"
              value={head.delivery_date}
              onChange={(e) => setH({ delivery_date: e.target.value })}
              disabled={!canManage}
              aria-label={c("f.delivery_date")}
            />
          </Field>
          <Field label={c("f.status")} required>
            <PSelect
              value={head.status}
              onValueChange={(v) => setH({ status: v ?? "" })}
              options={pick(statusOptions(c, options.statuses), c("ph.select"))}
              disabled={!canManage}
              aria-label={c("f.status")}
            />
          </Field>
        </div>
        <Field label={c("f.remarks")} className="mt-4">
          <Textarea
            rows={2}
            value={head.remarks}
            onChange={(e) => setH({ remarks: e.target.value })}
            maxLength={2000}
            disabled={!canManage}
            aria-label={c("f.remarks")}
          />
        </Field>
        {doc ? (
          <div className="mt-4 grid gap-3 rounded-lg border border-border bg-surface-muted p-3 text-sm sm:grid-cols-4">
            {[
              ["col.issue", doc.issue],
              ["col.delivery", doc.delivery],
              ["col.process_loss", doc.loss],
              ["col.with_party", doc.balance],
            ].map(([k, v]) => (
              <div key={k}>
                <p className="text-[12px] text-foreground-muted">{c(k)}</p>
                <p className="font-semibold tabular-nums">
                  {qtyFmt.format(v)} kg
                </p>
              </div>
            ))}
          </div>
        ) : null}
      </section>
      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold text-foreground">
              {c("sec.shades")}
            </h3>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {c("hint.shades")}
            </p>
          </div>
          {canManage ? (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={addLine}>
                <Plus /> {c("btn.add_shade")}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setDraft(asLine({ uom: "kg" }))}
              >
                {c("btn.cancel")}
              </Button>
            </div>
          ) : null}
        </div>
        {canManage ? (
          <div className="mt-4 grid gap-4 md:grid-cols-4">
            <Field label={c("f.colour")} required>
              <PSelect
                value={draft.colour_id}
                onValueChange={(v) =>
                  setD({
                    colour_id: v ?? "",
                    colour_name:
                      options.colours.find((x) => x.value === v)?.name ?? "",
                  })
                }
                options={pick(options.colours, c("ph.colour"))}
                aria-label={c("f.colour")}
              />
            </Field>
            <Field label={c("f.colour_group")}>
              <PSelect
                value={draft.colour_group}
                onValueChange={(v) => setD({ colour_group: v ?? "" })}
                options={pick(
                  options.colour_groups.map((g) => ({ value: g, label: g })),
                  c("ph.group"),
                )}
                aria-label={c("f.colour_group")}
              />
            </Field>
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
            <Field label={c("f.greige_lot")}>
              <Input
                value={draft.lot_no}
                onChange={(e) => setD({ lot_no: e.target.value })}
                maxLength={40}
                aria-label={c("f.greige_lot")}
              />
            </Field>
            <Field label={c("f.qty")} required>
              <Input
                {...numIn}
                value={draft.qty}
                onChange={(e) => setD({ qty: e.target.value })}
                aria-label={c("f.qty")}
              />
            </Field>
            <Field label={c("f.uom")} required>
              <PSelect
                value={draft.uom}
                onValueChange={(v) => setD({ uom: v ?? "" })}
                options={pick(options.uoms, c("ph.select"))}
                aria-label={c("f.uom")}
              />
            </Field>
            <Field label={c("f.rate")}>
              <Input
                {...numIn}
                value={draft.rate}
                onChange={(e) => setD({ rate: e.target.value })}
                aria-label={c("f.rate")}
              />
            </Field>
            <Field label={c("f.remarks")} className="md:col-span-4">
              <Input
                value={draft.remarks}
                onChange={(e) => setD({ remarks: e.target.value })}
                maxLength={300}
                aria-label={`${c("f.remarks")} (${c("f.colour")})`}
              />
            </Field>
          </div>
        ) : null}
        {doc?.used && canManage ? (
          <p className="mt-3 text-[13px] text-foreground-muted">
            {c("hint.program_used")}
          </p>
        ) : null}
        <div className="mt-5 overflow-x-auto rounded-lg border border-border">
          {lines.length === 0 ? (
            <NoLines title={c("empty.lines")} hint={c("empty.program_lines")} />
          ) : (
            <table className="w-full min-w-[1000px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    ["colour"],
                    ["colour_group"],
                    ["yarn"],
                    ["greige_lot"],
                    ["qty", true],
                    ["uom"],
                    ["rate", true],
                    ["delivered", true],
                    ["pending", true],
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
                      {l.colour_name}
                      {l.remarks ? (
                        <span className="block text-xs font-normal text-foreground-muted">
                          {l.remarks}
                        </span>
                      ) : null}
                    </td>
                    <td className="px-2 py-2">{l.colour_group || "—"}</td>
                    <td className="px-2 py-2">{yarnOf(options, l) || "—"}</td>
                    <td className="px-2 py-2">{l.lot_no || "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(num(l.qty))}
                    </td>
                    <td className="px-2 py-2">{l.uom}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {l.rate === "" ? "—" : moneyFmt.format(num(l.rate))}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(num(l.delivered))}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(
                        l.pending ?? Math.max(0, num(l.qty) - num(l.delivered)),
                      )}
                    </td>
                    <td className="px-2 py-1.5">
                      {canManage && !(num(l.delivered) > 0) ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          className="text-danger"
                          onClick={() =>
                            setLines((all) =>
                              all.filter((x) => x.key !== l.key),
                            )
                          }
                          aria-label={`${c("btn.remove")} ${i + 1}`}
                        >
                          <Trash2 /> {c("btn.remove")}
                        </Button>
                      ) : null}
                    </td>
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
                  <td colSpan={5} />
                </tr>
              </tfoot>
            </table>
          )}
        </div>
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section
        className={cn(
          card,
          "flex flex-wrap items-center justify-between gap-3 p-4",
        )}
      >
        <p className="max-w-2xl text-[13px] text-foreground-muted">
          {c("hint.program_foot")}
        </p>
        <div className="flex flex-wrap gap-2">
          <Link href={BASE} className={buttonVariants({ variant: "outline" })}>
            <ArrowLeft /> {c("btn.back")}
          </Link>
          <Button
            variant="outline"
            onClick={() => show(null)}
            disabled={pending}
          >
            <Eraser /> {c("btn.clear")}
          </Button>
          {doc ? (
            <Button variant="outline" onClick={() => printProgram(c, doc)}>
              <Printer /> {c("btn.print")}
            </Button>
          ) : null}
          {canManage ? (
            <Button onClick={save} loading={pending}>
              <Save /> {c("btn.save_program")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

/* ---------------- Y/D Program Revisions ---------------- */

function YdRevisions({ options, initial, canManage, act }) {
  const c = useYd();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [doc, setDoc] = useState(initial);
  const [adding, setAdding] = useState(Boolean(initial));
  const [rev, setRev] = useState({ revised_on: today, reason: "" });
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();

  function show(d) {
    setDoc(d);
    setRev({ revised_on: today, reason: "" });
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d
        ? `?tab=revisions&ref=${encodeURIComponent(d.program_no)}`
        : "?tab=revisions",
    );
  }
  function open(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`yd/programs/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function save() {
    const miss = [
      !doc && c("line.need_program"),
      doc && !rev.revised_on && c("line.need_revised_on"),
      doc && !rev.reason.trim() && c("line.need_reason_head"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    startTransition(async () => {
      const res = await act("yd/revisions", "POST", {
        program_no: doc.program_no,
        ...rev,
      });
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      setAdding(false);
      toastManager.add({
        title: c("done.revision", {
          rev: res.data.rev_no,
          no: res.data.program_no,
        }),
        type: "success",
      });
    });
  }
  const facts = [
    ["f.supplier", doc?.supplier],
    ["f.buyer", doc?.buyer],
    ["f.order", doc?.order],
    ["f.issue", doc ? `${qtyFmt.format(doc.issue)} kg` : ""],
    ["f.delivery", doc ? `${qtyFmt.format(doc.delivery)} kg` : ""],
  ];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {c("tabs.revisions")}
            </h2>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {c("hint.revisions")}
            </p>
          </div>
          {canManage ? (
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setAdding(true)}
              >
                <Plus /> {c("btn.add_revision")}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => (
                  setAdding(false),
                  setRev({ revised_on: today, reason: "" })
                )}
              >
                <X /> {c("btn.cancel")}
              </Button>
            </div>
          ) : null}
        </div>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Field label={c("f.program")} required>
            <PSelect
              value={doc?.program_no ?? ""}
              onValueChange={(v) => open(v ?? "")}
              options={pick(options.programs, c("ph.select"))}
              disabled={pending}
              aria-label={c("f.program")}
            />
          </Field>
          {facts.map(([k, v]) => (
            <Field key={k} label={c(k)}>
              <Input value={v ?? ""} disabled aria-label={c(k)} />
            </Field>
          ))}
        </div>
        {adding && canManage ? (
          <div className="mt-4 grid gap-4 rounded-lg border border-border p-4 md:grid-cols-[120px_200px_minmax(0,1fr)]">
            <Field label={c("f.rev_no")} hint={c("hint.rev_auto")}>
              <Input
                value={doc ? String(doc.rev_no + 1) : ""}
                disabled
                aria-label={c("f.rev_no")}
              />
            </Field>
            <Field label={c("f.revised_on")} required>
              <Input
                type="date"
                value={rev.revised_on}
                onChange={(e) =>
                  setRev((r) => ({ ...r, revised_on: e.target.value }))
                }
                aria-label={c("f.revised_on")}
              />
            </Field>
            <Field label={c("f.reason")} required>
              <Input
                value={rev.reason}
                onChange={(e) =>
                  setRev((r) => ({ ...r, reason: e.target.value }))
                }
                maxLength={500}
                aria-label={c("f.reason")}
              />
            </Field>
          </div>
        ) : null}
      </section>
      <section className={cn(card, "p-5")}>
        <h3 className="text-base font-semibold text-foreground">
          {c("sec.history")}
        </h3>
        <div className="mt-3 overflow-x-auto rounded-lg border border-border">
          {!doc || doc.revisions.length === 0 ? (
            <NoLines
              title={doc ? c("empty.history") : c("empty.pick_program")}
              hint=""
            />
          ) : (
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  <th className={th}>{c("col.rev")}</th>
                  <th className={th}>{c("col.revised_on")}</th>
                  <th className={th}>{c("col.reason")}</th>
                  <th className={th}>{c("col.by")}</th>
                </tr>
              </thead>
              <tbody>
                {doc.revisions.map((r) => (
                  <tr key={r.rev_no} className="border-t border-border">
                    <td className="px-2 py-2 font-medium tabular-nums">
                      {r.rev_no}
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {dayFmt(r.revised_on)}
                    </td>
                    <td className="px-2 py-2">{r.reason}</td>
                    <td className="px-2 py-2">{r.by ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </section>
      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className={cn(card, "flex flex-wrap justify-end gap-2 p-4")}>
        <Link href={BASE} className={buttonVariants({ variant: "outline" })}>
          <ArrowLeft /> {c("btn.back")}
        </Link>
        <Button variant="outline" onClick={() => show(null)} disabled={pending}>
          <Eraser /> {c("btn.clear")}
        </Button>
        {canManage ? (
          <Button onClick={save} loading={pending}>
            <Save /> {c("btn.save_program")}
          </Button>
        ) : null}
      </section>
    </div>
  );
}

export {
  YdRegister,
  YdProgram,
  YdRevisions,
  useYd,
  TONE,
  pick,
  named,
  yarnOf,
  statusOptions,
};
