"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { ArrowDownToLine, ArrowUpFromLine, Boxes, CircleCheck, Clock, Download, Layers, Package, PackageCheck, PackagePlus, Search, Ship, Truck, Users, Warehouse } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { StatCard } from "@/components/ui/stat-card";
import { Tabs, TabsIndicator, TabsList, TabsPanel, TabsTab } from "@/components/ui/tabs";
import { Chip, Panel, at, downloadCsv, firstError, nativeSelect, num, useWords } from "@/components/assembly/finishing";

/*
 * Assembly Floor → Ready For Shipment (AnnotechERP 20/06), as Brickland lays
 * its two screens out under the Ready Dashboard / Finished Goods Stock
 * buttons: the Ready for Shipment Dashboard (eight key figures, the filters,
 * the Ready for Shipment List — one row per PO and colour — with CSV and a
 * row's detail) and Finished Goods Stock (four key figures, the search, and
 * the Stock and Ledger tabs, each with CSV).
 */

const STATUS_TONE = { READY: "bg-success/15 text-success", PARTIALLY_SHIPPED: "bg-warning/15 text-warning", SHIPMENT_COMPLETED: "bg-info-subtle text-info" };
const BATCH_TONE = { PACKED_OUT: "bg-success/15 text-success" };
const day = (s) => {
  if (!s) return "—";
  const [y, m, d] = String(s).slice(0, 10).split("-");
  return `${d}-${m}-${y}`;
};

/** The Ready Dashboard / Finished Goods Stock buttons over both screens. */
function ReadyNav({ active }) {
  const w = useWords();
  const items = [
    ["ready", "/assembly-floor/ready-dashboard", "rs.nav.ready", Truck],
    ["stock", "/assembly-floor/finished-goods-stock", "rs.nav.stock", Warehouse],
    ["entry", "/assembly-floor/shipment-entry", "rs.nav.entry", PackagePlus],
  ];
  return (
    <nav className="mb-4 flex flex-wrap gap-2" aria-label={w("rs.nav.label")}>
      {items.map(([key, href, label, Icon]) => (
        <Link
          key={key}
          href={href}
          aria-current={key === active ? "page" : undefined}
          className={cn(
            "flex h-9 items-center gap-1.5 rounded-lg border px-3 text-[13px] font-semibold transition-colors",
            key === active ? "border-brand bg-brand text-brand-foreground" : "border-border bg-surface text-foreground hover:bg-surface-muted",
          )}
        >
          <Icon className="size-4" /> {w(label)}
        </Link>
      ))}
    </nav>
  );
}

function Empty({ children }) {
  return <p className="px-5 py-8 text-center text-sm text-foreground-muted">{children}</p>;
}

function Th({ children, right }) {
  return (
    <th scope="col" className={cn("border border-border px-2 py-2 text-left font-medium whitespace-nowrap", right && "text-right")}>
      {children}
    </th>
  );
}

function Td({ children, right, className }) {
  return <td className={cn("border border-border px-2 py-1.5", right && "text-right tabular-nums", className)}>{children ?? "—"}</td>;
}

