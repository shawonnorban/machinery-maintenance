"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { CalendarCheck2, Check, Container, FileStack, ListChecks, Package, ReceiptText, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { FileInput } from "@/components/ui/file-input";
import { useToastManager } from "@/components/ui/toast";
import { Field, Group } from "@/components/dyehouse/chem-store";
import { printDocument } from "@/lib/print-document";
import { DeskTabs, EditGrid, EntryHead, Filters, Foot, LockNote, OrderFlow, Register, SideList, Tiles, blank, day, firstError, has, key, listTab, money, qty, today, useSs, within } from "./shared";

/*
 * Sales & Shipment → Shipment (AnnotechERP 21/05), as Brickland lays out its
 * seven screens: Shipment Booking, Container / Vessel Details and Commercial
 * Document Handover (a list and a plain entry form each), the Shipment
 * Invoice and the Packing List (a shipment picked on the left, its lines
 * dropping into the grid), Shipment Confirmation (the readiness checklist,
 * a tick or a cross per check, Confirm / Unconfirm) and Shipment Closing.
 * Booking, container, invoice, packing, confirmation and closing sit on the
 * "Order to Shipment" strip (steps 5–8, 11, 12).
 */

const n = (v) => (v === "" || v === null || v === undefined ? null : Number(v));
const MODES = ["Sea", "Air", "Road", "Sea-Air", "Courier"];

function useDesk() {
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  return { pending, startTransition, warn: (t) => toastManager.add({ title: t, type: "danger" }), done: (t) => toastManager.add({ title: t, type: "success" }) };
}

/** One form field from its spec: text, number, date, select, textarea or readonly. */
function SpecField({ f, value, set, disabled }) {
  const common = { "aria-label": f.label, disabled: disabled || f.readOnly };
  return (
    <Field label={f.label} required={f.required} hint={f.hint} className={f.wide ? "sm:col-span-3" : f.half ? "sm:col-span-2" : undefined}>
      {f.type === "select" ? (
        <Select value={value ?? ""} onValueChange={(v) => set(f.key, v ?? "")} options={f.optional ? [{ value: "", label: "—" }, ...f.options] : f.options} placeholder={f.placeholder ?? "Select…"} {...common} />
      ) : f.type === "textarea" ? (
        <Textarea value={value ?? ""} onChange={(e) => set(f.key, e.target.value)} rows={2} placeholder={f.placeholder} {...common} />
      ) : (
        <div className="relative">
          <Input type={f.type === "date" ? "date" : f.type === "number" ? "number" : "text"} min={f.type === "number" ? "0" : undefined} step={f.type === "number" ? "any" : undefined} value={value ?? ""}
            onChange={(e) => set(f.key, f.upper ? e.target.value.toUpperCase() : e.target.value)} placeholder={f.placeholder} maxLength={f.max} readOnly={f.readOnly} className={cn(f.suffix && "pr-12")} {...common} />
          {f.suffix ? <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-foreground-muted">{f.suffix}</span> : null}
        </div>
      )}
    </Field>
  );
}

/**
 * A list and a plain entry form — Brickland's shape for Booking, Container
 * and Handover. `fields` describe the form, `refKey` the row's number,
 * `save(form, editingRow)` writes it.
 */
function FormDesk({ w, listLabel, entryLabel, icon, title, hint, note, rows, reload, fields, blankForm, rowToForm, save, refKey, editable, columns, actions, filters, tiles, exportName, emptyTitle, flow, extra = null, onLoaded }) {
  const { pending, startTransition, warn, done } = useDesk();
  const [tab, setTab] = useState("list");
  const [filter, setFilter] = useState({});
  const [form, setForm] = useState(blankForm);
  const [editing, setEditing] = useState(null);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const canEdit = editable(editing);
  const open = (row) => {
    setEditing(row);
    setForm(rowToForm(row));
    onLoaded?.(row);
    setTab("entry");
  };
  const submit = () =>
    startTransition(async () => {
      const res = await save(form, editing);
      if (res?.status !== "success") return warn(firstError(res));
      done(res.message);
      setEditing(res.row ?? null);
      if (res.form) setForm(res.form);
      reload();
    });
  const shown = useMemo(() => rows.filter((r) => filters.every((f) => f.test(r, filter[f.name]))), [rows, filter, filters]);
  return (
    <>
      {flow ? <OrderFlow w={w} active={flow} /> : null}
      <DeskTabs tab={tab} setTab={(t) => (t === "entry" && tab === "list" && !editing ? (setForm(blankForm()), setTab(t)) : setTab(t))} items={[listTab(listLabel, rows.length), ...(editable(null) ? [["entry", entryLabel, icon]] : [])]} />
      {tab === "entry" ? (
        <div className="flex flex-col gap-4">
          <EntryHead title={title} hint={hint}>
            <LockNote text={editing && !canEdit ? w("shp.locked", { no: editing[refKey] }) : null} />
            <Group legend={title}>
              {fields(form, set).map((f) => (
                <SpecField key={f.key} f={f} value={form[f.key]} set={set} disabled={!canEdit} />
              ))}
            </Group>
            {extra ? extra(form, set, canEdit, editing) : null}
          </EntryHead>
          <Foot w={w} note={note} editable={canEdit} pending={pending} onBack={() => setTab("list")} onClear={() => (setForm(blankForm()), setEditing(null))} onSave={submit} saveLabel={w("shp.save")} />
        </div>
      ) : (
        <>
          <Filters w={w} note={note} value={filter} onChange={setFilter} fields={filters} />
          <Tiles items={tiles(shown)} />
          <Register
            w={w}
            rows={shown}
            refKey={refKey}
            exportName={exportName}
            onDone={reload}
            actions={[...(editable(null) ? [{ key: "edit", label: w("od.reg.edit"), when: (r) => editable(r), open }] : []), ...actions]}
            emptyTitle={emptyTitle}
            emptyText={w("od.empty_text")}
            columns={columns}
          />
        </>
      )}
    </>
  );
}

const dateRange = (w, name, label, field) => [
  { name: `${name}_from`, label: `${label} ${w("shp.from")}`, type: "date", test: (r, v) => within(r[field], v, null) },
  { name: `${name}_to`, label: `${label} ${w("shp.to")}`, type: "date", test: (r, v) => within(r[field], null, v) },
];
const shipOpts = (opt, all = false) => opt.shipments.filter((s) => all || s.open).map((s) => ({ value: s.shipment_no, label: `${s.shipment_no} · ${s.so_no} · ${s.customer}` }));

function useOptions(act) {
  const [opt, setOpt] = useState({ shipments: [], bookings: [], invoices: [], packing_lists: [], container_types: [], me: "" });
  const load = () => act.read("/sales-shipment/shipment-papers/options").then((r) => r?.status === "success" && setOpt(r.data));
  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  return [opt, load];
}

/* ───────────────────────── Booking ───────────────────────── */

function BookingDesk({ initial, canManage, act }) {
  const w = useSs();
  const [rows, setRows] = useState(initial);
  const [opt, loadOpt] = useOptions(act);
  const reload = () => (act.read("/sales-shipment/bookings").then((r) => r?.status === "success" && setRows(r.data)), loadOpt());
  const STAT = ["REQUESTED", "CONFIRMED", "COMPLETED", "CANCELLED"];
  const blankForm = () => ({ shipment_no: "", booking_date: today(), forwarder: "", shipping_line: "", vessel_name: "", voyage_no: "", etd: "", eta: "", port_of_loading: "", port_of_discharge: "", final_destination: "",
    container_type: "", container_qty: "0", status: "REQUESTED", remarks: "" });
  const step = (a) => (no) => act.write("POST", `/sales-shipment/bookings/${encodeURIComponent(no)}/${a}`);
  return (
    <FormDesk
      w={w}
      flow="shipment-booking"
      listLabel={w("bk.list_tab")}
      entryLabel={w("bk.entry_tab")}
      icon={CalendarCheck2}
      title={w("bk.title")}
      hint={w("bk.hint")}
      note={w("bk.note")}
      rows={rows}
      reload={reload}
      refKey="booking_no"
      exportName="shipment-bookings"
      emptyTitle={w("bk.empty_title")}
      blankForm={blankForm}
      rowToForm={(r) => ({ ...blankForm(), ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v ?? ""])), port_of_loading: r.pol ?? "", port_of_discharge: r.pod ?? "", container_qty: String(r.container_qty ?? 0) })}
      editable={(r) => canManage && (!r || ["REQUESTED", "CONFIRMED"].includes(r.status))}
      fields={(form, set) => [
        { key: "shipment_no", label: w("shp.f.shipment"), type: "select", required: true, options: shipOpts(opt), hint: w("shp.f.shipment_hint"), placeholder: w("od.ed.select") },
        { key: "booking_no", label: w("bk.f.no"), readOnly: true, placeholder: w("od.ed.auto_gen") },
        { key: "booking_date", label: w("bk.f.date"), type: "date", required: true },
        { key: "forwarder", label: w("bk.f.forwarder"), required: true, max: 160 },
        { key: "shipping_line", label: w("bk.f.line"), max: 160 },
        { key: "vessel_name", label: w("bk.f.vessel"), max: 120 },
        { key: "voyage_no", label: w("bk.f.voyage"), max: 40 },
        { key: "etd", label: w("bk.f.etd"), type: "date" },
        { key: "eta", label: w("bk.f.eta"), type: "date", hint: w("bk.f.eta_hint") },
        { key: "port_of_loading", label: w("sp.f.port_of_loading"), max: 80 },
        { key: "port_of_discharge", label: w("bk.f.pod"), max: 80 },
        { key: "final_destination", label: w("bk.f.final"), max: 120 },
        { key: "container_type", label: w("bk.f.ctype"), type: "select", optional: true, options: opt.container_types.map((t) => ({ value: t, label: t })) },
        { key: "container_qty", label: w("bk.f.cqty"), type: "number" },
        { key: "status", label: w("bk.f.status"), type: "select", required: true, options: STAT.map((s) => ({ value: s, label: w(`bk.status.${s}`) })) },
        { key: "remarks", label: w("od.f.remarks"), type: "textarea", wide: true },
      ]}
      save={async (form, editing) => {
        const payload = { ...Object.fromEntries(Object.entries(form).filter(([k]) => k !== "booking_no").map(([k, v]) => [k, blank(v)])), container_qty: n(form.container_qty) ?? 0 };
        const res = await act.write(editing ? "PUT" : "POST", editing ? `/sales-shipment/bookings/${encodeURIComponent(editing.booking_no)}` : "/sales-shipment/bookings", payload);
        return res?.status === "success" ? { ...res, message: w("bk.saved", { no: res.data.booking_no }), row: { booking_no: res.data.booking_no, status: res.data.status }, form: { ...form, booking_no: res.data.booking_no } } : res;
      }}
      filters={[{ name: "status", label: w("bk.f.status"), type: "select", options: STAT.map((s) => ({ value: s, label: w(`bk.status.${s}`) })), test: (r, v) => !v || r.status === v }, ...dateRange(w, "etd", w("bk.f.etd"), "etd")]}
      tiles={(s) => [[w("bk.tile.all"), s.length], [w("bk.status.REQUESTED"), s.filter((r) => r.status === "REQUESTED").length, "warning"], [w("bk.status.CONFIRMED"), s.filter((r) => r.status === "CONFIRMED").length, "good"]]}
      actions={
        canManage
          ? [
              { key: "confirm", label: w("oc.reg.confirm"), when: (r) => r.status === "REQUESTED", confirm: w("bk.confirm.confirm"), done: w("bk.done.confirm"), run: step("confirm") },
              { key: "complete", label: w("bk.reg.complete"), when: (r) => r.status === "CONFIRMED", done: w("bk.done.complete"), run: step("complete") },
              { key: "cancel", label: w("od.reg.cancel"), when: (r) => ["REQUESTED", "CONFIRMED"].includes(r.status), confirm: w("bk.confirm.cancel"), done: w("bk.done.cancel"), run: step("cancel"), destructive: true },
              { key: "delete", label: w("od.reg.delete"), when: (r) => ["REQUESTED", "CANCELLED"].includes(r.status), confirm: w("od.confirm.delete"), done: w("od.done.delete"), run: (no) => act.write("DELETE", `/sales-shipment/bookings/${encodeURIComponent(no)}`), destructive: true },
            ]
          : []
      }
      columns={[
        { key: "booking_no", label: w("bk.f.no"), type: "mono" },
        { key: "booking_date", label: w("bk.f.date"), type: "date" },
        { key: "shipment_no", label: w("sp.f.shipment_no"), type: "mono" },
        { key: "so_no", label: w("so.f.so_no"), type: "mono" },
        { key: "forwarder", label: w("bk.f.forwarder") },
        { key: "shipping_line", label: w("bk.f.line") },
        { key: "vessel", label: w("bk.col.vessel") },
        { key: "etd", label: w("bk.f.etd"), type: "date" },
        { key: "eta", label: w("bk.f.eta"), type: "date" },
        { key: "pol", label: w("bk.col.pol") },
        { key: "pod", label: w("bk.col.pod") },
        { key: "final_destination", label: w("bk.f.final") },
        { key: "containers", label: w("bk.col.containers") },
        { key: "status_label", label: w("bk.f.status"), type: "badge", read: (r) => w(`bk.status.${r.status}`) },
      ].map((c) => (c.key === "status_label" ? { ...c, key: "status", type: "badge", labels: Object.fromEntries(STAT.map((s) => [s, w(`bk.status.${s}`)])) } : c))}
    />
  );
}

