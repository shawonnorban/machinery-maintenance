"use client";

import { useMemo, useState, useTransition } from "react";
import { printDocument, cardGrid } from "@/lib/print-document";
import Link from "next/link";
import {
  ChevronDown,
  ChevronRight,
  ListPlus,
  Plus,
  Printer,
  RotateCcw,
  Save,
  Undo2,
  X,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button, buttonVariants } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { UomSelect } from "@/components/erp/uom-select";
import { Select } from "@/components/ui/select";

const ACCESSORIES = [
  "SEWING_ACC",
  "FINISHING_ACC",
  "GENERAL_ACC",
  "SPECIAL_ACC",
];
const TYPED = [
  "print",
  "embroidery",
  "wash",
  "cm",
  "commercial",
  "service",
  "expense",
  "fob",
];
const num = (v) =>
  v === "" || v == null || Number.isNaN(Number(v)) ? 0 : Number(v);
const money = (v) => num(v).toFixed(2);
const blankOr = (v) => (v == null ? "" : String(v));
const given = (v) => v !== "" && v != null && !Number.isNaN(Number(v));
/** A piece's quantity: from Cons/Dzn and Wastage % when the consumption is given, else as typed. */
const qtyOf = (l) =>
  given(l.cons_dzn)
    ? Math.round((num(l.cons_dzn) / 12) * (1 + num(l.wastage_pct) / 100) * 10000) / 10000
    : num(l.qty);
let seq = 0;

const lineOf = (l) => ({
  key: `l${++seq}`,
  section: l.section,
  item_id: l.item_id ?? "",
  item_code: l.item_code ?? "",
  supplier_id: l.supplier_id ?? "",
  // What each supplier asks for the item, read when the item is picked (or its supplier changed).
  offers: l.offers ?? null,
  description: l.description,
  uom: l.uom ?? "",
  cons_dzn: blankOr(l.cons_dzn),
  wastage_pct: blankOr(l.wastage_pct),
  qty: String(l.qty ?? 0),
  rate: String(l.rate ?? 0),
  source: l.source ?? null,
});

function formOf(data) {
  const b = data.budget;
  return {
    buyer_office_id: b.buyer_office_id ?? "",
    currency: b.currency ?? "USD",
    exchange_rate: String(b.exchange_rate ?? 1),
    remarks: b.remarks ?? "",
    lines: b.lines.map(lineOf),
    process: Object.fromEntries(
      data.process_heads.map((h) => [
        h,
        b.process?.[h] == null ? "" : String(b.process[h]),
      ]),
    ),
    overall: Object.fromEntries(
      TYPED.map((h) => [h, b.overall?.[h] == null ? "" : String(b.overall[h])]),
    ),
  };
}

/**
 * Costing & Budget → Order Budget Sheet (AnnotechERP 06/09), Brickland's
 * sheet: Key Information (buyer account, style, its order — currency, rate,
 * quantity, value and inquiry come from the order), Budget Vs Inquiry
 * Comparisons with Print Inquiry, and three tabs — Bill of Material (six
 * sections of lines), Fabric Process Cost and Overall Cost — every figure per
 * piece. FOB should equal the sum of the heads; the sheet says when it does
 * not. Picking an order reads its budget without leaving the page.
 *
 * The Bill of Material goes beyond Brickland (the user's call, 2026-11-17):
 * a line may carry Cons/Dzn and Wastage %, its Qty per piece following from
 * them; Order Req. shows what the whole order takes; a new line's unit
 * follows its group; and "Load from BOM / Booking" brings in the order's
 * fabric booking and its style's BOM, replacing only what was loaded before.
 * An accessory line picks its Item from the trims master (code and unit
 * follow, Cons/Dzn and Wastage from the style's BOM) and its Supplier, whose
 * price (approved quotation, last PO, open quotation) is the Rate.
 *
 * @param {{ options: object, initial: ?object, save: Function, load: Function, loadSources: Function, loadItem: Function, canManage: boolean, listHref: string }} props
 */
