"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Ban,
  Lock,
  Pencil,
  Plus,
  RotateCcw,
  Save,
  Search,
  Send,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
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
  moneyFmt,
  dayFmt,
  todayIso,
  messagesOf,
  key,
  useYarn,
  card,
  th,
  Field,
  StatusChip,
  NoLines,
  YARN_HEADS,
  YarnCells,
  QtyInput,
  DateInput,
  LineButtons,
  yarnPayload,
  orNull,
} from "./yarn-common";

/*
 * Knit Production → Yarn Procurement (AnnotechERP 15/02) → Yarn Purchase
 * Order: the PO register (search, Place, Close, Cancel) and the order desk —
 * the finalized comparisons beside it, one row per spinner (Order This
 * brings that spinner's lines across at the agreed price), the order's
 * terms, and its yarn lines (amount = quantity × rate, received kept by the
 * store). Nothing reloads.
 */

const ENTRY = "/knit-production/yarn-purchase-order?tab=entry";
const PAGE = 10;

function months() {
  const out = [];
  const now = new Date();
  for (let i = 0; i < 18; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    out.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`);
  }
  return out;
}

function PoList({ options, initialRows, canManage, act }) {
  const y = useYarn();
  const toastManager = useToastManager();
  const blank = {
    yr: "",
    no: "",
    month: "",
    party: "",
    inco: "",
    payment: "",
    status: "",
    method: "",
  };
  const [filters, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((f) => ({ ...f, [k]: v ?? "" }));

  function search(f = filters) {
    startTransition(async () => {
      const res = await act("orders", "list", { query: f });
      if (res?.status === "success") {
        setRows(res.data);
        setError(null);
      } else setError(messagesOf(res));
    });
  }
  function run(no, action) {
    startTransition(async () => {
      const res = await act("orders", action, { no });
      if (res?.status !== "success") return setError(messagesOf(res));
      toastManager.add({ title: y(`done.${action}`, { no }), type: "success" });
      search();
    });
  }

  return (
    <section className={card}>
      <div className="px-5 pt-5">
        <h2 className="text-base font-semibold text-foreground">
          {y("po.list_title")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {y("po.list_hint")}
        </p>
      </div>
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-4">
        <Field label={y("po.yr")} hint={y("po.yr_hint")}>
          <Input
            value={filters.yr}
            onChange={(e) => setF("yr", e.target.value)}
            placeholder={y("yr.no")}
            aria-label={y("po.yr")}
          />
        </Field>
        <Field label={y("po.lc.no")}>
          <Input
            value={filters.no}
            onChange={(e) => setF("no", e.target.value)}
            placeholder={y("search_ph")}
            aria-label={y("po.lc.no")}
          />
        </Field>
        <Field label={y("po.month")}>
          <PSelect
            value={filters.month}
            onValueChange={(v) => setF("month", v)}
            options={[
              { value: "", label: y("po.month_ph") },
              ...months().map((m) => ({ value: m, label: m })),
            ]}
            aria-label={y("po.month")}
          />
        </Field>
        <Field label={y("po.party")}>
          <PSelect
            value={filters.party}
            onValueChange={(v) => setF("party", v)}
            options={[{ value: "", label: y("select") }, ...options.suppliers]}
            aria-label={y("po.party")}
          />
        </Field>
        <Field label={y("po.inco")}>
          <PSelect
            value={filters.inco}
            onValueChange={(v) => setF("inco", v)}
            options={[
              { value: "", label: y("po.inco_ph") },
              ...options.inco_terms.map((v) => ({
                value: v,
                label: `${v} — ${v}`,
              })),
            ]}
            aria-label={y("po.inco")}
          />
        </Field>
        <Field label={y("po.payment")}>
          <PSelect
            value={filters.payment}
            onValueChange={(v) => setF("payment", v)}
            options={[
              { value: "", label: y("po.payment_ph") },
              ...options.payment_terms.map((v) => ({
                value: v,
                label: `${v} — ${v}`,
              })),
            ]}
            aria-label={y("po.payment")}
          />
        </Field>
        <Field label={y("status")}>
          <PSelect
            value={filters.status}
            onValueChange={(v) => setF("status", v)}
            options={[
              { value: "", label: y("any") },
              ...options.statuses.map((s) => ({
                value: s,
                label: y(`po_status.${s}`),
              })),
            ]}
            aria-label={y("status")}
          />
        </Field>
        <Field label={y("po.method")}>
          <PSelect
            value={filters.method}
            onValueChange={(v) => setF("method", v)}
            options={[
              { value: "", label: y("any") },
              ...options.methods.map((m) => ({
                value: m,
                label: y(`method.${m}`),
              })),
            ]}
            aria-label={y("po.method")}
          />
        </Field>
      </div>
      <div className="flex flex-wrap gap-2 px-5 pt-4 pb-5">
        <Button onClick={() => search()} loading={pending}>
          <Search /> {y("show")}
        </Button>
        {canManage ? (
          <Link href={ENTRY} className={buttonVariants({ variant: "outline" })}>
            <Plus /> {y("add_new")}
          </Link>
        ) : null}
        <Button
          variant="outline"
          onClick={() => (setFilters(blank), search(blank))}
          disabled={pending}
        >
          <RotateCcw /> {y("reset")}
        </Button>
      </div>
      {error ? (
        <div className="px-5 pb-4">
          <Alert variant="danger">{error.join(" · ")}</Alert>
        </div>
      ) : null}
      {rows.length === 0 ? (
        <NoLines title={y("po.empty")} hint={y("nothing")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[1400px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "no",
                  "date",
                  "spinner",
                  "yr",
                  "cs",
                  "source",
                  "method",
                  "inco",
                  "payment",
                  "currency",
                  "delivery",
                  "qty",
                  "value",
                  "received",
                  "status",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      th,
                      ["qty", "value", "received"].includes(k) && "text-right",
                    )}
                  >
                    {y(`po.lc.${k}`)}
                  </th>
                ))}
                <th className={th}>{y("actions")}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.po_no} className="border-t border-border">
                  <td className="px-2 py-2 font-medium whitespace-nowrap">
                    <Link
                      href={`${ENTRY}&ref=${encodeURIComponent(r.po_no)}`}
                      className="text-brand hover:underline"
                    >
                      {r.po_no}
                    </Link>
                  </td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {dayFmt(r.po_date)}
                  </td>
                  <td className="px-2 py-2">{r.spinner ?? "—"}</td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {r.requisition_no ?? "—"}
                  </td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {r.cs_no ?? "—"}
                  </td>
                  <td className="px-2 py-2">{y(`source.${r.source}`)}</td>
                  <td className="px-2 py-2">{y(`method.${r.method}`)}</td>
                  <td className="px-2 py-2">{r.inco_term ?? "—"}</td>
                  <td className="px-2 py-2">{r.payment_term ?? "—"}</td>
                  <td className="px-2 py-2">{r.currency ?? "—"}</td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {dayFmt(r.delivery_date)}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(r.qty)}
                  </td>
                  <td className="px-2 py-2 text-right font-semibold tabular-nums">
                    {moneyFmt.format(r.value)}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(r.received)}
                  </td>
                  <td className="px-2 py-2">
                    <StatusChip
                      status={r.status}
                      label={y(`po_status.${r.status}`)}
                    />
                  </td>
                  <td className="px-2 py-1.5">
                    {canManage ? (
                      <div className="flex gap-1">
                        {r.status === "DRAFT" ? (
                          <>
                            <Link
                              href={`${ENTRY}&ref=${encodeURIComponent(r.po_no)}`}
                              className={buttonVariants({
                                variant: "outline",
                                size: "sm",
                              })}
                            >
                              <Pencil /> {y("edit")}
                            </Link>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => run(r.po_no, "place")}
                              disabled={pending}
                            >
                              <Send /> {y("btn.place")}
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              className="text-danger"
                              onClick={() => run(r.po_no, "cancel")}
                              disabled={pending}
                            >
                              <Ban /> {y("btn.cancel")}
                            </Button>
                          </>
                        ) : null}
                        {["PLACED", "PART_RECEIVED", "RECEIVED"].includes(
                          r.status,
                        ) ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => run(r.po_no, "close")}
                            disabled={pending}
                          >
                            <Lock /> {y("btn.close")}
                          </Button>
                        ) : null}
                      </div>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

const HEAD_KEYS = [
  "po_date",
  "supplier_id",
  "store_id",
  "cs_no",
  "requisition_no",
  "buyer_id",
  "order_id",
  "source",
  "method",
  "inco_term",
  "payment_term",
  "ship_mode",
  "currency_id",
  "conv_rate",
  "delivery_date",
  "shipment_date",
  "remarks",
];
const blankHead = (today) => ({
  ...Object.fromEntries(HEAD_KEYS.map((k) => [k, ""])),
  po_date: today,
});
const asLine = (l = {}) => ({
  key: key(),
  composition_id: str(l.composition_id),
  yarn_count_id: str(l.yarn_count_id),
  colour: str(l.colour),
  brand: str(l.brand),
  lot_no: str(l.lot_no),
  uom: str(l.uom),
  qty: str(l.qty),
  rate: str(l.rate),
  received_qty: Number(l.received_qty ?? 0),
  knit_start_date: str(l.knit_start_date),
  ship_date: str(l.ship_date),
  remarks: str(l.remarks),
});

function PoEntry({ options, initial, canManage, act }) {
  const y = useYarn();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() =>
    initial
      ? Object.fromEntries(HEAD_KEYS.map((k) => [k, str(initial[k])]))
      : blankHead(today),
  );
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [spinner, setSpinner] = useState("");
  const [page, setPage] = useState(0);
  const [errors, setErrors] = useState(null);
  const [confirm, setConfirm] = useState(false);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  const shown = options.finalized.filter(
    (s) => !spinner || s.supplier_id === spinner,
  );
  const pages = Math.max(1, Math.ceil(shown.length / PAGE));
  const amount = (l) => num(l.qty) * num(l.rate);
  const pick = (list, ph = y("select")) => [{ value: "", label: ph }, ...list];

  function take(data) {
    setDoc(data);
    setHead(Object.fromEntries(HEAD_KEYS.map((k) => [k, str(data[k])])));
    setLines(data.lines.map(asLine));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      `?tab=entry&ref=${encodeURIComponent(data.po_no)}`,
    );
  }
  function clear() {
    setDoc(null);
    setHead(blankHead(today));
    setLines([]);
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }
  function load(no) {
    if (!no) return clear();
    startTransition(async () => {
      const res = await act("orders", "show", { no });
      if (res?.status === "success") take(res.data);
      else setErrors(messagesOf(res));
    });
  }
  function bring(query) {
    startTransition(async () => {
      const res = await act("orders", "source", { query });
      if (res?.status !== "success") return setErrors(messagesOf(res));
      const d = res.data;
      const currency = options.currencies.find(
        (c) => c.value === d.currency_id,
      );
      setH({
        cs_no: str(d.cs_no),
        requisition_no: str(d.requisition_no),
        buyer_id: str(d.buyer_id),
        order_id: str(d.order_id),
        ...(d.supplier_id ? { supplier_id: d.supplier_id } : {}),
        ...(d.store_id ? { store_id: d.store_id } : {}),
        ...(d.source ? { source: d.source } : {}),
        ...(d.currency_id
          ? {
              currency_id: d.currency_id,
              conv_rate: currency?.rate ? String(currency.rate) : "",
            }
          : {}),
      });
      setLines(d.lines.map(asLine));
      setErrors(null);
    });
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          Object.entries(head).map(([k, v]) => [k, v === "" ? null : v]),
        ),
        conv_rate: orNull(head.conv_rate),
        lines: lines.map((l) => ({
          ...yarnPayload(l),
          lot_no: l.lot_no || null,
          qty: orNull(l.qty),
          rate: orNull(l.rate),
        })),
      };
      const res = await act("orders", "save", { no: doc?.po_no, body });
      if (res?.status === "success") {
        take(res.data);
        toastManager.add({
          title: y(doc ? "updated" : "saved", { no: res.data.po_no }),
          type: "success",
        });
      } else setErrors(messagesOf(res));
    });
  }
  function remove() {
    startTransition(async () => {
      const res = await act("orders", "remove", { no: doc.po_no });
      setConfirm(false);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      toastManager.add({
        title: y("done.delete", { no: doc.po_no }),
        type: "success",
      });
      clear();
    });
  }

  return (
    <div className="grid gap-4 2xl:grid-cols-[26rem_1fr]">
      <section className={cn(card, "self-start p-4")}>
        <h2 className="text-base font-semibold text-foreground">
          {y("po.search_cs")}
        </h2>
        <div className="mt-3">
          <PSelect
            value={spinner}
            onValueChange={(v) => (setSpinner(v ?? ""), setPage(0))}
            options={pick(options.suppliers, y("po.spinner_ph"))}
            aria-label={y("po.search_cs")}
          />
        </div>
        <div className="mt-3 overflow-x-auto rounded-lg border border-border">
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["cs", "yr", "spinner", "items", "qty", "value"].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      th,
                      ["items", "qty", "value"].includes(k) && "text-right",
                    )}
                  >
                    {y(`po.sc.${k}`)}
                  </th>
                ))}
                <th className={th} />
              </tr>
            </thead>
            <tbody>
              {shown.slice(page * PAGE, page * PAGE + PAGE).map((s) => (
                <tr
                  key={`${s.cs_no}-${s.supplier_id}`}
                  className={cn(
                    "border-t border-border",
                    head.cs_no === s.cs_no &&
                      head.supplier_id === s.supplier_id &&
                      "bg-brand-subtle",
                  )}
                >
                  <td className="px-2 py-2 font-medium whitespace-nowrap">
                    {s.cs_no}
                  </td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {s.requisition_no ?? "—"}
                  </td>
                  <td className="px-2 py-2">{s.spinner}</td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {s.items}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(s.qty)}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {moneyFmt.format(s.value)}
                  </td>
                  <td className="px-2 py-1.5">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() =>
                        bring({ cs: s.cs_no, supplier: s.supplier_id })
                      }
                      disabled={!editable || pending}
                    >
                      {y("po.order_this")}
                    </Button>
                  </td>
                </tr>
              ))}
              {shown.length === 0 ? (
                <tr>
                  <td
                    colSpan={7}
                    className="px-3 py-6 text-center text-foreground-muted"
                  >
                    {y("cs.nothing_left")}
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
            aria-label={y("prev")}
          >
            ‹
          </Button>
          {page + 1} / {pages}
          <Button
            variant="outline"
            size="sm"
            onClick={() => setPage((p) => Math.min(pages - 1, p + 1))}
            disabled={page >= pages - 1}
            aria-label={y("next")}
          >
            ›
          </Button>
        </div>
      </section>

      <div className="flex min-w-0 flex-col gap-4">
        <section className={cn(card, "p-5")}>
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {y("tabs.po_entry")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {y("po.entry_hint")}
              </p>
            </div>
            {doc ? (
              <StatusChip
                status={doc.status}
                label={
                  doc.editable
                    ? y("editing", { no: doc.po_no })
                    : y("read_only", { status: y(`po_status.${doc.status}`) })
                }
              />
            ) : null}
          </div>
          <Field
            label={y("po.existing")}
            hint={y("yr.existing_hint")}
            className="mt-4"
          >
            <PSelect
              value={doc?.po_no ?? ""}
              onValueChange={(v) => load(v ?? "")}
              options={[
                { value: "", label: y("po.existing_new") },
                ...options.orders_list,
              ]}
              aria-label={y("po.existing")}
            />
          </Field>
          <div className="mt-4 grid gap-4 md:grid-cols-3">
            <Field label={y("po.no")} hint={y("po.no_hint")}>
              <Input
                value={doc?.po_no ?? ""}
                placeholder={y("yr.no_ph")}
                readOnly
                className="bg-surface-muted"
                aria-label={y("po.no")}
              />
            </Field>
            <Field label={y("po.date")} required>
              <Input
                type="date"
                value={head.po_date}
                onChange={(e) => setH({ po_date: e.target.value })}
                disabled={!editable}
                aria-label={y("po.date")}
              />
            </Field>
            <Field label={y("company")} required>
              <PSelect
                value={options.company.value}
                options={[options.company]}
                disabled
                aria-label={y("company")}
              />
            </Field>
            <Field label={y("po.supplier")} required>
              <PSelect
                value={head.supplier_id}
                onValueChange={(v) => setH({ supplier_id: v ?? "" })}
                options={pick(options.suppliers)}
                disabled={!editable}
                aria-label={y("po.supplier")}
              />
            </Field>
            <Field label={y("store")} required hint={y("po.store_hint")}>
              <PSelect
                value={head.store_id}
                onValueChange={(v) => setH({ store_id: v ?? "" })}
                options={pick(options.stores, y("store_ph"))}
                disabled={!editable}
                aria-label={y("store")}
              />
            </Field>
            <Field label={y("po.cs")} hint={y("po.cs_hint")}>
              <PSelect
                value={head.cs_no}
                onValueChange={(v) => setH({ cs_no: v ?? "" })}
                options={pick(options.comparisons)}
                disabled
                aria-label={y("po.cs")}
              />
            </Field>
            <Field label={y("po.yr")} hint={y("po.yr_hint2")}>
              <PSelect
                value={head.requisition_no}
                onValueChange={(v) =>
                  v ? bring({ requisition: v }) : setH({ requisition_no: "" })
                }
                options={pick(options.requisitions)}
                disabled={!editable || Boolean(head.cs_no)}
                aria-label={y("po.yr")}
              />
            </Field>
            <Field label={y("buyer")}>
              <PSelect
                value={head.buyer_id}
                onValueChange={(v) => setH({ buyer_id: v ?? "" })}
                options={pick(options.buyers)}
                disabled={!editable}
                aria-label={y("buyer")}
              />
            </Field>
            <Field label={y("job")}>
              <PSelect
                value={head.order_id}
                onValueChange={(v) => setH({ order_id: v ?? "" })}
                options={pick(options.orders)}
                disabled={!editable}
                aria-label={y("job")}
              />
            </Field>
            <Field label={y("po.source")} required>
              <PSelect
                value={head.source}
                onValueChange={(v) => setH({ source: v ?? "" })}
                options={pick(
                  options.sources.map((s) => ({
                    value: s,
                    label: y(`source.${s}`),
                  })),
                )}
                disabled={!editable}
                aria-label={y("po.source")}
              />
            </Field>
            <Field label={y("po.method")} required>
              <PSelect
                value={head.method}
                onValueChange={(v) => setH({ method: v ?? "" })}
                options={pick(
                  options.methods.map((m) => ({
                    value: m,
                    label: y(`method.${m}`),
                  })),
                )}
                disabled={!editable}
                aria-label={y("po.method")}
              />
            </Field>
            <Field label={y("po.inco")}>
              <PSelect
                value={head.inco_term}
                onValueChange={(v) => setH({ inco_term: v ?? "" })}
                options={pick(
                  options.inco_terms.map((v) => ({
                    value: v,
                    label: `${v} — ${v}`,
                  })),
                  y("po.inco_ph"),
                )}
                disabled={!editable}
                aria-label={y("po.inco")}
              />
            </Field>
            <Field label={y("po.payment")}>
              <PSelect
                value={head.payment_term}
                onValueChange={(v) => setH({ payment_term: v ?? "" })}
                options={pick(
                  options.payment_terms.map((v) => ({
                    value: v,
                    label: `${v} — ${v}`,
                  })),
                  y("po.payment_ph"),
                )}
                disabled={!editable}
                aria-label={y("po.payment")}
              />
            </Field>
            <Field label={y("po.ship_mode")}>
              <PSelect
                value={head.ship_mode}
                onValueChange={(v) => setH({ ship_mode: v ?? "" })}
                options={pick(
                  options.ship_modes.map((v) => ({
                    value: v,
                    label: `${v} — ${v}`,
                  })),
                )}
                disabled={!editable}
                aria-label={y("po.ship_mode")}
              />
            </Field>
            <Field label={y("po.currency")} required>
              <PSelect
                value={head.currency_id}
                onValueChange={(v) =>
                  setH({
                    currency_id: v ?? "",
                    conv_rate:
                      head.conv_rate ||
                      str(options.currencies.find((c) => c.value === v)?.rate),
                  })
                }
                options={pick(options.currencies)}
                disabled={!editable}
                aria-label={y("po.currency")}
              />
            </Field>
            <Field label={y("po.conv")} hint={y("po.conv_hint")}>
              <Input
                type="number"
                min="0"
                step="any"
                value={head.conv_rate}
                onChange={(e) => setH({ conv_rate: e.target.value })}
                disabled={!editable}
                aria-label={y("po.conv")}
              />
            </Field>
            <Field label={y("po.delivery")}>
              <Input
                type="date"
                value={head.delivery_date}
                onChange={(e) => setH({ delivery_date: e.target.value })}
                disabled={!editable}
                aria-label={y("po.delivery")}
              />
            </Field>
            <Field label={y("po.shipment")}>
              <Input
                type="date"
                value={head.shipment_date}
                onChange={(e) => setH({ shipment_date: e.target.value })}
                disabled={!editable}
                aria-label={y("po.shipment")}
              />
            </Field>
            <Field label={y("remarks")} className="md:col-span-3">
              <Textarea
                rows={2}
                value={head.remarks}
                onChange={(e) => setH({ remarks: e.target.value })}
                maxLength={2000}
                disabled={!editable}
                aria-label={y("remarks")}
              />
            </Field>
          </div>
        </section>

        <section className={cn(card, "overflow-hidden")}>
          <div className="flex items-start justify-between gap-3 px-5 py-4">
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {y("col.yarn")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">
                {y("po.lines_hint")}
              </p>
            </div>
            {editable ? (
              <Button
                variant="outline"
                size="sm"
                onClick={() =>
                  setLines((all) => [...all, asLine({ uom: "kg" })])
                }
              >
                <Plus /> {y("po.add")}
              </Button>
            ) : null}
          </div>
          {lines.length === 0 ? (
            <NoLines title={y("no_lines")} hint={y("po.no_lines_hint")} />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[1800px] text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    <th
                      colSpan={6}
                      className={cn(th, "border-b border-white/20 text-center")}
                    >
                      {y("col.yarn")}
                    </th>
                    <th
                      colSpan={doc ? 4 : 3}
                      className={cn(th, "border-b border-white/20 text-center")}
                    >
                      {y("col.order_grp")}
                    </th>
                    <th
                      colSpan={2}
                      className={cn(th, "border-b border-white/20 text-center")}
                    >
                      {y("col.schedule")}
                    </th>
                    <th colSpan={3} />
                  </tr>
                  <tr>
                    {[
                      ...YARN_HEADS.slice(0, 4),
                      "lot",
                      "uom",
                      "qty",
                      "rate",
                      "amount",
                      ...(doc ? ["received"] : []),
                      "knit_start",
                      "ship",
                      "remarks",
                      "copy",
                      "remove",
                    ].map((k) => (
                      <th
                        key={k}
                        className={cn(
                          th,
                          ["qty", "rate", "amount", "received"].includes(k) &&
                            "text-right",
                        )}
                      >
                        {y(`col.${k}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {lines.map((l, i) => (
                    <tr key={l.key} className="border-t border-border">
                      <YarnCells
                        line={l}
                        set={(p) => setLine(l.key, p)}
                        options={options}
                        disabled={!editable}
                        n={i + 1}
                        lot
                      />
                      <td className="px-2 py-1.5">
                        <QtyInput
                          value={l.qty}
                          onChange={(v) => setLine(l.key, { qty: v })}
                          disabled={!editable}
                          label={`${y("col.qty")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <QtyInput
                          value={l.rate}
                          onChange={(v) => setLine(l.key, { rate: v })}
                          disabled={!editable}
                          label={`${y("col.rate")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5 text-right font-semibold tabular-nums">
                        {moneyFmt.format(amount(l))}
                      </td>
                      {doc ? (
                        <td className="px-2 py-1.5 text-right tabular-nums">
                          {qtyFmt.format(l.received_qty)}
                        </td>
                      ) : null}
                      <td className="px-2 py-1.5">
                        <DateInput
                          value={l.knit_start_date}
                          onChange={(v) =>
                            setLine(l.key, { knit_start_date: v })
                          }
                          disabled={!editable}
                          label={`${y("col.knit_start")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <DateInput
                          value={l.ship_date}
                          onChange={(v) => setLine(l.key, { ship_date: v })}
                          disabled={!editable}
                          label={`${y("col.ship")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          value={l.remarks}
                          onChange={(e) =>
                            setLine(l.key, { remarks: e.target.value })
                          }
                          maxLength={300}
                          className="h-8 w-40"
                          disabled={!editable}
                          aria-label={`${y("col.remarks")} ${i + 1}`}
                        />
                      </td>
                      <LineButtons
                        n={i + 1}
                        disabled={!editable}
                        onCopy={() =>
                          setLines((all) => [
                            ...all.slice(0, i + 1),
                            { ...l, key: key(), received_qty: 0 },
                            ...all.slice(i + 1),
                          ])
                        }
                        onRemove={() =>
                          setLines((all) => all.filter((x) => x.key !== l.key))
                        }
                      />
                    </tr>
                  ))}
                </tbody>
                <tfoot className="bg-surface-muted font-semibold">
                  <tr className="border-t-2 border-border">
                    <td colSpan={6} className="px-2 py-2">
                      {y("total_row")}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(lines.reduce((s, l) => s + num(l.qty), 0))}
                    </td>
                    <td />
                    <td className="px-2 py-2 text-right tabular-nums">
                      {moneyFmt.format(
                        lines.reduce((s, l) => s + amount(l), 0),
                      )}
                    </td>
                    <td colSpan={doc ? 6 : 5} />
                  </tr>
                </tfoot>
              </table>
            </div>
          )}
        </section>

        {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
        <section className={cn(card, "flex flex-col gap-3 p-5")}>
          <p className="text-[13px] text-foreground-muted">{y("po.foot")}</p>
          <div className="flex flex-wrap gap-2">
            <Link
              href="/knit-production/yarn-purchase-order"
              className={buttonVariants({ variant: "outline" })}
            >
              <ArrowLeft /> {y("back")}
            </Link>
            <Button variant="outline" onClick={clear} disabled={pending}>
              <RotateCcw /> {y("clear")}
            </Button>
            {doc && editable ? (
              <Button
                variant="outline"
                className="text-danger"
                onClick={() => setConfirm(true)}
                disabled={pending}
              >
                <Trash2 /> {y("delete")}
              </Button>
            ) : null}
            {editable ? (
              <Button onClick={save} loading={pending}>
                <Save /> {y("po.save")}
              </Button>
            ) : null}
          </div>
        </section>
      </div>
      <ConfirmDialog
        open={confirm}
        onOpenChange={setConfirm}
        title={y("delete")}
        description={doc ? y("confirm_delete", { no: doc.po_no }) : ""}
        confirmLabel={y("delete")}
        cancelLabel={y("cancel")}
        destructive
        onConfirm={remove}
        loading={pending}
      />
    </div>
  );
}

export { PoList, PoEntry };
