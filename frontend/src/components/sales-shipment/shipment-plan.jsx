"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { CalendarClock } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useToastManager } from "@/components/ui/toast";
import { Field, Group } from "@/components/dyehouse/chem-store";
import { printDocument } from "@/lib/print-document";
import { DeskTabs, EditGrid, EntryHead, Filters, Foot, LockNote, OrderFlow, Register, SideList, Tiles, blank, day, firstError, has, key, listTab, money, qty, today, useSs, within } from "./shared";

/*
 * Sales & Shipment → General → Shipment Planning (AnnotechERP 21/04), as
 * Brickland lays it out under "Order to Shipment · Step 4 of 12": the
 * Shipment Plan List (Searching Data, the tiles, the register: Edit, Print,
 * Cancel, Delete) and the Shipment Plan Entry — a confirmed order picked on
 * the left, Plan and Destination, the Planned Lines with order qty, what
 * other shipments planned, the balance, plan qty, cartons and rate.
 */

const MODES = ["Sea", "Air", "Road", "Sea-Air", "Courier"];
const STAGES = ["PLANNING", "BOOKED", "DOCUMENTATION", "READY", "CONFIRMED", "CLOSED", "CANCELLED"];
const OPEN = ["PLANNING", "BOOKED", "DOCUMENTATION", "READY"];
const n = (v) => (v === "" || v === null || v === undefined ? null : Number(v));

function printShipment(w, s) {
  printDocument({
    title: w("sp.print.title"),
    docNo: s.shipment_no,
    subtitle: `${s.so_no} · ${w(`sp.stage.${s.stage}`)}`,
    meta: [
      [w("so.f.customer"), s.customer],
      [w("cp.col.po"), s.po],
      [w("sp.f.plan_date"), day(s.plan_date)],
      [w("sp.f.shipment_date"), day(s.shipment_date)],
      [w("sp.f.ex_factory"), s.ex_factory_date ? day(s.ex_factory_date) : null],
      [w("sp.f.mode"), s.shipment_mode],
      [w("so.f.destination_country"), s.country],
      [w("so.f.destination_port"), s.destination_port],
      [w("sp.f.port_of_loading"), s.port_of_loading],
      [w("so.f.incoterm"), s.incoterm],
    ],
    sections: [
      {
        columns: ["item", "qty", "cartons", "rate", "amount"].map((k) => ({ label: w(`sp.col.${k}`), align: k === "item" ? "left" : "right" })),
        rows: s.lines.map((l) => [l.label, qty(l.qty), l.carton_qty, money(l.rate), money(l.qty * l.rate)]),
        foot: [[w("so.print.total"), qty(s.lines.reduce((a, l) => a + l.qty, 0)), s.lines.reduce((a, l) => a + l.carton_qty, 0), "", money(s.lines.reduce((a, l) => a + l.qty * l.rate, 0))]],
      },
    ],
    notes: [s.remarks],
    signatures: true,
  });
}

