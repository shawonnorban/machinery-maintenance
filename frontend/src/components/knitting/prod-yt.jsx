"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Ban,
  Eraser,
  FilterX,
  Plus,
  Printer,
  Save,
  Send,
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

/*
 * Knit Production → Production (AnnotechERP 15/04) → Knit Plan & Yarn Test
 * Indent: the list of test orders (Status, Req. By, Attn. To and Order Date
 * above, a filter under every column; Knit Card #, Issue and Cancel by
 * status) and the test order's own form — Key Information, People, Yarn,
 * then one line per lot going to the lab, the yarn picked from what the
 * chosen store has held. Nothing reloads. Also what the yarn test screens
 * share: the hook, the status tones and the lot pickers.
 */

const BASE = "/knit-production/knit-plan-requisition-for-yarn-test";
const TONE = {
  WAITING_ISSUE: "WAITING_APPROVAL",
  ISSUED: "PLACED",
  TESTED: "RECEIVED",
  CANCELLED: "CANCELLED",
};
const RESULT_TONE = {
  PENDING: "DRAFT",
  PASSED: "RECEIVED",
  FAILED: "REJECTED",
  CONDITIONAL: "WAITING_APPROVAL",
};

function useYt() {
  const t = useT("knitting");
  return (k, p) => t(`yt.${k}`, p);
}

const yarnKey = (x) => `${x.composition_id ?? ""}|${x.yarn_count_id ?? ""}`;
/** A lot's yarn as the pickers name it: "100% Cotton 30/1". */
const yarnName = (options, x) =>
  [
    options.yarn_names.compositions[x.composition_id],
    options.yarn_names.counts[x.yarn_count_id],
  ]
    .filter(Boolean)
    .join(" ");

/** Yarn Item and Brand options from the lots the stores have held (one store's when given). */
function lotPickers(options, storeId, picked) {
  const lots = options.lots.filter((l) => !storeId || l.store_id === storeId);
  const items = [
    ...new Map(
      lots.map((l) => [yarnKey(l), yarnName(options, l) || l.yarn]),
    ).entries(),
  ].map(([value, label]) => ({ value, label }));
  const brands = [
    ...new Set(
      lots
        .filter((l) => !picked || yarnKey(l) === picked)
        .map((l) => l.brand)
        .filter(Boolean),
    ),
  ].map((b) => ({ value: b, label: b }));
  return { items, brands };
}

function printOrder(c, ys, d) {
  const total = d.lines.reduce((a, l) => a + num(l.qty), 0);
  printDocument({
    title: c("p.order_title"),
    docNo: d.order_no,
    orientation: "landscape",
    meta: [
      [c("f.order_date"), dayFmt(d.order_date)],
      [c("f.company"), d.company],
      [c("f.req_type"), ys(`req_type.${d.req_type}`)],
      [c("f.store"), d.store],
      [c("f.req_by"), d.requested_by_name],
      [c("f.attn_to"), d.attn_to_name],
      [c("f.yarn_description"), d.yarn_description],
      [c("f.knit_card"), d.knit_card_no],
      [c("f.status"), { v: c(`status.${d.status}`), tone: "info" }],
    ],
    metaColumns: 3,
    sections: [
      {
        title: c("p.lots"),
        columns: [
          { label: "#", align: "center", width: "4%" },
          { label: c("col.yarn_item") },
          { label: c("col.colour_group") },
          { label: c("col.brand") },
          { label: c("col.supplier") },
          { label: c("col.lc") },
          { label: c("col.lot") },
          { label: c("col.qty"), align: "right" },
          { label: c("col.uom"), align: "center" },
          { label: c("col.result") },
          { label: c("col.remarks") },
        ],
        rows: d.lines.map((l, i) => [
          i + 1,
          { v: l.yarn, bold: true },
          l.colour_group,
          l.brand,
          l.supplier,
          l.lc_no,
          { v: l.lot_no, bold: true },
          l.qty === null ? null : qtyFmt.format(l.qty),
          l.uom,
          l.result
            ? {
                v: c(`result.${l.result}`),
                tone:
                  l.result === "PASSED"
                    ? "ok"
                    : l.result === "FAILED"
                      ? "bad"
                      : "warn",
              }
            : null,
          l.remarks,
        ]),
        foot: [
          [
            "",
            { v: c("p.total"), bold: true },
            "",
            "",
            "",
            "",
            "",
            { v: qtyFmt.format(total), bold: true },
            "",
            "",
            "",
          ],
        ],
        empty: c("p.no_lots"),
      },
    ],
    notes: d.remarks ? [d.remarks] : [],
    signatures: [c("p.sign.req_by"), c("p.sign.store"), c("p.sign.lab")],
  });
}

