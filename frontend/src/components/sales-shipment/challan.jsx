"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { Truck, Undo2 } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useToastManager } from "@/components/ui/toast";
import { Field, Group } from "@/components/dyehouse/chem-store";
import { printDocument } from "@/lib/print-document";
import { DeskTabs, EditGrid, EntryHead, Filters, Foot, LockNote, OrderFlow, Register, SideList, Tiles, blank, day, firstError, has, key, listTab, money, qty, today, useSs, within } from "./shared";

/*
 * Sales & Shipment → Order & Delivery (AnnotechERP 21/02), as Brickland lays
 * them out: Delivery Challan Issue ("Order to Shipment · Step 3 of 12":
 * Delivery Chalan List — Searching Data, tiles, the register with Edit,
 * Print, Submit, Mark Delivered, Cancel, Delete — and Delivery Chalan Entry:
 * the SO List on the left, Master Data, Carrier & References, and the lines
 * still to ship with ordered, delivered and the balance beside the box you
 * fill) and Delivery Challan Return (Challan Return List and the entry: the
 * Delivered Challan List on the left, Return Info, Remarks, Returned Goods).
 */

const n = (v) => (v === "" || v === null || v === undefined ? null : Number(v));

function printChallan(w, c, autoPrint) {
  printDocument({
    title: w("dc.print.title"),
    docNo: c.chalan_no,
    subtitle: `${w("dc.f.gate_pass")} ${c.gate_pass_no} · ${w(`dc.state.${c.state}`)}`,
    autoPrint,
    meta: [
      [w("dc.f.customer"), c.customer],
      [w("so.f.delivery_address"), c.address],
      [w("dc.f.chalan_date"), day(c.chalan_date)],
      [w("dc.f.order"), `${c.so_no} · ${c.po_wo_no}`],
      [w("dc.col.store"), c.store],
      [w("dc.f.target"), day(c.delivery_target_date)],
      [w("so.f.mode_of_shipment"), c.mode_of_shipment],
      [w("dc.f.carrier_name"), c.carrier_name],
      [w("dc.f.carrier_no"), c.carrier_no],
    ],
    sections: [
      {
        columns: ["item", "qty", "rate", "amount", "remarks"].map((k) => ({ label: w(`dc.col.${k}`), align: ["qty", "rate", "amount"].includes(k) ? "right" : "left" })),
        rows: c.lines.map((l) => [l.label, qty(l.qty), money(l.rate), money(l.qty * l.rate), l.remarks]),
        foot: [[w("so.print.total"), qty(c.lines.reduce((a, l) => a + l.qty, 0)), "", money(c.lines.reduce((a, l) => a + l.qty * l.rate, 0)), ""]],
      },
    ],
    notes: [c.pr_po_ref, c.remarks],
    signatures: [w("dc.print.prepared"), w("dc.print.store"), w("dc.print.received")],
  });
}

