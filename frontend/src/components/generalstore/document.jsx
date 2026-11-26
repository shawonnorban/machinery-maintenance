"use client";

import { useMemo, useState, useTransition } from "react";
import {
  Ban,
  Download,
  Eye,
  Plus,
  Printer,
  RotateCcw,
  Save,
  Search,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import {
  card,
  day,
  stamp,
  num,
  money,
  dash,
  messagesOf,
  today,
  dropError,
  Field,
  Pill,
  printWindow,
} from "./common";

/*
 * General Store → Inventory (AnnotechERP 10/03), the six document screens as
 * Brickland lays them out: the new document's header, its item lines (each
 * showing the item's code, unit, stock here and in all stores, price and
 * value), the totals with Clear and Save; then the filters and the list with
 * Excel, View and Print. A saved document says so in a banner with Print
 * Voucher; a posted one is cancelled from its view with a reason. Nothing
 * reloads.
 */

const KIND = {
  opening: {
    type: "OPENING",
    steps: "price",
    head: [["date", "store", "reference"], ["remarks"]],
    price: "optional",
    filter: null,
    cols: ["store"],
  },
  receive: {
    type: "RECEIVE",
    steps: "price",
    head: [
      ["date", "store", "supplier", "challan"],
      ["reference", "remarks"],
    ],
    price: "optional",
    filter: "supplier",
    cols: ["store", "supplier"],
  },
  issue: {
    type: "ISSUE",
    steps: "issue",
    head: [
      ["date", "store", "requisition", "department"],
      ["receiver", "remarks"],
    ],
    price: "required",
    qty: "issue_qty",
    filter: "department",
    cols: ["store", "department", "requisition"],
  },
  return: {
    type: "RETURN",
    steps: "plain",
    head: [
      ["date", "store", "against", "department"],
      ["returned_by", "reference", "remarks"],
    ],
    qty: "return_qty",
    filter: "department",
    cols: ["store", "department", "against"],
  },
  adjustment: {
    type: "ADJUST",
    steps: "plain",
    head: [["date", "store", "adjustment_type", "reference"], ["reason"]],
    qty: "adjust_qty",
    filter: null,
    cols: ["store", "type"],
  },
  transfer: {
    type: "TRANSFER",
    steps: "plain",
    head: [["date", "from_store", "to_store", "reference"], ["remarks"]],
    filter: null,
    cols: ["from_store", "to_store"],
  },
};

/** Lines that take stock out of the store: their quantity is held to what is there. */
const OUTWARD = { ISSUE: true, TRANSFER: true };
const DECREASING = ["DECREASE", "DAMAGE", "LOST"];

const blankLine = () => ({
  category: "",
  gs_item_id: "",
  quantity: "",
  unit_price: "",
  line_remarks: "",
});
const blankFilters = {
  q: "",
  from: "",
  to: "",
  store: "",
  item: "",
  supplier: "",
  department: "",
  status: "",
};
const used = (l) => l.gs_item_id || String(l.quantity).trim() !== "";

/**
 * One document screen.
 *
 * @param {{ kind: string, options: object, rows: object[], canPost: boolean, canCancel: boolean, act: (op: string, ...args: any[]) => Promise<object> }} props
 */
function StoreDocumentScreen({
  kind,
  options: firstOptions,
  rows: firstRows,
  canPost,
  canCancel,
  act,
  initialRequisition = null,
}) {
  const t = useT("store");
  const v = (k, p) => t(`inv.${k}`, p);
  const toastManager = useToastManager();
  const cfg = KIND[kind];
  const type = cfg.type;
  const [options, setOptions] = useState(firstOptions);
  const defaultStore =
    options.stores.find((s) => s.is_default)?.value ??
    options.stores[0]?.value ??
    "";
  const blankHead = (store = defaultStore, date = today()) => ({
    doc_date: date,
    gs_store_id: store,
    to_store_id: "",
    gs_supplier_id: "",
    challan_no: "",
    reference_no: "",
    gs_department_id: "",
    person: "",
    against: "",
    requisition: "",
    adjustment_type: "",
    remarks: "",
  });
  // An issue opened from an approved requisition (`?req=REQ-…`) starts with it picked.
  const fromRequisition = (r) => ({
    store_id: r.store_id,
    lines: r.lines.map((x) => ({ item_id: x.item_id, eligible: x.remaining })),
  });
  const requisitionLines = (r) =>
    r.lines
      .filter((x) => x.remaining > 0)
      .map((x) => ({
        ...blankLine(),
        gs_item_id: x.item_id,
        unit_price: priceOf(x.item_id),
      }));
  const priceOf = (id) => {
    const price = firstOptions.items.find((i) => i.value === id)?.price;
    return price === null || price === undefined ? "" : String(price);
  };
  const [head, setHead] = useState(() =>
    initialRequisition
      ? {
          ...blankHead(initialRequisition.store_id),
          requisition: initialRequisition.req_no,
          gs_department_id: initialRequisition.department_id ?? "",
          person: initialRequisition.requested_by ?? "",
        }
      : blankHead(),
  );
  const [lines, setLines] = useState(() =>
    initialRequisition ? requisitionLines(initialRequisition) : [blankLine()],
  );
  // What a return or a requisition-issue is held to: its items and how much of each may still go.
  const [against, setAgainst] = useState(() =>
    initialRequisition ? fromRequisition(initialRequisition) : null,
  );
  const [errors, setErrors] = useState({});
  const [banner, setBanner] = useState(null);
  const [rows, setRows] = useState(firstRows);
  const [filters, setFilters] = useState(blankFilters);
  const [viewing, setViewing] = useState(null);
  const [reason, setReason] = useState(null);
  const [pending, startTransition] = useTransition();

  const items = useMemo(
    () => Object.fromEntries(options.items.map((i) => [i.value, i])),
    [options.items],
  );
  const count = type === "ADJUST" && head.adjustment_type === "COUNT";
  const decreasing =
    type === "ADJUST" && DECREASING.includes(head.adjustment_type);
  const qtyLabel = count ? "physical_qty" : (cfg.qty ?? "quantity");
  const fail = (result) => {
    const all = messagesOf(result);
    setErrors(all);
    const first = Object.values(all)[0]?.[0];
    if (first) toastManager.add({ title: first, type: "error" });
  };

  const setH = (k) => (value) => {
    setHead((all) => ({ ...all, [k]: value ?? "" }));
    setErrors((all) => dropError(all, k));
  };
  const setLine = (i, k) => (value) => {
    setLines((all) =>
      all.map((l, j) => {
        if (j !== i) return l;
        const next = { ...l, [k]: value ?? "" };
        if (k === "category") next.gs_item_id = "";
        // A picked item brings its last purchase price, as Brickland fills it.
        if (k === "gs_item_id" && cfg.price) {
          const price = items[value]?.price;
          next.unit_price =
            price === null || price === undefined ? "" : String(price);
        }
        return next;
      }),
    );
    setErrors((all) => dropError(dropError(all, `lines.${i}.${k}`), "lines"));
  };

  // A line's figures: the item, its stock here and in all stores, the movement it makes and its value.
  const figures = (l) => {
    const item = items[l.gs_item_id];
    if (!item) return null;
    const stock = item.stock ?? {};
    const here = Number(stock[head.gs_store_id] ?? 0);
    const all = Object.values(stock).reduce((s, q) => s + Number(q), 0);
    const qty = Number(l.quantity || 0);
    const price =
      cfg.price && l.unit_price !== "" ? Number(l.unit_price) : item.price;
    const moved = count ? qty - here : decreasing || OUTWARD[type] ? -qty : qty;
    const leaving =
      OUTWARD[type] || decreasing ? qty : count && moved < 0 ? -moved : 0;
    const value =
      price === null || price === undefined ? null : Math.abs(moved) * price;
    const cap = against?.lines.find(
      (x) => x.item_id === l.gs_item_id,
    )?.eligible;
    return { item, here, all, qty, price, moved, leaving, value, cap };
  };

  const picked = lines.filter((l) => l.gs_item_id);
  const totals = picked.reduce(
    (acc, l) => {
      const f = figures(l);
      if (!f) return acc;
      acc.qty += Math.abs(count ? f.moved : f.qty);
      if (f.value !== null) {
        acc.value += f.value;
        acc.priced = true;
      }
      return acc;
    },
    { qty: 0, value: 0, priced: false },
  );

  function pickAgainst(no) {
    setH("against")(no);
    setAgainst(null);
    if (!no) return setLines([blankLine()]);
    startTransition(async () => {
      const result = await act("returnable", no);
      if (result?.status !== "success") return fail(result);
      const r = result.data;
      setAgainst(r);
      setHead((all) => ({
        ...all,
        gs_store_id: r.store_id,
        gs_department_id: r.department_id ?? "",
      }));
      setLines(
        r.lines
          .filter((x) => x.eligible > 0)
          .map((x) => ({ ...blankLine(), gs_item_id: x.item_id })),
      );
    });
  }

  function pickRequisition(no) {
    setH("requisition")(no);
    setAgainst(null);
    if (!no) return setLines([blankLine()]);
    startTransition(async () => {
      const result = await act("issuable", no);
      if (result?.status !== "success") return fail(result);
      const r = result.data;
      setAgainst(fromRequisition(r));
      setHead((all) => ({
        ...all,
        gs_store_id: r.store_id,
        gs_department_id: r.department_id ?? "",
        person: all.person || (r.requested_by ?? ""),
      }));
      setLines(requisitionLines(r));
    });
  }

  function clear() {
    setHead(blankHead(head.gs_store_id || defaultStore, head.doc_date));
    setLines([blankLine()]);
    setAgainst(null);
    setErrors({});
  }

  async function refresh(nf = filters) {
    const [o, l] = await Promise.all([
      act("options", kind),
      act("list", kind, filled(nf)),
    ]);
    if (o?.status === "success") setOptions(o.data);
    if (l?.status === "success") setRows(l.data.rows);
  }

  function save() {
    setErrors({});
    // Against a requisition, a line left at nothing is simply not issued now.
    const sent = lines
      .map((l, i) => ({ l, i }))
      .filter(
        ({ l }) => used(l) && (!head.requisition || Number(l.quantity) > 0),
      );
    const fields = {
      OPENING: ["reference_no"],
      RECEIVE: ["gs_supplier_id", "challan_no", "reference_no"],
      ISSUE: ["requisition", "gs_department_id", "person"],
      RETURN: [
        "against",
        ...(head.against ? [] : ["gs_department_id"]),
        "person",
        "reference_no",
      ],
      ADJUST: ["adjustment_type", "reference_no"],
      TRANSFER: ["to_store_id", "reference_no"],
    }[type];
    const nil = (x) => (x === "" ? null : x);
    const payload = {
      doc_date: head.doc_date,
      gs_store_id: nil(head.gs_store_id),
      remarks: nil(head.remarks),
      ...Object.fromEntries(fields.map((k) => [k, nil(head[k])])),
      lines: sent.map(({ l }) => ({
        gs_item_id: nil(l.gs_item_id),
        quantity: l.quantity,
        ...(cfg.price ? { unit_price: nil(l.unit_price) } : {}),
        line_remarks: nil(l.line_remarks),
      })),
    };
    startTransition(async () => {
      const result = await act("save", kind, payload);
      if (result?.status !== "success") {
        // The server numbers the lines it was sent; map them back to the form's.
        const remapped = Object.fromEntries(
          Object.entries(messagesOf(result)).map(([k, msg]) => {
            const m = k.match(/^lines\.(\d+)\.(.+)$/);
            return [
              m ? `lines.${sent[Number(m[1])]?.i ?? m[1]}.${m[2]}` : k,
              msg,
            ];
          }),
        );
        return fail({ errors: remapped });
      }
      const doc = result.data;
      setBanner(doc);
      toastManager.add({
        title: v("posted_toast", { type: v(`type.${type}`), no: doc.doc_no }),
        type: "success",
      });
      clear();
      await refresh();
    });
  }

  function search(nf) {
    setFilters(nf);
    startTransition(async () => {
      const result = await act("list", kind, filled(nf));
      if (result?.status === "success") setRows(result.data.rows);
    });
  }

  function open(no, then) {
    startTransition(async () => {
      const result = await act("show", no);
      if (result?.status !== "success") return fail(result);
      then(result.data);
    });
  }

  function cancelDoc() {
    startTransition(async () => {
      const result = await act("cancel", viewing.doc_no, { reason });
      if (result?.status !== "success") return fail(result);
      setReason(null);
      setViewing(result.data);
      setErrors({});
      toastManager.add({
        title: v("cancel.done", { no: result.data.doc_no }),
        type: "success",
      });
      await refresh();
    });
  }

  const print = (doc) =>
    printWindow({
      title: v(`type.${doc.doc_type}`),
      docNo: doc.doc_no,
      org: options.voucher?.name,
      footer: options.voucher?.footer,
      facts: viewFacts(doc, v).map(([k, val]) => [k, val ?? "—"]),
      columns: [
        { label: v("view.col.sl") },
        { label: v("view.col.item_code") },
        { label: v("view.col.item_name") },
        { label: v("view.col.unit") },
        { label: v("view.col.qty"), right: true },
        { label: v("view.col.price"), right: true },
        { label: v("view.col.value"), right: true },
        { label: v("view.col.remarks") },
      ],
      rows: doc.lines.map((l) => [
        l.sl,
        l.item_code,
        l.item_name,
        l.unit,
        num(doc.doc_type === "ADJUST" ? l.movement_qty : l.quantity),
        num(l.unit_price),
        num(l.value),
        dash(l.remarks),
      ]),
      foot: [
        t("rpt.total"),
        "",
        "",
        "",
        num(doc.total_qty),
        "",
        money(doc.total_value),
        "",
      ],
    });

  const err = (k) => errors[k]?.[0];
  const general = ["lines", "document", "_"]
    .map((k) => errors[k]?.[0])
    .filter(Boolean);
  const storeOptions = options.stores.map((s) => ({
    value: s.value,
    label: s.label,
  }));

  const headField = (k) => {
    switch (k) {
      case "date":
        return (
          <Field key={k} label={v("f.date")} required error={err("doc_date")}>
            <Input
              type="date"
              value={head.doc_date}
              onChange={(ev) => setH("doc_date")(ev.target.value)}
              aria-label={v("f.date")}
            />
          </Field>
        );
      case "store":
      case "from_store":
        return (
          <Field
            key={k}
            label={v(`f.${k}`)}
            required
            error={err("gs_store_id")}
          >
            <Select
              value={head.gs_store_id}
              onValueChange={setH("gs_store_id")}
              options={storeOptions}
              placeholder={v("f.select")}
              disabled={Boolean(against)}
              aria-label={v(`f.${k}`)}
            />
          </Field>
        );
      case "to_store":
        return (
          <Field
            key={k}
            label={v("f.to_store")}
            required
            error={err("to_store_id")}
          >
            <Select
              value={head.to_store_id}
              onValueChange={setH("to_store_id")}
              options={storeOptions.filter((s) => s.value !== head.gs_store_id)}
              placeholder={v("f.select")}
              aria-label={v("f.to_store")}
            />
          </Field>
        );
      case "supplier":
        return (
          <Field
            key={k}
            label={v("f.supplier")}
            hint={options.suppliers.length ? null : v("f.no_records")}
          >
            <Select
              value={head.gs_supplier_id}
              onValueChange={setH("gs_supplier_id")}
              options={[
                { value: "", label: v("f.no_supplier") },
                ...options.suppliers,
              ]}
              placeholder={v("f.no_supplier")}
              disabled={!options.suppliers.length}
              aria-label={v("f.supplier")}
            />
          </Field>
        );
      case "challan":
        return (
          <Field key={k} label={v("f.challan")}>
            <Input
              value={head.challan_no}
              onChange={(ev) => setH("challan_no")(ev.target.value)}
              aria-label={v("f.challan")}
            />
          </Field>
        );
      case "reference":
        return (
          <Field key={k} label={v("f.reference")}>
            <Input
              value={head.reference_no}
              onChange={(ev) => setH("reference_no")(ev.target.value)}
              aria-label={v("f.reference")}
            />
          </Field>
        );
      case "requisition":
        return (
          <Field key={k} label={v("f.requisition")} error={err("requisition")}>
            <Select
              value={head.requisition}
              onValueChange={pickRequisition}
              options={[
                ...(options.direct_issue
                  ? [{ value: "", label: v("f.direct") }]
                  : []),
                ...options.requisitions,
              ]}
              placeholder={options.direct_issue ? v("f.direct") : v("f.select")}
              aria-label={v("f.requisition")}
            />
          </Field>
        );
      case "department":
        return (
          <Field
            key={k}
            label={v("f.department")}
            required={type === "ISSUE"}
            error={err("gs_department_id")}
          >
            <Select
              value={head.gs_department_id}
              onValueChange={setH("gs_department_id")}
              options={options.departments}
              placeholder={v("f.select")}
              disabled={Boolean(against)}
              aria-label={v("f.department")}
            />
          </Field>
        );
      case "receiver":
      case "returned_by":
        return (
          <Field
            key={k}
            label={v(`f.${k}`)}
            required={k === "receiver" && options.receiver_required}
            error={err("person")}
          >
            <Input
              value={head.person}
              onChange={(ev) => setH("person")(ev.target.value)}
              aria-label={v(`f.${k}`)}
            />
          </Field>
        );
      case "against":
        return (
          <Field key={k} label={v("f.against")} error={err("against")}>
            <Select
              value={head.against}
              onValueChange={pickAgainst}
              options={[
                { value: "", label: v("f.without_issue") },
                ...options.issues,
              ]}
              placeholder={v("f.without_issue")}
              aria-label={v("f.against")}
            />
          </Field>
        );
      case "adjustment_type":
        return (
          <Field
            key={k}
            label={v("f.adjustment_type")}
            required
            error={err("adjustment_type")}
          >
            <Select
              value={head.adjustment_type}
              onValueChange={setH("adjustment_type")}
              options={options.adjustment_types.map((a) => ({
                value: a,
                label: v(`adjust.${a}`),
              }))}
              placeholder={v("f.select")}
              aria-label={v("f.adjustment_type")}
            />
          </Field>
        );
      case "remarks":
      case "reason":
        return (
          <Field
            key={k}
            label={v(`f.${k}`)}
            required={k === "reason"}
            error={err("remarks")}
            className="sm:col-span-2"
          >
            <Textarea
              rows={2}
              value={head.remarks}
              onChange={(ev) => setH("remarks")(ev.target.value)}
              aria-label={v(`f.${k}`)}
            />
          </Field>
        );
      default:
        return null;
    }
  };

  return (
    <div className="flex flex-col gap-4">
      {banner ? (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-success/30 bg-success-subtle px-5 py-3">
          <p className="text-sm font-semibold text-success">
            {v("posted_banner", { no: banner.doc_no })}
          </p>
          <div className="flex gap-2">
            <Button size="sm" variant="outline" onClick={() => print(banner)}>
              <Printer className="size-4" aria-hidden />
              {v("btn.print_voucher")}
            </Button>
            <Button size="sm" variant="outline" onClick={() => setBanner(null)}>
              {v("btn.dismiss")}
            </Button>
          </div>
        </div>
      ) : null}

      {canPost ? (
        <section className={cn(card, "flex flex-col gap-4 p-5")}>
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {v("new", { type: v(`type.${type}`) })}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {v(`steps.${cfg.steps}`)}
            </p>
          </div>
          {cfg.head.map((row, r) => (
            <div
              key={r}
              className="grid gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-4"
            >
              {row.map(headField)}
            </div>
          ))}

          <div className="flex flex-col gap-3">
            {lines.map((l, i) => {
              const f = figures(l);
              const short = f && f.leaving > f.here + 0.00005;
              const over = f && f.cap !== undefined && f.qty > f.cap + 0.00005;
              const choices = options.items.filter(
                (o) =>
                  (!l.category || o.category_id === l.category) &&
                  (!against ||
                    against.lines.some((x) => x.item_id === o.value)),
              );
              const hint =
                f && count
                  ? v("line.difference", { n: num(f.moved) })
                  : f && f.cap !== undefined
                    ? v("line.up_to", { n: num(f.cap) })
                    : null;
              const qtyBox = (
                <Field
                  label={v(`line.${qtyLabel}`)}
                  required
                  error={
                    err(`lines.${i}.quantity`) ??
                    (short
                      ? v("line.only", { n: num(Math.max(0, f.here)) })
                      : null) ??
                    (over ? v("line.up_to", { n: num(f.cap) }) : null)
                  }
                  hint={hint}
                >
                  <div className="relative">
                    <Input
                      type="number"
                      step="any"
                      value={l.quantity}
                      onChange={(ev) => setLine(i, "quantity")(ev.target.value)}
                      aria-label={`${v(`line.${qtyLabel}`)} ${i + 1}`}
                      className={cn(
                        "pr-14",
                        (short || over || err(`lines.${i}.quantity`)) &&
                          "border-danger",
                      )}
                    />
                    {f ? (
                      <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-foreground-muted">
                        {f.item.unit}
                      </span>
                    ) : null}
                  </div>
                </Field>
              );
              return (
                <div
                  key={i}
                  className="flex flex-col gap-3 rounded-lg border border-border p-3"
                >
                  <div className="flex items-center justify-between">
                    <p className="text-[13px] font-semibold text-foreground">
                      {v("line.title", { n: i + 1 })}
                    </p>
                    {lines.length > 1 ? (
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() =>
                          setLines((all) => all.filter((_, j) => j !== i))
                        }
                        aria-label={`${v("line.remove")} ${i + 1}`}
                      >
                        <Trash2 className="size-4" aria-hidden />
                        {v("line.remove")}
                      </Button>
                    ) : null}
                  </div>
                  <div
                    className={cn(
                      "grid items-start gap-3 sm:grid-cols-2",
                      type === "ISSUE"
                        ? "lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_repeat(5,minmax(0,1fr))]"
                        : cfg.price
                          ? "lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_repeat(3,minmax(0,1fr))]"
                          : "lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_repeat(2,minmax(0,1fr))_minmax(0,1fr)]",
                    )}
                  >
                    <Field label={v("line.category")}>
                      <Select
                        value={l.category}
                        onValueChange={setLine(i, "category")}
                        options={[
                          { value: "", label: v("line.all_categories") },
                          ...options.categories,
                        ]}
                        placeholder={v("line.all_categories")}
                        aria-label={`${v("line.category")} ${i + 1}`}
                      />
                    </Field>
                    <Field
                      label={v("line.item")}
                      required
                      error={err(`lines.${i}.gs_item_id`)}
                    >
                      <Select
                        value={l.gs_item_id}
                        onValueChange={setLine(i, "gs_item_id")}
                        options={choices.map((o) => ({
                          value: o.value,
                          label: o.label,
                        }))}
                        placeholder={v("f.select")}
                        aria-label={`${v("line.item")} ${i + 1}`}
                      />
                    </Field>
                    {type === "ISSUE" ? (
                      <Field label={v("line.available")}>
                        <div className="relative">
                          <Input
                            readOnly
                            value={f ? num(f.here) : "—"}
                            className="bg-surface-muted pr-14"
                            aria-label={`${v("line.available")} ${i + 1}`}
                          />
                          {f ? (
                            <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-foreground-muted">
                              {f.item.unit}
                            </span>
                          ) : null}
                        </div>
                      </Field>
                    ) : null}
                    {qtyBox}
                    {cfg.price ? (
                      <Field
                        label={v(
                          type === "ISSUE" ? "line.unit_price" : "line.price",
                        )}
                        required={type === "ISSUE"}
                        error={err(`lines.${i}.unit_price`)}
                        hint={
                          type === "ISSUE"
                            ? f
                              ? v("line.last_price")
                              : null
                            : v("line.optional")
                        }
                      >
                        <Input
                          type="number"
                          min="0"
                          step="any"
                          value={l.unit_price}
                          onChange={(ev) =>
                            setLine(i, "unit_price")(ev.target.value)
                          }
                          aria-label={`${v(type === "ISSUE" ? "line.unit_price" : "line.price")} ${i + 1}`}
                        />
                      </Field>
                    ) : null}
                    {type === "ISSUE" ? (
                      <Field label={v("line.total_value")}>
                        <Input
                          readOnly
                          value={f && f.value !== null ? num(f.value) : "—"}
                          className="bg-surface-muted"
                          aria-label={`${v("line.total_value")} ${i + 1}`}
                        />
                      </Field>
                    ) : null}
                    <Field label={v("line.remarks")}>
                      <Input
                        value={l.line_remarks}
                        onChange={(ev) =>
                          setLine(i, "line_remarks")(ev.target.value)
                        }
                        aria-label={`${v("line.remarks")} ${i + 1}`}
                      />
                    </Field>
                  </div>
                  {f ? (
                    <p className="flex flex-wrap gap-x-5 gap-y-1 text-xs text-foreground-muted">
                      <span>
                        {v("line.info.code")}{" "}
                        <b className="text-foreground">{f.item.code}</b>
                      </span>
                      <span>
                        {v("line.info.unit")}{" "}
                        <b className="text-foreground">{f.item.unit}</b>
                      </span>
                      <span>
                        {v("line.info.current")}{" "}
                        <b
                          className={
                            f.here > 0 ? "text-success" : "text-danger"
                          }
                        >
                          {num(f.here)} {f.item.unit}
                        </b>
                      </span>
                      <span>
                        {v("line.info.all_stores")}{" "}
                        <b className="text-foreground">
                          {num(f.all)} {f.item.unit}
                        </b>
                      </span>
                      <span>
                        {v("line.info.price")}{" "}
                        <b className="text-foreground">{money(f.price)}</b>
                      </span>
                      <span>
                        {v("line.info.value")}{" "}
                        <b className="text-foreground">{money(f.value ?? 0)}</b>
                      </span>
                    </p>
                  ) : null}
                </div>
              );
            })}
          </div>

          {general.length ? <Alert variant="danger">{general[0]}</Alert> : null}

          <div className="flex flex-wrap items-center justify-between gap-3">
            {against ? (
              <span />
            ) : (
              <Button
                variant="outline"
                size="sm"
                onClick={() => setLines((all) => [...all, blankLine()])}
              >
                <Plus className="size-4" aria-hidden />
                {v("line.add")}
              </Button>
            )}
            <div className="flex flex-wrap items-center gap-4 text-sm text-foreground">
              {type === "ISSUE" ? (
                <span>
                  {v("totals.items")} <b>{picked.length}</b>
                </span>
              ) : null}
              <span>
                {v(type === "ISSUE" ? "totals.issue_qty" : "totals.qty")}{" "}
                <b className="tabular-nums">{num(totals.qty)}</b>
              </span>
              <span>
                {v(type === "ISSUE" ? "totals.issue_value" : "totals.value")}{" "}
                <b className="tabular-nums">
                  {totals.priced ? money(totals.value) : "—"}
                </b>
              </span>
              <Button variant="outline" onClick={clear} disabled={pending}>
                {v("btn.clear")}
              </Button>
              <Button onClick={save} loading={pending}>
                <Save className="size-4" aria-hidden />
                {v("btn.save")}
              </Button>
            </div>
          </div>
        </section>
      ) : null}

      <ListFilters
        cfg={cfg}
        options={options}
        pending={pending}
        onSearch={search}
        v={v}
      />

      <section className={cn(card, "min-w-0 overflow-hidden")}>
        <div className="flex items-center justify-between gap-3 p-5">
          <h2 className="text-base font-semibold text-foreground">
            {v("list.title", { type: v(`type.${type}`), n: rows.length })}
          </h2>
          <a
            href={`/api/general-store/excel/${kind}${query(filled(filters))}`}
            className={buttonVariants({ variant: "outline", size: "sm" })}
          >
            <Download className="size-4" aria-hidden />
            {v("btn.excel")}
          </a>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[960px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "doc_no",
                  "date",
                  ...cfg.cols,
                  "items",
                  "total_qty",
                  "value",
                  "status",
                  "user",
                  "",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      "px-3 py-2.5 text-left text-xs font-semibold uppercase",
                      ["total_qty", "value"].includes(k) && "text-right",
                    )}
                  >
                    {k ? v(`col.${k}`) : ""}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr>
                  <td
                    colSpan={cfg.cols.length + 8}
                    className="px-3 py-10 text-center text-foreground-muted"
                  >
                    {v("list.empty")}
                  </td>
                </tr>
              ) : null}
              {rows.map((r) => (
                <tr key={r.doc_no} className="border-t border-border">
                  <td className="px-3 py-2.5 font-semibold whitespace-nowrap">
                    {r.doc_no}
                  </td>
                  <td className="px-3 py-2.5 whitespace-nowrap">
                    {day(r.date)}
                  </td>
                  {cfg.cols.map((k) => (
                    <td key={k} className="px-3 py-2.5">
                      {k === "type"
                        ? r.type
                          ? v(`adjust.${r.type}`)
                          : "—"
                        : dash(r[k === "from_store" ? "store" : k])}
                    </td>
                  ))}
                  <td className="px-3 py-2.5">{dash(r.items)}</td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(r.total_qty)}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums">
                    {num(r.value)}
                  </td>
                  <td className="px-3 py-2.5">
                    <Pill tone={r.status}>{v(`status.${r.status}`)}</Pill>
                  </td>
                  <td className="px-3 py-2.5">{dash(r.user)}</td>
                  <td className="px-3 py-2.5">
                    <div className="flex justify-end gap-1">
                      <Button
                        size="icon"
                        variant="outline"
                        className="size-8"
                        onClick={() => open(r.doc_no, setViewing)}
                        aria-label={`${v("btn.view")} ${r.doc_no}`}
                      >
                        <Eye className="size-4" aria-hidden />
                      </Button>
                      <Button
                        size="icon"
                        variant="outline"
                        className="size-8"
                        onClick={() => open(r.doc_no, print)}
                        aria-label={`${v("btn.print")} ${r.doc_no}`}
                      >
                        <Printer className="size-4" aria-hidden />
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      {viewing ? (
        <Modal
          open
          onOpenChange={(o) => !o && (setViewing(null), setReason(null))}
          title={`${v(`type.${viewing.doc_type}`)} ${viewing.doc_no}`}
          className="max-w-3xl"
          footer={
            <>
              {canCancel && viewing.status === "POSTED" ? (
                <Button
                  variant="outline"
                  className="border-danger text-danger"
                  onClick={() => (setErrors({}), setReason(""))}
                >
                  <Ban className="size-4" aria-hidden />
                  {v("btn.cancel_reverse")}
                </Button>
              ) : null}
              <Button variant="outline" onClick={() => print(viewing)}>
                <Printer className="size-4" aria-hidden />
                {v("btn.print")}
              </Button>
              <Button variant="outline" onClick={() => setViewing(null)}>
                {v("btn.close")}
              </Button>
            </>
          }
        >
          <DocumentView doc={viewing} v={v} />
        </Modal>
      ) : null}

      {viewing && reason !== null ? (
        <Modal
          open
          onOpenChange={(o) => !o && setReason(null)}
          title={v("cancel.title", { no: viewing.doc_no })}
          description={v("cancel.body")}
          footer={
            <>
              <Button
                variant="outline"
                onClick={() => setReason(null)}
                disabled={pending}
              >
                {v("btn.cancel")}
              </Button>
              <Button
                variant="danger"
                onClick={cancelDoc}
                loading={pending}
                disabled={!reason.trim()}
              >
                {v("btn.confirm_cancel")}
              </Button>
            </>
          }
        >
          <Field
            label={v("cancel.reason")}
            required
            error={err("reason") ?? err("document")}
          >
            <Textarea
              rows={3}
              maxLength={2000}
              value={reason}
              onChange={(ev) => setReason(ev.target.value)}
              aria-label={v("cancel.reason")}
            />
          </Field>
        </Modal>
      ) : null}
    </div>
  );
}

