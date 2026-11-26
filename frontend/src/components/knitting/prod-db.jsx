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
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";
import { printDocument } from "@/lib/print-document";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
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

/*
 * Knit Production → Production (AnnotechERP 15/04) → Direct Knit Fabric
 * Booking: the Direct Booking List (Booking Search above; Submit, Approve —
 * asked first — Cancel and Print) and the booking's own form: Key
 * Information, then one line per fabric and shade, picked from the type list
 * and described in the floor's own words. Nothing reloads.
 */

const BASE = "/knit-production/direct-fabric-booking";
const TONE = {
  DRAFT: "DRAFT",
  SUBMITTED: "WAITING_APPROVAL",
  APPROVED: "APPROVED",
  CANCELLED: "CANCELLED",
};

function useDb() {
  const t = useT("knitting");
  return (k, p) => t(`db.${k}`, p);
}

const finDia = (c, l) =>
  l.finish_dia === null || l.finish_dia === undefined || l.finish_dia === ""
    ? null
    : `${Number(l.finish_dia).toFixed(2)}${l.dia_unit ? ` ${c(`dia_unit.${l.dia_unit}`)}` : ""}${l.width_type ? ` (${c(`width_type.${l.width_type}`)})` : ""}`;

function printBooking(c, d) {
  printDocument({
    title: c("p.title"),
    docNo: d.booking_no,
    orientation: "landscape",
    meta: [
      [c("f.prog_date"), dayFmt(d.prog_date)],
      [c("f.category"), d.category],
      [c("f.buyer"), d.buyer],
      [c("f.style"), d.style],
      [c("p.order"), d.order_no],
      [c("p.job"), d.job],
      [c("f.company"), d.company],
      [c("f.status"), { v: c(`status.${d.status}`), tone: "info" }],
      [c("p.total_qty"), `${qtyFmt.format(d.qty)} Kg`],
    ],
    metaColumns: 3,
    sections: [
      {
        title: c("sec.lines"),
        columns: [
          { label: c("col.fabric_type") },
          { label: c("col.fabric") },
          { label: c("col.colour") },
          { label: c("col.gsm"), align: "right" },
          { label: c("col.finish_dia") },
          { label: c("col.qty"), align: "right" },
          { label: c("col.required_on") },
          { label: c("col.remarks") },
        ],
        rows: d.lines.map((l) => [
          l.fabric_type,
          { v: l.fabric, bold: true },
          [l.colour, l.colour_name].filter(Boolean).join(" · ") || null,
          l.gsm === null ? null : Number(l.gsm).toFixed(2),
          finDia(c, l),
          { v: `${qtyFmt.format(l.qty)} ${l.uom}`, bold: true },
          l.required_on ? dayFmt(l.required_on) : null,
          l.remarks,
        ]),
        foot: [
          [
            { v: c("total"), bold: true },
            "",
            "",
            "",
            "",
            { v: qtyFmt.format(d.qty), bold: true },
            "",
            "",
          ],
        ],
        empty: c("p.no_lines"),
      },
    ],
    notes: d.remarks ? [d.remarks] : [],
    signatures: [c("p.sign.booked"), c("p.sign.store"), c("p.sign.approved")],
  });
}