function YtoList({ options, initialRows, canManage, act }) {
  const c = useYt();
  const ys = useYs();
  const toastManager = useToastManager();
  const blank = { status: "", req_by: "", attn_to: "", from: "", to: "" };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [ask, setAsk] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const all = (list) => [{ value: "", label: c("ph.all") }, ...list];
  const shown = rows.filter(
    (r) =>
      (!f.status || r.status === f.status) &&
      (!f.req_by || r.requested_by_id === f.req_by) &&
      (!f.attn_to || r.attn_to_id === f.attn_to) &&
      (!f.from || r.order_date >= f.from) &&
      (!f.to || r.order_date <= f.to),
  );

  function refresh() {
    startTransition(async () => {
      const res = await act("yarn-test/orders");
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  function run() {
    const { row, action } = ask;
    startTransition(async () => {
      const res = await act(
        `yarn-test/orders/${encodeURIComponent(row.order_no)}/${action}`,
        "POST",
      );
      setAsk(null);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows((list) =>
        list.map((x) => (x.order_no === row.order_no ? res.data : x)),
      );
      setError(null);
      toastManager.add({
        title: c(`done.${action}`, { no: row.order_no }),
        type: "success",
      });
    });
  }
  function print(r) {
    startTransition(async () => {
      const res = await act(
        `yarn-test/orders/${encodeURIComponent(r.order_no)}`,
      );
      if (res?.status !== "success") return setError(messagesOf(res));
      printOrder(c, ys, res.data);
    });
  }
  const columns = [
    {
      key: "order_no",
      label: c("col.order_no"),
      nowrap: true,
      render: (r) => (
        <Link
          href={`${BASE}?tab=entry&ref=${encodeURIComponent(r.order_no)}`}
          className="font-medium text-brand hover:underline"
        >
          {r.order_no}
        </Link>
      ),
    },
    {
      key: "order_date",
      label: c("col.order_date"),
      nowrap: true,
      text: (r) => dayFmt(r.order_date),
    },
    { key: "requested_by", label: c("col.req_by") },
    { key: "attn_to", label: c("col.attn_to") },
    { key: "yarn_description", label: c("col.yarn_description") },
    { key: "knit_card_no", label: c("col.knit_card") },
    { key: "lots", label: c("col.lots") },
    {
      key: "results",
      label: c("col.results"),
      text: (r) => r.results ?? c(`status.${r.status}`),
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <StatusChip status={TONE[r.status]} label={c(`status.${r.status}`)} />
          {r.results ? (
            <span className="text-xs text-foreground-muted">{r.results}</span>
          ) : null}
        </span>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Field label={c("f.status")}>
            <PSelect
              value={f.status}
              onValueChange={(v) => setF("status", v)}
              options={all(
                options.statuses.map((s) => ({
                  value: s,
                  label: c(`status.${s}`),
                })),
              )}
              aria-label={c("f.status")}
            />
          </Field>
          <Field label={c("f.req_by")}>
            <PSelect
              value={f.req_by}
              onValueChange={(v) => setF("req_by", v)}
              options={all(options.employees)}
              aria-label={c("f.req_by")}
            />
          </Field>
          <Field label={c("f.attn_to")}>
            <PSelect
              value={f.attn_to}
              onValueChange={(v) => setF("attn_to", v)}
              options={all(options.employees)}
              aria-label={c("f.attn_to")}
            />
          </Field>
          <Field label={c("f.order_date")}>
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
              href={`${BASE}?tab=entry`}
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
          {c("hint.order_list")}
        </p>
        <ScTable
          rows={shown}
          columns={columns}
          colFilters
          exportName="yarn-test-orders"
          addHref={canManage ? `${BASE}?tab=entry` : null}
          empty={<NoLines title={c("empty.orders")} hint="" />}
          onRefresh={refresh}
          pending={pending}
          actions={(r) => (
            <>
              <Button
                variant="outline"
                size="sm"
                onClick={() => print(r)}
                disabled={pending}
                aria-label={`${c("btn.knit_card")} ${r.order_no}`}
              >
                <Printer /> {c("btn.knit_card")}
              </Button>
              {canManage && r.status === "WAITING_ISSUE" ? (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setAsk({ row: r, action: "issue" })}
                  disabled={pending}
                  aria-label={`${c("btn.issue")} ${r.order_no}`}
                >
                  <Send /> {c("btn.issue")}
                </Button>
              ) : null}
              {canManage && ["WAITING_ISSUE", "ISSUED"].includes(r.status) ? (
                <Button
                  variant="outline"
                  size="sm"
                  className="text-danger"
                  onClick={() => setAsk({ row: r, action: "cancel" })}
                  disabled={pending}
                  aria-label={`${c("btn.cancel")} ${r.order_no}`}
                >
                  <Ban /> {c("btn.cancel")}
                </Button>
              ) : null}
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
  "composition_id",
  "yarn_count_id",
  "colour_group",
  "brand",
  "supplier_id",
  "lc_no",
  "lot_no",
  "qty",
  "uom",
  "remarks",
];
const HEAD = [
  "order_date",
  "req_type",
  "store_id",
  "requested_by",
  "attn_to",
  "yarn_description",
  "knit_card_no",
  "remarks",
];
let seq = 0;
const asLine = (l = {}) => ({
  key: `t${++seq}`,
  ...Object.fromEntries(LINE.map((k) => [k, str(l[k])])),
});

function YtoEntry({ options, initial, canManage, act }) {
  const c = useYt();
  const ys = useYs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const asHead = (d) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, str(d?.[k])])),
    order_date: str(d?.order_date) || today,
    req_type: str(d?.req_type) || "YARN_TEST",
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [draft, setDraft] = useState(() => asLine());
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable =
    canManage && (!doc || ["WAITING_ISSUE", "ISSUED"].includes(doc.status));
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setD = (patch) => setDraft((d) => ({ ...d, ...patch }));
  const pick = (list, ph) => [{ value: "", label: ph }, ...list];
  const draftKey = draft.composition_id ? yarnKey(draft) : "";
  const { items, brands } = lotPickers(options, head.store_id, draftKey);
  const named = (list, v) => list.find((x) => x.value === v)?.label ?? null;
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
      d ? `?tab=entry&ref=${encodeURIComponent(d.order_no)}` : "?tab=entry",
    );
  }
  function open(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`yarn-test/orders/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function addLine() {
    if (!draft.lot_no.trim()) return setErrors([c("line.need_lot")]);
    setLines((all) => [...all, draft]);
    setDraft(asLine({ uom: draft.uom, supplier_id: draft.supplier_id }));
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
        })),
      };
      const res = await act(
        doc
          ? `yarn-test/orders/${encodeURIComponent(doc.order_no)}`
          : "yarn-test/orders",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.saved", { no: res.data.order_no }),
        type: "success",
      });
    });
  }
  const lineYarn = (l) =>
    l.composition_id ? yarnName(options, l) || "—" : "—";

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {doc ? doc.order_no : c("tabs.order_entry")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {c("hint.order_form")}
            </p>
          </div>
          {doc ? (
            <StatusChip
              status={TONE[doc.status]}
              label={c(`status.${doc.status}`)}
            />
          ) : (
            <StatusChip status="PLACED" label={c("new_badge")} />
          )}
        </div>
        {doc && !editable && canManage ? (
          <Alert variant="info" className="mt-3">
            {c("hint.order_fixed", {
              no: doc.order_no,
              status: c(`status.${doc.status}`),
            })}
          </Alert>
        ) : null}
        <Field
          label={c("f.existing_order")}
          hint={c("hint.existing")}
          className="mt-4"
        >
          <PSelect
            value={doc?.order_no ?? ""}
            onValueChange={(v) => open(v ?? "")}
            options={pick(options.orders, c("ph.new_order"))}
            disabled={pending}
            aria-label={c("f.existing_order")}
          />
        </Field>
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.key")}
        </h3>
        <div className="grid gap-4 md:grid-cols-3">
          <Field label={c("f.order_no")}>
            <Input
              value={doc?.order_no ?? ""}
              placeholder={c("ph.issued")}
              disabled
              aria-label={c("f.order_no")}
            />
          </Field>
          <Field label={c("f.order_date")} required>
            <Input
              type="date"
              value={head.order_date}
              onChange={(e) => setH({ order_date: e.target.value })}
              disabled={!editable}
              aria-label={c("f.order_date")}
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
          <Field label={c("f.store")}>
            <PSelect
              value={head.store_id}
              onValueChange={(v) => setH({ store_id: v ?? "" })}
              options={pick(options.stores, c("ph.select"))}
              disabled={!editable}
              aria-label={c("f.store")}
            />
          </Field>
        </div>
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.people")}
        </h3>
        <div className="grid gap-4 md:grid-cols-3">
          <Field label={c("f.req_by")}>
            <PSelect
              value={head.requested_by}
              onValueChange={(v) => setH({ requested_by: v ?? "" })}
              options={pick(options.employees, c("ph.select"))}
              disabled={!editable}
              aria-label={c("f.req_by")}
            />
          </Field>
          <Field label={c("f.attn_to")}>
            <PSelect
              value={head.attn_to}
              onValueChange={(v) => setH({ attn_to: v ?? "" })}
              options={pick(options.employees, c("ph.select"))}
              disabled={!editable}
              aria-label={c("f.attn_to")}
            />
          </Field>
        </div>
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.yarn")}
        </h3>
        <div className="grid gap-4 md:grid-cols-3">
          <Field
            label={c("f.yarn_description")}
            required
            hint={c("hint.yarn_description")}
            className="md:col-span-3"
          >
            <Input
              value={head.yarn_description}
              onChange={(e) => setH({ yarn_description: e.target.value })}
              maxLength={200}
              disabled={!editable}
              aria-label={c("f.yarn_description")}
            />
          </Field>
          <Field label={c("f.knit_card")} hint={c("hint.knit_card")}>
            <Input
              value={head.knit_card_no}
              onChange={(e) => setH({ knit_card_no: e.target.value })}
              maxLength={40}
              disabled={!editable}
              aria-label={c("f.knit_card")}
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
          <p className="max-w-3xl text-[13px] text-foreground-muted">
            {c("hint.order_lines")}
          </p>
          {editable ? (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={addLine}>
                <Plus /> {c("btn.add_yarn")}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setDraft(asLine())}
              >
                {c("btn.cancel_line")}
              </Button>
            </div>
          ) : null}
        </div>
        {editable ? (
          <div className="mt-4 grid gap-4 md:grid-cols-3">
            <Field label={c("f.yarn_item")}>
              <PSelect
                value={draftKey}
                onValueChange={(v) => {
                  const [composition_id = "", yarn_count_id = ""] = (
                    v ?? ""
                  ).split("|");
                  setD({ composition_id, yarn_count_id, brand: "" });
                }}
                options={pick(items, c("ph.yarn"))}
                aria-label={c("f.yarn_item")}
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
            <Field label={c("f.brand")}>
              <PSelect
                value={draft.brand}
                onValueChange={(v) => setD({ brand: v ?? "" })}
                options={pick(brands, c("ph.brand"))}
                aria-label={c("f.brand")}
              />
            </Field>
            <Field label={c("f.supplier")}>
              <PSelect
                value={draft.supplier_id}
                onValueChange={(v) => setD({ supplier_id: v ?? "" })}
                options={pick(options.suppliers, c("ph.supplier"))}
                aria-label={c("f.supplier")}
              />
            </Field>
            <Field label={c("f.lc")}>
              <Input
                value={draft.lc_no}
                onChange={(e) => setD({ lc_no: e.target.value })}
                maxLength={40}
                aria-label={c("f.lc")}
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
                type="number"
                min="0"
                step="any"
                value={draft.qty}
                onChange={(e) => setD({ qty: e.target.value })}
                aria-label={c("f.qty")}
              />
            </Field>
            <Field label={c("f.uom")}>
              <PSelect
                value={draft.uom}
                onValueChange={(v) => setD({ uom: v ?? "" })}
                options={pick(options.uoms, c("ph.uom"))}
                aria-label={c("f.uom")}
              />
            </Field>
            <Field label={c("f.remarks")}>
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
            <NoLines title={c("empty.lines")} hint={c("empty.order_lines")} />
          ) : (
            <table className="w-full min-w-[1100px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "yarn_item",
                    "colour_group",
                    "brand",
                    "supplier",
                    "lc",
                    "lot",
                    "qty",
                    "uom",
                    "remarks",
                  ].map((k) => (
                    <th key={k} className={cn(th, k === "qty" && "text-right")}>
                      {c(`col.${k}`)}
                    </th>
                  ))}
                  <th className={th} />
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={l.key} className="border-t border-border">
                    <td className="px-2 py-2 font-medium">{lineYarn(l)}</td>
                    <td className="px-2 py-2">{l.colour_group || "—"}</td>
                    <td className="px-2 py-2">{l.brand || "—"}</td>
                    <td className="px-2 py-2">
                      {named(options.suppliers, l.supplier_id) ?? "—"}
                    </td>
                    <td className="px-2 py-2">{l.lc_no || "—"}</td>
                    <td className="px-2 py-2 font-semibold">{l.lot_no}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {l.qty === "" ? "—" : qtyFmt.format(num(l.qty))}
                    </td>
                    <td className="px-2 py-2">{l.uom || "—"}</td>
                    <td className="px-2 py-2">{l.remarks || "—"}</td>
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
                  <td className="px-2 py-2" colSpan={6}>
                    {c("p.total")}
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
          {c("hint.order_foot")}
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
              <Save /> {c("btn.save_order")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

export {
  useYt,
  yarnKey,
  yarnName,
  lotPickers,
  YtoList,
  YtoEntry,
  RESULT_TONE,
  TONE as ORDER_TONE,
};
