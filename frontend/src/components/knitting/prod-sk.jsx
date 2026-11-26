"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Ban,
  Check,
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
  useYs,
  card,
  th,
  Field,
  StatusChip,
  NoLines,
  orNull,
} from "./yarn-common";
import { ScTable } from "./prod-sc";
import { yarnKey, yarnName, lotPickers } from "./prod-yt";

/*
 * Knit Production → Production (AnnotechERP 15/04) → Solid and Yarn-Dyed
 * Sample Knit Plan: the Sample Yarn Requisition List (Knit Plan Search above,
 * Plans and Yarn asked chips; Submit Requisition, Cancel and Print) and the
 * plan's own form — Knit Plan Info, then one line per order or KP with what
 * the sample room wrote about the yarn and the registered yarn the store
 * issues against. Nothing reloads.
 */

const SLUG = {
  SOLID: "knit-plan-for-sample-solid",
  YD: "knit-plan-for-sample-yd",
};
const TONE = {
  DRAFT: "DRAFT",
  REQ_SUBMITTED: "WAITING_APPROVAL",
  ISSUED: "RECEIVED",
  CLOSED: "CLOSED",
  CANCELLED: "CANCELLED",
};

function useSk() {
  const t = useT("knitting");
  return (k, p) => t(`sk.${k}`, p);
}

function printPlan(c, ys, d) {
  printDocument({
    title: c(`p.title.${d.kind}`),
    docNo: d.plan_no,
    orientation: "landscape",
    meta: [
      [c("f.date"), dayFmt(d.plan_date)],
      [c("f.buyer"), d.buyer],
      [c("f.store"), d.store],
      [c("f.req_type"), d.req_type ? ys(`req_type.${d.req_type}`) : null],
      [c("f.company"), d.company],
      [c("f.status"), { v: c(`status.${d.status}`), tone: "info" }],
      [c("col.req_no"), d.req_no],
      [c("p.total_yarn"), `${qtyFmt.format(d.qty)} Kg`],
    ],
    metaColumns: 4,
    sections: [
      {
        title: c("sec.lines"),
        columns: [
          { label: c("col.buyer") },
          { label: c("col.order") },
          { label: c("col.fab_type") },
          { label: c("col.colour") },
          { label: c("col.yarn_details") },
          { label: c("col.registered") },
          { label: c("col.gsm"), align: "right" },
          { label: c("col.fin_dia") },
          { label: c("f.qty"), align: "right" },
        ],
        rows: d.lines.map((l) => [
          l.buyer,
          {
            v: l.order ?? l.style_text ?? l.style ?? "—",
            bold: true,
            sub: l.order && l.style_text ? l.style_text : undefined,
          },
          l.fabric_type,
          [l.colour, l.colour_name].filter(Boolean).join(" · ") || null,
          l.yarn_details,
          [l.yarn, l.colour_group, l.brand].filter(Boolean).join(" · ") || null,
          l.gsm === null ? null : Number(l.gsm).toFixed(2),
          l.finish_dia,
          { v: `${qtyFmt.format(l.qty)} ${l.uom}`, bold: true },
        ]),
        foot: [
          [
            { v: c("total"), bold: true },
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            { v: qtyFmt.format(d.qty), bold: true },
          ],
        ],
        empty: c("p.no_lines"),
      },
    ],
    notes: d.remarks ? [d.remarks] : [],
    signatures: [c("p.sign.sample"), c("p.sign.store"), c("p.sign.approved")],
  });
}