function ShipmentPlanDesk({ initial, options, canManage, act }) {
  const w = useSs();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  const warn = (title) => toastManager.add({ title, type: "danger" });
  const [rows, setRows] = useState(initial);
  const [orders, setOrders] = useState([]);
  const [tab, setTab] = useState("list");
  const [filter, setFilter] = useState({});
  const blankForm = () => ({ shipment_no: "", so_no: "", plan_date: today(), shipment_date: "", ex_factory_date: "", shipment_mode: "Sea", destination_country_id: "", destination_port: "", port_of_loading: "",
    incoterm: "", remarks: "", stage: "PLANNING", locked: false, lines: [] });
  const [form, setForm] = useState(blankForm);
  const [source, setSource] = useState(null);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && OPEN.includes(form.stage) && !form.locked;
  const reload = () =>
    startTransition(async () => {
      const [a, b] = await Promise.all([act.read("/sales-shipment/shipments"), act.read("/sales-shipment/shipments/orders")]);
      if (a?.status === "success") setRows(a.data);
      if (b?.status === "success") setOrders(b.data);
    });
  useEffect(() => {
    act.read("/sales-shipment/shipments/orders").then((r) => r?.status === "success" && setOrders(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const pick = (so) =>
    startTransition(async () => {
      if (!so) return (setSource(null), set("lines", []));
      const res = await act.read(`/sales-shipment/shipments/source/${encodeURIComponent(so)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const s = res.data;
      setSource(s);
      setForm((f) => ({ ...f, so_no: so, shipment_date: f.shipment_date || s.shipment_date, destination_country_id: f.destination_country_id || (s.destination_country_id ?? ""),
        destination_port: f.destination_port || (s.destination_port ?? ""), incoterm: f.incoterm || (s.incoterm ?? ""),
        lines: s.lines.filter((l) => l.balance > 0).map((l) => ({ _k: key(), size_id: l.size_id, qty: String(l.balance), carton_qty: "", rate: String(l.rate) })) }));
    });
  const open = (no) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/shipments/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const s = res.data;
      setForm({ ...blankForm(), ...Object.fromEntries(Object.entries(s).map(([k, v]) => [k, v ?? ""])), locked: s.locked,
        lines: s.lines.map((l) => ({ _k: key(), size_id: l.size_id, qty: String(l.qty), carton_qty: l.carton_qty ? String(l.carton_qty) : "", rate: String(l.rate), label: l.label })) });
      setSource(s.source ?? { lines: s.lines.map((l) => ({ size_id: l.size_id, label: l.label })) });
      setTab("entry");
    });
  const save = () =>
    startTransition(async () => {
      const payload = { ...Object.fromEntries(["so_no", "plan_date", "shipment_date", "ex_factory_date", "shipment_mode", "destination_country_id", "destination_port", "port_of_loading", "incoterm", "remarks"].map((k) => [k, blank(form[k])])),
        lines: form.lines.filter((l) => l.size_id).map((l) => ({ size_id: l.size_id, qty: n(l.qty), carton_qty: n(l.carton_qty), rate: n(l.rate) })) };
      const res = await act.write(form.shipment_no ? "PUT" : "POST", form.shipment_no ? `/sales-shipment/shipments/${encodeURIComponent(form.shipment_no)}` : "/sales-shipment/shipments", payload);
      if (res?.status !== "success") return warn(firstError(res));
      setForm((f) => ({ ...f, shipment_no: res.data.shipment_no, stage: res.data.stage, locked: res.data.locked }));
      setSource(res.data.source);
      toastManager.add({ title: w("sp.saved", { no: res.data.shipment_no }), type: "success" });
      reload();
    });
  const line = (id) => source?.lines.find((l) => l.size_id === id);
  const lineOpts = (source?.lines ?? []).map((l) => ({ value: l.size_id, label: l.label }));
  const sum = (f) => form.lines.reduce((a, l) => a + f(l), 0);

  const shown = useMemo(
    () =>
      rows
        .filter((r) => (!filter.customer || r.customer_id === filter.customer) && has(r.so_no, filter.so) && has(r.shipment_no, filter.no) && (!filter.stage || r.stage === filter.stage) && within(r.shipment_date, filter.from, filter.to))
        .map((r) => ({ ...r, stage_label: w(`sp.stage.${r.stage}`) })),
    [rows, filter, w],
  );
  const view = (r) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/shipments/${encodeURIComponent(r.shipment_no)}`);
      if (res?.status === "success") printShipment(w, res.data);
      else warn(firstError(res));
    });
  const actions = [
    ...(canManage ? [{ key: "edit", label: w("od.reg.edit"), when: (r) => OPEN.includes(r.stage) && !r.locked, open: (r) => open(r.shipment_no) }] : []),
    { key: "print", label: w("od.reg.print"), open: view },
    ...(canManage
      ? [
          { key: "cancel", label: w("od.reg.cancel"), when: (r) => OPEN.includes(r.stage), confirm: w("sp.confirm.cancel"), done: w("sp.done.cancel"), run: (no) => act.write("POST", `/sales-shipment/shipments/${encodeURIComponent(no)}/cancel`), destructive: true },
          { key: "delete", label: w("od.reg.delete"), when: (r) => r.stage === "PLANNING" && !r.locked, confirm: w("od.confirm.delete"), done: w("od.done.delete"), run: (no) => act.write("DELETE", `/sales-shipment/shipments/${encodeURIComponent(no)}`), destructive: true },
        ]
      : []),
  ];
  const customers = options.customers.map(({ value, label }) => ({ value, label }));
  const live = shown.filter((r) => r.stage !== "CANCELLED");

  return (
    <>
      <OrderFlow w={w} active="shipment-planning" />
      <DeskTabs tab={tab} setTab={setTab} items={[listTab(w("sp.list_tab"), rows.length), ...(canManage ? [["entry", w("sp.entry_tab"), CalendarClock]] : [])]} />
      {tab === "entry" && canManage ? (
        <div className="grid gap-4 xl:grid-cols-[340px_1fr]">
          <SideList
            w={w}
            title={w("sp.side.title")}
            rows={orders.filter((o) => o.balance > 0)}
            refKey="so_no"
            columns={[
              { key: "so_no", label: w("sc.side.so_no"), mono: true },
              { key: "customer", label: w("sc.side.customer") },
              { key: "po", label: w("cp.col.po") },
              { key: "balance", label: w("sp.side.unplanned"), align: "right" },
              { key: "ship_date", label: w("sp.side.ship_date") },
            ]}
            searchPlaceholder={w("sp.side.search")}
            filterLabel={w("so.f.customer")}
            filterOptions={customers}
            filterPlaceholder={w("dr.side.any")}
            picked={form.so_no}
            onPick={(r) => (form.shipment_no ? warn(w("sp.order_fixed")) : pick(r.so_no))}
          />
          <div className="flex min-w-0 flex-col gap-4">
            <EntryHead title={w("sp.title")} hint={w("sp.hint")}>
              <Field label={w("sp.existing")} hint={w("od.ed.existing_hint")}>
                <Select value={form.shipment_no} onValueChange={(v) => (v ? open(v) : (setForm(blankForm()), setSource(null)))} options={[{ value: "", label: w("sp.new_option") }, ...rows.map((r) => ({ value: r.shipment_no, label: `${r.shipment_no} · ${r.so_no} · ${w(`sp.stage.${r.stage}`)}` }))]} placeholder={w("sp.new_option")} aria-label={w("sp.existing")} />
              </Field>
              <LockNote text={form.shipment_no && !editable ? w(form.locked && OPEN.includes(form.stage) ? "sp.papered" : "sp.locked", { no: form.shipment_no, stage: w(`sp.stage.${form.stage}`) }) : null} />
              <Group legend={w("sp.g.plan")}>
                <Field label={w("sp.f.order")} required hint={w("sp.f.order_hint")}>
                  <Select value={form.so_no} onValueChange={(v) => pick(v)} options={orders.map((o) => ({ value: o.so_no, label: `${o.so_no} · ${o.customer}` }))} placeholder={orders.length ? w("od.ed.select") : w("od.ed.no_records")} disabled={!editable || Boolean(form.shipment_no)} aria-label={w("sp.f.order")} />
                </Field>
                <Field label={w("sp.f.shipment_no")}>
                  <Input value={form.shipment_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("sp.f.shipment_no")} />
                </Field>
                <Field label={w("sp.f.plan_date")} required>
                  <Input type="date" value={form.plan_date} onChange={(e) => set("plan_date", e.target.value)} disabled={!editable} aria-label={w("sp.f.plan_date")} />
                </Field>
                <Field label={w("sp.f.shipment_date")} required>
                  <Input type="date" value={form.shipment_date} onChange={(e) => set("shipment_date", e.target.value)} disabled={!editable} aria-label={w("sp.f.shipment_date")} />
                </Field>
                <Field label={w("sp.f.ex_factory")} hint={w("sp.f.ex_factory_hint")}>
                  <Input type="date" value={form.ex_factory_date} onChange={(e) => set("ex_factory_date", e.target.value)} disabled={!editable} aria-label={w("sp.f.ex_factory")} />
                </Field>
                <Field label={w("sp.f.mode")} required>
                  <Select value={form.shipment_mode} onValueChange={(v) => set("shipment_mode", v ?? "Sea")} options={MODES.map((m) => ({ value: m, label: w(`sp.mode.${m}`) }))} disabled={!editable} aria-label={w("sp.f.mode")} />
                </Field>
              </Group>
              <Group legend={w("sp.g.destination")}>
                <Field label={w("so.f.destination_country")}>
                  <Select value={form.destination_country_id} onValueChange={(v) => set("destination_country_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.countries]} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("so.f.destination_country")} />
                </Field>
                <Field label={w("so.f.destination_port")}>
                  <Input value={form.destination_port} onChange={(e) => set("destination_port", e.target.value)} maxLength={80} disabled={!editable} aria-label={w("so.f.destination_port")} />
                </Field>
                <Field label={w("sp.f.port_of_loading")}>
                  <Input value={form.port_of_loading} onChange={(e) => set("port_of_loading", e.target.value)} placeholder="Chattogram" maxLength={80} disabled={!editable} aria-label={w("sp.f.port_of_loading")} />
                </Field>
                <Field label={w("so.f.incoterm")}>
                  <Select value={form.incoterm} onValueChange={(v) => set("incoterm", v ?? "")} options={[{ value: "", label: "—" }, ...options.incoterms.map((i) => ({ value: i, label: i }))]} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("so.f.incoterm")} />
                </Field>
                <Field label={w("od.f.remarks")} className="sm:col-span-2">
                  <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!editable} aria-label={w("od.f.remarks")} />
                </Field>
              </Group>
            </EntryHead>
            <EditGrid
              w={w}
              title={w("sp.lines.title")}
              hint={w("sp.lines.hint")}
              editable={editable}
              addLabel={lineOpts.length ? w("dc.lines.add") : null}
              onAdd={() => set("lines", [...form.lines, { _k: key(), size_id: "", qty: "", carton_qty: "", rate: "" }])}
              emptyNote={w("sp.lines.empty")}
              rows={form.lines}
              setRows={(v) => set("lines", v)}
              cols={[
                { key: "size_id", label: w("dc.col.so_item"), type: "select", options: lineOpts, width: 200, onPick: (v) => ({ rate: String(line(v)?.rate ?? "") }) },
                { key: "info", label: w("dc.col.item_info"), read: (r) => line(r.size_id)?.label ?? r.label ?? "—" },
                { key: "ordered", label: w("dc.col.ordered"), align: "right", read: (r) => qty(line(r.size_id)?.ordered) },
                { key: "prev", label: w("sp.col.prev"), align: "right", read: (r) => qty(line(r.size_id)?.prev) },
                { key: "balance", label: w("dc.col.balance"), align: "right", read: (r) => qty(line(r.size_id)?.balance) },
                { key: "qty", label: w("sp.col.plan_qty"), type: "number", align: "right" },
                { key: "carton_qty", label: w("sp.col.cartons"), type: "number", align: "right", step: "1", width: 90 },
                { key: "rate", label: w("dc.col.rate"), type: "number", align: "right" },
                { key: "amount", label: w("dc.col.amount"), align: "right", read: (r) => money((Number(r.qty) || 0) * (Number(r.rate) || 0)) },
              ]}
              summaries={[
                [w("sp.lines.planned"), qty(sum((l) => Number(l.qty) || 0))],
                [w("sp.col.cartons"), sum((l) => Number(l.carton_qty) || 0)],
                [w("sp.lines.value"), money(sum((l) => (Number(l.qty) || 0) * (Number(l.rate) || 0)))],
              ]}
            />
            {source?.against_delivered ? <p className="rounded-md bg-info-subtle px-3 py-2 text-sm text-info">{w("sp.against_delivered")}</p> : null}
            <Foot w={w} note={w("sp.footer")} editable={editable} pending={pending} onBack={() => setTab("list")} onClear={() => (setForm(blankForm()), setSource(null))} onSave={save} saveLabel={w("sp.save")} />
          </div>
        </div>
      ) : (
        <>
          <Filters
            w={w}
            note={w("sp.f.note")}
            value={filter}
            onChange={setFilter}
            fields={[
              { name: "customer", label: w("so.f.customer"), type: "select", options: customers },
              { name: "so", label: w("so.f.so_no"), placeholder: "SO-…" },
              { name: "no", label: w("sp.f.shipment_no"), placeholder: "SHP-…" },
              { name: "stage", label: w("sp.f.stage"), type: "select", options: STAGES.map((s) => ({ value: s, label: w(`sp.stage.${s}`) })) },
              { name: "from", label: w("sp.f.from"), type: "date" },
              { name: "to", label: w("sp.f.to"), type: "date" },
            ]}
          />
          <Tiles
            items={[
              [w("sp.tile.all"), live.length],
              [w("sp.lines.planned"), qty(live.reduce((a, r) => a + r.planned_qty, 0))],
              [w("sp.tile.shipped"), qty(live.reduce((a, r) => a + r.shipped_qty, 0)), "good"],
              [w("sp.tile.open"), live.filter((r) => OPEN.includes(r.stage)).length, "warning"],
            ]}
          />
          <Register
            w={w}
            rows={shown}
            refKey="shipment_no"
            exportName="shipment-plans"
            onDone={reload}
            actions={actions}
            emptyTitle={w("sp.empty_title")}
            emptyText={w("od.empty_text")}
            columns={[
              { key: "shipment_no", label: w("sp.f.shipment_no"), type: "mono" },
              { key: "plan_date", label: w("sp.f.plan_date"), type: "date" },
              { key: "so_no", label: w("so.f.so_no"), type: "mono" },
              { key: "customer", label: w("so.f.customer") },
              { key: "po", label: w("cp.col.po") },
              { key: "shipment_date", label: w("sp.f.shipment_date"), type: "date" },
              { key: "ex_factory", label: w("sp.col.ex_factory"), type: "date" },
              { key: "destination", label: w("sp.col.destination") },
              { key: "mode", label: w("sp.col.mode") },
              { key: "planned_qty", label: w("sp.lines.planned"), type: "number", align: "right" },
              { key: "cartons", label: w("sp.col.cartons"), type: "number", align: "right" },
              { key: "value", label: w("oc.col.value"), type: "number", align: "right" },
              { key: "shipped_qty", label: w("so.col.shipped_qty"), type: "number", align: "right" },
              { key: "stage_label", label: w("sp.f.stage"), type: "badge" },
            ]}
          />
        </>
      )}
    </>
  );
}

export { ShipmentPlanDesk };