function ChallanDesk({ initial, options, canManage, query, act }) {
  const w = useSs();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [orders, setOrders] = useState([]);
  const [tab, setTab] = useState("list");
  const [filter, setFilter] = useState(query?.so ? { so: query.so } : {});
  const [pending, startTransition] = useTransition();
  const warn = (title) => toastManager.add({ title, type: "danger" });
  const blankForm = () => ({ chalan_no: "", gate_pass_no: "", so_no: "", customer_id: "", chalan_date: today(), store_id: "", delivery_target_date: "", mode_of_shipment: "By Van", currency_id: "", conv_rate: "1",
    carrier_name: "", carrier_no: "", pr_po_ref: "", remarks: "", state: "DRAFT", lines: [] });
  const [form, setForm] = useState(blankForm);
  const [source, setSource] = useState(null);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && form.state === "DRAFT";
  const reload = () =>
    startTransition(async () => {
      const [a, b] = await Promise.all([act.read("/sales-shipment/challans"), act.read("/sales-shipment/challans/orders")]);
      if (a?.status === "success") setRows(a.data);
      if (b?.status === "success") setOrders(b.data);
    });
  useEffect(() => {
    act.read("/sales-shipment/challans/orders").then((r) => r?.status === "success" && setOrders(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const pick = (so) =>
    startTransition(async () => {
      if (!so) return (setSource(null), set("lines", []));
      const res = await act.read(`/sales-shipment/challans/source/${encodeURIComponent(so)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const s = res.data;
      setSource(s);
      setForm((f) => ({ ...f, so_no: so, customer_id: s.customer_id, currency_id: s.currency_id, conv_rate: String(s.conv_rate), delivery_target_date: s.delivery_target_date, mode_of_shipment: s.mode_of_shipment,
        pr_po_ref: s.pr_po_ref ?? "", lines: s.lines.filter((l) => l.balance > 0).map((l) => ({ _k: key(), size_id: l.size_id, qty: String(l.balance), rate: String(l.rate), remarks: "" })) }));
    });
  const open = (no) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/challans/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const c = res.data;
      setForm({ ...blankForm(), ...Object.fromEntries(Object.entries(c).map(([k, v]) => [k, v ?? ""])), conv_rate: String(c.conv_rate),
        lines: c.lines.map((l) => ({ _k: key(), size_id: l.size_id, qty: String(l.qty), rate: String(l.rate), remarks: l.remarks ?? "", label: l.label })) });
      setSource(c.source ?? { lines: c.lines.map((l) => ({ size_id: l.size_id, label: l.label })) });
      setTab("entry");
    });
  const save = () =>
    startTransition(async () => {
      const payload = { ...Object.fromEntries(["so_no", "customer_id", "chalan_date", "store_id", "delivery_target_date", "mode_of_shipment", "currency_id", "carrier_name", "carrier_no", "pr_po_ref", "remarks"].map((k) => [k, blank(form[k])])),
        conv_rate: n(form.conv_rate), lines: form.lines.filter((l) => l.size_id).map((l) => ({ size_id: l.size_id, qty: n(l.qty), rate: n(l.rate), remarks: blank(l.remarks) })) };
      const res = await act.write(form.chalan_no ? "PUT" : "POST", form.chalan_no ? `/sales-shipment/challans/${encodeURIComponent(form.chalan_no)}` : "/sales-shipment/challans", payload);
      if (res?.status !== "success") return warn(firstError(res));
      setForm((f) => ({ ...f, chalan_no: res.data.chalan_no, gate_pass_no: res.data.gate_pass_no, state: res.data.state }));
      setSource(res.data.source);
      toastManager.add({ title: w("dc.saved", { no: res.data.chalan_no }), type: "success" });
      reload();
    });
  const line = (id) => source?.lines.find((l) => l.size_id === id);
  const lineOpts = (source?.lines ?? []).map((l) => ({ value: l.size_id, label: l.label }));
  const total = form.lines.reduce((a, l) => a + (Number(l.qty) || 0), 0);
  const value = form.lines.reduce((a, l) => a + (Number(l.qty) || 0) * (Number(l.rate) || 0), 0);

  const shown = useMemo(
    () =>
      rows
        .filter((r) => has(r.search, filter.buyer) && has(r.search, filter.style) && has(r.search, filter.order) && (!filter.section || r.section_ids.includes(filter.section)))
        .filter((r) => (!filter.bill || r.bill_status === filter.bill) && has(r.chalan_no, filter.no) && has(r.so_no, filter.so) && (!filter.customer || r.customer_id === filter.customer) && within(r.chalan_date, filter.from, filter.to))
        .map((r) => ({ ...r, bill_label: w(`dc.bill.${r.bill_status}`), state_label: w(`dc.state.${r.state}`) })),
    [rows, filter, w],
  );
  const step = (action) => (no) => act.write("POST", `/sales-shipment/challans/${encodeURIComponent(no)}/${action}`);
  const is = (...s) => (r) => s.includes(r.state);
  const view = (r, autoPrint) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/challans/${encodeURIComponent(r.chalan_no)}`);
      if (res?.status === "success") printChallan(w, res.data, autoPrint);
      else warn(firstError(res));
    });
  const actions = [
    ...(canManage ? [{ key: "edit", label: w("od.reg.edit"), when: is("DRAFT"), open: (r) => open(r.chalan_no) }] : []),
    { key: "print", label: w("od.reg.print"), open: (r) => view(r, true) },
    ...(canManage
      ? [
          { key: "submit", label: w("od.reg.submit"), when: is("DRAFT"), confirm: w("dc.confirm.submit"), done: w("dc.done.submit"), run: step("submit") },
          { key: "deliver", label: w("dc.reg.deliver"), when: is("SUBMITTED"), done: w("dc.done.deliver"), run: step("deliver") },
          { key: "cancel", label: w("od.reg.cancel"), when: is("DRAFT", "SUBMITTED"), confirm: w("dc.confirm.cancel"), done: w("dc.done.cancel"), run: step("cancel"), destructive: true },
          { key: "delete", label: w("od.reg.delete"), when: is("DRAFT"), confirm: w("od.confirm.delete"), done: w("od.done.delete"), run: (no) => act.write("DELETE", `/sales-shipment/challans/${encodeURIComponent(no)}`), destructive: true },
        ]
      : []),
  ];
  const customers = options.customers.map(({ value: v, label }) => ({ value: v, label }));
  const live = rows.filter((r) => r.state !== "CANCELLED");

  return (
    <>
      <OrderFlow w={w} active="delivery-chalan" />
      <DeskTabs tab={tab} setTab={setTab} items={[listTab(w("dc.list_tab"), rows.length), ...(canManage ? [["entry", w("dc.entry_tab"), Truck]] : [])]} />
      {tab === "entry" && canManage ? (
        <div className="grid gap-4 xl:grid-cols-[340px_1fr]">
          <SideList
            w={w}
            title={w("dc.side.title")}
            rows={orders}
            refKey="so_no"
            columns={[
              { key: "so_no", label: w("sc.side.so_no"), mono: true },
              { key: "customer", label: w("sc.side.customer") },
              { key: "due", label: w("dc.side.due"), align: "right" },
              { key: "target", label: w("dc.side.target") },
            ]}
            searchPlaceholder={w("dc.side.search")}
            filterLabel={w("dc.f.customer")}
            filterOptions={customers}
            filterPlaceholder={w("dc.side.any")}
            picked={form.so_no}
            onPick={(r) => (form.chalan_no ? warn(w("dc.order_fixed")) : pick(r.so_no))}
          />
          <div className="flex min-w-0 flex-col gap-4">
            <EntryHead title={w("dc.title")} hint={w("dc.hint")}>
              <Field label={w("dc.existing")} hint={w("od.ed.existing_hint")}>
                <Select value={form.chalan_no} onValueChange={(v) => (v ? open(v) : (setForm(blankForm()), setSource(null)))} options={[{ value: "", label: w("dc.new_option") }, ...rows.map((r) => ({ value: r.chalan_no, label: `${r.chalan_no} · ${r.so_no} · ${w(`dc.state.${r.state}`)}` }))]} placeholder={w("dc.new_option")} aria-label={w("dc.existing")} />
              </Field>
              <LockNote text={form.chalan_no && !editable ? w("dc.locked", { no: form.chalan_no, status: w(`dc.state.${form.state}`) }) : null} />
              <Group legend={w("so.g.master")}>
                <Field label={w("dc.f.order")} required hint={w("od.ed.pick_left")}>
                  <Select value={form.so_no} onValueChange={(v) => pick(v)} options={orders.map((o) => ({ value: o.so_no, label: `${o.so_no} · ${o.customer}` }))} placeholder={orders.length ? w("od.ed.select") : w("od.ed.no_records")} disabled={!editable || Boolean(form.chalan_no)} aria-label={w("dc.f.order")} />
                </Field>
                <Field label={w("od.f.company")} required>
                  <Input value={options.company} readOnly aria-label={w("od.f.company")} />
                </Field>
                <Field label={w("dc.f.chalan_no")}>
                  <Input value={form.chalan_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("dc.f.chalan_no")} />
                </Field>
                <Field label={w("dc.f.customer")} required>
                  <Select value={form.customer_id} onValueChange={(v) => set("customer_id", v ?? "")} options={customers} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("dc.f.customer")} />
                </Field>
                <Field label={w("dc.f.chalan_date")} required>
                  <Input type="date" value={form.chalan_date} onChange={(e) => set("chalan_date", e.target.value)} disabled={!editable} aria-label={w("dc.f.chalan_date")} />
                </Field>
                <Field label={w("dc.f.store")} required hint={w("dc.f.store_hint")}>
                  <Select value={form.store_id} onValueChange={(v) => set("store_id", v ?? "")} options={options.stores} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("dc.f.store")} />
                </Field>
                <Field label={w("dc.f.target")} required>
                  <Input type="date" value={form.delivery_target_date} onChange={(e) => set("delivery_target_date", e.target.value)} disabled={!editable} aria-label={w("dc.f.target")} />
                </Field>
                <Field label={w("so.f.mode_of_shipment")} required>
                  <Select value={form.mode_of_shipment} onValueChange={(v) => set("mode_of_shipment", v ?? "")} options={options.ship_modes.map((m) => ({ value: m, label: m }))} disabled={!editable} aria-label={w("so.f.mode_of_shipment")} />
                </Field>
                <Field label={w("so.f.currency")} required>
                  <Select value={form.currency_id} onValueChange={(v) => set("currency_id", v ?? "")} options={options.currencies} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("so.f.currency")} />
                </Field>
                <Field label={w("so.f.conv_rate")}>
                  <Input type="number" min="0" step="any" value={form.conv_rate} onChange={(e) => set("conv_rate", e.target.value)} disabled={!editable} aria-label={w("so.f.conv_rate")} />
                </Field>
              </Group>
              <Group legend={w("dc.g.carrier")}>
                <Field label={w("dc.f.carrier_name")}>
                  <Input value={form.carrier_name} onChange={(e) => set("carrier_name", e.target.value)} maxLength={120} disabled={!editable} aria-label={w("dc.f.carrier_name")} />
                </Field>
                <Field label={w("dc.f.carrier_no")}>
                  <Input value={form.carrier_no} onChange={(e) => set("carrier_no", e.target.value)} maxLength={60} disabled={!editable} aria-label={w("dc.f.carrier_no")} />
                </Field>
                <Field label={w("dc.f.gate_pass")} hint={w("dc.f.gate_pass_hint")}>
                  <Input value={form.gate_pass_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("dc.f.gate_pass")} />
                </Field>
                <Field label={w("so.f.pr_po_ref")}>
                  <Textarea value={form.pr_po_ref} onChange={(e) => set("pr_po_ref", e.target.value)} rows={2} placeholder="BUYER | STYLE | ORDER#" disabled={!editable} aria-label={w("so.f.pr_po_ref")} />
                </Field>
                <Field label={w("od.f.remarks")} className="sm:col-span-2">
                  <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!editable} aria-label={w("od.f.remarks")} />
                </Field>
              </Group>
            </EntryHead>
            <EditGrid
              w={w}
              title={w("dc.lines.title")}
              hint={w("dc.lines.hint")}
              editable={editable}
              addLabel={lineOpts.length ? w("dc.lines.add") : null}
              onAdd={() => set("lines", [...form.lines, { _k: key(), size_id: "", qty: "", rate: "", remarks: "" }])}
              emptyNote={w("dc.lines.empty")}
              rows={form.lines}
              setRows={(v) => set("lines", v)}
              cols={[
                { key: "size_id", label: w("dc.col.so_item"), type: "select", options: lineOpts, width: 200, onPick: (v) => ({ rate: String(line(v)?.rate ?? "") }) },
                { key: "info", label: w("dc.col.item_info"), read: (r) => line(r.size_id)?.label ?? r.label ?? "—" },
                { key: "ordered", label: w("dc.col.ordered"), align: "right", read: (r) => qty(line(r.size_id)?.ordered) },
                { key: "prev", label: w("dc.col.prev"), align: "right", read: (r) => qty(line(r.size_id)?.prev) },
                { key: "balance", label: w("dc.col.balance"), align: "right", read: (r) => qty(line(r.size_id)?.balance) },
                { key: "qty", label: w("dc.col.qty"), type: "number", align: "right" },
                { key: "rate", label: w("dc.col.rate"), type: "number", align: "right" },
                { key: "amount", label: w("dc.col.amount"), align: "right", read: (r) => money((Number(r.qty) || 0) * (Number(r.rate) || 0)) },
                { key: "remarks", label: w("dc.col.remarks"), max: 300 },
              ]}
              summaries={[
                [w("dc.lines.total"), qty(total)],
                [w("dc.lines.value"), money(value)],
              ]}
            />
            <Foot w={w} note={w("dc.footer")} status={w("dc.action_status")} editable={editable} pending={pending} onBack={() => setTab("list")} onClear={() => (setForm(blankForm()), setSource(null))} onSave={save} saveLabel={w("dc.save")} />
          </div>
        </div>
      ) : (
        <>
          <Filters
            w={w}
            note={w("dc.f.note")}
            value={filter}
            onChange={setFilter}
            fields={[
              { name: "buyer", label: w("so.col.buyer"), placeholder: `${w("so.col.buyer")}…` },
              { name: "style", label: w("so.col.style"), placeholder: `${w("so.col.style")}…` },
              { name: "order", label: w("so.col.order"), placeholder: `${w("so.col.order")}…` },
              { name: "section", label: w("so.col.section"), type: "select", options: options.sections },
              { name: "bill", label: w("dc.col.bill_status"), type: "select", options: ["WAITING_FOR_BILL", "PARTIALLY_BILLED", "BILLED"].map((s) => ({ value: s, label: w(`dc.bill.${s}`) })) },
              { name: "no", label: w("dc.col.chalan_no") },
              { name: "so", label: w("dc.col.so_no") },
              { name: "customer", label: w("dc.f.customer"), type: "select", options: customers },
              { name: "from", label: w("od.f.from"), type: "date" },
              { name: "to", label: w("od.f.to"), type: "date" },
            ]}
          />
          <Tiles
            items={[
              [w("dc.tile.all"), shown.length],
              [w("dc.tile.delivered"), qty(live.filter((r) => ["SUBMITTED", "DELIVERED"].includes(r.state)).reduce((a, r) => a + r.total_qty, 0))],
              [w("dc.bill.WAITING_FOR_BILL"), live.filter((r) => r.state === "DELIVERED" && r.bill_status === "WAITING_FOR_BILL").length, "warning"],
            ]}
          />
          <Register
            w={w}
            rows={shown}
            refKey="chalan_no"
            exportName="delivery-chalans"
            onDone={reload}
            actions={actions}
            emptyTitle={w("dc.empty_title")}
            emptyText={w("od.empty_text")}
            columns={[
              { key: "company", label: w("od.f.company") },
              { key: "chalan_no", label: w("dc.col.chalan_no"), type: "mono" },
              { key: "so_no", label: w("dc.col.so_no"), type: "mono" },
              { key: "customer", label: w("so.f.customer") },
              { key: "store", label: w("dc.col.store") },
              { key: "target_date", label: w("so.col.target"), type: "date" },
              { key: "is_revised", label: w("dc.col.rev"), type: "yesno", align: "center" },
              { key: "bill_label", label: w("dc.col.bill_status"), type: "badge" },
              { key: "state_label", label: w("od.f.state"), type: "badge" },
              { key: "total_qty", label: w("dc.col.total_qty"), type: "number", align: "right" },
              { key: "total_value", label: w("so.col.total_value"), type: "number", align: "right" },
            ]}
          />
        </>
      )}
    </>
  );
}

