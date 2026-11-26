"use client";

import { useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { CalendarCheck, ListChecks, RefreshCw, RotateCcw } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { Field } from "@/components/dyehouse/chem-store";
import { cn } from "@/lib/utils";
import { printOrder } from "./sales-order";
import { Filters, OrderFlow, Register, Tiles, day, firstError, has, qty, useSs, within } from "./shared";

/*
 * Sales & Shipment → Order & Delivery (AnnotechERP 21/02): the three screens
 * that are one register each, as Brickland lays them out — Order
 * Confirmation ("Order to Shipment · Step 2 of 12": the approval chain with
 * its tiles, Submit → Check → Approve → Confirm, Return to Draft, Close
 * Order, the printed confirmation), Delivery Completion (each confirmed
 * order's delivered, returned, net delivered, balance and days to target)
 * and Sales Order Month Close (Parameters: Refresh, Month Close, Show
 * Exception, Reset; the closed months with Reopen).
 */

const APPROVALS = ["DRAFT", "SUBMITTED", "CHECKED", "APPROVED", "CONFIRMED"];

function ConfirmationDesk({ initial, options, canManage, canApprove, act }) {
  const w = useSs();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [filter, setFilter] = useState({});
  const [, startTransition] = useTransition();
  const reload = () =>
    startTransition(async () => {
      const res = await act.read("/sales-shipment/confirmations");
      if (res?.status === "success") setRows(res.data);
    });
  const shown = useMemo(
    () =>
      rows
        .filter((r) => has(r.buyers, filter.buyer) && (!filter.customer || r.customer_id === filter.customer) && has(r.so_no, filter.so) && has(r.po_wo_no, filter.po))
        .filter((r) => (!filter.approval || r.approval_status === filter.approval) && within(r.receive_date, filter.from, filter.to))
        .map((r) => ({ ...r, party: r.buyers ? `${r.customer} / ${r.buyers}` : r.customer, approval_label: w(`od.approval.${r.approval_status}`), state_label: w(`od.state.${r.state}`) })),
    [rows, filter, w],
  );
  const view = (r, autoPrint) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/orders/${encodeURIComponent(r.so_no)}`);
      if (res?.status === "success") printOrder(w, res.data, autoPrint, w("oc.print.title"));
      else toastManager.add({ title: firstError(res), type: "danger" });
    });
  const step = (action) => (no) => act.write("POST", `/sales-shipment/orders/${encodeURIComponent(no)}/${action}`);
  const at = (...s) => (r) => r.state === "OPEN" && s.includes(r.approval_status);
  const actions = [
    { key: "view", label: w("od.reg.view"), open: (r) => view(r, false) },
    { key: "print", label: w("od.reg.print"), open: (r) => view(r, true) },
    ...(canManage ? [{ key: "submit", label: w("od.reg.submit"), when: at("DRAFT"), confirm: w("oc.confirm.submit"), done: w("oc.done.submit"), run: step("submit") }] : []),
    ...(canApprove
      ? [
          { key: "check", label: w("oc.reg.check"), when: at("SUBMITTED"), done: w("oc.done.check"), run: step("check") },
          { key: "approve", label: w("od.reg.approve"), when: at("CHECKED"), confirm: w("oc.confirm.approve"), done: w("oc.done.approve"), run: step("approve") },
          { key: "confirm", label: w("oc.reg.confirm"), when: at("APPROVED"), confirm: w("oc.confirm.confirm"), done: w("oc.done.confirm"), run: step("confirm") },
          { key: "draft", label: w("oc.reg.return"), when: at("SUBMITTED", "CHECKED", "APPROVED"), confirm: w("oc.confirm.return"), done: w("oc.done.return"), run: step("return-to-draft"), destructive: true },
          { key: "close", label: w("oc.reg.close"), when: at("CONFIRMED"), confirm: w("oc.confirm.close"), done: w("oc.done.close"), run: step("close-order") },
        ]
      : []),
  ];
  const count = (s) => shown.filter((r) => r.approval_status === s).length;

  return (
    <>
      <OrderFlow w={w} active="order-confirmation" />
      <Filters
        w={w}
        note={w("oc.f.note")}
        value={filter}
        onChange={setFilter}
        fields={[
          { name: "buyer", label: w("so.col.buyer"), placeholder: `${w("so.col.buyer")}…` },
          { name: "customer", label: w("so.f.customer"), type: "select", options: options.customers.map(({ value, label }) => ({ value, label })) },
          { name: "so", label: w("so.f.so_no"), placeholder: "SO-…" },
          { name: "po", label: w("oc.col.po"), placeholder: `${w("oc.col.po")}…` },
          { name: "approval", label: w("oc.f.approval"), type: "select", options: APPROVALS.map((s) => ({ value: s, label: w(`od.approval.${s}`) })) },
          { name: "from", label: w("so.f.from"), type: "date" },
          { name: "to", label: w("so.f.to"), type: "date" },
        ]}
      />
      <Tiles items={[[w("od.approval.DRAFT"), count("DRAFT"), "neutral"], [w("od.approval.SUBMITTED"), count("SUBMITTED"), "warning"], [w("od.approval.CHECKED"), count("CHECKED"), "info"], [w("od.approval.APPROVED"), count("APPROVED"), "accent"], [w("od.approval.CONFIRMED"), count("CONFIRMED"), "good"]]} />
      <Register
        w={w}
        rows={shown}
        refKey="so_no"
        exportName="order-confirmation"
        onDone={reload}
        actions={actions}
        emptyTitle={w("oc.empty_title")}
        emptyText={w("od.empty_text")}
        columns={[
          { key: "so_no", label: w("so.f.so_no"), type: "mono" },
          { key: "party", label: w("oc.col.party") },
          { key: "po_wo_no", label: w("oc.col.po") },
          { key: "order_qty", label: w("oc.col.order_qty"), type: "number", align: "right" },
          { key: "value", label: w("oc.col.value"), type: "number", align: "right" },
          { key: "delivery_date", label: w("oc.col.delivery_date"), type: "date" },
          { key: "shipment_date", label: w("oc.col.shipment_date"), type: "date" },
          { key: "approval_label", label: w("so.col.approval"), type: "badge" },
          { key: "submitted", label: w("od.approval.SUBMITTED"), group: "trail", groupLabel: w("oc.grp.trail") },
          { key: "checked", label: w("od.approval.CHECKED"), group: "trail", groupLabel: w("oc.grp.trail") },
          { key: "approved", label: w("od.approval.APPROVED"), group: "trail", groupLabel: w("oc.grp.trail") },
          { key: "confirmed", label: w("od.approval.CONFIRMED"), group: "trail", groupLabel: w("oc.grp.trail") },
          { key: "state_label", label: w("od.f.state"), type: "badge" },
        ]}
      />
    </>
  );
}

function CompletionDesk({ initial, options, act }) {
  const w = useSs();
  const router = useRouter();
  const toastManager = useToastManager();
  const [filter, setFilter] = useState({});
  const [, startTransition] = useTransition();
  const shown = useMemo(
    () =>
      initial
        .filter((r) => (!filter.customer || r.customer_id === filter.customer) && has(r.so_no, filter.so) && (!filter.status || r.status === filter.status) && within(r.target, filter.from, filter.to))
        .map((r) => ({ ...r, status_label: w(`cp.status.${r.status}`) })),
    [initial, filter, w],
  );
  const view = (r) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/orders/${encodeURIComponent(r.so_no)}`);
      if (res?.status === "success") printOrder(w, res.data, false);
      else toastManager.add({ title: firstError(res), type: "danger" });
    });
  return (
    <>
      <Filters
        w={w}
        note={w("cp.f.note")}
        value={filter}
        onChange={setFilter}
        fields={[
          { name: "customer", label: w("so.f.customer"), type: "select", options: options.customers.map(({ value, label }) => ({ value, label })) },
          { name: "so", label: w("so.f.so_no"), placeholder: "SO-…" },
          { name: "status", label: w("cp.f.status"), type: "select", options: ["PENDING", "PARTIAL", "COMPLETED"].map((s) => ({ value: s, label: w(`cp.status.${s}`) })) },
          { name: "from", label: w("cp.f.from"), type: "date" },
          { name: "to", label: w("cp.f.to"), type: "date" },
        ]}
      />
      <Tiles
        items={[
          [w("cp.tile.orders"), shown.length],
          [w("cp.status.PENDING"), shown.filter((r) => r.status === "PENDING").length, "warning"],
          [w("cp.status.PARTIAL"), shown.filter((r) => r.status === "PARTIAL").length, "accent"],
          [w("cp.status.COMPLETED"), shown.filter((r) => r.status === "COMPLETED").length, "good"],
          [w("cp.tile.overdue"), shown.filter((r) => r.balance > 0 && r.days < 0).length, "critical"],
          [w("cp.tile.balance"), qty(shown.reduce((a, r) => a + r.balance, 0))],
        ]}
      />
      <Register
        w={w}
        rows={shown}
        refKey="so_no"
        exportName="delivery-completion"
        actions={[
          { key: "challans", label: w("cp.reg.challans"), open: (r) => router.push(`/sales-shipment/delivery-chalan?so=${encodeURIComponent(r.so_no)}`) },
          { key: "view", label: w("cp.reg.view"), open: view },
        ]}
        emptyTitle={w("cp.empty_title")}
        emptyText={w("od.empty_text")}
        columns={[
          { key: "so_no", label: w("so.f.so_no"), type: "mono" },
          { key: "customer", label: w("so.f.customer") },
          { key: "po_wo_no", label: w("cp.col.po") },
          { key: "style", label: w("so.col.style") },
          { key: "order_qty", label: w("oc.col.order_qty"), type: "number", align: "right", group: "q", groupLabel: w("cp.grp.qty") },
          { key: "delivered", label: w("cp.col.delivered"), type: "number", align: "right", group: "q", groupLabel: w("cp.grp.qty") },
          { key: "returned", label: w("cp.col.returned"), type: "number", align: "right", group: "q", groupLabel: w("cp.grp.qty") },
          { key: "net_delivered", label: w("cp.col.net"), type: "number", align: "right", group: "q", groupLabel: w("cp.grp.qty") },
          { key: "balance", label: w("cp.col.balance"), type: "number", align: "right", group: "q", groupLabel: w("cp.grp.qty") },
          { key: "target", label: w("cp.col.target"), type: "date" },
          { key: "days", label: w("cp.col.days"), type: "number", align: "right" },
          { key: "status_label", label: w("cp.f.status"), type: "badge" },
        ]}
      />
    </>
  );
}