function DbList({ options, initialRows, canManage, act }) {
  const c = useDb();
  const toastManager = useToastManager();
  const blank = { category: "", buyer: "", status: "", from: "", to: "" };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [ask, setAsk] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const pick = (list, ph) => [{ value: "", label: ph }, ...list];

  function search(next = f) {
    startTransition(async () => {
      const q = new URLSearchParams(Object.entries(next).filter(([, v]) => v));
      const res = await act(`direct-bookings?${q}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  function run(row, action) {
    startTransition(async () => {
      const res = await act(
        `direct-bookings/${encodeURIComponent(row.booking_no)}/${action}`,
        "POST",
      );
      setAsk(null);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows((list) =>
        list.map((x) => (x.booking_no === row.booking_no ? res.data : x)),
      );
      setError(null);
      toastManager.add({
        title: c(`done.${action}`, { no: row.booking_no }),
        type: "success",
      });
    });
  }
  function print(r) {
    startTransition(async () => {
      const res = await act(
        `direct-bookings/${encodeURIComponent(r.booking_no)}`,
      );
      if (res?.status !== "success") return setError(messagesOf(res));
      printBooking(c, res.data);
    });
  }
  const columns = [
    {
      key: "booking_no",
      label: c("col.booking_no"),
      nowrap: true,
      render: (r) => (
        <Link
          href={`${BASE}?tab=entry&ref=${encodeURIComponent(r.booking_no)}`}
          className="font-medium text-brand hover:underline"
        >
          {r.booking_no}
        </Link>
      ),
    },
    {
      key: "prog_date",
      label: c("col.prog_date"),
      nowrap: true,
      text: (r) => dayFmt(r.prog_date),
    },
    { key: "category", label: c("col.category") },
    { key: "buyer", label: c("col.buyer") },
    { key: "style", label: c("col.style") },
    { key: "order", label: c("col.order"), nowrap: true },
    { key: "lines", label: c("col.fabrics"), num: true },
    {
      key: "qty",
      label: c("col.qty_kg"),
      num: true,
      text: (r) => qtyFmt.format(r.qty),
    },
    { key: "remarks", label: c("col.remarks") },
    {
      key: "status",
      label: c("col.status"),
      text: (r) => c(`status.${r.status}`),
      render: (r) => (
        <StatusChip status={TONE[r.status]} label={c(`status.${r.status}`)} />
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
          <Search className="size-4" /> {c("sec.search")}
        </h2>
        <p className="mt-0.5 mb-4 text-[13px] text-foreground-muted">
          {c("hint.list")}
        </p>
        <div className="grid gap-4 md:grid-cols-3 lg:grid-cols-5">
          <Field label={c("f.category")}>
            <PSelect
              value={f.category}
              onValueChange={(v) => setF("category", v)}
              options={pick(options.categories, c("ph.type"))}
              aria-label={c("f.category")}
            />
          </Field>
          <Field label={c("f.buyer")}>
            <PSelect
              value={f.buyer}
              onValueChange={(v) => setF("buyer", v)}
              options={pick(options.buyers, c("ph.select"))}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.status")}>
            <PSelect
              value={f.status}
              onValueChange={(v) => setF("status", v)}
              options={pick(
                options.booking_statuses.map((s) => ({
                  value: s,
                  label: c(`status.${s}`),
                })),
                c("ph.all"),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <Field label={c("f.from")}>
            <Input
              type="date"
              value={f.from}
              onChange={(e) => setF("from", e.target.value)}
              aria-label={c("f.from")}
            />
          </Field>
          <Field label={c("f.to")}>
            <Input
              type="date"
              value={f.to}
              onChange={(e) => setF("to", e.target.value)}
              aria-label={c("f.to")}
            />
          </Field>
        </div>
        <div className="mt-4 flex justify-between gap-2 border-t border-border pt-4">
          <div className="flex gap-2">
            <Button onClick={() => search()} loading={pending}>
              <Search /> {c("btn.search")}
            </Button>
            {canManage ? (
              <Link
                href={`${BASE}?tab=entry`}
                className={buttonVariants({ variant: "outline" })}
              >
                <Plus /> {c("btn.add_new")}
              </Link>
            ) : null}
          </div>
          <Button
            variant="outline"
            onClick={() => (setFilters(blank), search(blank))}
            disabled={pending}
          >
            <RotateCcw /> {c("btn.reset")}
          </Button>
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>
        <div className="flex flex-wrap gap-2 border-b border-border px-5 py-4">
          {[
            [c("chip.bookings"), rows.length],
            [
              c("chip.qty"),
              qtyFmt.format(rows.reduce((a, r) => a + num(r.qty), 0)),
            ],
          ].map(([label, v]) => (
            <span
              key={label}
              className="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-1.5 text-[13px]"
            >
              {label}
              <span className="rounded-full bg-brand-subtle px-2 text-xs font-semibold text-brand tabular-nums">
                {v}
              </span>
            </span>
          ))}
        </div>
        <p className="px-5 pt-4 text-[13px] text-foreground-muted">
          {c("hint.caption")}
        </p>
        <ScTable
          rows={rows}
          columns={columns}
          colFilters
          exportName="direct-knit-fabric-bookings"
          addHref={canManage ? `${BASE}?tab=entry` : null}
          empty={
            <NoLines title={c("empty.list")} hint={c("empty.list_hint")} />
          }
          onRefresh={() => search()}
          pending={pending}
          actions={(r) => (
            <>
              {canManage && r.status === "DRAFT" ? (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => run(r, "submit")}
                  disabled={pending}
                  aria-label={`${c("btn.submit")} ${r.booking_no}`}
                >
                  <Check /> {c("btn.submit")}
                </Button>
              ) : null}
              {canManage && r.status === "SUBMITTED" ? (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setAsk({ row: r, action: "approve" })}
                  disabled={pending}
                  aria-label={`${c("btn.approve")} ${r.booking_no}`}
                >
                  <CheckCheck /> {c("btn.approve")}
                </Button>
              ) : null}
              {canManage && ["DRAFT", "SUBMITTED"].includes(r.status) ? (
                <Button
                  variant="outline"
                  size="sm"
                  className="text-danger"
                  onClick={() => setAsk({ row: r, action: "cancel" })}
                  disabled={pending}
                  aria-label={`${c("btn.cancel")} ${r.booking_no}`}
                >
                  <Ban /> {c("btn.cancel")}
                </Button>
              ) : null}
              <Button
                variant="outline"
                size="sm"
                onClick={() => print(r)}
                disabled={pending}
                aria-label={`${c("btn.print")} ${r.booking_no}`}
              >
                <Printer /> {c("btn.print")}
              </Button>
            </>
          )}
        />
      </section>
      <ConfirmDialog
        open={Boolean(ask)}
        onOpenChange={(o) => (o ? null : setAsk(null))}
        title={ask ? c(`ask.${ask.action}.title`) : ""}
        description={ask ? c(`ask.${ask.action}.body`) : ""}
        confirmLabel={ask ? c(`btn.${ask.action}`) : ""}
        cancelLabel={c("ask.no")}
        destructive={ask?.action === "cancel"}
        loading={pending}
        onConfirm={() => run(ask.row, ask.action)}
      />
    </div>
  );
}

const LINE = [
  "fabric_type_id",
  "fabric",
  "colour_id",
  "colour_name",
  "gsm",
  "finish_dia",
  "dia_unit",
  "width_type",
  "qty",
  "uom",
  "required_on",
  "remarks",
];
const HEAD = [
  "category_code",
  "buyer_id",
  "style_id",
  "order_id",
  "prog_date",
  "remarks",
];
let seq = 0;
const asLine = (l = {}) => ({
  key: `d${++seq}`,
  ...Object.fromEntries(LINE.map((k) => [k, str(l[k])])),
});
const fresh = () => asLine({ uom: "kg", dia_unit: "INCH", width_type: "OPEN" });

function DbEntry({ options, initial, canManage, act }) {
  const c = useDb();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, str(d?.[k])])),
    prog_date: str(d?.prog_date) || today,
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [draft, setDraft] = useState(fresh);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.status === "DRAFT");
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setD = (patch) => setDraft((d) => ({ ...d, ...patch }));
  const pick = (list, ph) => [{ value: "", label: ph }, ...list];
  const named = (list, v) => list.find((x) => x.value === v)?.label ?? null;
  const styles = options.styles.filter(
    (s) => !head.buyer_id || s.buyer_id === head.buyer_id,
  );
  const orders = options.orders.filter(
    (o) =>
      (!head.buyer_id || o.buyer_id === head.buyer_id) &&
      (!head.style_id || o.style_id === head.style_id),
  );
  const listOf = (codes, group) =>
    codes.map((v) => ({ value: v, label: c(`${group}.${v}`) }));
  const total = lines.reduce((a, l) => a + num(l.qty), 0);

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setDraft(fresh());
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${encodeURIComponent(d.booking_no)}` : "?tab=entry",
    );
  }
  function open(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`direct-bookings/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function addLine() {
    const miss = [
      !draft.fabric_type_id && !draft.fabric.trim() && c("line.need_fabric"),
      !(num(draft.qty) > 0) && c("line.need_qty"),
      !draft.uom && c("line.need_uom"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    setLines((all) => [...all, draft]);
    setDraft(
      asLine({
        uom: draft.uom,
        dia_unit: draft.dia_unit,
        width_type: draft.width_type,
        required_on: draft.required_on,
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
          gsm: orNull(l.gsm),
          finish_dia: orNull(l.finish_dia),
        })),
      };
      const res = await act(
        doc
          ? `direct-bookings/${encodeURIComponent(doc.booking_no)}`
          : "direct-bookings",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.saved", { no: res.data.booking_no }),
        type: "success",
      });
    });
  }
  const input = (k, label, props = {}) => (
    <Field label={label}>
      <Input
        value={draft[k]}
        onChange={(e) => setD({ [k]: e.target.value })}
        aria-label={label}
        {...props}
      />
    </Field>
  );
  const numeric = { type: "number", min: "0", step: "any" };

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {doc ? doc.booking_no : c("tabs.entry")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {c("hint.form")}
            </p>
          </div>
          {doc ? (
            <StatusChip
              status={TONE[doc.status]}
              label={c(`status.${doc.status}`)}
            />
          ) : null}
        </div>
        {doc && !editable && canManage ? (
          <Alert variant="info" className="mt-3">
            {c("hint.fixed", {
              no: doc.booking_no,
              status: c(`status.${doc.status}`),
            })}
          </Alert>
        ) : null}
        <Field
          label={c("f.existing")}
          hint={c("hint.existing")}
          className="mt-4"
        >
          <PSelect
            value={doc?.booking_no ?? ""}
            onValueChange={(v) => open(v ?? "")}
            options={pick(options.bookings, c("ph.new"))}
            disabled={pending}
            aria-label={c("f.existing")}
          />
        </Field>
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.key")}
        </h3>
        <div className="grid gap-4 md:grid-cols-3">
          <Field label={c("f.booking_no")}>
            <Input
              value={doc?.booking_no ?? ""}
              placeholder={c("ph.issued")}
              disabled
              aria-label={c("f.booking_no")}
            />
          </Field>
          <Field label={c("f.category")} required>
            <PSelect
              value={head.category_code}
              onValueChange={(v) => setH({ category_code: v ?? "" })}
              options={pick(options.categories, c("ph.type"))}
              disabled={!editable}
              aria-label={c("f.category")}
            />
          </Field>
          <Field label={c("f.buyer")} required>
            <PSelect
              value={head.buyer_id}
              onValueChange={(v) =>
                setH({ buyer_id: v ?? "", style_id: "", order_id: "" })
              }
              options={pick(options.buyers, c("ph.select"))}
              disabled={!editable}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.style")}>
            <PSelect
              value={head.style_id}
              onValueChange={(v) => setH({ style_id: v ?? "", order_id: "" })}
              options={pick(styles, c("ph.select"))}
              disabled={!editable}
              aria-label={c("f.style")}
            />
          </Field>
          <Field label={c("f.order")} required>
            <PSelect
              value={head.order_id}
              onValueChange={(v) => {
                const o = options.orders.find((x) => x.value === v);
                setH({
                  order_id: v ?? "",
                  buyer_id: o?.buyer_id ?? head.buyer_id,
                  style_id: o?.style_id ?? head.style_id,
                });
              }}
              options={pick(orders, c("ph.select"))}
              disabled={!editable}
              aria-label={c("f.order")}
            />
          </Field>
          <Field label={c("f.prog_date")} required>
            <Input
              type="date"
              value={head.prog_date}
              onChange={(e) => setH({ prog_date: e.target.value })}
              disabled={!editable}
              aria-label={c("f.prog_date")}
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
        <Field label={c("f.remarks")} className="mt-4">
          <Textarea
            rows={3}
            value={head.remarks}
            onChange={(e) => setH({ remarks: e.target.value })}
            maxLength={2000}
            disabled={!editable}
            aria-label={c("f.remarks")}
          />
        </Field>
      </section>

      <section className={cn(card, "p-5")}>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold text-foreground">
              {c("sec.lines")}
            </h3>
            <p className="mt-0.5 max-w-3xl text-[13px] text-foreground-muted">
              {c("hint.lines")}
            </p>
          </div>
          {editable ? (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={addLine}>
                <Plus /> {c("btn.add_fabric")}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setDraft(fresh())}
              >
                {c("btn.cancel_line")}
              </Button>
            </div>
          ) : null}
        </div>
        {editable ? (
          <div className="mt-4 grid gap-4 md:grid-cols-4">
            <Field label={c("f.fabric_type")}>
              <PSelect
                value={draft.fabric_type_id}
                onValueChange={(v) => setD({ fabric_type_id: v ?? "" })}
                options={pick(options.fabric_types, c("ph.select"))}
                aria-label={c("f.fabric_type")}
              />
            </Field>
            {input("fabric", c("f.fabric"), { maxLength: 160 })}
            <Field label={c("f.colour")}>
              <PSelect
                value={draft.colour_id}
                onValueChange={(v) => setD({ colour_id: v ?? "" })}
                options={pick(options.colours, c("ph.colour"))}
                aria-label={c("f.colour")}
              />
            </Field>
            {input("colour_name", c("f.colour_name"), { maxLength: 80 })}
            {input("gsm", c("f.gsm"), numeric)}
            {input("finish_dia", c("f.finish_dia"), numeric)}
            <Field label={c("f.dia_unit")}>
              <PSelect
                value={draft.dia_unit}
                onValueChange={(v) => setD({ dia_unit: v ?? "" })}
                options={pick(
                  listOf(["INCH", "CM"], "dia_unit"),
                  c("ph.choose"),
                )}
                aria-label={c("f.dia_unit")}
              />
            </Field>
            <Field label={c("f.width_type")}>
              <PSelect
                value={draft.width_type}
                onValueChange={(v) => setD({ width_type: v ?? "" })}
                options={pick(
                  listOf(["OPEN", "TUBE"], "width_type"),
                  c("ph.choose"),
                )}
                aria-label={c("f.width_type")}
              />
            </Field>
            {input("qty", c("f.qty"), numeric)}
            <Field label={c("f.uom")}>
              <PSelect
                value={draft.uom}
                onValueChange={(v) => setD({ uom: v ?? "" })}
                options={pick(options.uoms, c("ph.choose"))}
                aria-label={c("f.uom")}
              />
            </Field>
            {input("required_on", c("f.required_on"), { type: "date" })}
            <Field label={c("f.remarks")}>
              <Input
                value={draft.remarks}
                onChange={(e) => setD({ remarks: e.target.value })}
                maxLength={300}
                aria-label={`${c("f.remarks")} (${c("f.fabric")})`}
              />
            </Field>
          </div>
        ) : null}
        <div className="mt-5 overflow-x-auto rounded-lg border border-border">
          {lines.length === 0 ? (
            <NoLines title={c("empty.lines")} hint={c("empty.lines_hint")} />
          ) : (
            <table className="w-full min-w-[1000px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    ["fabric_type"],
                    ["fabric"],
                    ["colour"],
                    ["gsm", true],
                    ["finish_dia"],
                    ["qty", true],
                    ["uom"],
                    ["required_on"],
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
                    <td className="px-2 py-2">
                      {named(options.fabric_types, l.fabric_type_id) ?? "—"}
                    </td>
                    <td className="px-2 py-2 font-medium">{l.fabric || "—"}</td>
                    <td className="px-2 py-2">
                      {[named(options.colours, l.colour_id), l.colour_name]
                        .filter(Boolean)
                        .join(" · ") || "—"}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {l.gsm || "—"}
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {finDia(c, l) ?? "—"}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(num(l.qty))}
                    </td>
                    <td className="px-2 py-2">{l.uom}</td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {l.required_on ? dayFmt(l.required_on) : "—"}
                    </td>
                    <td className="px-2 py-1.5">
                      {editable ? (
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
                  <td className="px-2 py-2" colSpan={5}>
                    {c("total")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(total)}
                  </td>
                  <td colSpan={3} />
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
          {c("hint.foot")}
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
          {editable ? (
            <Button onClick={save} loading={pending}>
              <Save /> {c("btn.save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

export { DbList, DbEntry };
