"use client";

import { useMemo, useState, useTransition } from "react";
import { BookMarked, Plus, RotateCcw, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

// An error's field messages, or its message when it names no field.
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" });
const day = (v) => (v ? dateFmt.format(new Date(`${v}T00:00:00Z`)) : "—");
// Brickland's default: a bulk booking.
const blank = () => ({ po_no: "", subject: "", supplier_id: "", req_type: "BULK", po_date: "", delivery_date: "" });

/**
 * The Bookings tab of Trims Accessories Booking and the Accessories Booking
 * Register (AnnotechERP 06/08), as Brickland lays it out: the confirmed
 * orders on the left (searchable, with ship date and booking count); picking
 * one loads, beside it, the accessory bookings raised against it, and Book
 * accessories raises another — PO #, subject, supplier, req type and dates;
 * its trims are then added on the Booked Trims panel. Nothing reloads.
 *
 * @param {{ orders: object[], options: object, initial: ?{ order: string, bookings: object[] }, canManage: boolean, actions: object }} props
 */
function AccBookingOrders({ orders: initialOrders, options, initial, canManage, actions }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.acb.${k}`, p);
  const toastManager = useToastManager();
  const [orders, setOrders] = useState(initialOrders);
  const [query, setQuery] = useState("");
  const [picked, setPicked] = useState(() => (initial ? initialOrders.find((o) => o.order_no === initial.order) ?? null : null));
  const [bookings, setBookings] = useState(initial?.bookings ?? []);
  const [booking, setBooking] = useState(null);
  const [errors, setErrors] = useState(null);
  const [pending, startTransition] = useTransition();
  const shown = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return orders.filter((o) => !needle || o.title.toLowerCase().includes(needle));
  }, [orders, query]);
  const supplierOptions = [{ value: "", label: m("select") }, ...options.suppliers];

  function pick(o) {
    setPicked(o);
    window.history.replaceState(null, "", `?tab=bookings&order=${encodeURIComponent(o.order_no)}`);
    startTransition(async () => {
      const result = await actions.forOrder(o.order_no);
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setBookings(result.data);
      setErrors(null);
    });
  }

  function book() {
    startTransition(async () => {
      const blankToNull = (v) => (v === "" ? null : v);
      const result = await actions.book(picked.order_no, Object.fromEntries(Object.entries(booking).map(([k, v]) => [k, blankToNull(v)])));
      if (result?.status !== "success") return setErrors(messagesOf(result));
      setBookings(result.data);
      setOrders((all) => all.map((o) => (o.order_no === picked.order_no ? { ...o, bookings: result.data.length } : o)));
      setBooking(null);
      setErrors(null);
      toastManager.add({ title: m("booked", { no: result.data.at(-1)?.booking_no }), type: "success" });
    });
  }

  const set = (k, v) => setBooking((b) => ({ ...b, [k]: v }));
  const field = "flex flex-col gap-1.5";
  const lab = "text-[13px] font-semibold text-foreground";
  return (
    <div className="grid gap-4 lg:grid-cols-[22rem_1fr]">
      <section className="self-start overflow-hidden rounded-xl border border-border bg-surface">
        <div className="flex items-center justify-between px-5 pt-4">
          <h2 className="text-base font-semibold text-foreground">{m("orders")}</h2>
          <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs font-semibold text-foreground">{orders.length}</span>
        </div>
        <div className="relative px-5 py-3">
          <Search className="pointer-events-none absolute top-1/2 left-7.5 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={m("search")} className="pl-8" aria-label={m("search_orders")} />
        </div>
        <ul className="max-h-[36rem] overflow-y-auto border-t border-border">
          {shown.map((o) => (
            <li key={o.order_no}>
              <button type="button" onClick={() => pick(o)} className={cn("flex w-full items-center justify-between gap-3 border-b border-border px-5 py-3 text-left hover:bg-surface-muted", picked?.order_no === o.order_no && "bg-brand-subtle")}>
                <span>
                  <span className="block text-sm font-semibold text-foreground">{o.title}</span>
                  <span className="block text-xs text-foreground-muted">{day(o.ship_date)}</span>
                </span>
                <span className="text-sm tabular-nums text-foreground">{o.bookings}</span>
              </button>
            </li>
          ))}
          {shown.length === 0 ? <li className="px-5 py-6 text-center text-sm text-foreground-muted">{m("no_match")}</li> : null}
        </ul>
      </section>

      <section className="flex flex-col self-start overflow-hidden rounded-xl border border-border bg-surface">
        <div className="flex items-start justify-between gap-3 px-5 py-4">
          <div>
            <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
              <BookMarked className="size-4" />
              {picked ? m("order_bookings_of", { order: picked.title }) : m("order_bookings")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("order_hint")}</p>
          </div>
          {picked && canManage ? (
            <Button size="sm" onClick={() => (setBooking(blank()), setErrors(null))}>
              <Plus /> {m("book")}
            </Button>
          ) : null}
        </div>
        {errors && !booking ? <Alert variant="danger" className="mx-5 mb-3">{errors.join(" · ")}</Alert> : null}
        {!picked ? (
          <div className="border-t border-border px-5 py-12 text-center">
            <p className="text-sm font-semibold text-foreground">{m("pick_order")}</p>
            <p className="mt-1 text-xs text-foreground-muted">{m("pick_order_hint")}</p>
          </div>
        ) : bookings.length === 0 ? (
          <div className="border-t border-border px-5 py-12 text-center">
            <p className="text-sm font-semibold text-foreground">{m("no_bookings", { order: picked.title })}</p>
            <p className="mt-1 text-xs text-foreground-muted">{m("no_bookings_hint")}</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["booking_no", "po_no", "subject", "supplier", "req_type", "po_date", "delivery_date", "items", "status"].map((k) => (
                    <th key={k} className={cn("px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap", k === "items" && "text-right")}>
                      {m(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {bookings.map((b) => (
                  <tr key={b.booking_no} className="border-t border-border">
                    <td className="px-3 py-2 font-mono text-[13px]">{b.booking_no}</td>
                    <td className="px-3 py-2">{b.po_no ?? "—"}</td>
                    <td className="px-3 py-2">{b.subject ?? "—"}</td>
                    <td className="px-3 py-2">{b.supplier ?? "—"}</td>
                    <td className="px-3 py-2">{m(`req_types.${b.req_type}`)}</td>
                    <td className="px-3 py-2 whitespace-nowrap">{day(b.po_date)}</td>
                    <td className="px-3 py-2 whitespace-nowrap">{day(b.delivery_date)}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{b.items}</td>
                    <td className="px-3 py-2">{m(`status.${b.status}`)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {picked ? <p className="border-t border-border px-5 py-3 text-xs text-foreground-muted">{m("booking_count", { n: bookings.length })}</p> : null}
      </section>

      <Modal
        open={booking !== null}
        onOpenChange={(o) => !o && setBooking(null)}
        title={picked ? m("book_of", { order: picked.title }) : m("book")}
        description={m("booking_hint")}
        footer={
          <div className="flex w-full flex-wrap items-center justify-between gap-2">
            <span className="text-xs text-foreground-muted">{m("footer")}</span>
            <div className="flex gap-2">
              <Button variant="outline" onClick={() => setBooking(blank())} disabled={pending}>
                <RotateCcw /> {m("reset")}
              </Button>
              <Button onClick={book} loading={pending}>
                {m("book")}
              </Button>
            </div>
          </div>
        }
      >
        {booking ? (
          <div className="flex flex-col gap-4">
            <h3 className="text-[13px] font-semibold text-foreground">{m("new_booking")}</h3>
            {errors ? <Alert variant="danger">{errors.join(" · ")}</Alert> : null}
            <div className="grid gap-4 sm:grid-cols-2">
              <label className={field}>
                <span className={lab}>{m("f.po_no")}</span>
                <Input value={booking.po_no} onChange={(e) => set("po_no", e.target.value)} maxLength={60} aria-label={m("f.po_no")} />
              </label>
              <label className={field}>
                <span className={lab}>{m("f.subject")}</span>
                <Input value={booking.subject} onChange={(e) => set("subject", e.target.value)} maxLength={200} aria-label={m("f.subject")} />
              </label>
              <div className={field}>
                <span className={lab}>{m("f.supplier_id")}</span>
                <Select value={booking.supplier_id} onValueChange={(v) => set("supplier_id", v ?? "")} options={supplierOptions} placeholder={m("select")} aria-label={m("f.supplier_id")} />
              </div>
              <div className={field}>
                <span className={lab}>{m("f.req_type")}</span>
                <Select value={booking.req_type} onValueChange={(v) => set("req_type", v ?? "")} options={[{ value: "", label: m("select") }, ...options.req_types.map((v) => ({ value: v, label: m(`req_types.${v}`) }))]} aria-label={m("f.req_type")} />
              </div>
              <label className={field}>
                <span className={lab}>{m("f.po_date")}</span>
                <Input type="date" value={booking.po_date} onChange={(e) => set("po_date", e.target.value)} aria-label={m("f.po_date")} />
              </label>
              <label className={field}>
                <span className={lab}>{m("f.delivery_date")}</span>
                <Input type="date" value={booking.delivery_date} onChange={(e) => set("delivery_date", e.target.value)} aria-label={m("f.delivery_date")} />
              </label>
            </div>
          </div>
        ) : null}
      </Modal>
    </div>
  );
}

export { AccBookingOrders };