function SkList({ kind, options, initialRows, canManage, act }) {
  const c = useSk();
  const ys = useYs();
  const toastManager = useToastManager();
  const base = `/knit-production/${SLUG[kind]}`;
  const blank = { buyer: "", store: "", status: "", from: "", to: "" };
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
      const res = await act(`sample-plans/${kind}?${q}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  function run() {
    const { row, action } = ask;
    startTransition(async () => {
      const res = await act(
        `sample-plans/${kind}/${encodeURIComponent(row.plan_no)}/${action}`,
        "POST",
      );
      setAsk(null);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows((list) =>
        list.map((x) => (x.plan_no === row.plan_no ? res.data : x)),
      );
      setError(null);
      toastManager.add({
        title: c(`done.${action}`, { no: row.plan_no, req: res.data.req_no }),
        type: "success",
      });
    });
  }
  function print(r) {
    startTransition(async () => {
      const res = await act(
        `sample-plans/${kind}/${encodeURIComponent(r.plan_no)}`,
      );
      if (res?.status !== "success") return setError(messagesOf(res));
      printPlan(c, ys, res.data);
    });
  }
  const columns = [
    {
      key: "plan_no",
      label: c("col.plan_no"),
      nowrap: true,
      render: (r) => (
        <Link
          href={`${base}?tab=entry&ref=${encodeURIComponent(r.plan_no)}`}
          className="font-medium text-brand hover:underline"
        >
          {r.plan_no}
        </Link>
      ),
    },
    {
      key: "plan_date",
      label: c("col.date"),
      nowrap: true,
      text: (r) => dayFmt(r.plan_date),
    },
    { key: "buyer", label: c("col.buyer") },
    { key: "store", label: c("col.store") },
    { key: "lines", label: c("col.lines"), num: true },
    {
      key: "qty",
      label: c("col.yarn_qty"),
      num: true,
      text: (r) => qtyFmt.format(r.qty),
    },
    { key: "req_no", label: c("col.req_no"), nowrap: true },
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
          {c(`hint.list.${kind}`)}
        </p>
        <div className="grid gap-4 md:grid-cols-3 lg:grid-cols-5">
          <Field label={c("f.buyer")}>
            <PSelect
              value={f.buyer}
              onValueChange={(v) => setF("buyer", v)}
              options={pick(options.buyers, c("ph.select"))}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.store")}>
            <PSelect
              value={f.store}
              onValueChange={(v) => setF("store", v)}
              options={pick(options.stores, c("ph.select"))}
              aria-label={c("f.store")}
            />
          </Field>
          <Field label={c("f.status")}>
            <PSelect
              value={f.status}
              onValueChange={(v) => setF("status", v)}
              options={pick(
                options.plan_statuses.map((s) => ({
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
                href={`${base}?tab=entry`}
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
            [c("chip.plans"), rows.length],
            [
              c("chip.yarn"),
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
          {c(`hint.caption.${kind}`)}
        </p>
        <ScTable
          rows={rows}
          columns={columns}
          colFilters
          exportName={`sample-knit-plans-${kind.toLowerCase()}`}
          addHref={canManage ? `${base}?tab=entry` : null}
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
                  onClick={() => setAsk({ row: r, action: "submit" })}
                  disabled={pending}
                  aria-label={`${c("btn.submit")} ${r.plan_no}`}
                >
                  <Check /> {c("btn.submit")}
                </Button>
              ) : null}
              {canManage && ["DRAFT", "REQ_SUBMITTED"].includes(r.status) ? (
                <Button
                  variant="outline"
                  size="sm"
                  className="text-danger"
                  onClick={() => setAsk({ row: r, action: "cancel" })}
                  disabled={pending}
                  aria-label={`${c("btn.cancel")} ${r.plan_no}`}
                >
                  <Ban /> {c("btn.cancel")}
                </Button>
              ) : null}
              <Button
                variant="outline"
                size="sm"
                onClick={() => print(r)}
                disabled={pending}
                aria-label={`${c("btn.print")} ${r.plan_no}`}
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
        onConfirm={run}
      />
    </div>
  );
}

const LINE = [
  "buyer_id",
  "style_id",
  "order_id",
  "style_text",
  "fabric_type_id",
  "colour_id",
  "colour_name",
  "yarn_details",
  "composition_id",
  "yarn_count_id",
  "colour_group",
  "brand",
  "qty",
  "uom",
  "gsm",
  "finish_dia",
  "machine_dia",
  "gauge",
  "remarks",
];
const HEAD = ["plan_date", "buyer_id", "store_id", "req_type", "remarks"];
let seq = 0;
const asLine = (l = {}) => ({
  key: `k${++seq}`,
  ...Object.fromEntries(LINE.map((k) => [k, str(l[k])])),
});

function SkEntry({ kind, options, initial, canManage, act }) {
  const c = useSk();
  const ys = useYs();
  const toastManager = useToastManager();
  const base = `/knit-production/${SLUG[kind]}`;
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, str(d?.[k])])),
    plan_date: str(d?.plan_date) || today,
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [draft, setDraft] = useState(() => asLine({ uom: "kg" }));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.status === "DRAFT");
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setD = (patch) => setDraft((d) => ({ ...d, ...patch }));
  const pick = (list, ph) => [{ value: "", label: ph }, ...list];
  const named = (list, v) => list.find((x) => x.value === v)?.label ?? null;
  const draftKey = draft.composition_id ? yarnKey(draft) : "";
  const { items, brands } = lotPickers(options, head.store_id, draftKey);
  const styles = options.styles.filter(
    (s) => !draft.buyer_id || s.buyer_id === draft.buyer_id,
  );
  const orders = options.orders.filter(
    (o) =>
      (!draft.buyer_id || o.buyer_id === draft.buyer_id) &&
      (!draft.style_id || o.style_id === draft.style_id),
  );
  const existing = options.plans.filter((p) => p.kind === kind);
  const total = lines.reduce((a, l) => a + num(l.qty), 0);

  function show(d) {
    setDoc(d);
    setHead(asHead(d));
    setLines((d?.lines ?? []).map(asLine));
    setDraft(asLine({ uom: "kg" }));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      d ? `?tab=entry&ref=${encodeURIComponent(d.plan_no)}` : "?tab=entry",
    );
  }
  function open(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`sample-plans/${kind}/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function addLine() {
    const miss = [
      !(num(draft.qty) > 0) && c("line.need_qty"),
      !draft.uom && c("line.need_uom"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    setLines((all) => [...all, draft]);
    setDraft(asLine({ uom: draft.uom, buyer_id: draft.buyer_id }));
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
        })),
      };
      const res = await act(
        doc
          ? `sample-plans/${kind}/${encodeURIComponent(doc.plan_no)}`
          : `sample-plans/${kind}`,
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.saved", { no: res.data.plan_no }),
        type: "success",
      });
    });
  }
  const text = (k, label, props = {}) => (
    <Field label={label} hint={props.hint}>
      <Input
        value={draft[k]}
        onChange={(e) => setD({ [k]: e.target.value })}
        aria-label={label}
        maxLength={props.maxLength ?? 24}
        type={props.type}
        min={props.type === "number" ? "0" : undefined}
        step={props.type === "number" ? "any" : undefined}
      />
    </Field>
  );

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {doc ? doc.plan_no : c(`tabs.entry.${kind}`)}
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
              no: doc.plan_no,
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
            value={doc?.plan_no ?? ""}
            onValueChange={(v) => open(v ?? "")}
            options={pick(existing, c("ph.new"))}
            disabled={pending}
            aria-label={c("f.existing")}
          />
        </Field>
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.info")}
        </h3>
        <div className="grid gap-4 md:grid-cols-3">
          <Field label={c("f.plan_no")}>
            <Input
              value={doc?.plan_no ?? ""}
              placeholder={c("ph.issued")}
              disabled
              aria-label={c("f.plan_no")}
            />
          </Field>
          <Field label={c("f.date")} required>
            <Input
              type="date"
              value={head.plan_date}
              onChange={(e) => setH({ plan_date: e.target.value })}
              disabled={!editable}
              aria-label={c("f.date")}
            />
          </Field>
          <Field label={c("f.buyer")}>
            <PSelect
              value={head.buyer_id}
              onValueChange={(v) => setH({ buyer_id: v ?? "" })}
              options={pick(options.buyers, c("ph.select"))}
              disabled={!editable}
              aria-label={c("f.buyer")}
            />
          </Field>
          <Field label={c("f.store")} hint={c("hint.store")}>
            <PSelect
              value={head.store_id}
              onValueChange={(v) => setH({ store_id: v ?? "" })}
              options={pick(options.stores, c("ph.select"))}
              disabled={!editable}
              aria-label={c("f.store")}
            />
          </Field>
          <Field label={c("f.req_type")} hint={c("hint.req_type")}>
            <PSelect
              value={head.req_type}
              onValueChange={(v) => setH({ req_type: v ?? "" })}
              options={pick(
                options.req_types.map((t) => ({
                  value: t,
                  label: ys(`req_type.${t}`),
                })),
                c("ph.select"),
              )}
              disabled={!editable}
              aria-label={c("f.req_type")}
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
                <Plus /> {c("btn.add_line")}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setDraft(asLine({ uom: "kg" }))}
              >
                {c("btn.cancel_line")}
              </Button>
            </div>
          ) : null}
        </div>
        {editable ? (
          <div className="mt-4 grid gap-4 md:grid-cols-4">
            <Field label={c("f.buyer")}>
              <PSelect
                value={draft.buyer_id}
                onValueChange={(v) =>
                  setD({ buyer_id: v ?? "", style_id: "", order_id: "" })
                }
                options={pick(options.buyers, c("ph.select"))}
                aria-label={`${c("f.buyer")} (${c("f.order")})`}
              />
            </Field>
            <Field label={c("f.style")}>
              <PSelect
                value={draft.style_id}
                onValueChange={(v) => setD({ style_id: v ?? "", order_id: "" })}
                options={pick(styles, c("ph.style"))}
                aria-label={c("f.style")}
              />
            </Field>
            <Field label={c("f.order")}>
              <PSelect
                value={draft.order_id}
                onValueChange={(v) => {
                  const o = options.orders.find((x) => x.value === v);
                  setD({
                    order_id: v ?? "",
                    buyer_id: o?.buyer_id ?? draft.buyer_id,
                    style_id: o?.style_id ?? draft.style_id,
                  });
                }}
                options={pick(orders, c("ph.order"))}
                aria-label={c("f.order")}
              />
            </Field>
            {text("style_text", c("f.style_text"), {
              maxLength: 80,
              hint: c("hint.style_text"),
            })}
            <Field label={c("f.fab_type")}>
              <PSelect
                value={draft.fabric_type_id}
                onValueChange={(v) => setD({ fabric_type_id: v ?? "" })}
                options={pick(options.fabric_types, c("ph.select"))}
                aria-label={c("f.fab_type")}
              />
            </Field>
            <Field label={c("f.colour")}>
              <PSelect
                value={draft.colour_id}
                onValueChange={(v) => setD({ colour_id: v ?? "" })}
                options={pick(options.colours, c("ph.colour"))}
                aria-label={c("f.colour")}
              />
            </Field>
            {text("colour_name", c("f.colour_name"), { maxLength: 80 })}
            {text("yarn_details", c("f.yarn_details"), { maxLength: 200 })}
            <Field label={c("f.registered")}>
              <PSelect
                value={draftKey}
                onValueChange={(v) => {
                  const [composition_id = "", yarn_count_id = ""] = (
                    v ?? ""
                  ).split("|");
                  setD({ composition_id, yarn_count_id, brand: "" });
                }}
                options={pick(items, c("ph.yarn"))}
                aria-label={c("f.registered")}
              />
            </Field>
            <Field label={c("f.col_grp")}>
              <PSelect
                value={draft.colour_group}
                onValueChange={(v) => setD({ colour_group: v ?? "" })}
                options={pick(
                  options.colour_groups.map((g) => ({ value: g, label: g })),
                  c("ph.group"),
                )}
                aria-label={c("f.col_grp")}
              />
            </Field>
            <Field label={c("f.brand")}>
              <PSelect
                value={draft.brand}
                onValueChange={(v) => setD({ brand: v ?? "" })}
                options={pick(brands, c("ph.brand"))}
                aria-label={c("f.brand")}
              />
            </Field>
            {text("qty", c("f.qty"), { type: "number" })}
            <Field label={c("f.uom")}>
              <PSelect
                value={draft.uom}
                onValueChange={(v) => setD({ uom: v ?? "" })}
                options={pick(options.uoms, c("ph.choose"))}
                aria-label={c("f.uom")}
              />
            </Field>
            {text("gsm", c("f.gsm"), { type: "number" })}
            {text("finish_dia", c("f.fin_dia"))}
            {text("machine_dia", c("f.mc_dia"))}
            {text("gauge", c("f.gauge"))}
            <Field label={c("f.remarks")} className="md:col-span-2">
              <Input
                value={draft.remarks}
                onChange={(e) => setD({ remarks: e.target.value })}
                maxLength={300}
                aria-label={`${c("f.remarks")} (${c("f.order")})`}
              />
            </Field>
          </div>
        ) : null}
        <div className="mt-5 overflow-x-auto rounded-lg border border-border">
          {lines.length === 0 ? (
            <NoLines title={c("empty.lines")} hint={c("empty.lines_hint")} />
          ) : (
            <table className="w-full min-w-[1200px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "buyer",
                    "style",
                    "order",
                    "style_text",
                    "fab_type",
                    "colour",
                    "yarn_details",
                    "registered",
                  ].map((k) => (
                    <th key={k} className={th}>
                      {c(`col.${k}`)}
                    </th>
                  ))}
                  <th className={cn(th, "text-right")}>{c("col.qty")}</th>
                  <th className={th} />
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={l.key} className="border-t border-border">
                    <td className="px-2 py-2">
                      {named(options.buyers, l.buyer_id) ?? "—"}
                    </td>
                    <td className="px-2 py-2">
                      {named(options.styles, l.style_id) ?? "—"}
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {named(options.orders, l.order_id) ?? "—"}
                    </td>
                    <td className="px-2 py-2">{l.style_text || "—"}</td>
                    <td className="px-2 py-2">
                      {named(options.fabric_types, l.fabric_type_id) ?? "—"}
                    </td>
                    <td className="px-2 py-2">
                      {[named(options.colours, l.colour_id), l.colour_name]
                        .filter(Boolean)
                        .join(" · ") || "—"}
                    </td>
                    <td className="px-2 py-2">{l.yarn_details || "—"}</td>
                    <td className="px-2 py-2">
                      {l.composition_id || l.yarn_count_id
                        ? [yarnName(options, l), l.colour_group, l.brand]
                            .filter(Boolean)
                            .join(" · ")
                        : "—"}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(num(l.qty))} {l.uom}
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
                  <td className="px-2 py-2" colSpan={8}>
                    {c("total")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(total)}
                  </td>
                  <td />
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
          <Link href={base} className={buttonVariants({ variant: "outline" })}>
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

export { SkList, SkEntry, SLUG as SK_SLUG };