function MonthCloseDesk({ initial, canOverride, act }) {
  const w = useSs();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [form, setForm] = useState({ period: "", remarks: "", override: false });
  const [exceptions, setExceptions] = useState(null);
  const [asking, setAsking] = useState(false);
  const [pending, startTransition] = useTransition();
  const warn = (title) => toastManager.add({ title, type: "danger" });
  const reload = () =>
    startTransition(async () => {
      const res = await act.read("/sales-shipment/month-close");
      if (res?.status === "success") setRows(res.data);
    });
  const show = () =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/month-close/exceptions?period=${encodeURIComponent(form.period)}`);
      if (res?.status !== "success") return warn(firstError(res));
      setExceptions(res.data);
    });
  const close = () =>
    startTransition(async () => {
      const res = await act.write("POST", "/sales-shipment/month-close", { period: form.period, remarks: form.remarks || null, override: form.override });
      setAsking(false);
      if (res?.status !== "success") return warn(firstError(res));
      toastManager.add({ title: w("mc.done.close", { period: form.period, n: res.data.orders }), type: "success" });
      setForm({ period: "", remarks: "", override: false });
      setExceptions(null);
      reload();
    });

  return (
    <>
      <section className="mb-4 rounded-lg border border-border bg-surface p-5">
        <h3 className="text-[15px] font-semibold">{w("mc.title")}</h3>
        <p className="mt-0.5 mb-3 text-[13px] text-foreground-muted">{w("mc.note")}</p>
        <div className="grid gap-4 lg:grid-cols-3">
          <Field label={w("mc.f.period")} required hint={w("mc.f.period_hint")}>
            <Input value={form.period} onChange={(e) => setForm({ ...form, period: e.target.value })} placeholder="Nov/2026" maxLength={10} aria-label={w("mc.f.period")} />
          </Field>
          <Field label={w("od.f.remarks")} className="lg:col-span-2">
            <Textarea value={form.remarks} onChange={(e) => setForm({ ...form, remarks: e.target.value })} rows={2} placeholder={w("mc.f.remarks_ph")} aria-label={w("od.f.remarks")} />
          </Field>
          <label className="flex items-start gap-2 lg:col-span-3">
            <Checkbox checked={form.override} onCheckedChange={(v) => setForm({ ...form, override: v === true })} disabled={!canOverride} className="mt-0.5" aria-label={w("mc.f.override")} />
            <span>
              <span className="block text-sm font-semibold">{w("mc.f.override")}</span>
              <span className="block text-xs text-foreground-muted">{w("mc.f.override_hint")}</span>
            </span>
          </label>
        </div>
        <div className="mt-4 flex flex-wrap gap-2">
          <Button variant="outline" onClick={reload} disabled={pending}>
            <RefreshCw /> {w("mc.refresh")}
          </Button>
          <Button onClick={() => (form.period ? setAsking(true) : warn(w("mc.need_period")))} disabled={pending}>
            <CalendarCheck /> {w("mc.close")}
          </Button>
          <Button variant="outline" onClick={() => (form.period ? show() : warn(w("mc.need_period")))} disabled={pending}>
            <ListChecks /> {w("mc.show")}
          </Button>
          <Button variant="outline" onClick={() => (setForm({ period: "", remarks: "", override: false }), setExceptions(null))}>
            <RotateCcw /> {w("od.f.reset")}
          </Button>
        </div>
      </section>
      {exceptions ? (
        <section className="mb-4 rounded-lg border border-border bg-surface p-5">
          <h3 className="text-[15px] font-semibold">{w("mc.ex.title", { period: exceptions.label })}</h3>
          {exceptions.items.length ? (
            <div className="mt-3 overflow-x-auto rounded-md border border-border">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-brand text-left text-brand-foreground">
                    {["kind", "no", "date", "detail"].map((k) => (
                      <th key={k} className="px-3 py-2.5 font-semibold">
                        {w(`mc.ex.col.${k}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {exceptions.items.map((x) => (
                    <tr key={`${x.kind}-${x.no}`} className="border-b border-border last:border-0">
                      <td className="px-3 py-2">{w(`mc.ex.kind.${x.kind}`)}</td>
                      <td className="px-3 py-2 font-mono">{x.no}</td>
                      <td className="px-3 py-2">{day(x.date)}</td>
                      <td className={cn("px-3 py-2", x.kind === "UNDELIVERED" && "tabular-nums")}>{x.kind === "UNDELIVERED" ? w("mc.ex.balance", { qty: x.detail }) : w(`mc.ex.state.${x.detail}`)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="mt-2 rounded-md bg-success-subtle px-3 py-2 text-sm text-success">{w("mc.ex.none")}</p>
          )}
        </section>
      ) : null}
      <Register
        w={w}
        rows={rows.map((r) => ({ ...r, closed_on: r.closed_at?.slice(0, 10) }))}
        refKey="period"
        exportName="sales-order-month-close"
        onDone={reload}
        actions={[{ key: "reopen", label: w("mc.reopen"), confirm: w("mc.confirm.reopen"), done: w("mc.done.reopen"), run: (p) => act.write("DELETE", `/sales-shipment/month-close/${encodeURIComponent(p)}`), destructive: true }]}
        emptyTitle={w("mc.empty_title")}
        emptyText={w("mc.empty_text")}
        columns={[
          { key: "label", label: w("mc.col.period"), type: "mono" },
          { key: "closed_on", label: w("mc.col.closed_at"), type: "date" },
          { key: "closed_by", label: w("mc.col.closed_by") },
          { key: "orders", label: w("mc.col.orders"), type: "number", align: "right" },
          { key: "overridden_by", label: w("mc.col.overridden_by") },
          { key: "remarks", label: w("od.f.remarks") },
        ]}
      />
      <ConfirmDialog open={asking} onOpenChange={(o) => !o && setAsking(false)} title={w("mc.close")} description={w("mc.confirm.close")} confirmLabel={w("mc.close")} cancelLabel={w("od.reg.keep")} loading={pending} onConfirm={close} />
    </>
  );
}

export { ConfirmationDesk, CompletionDesk, MonthCloseDesk };