/** The facts a document's view and voucher show, in Brickland's order. */
function viewFacts(d, v) {
  const by = (who, when) => (who ? `${who} · ${stamp(when)}` : null);
  const base = {
    OPENING: [["date"], ["store"], ["reference", "reference_no"], ["remarks"]],
    RECEIVE: [
      ["date"],
      ["store"],
      ["supplier"],
      ["challan", "challan_no"],
      ["reference", "reference_no"],
      ["remarks"],
    ],
    ISSUE: [
      ["date"],
      ["store"],
      ["department"],
      ["receiver", "person"],
      ["requisition"],
      ["reference", "reference_no"],
      ["remarks"],
    ],
    RETURN: [
      ["date"],
      ["store"],
      ["department"],
      ["against"],
      ["returned_by", "person"],
      ["reference", "reference_no"],
      ["remarks"],
    ],
    ADJUST: [
      ["date"],
      ["store"],
      ["type", "adjustment_type"],
      ["reference", "reference_no"],
      ["reason", "remarks"],
    ],
    TRANSFER: [
      ["date"],
      ["from_store", "store"],
      ["to_store"],
      ["reference", "reference_no"],
      ["remarks"],
    ],
  }[d.doc_type];
  return [
    ...base.map(([label, key = label]) => [
      v(`view.${label}`),
      key === "date"
        ? day(d.date)
        : key === "adjustment_type"
          ? d.adjustment_type
            ? v(`adjust.${d.adjustment_type}`)
            : null
          : d[key],
    ]),
    [v("view.created"), by(d.created_by, d.created_at)],
    ...(d.status === "CANCELLED"
      ? [
          [v("view.cancelled"), by(d.cancelled_by, d.cancelled_at)],
          [v("view.cancel_reason"), d.cancel_reason],
        ]
      : []),
  ];
}

