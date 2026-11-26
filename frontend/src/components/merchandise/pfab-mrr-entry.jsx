"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  CheckCheck,
  PackageOpen,
  Plus,
  RotateCcw,
  Save,
  Search,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Button, buttonVariants } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

// An error's field messages, or its message when it names no field.
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const str = (v) => (v === null || v === undefined ? "" : String(v));
const todayIso = () =>
  new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(
    new Date(),
  );
const dateFmt = new Intl.DateTimeFormat("en-GB", {
  day: "numeric",
  month: "short",
  year: "numeric",
  timeZone: "UTC",
});
const day = (v) => (v ? dateFmt.format(new Date(`${v}T00:00:00Z`)) : "—");
const num = (v) => (v === "" || v === null || v === undefined ? 0 : Number(v));
const money = new Intl.NumberFormat("en-US", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});
const qtyFmt = new Intl.NumberFormat("en-US", { maximumFractionDigits: 4 });
const PAGE = 10;
// Brickland's defaults: an import receipt, by LC, in taka at 1, no deductions.
const blankHead = (date, company) => ({
  unit_company_id: company,
  supplier_id: "",
  store_id: "",
  po_id: "",
  mrr_date: date,
  payment_method: "LC",
  source_type: "IMPORT",
  challan_no: "",
  challan_date: "",
  currency: "BDT",
  conv_rate: "1",
  lc_no: "",
  lc_date: "",
  bill_entry_no: "",
  bill_entry_date: "",
  remarks: "",
  discount_pct: "0",
  discount_amount: "0",
  vat_pct: "0",
  tds_pct: "0",
  vds_pct: "0",
});
const headOf = (data, date, company) =>
  Object.fromEntries(
    Object.entries(blankHead(date, company)).map(([k, v]) => [
      k,
      data?.[k] === null || data?.[k] === undefined ? v : str(data[k]),
    ]),
  );
let seq = 0;
const asLine = (l = {}) => ({
  key: `m${++seq}`,
  ...l,
  batch_no: str(l.batch_no),
  rolls: str(l.rolls),
  receive_qty: str(l.receive_qty),
  reject_qty: str(l.reject_qty ?? 0),
  rate: str(l.rate),
  reject_reason: str(l.reject_reason),
});
const asCost = (c = {}) => ({
  key: `c${++seq}`,
  cost_head_id: str(c.cost_head_id),
  amount: str(c.amount),
  remarks: str(c.remarks),
});
const BILL = [
  "goods_value",
  "other_cost",
  "landed_cost",
  "vat_base",
  "vat",
  "tds",
  "vds",
  "net_payable",
];

/**
 * Purchased Fabric Receipt Report → Fabric MRR (AnnotechERP 06/06), as
 * Brickland lays it out: the Receive List on the left (fabric orders still to
 * receive, by supplier); picking one fills the Master List and the details
 * with what it was placed for — each batch received, part rejected, the rest
 * accepted, its balance against the order shown; Discount, VAT & Deductions,
 * the Bill Summary (recomputed on save) and Other Cost. Save keeps a draft;
 * Finalize MRR locks it and puts the accepted fabric on stock. Nothing reloads.
 *
 * @param {{ options: object, initial: ?object, prefill: ?object, canManage: boolean, actions: object, recordsHref: string }} props
 */