/* ───────────────────────── Container ───────────────────────── */

function ContainerDesk({ initial, canManage, act }) {
  const w = useSs();
  const [rows, setRows] = useState(initial);
  const [opt, loadOpt] = useOptions(act);
  const reload = () => (act.read("/sales-shipment/containers").then((r) => r?.status === "success" && setRows(r.data)), loadOpt());
  const blankForm = () => ({ shipment_no: "", booking_no: "", container_no: "", container_type: "", seal_no: "", gross_weight: "0", net_weight: "0", cbm: "0", carton_qty: "0", vessel_name: "", voyage_no: "",
    loading_date: "", etd: "", eta: "", remarks: "" });
  const live = (r) => opt.shipments.find((s) => s.shipment_no === r?.shipment_no)?.open ?? false;
  return (
    <FormDesk
      w={w}
      flow="container-vessel"
      listLabel={w("ct.list_tab")}
      entryLabel={w("ct.entry_tab")}
      icon={Container}
      title={w("ct.title")}
      hint={w("ct.hint")}
      note={w("ct.note")}
      rows={rows}
      reload={reload}
      refKey="key"
      exportName="containers"
      emptyTitle={w("ct.empty_title")}
      blankForm={blankForm}
      rowToForm={(r) => ({ ...blankForm(), ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v ?? ""])), container_type: r.type ?? "", carton_qty: String(r.cartons ?? 0), booking_no: r.booking_no ?? "" })}
      editable={(r) => canManage && (!r || live(r))}
      fields={(form, set) => [
        { key: "shipment_no", label: w("shp.f.shipment"), type: "select", required: true, options: shipOpts(opt), hint: w("shp.f.shipment_hint"), placeholder: w("od.ed.select") },
        { key: "booking_no", label: w("ct.f.booking"), type: "select", optional: true, options: opt.bookings.filter((b) => b.shipment_no === form.shipment_no).map((b) => ({ value: b.booking_no, label: `${b.booking_no} · ${b.vessel_name ?? "—"}` })), hint: w("ct.f.booking_hint") },
        { key: "container_no", label: w("ct.f.no"), required: true, placeholder: "MSKU1234567", max: 11, upper: true },
        { key: "container_type", label: w("bk.f.ctype"), type: "select", optional: true, options: opt.container_types.map((t) => ({ value: t, label: t })), hint: w("ct.f.type_hint") },
        { key: "seal_no", label: w("ct.f.seal"), max: 40 },
        { key: "gross_weight", label: w("ct.f.gross"), type: "number", suffix: "kg" },
        { key: "net_weight", label: w("ct.f.net"), type: "number", suffix: "kg" },
        { key: "cbm", label: w("ct.f.cbm"), type: "number", suffix: "m³" },
        { key: "carton_qty", label: w("sp.col.cartons"), type: "number" },
        { key: "vessel_name", label: w("bk.f.vessel"), max: 120 },
        { key: "voyage_no", label: w("bk.f.voyage"), max: 40 },
        { key: "loading_date", label: w("ct.f.loading"), type: "date" },
        { key: "etd", label: w("bk.f.etd"), type: "date" },
        { key: "eta", label: w("bk.f.eta"), type: "date" },
        { key: "remarks", label: w("od.f.remarks"), type: "textarea", half: true },
      ]}
      save={async (form, editing) => {
        const payload = { ...Object.fromEntries(Object.entries(form).map(([k, v]) => [k, blank(v)])), gross_weight: n(form.gross_weight), net_weight: n(form.net_weight), cbm: n(form.cbm), carton_qty: n(form.carton_qty) };
        const res = await act.write(editing ? "PUT" : "POST", editing ? `/sales-shipment/containers/${encodeURIComponent(editing.shipment_no)}/${encodeURIComponent(editing.container_no)}` : "/sales-shipment/containers", payload);
        return res?.status === "success" ? { ...res, message: w("ct.saved", { no: res.data.container_no }), row: { key: `${res.data.shipment_no}/${res.data.container_no}`, shipment_no: res.data.shipment_no, container_no: res.data.container_no } } : res;
      }}
      filters={[{ name: "type", label: w("bk.f.ctype"), type: "select", options: opt.container_types.map((t) => ({ value: t, label: t })), test: (r, v) => !v || r.type === v }, ...dateRange(w, "etd", w("bk.f.etd"), "etd")]}
      tiles={(s) => [[w("ct.tile.all"), s.length], [w("ct.tile.cbm"), qty(s.reduce((a, r) => a + r.cbm, 0))], [w("ct.tile.cartons"), s.reduce((a, r) => a + r.cartons, 0)]]}
      actions={canManage ? [{ key: "delete", label: w("od.reg.delete"), when: live, confirm: w("ct.confirm.delete"), done: w("od.done.delete"), run: (k) => act.write("DELETE", `/sales-shipment/containers/${k.split("/").map(encodeURIComponent).join("/")}`), destructive: true }] : []}
      columns={[
        { key: "container_no", label: w("ct.f.no"), type: "mono" },
        { key: "shipment_no", label: w("sp.f.shipment_no"), type: "mono" },
        { key: "booking_no", label: w("bk.f.no"), type: "mono" },
        { key: "type", label: w("ct.col.type") },
        { key: "seal_no", label: w("ct.f.seal") },
        { key: "gross_weight", label: w("ct.col.gross"), type: "number", align: "right" },
        { key: "net_weight", label: w("ct.col.net"), type: "number", align: "right" },
        { key: "cbm", label: w("ct.f.cbm"), type: "number", align: "right" },
        { key: "cartons", label: w("sp.col.cartons"), type: "number", align: "right" },
        { key: "vessel", label: w("bk.col.vessel") },
        { key: "loading_date", label: w("ct.f.loading"), type: "date" },
        { key: "etd", label: w("bk.f.etd"), type: "date" },
        { key: "eta", label: w("bk.f.eta"), type: "date" },
      ]}
    />
  );
}