function DocumentView({ doc, v }) {
  return (
    <div className="flex flex-col gap-4">
      <div className="flex gap-2">
        <Pill>{v(`badge.${doc.doc_type}`)}</Pill>
        <Pill tone={doc.status}>{v(`status.${doc.status}`)}</Pill>
      </div>
      <dl className="grid grid-cols-[8.5rem_1fr] gap-x-4 gap-y-1.5 text-sm">
        {viewFacts(doc, v).map(([k, val]) => (
          <div key={k} className="contents">
            <dt className="text-foreground-muted">{k}</dt>
            <dd className="font-semibold text-foreground">{dash(val)}</dd>
          </div>
        ))}
      </dl>
      <div className="overflow-x-auto rounded-lg border border-border">
        <table className="w-full min-w-[640px] text-sm">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {[
                "sl",
                "item_code",
                "item_name",
                "unit",
                "qty",
                "price",
                "value",
                "remarks",
              ].map((k) => (
                <th
                  key={k}
                  className={cn(
                    "px-3 py-2 text-left text-xs font-semibold uppercase",
                    ["qty", "price", "value"].includes(k) && "text-right",
                  )}
                >
                  {v(`view.col.${k}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {doc.lines.map((l) => (
              <tr key={l.sl} className="border-t border-border">
                <td className="px-3 py-2">{l.sl}</td>
                <td className="px-3 py-2">{l.item_code}</td>
                <td className="px-3 py-2 font-semibold">{l.item_name}</td>
                <td className="px-3 py-2">{l.unit}</td>
                <td className="px-3 py-2 text-right tabular-nums">
                  {num(doc.doc_type === "ADJUST" ? l.movement_qty : l.quantity)}
                </td>
                <td className="px-3 py-2 text-right tabular-nums">
                  {num(l.unit_price)}
                </td>
                <td className="px-3 py-2 text-right tabular-nums">
                  {num(l.value)}
                </td>
                <td className="px-3 py-2">{dash(l.remarks)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="text-right text-sm text-foreground">
        {v("view.total", {
          qty: num(doc.total_qty),
          value: money(doc.total_value),
        })}
      </p>
    </div>
  );
}

const filled = (f) =>
  Object.fromEntries(Object.entries(f).filter(([, x]) => x));
const query = (f) =>
  Object.keys(f).length ? `?${new URLSearchParams(f)}` : "";

function ListFilters({ cfg, options, pending, onSearch, v }) {
  const [f, setF] = useState(blankFilters);
  const set = (k) => (x) => setF((all) => ({ ...all, [k]: x ?? "" }));
  const select = (k, label, all, list) => (
    <Field key={k} label={label}>
      <Select
        value={f[k]}
        onValueChange={set(k)}
        options={[{ value: "", label: all }, ...list]}
        placeholder={all}
        aria-label={label}
      />
    </Field>
  );
  return (
    <form
      className={cn(card, "grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6")}
      onSubmit={(ev) => {
        ev.preventDefault();
        onSearch(f);
      }}
    >
      <Field label={v("filter.search")}>
        <Input
          value={f.q}
          onChange={(ev) => set("q")(ev.target.value)}
          placeholder={v("filter.search_ph")}
          aria-label={v("filter.search")}
        />
      </Field>
      {["from", "to"].map((k) => (
        <Field key={k} label={v(`filter.${k}`)}>
          <Input
            type="date"
            value={f[k]}
            onChange={(ev) => set(k)(ev.target.value)}
            aria-label={v(`filter.${k}`)}
          />
        </Field>
      ))}
      {select(
        "store",
        v("filter.store"),
        v("filter.all_stores"),
        options.store_filter,
      )}
      {select(
        "item",
        v("filter.item"),
        v("filter.all_items"),
        options.items.map((i) => ({ value: i.value, label: i.label })),
      )}
      {cfg.filter === "supplier"
        ? select(
            "supplier",
            v("filter.supplier"),
            v("filter.all_suppliers"),
            options.suppliers,
          )
        : null}
      {cfg.filter === "department"
        ? select(
            "department",
            v("filter.department"),
            v("filter.all_departments"),
            options.departments,
          )
        : null}
      {select("status", v("filter.status"), v("filter.any_status"), [
        { value: "POSTED", label: v("status.POSTED") },
        { value: "CANCELLED", label: v("status.CANCELLED") },
      ])}
      <div className="flex items-end gap-2">
        <Button type="submit" loading={pending}>
          <Search className="size-4" aria-hidden />
          {v("btn.search")}
        </Button>
        <Button
          type="button"
          variant="outline"
          onClick={() => {
            setF(blankFilters);
            onSearch(blankFilters);
          }}
        >
          <RotateCcw className="size-4" aria-hidden />
          {v("btn.reset")}
        </Button>
      </div>
    </form>
  );
}

export { StoreDocumentScreen };
