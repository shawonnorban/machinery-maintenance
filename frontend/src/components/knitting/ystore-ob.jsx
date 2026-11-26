"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import {
  ArrowLeft,
  Ban,
  Download,
  Eye,
  FilterX,
  Lock,
  Plus,
  RotateCcw,
  Save,
  Search,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
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
  useYs,
  card,
  th,
  Field,
  StatusChip,
  NoLines,
  QtyInput,
  DateInput,
  LineButtons,
  orNull,
} from "./yarn-common";

/*
 * Knit Production → Yarn Store (AnnotechERP 15/03): Yarn Store Opening
 * Balance — the register of declared stock lines (Finalize posts a balance
 * to the ledger) and its entry desk (Total Qty = Pack Qty × Qty/Pack, TTL
 * Value = Total Qty × Cost Price) — and Yarn Stock Adjustment, the list of
 * physical-count corrections and the desk that books one against the
 * store's lots. Nothing reloads; a document travels by its number.
 */

const OB = "/knit-production/opening-balance";
const ADJ = "/knit-production/yarn-adjustment";

function ObList({ options, initialRows, canManage, act }) {
  const y = useYarn();
  const s = useYs();
  const toastManager = useToastManager();
  const blank = {
    store: "",
    buyer: "",
    composition: "",
    source: "",
    lot: "",
    buffer: "",
    finalized: "",
  };
  const [filters, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((f) => ({ ...f, [k]: v ?? "" }));
  const all = (list) => [{ value: "", label: s("all") }, ...list];
  const yesNo = all(
    ["YES", "NO"].map((v) => ({
      value: v,
      label: s(v === "YES" ? "yes" : "no"),
    })),
  );

  function search(f = filters) {
    startTransition(async () => {
      const qs = new URLSearchParams(Object.entries(f).filter(([, v]) => v));
      const res = await act(`obs?${qs}`);
      if (res?.status === "success") {
        setRows(res.data);
        setError(null);
      } else setError(messagesOf(res));
    });
  }
  function finalize(no) {
    startTransition(async () => {
      const res = await act(`obs/${encodeURIComponent(no)}/finalize`, "POST");
      if (res?.status !== "success") return setError(messagesOf(res));
      toastManager.add({
        title: s("ob.done_finalize", { no }),
        type: "success",
      });
      search();
    });
  }
  const seen = new Set();

  return (
    <section className={card}>
      <div className="px-5 pt-5">
        <h2 className="text-base font-semibold text-foreground">
          {s("ob.search_title")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {s("ob.list_hint")}
        </p>
      </div>
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-4">
        <Field label={s("store")}>
          <PSelect
            value={filters.store}
            onValueChange={(v) => setF("store", v)}
            options={all(options.store_codes)}
            aria-label={s("store")}
          />
        </Field>
        <Field label={s("buyer")}>
          <PSelect
            value={filters.buyer}
            onValueChange={(v) => setF("buyer", v)}
            options={all(options.buyers)}
            aria-label={s("buyer")}
          />
        </Field>
        <Field label={s("yarn")}>
          <PSelect
            value={filters.composition}
            onValueChange={(v) => setF("composition", v)}
            options={all(options.compositions)}
            aria-label={s("yarn")}
          />
        </Field>
        <Field label={s("source")}>
          <PSelect
            value={filters.source}
            onValueChange={(v) => setF("source", v)}
            options={all(
              options.store_sources.map((o) => ({
                value: o,
                label: s(`store_source.${o}`),
              })),
            )}
            aria-label={s("source")}
          />
        </Field>
        <Field label={s("lot")}>
          <Input
            value={filters.lot}
            onChange={(e) => setF("lot", e.target.value)}
            placeholder={s("all")}
            aria-label={s("lot")}
          />
        </Field>
        <Field label={s("buffer")}>
          <PSelect
            value={filters.buffer}
            onValueChange={(v) => setF("buffer", v)}
            options={yesNo}
            aria-label={s("buffer")}
          />
        </Field>
        <Field label={s("finalized")}>
          <PSelect
            value={filters.finalized}
            onValueChange={(v) => setF("finalized", v)}
            options={yesNo}
            aria-label={s("finalized")}
          />
        </Field>
      </div>
      <div className="flex flex-wrap gap-2 px-5 pt-4 pb-5">
        <Button onClick={() => search()} loading={pending}>
          <Search /> {y("show")}
        </Button>
        {canManage ? (
          <Link
            href={`${OB}?tab=entry`}
            className={buttonVariants({ variant: "outline" })}
          >
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
        <NoLines title={s("ob.empty")} hint={s("ob.empty_hint")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[1400px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "no",
                  "date",
                  "store",
                  "buyer",
                  "source",
                  "yarn",
                  "brand",
                  "lot",
                  "qty",
                  "rate",
                  "value",
                  "state",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      th,
                      ["qty", "rate", "value"].includes(k) && "text-right",
                    )}
                  >
                    {s(`ob.lc.${k}`)}
                  </th>
                ))}
                <th className={th}>{s("actions")}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => {
                // Finalize once per balance, on its first line shown.
                const first = !seen.has(r.ob_no);
                seen.add(r.ob_no);
                return (
                  <tr
                    key={`${r.ob_no}-${i}`}
                    className="border-t border-border"
                  >
                    <td className="px-2 py-2 font-medium whitespace-nowrap">
                      <Link
                        href={`${OB}?tab=entry&ref=${encodeURIComponent(r.ob_no)}`}
                        className="text-brand hover:underline"
                      >
                        {r.ob_no}
                      </Link>
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {dayFmt(r.ob_date)}
                    </td>
                    <td className="px-2 py-2">{r.store ?? "—"}</td>
                    <td className="px-2 py-2">
                      {r.buyer ?? (r.is_buffer ? s("buffer_stock") : "—")}
                    </td>
                    <td className="px-2 py-2">
                      {s(`store_source.${r.source}`)}
                    </td>
                    <td className="px-2 py-2">{r.yarn || "—"}</td>
                    <td className="px-2 py-2">{r.brand ?? "—"}</td>
                    <td className="px-2 py-2">{r.lot_no ?? "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.total_qty)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {moneyFmt.format(r.cost_price)}
                    </td>
                    <td className="px-2 py-2 text-right font-semibold tabular-nums">
                      {moneyFmt.format(r.value)}
                    </td>
                    <td className="px-2 py-2">
                      <StatusChip
                        status={r.finalized ? "YES" : "NO"}
                        label={s(r.finalized ? "yes" : "no")}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      {canManage && !r.finalized && first ? (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => finalize(r.ob_no)}
                          disabled={pending}
                        >
                          <Lock /> {s("ob.finalize")}
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

const OB_HEAD = [
  "ob_date",
  "store_id",
  "currency_id",
  "buyer_id",
  "order_id",
  "remarks",
];
const obBlank = (today, store) => ({
  ...Object.fromEntries(OB_HEAD.map((k) => [k, ""])),
  ob_date: today,
  store_id: store ?? "",
  is_buffer: false,
});
const obLine = (l = {}) => ({
  key: key(),
  source: str(l.source),
  composition_id: str(l.composition_id),
  yarn_count_id: str(l.yarn_count_id),
  colour: str(l.colour),
  brand: str(l.brand),
  lot_no: str(l.lot_no),
  supplier_id: str(l.supplier_id),
  pack_qty: str(l.pack_qty),
  qty_per_pack: str(l.qty_per_pack),
  pack_uom: str(l.pack_uom),
  total_qty: str(l.total_qty),
  cost_price: str(l.cost_price),
  cones: str(l.cones),
  rcv_date: str(l.rcv_date),
});
// Total Qty is Pack Qty × Qty/Pack when both are given, as the server works it.
const totalOf = (l) =>
  num(l.pack_qty) && num(l.qty_per_pack)
    ? num(l.pack_qty) * num(l.qty_per_pack)
    : num(l.total_qty);

function ObEntry({ options, initial, canManage, act }) {
  const y = useYarn();
  const s = useYs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  // The yarn store, as Brickland's Receiving Store opens on.
  const defaultStore = (
    options.stores.find((x) => /yarn/i.test(x.label)) ?? options.stores[0]
  )?.value;
  const asHead = (d) => ({
    ...Object.fromEntries(OB_HEAD.map((k) => [k, str(d[k])])),
    is_buffer: Boolean(d.is_buffer),
  });
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() =>
    initial ? asHead(initial) : obBlank(today, defaultStore),
  );
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(obLine));
  const [errors, setErrors] = useState(null);
  const [confirm, setConfirm] = useState(false);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.editable);
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  const pick = (list, ph = s("select")) => [{ value: "", label: ph }, ...list];
  const uoms = options.uoms.map((u) => ({
    value: u.value.toLowerCase(),
    label: u.label.toLowerCase(),
  }));

  function take(data) {
    setDoc(data);
    setHead(asHead(data));
    setLines(data.lines.map(obLine));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      `?tab=entry&ref=${encodeURIComponent(data.ob_no)}`,
    );
  }
  function clear() {
    setDoc(null);
    setHead(obBlank(today, defaultStore));
    setLines([]);
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }
  function load(no) {
    if (!no) return clear();
    startTransition(async () => {
      const res = await act(`obs/${encodeURIComponent(no)}`);
      if (res?.status === "success") take(res.data);
      else setErrors(messagesOf(res));
    });
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          OB_HEAD.map((k) => [k, head[k] === "" ? null : head[k]]),
        ),
        is_buffer: head.is_buffer,
        lines: lines.map((l) => ({
          source: l.source || null,
          composition_id: l.composition_id || null,
          yarn_count_id: l.yarn_count_id || null,
          colour: l.colour || null,
          brand: l.brand || null,
          lot_no: l.lot_no || null,
          supplier_id: l.supplier_id || null,
          pack_qty: orNull(l.pack_qty),
          qty_per_pack: orNull(l.qty_per_pack),
          pack_uom: l.pack_uom || null,
          total_qty: totalOf(l) || null,
          cost_price: orNull(l.cost_price),
          cones: orNull(l.cones),
          rcv_date: l.rcv_date || null,
        })),
      };
      const res = await act(
        doc ? `obs/${encodeURIComponent(doc.ob_no)}` : "obs",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status === "success") {
        take(res.data);
        toastManager.add({
          title: s("ob.saved", { no: res.data.ob_no }),
          type: "success",
        });
      } else setErrors(messagesOf(res));
    });
  }
  function remove() {
    startTransition(async () => {
      const res = await act(`obs/${encodeURIComponent(doc.ob_no)}`, "DELETE");
      setConfirm(false);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      toastManager.add({
        title: y("done.delete", { no: doc.ob_no }),
        type: "success",
      });
      clear();
    });
  }

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {s("tabs.ob_entry")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {s("ob.entry_hint")}
            </p>
          </div>
          {doc ? (
            <StatusChip
              status={doc.finalized ? "YES" : "DRAFT"}
              label={
                doc.editable
                  ? y("editing", { no: doc.ob_no })
                  : y("read_only", { status: s("finalized") })
              }
            />
          ) : null}
        </div>
        <Field
          label={s("ob.existing")}
          hint={s("ob.existing_hint")}
          className="mt-4"
        >
          <PSelect
            value={doc?.ob_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={[
              { value: "", label: s("ob.existing_new") },
              ...options.obs,
            ]}
            aria-label={s("ob.existing")}
          />
        </Field>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Field label={s("company")} required>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={s("company")}
            />
          </Field>
          <Field label={s("receiving_store")} required>
            <PSelect
              value={head.store_id}
              onValueChange={(v) => setH({ store_id: v ?? "" })}
              options={pick(options.stores)}
              disabled={!editable}
              aria-label={s("receiving_store")}
            />
          </Field>
          <Field label={s("ob.date")} required>
            <Input
              type="date"
              value={head.ob_date}
              onChange={(e) => setH({ ob_date: e.target.value })}
              disabled={!editable}
              aria-label={s("ob.date")}
            />
          </Field>
          <Field label={s("currency")} required>
            <PSelect
              value={head.currency_id}
              onValueChange={(v) => setH({ currency_id: v ?? "" })}
              options={pick(options.currencies, "-- Select Currency --")}
              disabled={!editable}
              aria-label={s("currency")}
            />
          </Field>
          <Field label={s("buyer")} hint={s("ob.buyer_hint")}>
            <PSelect
              value={head.buyer_id}
              onValueChange={(v) => setH({ buyer_id: v ?? "" })}
              options={pick(options.buyers, "--Buyer--")}
              disabled={!editable}
              aria-label={s("buyer")}
            />
          </Field>
          <label className="flex items-center gap-2 self-center text-[13px] font-semibold">
            <Checkbox
              checked={head.is_buffer}
              onCheckedChange={(v) => setH({ is_buffer: Boolean(v) })}
              disabled={!editable}
              aria-label={s("buffer")}
            />
            {s("buffer")}
          </label>
          <Field label={s("job")}>
            <PSelect
              value={head.order_id}
              onValueChange={(v) => setH({ order_id: v ?? "" })}
              options={pick(options.orders, s("style_ph"))}
              disabled={!editable}
              aria-label={s("job")}
            />
          </Field>
          <Field label={s("remarks")} className="md:col-span-3">
            <Textarea
              rows={2}
              value={head.remarks}
              onChange={(e) => setH({ remarks: e.target.value })}
              maxLength={2000}
              disabled={!editable}
              aria-label={s("remarks")}
            />
          </Field>
        </div>
      </section>

      <section className={cn(card, "overflow-hidden")}>
        <div className="flex items-start justify-between gap-3 px-5 py-4">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {s("ob.details")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {s("ob.lines_hint")}
            </p>
          </div>
          {editable ? (
            <Button
              variant="outline"
              size="sm"
              onClick={() =>
                setLines((all) => [...all, obLine({ source: "LOCAL" })])
              }
            >
              <Plus /> {s("ob.add")}
            </Button>
          ) : null}
        </div>
        {lines.length === 0 ? (
          <NoLines title={s("ob.no_lines")} hint={s("ob.no_lines_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[2100px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    ["source", "s"],
                    ["composition", "y"],
                    ["count", "y"],
                    ["colour", "y"],
                    ["brand", "y"],
                    ["lot", "s"],
                    ["supplier", "s"],
                    ["pack_qty", "s"],
                    ["qty_per_pack", "s"],
                    ["pack_uom", "s"],
                    ["total_qty", "s"],
                    ["cost_price", "s"],
                    ["value", "s"],
                    ["cones", "s"],
                    ["rcv_date", "s"],
                  ].map(([k, g]) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        [
                          "pack_qty",
                          "qty_per_pack",
                          "total_qty",
                          "cost_price",
                          "value",
                          "cones",
                        ].includes(k) && "text-right",
                      )}
                    >
                      {g === "y" ? y(`col.${k}`) : s(`col.${k}`)}
                    </th>
                  ))}
                  <th className={th}>{y("col.copy")}</th>
                  <th className={th}>{y("col.remove")}</th>
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => {
                  const n = i + 1;
                  const set = (p) => setLine(l.key, p);
                  const computed = Boolean(
                    num(l.pack_qty) && num(l.qty_per_pack),
                  );
                  return (
                    <tr key={l.key} className="border-t border-border">
                      <td className="px-2 py-1.5">
                        <div className="w-36">
                          <PSelect
                            value={l.source}
                            onValueChange={(v) => set({ source: v ?? "" })}
                            options={pick(
                              options.store_sources.map((o) => ({
                                value: o,
                                label: s(`store_source.${o}`),
                              })),
                              "-- Select Source --",
                            )}
                            disabled={!editable}
                            aria-label={`${s("col.source")} ${n}`}
                          />
                        </div>
                      </td>
                      <td className="px-2 py-1.5">
                        <div className="w-44">
                          <PSelect
                            value={l.composition_id}
                            onValueChange={(v) =>
                              set({ composition_id: v ?? "" })
                            }
                            options={pick(options.compositions, "—")}
                            disabled={!editable}
                            aria-label={`${y("col.composition")} ${n}`}
                          />
                        </div>
                      </td>
                      <td className="px-2 py-1.5">
                        <div className="w-28">
                          <PSelect
                            value={l.yarn_count_id}
                            onValueChange={(v) =>
                              set({ yarn_count_id: v ?? "" })
                            }
                            options={pick(options.counts, "—")}
                            disabled={!editable}
                            aria-label={`${y("col.count")} ${n}`}
                          />
                        </div>
                      </td>
                      <td className="px-2 py-1.5">
                        <div className="w-36">
                          <PSelect
                            value={l.colour}
                            onValueChange={(v) => set({ colour: v ?? "" })}
                            options={pick(
                              options.colours.map((c) => ({
                                value: c,
                                label: c,
                              })),
                              "—",
                            )}
                            disabled={!editable}
                            aria-label={`${y("col.colour")} ${n}`}
                          />
                        </div>
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          value={l.brand}
                          onChange={(e) => set({ brand: e.target.value })}
                          maxLength={80}
                          className="h-8 w-28"
                          disabled={!editable}
                          aria-label={`${y("col.brand")} ${n}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <Input
                          value={l.lot_no}
                          onChange={(e) => set({ lot_no: e.target.value })}
                          maxLength={40}
                          className="h-8 w-24"
                          disabled={!editable}
                          aria-label={`${s("col.lot")} ${n}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <div className="w-44">
                          <PSelect
                            value={l.supplier_id}
                            onValueChange={(v) => set({ supplier_id: v ?? "" })}
                            options={pick(options.suppliers, s("supplier_ph"))}
                            disabled={!editable}
                            aria-label={`${s("col.supplier")} ${n}`}
                          />
                        </div>
                      </td>
                      <td className="px-2 py-1.5">
                        <QtyInput
                          value={l.pack_qty}
                          onChange={(v) => set({ pack_qty: v })}
                          disabled={!editable}
                          w="w-20"
                          label={`${s("col.pack_qty")} ${n}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <QtyInput
                          value={l.qty_per_pack}
                          onChange={(v) => set({ qty_per_pack: v })}
                          disabled={!editable}
                          w="w-20"
                          label={`${s("col.qty_per_pack")} ${n}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <div className="w-28">
                          <PSelect
                            value={l.pack_uom.toLowerCase()}
                            onValueChange={(v) => set({ pack_uom: v ?? "" })}
                            options={pick(uoms, s("pack_uom_ph"))}
                            disabled={!editable}
                            aria-label={`${s("col.pack_uom")} ${n}`}
                          />
                        </div>
                      </td>
                      <td className="px-2 py-1.5">
                        {computed ? (
                          <div className="w-24 text-right font-semibold tabular-nums">
                            {qtyFmt.format(totalOf(l))}
                          </div>
                        ) : (
                          <QtyInput
                            value={l.total_qty}
                            onChange={(v) => set({ total_qty: v })}
                            disabled={!editable}
                            label={`${s("col.total_qty")} ${n}`}
                          />
                        )}
                      </td>
                      <td className="px-2 py-1.5">
                        <QtyInput
                          value={l.cost_price}
                          onChange={(v) => set({ cost_price: v })}
                          disabled={!editable}
                          w="w-20"
                          label={`${s("col.cost_price")} ${n}`}
                        />
                      </td>
                      <td className="px-2 py-1.5 text-right font-semibold tabular-nums">
                        {moneyFmt.format(totalOf(l) * num(l.cost_price))}
                      </td>
                      <td className="px-2 py-1.5">
                        <QtyInput
                          value={l.cones}
                          onChange={(v) => set({ cones: v })}
                          disabled={!editable}
                          w="w-20"
                          label={`${s("col.cones")} ${n}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <DateInput
                          value={l.rcv_date}
                          onChange={(v) => set({ rcv_date: v })}
                          disabled={!editable}
                          label={`${s("col.rcv_date")} ${n}`}
                        />
                      </td>
                      <LineButtons
                        n={n}
                        disabled={!editable}
                        onCopy={() =>
                          setLines((all) => [
                            ...all.slice(0, i + 1),
                            { ...l, key: key() },
                            ...all.slice(i + 1),
                          ])
                        }
                        onRemove={() =>
                          setLines((all) => all.filter((x) => x.key !== l.key))
                        }
                      />
                    </tr>
                  );
                })}
              </tbody>
              <tfoot className="bg-surface-muted font-semibold">
                <tr className="border-t-2 border-border">
                  <td colSpan={10} className="px-2 py-2">
                    {s("total_row")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(lines.reduce((t, l) => t + totalOf(l), 0))}
                  </td>
                  <td />
                  <td className="px-2 py-2 text-right tabular-nums">
                    {moneyFmt.format(
                      lines.reduce(
                        (t, l) => t + totalOf(l) * num(l.cost_price),
                        0,
                      ),
                    )}
                  </td>
                  <td colSpan={4} />
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </section>

      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className={cn(card, "flex flex-col gap-3 p-5")}>
        <p className="text-[13px] text-foreground-muted">{s("ob.foot")}</p>
        <div className="flex flex-wrap gap-2">
          <Link href={OB} className={buttonVariants({ variant: "outline" })}>
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
              <Save /> {s("ob.save")}
            </Button>
          ) : null}
        </div>
      </section>
      <ConfirmDialog
        open={confirm}
        onOpenChange={setConfirm}
        title={y("delete")}
        description={doc ? y("confirm_delete", { no: doc.ob_no }) : ""}
        confirmLabel={y("delete")}
        cancelLabel={y("cancel")}
        destructive
        onConfirm={remove}
        loading={pending}
      />
    </div>
  );
}

/* ---------------- Yarn Stock Adjustment ---------------- */

const ADJ_COLS = [
  "status",
  "no",
  "date",
  "company",
  "store",
  "lines",
  "qty",
  "value",
  "reason",
];

function AdjList({ initialRows, canManage, act }) {
  const y = useYarn();
  const s = useYs();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initialRows);
  const [status, setStatus] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [q, setQ] = useState("");
  const [per, setPer] = useState(10);
  const [page, setPage] = useState(0);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const shown = useMemo(
    () =>
      rows.filter(
        (r) =>
          (!status || r.status === status) &&
          (!from || r.adj_date >= from) &&
          (!to || r.adj_date <= to) &&
          (!q ||
            [r.adj_no, r.store, r.reason].some((v) =>
              String(v ?? "")
                .toLowerCase()
                .includes(q.toLowerCase()),
            )),
      ),
    [rows, status, from, to, q],
  );
  const pages = Math.max(1, Math.ceil(shown.length / per));
  const active = rows.filter((r) => r.status === "ACTIVE");

  function refresh() {
    startTransition(async () => {
      const res = await act("adjustments");
      if (res?.status === "success") setRows(res.data);
      else setError(messagesOf(res));
    });
  }
  function cancel(no) {
    startTransition(async () => {
      const res = await act(
        `adjustments/${encodeURIComponent(no)}/cancel`,
        "POST",
      );
      if (res?.status !== "success") return setError(messagesOf(res));
      toastManager.add({ title: s("adj.cancelled", { no }), type: "success" });
      refresh();
    });
  }
  function exportCsv() {
    const esc = (v) => `"${String(v ?? "").replaceAll('"', '""')}"`;
    const lines = [
      ADJ_COLS.map((k) => esc(s(`adj.lc.${k}`))).join(","),
      ...shown.map((r) =>
        [
          s(`adj.status.${r.status}`),
          r.adj_no,
          r.adj_date,
          r.company,
          r.store,
          r.lines,
          r.qty,
          r.value,
          r.reason,
        ]
          .map(esc)
          .join(","),
      ),
    ];
    const url = URL.createObjectURL(
      new Blob([lines.join("\n")], { type: "text/csv" }),
    );
    const a = Object.assign(document.createElement("a"), {
      href: url,
      download: "yarn-adjustments.csv",
    });
    a.click();
    URL.revokeObjectURL(url);
  }

  return (
    <section className={card}>
      <div className="flex flex-wrap gap-2 px-5 pt-5">
        {[
          [s("adj.chip.count"), rows.length],
          [s("adj.chip.active"), active.length],
          [
            s("adj.chip.net"),
            qtyFmt.format(active.reduce((t, r) => t + r.qty, 0)),
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
      <div className="flex flex-wrap items-end gap-4 border-b border-border px-5 py-4">
        <Field label={s("adj.lc.status")} className="w-44">
          <PSelect
            value={status}
            onValueChange={(v) => (setStatus(v ?? ""), setPage(0))}
            options={[
              { value: "", label: y("any") },
              ...["ACTIVE", "CANCELLED"].map((v) => ({
                value: v,
                label: s(`adj.status.${v}`),
              })),
            ]}
            aria-label={s("adj.lc.status")}
          />
        </Field>
        <Field label={s("adj.lc.date")}>
          <div className="flex items-center gap-2">
            <Input
              type="date"
              value={from}
              onChange={(e) => (setFrom(e.target.value), setPage(0))}
              aria-label={s("from")}
            />
            <span className="text-sm text-foreground-muted">
              {s("to_word")}
            </span>
            <Input
              type="date"
              value={to}
              onChange={(e) => (setTo(e.target.value), setPage(0))}
              aria-label={s("to")}
            />
          </div>
        </Field>
        <div className="ml-auto flex items-center gap-3 text-[13px] text-foreground-muted">
          {s("adj.shown", { shown: shown.length, total: rows.length })}
          <Button
            variant="outline"
            size="sm"
            onClick={() => (
              setStatus(""),
              setFrom(""),
              setTo(""),
              setQ(""),
              setPage(0)
            )}
          >
            <FilterX /> {y("clear")}
          </Button>
        </div>
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
        <div className="relative w-full max-w-xs">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input
            value={q}
            onChange={(e) => (setQ(e.target.value), setPage(0))}
            placeholder={s("adj.search_ph", { count: rows.length })}
            className="pl-9"
            aria-label={s("search")}
          />
        </div>
        <Button variant="outline" size="sm" onClick={exportCsv}>
          <Download /> {s("export")}
        </Button>
      </div>
      {error ? (
        <div className="px-5 pb-3">
          <Alert variant="danger">{error.join(" · ")}</Alert>
        </div>
      ) : null}
      <div className="overflow-x-auto">
        <table className="w-full min-w-[1200px] text-sm">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {ADJ_COLS.map((k) => (
                <th
                  key={k}
                  className={cn(
                    th,
                    ["lines", "qty", "value"].includes(k) && "text-right",
                  )}
                >
                  {s(`adj.lc.${k}`)}
                </th>
              ))}
              <th className={cn(th, "text-right")}>{s("action_col")}</th>
            </tr>
          </thead>
          <tbody>
            {shown.length === 0 ? (
              <tr>
                <td
                  colSpan={10}
                  className="px-3 py-10 text-center text-foreground-muted"
                >
                  {s("adj.empty")}
                </td>
              </tr>
            ) : (
              shown.slice(page * per, page * per + per).map((r) => (
                <tr key={r.adj_no} className="border-t border-border">
                  <td className="px-2 py-2">
                    <StatusChip
                      status={r.status}
                      label={s(`adj.status.${r.status}`)}
                    />
                  </td>
                  <td className="px-2 py-2 font-medium whitespace-nowrap">
                    {r.adj_no}
                  </td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {dayFmt(r.adj_date)}
                  </td>
                  <td className="px-2 py-2">{r.company}</td>
                  <td className="px-2 py-2">{r.store ?? "—"}</td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {r.lines}
                  </td>
                  <td
                    className={cn(
                      "px-2 py-2 text-right tabular-nums",
                      r.qty < 0 && "text-danger",
                    )}
                  >
                    {qtyFmt.format(r.qty)}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {moneyFmt.format(r.value)}
                  </td>
                  <td className="px-2 py-2">{r.reason}</td>
                  <td className="px-2 py-1.5">
                    <div className="flex justify-end gap-1">
                      <Link
                        href={`${ADJ}?tab=entry&ref=${encodeURIComponent(r.adj_no)}`}
                        className={buttonVariants({
                          variant: "outline",
                          size: "sm",
                        })}
                      >
                        <Eye /> {y("open")}
                      </Link>
                      {canManage && r.status === "ACTIVE" ? (
                        <Button
                          variant="outline"
                          size="sm"
                          className="text-danger"
                          onClick={() => cancel(r.adj_no)}
                          disabled={pending}
                        >
                          <Ban /> {s("adj.cancel")}
                        </Button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-[13px] text-foreground-muted">
        <div className="flex items-center gap-2">
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
          <div className="w-20">
            <PSelect
              value={String(per)}
              onValueChange={(v) => (setPer(Number(v ?? 10)), setPage(0))}
              options={[10, 25, 50, 100].map((n) => ({
                value: String(n),
                label: String(n),
              }))}
              aria-label={s("per_page")}
            />
          </div>
          {s("per_page")}
        </div>
        <div className="flex items-center gap-2">
          {s("items", { count: shown.length })}
          <Button
            variant="outline"
            size="icon"
            className="size-8"
            onClick={refresh}
            loading={pending}
            aria-label={s("refresh")}
          >
            <RotateCcw />
          </Button>
        </div>
      </div>
    </section>
  );
}

const adjLine = (l = {}) => ({
  key: key(),
  lot_id: str(l.lot_id),
  qty: str(l.qty),
  remarks: str(l.remarks),
});

function AdjEntry({ options, initial, initialLots, canManage, act }) {
  const y = useYarn();
  const s = useYs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  const firstCode =
    (
      options.store_codes.find((x) => /yarn/i.test(x.label)) ??
      options.store_codes[0]
    )?.value ?? "";
  const codeOf = (id) =>
    options.store_codes.find((c) => c.id === id)?.value ?? "";
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() => ({
    adj_date: initial?.adj_date ?? today,
    store: initial ? codeOf(initial.store_id) : firstCode,
    reason: initial?.reason ?? "",
  }));
  // The first store's lots come with the page.
  const [lots, setLots] = useState(initialLots ?? []);
  const [lines, setLines] = useState(() =>
    initial
      ? initial.lines.map((l) => ({ ...adjLine(l), label: l.label }))
      : [],
  );
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && !doc;
  const setLine = (k, patch) =>
    setLines((all) => all.map((l) => (l.key === k ? { ...l, ...patch } : l)));
  const lotOf = (id) => lots?.find((l) => l.lot_id === id);

  function loadLots(code) {
    if (!code) return setLots([]);
    startTransition(async () => {
      const res = await act(`lots?store=${encodeURIComponent(code)}`);
      if (res?.status === "success") setLots(res.data);
      else setErrors(messagesOf(res));
    });
  }

  function clear() {
    setDoc(null);
    setHead({ adj_date: today, store: firstCode, reason: "" });
    setLines([]);
    setLots(initialLots ?? []);
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }
  function save() {
    startTransition(async () => {
      const res = await act("adjustments", "POST", {
        adj_date: head.adj_date || null,
        store_id:
          options.store_codes.find((c) => c.value === head.store)?.id ?? null,
        reason: head.reason || null,
        lines: lines.map((l) => ({
          lot_id: l.lot_id || null,
          qty: orNull(l.qty),
          remarks: l.remarks || null,
        })),
      });
      if (res?.status !== "success") return setErrors(messagesOf(res));
      setDoc(res.data);
      setLines(res.data.lines.map((l) => ({ ...adjLine(l), label: l.label })));
      setErrors(null);
      window.history.replaceState(
        null,
        "",
        `?tab=entry&ref=${encodeURIComponent(res.data.adj_no)}`,
      );
      toastManager.add({
        title: s("adj.saved", { no: res.data.adj_no }),
        type: "success",
      });
    });
  }

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {s("tabs.adj_entry")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {s("adj.entry_hint")}
            </p>
          </div>
          {doc ? (
            <StatusChip
              status={doc.status}
              label={`${doc.adj_no} · ${s(`adj.status.${doc.status}`)}`}
            />
          ) : null}
        </div>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Field label={s("adj.no")}>
            <Input
              value={doc?.adj_no ?? ""}
              placeholder="YADJ-…"
              readOnly
              className="bg-surface-muted"
              aria-label={s("adj.no")}
            />
          </Field>
          <Field label={s("adj.date")} required>
            <Input
              type="date"
              value={head.adj_date}
              onChange={(e) =>
                setHead((h) => ({ ...h, adj_date: e.target.value }))
              }
              disabled={!editable}
              aria-label={s("adj.date")}
            />
          </Field>
          <Field label={s("company")} required>
            <PSelect
              value={options.company.value}
              options={[options.company]}
              disabled
              aria-label={s("company")}
            />
          </Field>
          <Field label={s("store")} required>
            <PSelect
              value={head.store}
              onValueChange={(v) => {
                setHead((h) => ({ ...h, store: v ?? "" }));
                setLines([]);
                loadLots(v ?? "");
              }}
              options={[
                { value: "", label: s("select") },
                ...options.store_codes,
              ]}
              disabled={!editable}
              aria-label={s("store")}
            />
          </Field>
          <Field
            label={s("reason")}
            required
            hint={s("adj.reason_hint")}
            className="md:col-span-2"
          >
            <Input
              value={head.reason}
              onChange={(e) =>
                setHead((h) => ({ ...h, reason: e.target.value }))
              }
              maxLength={255}
              disabled={!editable}
              aria-label={s("reason")}
            />
          </Field>
        </div>
      </section>

      <section className={cn(card, "overflow-hidden")}>
        <div className="flex items-start justify-between gap-3 px-5 py-4">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {s("adj.lines")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {s("adj.lines_hint")}
            </p>
          </div>
          {editable ? (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setLines((all) => [...all, adjLine()])}
              disabled={!head.store}
            >
              <Plus /> {s("adj.add")}
            </Button>
          ) : null}
        </div>
        {lines.length === 0 ? (
          <NoLines title={s("adj.no_lines")} hint={s("adj.no_lines_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1000px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  <th className={th}>{s("lot")}</th>
                  {editable ? (
                    <th className={cn(th, "text-right")}>{s("col.balance")}</th>
                  ) : null}
                  <th className={cn(th, "text-right")}>
                    {s("col.adjust_qty")}
                  </th>
                  {editable ? (
                    <th className={cn(th, "text-right")}>
                      {s("col.new_balance")}
                    </th>
                  ) : null}
                  <th className={th}>{y("col.remarks")}</th>
                  {editable ? <th className={th}>{y("col.remove")}</th> : null}
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => {
                  const lot = lotOf(l.lot_id);
                  return (
                    <tr key={l.key} className="border-t border-border">
                      <td className="px-2 py-1.5">
                        {editable ? (
                          <div className="w-96">
                            <PSelect
                              value={l.lot_id}
                              onValueChange={(v) =>
                                setLine(l.key, { lot_id: v ?? "" })
                              }
                              options={[
                                { value: "", label: s("adj.lot_ph") },
                                ...lots.map((x) => ({
                                  value: x.lot_id,
                                  label: x.label || x.yarn,
                                })),
                              ]}
                              aria-label={`${s("lot")} ${i + 1}`}
                            />
                          </div>
                        ) : (
                          l.label
                        )}
                      </td>
                      {editable ? (
                        <td className="px-2 py-1.5 text-right tabular-nums">
                          {lot ? qtyFmt.format(lot.balance) : "—"}
                        </td>
                      ) : null}
                      <td className="px-2 py-1.5">
                        <div className="flex justify-end">
                          <Input
                            type="number"
                            step="any"
                            value={l.qty}
                            onChange={(e) =>
                              setLine(l.key, { qty: e.target.value })
                            }
                            className="h-8 w-28 text-right tabular-nums"
                            disabled={!editable}
                            aria-label={`${s("col.adjust_qty")} ${i + 1}`}
                          />
                        </div>
                      </td>
                      {editable ? (
                        <td className="px-2 py-1.5 text-right font-semibold tabular-nums">
                          {lot ? qtyFmt.format(lot.balance + num(l.qty)) : "—"}
                        </td>
                      ) : null}
                      <td className="px-2 py-1.5">
                        <Input
                          value={l.remarks}
                          onChange={(e) =>
                            setLine(l.key, { remarks: e.target.value })
                          }
                          maxLength={300}
                          className="h-8 w-56"
                          disabled={!editable}
                          aria-label={`${y("col.remarks")} ${i + 1}`}
                        />
                      </td>
                      {editable ? (
                        <td className="px-1 py-1.5">
                          <Button
                            variant="outline"
                            size="icon"
                            className="size-8 text-danger"
                            onClick={() =>
                              setLines((all) =>
                                all.filter((x) => x.key !== l.key),
                              )
                            }
                            aria-label={`${y("remove_line")} ${i + 1}`}
                          >
                            <Trash2 />
                          </Button>
                        </td>
                      ) : null}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className={cn(card, "flex flex-wrap gap-2 p-5")}>
        <Link href={ADJ} className={buttonVariants({ variant: "outline" })}>
          <ArrowLeft /> {y("back")}
        </Link>
        <Button variant="outline" onClick={clear} disabled={pending}>
          <RotateCcw /> {y("clear")}
        </Button>
        {editable ? (
          <Button onClick={save} loading={pending}>
            <Save /> {s("adj.save")}
          </Button>
        ) : null}
      </section>
    </div>
  );
}

export { ObList, ObEntry, AdjList, AdjEntry };