/* ───────────────────────── Document handover ───────────────────────── */

function printHandover(w, h, fin = false) {
  printDocument({
    title: w(fin ? "fh.print.title" : "dh.print.title"),
    docNo: h.handover_no,
    subtitle: `${h.shipment_no} · ${w(`dh.status.${h.status}`)}`,
    meta: [
      [w("od.f.date"), day(h.handover_date)],
      [w("so.col.buyer"), h.buyer],
      [w("dh.f.invoice"), h.invoice_no],
      [w("dh.f.packing"), h.packing_no],
      [w("dh.f.exp"), h.exp_no],
      [w("dh.f.certificate"), h.certificate],
      [w("dh.f.type"), h.document_type],
      [w("dh.f.submitted"), h.submitted_by],
      [w("dh.f.received"), h.received_by],
    ],
    notes: [h.document_list, h.remarks],
    signatures: [w("dh.f.submitted"), w("dh.f.received")],
  });
}

function HandoverDesk({ initial, canManage, act, kind = "COMMERCIAL" }) {
  const fin = kind === "FINAL";
  const P = fin ? "fh" : "dh";
  const base = fin ? "/sales-shipment/final-handovers" : "/sales-shipment/handovers";
  const w = useSs();
  const { warn } = useDesk();
  const [rows, setRows] = useState(initial);
  const [opt, loadOpt] = useOptions(act);
  const [file, setFile] = useState(null);
  const reload = () => (act.read(base).then((r) => r?.status === "success" && setRows(r.data)), loadOpt());
  const STAT = ["PENDING", "HANDED_OVER", "RECEIVED", "RETURNED"];
  const blankForm = () => ({ handover_no: "", handover_date: today(), shipment_no: "", invoice_no: "", packing_no: "", exp_no: "", certificate: "", document_type: "", document_list: "", submitted_by: "", received_by: "", status: "PENDING", remarks: "" });
  const step = (a) => (no) => act.write("POST", `/sales-shipment/handovers/${encodeURIComponent(no)}/${a}`);
  const buyer = (no) => opt.shipments.find((s) => s.shipment_no === no)?.customer ?? "";
  return (
    <FormDesk
      w={w}
      flow={fin ? "document-handover" : null}
      listLabel={w(`${P}.list_tab`)}
      entryLabel={w(`${P}.entry_tab`)}
      icon={FileStack}
      title={w(`${P}.title`)}
      hint={w(`${P}.hint`)}
      note={w(`${P}.note`)}
      rows={rows}
      reload={reload}
      refKey="handover_no"
      exportName={fin ? "final-document-handovers" : "document-handovers"}
      emptyTitle={w(`${P}.empty_title`)}
      blankForm={blankForm}
      onLoaded={() => setFile(null)}
      rowToForm={(r) => ({ ...blankForm(), ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v ?? ""])) })}
      editable={(r) => canManage && (!r || r.status !== "RECEIVED")}
      fields={(form) => [
        { key: "handover_no", label: w("dh.f.no"), readOnly: true, placeholder: w("od.ed.auto_gen") },
        { key: "handover_date", label: w("dh.f.date"), type: "date", required: true },
        { key: "shipment_no", label: w("shp.f.shipment"), type: "select", required: true, options: shipOpts(opt, true), placeholder: w("od.ed.select") },
        { key: "buyer", label: w("so.col.buyer"), readOnly: true, placeholder: buyer(form.shipment_no) || "—" },
        { key: "invoice_no", label: w("dh.f.invoice"), type: "select", optional: true, options: opt.invoices.filter((i) => i.shipment_no === form.shipment_no).map((i) => ({ value: i.invoice_no, label: i.invoice_no })) },
        { key: "packing_no", label: w("dh.f.packing"), type: "select", optional: true, options: opt.packing_lists.filter((p) => p.shipment_no === form.shipment_no).map((p) => ({ value: p.packing_no, label: p.packing_no })) },
        { key: "exp_no", label: w("dh.f.exp"), max: 60 },
        { key: "certificate", label: w("dh.f.certificate"), max: 120, placeholder: fin ? w("fh.f.certificate_ph") : undefined },
        { key: "document_type", label: w("dh.f.type"), max: 120, placeholder: fin ? w("fh.f.type_ph") : undefined },
        { key: "submitted_by", label: w(fin ? "fh.f.sender" : "dh.f.submitted"), max: 120, hint: w("dh.f.submitted_hint"), placeholder: opt.me },
        { key: "received_by", label: w(fin ? "fh.f.receiver" : "dh.f.received"), max: 120 },
        { key: "status_label", label: w("dh.f.status"), readOnly: true, placeholder: w(`dh.status.${form.status || "PENDING"}`) },
        { key: "document_list", label: w("dh.f.list"), type: "textarea", wide: true, required: fin, hint: w(fin ? "fh.f.list_hint" : "dh.f.list_hint"), placeholder: fin ? w("fh.f.list_ph") : undefined },
        { key: "remarks", label: w("od.f.remarks"), type: "textarea", wide: true },
      ]}
      extra={(form, set, canEdit) => (
        <Field label={w("dh.f.attachment")} hint={form.attachment ? w("dh.f.attached", { name: form.attachment }) : w("dh.f.attachment_hint")}>
          <FileInput onChange={(e) => setFile(e.target.files?.[0] ?? null)} disabled={!canEdit} aria-label={w("dh.f.attachment")} />
        </Field>
      )}
      save={async (form, editing) => {
        const payload = Object.fromEntries(["handover_date", "shipment_no", "invoice_no", "packing_no", "exp_no", "certificate", "document_type", "document_list", "submitted_by", "received_by", "remarks"].map((k) => [k, blank(form[k])]));
        const res = await act.write(editing ? "PUT" : "POST", editing ? `${base}/${encodeURIComponent(editing.handover_no)}` : base, payload);
        if (res?.status !== "success") return res;
        let attachment = form.attachment;
        if (file) {
          const body = new FormData();
          body.append("upload", file);
          const up = await fetch(`/api/sales-shipment/handover-attachment?no=${encodeURIComponent(res.data.handover_no)}`, { method: "POST", body });
          const json = await up.json().catch(() => ({}));
          if (!up.ok) warn(json?.message ?? w("dh.upload_failed"));
          else attachment = json?.data?.attachment ?? file.name;
          setFile(null);
        }
        return { ...res, message: w(`${P}.saved`, { no: res.data.handover_no }), row: { handover_no: res.data.handover_no, status: res.data.status }, form: { ...form, handover_no: res.data.handover_no, status: res.data.status, attachment } };
      }}
      filters={[{ name: "status", label: w("dh.f.status"), type: "select", options: STAT.map((s) => ({ value: s, label: w(`dh.status.${s}`) })), test: (r, v) => !v || r.status === v }, ...dateRange(w, "date", w("dh.f.date"), "handover_date")]}
      tiles={(s) => [[w("dh.tile.all"), s.length], [w("dh.status.PENDING"), s.filter((r) => r.status === "PENDING").length, "warning"], [w("dh.tile.awaiting"), s.filter((r) => r.status === "HANDED_OVER").length, "accent"], [w("dh.status.RETURNED"), s.filter((r) => r.status === "RETURNED").length, "critical"]]}
      actions={[
        { key: "print", label: w("od.reg.print"), open: (r) => printHandover(w, r, fin) },
        { key: "file", label: w("dh.reg.file"), when: (r) => Boolean(r.file_id), open: (r) => window.open(`/api/files/${r.file_id}/download`, "_blank") },
        ...(canManage
          ? [
              { key: "handed", label: w("dh.reg.handed"), when: (r) => r.status === "PENDING", done: w("dh.done.handed"), run: step("handed-over") },
              { key: "received", label: w("dh.reg.received"), when: (r) => r.status === "HANDED_OVER", done: w("dh.done.received"), run: step("received") },
              { key: "returned", label: w("dh.reg.returned"), when: (r) => ["HANDED_OVER", "RECEIVED"].includes(r.status), confirm: w("dh.confirm.returned"), done: w("dh.done.returned"), run: step("returned"), destructive: true },
              { key: "delete", label: w("od.reg.delete"), when: (r) => r.status === "PENDING", confirm: w("od.confirm.delete"), done: w("od.done.delete"), run: (no) => act.write("DELETE", `/sales-shipment/handovers/${encodeURIComponent(no)}`), destructive: true },
            ]
          : []),
      ]}
      columns={[
        { key: "handover_no", label: w("dh.f.no"), type: "mono" },
        { key: "handover_date", label: w("od.f.date"), type: "date" },
        { key: "shipment_no", label: w("sp.f.shipment_no"), type: "mono" },
        { key: "buyer", label: w("so.col.buyer") },
        { key: "invoice_no", label: w("si.col.no"), type: "mono" },
        { key: "packing_no", label: w("pl.col.no"), type: "mono" },
        { key: fin ? "document_list" : "documents", label: w(fin ? "fh.col.documents" : "dh.col.documents") },
        { key: "submitted_by", label: w(fin ? "fh.f.sender" : "dh.f.submitted") },
        { key: "received_by", label: w(fin ? "fh.f.receiver" : "dh.f.received") },
        { key: "attachment", label: w("dh.f.attachment") },
        { key: "status", label: w("dh.f.status"), type: "badge", labels: Object.fromEntries(STAT.map((s) => [s, w(`dh.status.${s}`)])) },
      ]}
    />
  );
}