/** The Ready for Shipment Dashboard. */
function ReadyDashboard({ initial, canShip, act }) {
  const w = useWords();
  const [view, setView] = useState(initial);
  const [f, setF] = useState({ q: "", buyer: "", status: "", from: "", to: "", all: false });
  const [detail, setDetail] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const { kpis } = view;
  const tiles = [
    ["total_ready", `${num(kpis.total_ready_qty)} ${w("rs.pcs")}`, null, Truck, "brand"],
    ["orders", num(kpis.ready_orders), null, PackageCheck, "info"],
    ["styles", num(kpis.ready_styles), null, Layers, "warning"],
    ["cartons", num(kpis.total_cartons), null, Boxes, "brand"],
    ["buyers", num(kpis.total_buyers), null, Users, "danger"],
    ["pending", num(kpis.shipment_pending), w("rs.kpi.pending_sub"), Clock, "success"],
    ["partial", num(kpis.partially_shipped), null, Ship, "info"],
    ["completed", num(kpis.shipment_completed), w("rs.kpi.completed_sub"), CircleCheck, "success"],
  ];
  const search = (e) => {
    e.preventDefault();
    startTransition(async () => {
      const res = await act.dashboard({ ...f, all: f.all ? 1 : 0 });
      if (res?.status === "success") {
        setView(res.data);
        setError(null);
      } else setError(firstError(res));
    });
  };
  const open = (row) =>
    startTransition(async () => {
      const res = await act.detail(row.key);
      if (res?.status === "success") {
        setDetail(res.data);
        setError(null);
      } else setError(firstError(res));
    });
  const csv = () =>
    downloadCsv(
      "ready-for-shipment",
      [w("rs.col.po"), w("rs.col.buyer"), w("rs.col.style"), w("rs.col.colour"), w("rs.col.final"), w("rs.col.shipped"), w("rs.col.ready"), w("rs.col.carton"), w("rs.col.ship_date"), w("rs.col.status")],
      view.rows.map((r) => [r.po_no, r.buyer, r.style, r.colour, r.final_packed_qty, r.shipped_qty, r.ready_qty, r.cartons, day(r.ship_date), w(`rs.status.${r.status}`)]),
    );

  return (
    <div className="flex flex-col gap-4">
      <section className="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label={w("rs.kpi.label")}>
        {tiles.map(([key, value, sub, Icon, tone]) => (
          <StatCard key={key} label={w(`rs.kpi.${key}`)} value={value} supportingText={sub} icon={<Icon />} tone={tone} />
        ))}
      </section>

      <section className="rounded-lg border border-border bg-surface">
        <form onSubmit={search} className="grid grid-cols-2 gap-3 px-5 py-4 md:grid-cols-4 xl:grid-cols-7">
          <label className="col-span-2 flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
            {w("rs.f.search")}
            <Input value={f.q} maxLength={60} placeholder={w("rs.f.search_ph")} onChange={(e) => setF({ ...f, q: e.target.value })} className="h-10" />
          </label>
          <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
            {w("rs.f.buyer")}
            <select className={nativeSelect} value={f.buyer} onChange={(e) => setF({ ...f, buyer: e.target.value })}>
              <option value="">{w("rs.f.all")}</option>
              {view.buyers.map((b) => (
                <option key={b} value={b}>
                  {b}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
            {w("rs.f.status")}
            <select className={nativeSelect} value={f.status} onChange={(e) => setF({ ...f, status: e.target.value })}>
              <option value="">{w("rs.f.active")}</option>
              {["READY", "PARTIALLY_SHIPPED", "SHIPMENT_COMPLETED"].map((s) => (
                <option key={s} value={s}>
                  {w(`rs.status.${s}`)}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
            {w("rs.f.from")}
            <Input type="date" value={f.from} onChange={(e) => setF({ ...f, from: e.target.value })} className="h-10" />
          </label>
          <label className="flex flex-col gap-1.5 text-xs font-medium text-foreground-muted">
            {w("rs.f.to")}
            <Input type="date" value={f.to} onChange={(e) => setF({ ...f, to: e.target.value })} className="h-10" />
          </label>
          <div className="flex items-end">
            <Button type="submit" className="w-full justify-center" disabled={pending}>
              <Search /> {w("rs.f.run")}
            </Button>
          </div>
          <label className="col-span-full flex items-center gap-2 text-xs text-foreground-muted">
            <input type="checkbox" checked={f.all} onChange={(e) => setF({ ...f, all: e.target.checked })} /> {w("rs.f.history")}
          </label>
        </form>
      </section>

      <Panel
        icon={Truck}
        title={w("rs.list.title")}
        sub={w("rs.list.sub", { n: view.rows.length })}
        action={
          <Button size="sm" variant="outline" disabled={!view.rows.length} onClick={csv}>
            <Download /> {w("rs.csv")}
          </Button>
        }
      >
        {error ? (
          <p role="alert" className="px-5 pt-3 text-sm text-danger">
            {error}
          </p>
        ) : null}
        {view.rows.length === 0 ? (
          <Empty>{w("rs.list.empty")}</Empty>
        ) : (
          <div className="overflow-x-auto px-5 py-4">
            <table className="w-full min-w-[60rem] border border-border text-sm">
              <thead className="bg-surface-muted text-xs text-foreground-muted">
                <tr>
                  {["sl", "po", "buyer", "style", "colour", "ready", "shipped", "carton", "ship_date", "status", "action"].map((k) => (
                    <Th key={k} right={["sl", "ready", "shipped", "carton"].includes(k)}>
                      {w(`rs.col.${k}`)}
                    </Th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {view.rows.map((r, i) => (
                  <tr key={r.key} className="hover:bg-surface-muted">
                    <Td right>{i + 1}</Td>
                    <Td className="font-semibold">{r.po_no}</Td>
                    <Td>{r.buyer}</Td>
                    <Td>{r.style}</Td>
                    <Td>{r.colour}</Td>
                    <Td right className="font-semibold">
                      {num(r.ready_qty)}
                    </Td>
                    <Td right>{num(r.shipped_qty)}</Td>
                    <Td right>{num(r.cartons)}</Td>
                    <Td>{day(r.ship_date)}</Td>
                    <Td>
                      <Chip tone={STATUS_TONE[r.status]}>{w(`rs.status.${r.status}`)}</Chip>
                    </Td>
                    <Td className="whitespace-nowrap">
                      <div className="flex gap-1.5">
                        <Button size="sm" variant="outline" disabled={pending} onClick={() => open(r)}>
                          {w("rs.view")}
                        </Button>
                        {r.available_qty > 0 && canShip ? (
                          <Link
                            href={`/assembly-floor/shipment-entry?${new URLSearchParams({ po: r.po_no ?? "", colour: r.colour }).toString()}`}
                            className="inline-flex h-8 items-center rounded-md bg-brand px-3 text-xs font-medium text-brand-foreground hover:bg-brand-hover"
                          >
                            {w("rs.ship")}
                          </Link>
                        ) : null}
                      </div>
                    </Td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      <Modal
        open={Boolean(detail)}
        onOpenChange={(o) => !o && setDetail(null)}
        title={detail ? `${detail.row.buyer ?? "—"} · ${w("rs.col.po")} ${detail.row.po_no ?? "—"} · ${detail.row.style ?? "—"}` : ""}
        description={detail ? w("rs.detail.sub", { colour: detail.row.colour, factory: detail.row.factory ?? "—", date: day(detail.row.ship_date) }) : undefined}
        className="max-w-2xl"
      >
        {detail ? <ReadyDetail detail={detail} /> : null}
      </Modal>
    </div>
  );
}

function ReadyDetail({ detail }) {
  const w = useWords();
  const i = detail.info;
  const lines = [
    ["order_qty", i.order_qty],
    ["qc_pass", i.qc_pass_qty],
    ["packing_in", i.packing_in_qty],
    ["final", i.final_packed_qty],
    ["cartons", i.cartons],
    ["shipped", i.shipped_qty],
    ["reserved", i.reserved_qty],
    ["ready", i.ready_qty],
  ];
  return (
    <div className="flex flex-col gap-4">
      <table className="w-full border border-border text-sm">
        <thead className="bg-surface-muted text-xs text-foreground-muted">
          <tr>
            <Th>{w("rs.detail.info")}</Th>
            <Th right>{w("rs.detail.qty")}</Th>
          </tr>
        </thead>
        <tbody>
          {lines.map(([k, v]) => (
            <tr key={k}>
              <Td>{w(`rs.detail.${k}`)}</Td>
              <Td right className={k === "ready" ? "font-semibold text-brand" : undefined}>
                {num(v)}
              </Td>
            </tr>
          ))}
        </tbody>
      </table>
      <div>
        <h3 className="mb-2 text-sm font-semibold">{w("rs.detail.batches")}</h3>
        <table className="w-full border border-border text-sm">
          <thead className="bg-surface-muted text-xs text-foreground-muted">
            <tr>
              {["packing_no", "packed_out", "final", "shipped", "cartons", "status"].map((k) => (
                <Th key={k} right={["final", "shipped", "cartons"].includes(k)}>
                  {w(`rs.detail.b.${k}`)}
                </Th>
              ))}
            </tr>
          </thead>
          <tbody>
            {detail.batches.map((b) => (
              <tr key={b.packing_no}>
                <Td className="font-mono text-xs">{b.packing_no}</Td>
                <Td>{at(b.packed_out_at)}</Td>
                <Td right>{num(b.final_packed_qty)}</Td>
                <Td right>{num(b.shipped_qty)}</Td>
                <Td right>{num(b.sealed_cartons)}</Td>
                <Td>
                  <Chip tone={BATCH_TONE[b.status]}>{w(`pk.stage.${b.status}`)}</Chip>
                </Td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div>
        <h3 className="mb-2 text-sm font-semibold">{w("rs.detail.shipments")}</h3>
        {detail.shipments.length === 0 ? (
          <p className="text-sm text-foreground-muted">{w("rs.detail.no_shipment")}</p>
        ) : (
          <table className="w-full border border-border text-sm">
            <thead className="bg-surface-muted text-xs text-foreground-muted">
              <tr>
                {["date", "shipment", "qty", "remaining", "status"].map((k) => (
                  <Th key={k} right={["qty", "remaining"].includes(k)}>
                    {w(`rs.detail.s.${k}`)}
                  </Th>
                ))}
              </tr>
            </thead>
            <tbody>
              {detail.shipments.map((s) => (
                <tr key={s.id}>
                  <Td>{day(s.ship_date)}</Td>
                  <Td className="font-mono text-xs">{s.shipment_no}</Td>
                  <Td right>{num(s.qty)}</Td>
                  <Td right>{s.remaining_after === null ? "—" : num(s.remaining_after)}</Td>
                  <Td>{w(s.status === "SHIPPED" && s.remaining_after === 0 ? "sh.status.COMPLETED" : `sh.status.${s.status}`)}</Td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}

/** Finished Goods Stock: the balance by PO, colour and size, and the ledger. */
function FinishedGoods({ initial, act }) {
  const w = useWords();
  const [view, setView] = useState(initial);
  const [q, setQ] = useState("");
  const [all, setAll] = useState(false);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const t = view.totals;
  const search = (e) => {
    e.preventDefault();
    startTransition(async () => {
      const res = await act.stock({ q, all: all ? 1 : 0 });
      if (res?.status === "success") {
        setView(res.data);
        setError(null);
      } else setError(firstError(res));
    });
  };
  const tiles = [
    ["balance", t.balance, w("rs.fg.balance_sub"), Warehouse, "brand"],
    ["in", t.in_qty, w("rs.fg.in_sub"), ArrowDownToLine, "success"],
    ["out", t.out_qty, null, ArrowUpFromLine, "info"],
    ["orders", t.orders, null, Package, "warning"],
  ];
  const stockHead = ["buyer", "po", "style", "colour", "size", "in", "out", "balance"];
  const ledgerHead = ["when", "doc", "doc_no", "packing_no", "po", "colour", "size", "in", "out", "by"];
  const stock = (
    <>
      <div className="flex items-center justify-between gap-2 py-4">
        <h3 className="text-[15px] font-semibold">{w("rs.fg.stock_title")}</h3>
        <Button
          size="sm"
          variant="outline"
          disabled={!view.stock.length}
          onClick={() => downloadCsv("finished-goods-stock", stockHead.map((k) => w(`rs.fg.col.${k}`)), view.stock.map((r) => [r.buyer, r.po_no, r.style, r.colour, r.size, r.in_qty, r.out_qty, r.balance]))}
        >
          <Download /> {w("rs.csv")}
        </Button>
      </div>
      {view.stock.length === 0 ? (
        <Empty>{w("rs.fg.stock_empty")}</Empty>
      ) : (
        <div className="overflow-x-auto pb-4">
          <table className="w-full min-w-[44rem] border border-border text-sm">
            <thead className="bg-surface-muted text-xs text-foreground-muted">
              <tr>
                {stockHead.map((k) => (
                  <Th key={k} right={["in", "out", "balance"].includes(k)}>
                    {w(`rs.fg.col.${k}`)}
                  </Th>
                ))}
              </tr>
            </thead>
            <tbody>
              {view.stock.map((r) => (
                <tr key={`${r.po_no}-${r.colour}-${r.size}`} className="hover:bg-surface-muted">
                  <Td>{r.buyer}</Td>
                  <Td>{r.po_no}</Td>
                  <Td>{r.style}</Td>
                  <Td>{r.colour}</Td>
                  <Td className="font-semibold">{r.size}</Td>
                  <Td right>{num(r.in_qty)}</Td>
                  <Td right>{num(r.out_qty)}</Td>
                  <Td right className="font-semibold">
                    {num(r.balance)}
                  </Td>
                </tr>
              ))}
            </tbody>
            <tfoot className="bg-surface-muted text-xs font-semibold">
              <tr>
                <Td>{w("rs.total")}</Td>
                <td className="border border-border" colSpan={4} />
                <Td right>{num(view.stock_totals.in_qty)}</Td>
                <Td right>{num(view.stock_totals.out_qty)}</Td>
                <Td right>{num(view.stock_totals.balance)}</Td>
              </tr>
            </tfoot>
          </table>
        </div>
      )}
    </>
  );
  const ledger = (
    <>
      <div className="flex items-center justify-between gap-2 py-4">
        <div>
          <h3 className="text-[15px] font-semibold">{w("rs.fg.ledger_title")}</h3>
          <p className="text-[13px] text-foreground-muted">{w("rs.fg.ledger_sub")}</p>
        </div>
        <Button
          size="sm"
          variant="outline"
          disabled={!view.ledger.length}
          onClick={() =>
            downloadCsv(
              "finished-goods-ledger",
              [w("rs.fg.col.when"), w("rs.fg.col.doc"), w("rs.fg.col.doc_no"), w("rs.fg.col.packing_no"), w("rs.fg.col.buyer"), w("rs.fg.col.po"), w("rs.fg.col.colour"), w("rs.fg.col.size"), w("rs.fg.col.in"), w("rs.fg.col.out"), w("rs.fg.col.by")],
              view.ledger.map((l) => [at(l.at), w(`rs.doc.${l.doc_type}`), l.doc_no, l.packing_no ?? "", l.buyer, l.po_no, l.colour, l.size, l.qty_in, l.qty_out, l.by ?? ""]),
            )
          }
        >
          <Download /> {w("rs.csv")}
        </Button>
      </div>
      {view.ledger.length === 0 ? (
        <Empty>{w("rs.fg.ledger_empty")}</Empty>
      ) : (
        <div className="max-h-[36rem] overflow-auto pb-4">
          <table className="w-full min-w-[56rem] border border-border text-sm">
            <thead className="sticky top-0 bg-surface-muted text-xs text-foreground-muted">
              <tr>
                {ledgerHead.map((k) => (
                  <Th key={k} right={["in", "out"].includes(k)}>
                    {w(`rs.fg.col.${k}`)}
                  </Th>
                ))}
              </tr>
            </thead>
            <tbody>
              {view.ledger.map((l) => (
                <tr key={l.id} className="hover:bg-surface-muted">
                  <Td className="text-xs whitespace-nowrap">{at(l.at)}</Td>
                  <Td>{w(`rs.doc.${l.doc_type}`)}</Td>
                  <Td className="font-mono text-xs">{l.doc_no}</Td>
                  <Td className="font-mono text-xs">{l.packing_no}</Td>
                  <Td>
                    {l.po_no}
                    <span className="block text-xs text-foreground-muted">
                      {l.buyer} · {l.style}
                    </span>
                  </Td>
                  <Td>{l.colour}</Td>
                  <Td>{l.size}</Td>
                  <Td right className={l.qty_in > 0 ? "text-success" : undefined}>
                    {l.qty_in ? num(l.qty_in) : ""}
                  </Td>
                  <Td right className={l.qty_out > 0 ? "text-danger" : undefined}>
                    {l.qty_out ? num(l.qty_out) : ""}
                  </Td>
                  <Td className="text-xs">{l.by}</Td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );

  return (
    <div className="flex flex-col gap-4">
      <section className="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label={w("rs.kpi.label")}>
        {tiles.map(([key, value, sub, Icon, tone]) => (
          <StatCard key={key} label={w(`rs.fg.${key}`)} value={key === "orders" ? num(value) : `${num(value)} ${w("rs.pcs")}`} supportingText={sub} icon={<Icon />} tone={tone} />
        ))}
      </section>
      <section className="rounded-lg border border-border bg-surface">
        <form onSubmit={search} className="flex flex-wrap items-end gap-3 px-5 py-4">
          <label className="flex min-w-64 flex-1 flex-col gap-1.5 text-xs font-medium text-foreground-muted">
            {w("rs.f.search")}
            <Input value={q} maxLength={60} placeholder={w("rs.fg.search_ph")} onChange={(e) => setQ(e.target.value)} className="h-10" />
          </label>
          <label className="flex h-10 items-center gap-2 text-xs text-foreground-muted">
            <input type="checkbox" checked={all} onChange={(e) => setAll(e.target.checked)} /> {w("rs.fg.zero")}
          </label>
          <Button type="submit" disabled={pending}>
            <Search /> {w("rs.f.run")}
          </Button>
        </form>
        {error ? (
          <p role="alert" className="px-5 pb-3 text-sm text-danger">
            {error}
          </p>
        ) : null}
      </section>
      <section className="rounded-lg border border-border bg-surface px-5 pt-3">
        <Tabs defaultValue="stock">
          <TabsList>
            <TabsTab value="stock">{w("rs.fg.tab_stock", { n: view.stock.length })}</TabsTab>
            <TabsTab value="ledger">{w("rs.fg.tab_ledger", { n: view.ledger.length })}</TabsTab>
            <TabsIndicator />
          </TabsList>
          <TabsPanel value="stock">{stock}</TabsPanel>
          <TabsPanel value="ledger">{ledger}</TabsPanel>
        </Tabs>
      </section>
    </div>
  );
}

export { ReadyNav, ReadyDashboard, FinishedGoods };