function PfabMrrEntry({
  options,
  initial,
  prefill,
  canManage,
  actions,
  recordsHref,
}) {
  const t = useT("merchandise");
  const tp = useT("materialprocurement");
  const r = (k, p) => t(`master.mrr.${k}`, p);
  const f = (k, p) => t(`master.fmrr.${k}`, p);
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const company = options.companies[0]?.value ?? "";
  const [mrr, setMrr] = useState(initial);
  const [head, setHead] = useState(() =>
    initial
      ? headOf(initial, today, company)
      : prefill
        ? headOf(prefill, today, company)
        : blankHead(today, company),
  );
  const [lines, setLines] = useState(() =>
    (initial?.lines ?? prefill?.lines ?? []).map(asLine),
  );
  const [costs, setCosts] = useState(() => (initial?.costs ?? []).map(asCost));
  const [errors, setErrors] = useState(null);
  const [customer, setCustomer] = useState("");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(0);
  const [confirm, setConfirm] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!mrr || mrr.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (key, patch) =>
    setLines((all) => all.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const setCost = (key, patch) =>
    setCosts((all) => all.map((c) => (c.key === key ? { ...c, ...patch } : c)));
  const pick = (list) => [{ value: "", label: r("select") }, ...list];
  const plain = (values) => values.map((v) => ({ value: v, label: v }));
  const coded = (prefix, values) =>
    values.map((v) => ({ value: v, label: t(`${prefix}.${v}`) }));
  const nameOf = (list, id) => list.find((x) => x.value === id)?.label ?? "—";
  const shown = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return options.receivable.filter(
      (p) =>
        (!customer || p.supplier_id === customer) &&
        (!needle || p.po_no.toLowerCase().includes(needle)),
    );
  }, [options.receivable, customer, query]);
  const pages = Math.max(1, Math.ceil(shown.length / PAGE));
  const openLines = options.open_lines.filter(
    (o) => !lines.some((l) => l.po_line_id === o.po_line_id),
  );
  const accept = (l) => Math.max(0, num(l.receive_qty) - num(l.reject_qty));

  function fillFrom(no) {
    startTransition(async () => {
      const result = await actions.fromOrder(no);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      const d = result.data;
      setH({
        po_id: d.po_id,
        supplier_id: str(d.supplier_id),
        store_id: str(d.store_id),
        payment_method: str(d.payment_method),
        source_type: str(d.source_type),
        currency: str(d.currency),
        conv_rate: str(d.conv_rate),
      });
      setLines(d.lines.map(asLine));
      setErrors(null);
    });
  }

  function take(data) {
    setMrr(data);
    setHead(headOf(data, today, company));
    setLines(data.lines.map(asLine));
    setCosts(data.costs.map(asCost));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      `?tab=entry&ref=${encodeURIComponent(data.mrr_no)}`,
    );
  }

  function clear() {
    setMrr(null);
    setHead(blankHead(today, company));
    setLines([]);
    setCosts([]);
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }

  function load(no) {
    if (!no) return clear();
    startTransition(async () => {
      const result = await actions.load(no);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
    });
  }

  function save() {
    startTransition(async () => {
      const blank = (v) => (v === "" ? null : v);
      const number = (v) => (v === "" ? null : Number(v));
      const payload = {
        ...Object.fromEntries(
          Object.entries(head).map(([k, v]) => [k, blank(v)]),
        ),
        ...Object.fromEntries(
          [
            "conv_rate",
            "discount_pct",
            "discount_amount",
            "vat_pct",
            "tds_pct",
            "vds_pct",
          ].map((k) => [k, number(head[k])]),
        ),
        // A line raised from a Material Procurement follow-up has no order line: it carries the requisition line, its description and unit.
        lines: lines.map((l) => ({
          po_line_id: l.po_line_id ?? null,
          mp_requisition_line_id: l.mp_requisition_line_id ?? null,
          description: l.po_line_id ? undefined : blank(l.description),
          uom: l.po_line_id ? undefined : blank(l.uom),
          batch_no: blank(l.batch_no),
          rolls: number(l.rolls),
          receive_qty: number(l.receive_qty),
          reject_qty: number(l.reject_qty),
          rate: number(l.rate),
          reject_reason: blank(l.reject_reason),
        })),
        costs: costs.map((c) => ({
          cost_head_id: blank(c.cost_head_id),
          amount: number(c.amount),
          remarks: blank(c.remarks),
        })),
      };
      const result = await actions.save(mrr?.mrr_no ?? null, payload);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      take(result.data);
      toastManager.add({
        title: r("saved", { no: result.data.mrr_no }),
        type: "success",
      });
    });
  }

  function run(kind) {
    startTransition(async () => {
      const result =
        kind === "delete"
          ? await actions.remove(mrr.mrr_no)
          : await actions.finalize(mrr.mrr_no);
      setConfirm(null);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      toastManager.add({
        title: r(kind === "delete" ? "deleted" : "finalized_toast", {
          no: mrr.mrr_no,
        }),
        type: "success",
      });
      if (kind === "delete") clear();
      else take(result.data);
    });
  }

  const card = "rounded-xl border border-border bg-surface";
  const group = "rounded-lg border border-border p-4";
  const field = "flex flex-col gap-1.5";
  const lab = "text-[13px] font-semibold text-foreground";
  const hint = "text-xs text-foreground-muted";
  const star = <span className="text-danger">*</span>;
  const sel = (key, label, list, required, extra = {}) => (
    <div className={field}>
      <span className={lab}>
        {label}
        {required ? star : null}
      </span>
      <Select
        value={head[key]}
        onValueChange={(v) =>
          setH({ [key]: v ?? "", ...(extra.onPick ? extra.onPick(v) : {}) })
        }
        options={pick(list)}
        placeholder={r("select")}
        disabled={!editable || extra.disabled}
        aria-label={label}
      />
    </div>
  );
  const txt = (key, label, extra = {}) => (
    <label className={cn(field, extra.span)}>
      <span className={lab}>
        {label}
        {extra.required ? star : null}
      </span>
      {extra.area ? (
        <Textarea
          rows={3}
          value={head[key]}
          onChange={(e) => setH({ [key]: e.target.value })}
          maxLength={2000}
          disabled={!editable}
          aria-label={label}
        />
      ) : (
        <Input
          type={extra.type ?? "text"}
          step={extra.type === "number" ? "any" : undefined}
          value={head[key]}
          onChange={(e) => setH({ [key]: e.target.value })}
          disabled={!editable || extra.disabled}
          aria-label={label}
        />
      )}
      {extra.hint ? <span className={hint}>{extra.hint}</span> : null}
    </label>
  );
  return (
    <div className="grid gap-4 xl:grid-cols-[22rem_1fr]">
      <section className={cn(card, "self-start p-4")}>
        <h2 className="text-base font-semibold text-foreground">
          {f("receive_list")}
        </h2>
        <div className="mt-3 flex flex-col gap-1.5">
          <span className={lab}>{t("master.rtn.f.customer")}</span>
          <Select
            value={customer}
            onValueChange={(v) => (setCustomer(v ?? ""), setPage(0))}
            options={[
              { value: "", label: t("master.rtn.pick_customer") },
              ...options.suppliers,
            ]}
            aria-label={t("master.rtn.f.customer")}
          />
        </div>
        <div className="relative mt-3">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input
            value={query}
            onChange={(e) => (setQuery(e.target.value), setPage(0))}
            placeholder={t("master.rtn.search_mrr")}
            className="pl-8"
            aria-label={t("master.rtn.search_mrr")}
          />
        </div>
        <div className="mt-3 overflow-hidden rounded-lg border border-border">
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                <th className="px-3 py-2 text-left text-[13px] font-semibold">
                  {f("col.po_number")}
                </th>
                <th className="px-3 py-2 text-left text-[13px] font-semibold">
                  {f("col.po_date")}
                </th>
              </tr>
            </thead>
            <tbody>
              {shown.slice(page * PAGE, page * PAGE + PAGE).map((p) => (
                <tr
                  key={p.po_no}
                  className={cn(
                    "border-t border-border",
                    head.po_id && mrr?.po_no === p.po_no && "bg-brand-subtle",
                  )}
                >
                  <td className="px-3 py-2">
                    <button
                      type="button"
                      className="text-left font-medium text-brand hover:underline disabled:text-foreground-muted"
                      onClick={() => fillFrom(p.po_no)}
                      disabled={!editable}
                    >
                      {p.po_no}
                    </button>
                  </td>
                  <td className="px-3 py-2 whitespace-nowrap">
                    {day(p.po_date)}
                  </td>
                </tr>
              ))}
              {shown.length === 0 ? (
                <tr>
                  <td
                    colSpan={2}
                    className="px-3 py-6 text-center text-foreground-muted"
                  >
                    {t("master.rtn.nothing_left")}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
        <div className="mt-2 flex items-center justify-end gap-1 text-xs text-foreground-muted">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage((p) => Math.max(0, p - 1))}
            disabled={page === 0}
            aria-label="Previous page"
          >
            ‹
          </Button>
          {page + 1} / {pages}
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage((p) => Math.min(pages - 1, p + 1))}
            disabled={page >= pages - 1}
            aria-label="Next page"
          >
            ›
          </Button>
        </div>
      </section>

      <div className="flex flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
                <PackageOpen className="size-4" /> {f("entry_tab")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {f("entry_hint")}
              </p>
            </div>
            <span className="rounded-md bg-brand-subtle px-2 py-0.5 text-xs font-medium text-brand">
              {mrr ? r(mrr.editable ? "draft" : "finalized") : r("new")}
            </span>
          </div>
          <div className={cn(field, "mt-4")}>
            <span className={lab}>{r("existing")}</span>
            <Select
              value={mrr?.mrr_no ?? ""}
              onValueChange={(v) => load(v ?? "")}
              options={[
                { value: "", label: r("new_receipt") },
                ...options.receipts,
              ]}
              aria-label={r("existing")}
            />
          </div>
          <fieldset className={cn(group, "mt-4")}>
            <legend className="px-1 text-[13px] font-semibold text-foreground">
              {f("master_list")}
            </legend>
            <div className="grid gap-4 md:grid-cols-3">
              {sel(
                "unit_company_id",
                r("f.unit_company_id"),
                options.companies,
                true,
              )}
              {sel("supplier_id", r("f.supplier_id"), options.suppliers, true)}
              {sel("store_id", r("f.store_id"), options.stores, true)}
              {sel(
                "payment_method",
                r("f.payment_method"),
                coded("master.apo.method", options.payment_methods),
                true,
              )}
              <div className={field}>
                <span className={lab}>{r("f.mrr_no")}</span>
                <Input
                  value={mrr?.mrr_no ?? ""}
                  placeholder={r("auto_gen")}
                  readOnly
                  className="bg-surface-muted"
                  aria-label={r("f.mrr_no")}
                />
              </div>
              {sel(
                "source_type",
                r("f.source_type"),
                coded("master.apo.source", options.sources),
                true,
              )}
              {txt("mrr_date", r("f.mrr_date"), {
                type: "date",
                required: true,
              })}
              {txt("challan_no", r("f.challan_no"), { required: true })}
              {txt("challan_date", r("f.challan_date"), {
                type: "date",
                required: true,
              })}
              {sel(
                "currency",
                r("f.currency"),
                plain(options.currencies),
                true,
                { onPick: (v) => (v === "BDT" ? { conv_rate: "1" } : {}) },
              )}
              {txt("conv_rate", r("f.conv_rate"), {
                type: "number",
                disabled: head.currency === "BDT",
                hint: r("conv_hint"),
              })}
              {txt("lc_no", f("f.lc_no"))}
              {txt("lc_date", f("f.lc_date"), { type: "date" })}
              {txt("bill_entry_no", f("f.bill_entry_no"))}
              {txt("bill_entry_date", f("f.bill_entry_date"), { type: "date" })}
              {txt("remarks", r("f.remarks"), {
                area: true,
                span: "md:col-span-3",
              })}
            </div>
          </fieldset>
          <div className="mt-4 grid gap-4 lg:grid-cols-2">
            <fieldset className={group}>
              <legend className="px-1 text-[13px] font-semibold text-foreground">
                {r("deductions")}
              </legend>
              <div className="grid gap-4 sm:grid-cols-2">
                {txt("discount_pct", r("f.discount_pct"), {
                  type: "number",
                  hint: r("discount_hint"),
                })}
                {txt("discount_amount", r("f.discount_amount"), {
                  type: "number",
                  disabled: num(head.discount_pct) > 0,
                })}
                {txt("vat_pct", r("f.vat_pct"), { type: "number" })}
                {txt("tds_pct", r("f.tds_pct"), {
                  type: "number",
                  hint: r("tds_hint"),
                })}
                {txt("vds_pct", r("f.vds_pct"), {
                  type: "number",
                  hint: r("vds_hint"),
                })}
              </div>
            </fieldset>
            <fieldset className={group}>
              <legend className="px-1 text-[13px] font-semibold text-foreground">
                {r("bill")}
              </legend>
              <div className="grid gap-4 sm:grid-cols-2">
                {BILL.map((k) => (
                  <div key={k} className={field}>
                    <span className={lab}>{r(`f.${k}`)}</span>
                    <Input
                      value={mrr ? money.format(mrr[k]) : ""}
                      placeholder={r("on_save")}
                      readOnly
                      className={cn(
                        "bg-surface-muted text-right tabular-nums",
                        k === "net_payable" && "font-semibold",
                      )}
                      aria-label={r(`f.${k}`)}
                    />
                  </div>
                ))}
                <div className={cn(field, "sm:col-span-2")}>
                  <span className={lab}>{r("f.voucher_no")}</span>
                  <Input
                    value={mrr?.voucher_no ?? ""}
                    placeholder={r("on_finalise")}
                    readOnly
                    className="bg-surface-muted"
                    aria-label={r("f.voucher_no")}
                  />
                </div>
              </div>
            </fieldset>
          </div>
        </section>

        <section className={cn(card, "overflow-hidden")}>
          <div className="flex items-start justify-between gap-3 px-5 py-4">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {t("master.apo.details")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {f("details_hint")}
              </p>
            </div>
            {editable ? (
              <Button
                variant="outline"
                size="sm"
                onClick={() =>
                  setLines((all) => [...all, asLine({ po_line_id: null })])
                }
              >
                <Plus /> {r("add_line")}
              </Button>
            ) : null}
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "fabric_type",
                    "description",
                    "composition",
                    "colour",
                    "gsm",
                    "dia",
                    "batch",
                    "rolls",
                    "uom",
                    "po_qty",
                    "prev",
                    "receive",
                    "reject",
                    "accept",
                    "balance",
                    "rate",
                    "amount",
                    "reason",
                    "remove",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        "px-2 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap",
                        [
                          "po_qty",
                          "prev",
                          "receive",
                          "reject",
                          "accept",
                          "balance",
                          "rate",
                          "amount",
                        ].includes(k) && "text-right",
                      )}
                    >
                      {k === "remove" ? (
                        <span className="sr-only">{f("col.remove")}</span>
                      ) : (
                        f(`col.${k}`)
                      )}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={l.key} className="border-t border-border">
                    <td className="px-2 py-1.5 whitespace-nowrap">
                      {l.po_line_id ? (
                        <>
                          {nameOf(options.fabric_types, l.fabric_type_id)}
                          <span className="block text-[11px] text-foreground-muted">
                            {l.po_no}
                          </span>
                        </>
                      ) : l.mp_requisition_line_id ? (
                        <span className="text-[11px] text-foreground-muted">
                          {tp("fu.from_procurement")}
                        </span>
                      ) : (
                        <div className="w-60">
                          <Select
                            value=""
                            onValueChange={(v) => {
                              const o = options.open_lines.find(
                                (x) => x.po_line_id === v,
                              );
                              if (o)
                                setLine(l.key, {
                                  ...o,
                                  receive_qty: str(o.receive_qty),
                                  reject_qty: "0",
                                  rate: str(o.rate),
                                });
                            }}
                            options={openLines.map((o) => ({
                              value: o.po_line_id,
                              label: `${o.po_no} · ${o.description ?? nameOf(options.fabric_types, o.fabric_type_id)}`,
                            }))}
                            placeholder={f("pick_line")}
                            disabled={!editable}
                            aria-label={f("row_line", { n: i + 1 })}
                          />
                        </div>
                      )}
                    </td>
                    <td className="px-2 py-1.5">{l.description ?? "—"}</td>
                    <td className="px-2 py-1.5 whitespace-nowrap">
                      {l.composition_id
                        ? nameOf(options.compositions, l.composition_id)
                        : "—"}
                    </td>
                    <td className="px-2 py-1.5">
                      {l.colour_id ? nameOf(options.colours, l.colour_id) : "—"}
                    </td>
                    <td className="px-2 py-1.5">{l.gsm ?? "—"}</td>
                    <td className="px-2 py-1.5">{l.dia ?? "—"}</td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={l.batch_no}
                        onChange={(e) =>
                          setLine(l.key, { batch_no: e.target.value })
                        }
                        maxLength={60}
                        className="h-8 w-28"
                        disabled={!editable}
                        aria-label={f("row_batch", { n: i + 1 })}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        type="number"
                        min="0"
                        value={l.rolls}
                        onChange={(e) =>
                          setLine(l.key, { rolls: e.target.value })
                        }
                        className="h-8 w-16 text-right"
                        disabled={!editable}
                        aria-label={f("row_rolls", { n: i + 1 })}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      {l.uom ? t(`master.pfi.uom.${l.uom}`) : "—"}
                    </td>
                    <td className="px-2 py-1.5 text-right tabular-nums">
                      {l.po_line_id ? qtyFmt.format(l.po_qty ?? 0) : "—"}
                    </td>
                    <td className="px-2 py-1.5 text-right tabular-nums">
                      {l.po_line_id ? qtyFmt.format(l.prev_received ?? 0) : "—"}
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        type="number"
                        min="0"
                        step="any"
                        value={l.receive_qty}
                        onChange={(e) =>
                          setLine(l.key, { receive_qty: e.target.value })
                        }
                        className="ml-auto h-8 w-24 text-right"
                        disabled={!editable}
                        aria-label={f("row_receive", { n: i + 1 })}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        type="number"
                        min="0"
                        step="any"
                        value={l.reject_qty}
                        onChange={(e) =>
                          setLine(l.key, { reject_qty: e.target.value })
                        }
                        className="ml-auto h-8 w-20 text-right"
                        disabled={!editable}
                        aria-label={f("row_reject", { n: i + 1 })}
                      />
                    </td>
                    <td className="px-2 py-1.5 text-right tabular-nums">
                      {qtyFmt.format(accept(l))}
                    </td>
                    <td className="px-2 py-1.5 text-right tabular-nums">
                      {l.po_line_id
                        ? qtyFmt.format(
                            Math.max(
                              0,
                              num(l.po_qty) - num(l.prev_received) - accept(l),
                            ),
                          )
                        : "—"}
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        type="number"
                        min="0"
                        step="any"
                        value={l.rate}
                        onChange={(e) =>
                          setLine(l.key, { rate: e.target.value })
                        }
                        className="ml-auto h-8 w-24 text-right"
                        disabled={!editable}
                        aria-label={f("row_rate", { n: i + 1 })}
                      />
                    </td>
                    <td className="px-2 py-1.5 text-right tabular-nums">
                      {money.format(accept(l) * num(l.rate))}
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={l.reject_reason}
                        onChange={(e) =>
                          setLine(l.key, { reject_reason: e.target.value })
                        }
                        maxLength={200}
                        className="h-8 w-36"
                        disabled={!editable}
                        aria-label={f("row_reason", { n: i + 1 })}
                      />
                    </td>
                    <td className="px-2 py-1.5 text-right">
                      {editable ? (
                        <Button
                          variant="ghost"
                          size="icon"
                          className="size-8"
                          aria-label={r("row_remove")}
                          onClick={() =>
                            setLines((all) =>
                              all.filter((x) => x.key !== l.key),
                            )
                          }
                        >
                          <Trash2 />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
                {lines.length === 0 ? (
                  <tr>
                    <td
                      colSpan={19}
                      className="px-5 py-8 text-center text-[13px] text-foreground-muted"
                    >
                      {r("no_lines")}
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
          {lines.length ? (
            <div className="border-t border-border px-5 py-3 text-sm font-semibold text-foreground">
              {f("total", {
                n: lines.length,
                rcv: qtyFmt.format(
                  lines.reduce((n, l) => n + num(l.receive_qty), 0),
                ),
                acc: qtyFmt.format(lines.reduce((n, l) => n + accept(l), 0)),
              })}{" "}
              ·{" "}
              {money.format(
                lines.reduce((n, l) => n + accept(l) * num(l.rate), 0),
              )}{" "}
              {head.currency}
            </div>
          ) : null}
        </section>

        <section className={cn(card, "overflow-hidden")}>
          <div className="flex items-start justify-between gap-3 px-5 py-4">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {r("costs")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {r("costs_hint")}
              </p>
            </div>
            {editable ? (
              <Button
                variant="outline"
                size="sm"
                onClick={() => setCosts((all) => [...all, asCost()])}
              >
                <Plus /> {r("add_cost")}
              </Button>
            ) : null}
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "cost_head",
                    "landed",
                    "vat",
                    "amount",
                    "remarks",
                    "remove",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        "px-3 py-2.5 text-left text-[13px] font-semibold",
                        k === "amount" && "text-right",
                      )}
                    >
                      {k === "remove" ? (
                        <span className="sr-only">{r("col.remove")}</span>
                      ) : (
                        r(`col.${k}`)
                      )}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {costs.map((c, i) => {
                  const h = options.cost_heads.find(
                    (x) => x.value === c.cost_head_id,
                  );
                  return (
                    <tr key={c.key} className="border-t border-border">
                      <td className="px-3 py-1.5">
                        <div className="w-64">
                          <Select
                            value={c.cost_head_id}
                            onValueChange={(v) =>
                              setCost(c.key, { cost_head_id: v ?? "" })
                            }
                            options={[
                              { value: "", label: "—" },
                              ...options.cost_heads.map(({ value, label }) => ({
                                value,
                                label,
                              })),
                            ]}
                            disabled={!editable}
                            aria-label={r("cost_row_head", { n: i + 1 })}
                          />
                        </div>
                      </td>
                      <td className="px-3 py-1.5">
                        {h ? r(h.landed ? "yes" : "no") : "—"}
                      </td>
                      <td className="px-3 py-1.5">
                        {h ? r(h.vat_applicable ? "yes" : "no") : "—"}
                      </td>
                      <td className="px-3 py-1.5">
                        <Input
                          type="number"
                          min="0"
                          step="any"
                          value={c.amount}
                          onChange={(e) =>
                            setCost(c.key, { amount: e.target.value })
                          }
                          className="ml-auto h-8 w-32 text-right"
                          disabled={!editable}
                          aria-label={r("cost_row_amount", { n: i + 1 })}
                        />
                      </td>
                      <td className="px-3 py-1.5">
                        <Input
                          value={c.remarks}
                          onChange={(e) =>
                            setCost(c.key, { remarks: e.target.value })
                          }
                          maxLength={200}
                          className="h-8 w-56"
                          disabled={!editable}
                          aria-label={r("cost_row_remarks", { n: i + 1 })}
                        />
                      </td>
                      <td className="px-3 py-1.5 text-right">
                        {editable ? (
                          <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label={r("row_remove")}
                            onClick={() =>
                              setCosts((all) =>
                                all.filter((x) => x.key !== c.key),
                              )
                            }
                          >
                            <Trash2 />
                          </Button>
                        ) : null}
                      </td>
                    </tr>
                  );
                })}
                {costs.length === 0 ? (
                  <tr>
                    <td
                      colSpan={6}
                      className="px-5 py-6 text-center text-[13px] text-foreground-muted"
                    >
                      {r("no_costs")}
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </section>

        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <div>
            <Link
              href={recordsHref}
              className={buttonVariants({ variant: "outline", size: "sm" })}
            >
              <ArrowLeft /> {t("master.rtn.back")}
            </Link>
          </div>
          <p className="text-[13px] text-foreground-muted">
            {f("action_status")}
          </p>
          <div className="flex flex-wrap gap-2">
            {mrr && editable ? (
              <Button
                variant="outline"
                className="text-danger"
                onClick={() => setConfirm("delete")}
                disabled={pending}
              >
                <Trash2 /> {r("delete")}
              </Button>
            ) : null}
            <Button variant="outline" onClick={clear} disabled={pending}>
              <RotateCcw /> {r("clear")}
            </Button>
            {editable ? (
              <Button onClick={save} loading={pending}>
                <Save /> {mrr ? r("save_changes") : r("save")}
              </Button>
            ) : null}
            {mrr && editable ? (
              <Button
                variant="success"
                onClick={() => setConfirm("finalize")}
                disabled={pending}
              >
                <CheckCheck /> {r("finalize")}
              </Button>
            ) : null}
          </div>
        </section>
      </div>

      <ConfirmDialog
        open={confirm !== null}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={confirm ? r(`confirm_${confirm}_title`) : ""}
        description={confirm ? r(`confirm_${confirm}_hint`) : ""}
        confirmLabel={confirm ? r(confirm) : ""}
        cancelLabel={r("cancel")}
        destructive={confirm === "delete"}
        onConfirm={() => confirm && run(confirm)}
        loading={pending}
      />
    </div>
  );
}

export { PfabMrrEntry };