/* ───────────────────────── Invoice ───────────────────────── */

function printInvoice(w, i) {
  printDocument({
    title: w("si.print.title"),
    docNo: i.invoice_no,
    subtitle: `${i.shipment_no} · ${i.so_no} · ${w(`si.state.${i.state}`)}`,
    orientation: "landscape",
    meta: [
      [w("si.f.consignee"), i.consignee],
      [w("si.f.notify"), i.notify_party],
      [w("si.f.date"), day(i.invoice_date)],
      [w("so.f.currency"), i.currency],
      [w("si.f.country"), i.country],
      [w("si.f.port"), i.port],
      [w("sp.f.mode"), i.shipment_mode],
      [w("so.f.incoterm"), i.incoterm],
      [w("si.f.terms"), i.payment_terms],
      [w("si.f.lc"), i.lc_contract_no],
    ],
    sections: [
      {
        columns: ["description", "qty", "price", "amount", "cartons", "net", "gross"].map((k) => ({ label: w(`si.col.${k}`), align: k === "description" ? "left" : "right" })),
        rows: i.lines.map((l) => [l.description, qty(l.qty), money(l.unit_price), money(l.qty * l.unit_price), l.carton_qty, l.net_weight ?? "", l.gross_weight ?? ""]),
        foot: [[w("so.print.total"), qty(i.lines.reduce((a, l) => a + l.qty, 0)), "", money(i.lines.reduce((a, l) => a + l.qty * l.unit_price, 0)), i.lines.reduce((a, l) => a + l.carton_qty, 0), "", ""]],
      },
    ],
    notes: [i.remarks],
    signatures: true,
  });
}

