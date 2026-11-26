"use client";

import { useMemo, useState, useTransition } from "react";
import { Check, Download, LayoutList, Plus, RotateCcw, Save, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

// An error's field messages, or its message when it names no field.
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const blank = () => ({ booking_date: "", ref_no: "", sp_no: "", ship_date: "", remarks: "" });
// Brickland's "16 Oct 2026".
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" });
const day = (v) => (v ? dateFmt.format(new Date(`${v}T00:00:00Z`)) : "—");

/**
 * Imported Fabric Booking → Bookings (AnnotechERP 06/11), as Brickland lays
 * it out: the confirmed orders on the left, and the import fabric bookings
 * raised against the one picked on the right — Raise booking (a dialog),
 * Approve (once, and the booking is closed) and Remove (a draft only), with
 * Export. The fabric lines themselves go on the Booking Items panel.
 * Switching order never reloads the page.
 *
 * @param {{ orders: object[], initialOrderId: string, initialRows: object[], canManage: boolean, actions: { load: Function, raise: Function, approve: Function, remove: Function } }} props
 */
function ImportBookingDesk({ orders: initialOrders, initialOrderId, initialRows, canManage, actions }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.ifb.${k}`, p);
  const toastManager = useToastManager();
  const [orders, setOrders] = useState(initialOrders);
  const [query, setQuery] = useState("");
  const [orderId, setOrderId] = useState(initialOrderId);
  const [rows, setRows] = useState(initialRows);
  const [raising, setRaising] = useState(false);
  const [form, setForm] = useState(blank);
  const [formError, setFormError] = useState(null);
  const [removing, setRemoving] = useState(null);
  const [pending, startTransition] = useTransition();
  const order = orders.find((o) => o.id === orderId);
  const shown = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return needle ? orders.filter((o) => `${o.order_no} ${o.style ?? ""}`.toLowerCase().includes(needle)) : orders;
  }, [orders, query]);
  const title = (o) => `${o.order_no} / ${o.style ?? "—"}`;

  function take(next) {
    setRows(next);
    setOrders((all) => all.map((o) => (o.id === orderId ? { ...o, bookings: next.length } : o)));
  }

  function open(o) {
    setOrderId(o.id);
    window.history.replaceState(null, "", `?tab=bookings&order=${encodeURIComponent(o.order_no)}`);
    startTransition(async () => {
      const result = await actions.load(o.id);
      if (result?.status === "success") setRows(result.data);
      else toastManager.add({ title: messagesOf(result)[0], type: "warning" });
    });
  }

  function raise() {
    startTransition(async () => {
      const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === "" ? null : v]));
      const result = await actions.raise({ order_id: orderId, ...payload });
      if (result?.status === "success") {
        take(result.data);
        setRaising(false);
        setForm(blank());
        setFormError(null);
        toastManager.add({ title: m("raised", { no: result.data.at(-1)?.booking_no ?? "" }), type: "success" });
      } else setFormError(messagesOf(result).join(" "));
    });
  }

  function act(kind, row) {
    startTransition(async () => {
      const result = await actions[kind](row.booking_no);
      if (result?.status === "success") {
        take(result.data);
        toastManager.add({ title: m(kind === "approve" ? "approved" : "removed", { no: row.booking_no }), type: "success" });
      } else toastManager.add({ title: messagesOf(result)[0], type: "warning" });
      setRemoving(null);
    });
  }

  function exportCsv() {
    const cols = ["booking_no", "booking_date", "revision", "ref_no", "ship_date", "items", "status"];
    const head = ["ref", "date", "rev", "ref_no", "ship_date", "items", "status"].map((k) => m(`col.${k}`));
    const lines = [head, ...rows.map((r) => cols.map((c) => (c === "status" ? m(`status.${r.status}`) : (r[c] ?? ""))))];
    const csv = lines.map((l) => l.map((v) => `"${String(v).replaceAll('"', '""')}"`).join(",")).join("\n");
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
    link.download = `import-fabric-bookings-${order?.order_no ?? "order"}.csv`;
    link.click();
  }

  const th = "px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap";
  return (
    <div className="grid gap-4 lg:grid-cols-[20rem_1fr]">
      <section className="self-start overflow-hidden rounded-xl border border-border bg-surface">
        <div className="flex items-center justify-between px-5 pt-4">
          <h2 className="text-base font-semibold text-foreground">{m("orders")}</h2>
          <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs font-semibold text-foreground">{orders.length}</span>
        </div>
        <div className="relative px-5 py-3">
          <Search className="pointer-events-none absolute top-1/2 left-7.5 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={m("search")} className="pl-8" aria-label={m("search_orders")} />
        </div>
        <ul className="max-h-[38rem] overflow-y-auto border-t border-border">
          {shown.map((o) => (
            <li key={o.id}>
              <button type="button" onClick={() => open(o)} className={cn("flex w-full items-center justify-between gap-3 border-b border-border px-5 py-3 text-left hover:bg-surface-muted", orderId === o.id && "bg-brand-subtle")}>
                <span>
                  <span className="block text-sm font-semibold text-foreground">{title(o)}</span>
                  <span className="block text-xs text-foreground-muted">{day(o.ship_date)}</span>
                </span>
                <span className="text-sm tabular-nums text-foreground">{o.bookings}</span>
              </button>
            </li>
          ))}
          {shown.length === 0 ? <li className="px-5 py-6 text-center text-sm text-foreground-muted">{m("no_orders")}</li> : null}
        </ul>
      </section>

      <section className="flex flex-col self-start rounded-xl border border-border bg-surface">
        <div className="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
          <div>
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
              <LayoutList className="size-4" />
              {order ? m("bookings_of", { order: title(order) }) : m("bookings")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("hint")}</p>
          </div>
          {order ? (
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={exportCsv} disabled={!rows.length}>
                <Download /> {m("export")}
              </Button>
              {canManage ? (
                <Button variant="outline" size="sm" onClick={() => setRaising(true)}>
                  <Plus /> {m("raise")}
                </Button>
              ) : null}
            </div>
          ) : null}
        </div>
        {!order ? (
          <Empty title={m("pick")} hint={m("pick_hint")} />
        ) : rows.length === 0 ? (
          <Empty title={m("empty", { order: title(order) })} hint={canManage ? m("empty_hint") : ""} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["ref", "date", "rev", "ref_no", "ship_date", "items", "status"].map((k) => (
                    <th key={k} className={cn(th, ["rev", "items"].includes(k) && "text-right")}>
                      {m(`col.${k}`)}
                    </th>
                  ))}
                  <th className={cn(th, "text-right")}>{m("col.action")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.booking_no} className="border-t border-border">
                    <td className="px-3 py-2 font-mono text-[13px]">{r.booking_no}</td>
                    <td className="px-3 py-2">{day(r.booking_date)}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{r.revision}</td>
                    <td className="px-3 py-2">{r.ref_no ?? "—"}</td>
                    <td className="px-3 py-2">{day(r.ship_date)}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{r.items}</td>
                    <td className="px-3 py-2">
                      <span className={cn("rounded-md px-1.5 py-0.5 text-xs font-medium", r.status === "APPROVED" ? "bg-success-subtle text-success" : "bg-surface-muted text-foreground")}>{m(`status.${r.status}`)}</span>
                    </td>
                    <td className="px-3 py-2">
                      {canManage ? (
                        <span className="flex justify-end gap-1.5">
                          <Button variant="outline" size="sm" onClick={() => act("approve", r)} disabled={pending || r.locked}>
                            <Check /> {m("approve")}
                          </Button>
                          <Button variant="outline" size="sm" className="text-danger" onClick={() => setRemoving(r)} disabled={pending || r.locked}>
                            <Trash2 /> {m("remove")}
                          </Button>
                        </span>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {order ? <p className="border-t border-border px-5 py-3 text-[13px] text-foreground-muted">{m("footer", { n: rows.length })}</p> : null}
      </section>

      <Modal open={raising} onOpenChange={setRaising} title={m("raise_title", { order: order ? title(order) : "" })} className="max-w-2xl">
        <div className="rounded-lg border border-border p-4">
          <h3 className="text-sm font-semibold text-foreground">{m("new_booking")}</h3>
          <p className="mt-0.5 text-xs text-foreground-muted">{m("lines_hint")}</p>
          <div className="mt-3 grid gap-3 sm:grid-cols-3">
            <label className="flex flex-col gap-1.5">
              <span className="text-[13px] font-semibold text-foreground">
                {m("f.booking_date")}
                <span className="text-danger">*</span>
              </span>
              <Input type="date" value={form.booking_date} onChange={(e) => setForm((f) => ({ ...f, booking_date: e.target.value }))} aria-label={m("f.booking_date")} />
            </label>
            {[
              ["ref_no", "f.ref", 64],
              ["sp_no", "f.sp", 32],
            ].map(([k, label, max]) => (
              <label key={k} className="flex flex-col gap-1.5">
                <span className="text-[13px] font-semibold text-foreground">{m(label)}</span>
                <Input value={form[k]} onChange={(e) => setForm((f) => ({ ...f, [k]: e.target.value }))} maxLength={max} aria-label={m(label)} />
              </label>
            ))}
            <label className="flex flex-col gap-1.5">
              <span className="text-[13px] font-semibold text-foreground">{m("f.ship_date")}</span>
              <Input type="date" value={form.ship_date} onChange={(e) => setForm((f) => ({ ...f, ship_date: e.target.value }))} aria-label={m("f.ship_date")} />
            </label>
            <label className="flex flex-col gap-1.5 sm:col-span-3">
              <span className="text-[13px] font-semibold text-foreground">{m("f.remarks")}</span>
              <Textarea rows={3} value={form.remarks} onChange={(e) => setForm((f) => ({ ...f, remarks: e.target.value }))} maxLength={2000} aria-label={m("f.remarks")} />
            </label>
          </div>
        </div>
        {formError ? <p className="mt-3 text-sm text-danger">{formError}</p> : null}
        <div className="mt-4 flex gap-2">
          <Button
            variant="outline"
            onClick={() => {
              setForm(blank());
              setFormError(null);
            }}
            disabled={pending}
          >
            <RotateCcw /> {m("reset")}
          </Button>
          <Button onClick={raise} loading={pending}>
            <Save /> {m("raise")}
          </Button>
        </div>
      </Modal>

      <ConfirmDialog
        open={removing !== null}
        onOpenChange={(o) => !o && setRemoving(null)}
        title={m("remove_title")}
        description={m("remove_hint")}
        confirmLabel={m("remove")}
        destructive
        cancelLabel={m("cancel")}
        onConfirm={() => removing && act("remove", removing)}
        loading={pending}
      />
    </div>
  );
}

function Empty({ title, hint }) {
  return (
    <div className="flex flex-col items-center gap-2 px-5 py-14 text-center">
      <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
        <LayoutList className="size-5" />
      </span>
      <p className="text-sm font-semibold text-foreground">{title}</p>
      {hint ? <p className="text-xs text-foreground-muted">{hint}</p> : null}
    </div>
  );
}

export { ImportBookingDesk };