function printReturn(w, r, autoPrint) {
  printDocument({
    title: w("dr.print.title"),
    docNo: r.return_no,
    subtitle: `${r.chalan_no} · ${w(`dr.state.${r.state}`)}`,
    autoPrint,
    meta: [
      [w("dr.f.challan"), r.chalan_no],
      [w("dr.f.order_info"), r.order_info],
      [w("dr.f.return_date"), day(r.return_date)],
      [w("dr.f.reason"), w(`dr.reason.${r.reason}`)],
      [w("dr.col.confirmed_by"), r.confirmed],
    ],
    sections: [
      {
        columns: ["item", "unit", "qty", "remarks"].map((k) => ({ label: w(`dr.col.${k}`), align: k === "qty" ? "right" : "left" })),
        rows: r.lines.map((l) => [l.label, l.unit, qty(l.qty), l.remarks]),
        foot: [[w("so.print.total"), "", qty(r.lines.reduce((a, l) => a + l.qty, 0)), ""]],
      },
    ],
    notes: [r.remarks],
    signatures: true,
  });
}

function ChallanReturnDesk({ initial, options, canManage, act }) {
  const w = useSs();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [challans, setChallans] = useState([]);
  const [tab, setTab] = useState("list");
  const [filter, setFilter] = useState({});
  const [pending, startTransition] = useTransition();
  const warn = (title) => toastManager.add({ title, type: "danger" });
  const blankForm = () => ({ return_no: "", chalan_no: "", return_date: today(), reason: "", remarks: "", state: "DRAFT", lines: [] });
  const [form, setForm] = useState(blankForm);
  const [source, setSource] = useState(null);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && form.state === "DRAFT";
  const reload = () =>
    startTransition(async () => {
      const [a, b] = await Promise.all([act.read("/sales-shipment/challan-returns"), act.read("/sales-shipment/challan-returns/challans")]);
      if (a?.status === "success") setRows(a.data);
      if (b?.status === "success") setChallans(b.data);
    });
  useEffect(() => {
    act.read("/sales-shipment/challan-returns/challans").then((r) => r?.status === "success" && setChallans(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const pick = (no, except = null, lines = null) =>
    startTransition(async () => {
      if (!no) return (setSource(null), set("lines", []));
      const res = await act.read(`/sales-shipment/challan-returns/source/${encodeURIComponent(no)}${except ? `?except=${encodeURIComponent(except)}` : ""}`);
      if (res?.status !== "success") return warn(firstError(res));
      setSource(res.data);
      setForm((f) => ({ ...f, chalan_no: no, lines: lines ?? res.data.lines.filter((l) => l.returnable > 0).map((l) => ({ _k: key(), challan_line_id: l.challan_line_id, qty: "", remarks: "" })) }));
    });
  const open = (no) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/challan-returns/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const r = res.data;
      setForm({ return_no: r.return_no, chalan_no: r.chalan_no, return_date: r.return_date, reason: r.reason, remarks: r.remarks ?? "", state: r.state, lines: r.lines.map((l) => ({ _k: key(), challan_line_id: l.challan_line_id, qty: String(l.qty), remarks: l.remarks ?? "" })) });
      setSource(r.source);
      setTab("entry");
    });
  const save = () =>
    startTransition(async () => {
      const payload = { chalan_no: blank(form.chalan_no), return_date: blank(form.return_date), reason: blank(form.reason), remarks: blank(form.remarks),
        lines: form.lines.filter((l) => l.challan_line_id).map((l) => ({ challan_line_id: l.challan_line_id, qty: n(l.qty), remarks: blank(l.remarks) })) };
      const res = await act.write(form.return_no ? "PUT" : "POST", form.return_no ? `/sales-shipment/challan-returns/${encodeURIComponent(form.return_no)}` : "/sales-shipment/challan-returns", payload);
      if (res?.status !== "success") return warn(firstError(res));
      setForm((f) => ({ ...f, return_no: res.data.return_no, state: res.data.state, lines: f.lines.filter((l) => Number(l.qty) > 0) }));
      setSource(res.data.source);
      toastManager.add({ title: w("dr.saved", { no: res.data.return_no }), type: "success" });
      reload();
    });
  const line = (id) => source?.lines.find((l) => l.challan_line_id === id);
  const lineOpts = (source?.lines ?? []).map((l) => ({ value: l.challan_line_id, label: l.label }));
  const total = form.lines.reduce((a, l) => a + (Number(l.qty) || 0), 0);

  const shown = useMemo(
    () =>
      rows
        .filter((r) => has(r.return_no, filter.no) && has(r.chalan_no, filter.chalan) && has(r.so_no, filter.so) && (!filter.customer || r.customer_id === filter.customer) && (!filter.state || r.state === filter.state) && within(r.return_date, filter.from, filter.to))
        .map((r) => ({ ...r, reason_label: w(`dr.reason.${r.reason}`), state_label: w(`dr.state.${r.state}`) })),
    [rows, filter, w],
  );
  const step = (action) => (no) => act.write("POST", `/sales-shipment/challan-returns/${encodeURIComponent(no)}/${action}`);
  const is = (...s) => (r) => s.includes(r.state);
  const view = (r) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/challan-returns/${encodeURIComponent(r.return_no)}`);
      if (res?.status === "success") printReturn(w, res.data, true);
      else warn(firstError(res));
    });
  const actions = [
    ...(canManage ? [{ key: "edit", label: w("od.reg.edit"), when: is("DRAFT"), open: (r) => open(r.return_no) }] : []),
    { key: "print", label: w("od.reg.print"), open: view },
    ...(canManage
      ? [
          { key: "confirm", label: w("dr.reg.confirm"), when: is("DRAFT"), confirm: w("dr.confirm.confirm"), done: w("dr.done.confirm"), run: step("confirm") },
          { key: "cancel", label: w("od.reg.cancel"), when: is("DRAFT", "CONFIRMED"), confirm: w("dr.confirm.cancel"), done: w("dr.done.cancel"), run: step("cancel"), destructive: true },
          { key: "delete", label: w("od.reg.delete"), when: is("DRAFT"), confirm: w("od.confirm.delete"), done: w("od.done.delete"), run: (no) => act.write("DELETE", `/sales-shipment/challan-returns/${encodeURIComponent(no)}`), destructive: true },
        ]
      : []),
  ];
  const customers = options.customers.map(({ value: v, label }) => ({ value: v, label }));

  return (
    <>
      <DeskTabs tab={tab} setTab={setTab} items={[listTab(w("dr.list_tab"), rows.length), ...(canManage ? [["entry", w("dr.entry_tab"), Undo2]] : [])]} />
      {tab === "entry" && canManage ? (
        <div className="grid gap-4 xl:grid-cols-[340px_1fr]">
          <SideList
            w={w}
            title={w("dr.side.title")}
            rows={challans}
            refKey="chalan_no"
            columns={[
              { key: "chalan_no", label: w("dr.side.chalan_no"), mono: true },
              { key: "so_no", label: w("sc.side.so_no"), mono: true },
              { key: "customer", label: w("sc.side.customer") },
              { key: "returnable", label: w("dr.side.returnable"), align: "right" },
            ]}
            searchPlaceholder={w("dr.side.search")}
            filterLabel={w("sc.side.customer")}
            filterOptions={customers}
            filterPlaceholder={w("dr.side.any")}
            picked={form.chalan_no}
            onPick={(r) => (form.return_no ? warn(w("dr.challan_fixed")) : pick(r.chalan_no))}
          />
          <div className="flex min-w-0 flex-col gap-4">
            <EntryHead title={w("dr.title")} hint={w("dr.hint")}>
              <Field label={w("dr.existing")} hint={w("od.ed.existing_hint")}>
                <Select value={form.return_no} onValueChange={(v) => (v ? open(v) : (setForm(blankForm()), setSource(null)))} options={[{ value: "", label: w("dr.new_option") }, ...rows.map((r) => ({ value: r.return_no, label: `${r.return_no} · ${r.chalan_no} · ${w(`dr.state.${r.state}`)}` }))]} placeholder={w("dr.new_option")} aria-label={w("dr.existing")} />
              </Field>
              <LockNote text={form.return_no && !editable ? w("dr.locked", { no: form.return_no, status: w(`dr.state.${form.state}`) }) : null} />
              <Group legend={w("dr.g.info")}>
                <Field label={w("dr.f.challan")} required hint={w("od.ed.pick_left")}>
                  <Select value={form.chalan_no} onValueChange={(v) => pick(v)} options={challans.map((c) => ({ value: c.chalan_no, label: `${c.chalan_no} · ${c.customer}` }))} placeholder={challans.length ? w("od.ed.select") : w("od.ed.no_records")} disabled={!editable || Boolean(form.return_no)} aria-label={w("dr.f.challan")} />
                </Field>
                <Field label={w("dr.f.order_info")}>
                  <Input value={source?.order_info ?? ""} placeholder={w("dr.f.order_info_ph")} readOnly aria-label={w("dr.f.order_info")} />
                </Field>
                <Field label={w("dr.f.return_no")}>
                  <Input value={form.return_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("dr.f.return_no")} />
                </Field>
                <Field label={w("dr.f.return_date")} required>
                  <Input type="date" value={form.return_date} onChange={(e) => set("return_date", e.target.value)} disabled={!editable} aria-label={w("dr.f.return_date")} />
                </Field>
                <Field label={w("dr.f.reason")} required>
                  <Select value={form.reason} onValueChange={(v) => set("reason", v ?? "")} options={["Quality Issue", "Wrong Item", "Excess Delivery", "Buyer Rejection", "Damaged in Transit", "Other"].map((r) => ({ value: r, label: w(`dr.reason.${r}`) }))} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("dr.f.reason")} />
                </Field>
              </Group>
              <Group legend={w("od.f.remarks")}>
                <Field label={w("od.f.remarks")} className="sm:col-span-3">
                  <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!editable} aria-label={w("od.f.remarks")} />
                </Field>
              </Group>
            </EntryHead>
            <EditGrid
              w={w}
              title={w("dr.lines.title")}
              hint={w("dr.lines.hint")}
              editable={editable}
              addLabel={lineOpts.length ? w("dc.lines.add") : null}
              onAdd={() => set("lines", [...form.lines, { _k: key(), challan_line_id: "", qty: "", remarks: "" }])}
              emptyNote={w("dr.lines.empty")}
              rows={form.lines}
              setRows={(v) => set("lines", v)}
              cols={[
                { key: "challan_line_id", label: w("dr.col.line"), type: "select", options: lineOpts, width: 200 },
                { key: "info", label: w("dr.col.item"), read: (r) => line(r.challan_line_id)?.label ?? "—" },
                { key: "unit", label: w("dr.col.unit"), align: "center", read: (r) => line(r.challan_line_id)?.unit ?? "—" },
                { key: "delivered", label: w("dr.col.delivered"), align: "right", read: (r) => qty(line(r.challan_line_id)?.delivered) },
                { key: "prev", label: w("dr.col.prev"), align: "right", read: (r) => qty(line(r.challan_line_id)?.prev) },
                { key: "qty", label: w("dr.col.qty"), type: "number", align: "right" },
                { key: "remarks", label: w("dr.col.remarks"), max: 300 },
              ]}
              summaries={[[w("dr.lines.total"), qty(total)]]}
            />
            <Foot w={w} note={w("dr.footer")} editable={editable} pending={pending} onBack={() => setTab("list")} onClear={() => (setForm(blankForm()), setSource(null))} onSave={save} saveLabel={w("dr.save")} />
          </div>
        </div>
      ) : (
        <>
          <Filters
            w={w}
            note={w("dr.f.note")}
            value={filter}
            onChange={setFilter}
            fields={[
              { name: "no", label: w("dr.f.return_no") },
              { name: "chalan", label: w("dr.col.challan_no") },
              { name: "so", label: w("dc.col.so_no") },
              { name: "customer", label: w("so.f.customer"), type: "select", options: customers },
              { name: "state", label: w("od.f.state"), type: "select", options: ["DRAFT", "CONFIRMED", "CANCELLED"].map((s) => ({ value: s, label: w(`dr.state.${s}`) })) },
              { name: "from", label: w("od.f.from"), type: "date" },
              { name: "to", label: w("od.f.to"), type: "date" },
            ]}
          />
          <Tiles
            items={[
              [w("dr.tile.all"), shown.length],
              [w("dr.tile.returned"), qty(shown.filter((r) => r.state === "CONFIRMED").reduce((a, r) => a + r.total_qty, 0)), "good"],
              [w("dr.tile.drafts"), shown.filter((r) => r.state === "DRAFT").length, "warning"],
            ]}
          />
          <Register
            w={w}
            rows={shown}
            refKey="return_no"
            exportName="challan-returns"
            onDone={reload}
            actions={actions}
            emptyTitle={w("dr.empty_title")}
            emptyText={w("od.empty_text")}
            columns={[
              { key: "return_no", label: w("dr.f.return_no"), type: "mono" },
              { key: "return_date", label: w("dr.f.return_date"), type: "date" },
              { key: "chalan_no", label: w("dr.col.challan_no"), type: "mono" },
              { key: "so_no", label: w("so.f.so_no"), type: "mono" },
              { key: "customer", label: w("so.f.customer") },
              { key: "reason_label", label: w("dr.f.reason") },
              { key: "total_qty", label: w("dc.col.total_qty"), type: "number", align: "right" },
              { key: "confirmed_by", label: w("dr.col.confirmed_by") },
              { key: "state_label", label: w("od.f.state"), type: "badge" },
            ]}
          />
        </>
      )}
    </>
  );
}

export { ChallanDesk, ChallanReturnDesk };