function InvoiceDesk({ initial, options, canManage, act }) {
  const w = useSs();
  const { pending, startTransition, warn, done } = useDesk();
  const [rows, setRows] = useState(initial);
  const [ships, setShips] = useState([]);
  const [tab, setTab] = useState("list");
  const [filter, setFilter] = useState({});
  const blankForm = () => ({ invoice_no: "", shipment_no: "", invoice_date: today(), currency_id: "", commercial_invoice_ref: "", consignee: "", notify_party: "", country_id: "", port: "", shipment_mode: "Sea", incoterm: "",
    payment_terms: "", lc_contract_no: "", remarks: "", state: "DRAFT", lines: [] });
  const [form, setForm] = useState(blankForm);
  const [source, setSource] = useState(null);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && form.state === "DRAFT";
  const reload = () =>
    startTransition(async () => {
      const [a, b] = await Promise.all([act.read("/sales-shipment/invoices"), act.read("/sales-shipment/invoice-shipments")]);
      if (a?.status === "success") setRows(a.data);
      if (b?.status === "success") setShips(b.data);
    });
  useEffect(() => {
    act.read("/sales-shipment/invoice-shipments").then((r) => r?.status === "success" && setShips(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const pick = (no) =>
    startTransition(async () => {
      if (!no) return (setSource(null), set("lines", []));
      const res = await act.read(`/sales-shipment/invoices/source/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const s = res.data;
      setSource(s);
      setForm((f) => ({ ...f, shipment_no: no, currency_id: f.currency_id || s.currency_id, consignee: f.consignee || s.consignee, country_id: f.country_id || (s.country_id ?? ""), port: f.port || (s.port ?? ""),
        shipment_mode: s.shipment_mode, incoterm: f.incoterm || (s.incoterm ?? ""), payment_terms: f.payment_terms || (s.payment_terms ?? ""),
        lines: s.lines.filter((l) => l.balance > 0).map((l) => ({ _k: key(), shipment_line_id: l.shipment_line_id, description: l.label, qty: String(l.balance), unit_price: String(l.rate), carton_qty: l.cartons ? String(l.cartons) : "", net_weight: "", gross_weight: "" })) }));
    });
  const open = (no) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/invoices/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const i = res.data;
      setForm({ ...blankForm(), ...Object.fromEntries(Object.entries(i).map(([k, v]) => [k, v ?? ""])),
        lines: i.lines.map((l) => ({ _k: key(), shipment_line_id: l.shipment_line_id, description: l.description ?? "", qty: String(l.qty), unit_price: String(l.unit_price), carton_qty: String(l.carton_qty || ""), net_weight: l.net_weight ?? "", gross_weight: l.gross_weight ?? "", label: l.label })) });
      setSource(i.source ?? { lines: i.lines.map((l) => ({ shipment_line_id: l.shipment_line_id, label: l.label })) });
      setTab("entry");
    });
  const save = () =>
    startTransition(async () => {
      const payload = { ...Object.fromEntries(["shipment_no", "invoice_date", "currency_id", "commercial_invoice_ref", "consignee", "notify_party", "country_id", "port", "shipment_mode", "incoterm", "payment_terms", "lc_contract_no", "remarks"].map((k) => [k, blank(form[k])])),
        lines: form.lines.filter((l) => l.shipment_line_id).map((l) => ({ shipment_line_id: l.shipment_line_id, description: blank(l.description), qty: n(l.qty), unit_price: n(l.unit_price), carton_qty: n(l.carton_qty), net_weight: n(l.net_weight), gross_weight: n(l.gross_weight) })) };
      const res = await act.write(form.invoice_no ? "PUT" : "POST", form.invoice_no ? `/sales-shipment/invoices/${encodeURIComponent(form.invoice_no)}` : "/sales-shipment/invoices", payload);
      if (res?.status !== "success") return warn(firstError(res));
      setForm((f) => ({ ...f, invoice_no: res.data.invoice_no, state: res.data.state }));
      setSource(res.data.source);
      done(w("si.saved", { no: res.data.invoice_no }));
      reload();
    });
  const line = (id) => source?.lines.find((l) => l.shipment_line_id === id);
  const lineOpts = (source?.lines ?? []).map((l) => ({ value: l.shipment_line_id, label: l.label }));
  const sum = (f) => form.lines.reduce((a, l) => a + f(l), 0);
  const STATES = ["DRAFT", "FINAL", "CANCELLED"];
  const shown = useMemo(
    () =>
      rows
        .filter((r) => has(r.invoice_no, filter.no) && has(r.shipment_no, filter.ship) && has(r.so_no, filter.so) && (!filter.customer || r.customer_id === filter.customer) && (!filter.currency || r.currency_id === filter.currency))
        .filter((r) => (!filter.state || r.state === filter.state) && within(r.invoice_date, filter.from, filter.to))
        .map((r) => ({ ...r, state_label: w(`si.state.${r.state}`) })),
    [rows, filter, w],
  );
  const totals = Object.entries(shown.filter((r) => r.state !== "CANCELLED").reduce((a, r) => ({ ...a, [r.currency]: (a[r.currency] ?? 0) + r.total_amount }), {}));
  const step = (a) => (no) => act.write("POST", `/sales-shipment/invoices/${encodeURIComponent(no)}/${a}`);
  const view = (r) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/invoices/${encodeURIComponent(r.invoice_no)}`);
      if (res?.status === "success") printInvoice(w, res.data);
      else warn(firstError(res));
    });
  const customers = options.customers.map(({ value, label }) => ({ value, label }));

  return (
    <>
      <OrderFlow w={w} active="shipment-invoice" />
      <DeskTabs tab={tab} setTab={setTab} items={[listTab(w("si.list_tab"), rows.length), ...(canManage ? [["entry", w("si.entry_tab"), ReceiptText]] : [])]} />
      {tab === "entry" && canManage ? (
        <div className="grid gap-4 xl:grid-cols-[340px_1fr]">
          <SideList
            w={w}
            title={w("si.side.title")}
            rows={ships.filter((s) => s.due > 0)}
            refKey="shipment_no"
            columns={[
              { key: "shipment_no", label: w("sp.f.shipment_no"), mono: true },
              { key: "buyer", label: w("so.col.buyer") },
              { key: "due", label: w("si.side.due"), align: "right" },
              { key: "ship_date", label: w("sp.side.ship_date") },
            ]}
            searchPlaceholder={w("si.side.search")}
            filterLabel={w("so.col.buyer")}
            filterOptions={customers}
            filterPlaceholder={w("dr.side.any")}
            picked={form.shipment_no}
            onPick={(r) => (form.invoice_no ? warn(w("si.shipment_fixed")) : pick(r.shipment_no))}
          />
          <div className="flex min-w-0 flex-col gap-4">
            <EntryHead title={w("si.title")} hint={w("si.hint")}>
              <Field label={w("si.existing")} hint={w("od.ed.existing_hint")}>
                <Select value={form.invoice_no} onValueChange={(v) => (v ? open(v) : (setForm(blankForm()), setSource(null)))} options={[{ value: "", label: w("si.new_option") }, ...rows.map((r) => ({ value: r.invoice_no, label: `${r.invoice_no} · ${r.shipment_no} · ${w(`si.state.${r.state}`)}` }))]} placeholder={w("si.new_option")} aria-label={w("si.existing")} />
              </Field>
              <LockNote text={form.invoice_no && !editable ? w("si.locked", { no: form.invoice_no, status: w(`si.state.${form.state}`) }) : null} />
              <Group legend={w("si.g.invoice")}>
                <Field label={w("shp.f.shipment")} required hint={w("od.ed.pick_left")}>
                  <Select value={form.shipment_no} onValueChange={(v) => pick(v)} options={ships.map((s) => ({ value: s.shipment_no, label: `${s.shipment_no} · ${s.buyer}` }))} placeholder={ships.length ? w("od.ed.select") : w("od.ed.no_records")} disabled={!editable || Boolean(form.invoice_no)} aria-label={w("shp.f.shipment")} />
                </Field>
                <Field label={w("si.col.no")}>
                  <Input value={form.invoice_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("si.col.no")} />
                </Field>
                <Field label={w("si.f.date")} required>
                  <Input type="date" value={form.invoice_date} onChange={(e) => set("invoice_date", e.target.value)} disabled={!editable} aria-label={w("si.f.date")} />
                </Field>
                <Field label={w("so.f.currency")} required>
                  <Select value={form.currency_id} onValueChange={(v) => set("currency_id", v ?? "")} options={options.currencies} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("so.f.currency")} />
                </Field>
                <Field label={w("si.f.commercial")} hint={w("si.f.commercial_hint")}>
                  <Input value={form.commercial_invoice_ref} onChange={(e) => set("commercial_invoice_ref", e.target.value)} maxLength={40} disabled={!editable} aria-label={w("si.f.commercial")} />
                </Field>
              </Group>
              <Group legend={w("si.g.parties")}>
                <Field label={w("si.f.consignee")} required className="sm:col-span-2">
                  <Textarea value={form.consignee} onChange={(e) => set("consignee", e.target.value)} rows={3} disabled={!editable} aria-label={w("si.f.consignee")} />
                </Field>
                <Field label={w("si.f.notify")}>
                  <Textarea value={form.notify_party} onChange={(e) => set("notify_party", e.target.value)} rows={3} disabled={!editable} aria-label={w("si.f.notify")} />
                </Field>
              </Group>
              <Group legend={w("si.g.terms")}>
                <Field label={w("si.f.country")}>
                  <Select value={form.country_id} onValueChange={(v) => set("country_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.countries]} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("si.f.country")} />
                </Field>
                <Field label={w("si.f.port")}>
                  <Input value={form.port} onChange={(e) => set("port", e.target.value)} maxLength={80} disabled={!editable} aria-label={w("si.f.port")} />
                </Field>
                <Field label={w("sp.f.mode")} required>
                  <Select value={form.shipment_mode} onValueChange={(v) => set("shipment_mode", v ?? "Sea")} options={MODES.map((m) => ({ value: m, label: w(`sp.mode.${m}`) }))} disabled={!editable} aria-label={w("sp.f.mode")} />
                </Field>
                <Field label={w("so.f.incoterm")}>
                  <Select value={form.incoterm} onValueChange={(v) => set("incoterm", v ?? "")} options={[{ value: "", label: "—" }, ...options.incoterms.map((i) => ({ value: i, label: i }))]} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("so.f.incoterm")} />
                </Field>
                <Field label={w("si.f.terms")}>
                  <Input value={form.payment_terms} onChange={(e) => set("payment_terms", e.target.value)} maxLength={120} disabled={!editable} aria-label={w("si.f.terms")} />
                </Field>
                <Field label={w("si.f.lc")}>
                  <Input value={form.lc_contract_no} onChange={(e) => set("lc_contract_no", e.target.value)} maxLength={60} disabled={!editable} aria-label={w("si.f.lc")} />
                </Field>
                <Field label={w("od.f.remarks")} className="sm:col-span-3">
                  <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!editable} aria-label={w("od.f.remarks")} />
                </Field>
              </Group>
            </EntryHead>
            <EditGrid
              w={w}
              title={w("si.lines.title")}
              hint={w("si.lines.hint")}
              editable={editable}
              addLabel={lineOpts.length ? w("dc.lines.add") : null}
              onAdd={() => set("lines", [...form.lines, { _k: key(), shipment_line_id: "", description: "", qty: "", unit_price: "", carton_qty: "", net_weight: "", gross_weight: "" }])}
              emptyNote={w("si.lines.empty")}
              rows={form.lines}
              setRows={(v) => set("lines", v)}
              cols={[
                { key: "shipment_line_id", label: w("si.col.line"), type: "select", options: lineOpts, width: 220, onPick: (v) => ({ description: line(v)?.label ?? "", unit_price: String(line(v)?.rate ?? "") }) },
                { key: "description", label: w("si.col.description"), max: 300, width: 220 },
                { key: "balance", label: w("dc.col.balance"), align: "right", read: (r) => qty(line(r.shipment_line_id)?.balance) },
                { key: "qty", label: w("si.col.qty"), type: "number", align: "right" },
                { key: "unit_price", label: w("si.col.price"), type: "number", align: "right" },
                { key: "amount", label: w("si.col.amount"), align: "right", read: (r) => money((Number(r.qty) || 0) * (Number(r.unit_price) || 0)) },
                { key: "carton_qty", label: w("sp.col.cartons"), type: "number", align: "right", step: "1", width: 90 },
                { key: "net_weight", label: w("si.col.net"), type: "number", align: "right", width: 100 },
                { key: "gross_weight", label: w("si.col.gross"), type: "number", align: "right", width: 100 },
              ]}
              summaries={[
                [w("si.lines.qty"), qty(sum((l) => Number(l.qty) || 0))],
                [w("si.lines.amount"), money(sum((l) => (Number(l.qty) || 0) * (Number(l.unit_price) || 0)))],
                [w("si.lines.cartons"), sum((l) => Number(l.carton_qty) || 0)],
              ]}
            />
            <Foot w={w} note={w("si.footer")} editable={editable} pending={pending} onBack={() => setTab("list")} onClear={() => (setForm(blankForm()), setSource(null))} onSave={save} saveLabel={w("si.save")} />
          </div>
        </div>
      ) : (
        <>
          <Filters
            w={w}
            note={w("si.f.note")}
            value={filter}
            onChange={setFilter}
            fields={[
              { name: "no", label: w("si.col.no") },
              { name: "ship", label: w("sp.f.shipment_no") },
              { name: "so", label: w("so.f.so_no") },
              { name: "customer", label: w("so.col.buyer"), type: "select", options: customers },
              { name: "currency", label: w("so.f.currency"), type: "select", options: options.currencies },
              { name: "state", label: w("od.f.state"), type: "select", options: STATES.map((s) => ({ value: s, label: w(`si.state.${s}`) })) },
              { name: "from", label: w("od.f.from"), type: "date" },
              { name: "to", label: w("od.f.to"), type: "date" },
            ]}
          />
          <Tiles items={[[w("si.tile.all"), shown.length], [w("si.state.DRAFT"), shown.filter((r) => r.state === "DRAFT").length, "warning"], ...totals.map(([c, v]) => [c, money(v), "accent"])]} />
          <Register
            w={w}
            rows={shown}
            refKey="invoice_no"
            exportName="shipment-invoices"
            onDone={reload}
            actions={[
              ...(canManage ? [{ key: "edit", label: w("od.reg.edit"), when: (r) => r.state === "DRAFT", open: (r) => open(r.invoice_no) }] : []),
              { key: "print", label: w("od.reg.print"), open: view },
              ...(canManage
                ? [
                    { key: "finalize", label: w("scd.reg.finalize"), when: (r) => r.state === "DRAFT", confirm: w("si.confirm.finalize"), done: w("si.done.finalize"), run: step("finalize") },
                    { key: "cancel", label: w("od.reg.cancel"), when: (r) => r.state !== "CANCELLED", confirm: w("si.confirm.cancel"), done: w("si.done.cancel"), run: step("cancel"), destructive: true },
                    { key: "delete", label: w("od.reg.delete"), when: (r) => r.state === "DRAFT", confirm: w("od.confirm.delete"), done: w("od.done.delete"), run: (no) => act.write("DELETE", `/sales-shipment/invoices/${encodeURIComponent(no)}`), destructive: true },
                  ]
                : []),
            ]}
            emptyTitle={w("si.empty_title")}
            emptyText={w("od.empty_text")}
            columns={[
              { key: "invoice_no", label: w("si.col.no"), type: "mono" },
              { key: "invoice_date", label: w("od.f.date"), type: "date" },
              { key: "shipment_no", label: w("sp.f.shipment_no"), type: "mono" },
              { key: "so_no", label: w("so.f.so_no"), type: "mono" },
              { key: "buyer", label: w("so.col.buyer") },
              { key: "consignee", label: w("si.f.consignee") },
              { key: "currency", label: w("so.f.currency"), align: "center" },
              { key: "total_qty", label: w("si.col.qty"), type: "number", align: "right" },
              { key: "total_amount", label: w("si.col.amount"), type: "number", align: "right" },
              { key: "cartons", label: w("sp.col.cartons"), type: "number", align: "right" },
              { key: "commercial_invoice", label: w("si.f.commercial") },
              { key: "state_label", label: w("od.f.state"), type: "badge" },
            ]}
          />
        </>
      )}
    </>
  );
}

/* ───────────────────────── Packing list ───────────────────────── */

function printPacking(w, p) {
  printDocument({
    title: w("pl.print.title"),
    docNo: p.packing_no,
    subtitle: `${p.shipment_no} · ${p.so_no}${p.invoice_no ? ` · ${p.invoice_no}` : ""}`,
    orientation: "landscape",
    meta: [
      [w("so.col.buyer"), p.buyer],
      [w("pl.f.date"), day(p.packing_date)],
    ],
    sections: [
      {
        columns: ["item", "color", "size", "from", "to", "cartons", "per", "pcs", "net", "gross", "cbm"].map((k) => ({ label: w(`pl.col.${k}`), align: ["item", "color", "size"].includes(k) ? "left" : "right" })),
        rows: p.lines.map((l) => {
          const c = l.carton_to - l.carton_from + 1;
          return [l.label, l.color, l.size, l.carton_from, l.carton_to, c, qty(l.pcs_per_carton), qty(c * l.pcs_per_carton), qty(l.net_weight), qty(l.gross_weight), qty(l.cbm)];
        }),
        foot: [
          [w("so.print.total"), "", "", "", "", p.lines.reduce((a, l) => a + l.carton_to - l.carton_from + 1, 0), "", qty(p.lines.reduce((a, l) => a + (l.carton_to - l.carton_from + 1) * l.pcs_per_carton, 0)),
            qty(p.lines.reduce((a, l) => a + l.net_weight, 0)), qty(p.lines.reduce((a, l) => a + l.gross_weight, 0)), qty(p.lines.reduce((a, l) => a + l.cbm, 0))],
        ],
      },
    ],
    notes: [p.remarks],
    signatures: true,
  });
}

function PackingDesk({ initial, options, canManage, act }) {
  const w = useSs();
  const { pending, startTransition, warn, done } = useDesk();
  const [rows, setRows] = useState(initial);
  const [ships, setShips] = useState([]);
  const [tab, setTab] = useState("list");
  const [filter, setFilter] = useState({});
  const blankForm = () => ({ packing_no: "", shipment_no: "", packing_date: today(), invoice_no: "", remarks: "", state: "DRAFT", lines: [] });
  const [form, setForm] = useState(blankForm);
  const [source, setSource] = useState(null);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && form.state === "DRAFT";
  const reload = () =>
    startTransition(async () => {
      const [a, b] = await Promise.all([act.read("/sales-shipment/packing-lists"), act.read("/sales-shipment/invoice-shipments?for=packing")]);
      if (a?.status === "success") setRows(a.data);
      if (b?.status === "success") setShips(b.data);
    });
  useEffect(() => {
    act.read("/sales-shipment/invoice-shipments?for=packing").then((r) => r?.status === "success" && setShips(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const nextFrom = (lines) => Math.max(0, ...lines.map((l) => Number(l.carton_to) || 0)) + 1;
  const pick = (no) =>
    startTransition(async () => {
      if (!no) return (setSource(null), set("lines", []));
      const res = await act.read(`/sales-shipment/packing-lists/source/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const s = res.data;
      setSource(s);
      setForm((f) => ({ ...f, shipment_no: no, invoice_no: f.invoice_no || (s.invoices[0] ?? ""),
        lines: s.lines.filter((l) => l.balance > 0).map((l, i) => ({ _k: key(), shipment_line_id: l.shipment_line_id, color: l.color ?? "", size: l.size ?? "", carton_from: i === 0 ? "1" : "", carton_to: "", pcs_per_carton: "", net_weight: "", gross_weight: "", cbm: "" })) }));
    });
  const open = (no) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/packing-lists/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const p = res.data;
      setForm({ packing_no: p.packing_no, shipment_no: p.shipment_no, packing_date: p.packing_date, invoice_no: p.invoice_no ?? "", remarks: p.remarks ?? "", state: p.state,
        lines: p.lines.map((l) => ({ _k: key(), ...Object.fromEntries(Object.entries(l).map(([k, v]) => [k, v === null ? "" : String(v)])) })) });
      setSource(p.source);
      setTab("entry");
    });
  const save = () =>
    startTransition(async () => {
      const payload = { shipment_no: blank(form.shipment_no), packing_date: blank(form.packing_date), invoice_no: blank(form.invoice_no), remarks: blank(form.remarks),
        lines: form.lines.filter((l) => l.shipment_line_id).map((l) => ({ shipment_line_id: l.shipment_line_id, color: blank(l.color), size: blank(l.size), carton_from: n(l.carton_from), carton_to: n(l.carton_to),
          pcs_per_carton: n(l.pcs_per_carton), net_weight: n(l.net_weight), gross_weight: n(l.gross_weight), cbm: n(l.cbm) })) };
      const res = await act.write(form.packing_no ? "PUT" : "POST", form.packing_no ? `/sales-shipment/packing-lists/${encodeURIComponent(form.packing_no)}` : "/sales-shipment/packing-lists", payload);
      if (res?.status !== "success") return warn(firstError(res));
      setForm((f) => ({ ...f, packing_no: res.data.packing_no, state: res.data.state }));
      setSource(res.data.source);
      done(w("pl.saved", { no: res.data.packing_no }));
      reload();
    });
  const line = (id) => source?.lines.find((l) => l.shipment_line_id === id);
  const lineOpts = (source?.lines ?? []).map((l) => ({ value: l.shipment_line_id, label: l.label }));
  const cartons = (l) => Math.max(0, (Number(l.carton_to) || 0) - (Number(l.carton_from) || 0) + 1);
  const sum = (f) => form.lines.reduce((a, l) => a + f(l), 0);
  const STATES = ["DRAFT", "FINAL", "CANCELLED"];
  const shown = useMemo(
    () =>
      rows
        .filter((r) => has(r.packing_no, filter.no) && has(r.shipment_no, filter.ship) && has(r.invoice_no, filter.inv) && (!filter.customer || r.customer_id === filter.customer) && (!filter.state || r.state === filter.state) && within(r.packing_date, filter.from, filter.to))
        .map((r) => ({ ...r, state_label: w(`pl.state.${r.state}`) })),
    [rows, filter, w],
  );
  const step = (a) => (no) => act.write("POST", `/sales-shipment/packing-lists/${encodeURIComponent(no)}/${a}`);
  const view = (r) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/packing-lists/${encodeURIComponent(r.packing_no)}`);
      if (res?.status === "success") printPacking(w, res.data);
      else warn(firstError(res));
    });
  const customers = options.customers.map(({ value, label }) => ({ value, label }));
  const live = shown.filter((r) => r.state !== "CANCELLED");

  return (
    <>
      <OrderFlow w={w} active="packing-list" />
      <DeskTabs tab={tab} setTab={setTab} items={[listTab(w("pl.list_tab"), rows.length), ...(canManage ? [["entry", w("pl.entry_tab"), Package]] : [])]} />
      {tab === "entry" && canManage ? (
        <div className="grid gap-4 xl:grid-cols-[340px_1fr]">
          <SideList
            w={w}
            title={w("si.side.title")}
            rows={ships.filter((s) => s.due > 0)}
            refKey="shipment_no"
            columns={[
              { key: "shipment_no", label: w("sp.f.shipment_no"), mono: true },
              { key: "buyer", label: w("so.col.buyer") },
              { key: "due", label: w("pl.side.due"), align: "right" },
              { key: "ship_date", label: w("sp.side.ship_date") },
            ]}
            searchPlaceholder={w("si.side.search")}
            filterLabel={w("so.col.buyer")}
            filterOptions={customers}
            filterPlaceholder={w("dr.side.any")}
            picked={form.shipment_no}
            onPick={(r) => (form.packing_no ? warn(w("si.shipment_fixed")) : pick(r.shipment_no))}
          />
          <div className="flex min-w-0 flex-col gap-4">
            <EntryHead title={w("pl.title")} hint={w("pl.hint")}>
              <Field label={w("pl.existing")} hint={w("od.ed.existing_hint")}>
                <Select value={form.packing_no} onValueChange={(v) => (v ? open(v) : (setForm(blankForm()), setSource(null)))} options={[{ value: "", label: w("pl.new_option") }, ...rows.map((r) => ({ value: r.packing_no, label: `${r.packing_no} · ${r.shipment_no} · ${w(`pl.state.${r.state}`)}` }))]} placeholder={w("pl.new_option")} aria-label={w("pl.existing")} />
              </Field>
              <LockNote text={form.packing_no && !editable ? w("si.locked", { no: form.packing_no, status: w(`pl.state.${form.state}`) }) : null} />
              <Group legend={w("pl.g.packing")}>
                <Field label={w("shp.f.shipment")} required hint={w("od.ed.pick_left")}>
                  <Select value={form.shipment_no} onValueChange={(v) => pick(v)} options={ships.map((s) => ({ value: s.shipment_no, label: `${s.shipment_no} · ${s.buyer}` }))} placeholder={ships.length ? w("od.ed.select") : w("od.ed.no_records")} disabled={!editable || Boolean(form.packing_no)} aria-label={w("shp.f.shipment")} />
                </Field>
                <Field label={w("pl.col.no")}>
                  <Input value={form.packing_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("pl.col.no")} />
                </Field>
                <Field label={w("pl.f.date")} required>
                  <Input type="date" value={form.packing_date} onChange={(e) => set("packing_date", e.target.value)} disabled={!editable} aria-label={w("pl.f.date")} />
                </Field>
                <Field label={w("dh.f.invoice")} hint={w("pl.f.invoice_hint")}>
                  <Select value={form.invoice_no} onValueChange={(v) => set("invoice_no", v ?? "")} options={[{ value: "", label: "—" }, ...(source?.invoices ?? []).map((i) => ({ value: i, label: i }))]} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("dh.f.invoice")} />
                </Field>
                <Field label={w("od.f.remarks")} className="sm:col-span-2">
                  <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!editable} aria-label={w("od.f.remarks")} />
                </Field>
              </Group>
            </EntryHead>
            <EditGrid
              w={w}
              title={w("pl.lines.title")}
              hint={w("pl.lines.hint")}
              editable={editable}
              addLabel={lineOpts.length ? w("pl.lines.add") : null}
              onAdd={() => set("lines", [...form.lines, { _k: key(), shipment_line_id: form.lines.at(-1)?.shipment_line_id ?? "", color: form.lines.at(-1)?.color ?? "", size: form.lines.at(-1)?.size ?? "", carton_from: String(nextFrom(form.lines)), carton_to: "", pcs_per_carton: form.lines.at(-1)?.pcs_per_carton ?? "", net_weight: "", gross_weight: "", cbm: "" }])}
              emptyNote={w("pl.lines.empty")}
              rows={form.lines}
              setRows={(v) => set("lines", v)}
              cols={[
                { key: "shipment_line_id", label: w("si.col.line"), type: "select", options: lineOpts, width: 220, onPick: (v) => ({ color: line(v)?.color ?? "", size: line(v)?.size ?? "" }) },
                { key: "color", label: w("pl.col.color"), max: 120, width: 100 },
                { key: "size", label: w("pl.col.size"), max: 60, width: 80 },
                { key: "carton_from", label: w("pl.col.from"), type: "number", align: "right", step: "1", width: 80 },
                { key: "carton_to", label: w("pl.col.to"), type: "number", align: "right", step: "1", width: 80 },
                { key: "cartons", label: w("pl.col.cartons"), align: "right", read: (r) => cartons(r) },
                { key: "pcs_per_carton", label: w("pl.col.per"), type: "number", align: "right", width: 90 },
                { key: "pcs", label: w("pl.col.pcs"), align: "right", read: (r) => qty(cartons(r) * (Number(r.pcs_per_carton) || 0)) },
                { key: "net_weight", label: w("pl.col.net"), type: "number", align: "right", width: 90 },
                { key: "gross_weight", label: w("pl.col.gross"), type: "number", align: "right", width: 90 },
                { key: "cbm", label: w("pl.col.cbm"), type: "number", align: "right", width: 90 },
              ]}
              summaries={[
                [w("pl.lines.cartons"), sum(cartons)],
                [w("pl.lines.pcs"), qty(sum((l) => cartons(l) * (Number(l.pcs_per_carton) || 0)))],
                [w("pl.col.net"), qty(sum((l) => Number(l.net_weight) || 0))],
                [w("pl.col.gross"), qty(sum((l) => Number(l.gross_weight) || 0))],
                [w("pl.col.cbm"), qty(sum((l) => Number(l.cbm) || 0))],
              ]}
            />
            <Foot w={w} note={w("pl.footer")} editable={editable} pending={pending} onBack={() => setTab("list")} onClear={() => (setForm(blankForm()), setSource(null))} onSave={save} saveLabel={w("pl.save")} />
          </div>
        </div>
      ) : (
        <>
          <Filters
            w={w}
            value={filter}
            onChange={setFilter}
            fields={[
              { name: "no", label: w("pl.col.no") },
              { name: "ship", label: w("sp.f.shipment_no") },
              { name: "inv", label: w("si.col.no") },
              { name: "customer", label: w("so.col.buyer"), type: "select", options: customers },
              { name: "state", label: w("od.f.state"), type: "select", options: STATES.map((s) => ({ value: s, label: w(`pl.state.${s}`) })) },
              { name: "from", label: w("od.f.from"), type: "date" },
              { name: "to", label: w("od.f.to"), type: "date" },
            ]}
          />
          <Tiles items={[[w("pl.tile.all"), shown.length], [w("pl.col.cartons"), live.reduce((a, r) => a + r.cartons, 0)], [w("pl.col.pcs"), qty(live.reduce((a, r) => a + r.pcs, 0))], [w("pl.state.DRAFT"), shown.filter((r) => r.state === "DRAFT").length, "warning"]]} />
          <Register
            w={w}
            rows={shown}
            refKey="packing_no"
            exportName="packing-lists"
            onDone={reload}
            actions={[
              ...(canManage ? [{ key: "edit", label: w("od.reg.edit"), when: (r) => r.state === "DRAFT", open: (r) => open(r.packing_no) }] : []),
              { key: "print", label: w("od.reg.print"), open: view },
              ...(canManage
                ? [
                    { key: "finalize", label: w("scd.reg.finalize"), when: (r) => r.state === "DRAFT", confirm: w("pl.confirm.finalize"), done: w("si.done.finalize"), run: step("finalize") },
                    { key: "cancel", label: w("od.reg.cancel"), when: (r) => r.state !== "CANCELLED", confirm: w("pl.confirm.cancel"), done: w("si.done.cancel"), run: step("cancel"), destructive: true },
                    { key: "delete", label: w("od.reg.delete"), when: (r) => r.state === "DRAFT", confirm: w("od.confirm.delete"), done: w("od.done.delete"), run: (no) => act.write("DELETE", `/sales-shipment/packing-lists/${encodeURIComponent(no)}`), destructive: true },
                  ]
                : []),
            ]}
            emptyTitle={w("pl.empty_title")}
            emptyText={w("od.empty_text")}
            columns={[
              { key: "packing_no", label: w("pl.col.no"), type: "mono" },
              { key: "packing_date", label: w("od.f.date"), type: "date" },
              { key: "shipment_no", label: w("sp.f.shipment_no"), type: "mono" },
              { key: "invoice_no", label: w("si.col.no"), type: "mono" },
              { key: "buyer", label: w("so.col.buyer") },
              { key: "cartons", label: w("pl.col.cartons"), type: "number", align: "right" },
              { key: "pcs", label: w("pl.col.pcs"), type: "number", align: "right" },
              { key: "net_weight", label: w("pl.col.net"), type: "number", align: "right" },
              { key: "gross_weight", label: w("pl.col.gross"), type: "number", align: "right" },
              { key: "cbm", label: w("pl.col.cbm"), type: "number", align: "right" },
              { key: "state_label", label: w("od.f.state"), type: "badge" },
            ]}
          />
        </>
      )}
    </>
  );
}

/* ───────────────────────── Confirmation ───────────────────────── */

const CHECKS = ["qty", "booking", "container", "invoice", "packing", "documents"];
const Tick = ({ ok, label, inline = false }) => (ok ? <Check className={cn("size-4 shrink-0 text-success", inline ? "mt-0.5" : "mx-auto")} aria-label={label} /> : <X className={cn("size-4 shrink-0 text-danger", inline ? "mt-0.5" : "mx-auto")} aria-label={label} />);

function ShipmentConfirmDesk({ initial, canConfirm, act }) {
  const w = useSs();
  const { startTransition, warn } = useDesk();
  const [rows, setRows] = useState(initial);
  const [picked, setPicked] = useState("");
  const [detail, setDetail] = useState(null);
  const reload = () => act.read("/sales-shipment/confirmations-shipment").then((r) => r?.status === "success" && setRows(r.data));
  const check = () =>
    startTransition(async () => {
      if (!picked) return warn(w("cf.need_shipment"));
      const res = await act.read(`/sales-shipment/confirmations-shipment/${encodeURIComponent(picked)}/check`);
      if (res?.status !== "success") return warn(firstError(res));
      setDetail(res.data);
    });
  const shown = rows.filter((r) => !picked || r.shipment_no === picked).map((r) => ({ ...r, stage_label: w(`sp.stage.${r.stage}`) }));
  const print = (r) =>
    printDocument({
      title: w("cf.print.title"),
      docNo: r.shipment_no,
      subtitle: `${r.so_no} · ${w(`sp.stage.${r.stage}`)}`,
      meta: [
        [w("so.f.customer"), r.customer],
        [w("sp.lines.planned"), qty(r.planned_qty)],
        [w("cf.col.invoiced"), qty(r.invoiced)],
        [w("cf.col.packed"), qty(r.packed)],
        [w("cf.col.vessel"), r.vessel],
        [w("bk.f.etd"), r.etd ? day(r.etd) : null],
        [w("cf.col.confirmed"), r.confirmed],
      ],
      sections: [{ columns: [{ label: w("cf.col.check") }, { label: w("cf.col.result") }], rows: CHECKS.map((k) => [w(`cf.check.${k}`), { v: r[k] ? w("cf.pass") : w("cf.fail"), tone: r[k] ? "ok" : "bad" }]) }],
      signatures: true,
    });
  const tickCol = (k) => ({ key: k, label: w(`cf.col.${k}`), group: "ready", groupLabel: w("cf.grp.ready"), align: "center", render: (r) => <Tick ok={r[k]} label={`${w(`cf.check.${k}`)}: ${r[k] ? w("cf.pass") : w("cf.fail")}`} />, read: (r) => (r[k] ? "✓" : "✗") });
  return (
    <>
      <OrderFlow w={w} active="shipment-confirmation" />
      <section className="mb-4 rounded-lg border border-border bg-surface p-5">
        <h3 className="text-[15px] font-semibold">{w("cf.f.title")}</h3>
        <p className="mt-0.5 mb-3 text-[13px] text-foreground-muted">{w("cf.f.note")}</p>
        <div className="flex flex-wrap items-end gap-3">
          <Field label={w("shp.f.shipment")} hint={w("cf.f.shipment_hint")} className="min-w-72">
            <Select value={picked} onValueChange={(v) => (setPicked(v ?? ""), setDetail(null))} options={[{ value: "", label: w("od.f.all") }, ...rows.map((r) => ({ value: r.shipment_no, label: `${r.shipment_no} · ${r.so_no}` }))]} aria-label={w("shp.f.shipment")} />
          </Field>
          <Button variant="outline" onClick={check}>
            <ListChecks /> {w("cf.check_btn")}
          </Button>
        </div>
        {detail ? (
          <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {CHECKS.map((k) => (
              <div key={k} className={cn("flex items-start gap-2 rounded-md border px-3 py-2 text-sm", detail.checks[k] ? "border-success/30 bg-success-subtle" : "border-danger/30 bg-danger-subtle")}>
                <Tick ok={detail.checks[k]} label={w(`cf.check.${k}`)} inline />
                <span>
                  <b>{w(`cf.check.${k}`)}</b>
                  <span className="block text-xs text-foreground-muted">{w(`cf.hint.${k}`)}</span>
                </span>
              </div>
            ))}
          </div>
        ) : null}
      </section>
      <Tiles items={[[w("sp.tile.all"), shown.length], [w("cf.tile.ready"), shown.filter((r) => r.ready && ["PLANNING", "BOOKED", "DOCUMENTATION", "READY"].includes(r.stage)).length, "accent"], [w("cf.tile.not_ready"), shown.filter((r) => !r.ready && ["PLANNING", "BOOKED", "DOCUMENTATION", "READY"].includes(r.stage)).length, "warning"], [w("sp.stage.CONFIRMED"), shown.filter((r) => ["CONFIRMED", "CLOSED"].includes(r.stage)).length, "good"]]} />
      <Register
        w={w}
        rows={shown}
        refKey="shipment_no"
        exportName="shipment-confirmation"
        onDone={reload}
        actions={[
          ...(canConfirm
            ? [
                { key: "confirm", label: w("oc.reg.confirm"), when: (r) => ["PLANNING", "BOOKED", "DOCUMENTATION", "READY"].includes(r.stage), confirm: w("cf.confirm.confirm"), done: w("cf.done.confirm"), run: (no) => act.write("POST", `/sales-shipment/confirmations-shipment/${encodeURIComponent(no)}/confirm`) },
                { key: "unconfirm", label: w("cf.reg.unconfirm"), when: (r) => r.stage === "CONFIRMED", confirm: w("cf.confirm.unconfirm"), done: w("cf.done.unconfirm"), run: (no) => act.write("POST", `/sales-shipment/confirmations-shipment/${encodeURIComponent(no)}/unconfirm`), destructive: true },
              ]
            : []),
          { key: "print", label: w("od.reg.print"), open: print },
        ]}
        emptyTitle={w("cf.empty_title")}
        emptyText={w("od.empty_text")}
        columns={[
          { key: "shipment_no", label: w("sp.f.shipment_no"), type: "mono" },
          { key: "so_no", label: w("so.f.so_no"), type: "mono" },
          { key: "customer", label: w("so.f.customer") },
          { key: "planned_qty", label: w("sp.lines.planned"), type: "number", align: "right" },
          { key: "invoiced", label: w("cf.col.invoiced"), type: "number", align: "right" },
          { key: "packed", label: w("cf.col.packed"), type: "number", align: "right" },
          { key: "vessel", label: w("cf.col.vessel") },
          { key: "etd", label: w("bk.f.etd"), type: "date" },
          ...CHECKS.map(tickCol),
          { key: "stage_label", label: w("sp.f.stage"), type: "badge" },
        ]}
      />
    </>
  );
}

/* ───────────────────────── Closing ───────────────────────── */

function ShipmentCloseDesk({ initial, canConfirm, canReopen, act }) {
  const w = useSs();
  const [rows, setRows] = useState(initial);
  const reload = () => act.read("/sales-shipment/closings").then((r) => r?.status === "success" && setRows(r.data));
  const shown = rows.map((r) => ({ ...r, closed_on: r.closed_at?.slice(0, 10), stage_label: w(`sp.stage.${r.stage}`) }));
  return (
    <>
      <OrderFlow w={w} active="shipment-closing" />
      <Tiles items={[[w("cl.tile.awaiting"), shown.filter((r) => r.stage === "CONFIRMED").length, "warning"], [w("sp.stage.CLOSED"), shown.filter((r) => r.stage === "CLOSED").length, "good"], [w("sp.tile.shipped"), qty(shown.reduce((a, r) => a + r.shipped_qty, 0))]]} />
      <Register
        w={w}
        rows={shown}
        refKey="shipment_no"
        exportName="shipment-closing"
        onDone={reload}
        actions={[
          ...(canConfirm ? [{ key: "close", label: w("cl.reg.close"), when: (r) => r.stage === "CONFIRMED", confirm: w("cl.confirm.close"), done: w("cl.done.close"), run: (no) => act.write("POST", `/sales-shipment/closings/${encodeURIComponent(no)}/close`) }] : []),
          ...(canReopen ? [{ key: "reopen", label: w("mc.reopen"), when: (r) => r.stage === "CLOSED", confirm: w("cl.confirm.reopen"), done: w("cl.done.reopen"), run: (no) => act.write("POST", `/sales-shipment/closings/${encodeURIComponent(no)}/reopen`), destructive: true }] : []),
        ]}
        emptyTitle={w("cl.empty_title")}
        emptyText={w("cl.empty_text")}
        columns={[
          { key: "shipment_no", label: w("sp.f.shipment_no"), type: "mono" },
          { key: "so_no", label: w("so.f.so_no"), type: "mono" },
          { key: "po", label: w("cp.col.po") },
          { key: "shipped_qty", label: w("cl.col.qty"), type: "number", align: "right" },
          { key: "invoice_value", label: w("cl.col.value"), type: "number", align: "right" },
          { key: "shipment_date", label: w("sp.f.shipment_date"), type: "date" },
          { key: "vessel", label: w("cf.col.vessel") },
          { key: "containers", label: w("bk.col.containers") },
          { key: "documents", label: w("cl.col.documents"), type: "number", align: "right" },
          { key: "confirmation", label: w("cl.col.confirmation") },
          { key: "closed_on", label: w("cl.col.closed_at"), type: "date" },
          { key: "closed_by", label: w("cl.col.closed_by") },
          { key: "stage_label", label: w("sp.f.stage"), type: "badge" },
        ]}
      />
    </>
  );
}

export { BookingDesk, ContainerDesk, HandoverDesk, InvoiceDesk, PackingDesk, ShipmentConfirmDesk, ShipmentCloseDesk, FormDesk, dateRange, useDesk };
