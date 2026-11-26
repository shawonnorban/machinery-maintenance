"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  ArrowLeft,
  Ban,
  CheckCheck,
  Pencil,
  Plus,
  Repeat,
  RotateCcw,
  Save,
  Search,
  ShieldCheck,
  Trash2,
  Undo2,
  Wallet,
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
  LineButtons,
  orNull,
} from "./yarn-common";

/*
 * Knit Production → Yarn Store (AnnotechERP 15/03) → Daily Yarn Receipt: the
 * MRR register (search; Store Verify → Audit Verify → Accounts Approve,
 * Cancel, and Return / Exchange, which raise a challan of the lines still
 * held), the receipt desk (direct, or by a placed purchase order whose open
 * lines it brings across), and Returns & Exchanges — the challan trimmed to
 * what leaves and issued. Daily Yarn Receipt Return lists the challans.
 * Nothing reloads; a document travels by its number.
 */

const MRR = "/knit-production/daily-receive";
const RR = "/knit-production/daily-receive-return";

function MrrList({ options, initialRows, canManage, act }) {
  const y = useYarn();
  const s = useYs();
  const router = useRouter();
  const toastManager = useToastManager();
  const blank = { buyer: "", order: "", po: "", recv_opt: "", status: "" };
  const [filters, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((f) => ({ ...f, [k]: v ?? "" }));
  const qs = (f) =>
    new URLSearchParams(Object.entries(f).filter(([, v]) => v)).toString();

  function search(f = filters) {
    startTransition(async () => {
      const res = await act(`receipts?${qs(f)}`);
      if (res?.status === "success") {
        setRows(res.data);
        setError(null);
      } else setError(messagesOf(res));
    });
  }
  function run(no, action) {
    startTransition(async () => {
      const res = await act(
        `receipts/${encodeURIComponent(no)}/${action}`,
        "POST",
      );
      if (res?.status !== "success") return setError(messagesOf(res));
      toastManager.add({
        title: s(`mrr.done.${action}`, { no }),
        type: "success",
      });
      search();
    });
  }
  function raise(no, type) {
    startTransition(async () => {
      const res = await act(
        `receipts/${encodeURIComponent(no)}/return`,
        "POST",
        {
          type,
        },
      );
      if (res?.status !== "success") return setError(messagesOf(res));
      toastManager.add({
        title: s("mrr.done.return", { no: res.data.return_no }),
        type: "success",
      });
      router.push(
        `${MRR}?tab=returns&ref=${encodeURIComponent(res.data.return_no)}`,
      );
    });
  }
  const pick = (list, ph = s("select")) => [{ value: "", label: ph }, ...list];

  return (
    <section className={card}>
      <div className="px-5 pt-5">
        <h2 className="text-base font-semibold text-foreground">
          {s("mrr.search_title")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {s("mrr.list_hint")}
        </p>
      </div>
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-5">
        <Field label={s("buyer")}>
          <PSelect
            value={filters.buyer}
            onValueChange={(v) => setF("buyer", v)}
            options={pick(options.buyers, s("all"))}
            aria-label={s("buyer")}
          />
        </Field>
        <Field label={s("job")}>
          <PSelect
            value={filters.order}
            onValueChange={(v) => setF("order", v)}
            options={pick(options.orders, s("style_ph"))}
            aria-label={s("job")}
          />
        </Field>
        <Field label={s("mrr.po")}>
          <Input
            value={filters.po}
            onChange={(e) => setF("po", e.target.value.trim())}
            placeholder="YPO-…"
            aria-label={s("mrr.po")}
          />
        </Field>
        <Field label={s("mrr.recv_opt")}>
          <PSelect
            value={filters.recv_opt}
            onValueChange={(v) => setF("recv_opt", v)}
            options={pick(
              options.recv_opts.map((o) => ({
                value: o,
                label: s(`mrr.opt.${o}`),
              })),
              s("all"),
            )}
            aria-label={s("mrr.recv_opt")}
          />
        </Field>
        <Field label={s("mrr.lc.status")}>
          <PSelect
            value={filters.status}
            onValueChange={(v) => setF("status", v)}
            options={pick(
              options.statuses.map((o) => ({
                value: o,
                label: s(`mrr_status.${o}`),
              })),
              s("all"),
            )}
            aria-label={s("mrr.lc.status")}
          />
        </Field>
      </div>
      <div className="flex flex-wrap gap-2 px-5 pt-4 pb-5">
        <Button onClick={() => search()} loading={pending}>
          <Search /> {s("search")}
        </Button>
        {canManage ? (
          <Link
            href={`${MRR}?tab=entry`}
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
        <NoLines title={s("mrr.empty")} hint={s("mrr.empty_hint")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[1500px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "no",
                  "date",
                  "store",
                  "opt",
                  "po",
                  "challan",
                  "supplier",
                  "buyer",
                  "lines",
                  "qty",
                  "challan_qty",
                  "value",
                  "status",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      th,
                      ["lines", "qty", "challan_qty", "value"].includes(k) &&
                        "text-right",
                    )}
                  >
                    {s(`mrr.lc.${k}`)}
                  </th>
                ))}
                <th className={th}>{s("actions")}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.mrr_no} className="border-t border-border">
                  <td className="px-2 py-2 font-medium whitespace-nowrap">
                    <Link
                      href={`${MRR}?tab=entry&ref=${encodeURIComponent(r.mrr_no)}`}
                      className="text-brand hover:underline"
                    >
                      {r.mrr_no}
                    </Link>
                  </td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {dayFmt(r.mrr_date)}
                  </td>
                  <td className="px-2 py-2">{r.store ?? "—"}</td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {s(`mrr.opt.${r.recv_opt}`)}
                  </td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {r.po_no ?? "—"}
                  </td>
                  <td className="px-2 py-2">{r.challan_no ?? "—"}</td>
                  <td className="px-2 py-2">{r.supplier ?? "—"}</td>
                  <td className="px-2 py-2">{r.buyer ?? "—"}</td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {r.lines}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(r.qty)}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(r.challan_qty)}
                  </td>
                  <td className="px-2 py-2 text-right font-semibold tabular-nums">
                    {moneyFmt.format(r.value)}
                  </td>
                  <td className="px-2 py-2">
                    <StatusChip
                      status={r.status}
                      label={s(`mrr_status.${r.status}`)}
                    />
                  </td>
                  <td className="px-2 py-1.5">
                    {canManage ? (
                      <div className="flex gap-1">
                        {r.status === "STORE_RECEIVE" ? (
                          <>
                            <Link
                              href={`${MRR}?tab=entry&ref=${encodeURIComponent(r.mrr_no)}`}
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
                              onClick={() => run(r.mrr_no, "verify")}
                              disabled={pending}
                            >
                              <CheckCheck /> {s("mrr.btn.verify")}
                            </Button>
                          </>
                        ) : null}
                        {r.status === "STORE_VERIFY" ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => run(r.mrr_no, "audit")}
                            disabled={pending}
                          >
                            <ShieldCheck /> {s("mrr.btn.audit")}
                          </Button>
                        ) : null}
                        {r.status === "AUDIT_VERIFY" ? (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => run(r.mrr_no, "approve")}
                            disabled={pending}
                          >
                            <Wallet /> {s("mrr.btn.approve")}
                          </Button>
                        ) : null}
                        {r.status !== "CANCELLED" ? (
                          <>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => raise(r.mrr_no, "RETURN")}
                              disabled={pending}
                            >
                              <Undo2 /> {s("mrr.btn.return")}
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => raise(r.mrr_no, "EXCHANGE")}
                              disabled={pending}
                            >
                              <Repeat /> {s("mrr.btn.exchange")}
                            </Button>
                          </>
                        ) : null}
                        {r.status === "STORE_RECEIVE" ? (
                          <Button
                            variant="outline"
                            size="sm"
                            className="text-danger"
                            onClick={() => run(r.mrr_no, "cancel")}
                            disabled={pending}
                          >
                            <Ban /> {s("mrr.btn.cancel")}
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
  "mrr_date",
  "store_id",
  "recv_opt",
  "po_no",
  "recv_ref",
  "challan_no",
  "supplier_id",
  "buyer_id",
  "order_id",
  "remarks",
];
const blankHead = (today, store) => ({
  ...Object.fromEntries(HEAD_KEYS.map((k) => [k, ""])),
  mrr_date: today,
  recv_opt: "DIRECT",
  store_id: store ?? "",
});
const asLine = (l = {}) => ({
  key: key(),
  po_line_id: str(l.po_line_id),
  left: l.left ?? null,
  composition_id: str(l.composition_id),
  yarn_count_id: str(l.yarn_count_id),
  colour: str(l.colour),
  brand: str(l.brand),
  lot_no: str(l.lot_no),
  pack_qty: str(l.pack_qty),
  qty_per_pack: str(l.qty_per_pack),
  pack_uom: str(l.pack_uom),
  recv_qty: str(l.recv_qty),
  uom: str(l.uom || "kg"),
  rate: str(l.rate),
  challan_qty: str(l.challan_qty),
  cones: str(l.cones),
  remarks: str(l.remarks),
});

function MrrEntry({ options, initial, canManage, act }) {
  const y = useYarn();
  const s = useYs();
  const toastManager = useToastManager();
  const [today] = useState(todayIso);
  // The yarn store, as Brickland's Receiving Store opens on.
  const defaultStore = (
    options.stores.find((x) => /yarn/i.test(x.label)) ?? options.stores[0]
  )?.value;
  const [doc, setDoc] = useState(initial);
  const [head, setHead] = useState(() =>
    initial
      ? Object.fromEntries(HEAD_KEYS.map((k) => [k, str(initial[k])]))
      : blankHead(today, defaultStore),
  );
  const [lines, setLines] = useState(() => (initial?.lines ?? []).map(asLine));
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const editable = canManage && (!doc || doc.editable);
  const byPo = head.recv_opt === "PO";
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));
  const setLine = (k, patch) =>
    setLines((all) =>
      all.map((l) => {
        if (l.key !== k) return l;
        const next = { ...l, ...patch };
        // Recv Qty follows Pack Qty × Qty/Pack while both are given.
        if (
          ("pack_qty" in patch || "qty_per_pack" in patch) &&
          num(next.pack_qty) &&
          num(next.qty_per_pack)
        )
          next.recv_qty = String(
            +(num(next.pack_qty) * num(next.qty_per_pack)).toFixed(4),
          );
        return next;
      }),
    );
  const pick = (list, ph = s("select")) => [{ value: "", label: ph }, ...list];
  const amount = (l) => num(l.recv_qty) * num(l.rate);
  const uoms = options.uoms.map((u) => ({
    value: u.value.toLowerCase(),
    label: u.label.toLowerCase(),
  }));

  function take(data) {
    setDoc(data);
    setHead(Object.fromEntries(HEAD_KEYS.map((k) => [k, str(data[k])])));
    setLines(data.lines.map(asLine));
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      `?tab=entry&ref=${encodeURIComponent(data.mrr_no)}`,
    );
  }
  function clear() {
    setDoc(null);
    setHead(blankHead(today, defaultStore));
    setLines([]);
    setErrors(null);
    window.history.replaceState(null, "", "?tab=entry");
  }
  function load(no) {
    if (!no) return clear();
    startTransition(async () => {
      const res = await act(`receipts/${encodeURIComponent(no)}`);
      if (res?.status === "success") take(res.data);
      else setErrors(messagesOf(res));
    });
  }
  function bringPo(no) {
    setH({ po_no: no });
    if (!no) return;
    startTransition(async () => {
      const res = await act(`receipts/po/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      const d = res.data;
      setH({
        po_no: d.po_no,
        supplier_id: str(d.supplier_id),
        buyer_id: str(d.buyer_id),
        order_id: str(d.order_id),
        ...(d.store_id ? { store_id: d.store_id } : {}),
      });
      setLines(
        d.lines.map((l) =>
          asLine({ ...l, recv_qty: l.left, challan_qty: l.left, uom: "kg" }),
        ),
      );
      setErrors(null);
    });
  }
  function save() {
    startTransition(async () => {
      const body = {
        ...Object.fromEntries(
          Object.entries(head).map(([k, v]) => [k, v === "" ? null : v]),
        ),
        lines: lines.map((l) => ({
          po_line_id: l.po_line_id || null,
          composition_id: l.composition_id || null,
          yarn_count_id: l.yarn_count_id || null,
          colour: l.colour || null,
          brand: l.brand || null,
          lot_no: l.lot_no || null,
          pack_qty: orNull(l.pack_qty),
          qty_per_pack: orNull(l.qty_per_pack),
          pack_uom: l.pack_uom || null,
          recv_qty: orNull(l.recv_qty),
          uom: l.uom || null,
          rate: orNull(l.rate),
          challan_qty: orNull(l.challan_qty),
          cones: orNull(l.cones),
          remarks: l.remarks || null,
        })),
      };
      const res = await act(
        doc ? `receipts/${encodeURIComponent(doc.mrr_no)}` : "receipts",
        doc ? "PUT" : "POST",
        body,
      );
      if (res?.status === "success") {
        take(res.data);
        toastManager.add({
          title: s("mrr.saved", { no: res.data.mrr_no }),
          type: "success",
        });
      } else setErrors(messagesOf(res));
    });
  }

  const pos = options.placed_pos.some((p) => p.value === head.po_no)
    ? options.placed_pos
    : head.po_no
      ? [{ value: head.po_no, label: head.po_no }, ...options.placed_pos]
      : options.placed_pos;

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {s("tabs.mrr_entry")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {s("mrr.entry_hint")}
            </p>
          </div>
          {doc ? (
            <StatusChip
              status={doc.status}
              label={
                doc.editable
                  ? y("editing", { no: doc.mrr_no })
                  : y("read_only", { status: s(`mrr_status.${doc.status}`) })
              }
            />
          ) : null}
        </div>
        <Field
          label={s("mrr.existing")}
          hint={s("ob.existing_hint")}
          className="mt-4"
        >
          <PSelect
            value={doc?.mrr_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={[
              { value: "", label: s("mrr.existing_new") },
              ...options.receipts,
            ]}
            aria-label={s("mrr.existing")}
          />
        </Field>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Field label={s("mrr.no")}>
            <Input
              value={doc?.mrr_no ?? ""}
              placeholder="YMRR-…"
              readOnly
              className="bg-surface-muted"
              aria-label={s("mrr.no")}
            />
          </Field>
          <Field label={s("mrr.date")} required>
            <Input
              type="date"
              value={head.mrr_date}
              onChange={(e) => setH({ mrr_date: e.target.value })}
              disabled={!editable}
              aria-label={s("mrr.date")}
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
          <Field label={s("receiving_store")} required>
            <PSelect
              value={head.store_id}
              onValueChange={(v) => setH({ store_id: v ?? "" })}
              options={pick(options.stores)}
              disabled={!editable}
              aria-label={s("receiving_store")}
            />
          </Field>
          <Field label={s("mrr.recv_opt")} required>
            <PSelect
              value={head.recv_opt}
              onValueChange={(v) => {
                setH({ recv_opt: v ?? "DIRECT", po_no: "" });
                if (head.recv_opt === "PO") setLines([]);
              }}
              options={options.recv_opts.map((o) => ({
                value: o,
                label: s(`mrr.opt.${o}`),
              }))}
              disabled={!editable}
              aria-label={s("mrr.recv_opt")}
            />
          </Field>
          <Field label={s("mrr.po")} hint={s("mrr.po_hint")}>
            <PSelect
              value={head.po_no}
              onValueChange={(v) => bringPo(v ?? "")}
              options={pick(pos, "-- Select PO --")}
              disabled={!editable || !byPo}
              aria-label={s("mrr.po")}
            />
          </Field>
          <Field label={s("mrr.pi")} hint={s("mrr.pi_hint")}>
            <PSelect
              value=""
              options={[{ value: "", label: s("mrr.no_pi") }]}
              disabled
              aria-label={s("mrr.pi")}
            />
          </Field>
          <Field label={s("supplier")} required={!byPo}>
            <PSelect
              value={head.supplier_id}
              onValueChange={(v) => setH({ supplier_id: v ?? "" })}
              options={pick(options.suppliers, s("supplier_ph"))}
              disabled={!editable || byPo}
              aria-label={s("supplier")}
            />
          </Field>
          <Field label={s("mrr.ref")} hint={s("mrr.ref_hint")}>
            <Input
              value={head.recv_ref}
              onChange={(e) => setH({ recv_ref: e.target.value })}
              maxLength={64}
              disabled={!editable}
              aria-label={s("mrr.ref")}
            />
          </Field>
          <Field label={s("mrr.challan")}>
            <Input
              value={head.challan_no}
              onChange={(e) => setH({ challan_no: e.target.value })}
              maxLength={64}
              disabled={!editable}
              aria-label={s("mrr.challan")}
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
              {s("mrr.lines")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {s("mrr.lines_hint")}
            </p>
          </div>
          {editable && !byPo ? (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setLines((all) => [...all, asLine()])}
            >
              <Plus /> {s("mrr.add")}
            </Button>
          ) : null}
        </div>
        {lines.length === 0 ? (
          <NoLines title={s("mrr.no_lines")} hint={s("mrr.no_lines_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[2000px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "yarn",
                    "count",
                    "colour",
                    "brand",
                    "lot",
                    ...(byPo ? ["left"] : []),
                    "pack_qty",
                    "qty_per_pack",
                    "pack_uom",
                    "recv_qty",
                    "uom",
                    "rate",
                    "amount",
                    "challan_qty",
                    "cones",
                    "remarks",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        [
                          "left",
                          "pack_qty",
                          "qty_per_pack",
                          "recv_qty",
                          "rate",
                          "amount",
                          "challan_qty",
                          "cones",
                        ].includes(k) && "text-right",
                      )}
                    >
                      {[
                        "yarn",
                        "count",
                        "colour",
                        "brand",
                        "uom",
                        "remarks",
                      ].includes(k)
                        ? y(`col.${k === "yarn" ? "composition" : k}`)
                        : s(`col.${k}`)}
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
                  const fixed = !editable || (byPo && Boolean(l.po_line_id));
                  return (
                    <tr key={l.key} className="border-t border-border">
                      <td className="px-2 py-1.5">
                        <div className="w-44">
                          <PSelect
                            value={l.composition_id}
                            onValueChange={(v) =>
                              set({ composition_id: v ?? "" })
                            }
                            options={pick(options.compositions, "—")}
                            disabled={fixed}
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
                            disabled={fixed}
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
                            disabled={fixed}
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
                      {byPo ? (
                        <td className="px-2 py-1.5 text-right tabular-nums">
                          {l.left === null ? "—" : qtyFmt.format(l.left)}
                        </td>
                      ) : null}
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
                        <QtyInput
                          value={l.recv_qty}
                          onChange={(v) => set({ recv_qty: v })}
                          disabled={!editable}
                          label={`${s("col.recv_qty")} ${n}`}
                        />
                      </td>
                      <td className="px-2 py-1.5">
                        <div className="w-24">
                          <PSelect
                            value={l.uom.toLowerCase()}
                            onValueChange={(v) => set({ uom: v ?? "" })}
                            options={uoms}
                            disabled={!editable}
                            aria-label={`${y("col.uom")} ${n}`}
                          />
                        </div>
                      </td>
                      <td className="px-2 py-1.5">
                        <QtyInput
                          value={l.rate}
                          onChange={(v) => set({ rate: v })}
                          disabled={!editable}
                          w="w-20"
                          label={`${s("col.rate")} ${n}`}
                        />
                      </td>
                      <td className="px-2 py-1.5 text-right font-semibold tabular-nums">
                        {moneyFmt.format(amount(l))}
                      </td>
                      <td className="px-2 py-1.5">
                        <QtyInput
                          value={l.challan_qty}
                          onChange={(v) => set({ challan_qty: v })}
                          disabled={!editable}
                          label={`${s("col.challan_qty")} ${n}`}
                        />
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
                        <Input
                          value={l.remarks}
                          onChange={(e) => set({ remarks: e.target.value })}
                          maxLength={300}
                          className="h-8 w-40"
                          disabled={!editable}
                          aria-label={`${y("col.remarks")} ${n}`}
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
                  <td colSpan={byPo ? 9 : 8} className="px-2 py-2">
                    {s("total_row")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(
                      lines.reduce((t, l) => t + num(l.recv_qty), 0),
                    )}
                  </td>
                  <td colSpan={2} />
                  <td className="px-2 py-2 text-right tabular-nums">
                    {moneyFmt.format(lines.reduce((t, l) => t + amount(l), 0))}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(
                      lines.reduce((t, l) => t + num(l.challan_qty), 0),
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
        <p className="text-[13px] text-foreground-muted">{s("mrr.foot")}</p>
        <div className="flex flex-wrap gap-2">
          <Link href={MRR} className={buttonVariants({ variant: "outline" })}>
            <ArrowLeft /> {y("back")}
          </Link>
          <Button variant="outline" onClick={clear} disabled={pending}>
            <RotateCcw /> {y("clear")}
          </Button>
          {editable ? (
            <Button onClick={save} loading={pending}>
              <Save /> {s("mrr.save")}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}

/**
 * Returns & Exchanges (and Daily Yarn Receipt Return's entry tab): a challan
 * raised from the register, trimmed line by line to what is leaving; Is
 * Finalized? issues it, taking the yarn back off the ledger.
 */
function ReturnDesk({ options, initial, canManage, act, tab = "returns" }) {
  const y = useYarn();
  const s = useYs();
  const toastManager = useToastManager();
  const [doc, setDoc] = useState(initial);
  const asHead = (d) => ({
    return_date: str(d?.return_date),
    challan_type: str(d?.challan_type),
    finalized: Boolean(d?.finalized),
    reason: str(d?.reason),
  });
  const [head, setHead] = useState(() => asHead(initial));
  const [lines, setLines] = useState(() =>
    (initial?.lines ?? []).map((l) => ({
      ...l,
      qty: str(l.qty),
      remarks: str(l.remarks),
    })),
  );
  const [errors, setErrors] = useState(null);
  const [confirm, setConfirm] = useState(false);
  const [pending, startTransition] = useTransition();
  const editable = canManage && doc && !doc.finalized;
  const setH = (patch) => setHead((h) => ({ ...h, ...patch }));

  function take(data) {
    setDoc(data);
    setHead(asHead(data));
    setLines(
      data.lines.map((l) => ({
        ...l,
        qty: str(l.qty),
        remarks: str(l.remarks),
      })),
    );
    setErrors(null);
    window.history.replaceState(
      null,
      "",
      `?tab=${tab}&ref=${encodeURIComponent(data.return_no)}`,
    );
  }
  function clear() {
    setDoc(null);
    setHead(asHead(null));
    setLines([]);
    setErrors(null);
    window.history.replaceState(null, "", `?tab=${tab}`);
  }
  function load(no) {
    if (!no) return clear();
    startTransition(async () => {
      const res = await act(`returns/${encodeURIComponent(no)}`);
      if (res?.status === "success") take(res.data);
      else setErrors(messagesOf(res));
    });
  }
  function save() {
    startTransition(async () => {
      const res = await act(
        `returns/${encodeURIComponent(doc.return_no)}`,
        "PUT",
        {
          return_date: head.return_date || null,
          challan_type: head.challan_type || null,
          finalized: head.finalized,
          reason: head.reason || null,
          lines: lines.map((l) => ({
            receipt_line_id: l.receipt_line_id,
            qty: orNull(l.qty),
            remarks: l.remarks || null,
          })),
        },
      );
      if (res?.status !== "success") return setErrors(messagesOf(res));
      take(res.data);
      toastManager.add({
        title: s(res.data.finalized ? "ret.issued" : "ret.saved", {
          no: res.data.return_no,
        }),
        type: "success",
      });
    });
  }
  function remove() {
    startTransition(async () => {
      const res = await act(
        `returns/${encodeURIComponent(doc.return_no)}`,
        "DELETE",
      );
      setConfirm(false);
      if (res?.status !== "success") return setErrors(messagesOf(res));
      clear();
    });
  }

  return (
    <div className="flex min-w-0 flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {s("ret.title")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              {s("ret.hint")}
            </p>
          </div>
          {doc ? (
            <StatusChip
              status={doc.finalized ? "YES" : "NO"}
              label={
                doc.finalized
                  ? y("read_only", { status: s("ret.finalized") })
                  : y("editing", { no: doc.return_no })
              }
            />
          ) : null}
        </div>
        <Field
          label={s("ret.existing")}
          hint={s("ob.existing_hint")}
          className="mt-4"
        >
          <PSelect
            value={doc?.return_no ?? ""}
            onValueChange={(v) => load(v ?? "")}
            options={[
              { value: "", label: s("ret.existing_ph") },
              ...options.returns,
            ]}
            aria-label={s("ret.existing")}
          />
        </Field>
        <div className="mt-4 grid gap-4 md:grid-cols-3">
          <Field label={s("ret.no")}>
            <Input
              value={doc?.return_no ?? ""}
              readOnly
              className="bg-surface-muted"
              aria-label={s("ret.no")}
            />
          </Field>
          <Field label={s("ret.mrr")}>
            <Input
              value={doc?.mrr_no ?? ""}
              readOnly
              className="bg-surface-muted"
              aria-label={s("ret.mrr")}
            />
          </Field>
          <Field label={s("supplier")}>
            <Input
              value={doc?.supplier ?? ""}
              readOnly
              className="bg-surface-muted"
              aria-label={s("supplier")}
            />
          </Field>
          <Field label={s("ret.date")} required>
            <Input
              type="date"
              value={head.return_date}
              onChange={(e) => setH({ return_date: e.target.value })}
              disabled={!editable}
              aria-label={s("ret.date")}
            />
          </Field>
          <Field label={s("ret.type")} required>
            <Input
              value={doc ? s(`ret.type_v.${doc.type}`) : ""}
              readOnly
              className="bg-surface-muted"
              aria-label={s("ret.type")}
            />
          </Field>
          <div />
          <Field
            label={s("ret.challan_type")}
            required
            hint={s("ret.challan_hint")}
          >
            <PSelect
              value={head.challan_type}
              onValueChange={(v) => setH({ challan_type: v ?? "" })}
              options={[
                { value: "", label: "Select..." },
                ...["RETURNABLE", "NON_RETURNABLE"].map((v) => ({
                  value: v,
                  label: s(`ret.challan_v.${v}`),
                })),
              ]}
              disabled={!editable}
              aria-label={s("ret.challan_type")}
            />
          </Field>
          <label className="flex items-center gap-2 self-center text-[13px] font-semibold">
            <Checkbox
              checked={head.finalized}
              onCheckedChange={(v) => setH({ finalized: Boolean(v) })}
              disabled={!editable}
              aria-label={s("ret.finalized")}
            />
            {s("ret.finalized")}
          </label>
          <div />
          <Field label={s("reason")} className="md:col-span-3">
            <Textarea
              rows={2}
              value={head.reason}
              onChange={(e) => setH({ reason: e.target.value })}
              maxLength={2000}
              disabled={!editable}
              aria-label={s("reason")}
            />
          </Field>
        </div>
      </section>

      <section className={cn(card, "overflow-hidden")}>
        <div className="px-5 py-4">
          <h2 className="text-base font-semibold text-foreground">
            {s("ret.lines")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {s("ret.lines_hint")}
          </p>
        </div>
        {lines.length === 0 ? (
          <NoLines title={s("ret.no_lines")} hint={s("ret.no_lines_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  <th className={th}>{s("col.line")}</th>
                  <th className={cn(th, "text-right")}>{s("col.recv_qty")}</th>
                  <th className={cn(th, "text-right")}>{s("col.balance")}</th>
                  <th className={cn(th, "text-right")}>{s("col.qty")}</th>
                  <th className={th}>{y("col.remarks")}</th>
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr
                    key={l.receipt_line_id}
                    className="border-t border-border"
                  >
                    <td className="px-2 py-2">{l.label}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(l.recv_qty)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(l.left)}
                    </td>
                    <td className="px-2 py-1.5">
                      <div className="flex justify-end">
                        <QtyInput
                          value={l.qty}
                          onChange={(v) =>
                            setLines((all) =>
                              all.map((x, j) =>
                                j === i ? { ...x, qty: v } : x,
                              ),
                            )
                          }
                          disabled={!editable}
                          label={`${s("col.qty")} ${i + 1}`}
                        />
                      </div>
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={l.remarks}
                        onChange={(e) =>
                          setLines((all) =>
                            all.map((x, j) =>
                              j === i ? { ...x, remarks: e.target.value } : x,
                            ),
                          )
                        }
                        maxLength={300}
                        className="h-8 w-56"
                        disabled={!editable}
                        aria-label={`${y("col.remarks")} ${i + 1}`}
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot className="bg-surface-muted font-semibold">
                <tr className="border-t-2 border-border">
                  <td colSpan={3} className="px-2 py-2">
                    {s("total_row")}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(lines.reduce((t, l) => t + num(l.qty), 0))}
                  </td>
                  <td />
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </section>

      {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
      <section className={cn(card, "flex flex-col gap-3 p-5")}>
        <p className="text-[13px] text-foreground-muted">{s("ret.foot")}</p>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" onClick={clear} disabled={pending}>
            <RotateCcw /> {y("clear")}
          </Button>
          {editable ? (
            <>
              <Button
                variant="outline"
                className="text-danger"
                onClick={() => setConfirm(true)}
                disabled={pending}
              >
                <Trash2 /> {s("ret.delete")}
              </Button>
              <Button onClick={save} loading={pending}>
                <Save /> {s("ret.save")}
              </Button>
            </>
          ) : null}
        </div>
      </section>
      <ConfirmDialog
        open={confirm}
        onOpenChange={setConfirm}
        title={s("ret.delete")}
        description={doc ? y("confirm_delete", { no: doc.return_no }) : ""}
        confirmLabel={s("ret.delete")}
        cancelLabel={y("cancel")}
        destructive
        onConfirm={remove}
        loading={pending}
      />
    </div>
  );
}

/** Daily Yarn Receipt Return: the return challans, dates inclusive of both ends. */
function ReturnList({ options, initialRows, act }) {
  const y = useYarn();
  const s = useYs();
  const blank = { supplier: "", from: "", to: "" };
  const [filters, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((f) => ({ ...f, [k]: v ?? "" }));

  function search(f = filters) {
    startTransition(async () => {
      const qs = new URLSearchParams(Object.entries(f).filter(([, v]) => v));
      const res = await act(`returns?${qs}`);
      if (res?.status === "success") {
        setRows(res.data);
        setError(null);
      } else setError(messagesOf(res));
    });
  }

  return (
    <section className={card}>
      <div className="px-5 pt-5">
        <h2 className="text-base font-semibold text-foreground">
          {s("tabs.rr_list")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {s("ret.list_hint")}
        </p>
      </div>
      <div className="grid gap-4 px-5 pt-4 sm:grid-cols-3">
        <Field label={s("supplier")}>
          <PSelect
            value={filters.supplier}
            onValueChange={(v) => setF("supplier", v)}
            options={[
              { value: "", label: s("supplier_ph") },
              ...options.suppliers,
            ]}
            aria-label={s("supplier")}
          />
        </Field>
        <Field label={s("from")}>
          <Input
            type="date"
            value={filters.from}
            onChange={(e) => setF("from", e.target.value)}
            aria-label={s("from")}
          />
        </Field>
        <Field label={s("to")}>
          <Input
            type="date"
            value={filters.to}
            onChange={(e) => setF("to", e.target.value)}
            aria-label={s("to")}
          />
        </Field>
      </div>
      <div className="flex flex-wrap gap-2 px-5 pt-4 pb-5">
        <Button onClick={() => search()} loading={pending}>
          <Search /> {s("search")}
        </Button>
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
        <NoLines title={s("ret.empty")} hint={s("mrr.empty_hint")} />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[1100px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {[
                  "no",
                  "date",
                  "mrr",
                  "supplier",
                  "type",
                  "challan_type",
                  "lines",
                  "qty",
                  "state",
                  "reason",
                ].map((k) => (
                  <th
                    key={k}
                    className={cn(
                      th,
                      ["lines", "qty"].includes(k) && "text-right",
                    )}
                  >
                    {s(`ret.lc.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.return_no} className="border-t border-border">
                  <td className="px-2 py-2 font-medium whitespace-nowrap">
                    <Link
                      href={`${RR}?tab=entry&ref=${encodeURIComponent(r.return_no)}`}
                      className="text-brand hover:underline"
                    >
                      {r.return_no}
                    </Link>
                  </td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {dayFmt(r.return_date)}
                  </td>
                  <td className="px-2 py-2 whitespace-nowrap">
                    {r.mrr_no ?? "—"}
                  </td>
                  <td className="px-2 py-2">{r.supplier ?? "—"}</td>
                  <td className="px-2 py-2">{s(`ret.type_v.${r.type}`)}</td>
                  <td className="px-2 py-2">
                    {s(`ret.challan_v.${r.challan_type}`)}
                  </td>
                  <td className="px-2 py-2 text-right tabular-nums">
                    {r.lines}
                  </td>
                  <td className="px-2 py-2 text-right font-semibold tabular-nums">
                    {qtyFmt.format(r.qty)}
                  </td>
                  <td className="px-2 py-2">
                    <StatusChip
                      status={r.finalized ? "YES" : "NO"}
                      label={s(r.finalized ? "yes" : "no")}
                    />
                  </td>
                  <td className="px-2 py-2">{r.reason ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

export { MrrList, MrrEntry, ReturnDesk, ReturnList };
