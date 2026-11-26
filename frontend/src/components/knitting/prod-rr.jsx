"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Ban,
  Check,
  Eraser,
  FilterX,
  Plus,
  Save,
  Send,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";
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
 * Knit Production → Production (AnnotechERP 15/04) → Supplier Yarn
 * Replacement Indent: the claims raised on spinners (Supplier, Return Type,
 * Status and Requisition Date above; Submit, Issue and Cancel, each asked
 * first) and the claim's own form — Key Information, Claim, then one line
 * per lot going back. Nothing reloads.
 */

const BASE = "/knit-production/requisition-for-yarn-replace-by-supplier";
const TONE = {
  DRAFT: "DRAFT",
  SUBMITTED: "WAITING_APPROVAL",
  ISSUED: "RECEIVED",
  CANCELLED: "CANCELLED",
};
const STEPS = [
  ["submit", ["DRAFT"], Check],
  ["issue", ["SUBMITTED"], Send],
  ["cancel", ["DRAFT", "SUBMITTED"], Ban],
];

function useRr() {
  const t = useT("knitting");
  return (k, p) => t(`rr.${k}`, p);
}

function RrList({ options, initialRows, canManage, act }) {
  const c = useRr();
  const ys = useYs();
  const toastManager = useToastManager();
  const blank = { supplier: "", type: "", status: "", from: "", to: "" };
  const [f, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [ask, setAsk] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((x) => ({ ...x, [k]: v ?? "" }));
  const all = (list) => [{ value: "", label: c("ph.all") }, ...list];
  const shown = rows.filter(
    (r) =>
      (!f.supplier || r.supplier_id === f.supplier) &&
      (!f.type || r.return_type === f.type) &&
      (!f.status || r.status === f.status) &&
      (!f.from || r.req_date >= f.from) &&
      (!f.to || r.req_date <= f.to),
  );

  function refresh() {
    startTransition(async () => {
      const res = await act("replace-reqs");
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
    });
  }
  function run() {
    const { row, action } = ask;
    startTransition(async () => {
      const res = await act(
        `replace-reqs/${encodeURIComponent(row.req_no)}/${action}`,
        "POST",
      );
      setAsk(null);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows((list) =>
        list.map((x) => (x.req_no === row.req_no ? res.data : x)),
      );
      setError(null);
      toastManager.add({
        title: c(`done.${action}`, { no: row.req_no }),
        type: "success",
      });
    });
  }
  const columns = [
    {
      key: "req_no",
      label: c("col.req_no"),
      nowrap: true,
      render: (r) => (
        <span className="flex flex-col items-start gap-1">
          <Link
            href={`${BASE}?tab=entry&ref=${encodeURIComponent(r.req_no)}`}
            className="font-medium text-brand hover:underline"
          >
            {r.req_no}
          </Link>
          <StatusChip status={TONE[r.status]} label={c(`status.${r.status}`)} />
        </span>
      ),
    },
    {
      key: "req_date",
      label: c("col.req_date"),
      nowrap: true,
      text: (r) => dayFmt(r.req_date),
    },
    { key: "company", label: c("col.company") },
    { key: "store", label: c("col.store") },
    { key: "supplier", label: c("col.supplier") },
    {
      key: "return_type",
      label: c("col.return_type"),
      text: (r) => ys(`return_type.${r.return_type}`),
    },
    { key: "lc_no", label: c("col.lc") },
    { key: "lot_ref", label: c("col.lot") },
    { key: "lines", label: c("col.lines"), num: true },
    {
      key: "qty",
      label: c("col.qty"),
      num: true,
      text: (r) => qtyFmt.format(r.qty),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <Field label={c("f.supplier")}>
            <PSelect
              value={f.supplier}
              onValueChange={(v) => setF("supplier", v)}
              options={all(options.suppliers)}
              aria-label={c("f.supplier")}
            />
          </Field>
          <Field label={c("f.return_type")}>
            <PSelect
              value={f.type}
              onValueChange={(v) => setF("type", v)}
              options={all(
                options.return_types.map((t) => ({
                  value: t,
                  label: ys(`return_type.${t}`),
                })),
              )}
              aria-label={c("f.return_type")}
            />
          </Field>
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
          <Field label={c("f.req_date")} className="lg:col-span-2">
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
          {c("hint.list")}
        </p>
        <ScTable
          rows={shown}
          columns={columns}
          exportName="supplier-yarn-replacement-indent"
          addHref={canManage ? `${BASE}?tab=entry` : null}
          empty={<NoLines title={c("empty.list")} hint="" />}
          onRefresh={refresh}
          pending={pending}
          actions={(r) =>
            canManage
              ? STEPS.filter(([, from]) => from.includes(r.status)).map(
                  ([action, , Icon]) => (
                    <Button
                      key={action}
                      variant="outline"
                      size="sm"
                      className={
                        action === "cancel" ? "text-danger" : undefined
                      }
                      onClick={() => setAsk({ row: r, action })}
                      disabled={pending}
                      aria-label={`${c(`btn.${action}`)} ${r.req_no}`}
                    >
                      <Icon /> {c(`btn.${action}`)}
                    </Button>
                  ),
                )
              : null
          }
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
  "lot_no",
  "qty",
  "uom",
  "remarks",
];
const HEAD = [
  "req_date",
  "store_id",
  "supplier_id",
  "return_type",
  "lc_no",
  "lot_ref",
  "remarks",
];
let seq = 0;
const asLine = (l = {}) => ({
  key: `p${++seq}`,
  ...Object.fromEntries(LINE.map((k) => [k, str(l[k])])),
});

function RrEntry({ options, initial, canManage, act }) {
  const c = useRr();
  const ys = useYs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const yarnStore =
    options.stores.find((s) => /yarn/i.test(s.label))?.value ?? "";
  const asHead = (d) => ({
    ...Object.fromEntries(HEAD.map((k) => [k, str(d?.[k])])),
    req_date: str(d?.req_date) || today,
    store_id: d ? str(d.store_id) : yarnStore,
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
  const draftKey = draft.composition_id ? yarnKey(draft) : "";
  const { items, brands } = lotPickers(options, head.store_id, draftKey);
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
      d ? `?tab=entry&ref=${encodeURIComponent(d.req_no)}` : "?tab=entry",
    );
  }
  function open(no) {
    if (!no) return show(null);
    startTransition(async () => {
      const res = await act(`replace-reqs/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
    });
  }
  function addLine() {
    const miss = [
      !draft.lot_no.trim() && c("line.need_lot"),
      !(num(draft.qty) > 0) && c("line.need_qty"),
      !draft.uom && c("line.need_uom"),
    ].filter(Boolean);
    if (miss.length) return setErrors(miss);
    setLines((all) => [
      ...all,
      { ...draft, lot_no: draft.lot_no.trim().toUpperCase() },
    ]);
    setDraft(asLine({ uom: draft.uom, colour_group: draft.colour_group }));
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
        doc ? `replace-reqs/${encodeURIComponent(doc.req_no)}` : "replace-reqs",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      show(res.data);
      toastManager.add({
        title: c("done.saved", { no: res.data.req_no }),
        type: "success",
      });
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {doc ? doc.req_no : c("tabs.entry")}
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
              no: doc.req_no,
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
            value={doc?.req_no ?? ""}
            onValueChange={(v) => open(v ?? "")}
            options={pick(options.reqs, c("ph.new"))}
            disabled={pending}
            aria-label={c("f.existing")}
          />
        </Field>
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.key")}
        </h3>
        <div className="grid gap-4 md:grid-cols-4">
          <Field label={c("f.req_no")}>
            <Input
              value={doc?.req_no ?? ""}
              placeholder={c("ph.issued")}
              disabled
              aria-label={c("f.req_no")}
            />
          </Field>
          <Field label={c("f.req_date")} required>
            <Input
              type="date"
              value={head.req_date}
              onChange={(e) => setH({ req_date: e.target.value })}
              disabled={!editable}
              aria-label={c("f.req_date")}
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
              options={pick(options.stores, c("ph.select"))}
              disabled={!editable}
              aria-label={c("f.store")}
            />
          </Field>
        </div>
        <h3 className="mt-5 mb-3 text-sm font-semibold text-foreground">
          {c("sec.claim")}
        </h3>
        <div className="grid gap-4 md:grid-cols-4">
          <Field label={c("f.supplier")} required>
            <PSelect
              value={head.supplier_id}
              onValueChange={(v) => setH({ supplier_id: v ?? "" })}
              options={pick(options.suppliers, c("ph.select"))}
              disabled={!editable}
              aria-label={c("f.supplier")}
            />
          </Field>
          <Field label={c("f.return_type")} required>
            <PSelect
              value={head.return_type}
              onValueChange={(v) => setH({ return_type: v ?? "" })}
              options={pick(
                options.return_types.map((t) => ({
                  value: t,
                  label: ys(`return_type.${t}`),
                })),
                c("ph.choose"),
              )}
              disabled={!editable}
              aria-label={c("f.return_type")}
            />
          </Field>
          <Field label={c("f.lc")} hint={c("hint.lc")}>
            <Input
              value={head.lc_no}
              onChange={(e) => setH({ lc_no: e.target.value })}
              maxLength={40}
              disabled={!editable}
              aria-label={c("f.lc")}
            />
          </Field>
          <Field label={c("f.lot_ref")} hint={c("hint.lot_ref")}>
            <Input
              value={head.lot_ref}
              onChange={(e) => setH({ lot_ref: e.target.value.toUpperCase() })}
              maxLength={40}
              disabled={!editable}
              aria-label={c("f.lot_ref")}
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
                <Plus /> {c("btn.add_yarn")}
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
                options={pick(options.uoms, c("ph.choose"))}
                aria-label={c("f.uom")}
              />
            </Field>
            <Field label={c("f.remarks")} className="md:col-span-3">
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
            <NoLines title={c("empty.lines")} hint={c("empty.lines_hint")} />
          ) : (
            <table className="w-full min-w-[900px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    ["yarn_item"],
                    ["colour_group"],
                    ["brand"],
                    ["lot_no"],
                    ["line_qty", true],
                    ["uom"],
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
                      {l.composition_id ? yarnName(options, l) : "—"}
                    </td>
                    <td className="px-2 py-2">{l.colour_group || "—"}</td>
                    <td className="px-2 py-2">{l.brand || "—"}</td>
                    <td className="px-2 py-2 font-semibold">{l.lot_no}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(num(l.qty))}
                    </td>
                    <td className="px-2 py-2">{l.uom}</td>
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
                  <td className="px-2 py-2" colSpan={4}>
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

export { RrList, RrEntry };