function BudgetSheet({
  options,
  initial,
  save,
  load,
  loadSources,
  loadItem,
  canManage: mayManage,
  listHref,
}) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.bgt.${k}`, p);
  const toastManager = useToastManager();
  const [data, setData] = useState(initial);
  // An approved sheet is locked for costing until the Budget Sheet Workflow unlocks it.
  const locked = Boolean(data?.budget?.approved_at);
  const canManage = mayManage && !locked;
  const [form, setForm] = useState(() => (initial ? formOf(initial) : null));
  const [styleId, setStyleId] = useState(initial?.order.style_id ?? "");
  const [tab, setTab] = useState("bom");
  const [open, setOpen] = useState({});
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const orders = options.orders.filter(
    (o) => !styleId || o.style_id === styleId,
  );

  function pickOrder(id) {
    if (!id) {
      setData(null);
      setForm(null);
      return;
    }
    startTransition(async () => {
      const result = await load(id);
      if (result?.status === "success") {
        setData(result.data);
        setForm(formOf(result.data));
        setErrors(null);
        // The order is named in the address by its number; no page is loaded.
        window.history.replaceState(
          null,
          "",
          `?order=${encodeURIComponent(result.data.order.order_no)}`,
        );
      } else {
        toastManager.add({ title: result?.message, type: "danger" });
      }
    });
  }

  const sums = useMemo(() => {
    if (!form) return null;
    const section = (s) =>
      form.lines
        .filter((l) => l.section === s)
        .reduce((a, l) => a + qtyOf(l) * num(l.rate), 0);
    const yarn = section("YARN");
    const purchased = section("PURCHASED_FABRIC");
    const trims = ACCESSORIES.reduce((a, s) => a + section(s), 0);
    const process = Object.values(form.process).reduce((a, v) => a + num(v), 0);
    const o = form.overall;
    const heads = {
      yarn,
      fab: purchased + process,
      trims,
      print: num(o.print),
      embr: num(o.embroidery),
      wash: num(o.wash),
      cm: num(o.cm),
      commercial: num(o.commercial),
      service: num(o.service),
      fob: num(o.fob),
    };
    const total =
      heads.yarn +
      heads.fab +
      heads.trims +
      heads.print +
      heads.embr +
      heads.wash +
      heads.cm +
      heads.commercial +
      heads.service +
      num(o.expense);
    return { section, yarn, purchased, trims, process, heads, total };
  }, [form]);

  const set = (patch) => setForm((f) => ({ ...f, ...patch }));
  const setLine = (key, patch) =>
    setForm((f) => ({
      ...f,
      lines: f.lines.map((l) => (l.key === key ? { ...l, ...patch } : l)),
    }));
  const addLine = (section) => {
    setForm((f) => ({
      ...f,
      lines: [
        ...f.lines,
        // Yarn by the kilo, purchased fabric by the company's length, the rest by the piece.
        lineOf({
          section,
          description: "",
          uom: data.section_uoms?.[section] ?? "PCS",
          qty: 0,
          rate: 0,
        }),
      ],
    }));
    setOpen((o) => ({ ...o, [section]: true }));
  };
  const [loadNotes, setLoadNotes] = useState(null);

  /** What each supplier asks for an item, in the sheet's currency and rate as keyed now. */
  const itemInfo = (itemId) =>
    loadItem(data.order.id, {
      item_id: itemId,
      currency: form.currency,
      rate: String(num(form.exchange_rate) || 1),
    });

  // Picking the item fills its code, unit, BOM consumption, and the supplier with its price.
  function pickItem(l, itemId) {
    if (!itemId) {
      setLine(l.key, { item_id: "", item_code: "", offers: null });
      return;
    }
    const known = data.catalog?.items.find((i) => i.value === itemId);
    setLine(l.key, {
      item_id: itemId,
      item_code: known?.code ?? "",
      description: known?.name ?? l.description,
      uom: known?.uom || l.uom,
    });
    startTransition(async () => {
      const result = await itemInfo(itemId);
      if (result?.status !== "success") {
        toastManager.add({ title: result?.message, type: "danger" });
        return;
      }
      const { bom, offers, pick } = result.data;
      // Another item in its place, not on the BOM: the old item's consumption no longer holds.
      const replaced = l.item_id && l.item_id !== itemId;
      setLine(l.key, {
        offers,
        ...(bom
          ? {
              cons_dzn: String(bom.cons_dzn),
              wastage_pct: String(bom.wastage_pct),
            }
          : replaced
            ? { cons_dzn: "", wastage_pct: "", qty: "0" }
            : {}),
        supplier_id: pick.supplier_id ?? "",
        ...(pick.rate != null ? { rate: String(pick.rate) } : {}),
      });
    });
  }

  // Changing the supplier takes that supplier's price as the rate (kept as it is when the supplier has none).
  function pickSupplier(l, supplierId) {
    const apply = (offers) => {
      const offer = offers?.find((o) => o.supplier_id === supplierId);
      setLine(l.key, {
        supplier_id: supplierId ?? "",
        offers,
        ...(offer?.price != null ? { rate: String(offer.price) } : {}),
      });
    };
    if (l.offers || !l.item_id || !supplierId) {
      apply(l.offers);
      return;
    }
    startTransition(async () => {
      const result = await itemInfo(l.item_id);
      apply(result?.status === "success" ? result.data.offers : null);
    });
  }

  /** The suppliers, those with a price for the line's item first, each saying its price. */
  function supplierOptions(l) {
    const all = data.catalog?.suppliers ?? [];
    const offers = l.offers ?? [];
    const priced = offers.map((o) => ({
      value: o.supplier_id,
      label: `${o.supplier ?? ""} — ${
        o.price != null
          ? `${o.price} ${form.currency}`
          : `${o.quoted} ${o.currency}`
      } (${m(`offer.${o.source}`)} ${o.ref ?? ""})`,
    }));
    const rest = all.filter(
      (s) => !offers.some((o) => o.supplier_id === s.value),
    );
    return [{ value: "", label: m("offer.none") }, ...priced, ...rest];
  }

  // The order's fabric booking and its style's BOM onto the sheet: lines loaded before are replaced, typed ones kept.
  function loadFromSources() {
    if (form.lines.some((l) => l.source) && !window.confirm(m("load.replace")))
      return;
    startTransition(async () => {
      const result = await loadSources(data.order.id);
      if (result?.status !== "success") {
        toastManager.add({ title: result?.message, type: "danger" });
        return;
      }
      const got = result.data;
      if (!got.lines.length) {
        setLoadNotes(null);
        toastManager.add({ title: m("load.none"), type: "warning" });
        return;
      }
      setForm((f) => ({
        ...f,
        lines: [...f.lines.filter((l) => !l.source), ...got.lines.map(lineOf)],
      }));
      setOpen((o) => ({
        ...o,
        ...Object.fromEntries(got.lines.map((l) => [l.section, true])),
      }));
      const from = [
        ...got.bookings.map((no) => m("load.booking", { no })),
        ...(got.bom
          ? [m("load.bom", { no: got.bom.bom_no, v: got.bom.version })]
          : []),
      ].join(", ");
      setLoadNotes(got.notes.length ? got.notes : null);
      toastManager.add({
        title: m("load.loaded", { n: got.lines.length, from }),
        type: "success",
      });
    });
  }

  function submit() {
    startTransition(async () => {
      const result = await save(data.order.id, {
        buyer_office_id: form.buyer_office_id || null,
        currency: form.currency,
        exchange_rate: num(form.exchange_rate) || 1,
        remarks: form.remarks || null,
        lines: form.lines.map((l) => ({
          section: l.section,
          description: l.description,
          item_id: l.item_id || null,
          supplier_id: l.supplier_id || null,
          uom: l.uom || null,
          cons_dzn: given(l.cons_dzn) ? num(l.cons_dzn) : null,
          wastage_pct: given(l.wastage_pct) ? num(l.wastage_pct) : null,
          qty: qtyOf(l),
          rate: num(l.rate),
          source: l.source || null,
        })),
        process: Object.fromEntries(
          Object.entries(form.process).map(([k, v]) => [k, num(v)]),
        ),
        overall: Object.fromEntries(
          Object.entries(form.overall).map(([k, v]) => [k, num(v)]),
        ),
      });
      if (result?.status === "success") {
        setData(result.data);
        setForm(formOf(result.data));
        setErrors(null);
        toastManager.add({
          title: m("saved", { no: result.data.budget.budget_no }),
          type: "success",
        });
      } else {
        setErrors(
          result?.errors
            ? [...new Set(Object.values(result.errors).flat())]
            : [result?.message],
        );
      }
    });
  }

  function reset() {
    setForm((f) => ({
      ...f,
      lines: [],
      process: Object.fromEntries(Object.keys(f.process).map((k) => [k, ""])),
      overall: Object.fromEntries(
        TYPED.map((k) => [k, k === "fob" ? String(data.order.unit_price) : ""]),
      ),
      remarks: "",
    }));
  }

  const HEADS = [
    "yarn",
    "fab",
    "trims",
    "print",
    "embr",
    "wash",
    "cm",
    "commercial",
    "service",
    "fob",
  ];
  const inquiryOf = (h) => (h === "fob" ? num(data?.inquiry.fob) : 0);
  const diff = (b, i) =>
    `${money(b - i)} [ ${i ? Math.round(((b - i) / i) * 100) : 0}%]`;

  // The budget against the inquiry, head by head, per piece — on paper.
  function printInquiry() {
    if (!sums) return;
    const cols = [
      { label: m("figure") },
      ...HEADS.map((h) => ({ label: m(`head.${h}`), align: "right" })),
    ];
    printDocument({
      title: m("compare"),
      docNo: data.order.order_no,
      subtitle: data.order.style_name,
      orientation: "landscape",
      meta: [[m("f.inquiry"), data.order.inquiry_no]],
      notes: [m("per_piece")],
      sections: [
        {
          columns: cols,
          rows: [
            [
              { v: m("budget"), bold: true },
              ...HEADS.map((h) => money(sums.heads[h])),
            ],
            [
              { v: m("inquiry"), bold: true },
              ...HEADS.map((h) => money(inquiryOf(h))),
            ],
          ],
          foot: [
            [m("diff"), ...HEADS.map((h) => diff(sums.heads[h], inquiryOf(h)))],
          ],
        },
      ],
      signatures: true,
    });
  }

  const label =
    "flex flex-col gap-1.5 text-[13px] font-semibold text-foreground";
  const select =
    "h-10 w-full rounded-md border border-border-strong bg-surface px-2 text-sm text-foreground disabled:opacity-60";
  const th =
    "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  const closed = sums ? Math.abs(sums.heads.fob - sums.total) < 0.005 : true;

  return (
    <div className="flex flex-col gap-4">
      <section className="rounded-lg border border-border bg-surface p-5">
        <h2 className="text-base font-semibold text-foreground">
          {m("title")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {m("subtitle")}
        </p>
        <fieldset className="mt-4 rounded-lg border border-border px-4 pt-2 pb-4">
          <legend className="px-1 text-[13px] font-semibold text-brand italic">
            {m("key_info")}
          </legend>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label className={label}>
              {m("f.buyer_ac")}
              <select
                className={select}
                value={form?.buyer_office_id ?? ""}
                onChange={(e) =>
                  form && set({ buyer_office_id: e.target.value })
                }
                disabled={!form || !canManage}
              >
                <option value="">{m("select")}</option>
                {options.offices.map((o) => (
                  <option key={o.value} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </select>
            </label>
            <label className={label}>
              {m("f.style")}
              <select
                className={select}
                value={styleId}
                onChange={(e) => {
                  setStyleId(e.target.value);
                  pickOrder("");
                }}
              >
                <option value="">{m("select")}</option>
                {options.styles.map((s) => (
                  <option key={s.value} value={s.value}>
                    {s.label}
                  </option>
                ))}
              </select>
            </label>
            <label className={label}>
              {m("f.style_name")}
              <Input
                value={
                  data?.order.style_name ??
                  options.styles.find((s) => s.value === styleId)?.name ??
                  ""
                }
                readOnly
                disabled
              />
            </label>
            <label className={label}>
              {m("f.order")}
              <select
                className={select}
                value={data?.order.id ?? ""}
                onChange={(e) => pickOrder(e.target.value)}
              >
                <option value="">{m("select")}</option>
                {orders.map((o) => (
                  <option key={o.value} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </select>
            </label>
            <label className={label}>
              {m("f.currency")}
              <Input
                value={form?.currency ?? "USD"}
                onChange={(e) =>
                  set({ currency: e.target.value.toUpperCase() })
                }
                disabled={!form || !canManage}
                maxLength={3}
              />
            </label>
            <label className={label}>
              {m("f.rate")}
              <Input
                type="number"
                min="0"
                step="0.0001"
                value={form?.exchange_rate ?? "1"}
                onChange={(e) => set({ exchange_rate: e.target.value })}
                disabled={!form || !canManage}
              />
            </label>
            <label className={label}>
              {m("f.oq")}
              <Input
                value={
                  data ? data.order.order_qty.toLocaleString("en-US") : "0"
                }
                readOnly
                disabled
              />
            </label>
            <label className={label}>
              {m("f.value")}
              <Input
                value={data ? money(data.order.order_value) : "0.00"}
                readOnly
                disabled
              />
            </label>
            <label className={label}>
              {m("f.inquiry")}
              <Input value={data?.order.inquiry_no ?? ""} readOnly disabled />
            </label>
            <label className={cn(label, "lg:col-span-3")}>
              {m("f.remarks")}
              <Textarea
                value={form?.remarks ?? ""}
                onChange={(e) => set({ remarks: e.target.value })}
                rows={2}
                disabled={!form || !canManage}
              />
            </label>
          </div>
          <p className="mt-3 text-[13px] text-foreground-muted">
            {m("budget_no")}:{" "}
            <b className="text-foreground">
              {data?.budget.budget_no ?? m("new_budget")}
            </b>{" "}
            · {m("per_piece")}
          </p>
        </fieldset>
      </section>

      {!data || !form ? (
        <p className="rounded-lg border border-border bg-surface px-5 py-10 text-center text-sm text-foreground-muted">
          {m("pick_order")}
        </p>
      ) : (
        <>
          <section className="rounded-lg border border-border bg-surface">
            <div className="flex flex-wrap items-center justify-between gap-3 px-5 pt-4 pb-3">
              <h3 className="text-base font-semibold text-foreground">
                {m("compare")}
              </h3>
              <Button variant="outline" size="sm" onClick={printInquiry}>
                <Printer /> {m("print_inquiry")}
              </Button>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-max text-sm">
                <thead>
                  <tr className="bg-brand text-brand-foreground">
                    <th className={th}>{m("figure")}</th>
                    {HEADS.map((h) => (
                      <th key={h} className={cn(th, "text-right")}>
                        {m(`head.${h}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {[
                    [m("budget"), (h) => money(sums.heads[h])],
                    [m("inquiry"), (h) => money(inquiryOf(h))],
                    [m("diff"), (h) => diff(sums.heads[h], inquiryOf(h))],
                  ].map(([name, cell]) => (
                    <tr
                      key={name}
                      className="border-b border-border last:border-0"
                    >
                      <td className="px-3 py-2 font-semibold text-foreground">
                        {name}
                      </td>
                      {HEADS.map((h) => (
                        <td
                          key={h}
                          className="px-3 py-2 text-right tabular-nums"
                        >
                          {cell(h)}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          <section className="rounded-lg border border-border bg-surface">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 pt-3">
              <div role="tablist" className="flex gap-1">
                {["bom", "process", "overall"].map((k) => (
                  <button
                    key={k}
                    type="button"
                    role="tab"
                    aria-selected={tab === k}
                    onClick={() => setTab(k)}
                    className={cn(
                      "-mb-px border-b-2 px-3 py-2.5 text-sm font-semibold",
                      tab === k
                        ? "border-brand text-brand"
                        : "border-transparent text-foreground-muted hover:text-foreground",
                    )}
                  >
                    {m(`tab_${k}`)}
                  </button>
                ))}
              </div>
              <span className="mb-2 flex flex-wrap items-center gap-2">
                {canManage && tab === "bom" ? (
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={loadFromSources}
                    disabled={pending}
                  >
                    <ListPlus /> {m("load.button")}
                  </Button>
                ) : null}
                <Link
                  href={`/merchandise-desk/bulk-fabric-booking?order=${encodeURIComponent(data.order.order_no)}`}
                  className={buttonVariants({ variant: "outline", size: "sm" })}
                  title={m("load.booking_open")}
                >
                  {m("fabric_booking")}
                </Link>
              </span>
            </div>
            <div className="p-5">
              {tab === "bom" && loadNotes ? (
                <Alert variant="warning" className="mb-3">
                  {loadNotes.map((n) => (
                    <p key={n}>{n}</p>
                  ))}
                </Alert>
              ) : null}
              {tab === "bom" ? (
                <div className="flex flex-col gap-2">
                  {data.sections.map((section, i) => {
                    const lines = form.lines.filter(
                      (l) => l.section === section,
                    );
                    const expanded = open[section] ?? lines.length > 0;
                    return (
                      <div
                        key={section}
                        className="rounded-md border border-border"
                      >
                        <div className="flex items-center justify-between gap-3 px-3 py-2">
                          <button
                            type="button"
                            className="flex items-center gap-2 text-sm font-semibold text-foreground"
                            onClick={() =>
                              setOpen((o) => ({ ...o, [section]: !expanded }))
                            }
                          >
                            {expanded ? (
                              <ChevronDown className="size-4" />
                            ) : (
                              <ChevronRight className="size-4" />
                            )}
                            {i + 1} .{m(`section.${section}`)}
                            {lines.length ? `(${lines.length})` : ""}
                          </button>
                          <span className="flex items-center gap-3">
                            <span className="text-[13px] tabular-nums text-foreground-muted">
                              {money(sums.section(section))}
                            </span>
                            {canManage ? (
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => addLine(section)}
                              >
                                <Plus /> {m("add")}
                              </Button>
                            ) : null}
                          </span>
                        </div>
                        {expanded && lines.length ? (
                          <div className="overflow-x-auto border-t border-border">
                            <table className="w-full min-w-max text-sm">
                              <thead>
                                <tr className="bg-surface-muted text-left">
                                  {[
                                    ...(data.accessory_sections?.includes(
                                      section,
                                    )
                                      ? ["item", "item_code", "supplier"]
                                      : ["description"]),
                                    "uom",
                                    "cons_dzn",
                                    "wastage",
                                    "qty",
                                    "rate_col",
                                    "amount",
                                    "order_req",
                                  ].map((k) => (
                                    <th
                                      key={k}
                                      className="px-3 py-2 text-[13px] font-semibold"
                                    >
                                      {m(`f.${k}`)}
                                    </th>
                                  ))}
                                  <th className="px-3 py-2 text-[13px] font-semibold">
                                    {m("remove")}
                                  </th>
                                </tr>
                              </thead>
                              <tbody>
                                {lines.map((l) => (
                                  <tr
                                    key={l.key}
                                    className="border-t border-border"
                                  >
                                    <td className="px-3 py-1.5 align-top">
                                      {data.accessory_sections?.includes(
                                        section,
                                      ) ? (
                                        <div className="flex w-72 flex-col gap-1">
                                          <Select
                                            value={l.item_id}
                                            onValueChange={(v) =>
                                              pickItem(l, v ?? "")
                                            }
                                            options={[
                                              {
                                                value: "",
                                                label: m("offer.item_pick"),
                                              },
                                              ...(data.catalog?.items ?? []),
                                            ]}
                                            disabled={!canManage}
                                            aria-label={m("f.item")}
                                          />
                                          {/* A line with no item in the master keeps its own description. */}
                                          {!l.item_id ? (
                                            <Input
                                              value={l.description}
                                              onChange={(e) =>
                                                setLine(l.key, {
                                                  description: e.target.value,
                                                })
                                              }
                                              placeholder={m("f.description")}
                                              disabled={!canManage}
                                              aria-label={m("f.description")}
                                            />
                                          ) : null}
                                        </div>
                                      ) : (
                                        <Input
                                          value={l.description}
                                          onChange={(e) =>
                                            setLine(l.key, {
                                              description: e.target.value,
                                            })
                                          }
                                          className="w-72"
                                          disabled={!canManage}
                                          aria-label={m("f.description")}
                                        />
                                      )}
                                      {l.source ? (
                                        <span className="mt-0.5 inline-block rounded bg-brand/10 px-1.5 text-[11px] font-semibold text-brand">
                                          {m(`load.src_${l.source}`)}
                                        </span>
                                      ) : null}
                                    </td>
                                    {data.accessory_sections?.includes(
                                      section,
                                    ) ? (
                                      <>
                                        <td className="px-3 py-1.5 whitespace-nowrap text-foreground-muted">
                                          {l.item_code || "—"}
                                        </td>
                                        <td className="px-3 py-1.5">
                                          <Select
                                            value={l.supplier_id}
                                            onValueChange={(v) =>
                                              pickSupplier(l, v ?? "")
                                            }
                                            options={supplierOptions(l)}
                                            className="w-72"
                                            disabled={!canManage}
                                            aria-label={m("f.supplier")}
                                          />
                                        </td>
                                      </>
                                    ) : null}
                                    <td className="px-3 py-1.5">
                                      <UomSelect
                                        value={l.uom}
                                        onChange={(v) =>
                                          setLine(l.key, { uom: v })
                                        }
                                        compact
                                        className="w-32"
                                        disabled={!canManage}
                                        aria-label={m("f.uom")}
                                      />
                                    </td>
                                    {["cons_dzn", "wastage_pct"].map((k) => (
                                      <td key={k} className="px-3 py-1.5">
                                        <Input
                                          type="number"
                                          min="0"
                                          step="any"
                                          value={l[k]}
                                          onChange={(e) =>
                                            setLine(l.key, {
                                              [k]: e.target.value,
                                            })
                                          }
                                          className="w-24"
                                          disabled={!canManage}
                                          aria-label={m(
                                            k === "cons_dzn"
                                              ? "f.cons_dzn"
                                              : "f.wastage",
                                          )}
                                        />
                                      </td>
                                    ))}
                                    <td className="px-3 py-1.5">
                                      {/* Worked out when the consumption is given; typed when it is not. */}
                                      <Input
                                        type="number"
                                        min="0"
                                        step="any"
                                        value={
                                          given(l.cons_dzn)
                                            ? String(qtyOf(l))
                                            : l.qty
                                        }
                                        onChange={(e) =>
                                          setLine(l.key, {
                                            qty: e.target.value,
                                          })
                                        }
                                        className="w-24"
                                        disabled={!canManage}
                                        readOnly={given(l.cons_dzn)}
                                        title={
                                          given(l.cons_dzn)
                                            ? m("load.auto_qty")
                                            : undefined
                                        }
                                        aria-label={m("f.qty")}
                                      />
                                    </td>
                                    <td className="px-3 py-1.5">
                                      <Input
                                        type="number"
                                        min="0"
                                        step="any"
                                        value={l.rate}
                                        onChange={(e) =>
                                          setLine(l.key, {
                                            rate: e.target.value,
                                          })
                                        }
                                        className="w-24"
                                        disabled={!canManage}
                                        aria-label={m("f.rate_col")}
                                      />
                                    </td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">
                                      {money(qtyOf(l) * num(l.rate))}
                                    </td>
                                    <td className="px-3 py-1.5 text-right whitespace-nowrap tabular-nums text-foreground-muted">
                                      {(
                                        qtyOf(l) * data.order.order_qty
                                      ).toLocaleString("en-US", {
                                        maximumFractionDigits: 2,
                                      })}{" "}
                                      {l.uom}
                                    </td>
                                    <td className="px-3 py-1.5">
                                      {canManage ? (
                                        <Button
                                          variant="outline"
                                          size="sm"
                                          onClick={() =>
                                            setForm((f) => ({
                                              ...f,
                                              lines: f.lines.filter(
                                                (x) => x.key !== l.key,
                                              ),
                                            }))
                                          }
                                          aria-label={m("remove")}
                                        >
                                          <X className="text-danger" />
                                        </Button>
                                      ) : null}
                                    </td>
                                  </tr>
                                ))}
                              </tbody>
                            </table>
                          </div>
                        ) : null}
                      </div>
                    );
                  })}
                </div>
              ) : (
                <HeadTable
                  title={m(tab === "process" ? "tab_process" : "tab_overall")}
                  rows={
                    tab === "process"
                      ? data.process_heads.map((h) => ({
                          key: h,
                          label: m(`process.${h}`),
                          inquiry: 0,
                          value: form.process[h],
                          onChange: (v) =>
                            set({ process: { ...form.process, [h]: v } }),
                        }))
                      : [
                          {
                            key: "yarn",
                            label: m("overall.yarn"),
                            inquiry: 0,
                            fixed: sums.yarn,
                          },
                          {
                            key: "trims",
                            label: m("overall.trims"),
                            inquiry: 0,
                            fixed: sums.trims,
                          },
                          ...TYPED.map((h) => ({
                            key: h,
                            label: m(`overall.${h}`),
                            inquiry: h === "fob" ? num(data.inquiry.fob) : 0,
                            value: form.overall[h],
                            onChange: (v) =>
                              set({ overall: { ...form.overall, [h]: v } }),
                          })),
                        ]
                  }
                  m={m}
                  canManage={canManage}
                />
              )}
              <p className="mt-4 text-[13px] text-foreground-muted">
                {m("fob_note")}
              </p>
              <Alert variant={closed ? "success" : "warning"} className="mt-2">
                {closed
                  ? m("closed")
                  : m("not_closed", {
                      fob: money(sums.heads.fob),
                      sum: money(sums.total),
                    })}
              </Alert>
            </div>
          </section>

          {locked ? <Alert variant="info">{m("locked")}</Alert> : null}
          {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
          <div className="flex flex-wrap gap-2">
            {canManage ? (
              <>
                <Button onClick={submit} loading={pending}>
                  <Save /> {m("save")}
                </Button>
                <Button
                  variant="outline"
                  onClick={() => setForm(formOf(data))}
                  disabled={pending}
                >
                  <Undo2 /> {m("cancel")}
                </Button>
                <Button variant="outline" onClick={reset} disabled={pending}>
                  <RotateCcw /> {m("reset")}
                </Button>
              </>
            ) : null}
            <Link
              href={listHref}
              className={buttonVariants({ variant: "outline" })}
            >
              {m("back")}
            </Link>
            <Link
              href="/merchandise-desk/bulk-fabric-booking?tab=new"
              className={buttonVariants({ variant: "outline" })}
            >
              {m("create_booking")}
            </Link>
          </div>
        </>
      )}
    </div>
  );
}

/** Fabric Process Cost / Overall Cost: each head's inquiry figure, its budget (typed, or worked out) and the difference. */
function HeadTable({ title, rows, m, canManage }) {
  const th =
    "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  return (
    <div>
      <h4 className="mb-2 text-sm font-semibold text-foreground">{title}</h4>
      <div className="overflow-x-auto rounded-md border border-border">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-brand text-brand-foreground">
              <th className={th}>{m("cost_head")}</th>
              <th className={cn(th, "text-right")}>{m("inquiry")}</th>
              <th className={th}>{m("budget")}</th>
              <th className={cn(th, "text-right")}>{m("diff")}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => {
              const budget = r.fixed ?? num(r.value);
              return (
                <tr
                  key={r.key}
                  className="border-b border-border last:border-0"
                >
                  <td className="px-3 py-2 font-medium text-foreground">
                    {r.label}
                  </td>
                  <td className="px-3 py-2 text-right tabular-nums">
                    {money(r.inquiry)}
                  </td>
                  <td className="px-3 py-2">
                    {r.fixed !== undefined ? (
                      <span className="tabular-nums" title={m("from_bom")}>
                        {money(r.fixed)}
                      </span>
                    ) : (
                      <Input
                        type="number"
                        min="0"
                        step="any"
                        value={r.value}
                        onChange={(e) => r.onChange(e.target.value)}
                        className="w-32"
                        disabled={!canManage}
                        aria-label={r.label}
                      />
                    )}
                  </td>
                  <td className="px-3 py-2 text-right tabular-nums">
                    {money(budget - r.inquiry)}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export { BudgetSheet };
