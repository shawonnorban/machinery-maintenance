"use client";

import { useState, useTransition } from "react";
import {
  CalendarCheck,
  CalendarX,
  Lock,
  PackageCheck,
  RotateCcw,
  Save,
  Search,
  TriangleAlert,
  Warehouse,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import {
  PSelect,
  str,
  num,
  qtyFmt,
  dayFmt,
  messagesOf,
  useYarn,
  useYs,
  card,
  th,
  Field,
  StatusChip,
  NoLines,
  QtyInput,
} from "./yarn-common";
import { ampm } from "@/lib/clock";

/*
 * Knit Production → Yarn Store (AnnotechERP 15/03): the Yarn Racking Board
 * (the dashboard of every stock line — received, back, racked, pending — and
 * Rack Assignment, a receipt line at a time), the Yarn Opening Entry grid
 * (one store's month; only Adjustment and its reason are typed) and Yarn
 * Store Month Closing (Show Exception, Month Close). Nothing reloads.
 */

const qs = (f) =>
  new URLSearchParams(Object.entries(f).filter(([, v]) => v)).toString();

function Chips({ items }) {
  return (
    <div className="flex flex-wrap gap-2">
      {items.map(([label, v, tone]) => (
        <span
          key={label}
          className="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-1.5 text-[13px]"
        >
          {label}
          <span
            className={cn(
              "rounded-full px-2 text-xs font-semibold tabular-nums",
              tone ?? "bg-brand-subtle text-brand",
            )}
          >
            {v}
          </span>
        </span>
      ))}
    </div>
  );
}

function Empty({ icon: Icon = Warehouse, title, hint }) {
  return (
    <div className="flex flex-col items-center px-5 py-12 text-center">
      <span className="flex size-12 items-center justify-center rounded-xl border border-border">
        <Icon className="size-5 text-foreground-muted" />
      </span>
      <p className="mt-3 text-sm font-semibold text-foreground">{title}</p>
      <p className="mt-1 text-xs text-foreground-muted">{hint}</p>
    </div>
  );
}

function RackBoard({ options, initialRows, act }) {
  const y = useYarn();
  const s = useYs();
  const toastManager = useToastManager();
  const blank = {
    store: "",
    buyer: "",
    brand: "",
    lot: "",
    rack: "",
    buffer: "",
    from: "",
    to: "",
  };
  const [filters, setFilters] = useState(blank);
  const [rows, setRows] = useState(initialRows);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((f) => ({ ...f, [k]: v ?? "" }));
  const all = (list) => [{ value: "", label: s("all") }, ...list];

  function search(f = filters) {
    startTransition(async () => {
      const res = await act(`racks/board?${qs(f)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setError(null);
      toastManager.add({
        title: s("rack.found", { count: res.data.length }),
        type: "success",
      });
    });
  }
  const sum = (k) => rows.reduce((t, r) => t + r[k], 0);

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold text-foreground">
          {s("rack.search_title")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {s("rack.board_hint")}
        </p>
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Field label={s("store")}>
            <PSelect
              value={filters.store}
              onValueChange={(v) => setF("store", v)}
              options={all(options.store_codes)}
              aria-label={s("store")}
            />
          </Field>
          <Field label={s("col.buyer")}>
            <PSelect
              value={filters.buyer}
              onValueChange={(v) => setF("buyer", v)}
              options={all(options.buyers)}
              aria-label={s("col.buyer")}
            />
          </Field>
          <Field label={s("brand")}>
            <Input
              value={filters.brand}
              onChange={(e) => setF("brand", e.target.value)}
              placeholder={s("all")}
              aria-label={s("brand")}
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
          <Field label={s("col.rack_no")}>
            <Input
              value={filters.rack}
              onChange={(e) => setF("rack", e.target.value.toUpperCase())}
              placeholder={s("all")}
              aria-label={s("col.rack_no")}
            />
          </Field>
          <Field label={s("buffer")}>
            <PSelect
              value={filters.buffer}
              onValueChange={(v) => setF("buffer", v)}
              options={all(
                ["YES", "NO"].map((v) => ({
                  value: v,
                  label: s(v === "YES" ? "yes" : "no"),
                })),
              )}
              aria-label={s("buffer")}
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
        <div className="mt-4 flex justify-between gap-2 border-t border-border pt-4">
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
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>
        <div className="border-b border-border px-5 py-4">
          <Chips
            items={[
              [s("rack.chip.lines"), rows.length],
              [s("rack.chip.recv"), qtyFmt.format(sum("recv"))],
              [
                s("rack.chip.racked"),
                qtyFmt.format(sum("racked")),
                "bg-success-subtle text-success",
              ],
              [
                s("rack.chip.pending"),
                qtyFmt.format(sum("pending")),
                "bg-warning-subtle text-warning",
              ],
            ]}
          />
        </div>
        {rows.length === 0 ? (
          <Empty title={s("rack.empty")} hint={s("rack.empty_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1500px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "store",
                    "yarn",
                    "brand",
                    "lot",
                    "supplier",
                    "buyer",
                    "receive",
                    "returned",
                    "racked",
                    "pending",
                    "balance",
                    "racks",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        [
                          "receive",
                          "returned",
                          "racked",
                          "pending",
                          "balance",
                        ].includes(k) && "text-right",
                      )}
                    >
                      {s(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.lot_id} className="border-t border-border">
                    <td className="px-2 py-2">{r.store ?? "—"}</td>
                    <td className="px-2 py-2">{r.yarn || "—"}</td>
                    <td className="px-2 py-2">{r.brand ?? "—"}</td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {r.lot_no ?? "—"}
                    </td>
                    <td className="px-2 py-2">{r.supplier ?? "—"}</td>
                    <td className="px-2 py-2">
                      {r.buyer ?? (r.is_buffer ? s("buffer_stock") : "—")}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.recv)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.returned)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.racked)}
                    </td>
                    <td
                      className={cn(
                        "px-2 py-2 text-right font-semibold tabular-nums",
                        r.pending > 0 && "text-warning",
                      )}
                    >
                      {qtyFmt.format(r.pending)}
                    </td>
                    <td className="px-2 py-2 text-right font-semibold tabular-nums">
                      {qtyFmt.format(r.balance)}
                    </td>
                    <td className="px-2 py-2">
                      {r.racks.length
                        ? r.racks
                            .map((x) => `${x.rack_no}: ${qtyFmt.format(x.qty)}`)
                            .join(", ")
                        : "—"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}

function RackAssign({ options, canManage, act }) {
  const y = useYarn();
  const s = useYs();
  const toastManager = useToastManager();
  const blank = {
    store: "",
    composition: "",
    brand: "",
    lot: "",
    from: "",
    to: "",
    pending: "YES",
  };
  const [filters, setFilters] = useState(blank);
  const [rows, setRows] = useState(null);
  const [put, setPut] = useState({});
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const setF = (k, v) => setFilters((f) => ({ ...f, [k]: v ?? "" }));
  const setP = (id, patch) =>
    setPut((p) => ({ ...p, [id]: { ...p[id], ...patch } }));

  function search(f = filters) {
    if (!f.store) return setError([s("rack.store_first")]);
    startTransition(async () => {
      const res = await act(`racks/pending?${qs(f)}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      setRows(res.data);
      setPut({});
      setError(null);
    });
  }
  function rack() {
    const chosen = (rows ?? [])
      .filter((r) => str(put[r.receipt_line_id]?.rack_no).trim())
      .map((r) => ({
        receipt_line_id: r.receipt_line_id,
        rack_no: put[r.receipt_line_id].rack_no.trim(),
        qty: put[r.receipt_line_id].qty
          ? Number(put[r.receipt_line_id].qty)
          : null,
      }));
    if (!chosen.length) return setError([s("rack.pick_rows")]);
    startTransition(async () => {
      const res = await act("racks", "POST", { rows: chosen });
      if (res?.status !== "success") return setError(messagesOf(res));
      toastManager.add({
        title: s("rack.racked_done", { count: res.data.count }),
        type: "success",
      });
      search();
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "p-5")}>
        <h2 className="text-base font-semibold text-foreground">
          {s("rack.assign_title")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {s("rack.assign_hint")}
        </p>
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Field label={s("store")} required>
            <PSelect
              value={filters.store}
              onValueChange={(v) => setF("store", v)}
              options={[
                { value: "", label: s("select") },
                ...options.store_codes,
              ]}
              aria-label={s("store")}
            />
          </Field>
          <Field label={s("yarn")}>
            <PSelect
              value={filters.composition}
              onValueChange={(v) => setF("composition", v)}
              options={[
                { value: "", label: s("all") },
                ...options.compositions,
              ]}
              aria-label={s("yarn")}
            />
          </Field>
          <Field label={s("brand")}>
            <Input
              value={filters.brand}
              onChange={(e) => setF("brand", e.target.value)}
              aria-label={s("brand")}
            />
          </Field>
          <Field label={s("lot")}>
            <Input
              value={filters.lot}
              onChange={(e) => setF("lot", e.target.value)}
              aria-label={s("lot")}
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
          <Field label={s("rack.pending_only")}>
            <PSelect
              value={filters.pending}
              onValueChange={(v) => setF("pending", v ?? "YES")}
              options={[
                { value: "ALL", label: s("all") },
                { value: "YES", label: s("yes") },
                { value: "NO", label: s("no") },
              ]}
              aria-label={s("rack.pending_only")}
            />
          </Field>
        </div>
        <div className="mt-4 flex gap-2 border-t border-border pt-4">
          <Button onClick={() => search()} loading={pending}>
            <Search /> {s("search")}
          </Button>
          {canManage ? (
            <Button
              variant="outline"
              onClick={rack}
              disabled={pending || !rows?.length}
            >
              <PackageCheck /> {s("rack.rack")}
            </Button>
          ) : null}
        </div>
      </section>
      {error ? <Alert variant="danger">{error.join(" · ")}</Alert> : null}
      <section className={card}>
        {rows === null ? (
          <Empty title={s("rack.pick_title")} hint={s("rack.pick")} />
        ) : rows.length === 0 ? (
          <Empty title={s("rack.empty")} hint={s("rack.empty_hint")} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1400px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "mrr",
                    "mrr_date",
                    "yarn",
                    "brand",
                    "lot",
                    "recv_qty",
                    "returned",
                    "racked",
                    "pending",
                    "racks",
                    "rack_no",
                    "rack_qty",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        [
                          "recv_qty",
                          "returned",
                          "racked",
                          "pending",
                          "rack_qty",
                        ].includes(k) && "text-right",
                      )}
                    >
                      {s(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((r, i) => (
                  <tr
                    key={r.receipt_line_id}
                    className="border-t border-border"
                  >
                    <td className="px-2 py-2 font-medium whitespace-nowrap">
                      {r.mrr_no}
                    </td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {dayFmt(r.mrr_date)}
                    </td>
                    <td className="px-2 py-2">{r.yarn || "—"}</td>
                    <td className="px-2 py-2">{r.brand ?? "—"}</td>
                    <td className="px-2 py-2">{r.lot_no ?? "—"}</td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.recv_qty)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.returned)}
                    </td>
                    <td className="px-2 py-2 text-right tabular-nums">
                      {qtyFmt.format(r.racked)}
                    </td>
                    <td className="px-2 py-2 text-right font-semibold tabular-nums">
                      {qtyFmt.format(r.pending)}
                    </td>
                    <td className="px-2 py-2">{r.racks || "—"}</td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={str(put[r.receipt_line_id]?.rack_no)}
                        onChange={(e) =>
                          setP(r.receipt_line_id, {
                            rack_no: e.target.value.toUpperCase(),
                          })
                        }
                        maxLength={40}
                        className="h-8 w-24"
                        disabled={!canManage || r.pending <= 0}
                        aria-label={`${s("col.rack_no")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-1.5">
                      <div className="flex justify-end">
                        <QtyInput
                          value={put[r.receipt_line_id]?.qty}
                          onChange={(v) => setP(r.receipt_line_id, { qty: v })}
                          disabled={!canManage || r.pending <= 0}
                          label={`${s("col.rack_qty")} ${i + 1}`}
                        />
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <p className="border-t border-border px-5 py-3 text-[13px] text-foreground-muted">
          {s("rack.assign_foot")}
        </p>
      </section>
    </div>
  );
}

/** Yarn Opening Entry: one store's month per stock line; only Adjustment and Reason of Adj are typed. */
function OpeningGrid({ options, canManage, act }) {
  const y = useYarn();
  const s = useYs();
  const toastManager = useToastManager();
  const [store, setStore] = useState("");
  const [period, setPeriod] = useState("");
  const [grid, setGrid] = useState(null);
  const [typed, setTyped] = useState({});
  const noFilter = {
    standing: "",
    count: "",
    colour: "",
    composition: "",
    brand: "",
    lot: "",
  };
  const [f, setFilter] = useState(noFilter);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const signed = grid?.rows.some((r) => r.finalized);
  const locked = !canManage || grid?.closed || signed;
  const take = (data, closed) => {
    setGrid({ rows: data.rows, closed: closed ?? data.closed });
    setTyped(
      Object.fromEntries(
        data.rows.map((r) => [
          r.lot_id,
          {
            adjustment: r.adjustment ? String(r.adjustment) : "",
            reason: str(r.reason),
          },
        ]),
      ),
    );
  };
  const setF = (k, v) => setFilter((x) => ({ ...x, [k]: v ?? "" }));
  // Closing as it will stand: the stored closing less the stored adjustment, plus what is typed now.
  const closingOf = (r) =>
    r.closing - r.adjustment + num(typed[r.lot_id]?.adjustment);
  // Standing: Running holds yarn, Closable has come to nothing, Finalized is signed.
  const standingOf = (r) =>
    r.finalized ? "FINALIZED" : closingOf(r) > 0.00005 ? "RUNNING" : "CLOSABLE";
  const has = (v, q) =>
    String(v ?? "")
      .toLowerCase()
      .includes(q.toLowerCase());
  const shown = (grid?.rows ?? []).filter(
    (r) =>
      (!f.standing || standingOf(r) === f.standing) &&
      (!f.count || r.yarn_count_id === f.count) &&
      (!f.colour || r.colour === f.colour) &&
      (!f.composition || r.composition_id === f.composition) &&
      (!f.brand || has(r.brand, f.brand)) &&
      (!f.lot || has(r.lot_no, f.lot)),
  );
  const params = () =>
    `store=${encodeURIComponent(store)}&period=${encodeURIComponent(period)}`;

  function search() {
    if (!store) return setError([s("op.store_first")]);
    if (!period) return setError([s("v.period")]);
    startTransition(async () => {
      const res = await act(`period?${params()}`);
      if (res?.status !== "success") return setError(messagesOf(res));
      take(res.data);
      setError(null);
    });
  }
  function save() {
    startTransition(async () => {
      const res = await act("period", "PUT", {
        store,
        period,
        rows: grid.rows.map((r) => ({
          lot_id: r.lot_id,
          adjustment:
            typed[r.lot_id]?.adjustment === ""
              ? 0
              : Number(typed[r.lot_id]?.adjustment ?? 0),
          reason: typed[r.lot_id]?.reason || null,
        })),
      });
      if (res?.status !== "success") return setError(messagesOf(res));
      take(res.data, grid.closed);
      setError(null);
      toastManager.add({ title: s("op.saved"), type: "success" });
    });
  }
  function finalize() {
    startTransition(async () => {
      const res = await act("period/finalize", "POST", { store, period });
      if (res?.status !== "success") return setError(messagesOf(res));
      take(res.data, grid.closed);
      setError(null);
      toastManager.add({ title: s("op.done"), type: "success" });
    });
  }
  const storeLabel =
    options.store_codes.find((c) => c.value === store)?.label ?? "";

  return (
    <section className={card}>
      <div className="flex items-start justify-between gap-3 px-5 pt-5">
        <div>
          <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
            <CalendarCheck className="size-4" /> {s("op.title")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {s("op.hint")}
          </p>
        </div>
        <span className="rounded-full border border-border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap">
          {s("op.lines", { count: grid?.rows.length ?? 0 })}
        </span>
      </div>
      <div className="grid gap-4 px-5 pt-4 md:grid-cols-3">
        <Field label={s("company")}>
          <PSelect
            value={options.company.value}
            options={[options.company]}
            disabled
            aria-label={s("company")}
          />
        </Field>
        <Field label={s("store")}>
          <PSelect
            value={store}
            onValueChange={(v) => (setStore(v ?? ""), setGrid(null))}
            options={[
              { value: "", label: s("select") },
              ...options.store_codes,
            ]}
            aria-label={s("store")}
          />
        </Field>
        <Field label={s("month")}>
          <PSelect
            value={period}
            onValueChange={(v) => (setPeriod(v ?? ""), setGrid(null))}
            options={[
              { value: "", label: s("month_ph") },
              ...options.periods.map((p) => ({ value: p, label: p })),
            ]}
            aria-label={s("month")}
          />
        </Field>
        <Field label={s("op.standing")}>
          <PSelect
            value={f.standing}
            onValueChange={(v) => setF("standing", v)}
            options={[
              { value: "", label: y("any") },
              ...["RUNNING", "CLOSABLE", "FINALIZED"].map((v) => ({
                value: v,
                label: s(`op.standing_v.${v}`),
              })),
            ]}
            aria-label={s("op.standing")}
          />
        </Field>
        <Field label={s("op.count")}>
          <PSelect
            value={f.count}
            onValueChange={(v) => setF("count", v)}
            options={[{ value: "", label: s("all") }, ...options.counts]}
            aria-label={s("op.count")}
          />
        </Field>
        <Field label={s("op.colour_group")}>
          <PSelect
            value={f.colour}
            onValueChange={(v) => setF("colour", v)}
            options={[
              { value: "", label: s("all") },
              ...options.colours.map((c) => ({ value: c, label: c })),
            ]}
            aria-label={s("op.colour_group")}
          />
        </Field>
        <Field label={s("op.composition")}>
          <PSelect
            value={f.composition}
            onValueChange={(v) => setF("composition", v)}
            options={[{ value: "", label: s("all") }, ...options.compositions]}
            aria-label={s("op.composition")}
          />
        </Field>
        <Field label={s("brand")}>
          <Input
            value={f.brand}
            onChange={(e) => setF("brand", e.target.value)}
            placeholder={s("all")}
            aria-label={s("brand")}
          />
        </Field>
        <Field label={s("lot")}>
          <Input
            value={f.lot}
            onChange={(e) => setF("lot", e.target.value)}
            placeholder={s("all")}
            aria-label={s("lot")}
          />
        </Field>
      </div>
      <div className="flex flex-wrap gap-2 px-5 pt-4 pb-5">
        <Button variant="outline" onClick={search} loading={pending}>
          <Search /> {s("search")}
        </Button>
        {grid && !locked ? (
          <>
            <Button onClick={save} disabled={pending || !grid.rows.length}>
              <Save /> {s("save")}
            </Button>
            <Button
              variant="outline"
              onClick={finalize}
              disabled={pending || !grid.rows.length}
            >
              <Lock /> {s("op.finalise_all")}
            </Button>
          </>
        ) : null}
      </div>
      {error ? (
        <div className="px-5 pb-4">
          <Alert variant="danger">{error.join(" · ")}</Alert>
        </div>
      ) : null}
      {grid?.closed ? (
        <div className="px-5 pb-4">
          <Alert variant="warning">{s("op.closed")}</Alert>
        </div>
      ) : signed ? (
        <div className="px-5 pb-4">
          <Alert variant="success">{s("op.finalized_note")}</Alert>
        </div>
      ) : null}
      {grid === null ? (
        <Empty
          icon={CalendarCheck}
          title={s("op.pick_title")}
          hint={s("op.pick")}
        />
      ) : shown.length === 0 ? (
        <Empty
          icon={CalendarCheck}
          title={s("rack.empty")}
          hint={s("rack.empty_hint")}
        />
      ) : (
        <div className="border-t border-border">
          <p className="px-5 py-2 text-[13px] text-foreground-muted">
            {s("op.summary", {
              store: storeLabel,
              month: period,
              lines: shown.length,
              qty: qtyFmt.format(shown.reduce((t, r) => t + closingOf(r), 0)),
            })}
          </p>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1600px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {[
                    "yarn",
                    "brand",
                    "lot",
                    "source",
                    "opening",
                    "receive",
                    "returned",
                    "adjusted",
                    "issued",
                    "adjustment",
                    "adj_reason",
                    "closing",
                  ].map((k) => (
                    <th
                      key={k}
                      className={cn(
                        th,
                        [
                          "opening",
                          "receive",
                          "returned",
                          "adjusted",
                          "issued",
                          "adjustment",
                          "closing",
                        ].includes(k) && "text-right",
                      )}
                    >
                      {s(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {shown.map((r, i) => (
                  <tr key={r.lot_id} className="border-t border-border">
                    <td className="px-2 py-2">{r.yarn || "—"}</td>
                    <td className="px-2 py-2">{r.brand ?? "—"}</td>
                    <td className="px-2 py-2 whitespace-nowrap">
                      {r.lot_no ?? "—"}
                    </td>
                    <td className="px-2 py-2">
                      {r.source ? s(`store_source.${r.source}`) : "—"}
                    </td>
                    {[
                      "opening",
                      "receive",
                      "returned",
                      "adjusted",
                      "issued",
                    ].map((k) => (
                      <td key={k} className="px-2 py-2 text-right tabular-nums">
                        {qtyFmt.format(r[k])}
                      </td>
                    ))}
                    <td className="px-2 py-1.5">
                      <div className="flex justify-end">
                        <Input
                          type="number"
                          step="any"
                          value={typed[r.lot_id]?.adjustment ?? ""}
                          onChange={(e) =>
                            setTyped((t) => ({
                              ...t,
                              [r.lot_id]: {
                                ...t[r.lot_id],
                                adjustment: e.target.value,
                              },
                            }))
                          }
                          className="h-8 w-24 text-right tabular-nums"
                          disabled={locked}
                          aria-label={`${s("col.adjustment")} ${i + 1}`}
                        />
                      </div>
                    </td>
                    <td className="px-2 py-1.5">
                      <Input
                        value={typed[r.lot_id]?.reason ?? ""}
                        onChange={(e) =>
                          setTyped((t) => ({
                            ...t,
                            [r.lot_id]: {
                              ...t[r.lot_id],
                              reason: e.target.value,
                            },
                          }))
                        }
                        maxLength={255}
                        className="h-8 w-48"
                        disabled={locked}
                        aria-label={`${s("col.adj_reason")} ${i + 1}`}
                      />
                    </td>
                    <td className="px-2 py-2 text-right font-semibold tabular-nums">
                      {qtyFmt.format(closingOf(r))}
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot className="bg-surface-muted font-semibold">
                <tr className="border-t-2 border-border">
                  <td colSpan={4} className="px-2 py-2">
                    {s("total_row")}
                  </td>
                  {["opening", "receive", "returned", "adjusted", "issued"].map(
                    (k) => (
                      <td key={k} className="px-2 py-2 text-right tabular-nums">
                        {qtyFmt.format(shown.reduce((t, r) => t + r[k], 0))}
                      </td>
                    ),
                  )}
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(
                      shown.reduce(
                        (t, r) => t + num(typed[r.lot_id]?.adjustment),
                        0,
                      ),
                    )}
                  </td>
                  <td />
                  <td className="px-2 py-2 text-right tabular-nums">
                    {qtyFmt.format(shown.reduce((t, r) => t + closingOf(r), 0))}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      )}
      <p className="border-t border-border px-5 py-3 text-[13px] text-foreground-muted">
        {s("op.pick_title")} {s("op.foot")}
      </p>
    </section>
  );
}

/** Yarn Store Month Closing: Show lists the closed months; Show Exception what stops one; Month Close strikes it. */
function MonthClose({ options, initialCloses, canManage, act }) {
  const y = useYarn();
  const s = useYs();
  const toastManager = useToastManager();
  const [period, setPeriod] = useState("");
  const [closes, setCloses] = useState(initialCloses);
  const [view, setView] = useState("closes");
  const [found, setFound] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  // A month is closed after it has finished.
  const finished = options.periods.slice(1);
  const done = closes.find((c) => c.period === period);

  function show() {
    startTransition(async () => {
      const res = await act("closes");
      if (res?.status !== "success") return setError(messagesOf(res));
      setCloses(res.data);
      setView("closes");
      setError(null);
    });
  }
  function exceptions() {
    if (!period) return setError([s("v.period")]);
    startTransition(async () => {
      const res = await act(
        `closes/exceptions?period=${encodeURIComponent(period)}`,
      );
      if (res?.status !== "success") return setError(messagesOf(res));
      setFound(res.data.exceptions);
      setView("exceptions");
      setError(null);
    });
  }
  function close() {
    if (!period) return setError([s("v.period")]);
    startTransition(async () => {
      const res = await act("closes", "POST", { period });
      if (res?.status !== "success") return setError(messagesOf(res));
      toastManager.add({
        title: s("mc.closed_done", { month: period }),
        type: "success",
      });
      show();
    });
  }

  return (
    <section className={card}>
      <div className="px-5 pt-5">
        <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
          <CalendarX className="size-4" /> {s("mc.title")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">
          {s("mc.hint")}
        </p>
      </div>
      <div className="flex flex-wrap items-end gap-3 px-5 pt-4 pb-5">
        <Field label={s("month")} hint={s("mc.finished")} className="w-64">
          <PSelect
            value={period}
            onValueChange={(v) => setPeriod(v ?? "")}
            options={[
              { value: "", label: s("mc.month_ph") },
              ...finished.map((p) => ({ value: p, label: p })),
            ]}
            aria-label={s("month")}
          />
        </Field>
        <div className="flex flex-wrap gap-2 pb-6">
          <Button variant="outline" onClick={show} disabled={pending}>
            <Search /> {s("mc.show")}
          </Button>
          {canManage ? (
            <Button onClick={close} disabled={pending || Boolean(done)}>
              <Lock /> {s("mc.close")}
            </Button>
          ) : null}
          <Button variant="outline" onClick={exceptions} disabled={pending}>
            <TriangleAlert /> {s("mc.exceptions")}
          </Button>
          <Button
            variant="outline"
            onClick={() => (
              setPeriod(""),
              setFound(null),
              setView("closes"),
              setError(null)
            )}
            disabled={pending}
          >
            <RotateCcw /> {s("mc.reset")}
          </Button>
        </div>
      </div>
      {error ? (
        <div className="px-5 pb-4">
          <Alert variant="danger">{error.join(" · ")}</Alert>
        </div>
      ) : null}
      {done ? (
        <div className="px-5 pb-4">
          <Alert variant="info">
            {s("mc.closed_already", {
              month: done.period,
              at: done.closed_at,
              by: done.closed_by ?? "—",
            })}
          </Alert>
        </div>
      ) : null}
      {view === "exceptions" ? (
        found.length === 0 ? (
          <div className="px-5 pb-5">
            <Alert variant="success">{s("mc.clear", { month: period })}</Alert>
          </div>
        ) : (
          <div className="overflow-x-auto border-t border-border">
            <table className="w-full min-w-[800px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["kind", "ref", "note"].map((k) => (
                    <th key={k} className={th}>
                      {s(`mc.lc.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {found.map((e) => (
                  <tr
                    key={`${e.kind}-${e.ref}`}
                    className="border-t border-border"
                  >
                    <td className="px-2 py-2">
                      <StatusChip
                        status="PART_RECEIVED"
                        label={s(`mc.kind.${e.kind}`)}
                      />
                    </td>
                    <td className="px-2 py-2 font-medium whitespace-nowrap">
                      {e.ref}
                    </td>
                    <td className="px-2 py-2">{e.note}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      ) : closes.length === 0 ? (
        <Empty
          icon={CalendarX}
          title={s("mc.none")}
          hint={s("mc.empty_hint")}
        />
      ) : (
        <div className="overflow-x-auto border-t border-border">
          <table className="w-full min-w-[600px] text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                {["period", "closed_at", "closed_by"].map((k) => (
                  <th key={k} className={th}>
                    {s(`mc.lc.${k}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {closes.map((c) => (
                <tr key={c.period} className="border-t border-border">
                  <td className="px-2 py-2 font-medium">{c.period}</td>
                  <td className="px-2 py-2">{ampm(c.closed_at)}</td>
                  <td className="px-2 py-2">{c.closed_by ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

export { RackBoard, RackAssign, OpeningGrid, MonthClose };
