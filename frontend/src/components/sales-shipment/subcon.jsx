"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { PackageOpen, Scale, Send, Undo2 } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useToastManager } from "@/components/ui/toast";
import { Field, Group } from "@/components/dyehouse/chem-store";
import { printDocument } from "@/lib/print-document";
import { DeskTabs, EditGrid, EntryHead, Filters, Foot, LockNote, Register, SideList, Tiles, blank, day, firstError, has, key, listTab, money, qty, today, useSs, within } from "./shared";

/*
 * Sales & Shipment → Sub-Contract (AnnotechERP 21/03), as Brickland lays the
 * five screens out: the Sub-Contract Delivery Register (Sales Contract
 * Delivery List / Entry: the SC List on the left, Master Data, Carrier &
 * References, the contract lines with SC Qty, Prev. Sent and Balance), the
 * Sub-Contract Receipt Register (Sales Contract Receive List / SC Receive
 * Entry: the S/C Challan List, Receive Type, received and rejected per
 * line), Sub-Contract Pending (what is still out, overdue first), the
 * Sub-Contract Return (back to the party against a receipt) and the
 * Sub-Contract Reconciliation (a chalan squared, short agreed, closed).
 */

const n = (v) => (v === "" || v === null || v === undefined ? null : Number(v));
const customersOf = (options) => options.customers.map(({ value, label }) => ({ value, label }));

function useDesk(act, listPath) {
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  const warn = (title) => toastManager.add({ title, type: "danger" });
  const done = (title) => toastManager.add({ title, type: "success" });
  return { toastManager, pending, startTransition, warn, done, listPath };
}

/* ───────────────────────── Delivery ───────────────────────── */

function printScDelivery(w, d, autoPrint) {
  printDocument({
    title: w("scd.print.title"),
    docNo: d.chalan_no,
    subtitle: `${w("dc.f.gate_pass")} ${d.gate_pass_no} · ${d.sc_no} · ${d.process}`,
    autoPrint,
    meta: [
      [w("scd.f.customer"), d.customer],
      [w("so.f.delivery_address"), d.address],
      [w("dc.f.chalan_date"), day(d.chalan_date)],
      [w("dc.col.store"), d.store],
      [w("dc.f.target"), day(d.delivery_target_date)],
      [w("scd.f.transfer_from"), d.transfer_from],
      [w("so.f.mode_of_shipment"), d.mode_of_shipment],
      [w("dc.f.carrier_name"), d.carrier_name],
      [w("dc.f.carrier_no"), d.carrier_no],
    ],
    sections: [
      {
        columns: ["item", "qty", "rate", "amount", "remarks"].map((k) => ({ label: w(`dc.col.${k}`), align: ["qty", "rate", "amount"].includes(k) ? "right" : "left" })),
        rows: d.lines.map((l) => [l.label, qty(l.qty), money(l.rate), money(l.qty * l.rate), l.remarks]),
        foot: [[w("so.print.total"), qty(d.lines.reduce((a, l) => a + l.qty, 0)), "", money(d.lines.reduce((a, l) => a + l.qty * l.rate, 0)), ""]],
      },
    ],
    notes: [d.pr_po_ref, d.remarks],
    signatures: [w("dc.print.prepared"), w("dc.print.store"), w("dc.print.received")],
  });
}

function ScDeliveryDesk({ initial, options, canManage, act }) {
  const w = useSs();
  const { pending, startTransition, warn, done } = useDesk(act);
  const [rows, setRows] = useState(initial);
  const [contracts, setContracts] = useState([]);
  const [tab, setTab] = useState("list");
  const [filter, setFilter] = useState({});
  const blankForm = () => ({ chalan_no: "", gate_pass_no: "", sc_no: "", customer_id: "", transfer_from_id: "", chalan_date: today(), store_id: "", delivery_target_date: "", mode_of_shipment: "By Van",
    carrier_name: "", carrier_no: "", pr_po_ref: "", remarks: "", state: "DRAFT", finalized: false, closed: false, lines: [] });
  const [form, setForm] = useState(blankForm);
  const [source, setSource] = useState(null);
  const [locked, setLocked] = useState(false);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && !locked && form.state !== "CANCELLED" && !form.finalized && !form.closed;
  const reload = () =>
    startTransition(async () => {
      const [a, b] = await Promise.all([act.read("/sales-shipment/sc-deliveries"), act.read("/sales-shipment/sc-deliveries/contracts")]);
      if (a?.status === "success") setRows(a.data);
      if (b?.status === "success") setContracts(b.data);
    });
  useEffect(() => {
    act.read("/sales-shipment/sc-deliveries/contracts").then((r) => r?.status === "success" && setContracts(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const pick = (sc) =>
    startTransition(async () => {
      if (!sc) return (setSource(null), set("lines", []));
      const res = await act.read(`/sales-shipment/sc-deliveries/source/${encodeURIComponent(sc)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const s = res.data;
      setSource(s);
      setForm((f) => ({ ...f, sc_no: sc, customer_id: f.customer_id || s.customer_id, delivery_target_date: f.delivery_target_date || s.target_date,
        lines: s.lines.filter((l) => l.balance > 0).map((l) => ({ _k: key(), programme_line_id: l.programme_line_id, qty: String(l.balance), rate: String(l.rate), remarks: "" })) }));
    });
  const open = (no) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/sc-deliveries/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const d = res.data;
      const row = rows.find((r) => r.chalan_no === no);
      setLocked(Boolean(row && row.returned_qty > 0));
      setForm({ ...blankForm(), ...Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v ?? ""])), finalized: d.finalized, closed: d.closed,
        lines: d.lines.map((l) => ({ _k: key(), programme_line_id: l.programme_line_id, qty: String(l.qty), rate: String(l.rate), remarks: l.remarks ?? "" })) });
      setSource(d.source);
      setTab("entry");
    });
  const save = () =>
    startTransition(async () => {
      const payload = { ...Object.fromEntries(["sc_no", "customer_id", "transfer_from_id", "chalan_date", "store_id", "delivery_target_date", "mode_of_shipment", "carrier_name", "carrier_no", "pr_po_ref", "remarks"].map((k) => [k, blank(form[k])])),
        lines: form.lines.filter((l) => l.programme_line_id).map((l) => ({ programme_line_id: l.programme_line_id, qty: n(l.qty), rate: n(l.rate), remarks: blank(l.remarks) })) };
      const res = await act.write(form.chalan_no ? "PUT" : "POST", form.chalan_no ? `/sales-shipment/sc-deliveries/${encodeURIComponent(form.chalan_no)}` : "/sales-shipment/sc-deliveries", payload);
      if (res?.status !== "success") return warn(firstError(res));
      setForm((f) => ({ ...f, chalan_no: res.data.chalan_no, gate_pass_no: res.data.gate_pass_no, state: res.data.state }));
      setSource(res.data.source);
      done(w("scd.saved", { no: res.data.chalan_no }));
      reload();
    });
  const line = (id) => source?.lines.find((l) => l.programme_line_id === id);
  const lineOpts = (source?.lines ?? []).map((l) => ({ value: l.programme_line_id, label: l.label }));
  const total = form.lines.reduce((a, l) => a + (Number(l.qty) || 0), 0);

  const shown = useMemo(
    () =>
      rows
        .filter((r) => has(r.chalan_no, filter.no) && has(r.sc_no, filter.sc) && (!filter.customer || r.customer_id === filter.customer) && within(r.target_date, filter.from, filter.to))
        .map((r) => ({ ...r, state_label: r.closed ? w("scd.state.CLOSED") : w(`scd.state.${r.state}`) })),
    [rows, filter, w],
  );
  const step = (action) => (no) => act.write("POST", `/sales-shipment/sc-deliveries/${encodeURIComponent(no)}/${action}`);
  const view = (r) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/sc-deliveries/${encodeURIComponent(r.chalan_no)}`);
      if (res?.status === "success") printScDelivery(w, res.data, true);
      else warn(firstError(res));
    });
  const live = (r) => !r.closed;
  const actions = [
    ...(canManage ? [{ key: "edit", label: w("od.reg.edit"), when: (r) => live(r) && r.state !== "CANCELLED" && !r.finalized && !(r.returned_qty > 0), open: (r) => open(r.chalan_no) }] : []),
    { key: "print", label: w("od.reg.print"), open: view },
    ...(canManage
      ? [
          { key: "deliver", label: w("dc.reg.deliver"), when: (r) => live(r) && r.state === "DRAFT", done: w("scd.done.deliver"), run: step("deliver") },
          { key: "finalize", label: w("scd.reg.finalize"), when: (r) => live(r) && r.state === "DELIVERED" && !r.finalized, confirm: w("scd.confirm.finalize"), done: w("scd.done.finalize"), run: step("finalize") },
          { key: "cancel", label: w("od.reg.cancel"), when: (r) => live(r) && r.state === "DRAFT", confirm: w("scd.confirm.cancel"), done: w("dc.done.cancel"), run: step("cancel"), destructive: true },
          { key: "delete", label: w("od.reg.delete"), when: (r) => r.state === "DRAFT", confirm: w("od.confirm.delete"), done: w("od.done.delete"), run: (no) => act.write("DELETE", `/sales-shipment/sc-deliveries/${encodeURIComponent(no)}`), destructive: true },
        ]
      : []),
  ];
  const customers = customersOf(options);
  const lockText = form.chalan_no && !editable ? w("scd.locked", { no: form.chalan_no, status: form.closed ? w("scd.state.CLOSED") : form.finalized ? w("scd.state.FINALIZED") : locked ? w("scd.received") : w(`scd.state.${form.state}`) }) : null;

  return (
    <>
      <DeskTabs tab={tab} setTab={setTab} items={[listTab(w("scd.list_tab"), rows.length), ...(canManage ? [["entry", w("scd.entry_tab"), Send]] : [])]} />
      {tab === "entry" && canManage ? (
        <div className="grid gap-4 xl:grid-cols-[340px_1fr]">
          <SideList
            w={w}
            title={w("scd.side.title")}
            rows={contracts}
            refKey="sc_no"
            columns={[
              { key: "sc_no", label: w("scd.side.sc_no"), mono: true },
              { key: "so_no", label: w("sc.side.so_no"), mono: true },
              { key: "party", label: w("scd.side.party") },
              { key: "due", label: w("dc.side.due"), align: "right" },
            ]}
            searchPlaceholder={w("scd.side.search")}
            filterLabel={w("dc.f.customer")}
            filterOptions={customers}
            filterPlaceholder={w("dc.side.any")}
            picked={form.sc_no}
            onPick={(r) => (form.chalan_no ? warn(w("scd.contract_fixed")) : pick(r.sc_no))}
          />
          <div className="flex min-w-0 flex-col gap-4">
            <EntryHead title={w("scd.title")} hint={w("scd.hint")}>
              <Field label={w("scd.existing")} hint={w("od.ed.existing_hint")}>
                <Select value={form.chalan_no} onValueChange={(v) => (v ? open(v) : (setForm(blankForm()), setSource(null), setLocked(false)))} options={[{ value: "", label: w("scd.new_option") }, ...rows.map((r) => ({ value: r.chalan_no, label: `${r.chalan_no} · ${r.sc_no} · ${r.customer}` }))]} placeholder={w("scd.new_option")} aria-label={w("scd.existing")} />
              </Field>
              <LockNote text={lockText} />
              <Group legend={w("so.g.master")}>
                <Field label={w("scd.f.contract")} required hint={w("od.ed.pick_left")}>
                  <Select value={form.sc_no} onValueChange={(v) => pick(v)} options={contracts.map((c) => ({ value: c.sc_no, label: `${c.sc_no} · ${c.party}` }))} placeholder={contracts.length ? w("od.ed.select") : w("od.ed.no_records")} disabled={!editable || Boolean(form.chalan_no)} aria-label={w("scd.f.contract")} />
                </Field>
                <Field label={w("od.f.company")} required>
                  <Input value={options.company} readOnly aria-label={w("od.f.company")} />
                </Field>
                <Field label={w("so.f.sc_no")}>
                  <Input value={form.sc_no} readOnly aria-label={w("so.f.sc_no")} />
                </Field>
                <Field label={w("dc.f.chalan_no")}>
                  <Input value={form.chalan_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("dc.f.chalan_no")} />
                </Field>
                <Field label={w("dc.f.chalan_date")} required>
                  <Input type="date" value={form.chalan_date} onChange={(e) => set("chalan_date", e.target.value)} disabled={!editable} aria-label={w("dc.f.chalan_date")} />
                </Field>
                <Field label={w("dc.f.customer")} required>
                  <Select value={form.customer_id} onValueChange={(v) => set("customer_id", v ?? "")} options={customers} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("dc.f.customer")} />
                </Field>
                <Field label={w("scd.f.transfer_from")} hint={w("scd.f.transfer_from_hint")}>
                  <Select value={form.transfer_from_id} onValueChange={(v) => set("transfer_from_id", v ?? "")} options={[{ value: "", label: "—" }, ...customers]} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("scd.f.transfer_from")} />
                </Field>
                <Field label={w("dc.f.store")} required>
                  <Select value={form.store_id} onValueChange={(v) => set("store_id", v ?? "")} options={options.stores} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("dc.f.store")} />
                </Field>
                <Field label={w("dc.f.target")} required>
                  <Input type="date" value={form.delivery_target_date} onChange={(e) => set("delivery_target_date", e.target.value)} disabled={!editable} aria-label={w("dc.f.target")} />
                </Field>
                <Field label={w("so.f.mode_of_shipment")} required>
                  <Select value={form.mode_of_shipment} onValueChange={(v) => set("mode_of_shipment", v ?? "")} options={options.ship_modes.map((m) => ({ value: m, label: m }))} disabled={!editable} aria-label={w("so.f.mode_of_shipment")} />
                </Field>
                <Field label={w("dc.f.gate_pass")}>
                  <Input value={form.gate_pass_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("dc.f.gate_pass")} />
                </Field>
              </Group>
              <Group legend={w("dc.g.carrier")}>
                <Field label={w("dc.f.carrier_name")}>
                  <Input value={form.carrier_name} onChange={(e) => set("carrier_name", e.target.value)} maxLength={120} disabled={!editable} aria-label={w("dc.f.carrier_name")} />
                </Field>
                <Field label={w("dc.f.carrier_no")}>
                  <Input value={form.carrier_no} onChange={(e) => set("carrier_no", e.target.value)} maxLength={60} disabled={!editable} aria-label={w("dc.f.carrier_no")} />
                </Field>
                <Field label={w("so.f.pr_po_ref")}>
                  <Textarea value={form.pr_po_ref} onChange={(e) => set("pr_po_ref", e.target.value)} rows={2} placeholder="BUYER | STYLE | ORDER#" disabled={!editable} aria-label={w("so.f.pr_po_ref")} />
                </Field>
                <Field label={w("od.f.remarks")} className="sm:col-span-3">
                  <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!editable} aria-label={w("od.f.remarks")} />
                </Field>
              </Group>
            </EntryHead>
            <EditGrid
              w={w}
              title={w("dc.lines.title")}
              hint={w("scd.lines.hint")}
              editable={editable}
              addLabel={lineOpts.length ? w("dc.lines.add") : null}
              onAdd={() => set("lines", [...form.lines, { _k: key(), programme_line_id: "", qty: "", rate: "", remarks: "" }])}
              emptyNote={w("scd.lines.empty")}
              rows={form.lines}
              setRows={(v) => set("lines", v)}
              cols={[
                { key: "programme_line_id", label: w("scd.col.line"), type: "select", options: lineOpts, width: 220, onPick: (v) => ({ rate: String(line(v)?.rate ?? "") }) },
                { key: "info", label: w("dc.col.item_info"), read: (r) => line(r.programme_line_id)?.label ?? "—" },
                { key: "contract", label: w("scd.col.contract"), align: "right", read: (r) => qty(line(r.programme_line_id)?.contract) },
                { key: "sent", label: w("scd.col.sent"), align: "right", read: (r) => qty(line(r.programme_line_id)?.sent) },
                { key: "balance", label: w("dc.col.balance"), align: "right", read: (r) => qty(line(r.programme_line_id)?.balance) },
                { key: "qty", label: w("dc.col.qty"), type: "number", align: "right" },
                { key: "rate", label: w("dc.col.rate"), type: "number", align: "right" },
                { key: "amount", label: w("dc.col.amount"), align: "right", read: (r) => money((Number(r.qty) || 0) * (Number(r.rate) || 0)) },
                { key: "remarks", label: w("dc.col.remarks"), max: 300 },
              ]}
              summaries={[[w("dc.col.total_qty"), qty(total)]]}
            />
            <Foot w={w} note={w("scd.footer")} status={w("scd.action_status")} editable={editable} pending={pending} onBack={() => setTab("list")} onClear={() => (setForm(blankForm()), setSource(null), setLocked(false))} onSave={save} saveLabel={w("scd.save")} />
          </div>
        </div>
      ) : (
        <>
          <Filters
            w={w}
            note={w("scd.f.note")}
            value={filter}
            onChange={setFilter}
            fields={[
              { name: "no", label: w("dc.col.chalan_no") },
              { name: "sc", label: w("scd.col.sc_no") },
              { name: "customer", label: w("dc.f.customer"), type: "select", options: customers },
              { name: "from", label: w("scd.f.from"), type: "date" },
              { name: "to", label: w("scd.f.to"), type: "date" },
            ]}
          />
          <Tiles
            items={[
              [w("dc.tile.all"), shown.length],
              [w("scd.tile.sent"), qty(shown.filter((r) => r.state === "DELIVERED").reduce((a, r) => a + r.total_qty, 0))],
              [w("scd.tile.out"), qty(shown.reduce((a, r) => a + r.out_qty, 0)), "warning"],
            ]}
          />
          <Register
            w={w}
            rows={shown}
            refKey="chalan_no"
            exportName="sc-deliveries"
            onDone={reload}
            actions={actions}
            emptyTitle={w("scd.empty_title")}
            emptyText={w("od.empty_text")}
            columns={[
              { key: "company", label: w("od.f.company") },
              { key: "chalan_no", label: w("dc.col.chalan_no"), type: "mono" },
              { key: "sc_no", label: w("scd.col.sc_no"), type: "mono" },
              { key: "customer", label: w("so.f.customer") },
              { key: "store", label: w("dc.col.store") },
              { key: "target_date", label: w("so.col.target"), type: "date" },
              { key: "is_revised", label: w("so.col.is_revised"), type: "yesno", align: "center" },
              { key: "finalized", label: w("scd.col.finalized"), type: "yesno", align: "center" },
              { key: "state_label", label: w("od.f.state"), type: "badge" },
              { key: "returned_qty", label: w("scd.col.returned"), type: "number", align: "right" },
              { key: "total_qty", label: w("dc.col.total_qty"), type: "number", align: "right" },
            ]}
          />
        </>
      )}
    </>
  );
}

/* ───────────────────────── Receipt ───────────────────────── */

const RCV_TYPES = ["FINISHED_GOODS", "LEFT_OVER", "REJECTION"];

function ScReceiptDesk({ initial, options, canManage, act }) {
  const w = useSs();
  const { pending, startTransition, warn, done } = useDesk(act);
  const [rows, setRows] = useState(initial);
  const [outstanding, setOutstanding] = useState([]);
  const [tab, setTab] = useState("list");
  const [filter, setFilter] = useState({});
  const blankForm = () => ({ mrr_no: "", chalan_no: "", mrr_date: today(), store_id: "", customer_id: "", receive_type: "FINISHED_GOODS", remarks: "", state: "RECEIVED", finalized: false, lines: [] });
  const [form, setForm] = useState(blankForm);
  const [source, setSource] = useState(null);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && form.state === "RECEIVED" && !form.finalized;
  const reload = () =>
    startTransition(async () => {
      const [a, b] = await Promise.all([act.read("/sales-shipment/sc-receipts"), act.read("/sales-shipment/sc-deliveries/outstanding")]);
      if (a?.status === "success") setRows(a.data);
      if (b?.status === "success") setOutstanding(b.data);
    });
  useEffect(() => {
    act.read("/sales-shipment/sc-deliveries/outstanding").then((r) => r?.status === "success" && setOutstanding(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const pick = (no) =>
    startTransition(async () => {
      if (!no) return (setSource(null), set("lines", []));
      const res = await act.read(`/sales-shipment/sc-receipts/source/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const s = res.data;
      setSource(s);
      setForm((f) => ({ ...f, chalan_no: no, customer_id: s.customer_id, store_id: f.store_id || s.store_id,
        lines: s.lines.filter((l) => l.balance > 0).map((l) => ({ _k: key(), delivery_line_id: l.delivery_line_id, qty: String(l.balance), rejected_qty: "", rate: String(l.rate), remarks: "" })) }));
    });
  const open = (no) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/sc-receipts/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const r = res.data;
      setForm({ mrr_no: r.mrr_no, chalan_no: r.chalan_no, mrr_date: r.mrr_date, store_id: r.store_id, customer_id: r.customer_id, receive_type: r.receive_type, remarks: r.remarks ?? "", state: r.state, finalized: r.finalized,
        lines: r.lines.map((l) => ({ _k: key(), delivery_line_id: l.delivery_line_id, qty: String(l.qty), rejected_qty: l.rejected_qty ? String(l.rejected_qty) : "", rate: String(l.rate), remarks: l.remarks ?? "" })) });
      setSource(r.source);
      setTab("entry");
    });
  const save = () =>
    startTransition(async () => {
      const payload = { ...Object.fromEntries(["chalan_no", "mrr_date", "store_id", "customer_id", "receive_type", "remarks"].map((k) => [k, blank(form[k])])),
        lines: form.lines.filter((l) => l.delivery_line_id).map((l) => ({ delivery_line_id: l.delivery_line_id, qty: n(l.qty), rejected_qty: n(l.rejected_qty), rate: n(l.rate), remarks: blank(l.remarks) })) };
      const res = await act.write(form.mrr_no ? "PUT" : "POST", form.mrr_no ? `/sales-shipment/sc-receipts/${encodeURIComponent(form.mrr_no)}` : "/sales-shipment/sc-receipts", payload);
      if (res?.status !== "success") return warn(firstError(res));
      setForm((f) => ({ ...f, mrr_no: res.data.mrr_no, state: res.data.state }));
      setSource(res.data.source);
      done(w("scr.saved", { no: res.data.mrr_no }));
      reload();
    });
  const line = (id) => source?.lines.find((l) => l.delivery_line_id === id);
  const lineOpts = (source?.lines ?? []).map((l) => ({ value: l.delivery_line_id, label: l.label }));
  const sum = (k) => form.lines.reduce((a, l) => a + (Number(l[k]) || 0), 0);

  const shown = useMemo(
    () =>
      rows
        .filter((r) => has(r.mrr_no, filter.no) && has(r.chalan_no, filter.chalan) && has(r.so_no, filter.so) && (!filter.store || r.store_id === filter.store) && (!filter.customer || r.customer_id === filter.customer))
        .filter((r) => (!filter.finalized || (filter.finalized === "yes") === r.finalized) && (!filter.type || r.receive_type === filter.type) && within(r.mrr_date, filter.from, filter.to))
        .map((r) => ({ ...r, type_label: w(`scr.type.${r.receive_type}`), state_label: w(`scr.state.${r.state}`), bill_label: w("scr.bill.none") })),
    [rows, filter, w],
  );
  const step = (action) => (no) => act.write("POST", `/sales-shipment/sc-receipts/${encodeURIComponent(no)}/${action}`);
  const can = (r) => r.state === "RECEIVED" && !r.finalized;
  const actions = canManage
    ? [
        { key: "edit", label: w("od.reg.edit"), when: can, open: (r) => open(r.mrr_no) },
        { key: "finalize", label: w("scd.reg.finalize"), when: can, confirm: w("scr.confirm.finalize"), done: w("scr.done.finalize"), run: step("finalize") },
        { key: "cancel", label: w("od.reg.cancel"), when: can, confirm: w("scr.confirm.cancel"), done: w("scr.done.cancel"), run: step("cancel"), destructive: true },
        { key: "delete", label: w("od.reg.delete"), when: can, confirm: w("od.confirm.delete"), done: w("od.done.delete"), run: (no) => act.write("DELETE", `/sales-shipment/sc-receipts/${encodeURIComponent(no)}`), destructive: true },
      ]
    : [];
  const customers = customersOf(options);

  return (
    <>
      <DeskTabs tab={tab} setTab={setTab} items={[listTab(w("scr.list_tab"), rows.length), ...(canManage ? [["entry", w("scr.entry_tab"), PackageOpen]] : [])]} />
      {tab === "entry" && canManage ? (
        <div className="grid gap-4 xl:grid-cols-[340px_1fr]">
          <SideList
            w={w}
            title={w("scr.side.title")}
            rows={outstanding.filter((r) => r.out > 0)}
            refKey="chalan_no"
            columns={[
              { key: "chalan_no", label: w("dr.side.chalan_no"), mono: true },
              { key: "sc_no", label: w("scd.side.sc_no"), mono: true },
              { key: "party", label: w("scd.side.party") },
              { key: "out", label: w("scr.side.out"), align: "right" },
            ]}
            searchPlaceholder={w("scr.side.search")}
            filterLabel={w("dc.f.customer")}
            filterOptions={customers}
            filterPlaceholder={w("dc.side.any")}
            picked={form.chalan_no}
            onPick={(r) => (form.mrr_no ? warn(w("scr.chalan_fixed")) : pick(r.chalan_no))}
          />
          <div className="flex min-w-0 flex-col gap-4">
            <EntryHead title={w("scr.title")} hint={w("scr.hint")}>
              <Field label={w("scr.existing")} hint={w("od.ed.existing_hint")}>
                <Select value={form.mrr_no} onValueChange={(v) => (v ? open(v) : (setForm(blankForm()), setSource(null)))} options={[{ value: "", label: w("scr.new_option") }, ...rows.map((r) => ({ value: r.mrr_no, label: `${r.mrr_no} · ${r.chalan_no} · ${w(`scr.type.${r.receive_type}`)}` }))]} placeholder={w("scr.new_option")} aria-label={w("scr.existing")} />
              </Field>
              <LockNote text={form.mrr_no && !editable ? w("scr.locked_note", { no: form.mrr_no, status: form.finalized ? w("scd.state.FINALIZED") : w(`scr.state.${form.state}`) }) : null} />
              <Group legend={w("so.g.master")}>
                <Field label={w("scr.f.chalan")} required hint={w("od.ed.pick_left")}>
                  <Select value={form.chalan_no} onValueChange={(v) => pick(v)} options={outstanding.map((c) => ({ value: c.chalan_no, label: `${c.chalan_no} · ${c.party}` }))} placeholder={outstanding.length ? w("od.ed.select") : w("od.ed.no_records")} disabled={!editable || Boolean(form.mrr_no)} aria-label={w("scr.f.chalan")} />
                </Field>
                <Field label={w("scr.f.mrr_no")}>
                  <Input value={form.mrr_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("scr.f.mrr_no")} />
                </Field>
                <Field label={w("scr.f.mrr_date")} required>
                  <Input type="date" value={form.mrr_date} onChange={(e) => set("mrr_date", e.target.value)} disabled={!editable} aria-label={w("scr.f.mrr_date")} />
                </Field>
                <Field label={w("od.f.company")} required>
                  <Input value={options.company} readOnly aria-label={w("od.f.company")} />
                </Field>
                <Field label={w("dc.f.store")} required>
                  <Select value={form.store_id} onValueChange={(v) => set("store_id", v ?? "")} options={options.stores} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("dc.f.store")} />
                </Field>
                <Field label={w("dc.f.customer")} required>
                  <Select value={form.customer_id} onValueChange={(v) => set("customer_id", v ?? "")} options={customers} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("dc.f.customer")} />
                </Field>
                <Field label={w("scr.f.type")} required>
                  <Select value={form.receive_type} onValueChange={(v) => set("receive_type", v ?? "FINISHED_GOODS")} options={RCV_TYPES.map((t) => ({ value: t, label: w(`scr.type.${t}`) }))} disabled={!editable} aria-label={w("scr.f.type")} />
                </Field>
                <Field label={w("od.f.remarks")} className="sm:col-span-2">
                  <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!editable} aria-label={w("od.f.remarks")} />
                </Field>
              </Group>
            </EntryHead>
            <EditGrid
              w={w}
              title={w("dc.lines.title")}
              hint={w("scr.lines.hint")}
              editable={editable}
              addLabel={lineOpts.length ? w("dc.lines.add") : null}
              onAdd={() => set("lines", [...form.lines, { _k: key(), delivery_line_id: "", qty: "", rejected_qty: "", rate: "", remarks: "" }])}
              emptyNote={w("scr.lines.empty")}
              rows={form.lines}
              setRows={(v) => set("lines", v)}
              cols={[
                { key: "delivery_line_id", label: w("dr.col.line"), type: "select", options: lineOpts, width: 220, onPick: (v) => ({ rate: String(line(v)?.rate ?? "") }) },
                { key: "info", label: w("dc.col.item_info"), read: (r) => line(r.delivery_line_id)?.label ?? "—" },
                { key: "sent", label: w("scr.col.sent"), align: "right", read: (r) => qty(line(r.delivery_line_id)?.sent) },
                { key: "prev", label: w("cb.col.prev"), align: "right", read: (r) => qty(line(r.delivery_line_id)?.prev) },
                { key: "balance", label: w("dc.col.balance"), align: "right", read: (r) => qty(line(r.delivery_line_id)?.balance) },
                { key: "qty", label: w("scr.col.qty"), type: "number", align: "right" },
                { key: "rejected_qty", label: w("scr.col.rejected"), type: "number", align: "right" },
                { key: "rate", label: w("dc.col.rate"), type: "number", align: "right" },
                { key: "remarks", label: w("dc.col.remarks"), max: 300 },
              ]}
              summaries={[
                [w("scr.lines.received"), qty(sum("qty"))],
                [w("scr.lines.rejected"), qty(sum("rejected_qty"))],
              ]}
            />
            <Foot w={w} note={w("scr.footer")} status={w("scr.action_status")} editable={editable} pending={pending} onBack={() => setTab("list")} onClear={() => (setForm(blankForm()), setSource(null))} onSave={save} saveLabel={w("scr.save")} />
          </div>
        </div>
      ) : (
        <>
          <Filters
            w={w}
            note={w("scr.f.note")}
            value={filter}
            onChange={setFilter}
            fields={[
              { name: "no", label: w("scr.f.mrr_no") },
              { name: "chalan", label: w("dr.col.challan_no") },
              { name: "so", label: w("so.f.so_no") },
              { name: "company", label: w("od.f.company"), type: "select", options: [{ value: "c", label: options.company }] },
              { name: "store", label: w("dc.f.store"), type: "select", options: options.stores },
              { name: "customer", label: w("dc.f.customer"), type: "select", options: customers },
              { name: "finalized", label: w("scr.f.finalized"), type: "select", options: [{ value: "yes", label: w("od.yes") }, { value: "no", label: w("od.no") }] },
              { name: "type", label: w("scr.f.type"), type: "select", options: RCV_TYPES.map((t) => ({ value: t, label: w(`scr.type.${t}`) })) },
              { name: "from", label: w("od.f.from"), type: "date" },
              { name: "to", label: w("od.f.to"), type: "date" },
            ]}
          />
          <Tiles
            items={[
              [w("scr.tile.all"), shown.length],
              [w("scr.tile.received"), qty(shown.filter((r) => r.state === "RECEIVED").reduce((a, r) => a + r.received_qty, 0)), "good"],
              [w("scr.tile.rejected"), qty(shown.filter((r) => r.state === "RECEIVED").reduce((a, r) => a + r.rejected_qty, 0)), "critical"],
            ]}
          />
          <Register
            w={w}
            rows={shown}
            refKey="mrr_no"
            exportName="sc-receipts"
            onDone={reload}
            actions={actions}
            emptyTitle={w("scr.empty_title")}
            emptyText={w("od.empty_text")}
            columns={[
              { key: "company", label: w("od.f.company") },
              { key: "mrr_no", label: w("scr.f.mrr_no"), type: "mono" },
              { key: "mrr_date", label: w("scr.f.mrr_date"), type: "date" },
              { key: "chalan_no", label: w("dr.col.challan_no"), type: "mono" },
              { key: "so_no", label: w("dc.col.so_no"), type: "mono" },
              { key: "customer", label: w("so.f.customer") },
              { key: "store", label: w("dc.col.store") },
              { key: "type_label", label: w("scr.col.type") },
              { key: "is_revised", label: w("so.col.is_revised"), type: "yesno", align: "center" },
              { key: "sc_type", label: w("scr.col.sc_type") },
              { key: "bill_label", label: w("dc.col.bill_status") },
              { key: "state_label", label: w("od.f.state"), type: "badge" },
              { key: "total_qty", label: w("dc.col.total_qty"), type: "number", align: "right" },
            ]}
          />
        </>
      )}
    </>
  );
}

/* ───────────────────────── Pending ───────────────────────── */

function ScPendingDesk({ initial, options }) {
  const w = useSs();
  const [filter, setFilter] = useState({});
  const shown = useMemo(
    () =>
      initial
        .filter((r) => (!filter.customer || r.customer_id === filter.customer) && has(r.sc_no, filter.sc) && within(r.chalan_date, filter.from, filter.to))
        .filter((r) => (filter.completed || r.status !== "COMPLETED") && (!filter.overdue || r.status === "OVERDUE"))
        .sort((a, b) => b.overdue - a.overdue)
        .map((r) => ({ ...r, status_label: w(`scp.status.${r.status}`) })),
    [initial, filter, w],
  );
  const open = shown.filter((r) => r.pending > 0);
  return (
    <>
      <Filters
        w={w}
        note={w("scp.f.note")}
        value={filter}
        onChange={setFilter}
        fields={[
          { name: "customer", label: w("scp.col.party"), type: "select", options: customersOf(options) },
          { name: "sc", label: w("scd.col.sc_no") },
          { name: "from", label: w("scp.f.from"), type: "date" },
          { name: "to", label: w("scp.f.to"), type: "date" },
          { name: "overdue", label: w("scp.f.overdue"), type: "checkbox" },
          { name: "completed", label: w("scp.f.completed"), type: "checkbox" },
        ]}
      />
      <Tiles
        items={[
          [w("scp.tile.lines"), open.length, "warning"],
          [w("scp.tile.qty"), qty(open.reduce((a, r) => a + r.pending, 0))],
          [w("scp.tile.overdue"), open.filter((r) => r.overdue > 0).length, open.some((r) => r.overdue > 0) ? "critical" : "good"],
        ]}
      />
      <Register
        w={w}
        rows={shown}
        refKey="line_id"
        exportName="sc-pending"
        actions={[]}
        emptyTitle={w("scp.empty_title")}
        emptyText={w("od.empty_text")}
        columns={[
          { key: "party", label: w("scp.col.party") },
          { key: "chalan_no", label: w("scp.col.chalan_no"), type: "mono" },
          { key: "chalan_date", label: w("scp.col.chalan_date"), type: "date" },
          { key: "sc_no", label: w("scd.side.sc_no"), type: "mono" },
          { key: "so_no", label: w("sc.side.so_no"), type: "mono" },
          { key: "style", label: w("so.col.style") },
          { key: "po", label: w("cp.col.po") },
          { key: "item", label: w("so.col.item") },
          { key: "delivered", label: w("cp.col.delivered"), type: "number", align: "right" },
          { key: "received", label: w("scp.col.received"), type: "number", align: "right" },
          { key: "rejected", label: w("scr.col.rejected"), type: "number", align: "right" },
          { key: "returned", label: w("cp.col.returned"), type: "number", align: "right" },
          { key: "pending", label: w("scp.col.pending"), type: "number", align: "right" },
          { key: "expected", label: w("scp.col.expected"), type: "date" },
          { key: "overdue", label: w("scp.col.overdue"), type: "number", align: "right" },
          { key: "status_label", label: w("cp.f.status"), type: "badge" },
        ]}
      />
    </>
  );
}

/* ───────────────────────── Return ───────────────────────── */

function printScReturn(w, t) {
  printDocument({
    title: w("sct.print.title"),
    docNo: t.return_no,
    subtitle: `${t.mrr_no} · ${t.chalan_no} · ${w(`sct.state.${t.state}`)}`,
    meta: [
      [w("scd.side.party"), t.party],
      [w("dr.f.return_date"), day(t.return_date)],
      [w("dr.f.reason"), w(`sct.reason.${t.reason}`)],
      [w("dr.col.confirmed_by"), t.confirmed],
    ],
    sections: [
      {
        columns: ["item", "qty", "remarks"].map((k) => ({ label: w(`dr.col.${k}`), align: k === "qty" ? "right" : "left" })),
        rows: t.lines.map((l) => [l.label, qty(l.qty), l.remarks]),
        foot: [[w("so.print.total"), qty(t.lines.reduce((a, l) => a + l.qty, 0)), ""]],
      },
    ],
    notes: [t.remarks],
    signatures: true,
  });
}

const SCT_REASONS = ["Rework", "Rejection Return", "Wrong Delivery", "Other"];

function ScReturnDesk({ initial, options, canManage, act }) {
  const w = useSs();
  const { pending, startTransition, warn, done } = useDesk(act);
  const [rows, setRows] = useState(initial);
  const [receipts, setReceipts] = useState([]);
  const [tab, setTab] = useState("list");
  const [filter, setFilter] = useState({});
  const blankForm = () => ({ return_no: "", mrr_no: "", return_date: today(), reason: "", remarks: "", state: "DRAFT", lines: [] });
  const [form, setForm] = useState(blankForm);
  const [source, setSource] = useState(null);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && form.state === "DRAFT";
  const reload = () =>
    startTransition(async () => {
      const [a, b] = await Promise.all([act.read("/sales-shipment/sc-returns"), act.read("/sales-shipment/sc-returns/receipts")]);
      if (a?.status === "success") setRows(a.data);
      if (b?.status === "success") setReceipts(b.data);
    });
  useEffect(() => {
    act.read("/sales-shipment/sc-returns/receipts").then((r) => r?.status === "success" && setReceipts(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const pick = (mrr) =>
    startTransition(async () => {
      if (!mrr) return (setSource(null), set("lines", []));
      const res = await act.read(`/sales-shipment/sc-returns/source/${encodeURIComponent(mrr)}`);
      if (res?.status !== "success") return warn(firstError(res));
      setSource(res.data);
      setForm((f) => ({ ...f, mrr_no: mrr, lines: res.data.lines.filter((l) => l.returnable > 0).map((l) => ({ _k: key(), receipt_line_id: l.receipt_line_id, qty: "", remarks: "" })) }));
    });
  const open = (no) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/sc-returns/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const t = res.data;
      setForm({ return_no: t.return_no, mrr_no: t.mrr_no, return_date: t.return_date, reason: t.reason, remarks: t.remarks ?? "", state: t.state, lines: t.lines.map((l) => ({ _k: key(), receipt_line_id: l.receipt_line_id, qty: String(l.qty), remarks: l.remarks ?? "" })) });
      setSource(t.source);
      setTab("entry");
    });
  const save = () =>
    startTransition(async () => {
      const payload = { mrr_no: blank(form.mrr_no), return_date: blank(form.return_date), reason: blank(form.reason), remarks: blank(form.remarks),
        lines: form.lines.filter((l) => l.receipt_line_id).map((l) => ({ receipt_line_id: l.receipt_line_id, qty: n(l.qty), remarks: blank(l.remarks) })) };
      const res = await act.write(form.return_no ? "PUT" : "POST", form.return_no ? `/sales-shipment/sc-returns/${encodeURIComponent(form.return_no)}` : "/sales-shipment/sc-returns", payload);
      if (res?.status !== "success") return warn(firstError(res));
      setForm((f) => ({ ...f, return_no: res.data.return_no, state: res.data.state, lines: f.lines.filter((l) => Number(l.qty) > 0) }));
      setSource(res.data.source);
      done(w("sct.saved", { no: res.data.return_no }));
      reload();
    });
  const line = (id) => source?.lines.find((l) => l.receipt_line_id === id);
  const lineOpts = (source?.lines ?? []).map((l) => ({ value: l.receipt_line_id, label: l.label }));

  const shown = useMemo(
    () =>
      rows
        .filter((r) => has(r.return_no, filter.no) && has(r.mrr_no, filter.mrr) && (!filter.customer || r.customer_id === filter.customer) && (!filter.state || r.state === filter.state) && within(r.return_date, filter.from, filter.to))
        .map((r) => ({ ...r, reason_label: w(`sct.reason.${r.reason}`), state_label: w(`sct.state.${r.state}`) })),
    [rows, filter, w],
  );
  const step = (action) => (no) => act.write("POST", `/sales-shipment/sc-returns/${encodeURIComponent(no)}/${action}`);
  const is = (...s) => (r) => s.includes(r.state);
  const view = (r) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/sc-returns/${encodeURIComponent(r.return_no)}`);
      if (res?.status === "success") printScReturn(w, res.data);
      else warn(firstError(res));
    });
  const actions = [
    ...(canManage ? [{ key: "edit", label: w("od.reg.edit"), when: is("DRAFT"), open: (r) => open(r.return_no) }] : []),
    { key: "print", label: w("od.reg.print"), open: view },
    ...(canManage
      ? [
          { key: "confirm", label: w("dr.reg.confirm"), when: is("DRAFT"), confirm: w("sct.confirm.confirm"), done: w("sct.done.confirm"), run: step("confirm") },
          { key: "cancel", label: w("od.reg.cancel"), when: is("DRAFT", "CONFIRMED"), confirm: w("sct.confirm.cancel"), done: w("dr.done.cancel"), run: step("cancel"), destructive: true },
          { key: "delete", label: w("od.reg.delete"), when: is("DRAFT"), confirm: w("od.confirm.delete"), done: w("od.done.delete"), run: (no) => act.write("DELETE", `/sales-shipment/sc-returns/${encodeURIComponent(no)}`), destructive: true },
        ]
      : []),
  ];
  const customers = customersOf(options);

  return (
    <>
      <DeskTabs tab={tab} setTab={setTab} items={[listTab(w("sct.list_tab"), rows.length), ...(canManage ? [["entry", w("sct.entry_tab"), Undo2]] : [])]} />
      {tab === "entry" && canManage ? (
        <div className="grid gap-4 xl:grid-cols-[340px_1fr]">
          <SideList
            w={w}
            title={w("sct.side.title")}
            rows={receipts.filter((r) => r.returnable > 0)}
            refKey="mrr_no"
            columns={[
              { key: "mrr_no", label: w("scr.f.mrr_no"), mono: true },
              { key: "chalan_no", label: w("dr.side.chalan_no"), mono: true },
              { key: "party", label: w("scd.side.party") },
              { key: "returnable", label: w("dr.side.returnable"), align: "right" },
            ]}
            searchPlaceholder={w("sct.side.search")}
            filterLabel={w("dc.f.customer")}
            filterOptions={customers}
            filterPlaceholder={w("dc.side.any")}
            picked={form.mrr_no}
            onPick={(r) => (form.return_no ? warn(w("sct.receipt_fixed")) : pick(r.mrr_no))}
          />
          <div className="flex min-w-0 flex-col gap-4">
            <EntryHead title={w("sct.title")} hint={w("sct.hint")}>
              <Field label={w("sct.existing")} hint={w("od.ed.existing_hint")}>
                <Select value={form.return_no} onValueChange={(v) => (v ? open(v) : (setForm(blankForm()), setSource(null)))} options={[{ value: "", label: w("sct.new_option") }, ...rows.map((r) => ({ value: r.return_no, label: `${r.return_no} · ${r.mrr_no} · ${w(`sct.state.${r.state}`)}` }))]} placeholder={w("sct.new_option")} aria-label={w("sct.existing")} />
              </Field>
              <LockNote text={form.return_no && !editable ? w("dr.locked", { no: form.return_no, status: w(`sct.state.${form.state}`) }) : null} />
              <Group legend={w("so.g.master")}>
                <Field label={w("sct.f.receipt")} required hint={w("od.ed.pick_left")}>
                  <Select value={form.mrr_no} onValueChange={(v) => pick(v)} options={receipts.map((r) => ({ value: r.mrr_no, label: `${r.mrr_no} · ${r.party}` }))} placeholder={receipts.length ? w("od.ed.select") : w("od.ed.no_records")} disabled={!editable || Boolean(form.return_no)} aria-label={w("sct.f.receipt")} />
                </Field>
                <Field label={w("dr.f.return_no")}>
                  <Input value={form.return_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("dr.f.return_no")} />
                </Field>
                <Field label={w("sct.f.chalan")}>
                  <Input value={source?.chalan_no ?? ""} readOnly aria-label={w("sct.f.chalan")} />
                </Field>
                <Field label={w("scd.side.party")}>
                  <Input value={source?.party ?? ""} readOnly aria-label={w("scd.side.party")} />
                </Field>
                <Field label={w("dr.f.return_date")} required>
                  <Input type="date" value={form.return_date} onChange={(e) => set("return_date", e.target.value)} disabled={!editable} aria-label={w("dr.f.return_date")} />
                </Field>
                <Field label={w("dr.f.reason")} required>
                  <Select value={form.reason} onValueChange={(v) => set("reason", v ?? "")} options={SCT_REASONS.map((r) => ({ value: r, label: w(`sct.reason.${r}`) }))} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("dr.f.reason")} />
                </Field>
                <Field label={w("od.f.remarks")} className="sm:col-span-3">
                  <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!editable} aria-label={w("od.f.remarks")} />
                </Field>
              </Group>
            </EntryHead>
            <EditGrid
              w={w}
              title={w("dc.lines.title")}
              hint={w("sct.lines.hint")}
              editable={editable}
              addLabel={lineOpts.length ? w("dc.lines.add") : null}
              onAdd={() => set("lines", [...form.lines, { _k: key(), receipt_line_id: "", qty: "", remarks: "" }])}
              emptyNote={w("sct.lines.empty")}
              rows={form.lines}
              setRows={(v) => set("lines", v)}
              cols={[
                { key: "receipt_line_id", label: w("sct.col.line"), type: "select", options: lineOpts, width: 220 },
                { key: "info", label: w("dc.col.item_info"), read: (r) => line(r.receipt_line_id)?.label ?? "—" },
                { key: "received", label: w("scp.col.received"), align: "right", read: (r) => qty(line(r.receipt_line_id)?.received) },
                { key: "rejected", label: w("scr.col.rejected"), align: "right", read: (r) => qty(line(r.receipt_line_id)?.rejected) },
                { key: "prev", label: w("dr.col.prev"), align: "right", read: (r) => qty(line(r.receipt_line_id)?.prev) },
                { key: "qty", label: w("dr.col.qty"), type: "number", align: "right" },
                { key: "remarks", label: w("dc.col.remarks"), max: 300 },
              ]}
              summaries={[[w("dr.lines.total"), qty(form.lines.reduce((a, l) => a + (Number(l.qty) || 0), 0))]]}
            />
            <Foot w={w} note={w("sct.footer")} status={w("sct.action_status")} editable={editable} pending={pending} onBack={() => setTab("list")} onClear={() => (setForm(blankForm()), setSource(null))} onSave={save} saveLabel={w("sct.save")} />
          </div>
        </div>
      ) : (
        <>
          <Filters
            w={w}
            note={w("sct.f.note")}
            value={filter}
            onChange={setFilter}
            fields={[
              { name: "no", label: w("dr.f.return_no") },
              { name: "mrr", label: w("scr.f.mrr_no") },
              { name: "customer", label: w("dc.f.customer"), type: "select", options: customers },
              { name: "state", label: w("od.f.state"), type: "select", options: ["DRAFT", "CONFIRMED", "CANCELLED"].map((s) => ({ value: s, label: w(`sct.state.${s}`) })) },
              { name: "from", label: w("od.f.from"), type: "date" },
              { name: "to", label: w("od.f.to"), type: "date" },
            ]}
          />
          <Tiles
            items={[
              [w("dr.tile.all"), shown.length],
              [w("sct.tile.awaiting"), shown.filter((r) => r.state === "DRAFT").length, "warning"],
              [w("sct.tile.returned"), qty(shown.filter((r) => r.state === "CONFIRMED").reduce((a, r) => a + r.total_qty, 0)), "good"],
            ]}
          />
          <Register
            w={w}
            rows={shown}
            refKey="return_no"
            exportName="sc-returns"
            onDone={reload}
            actions={actions}
            emptyTitle={w("sct.empty_title")}
            emptyText={w("od.empty_text")}
            columns={[
              { key: "return_no", label: w("dr.f.return_no"), type: "mono" },
              { key: "return_date", label: w("od.f.date"), type: "date" },
              { key: "mrr_no", label: w("scr.f.mrr_no"), type: "mono" },
              { key: "chalan_no", label: w("sct.f.chalan"), type: "mono" },
              { key: "party", label: w("scd.side.party") },
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

/* ───────────────────────── Reconciliation ───────────────────────── */

const SCC_STATES = ["PENDING", "MATCHED", "SHORT", "EXCESS", "RECONCILED"];

function ScReconDesk({ initial, options, canManage, canReopen, act }) {
  const w = useSs();
  const { pending, startTransition, warn, done } = useDesk(act);
  const [rows, setRows] = useState(initial);
  const [outstanding, setOutstanding] = useState([]);
  const [tab, setTab] = useState("list");
  const [filter, setFilter] = useState({});
  const blankForm = () => ({ recon_no: "", chalan_no: "", recon_date: today(), remarks: "", state: "", lines: [] });
  const [form, setForm] = useState(blankForm);
  const [source, setSource] = useState(null);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && form.state !== "RECONCILED";
  const reload = () =>
    startTransition(async () => {
      const [a, b] = await Promise.all([act.read("/sales-shipment/sc-reconciliations"), act.read("/sales-shipment/sc-deliveries/outstanding")]);
      if (a?.status === "success") setRows(a.data);
      if (b?.status === "success") setOutstanding(b.data);
    });
  useEffect(() => {
    act.read("/sales-shipment/sc-deliveries/outstanding").then((r) => r?.status === "success" && setOutstanding(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const pick = (no) =>
    startTransition(async () => {
      if (!no) return (setSource(null), set("lines", []));
      const res = await act.read(`/sales-shipment/sc-reconciliations/source/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      setSource(res.data);
      setForm((f) => ({ ...f, chalan_no: no, lines: res.data.lines.map((l) => ({ _k: key(), delivery_line_id: l.delivery_line_id, short_qty: "" })) }));
    });
  const open = (no) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/sc-reconciliations/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const r = res.data;
      setForm({ recon_no: r.recon_no, chalan_no: r.chalan_no, recon_date: r.recon_date, remarks: r.remarks ?? "", state: r.state, lines: r.lines.map((l) => ({ _k: key(), delivery_line_id: l.delivery_line_id, short_qty: l.short_qty ? String(l.short_qty) : "" })) });
      setSource({ chalan_no: r.chalan_no, party: r.party, lines: r.lines.map((l) => ({ ...l, out: l.balance + l.short_qty })) });
      setTab("entry");
    });
  const save = () =>
    startTransition(async () => {
      const payload = { chalan_no: blank(form.chalan_no), recon_date: blank(form.recon_date), remarks: blank(form.remarks), lines: form.lines.filter((l) => l.delivery_line_id).map((l) => ({ delivery_line_id: l.delivery_line_id, short_qty: n(l.short_qty) ?? 0 })) };
      const res = await act.write(form.recon_no ? "PUT" : "POST", form.recon_no ? `/sales-shipment/sc-reconciliations/${encodeURIComponent(form.recon_no)}` : "/sales-shipment/sc-reconciliations", payload);
      if (res?.status !== "success") return warn(firstError(res));
      const r = res.data;
      setForm((f) => ({ ...f, recon_no: r.recon_no, state: r.state }));
      setSource({ chalan_no: r.chalan_no, party: r.party, lines: r.lines.map((l) => ({ ...l, out: l.balance + l.short_qty })) });
      done(w("scc.saved", { no: r.recon_no, state: w(`scc.state.${r.state}`) }));
      reload();
    });
  const line = (id) => source?.lines.find((l) => l.delivery_line_id === id);

  const shown = useMemo(
    () =>
      rows
        .filter((r) => has(r.recon_no, filter.no) && has(r.chalan_no, filter.chalan) && (!filter.customer || r.customer_id === filter.customer) && (!filter.state || r.state === filter.state) && within(r.recon_date, filter.from, filter.to))
        .map((r) => ({ ...r, state_label: w(`scc.state.${r.state}`) })),
    [rows, filter, w],
  );
  const step = (action) => (no) => act.write("POST", `/sales-shipment/sc-reconciliations/${encodeURIComponent(no)}/${action}`);
  const is = (...s) => (r) => s.includes(r.state);
  const actions = [
    ...(canManage
      ? [
          { key: "edit", label: w("od.reg.edit"), when: (r) => r.state !== "RECONCILED", open: (r) => open(r.recon_no) },
          { key: "reconcile", label: w("scc.reg.reconcile"), when: is("MATCHED", "SHORT", "EXCESS"), confirm: w("scc.confirm.reconcile"), done: w("scc.done.reconcile"), run: step("reconcile") },
          { key: "delete", label: w("od.reg.delete"), when: (r) => r.state !== "RECONCILED", confirm: w("od.confirm.delete"), done: w("od.done.delete"), run: (no) => act.write("DELETE", `/sales-shipment/sc-reconciliations/${encodeURIComponent(no)}`), destructive: true },
        ]
      : []),
    ...(canReopen ? [{ key: "reopen", label: w("mc.reopen"), when: is("RECONCILED"), confirm: w("scc.confirm.reopen"), done: w("scc.done.reopen"), run: step("reopen"), destructive: true }] : []),
  ];
  const customers = customersOf(options);
  const shortTotal = form.lines.reduce((a, l) => a + (Number(l.short_qty) || 0), 0);

  return (
    <>
      <DeskTabs tab={tab} setTab={setTab} items={[listTab(w("scc.list_tab"), rows.length), ...(canManage ? [["entry", w("scc.entry_tab"), Scale]] : [])]} />
      {tab === "entry" && canManage ? (
        <div className="grid gap-4 xl:grid-cols-[340px_1fr]">
          <SideList
            w={w}
            title={w("scr.side.title")}
            rows={outstanding.map((r) => ({ ...r, open_label: r.open_recon ?? "—" }))}
            refKey="chalan_no"
            columns={[
              { key: "chalan_no", label: w("dr.side.chalan_no"), mono: true },
              { key: "sc_no", label: w("scd.side.sc_no"), mono: true },
              { key: "party", label: w("scd.side.party") },
              { key: "out", label: w("scr.side.out"), align: "right" },
              { key: "open_label", label: w("scc.side.open") },
            ]}
            searchPlaceholder={w("scr.side.search")}
            filterLabel={w("dc.f.customer")}
            filterOptions={customers}
            filterPlaceholder={w("dc.side.any")}
            picked={form.chalan_no}
            onPick={(r) => (form.recon_no ? warn(w("scc.chalan_fixed")) : r.open_recon ? open(r.open_recon) : pick(r.chalan_no))}
          />
          <div className="flex min-w-0 flex-col gap-4">
            <EntryHead title={w("scc.title")} hint={w("scc.hint")}>
              <Field label={w("scc.existing")} hint={w("od.ed.existing_hint")}>
                <Select value={form.recon_no} onValueChange={(v) => (v ? open(v) : (setForm(blankForm()), setSource(null)))} options={[{ value: "", label: w("scc.new_option") }, ...rows.map((r) => ({ value: r.recon_no, label: `${r.recon_no} · ${r.chalan_no} · ${w(`scc.state.${r.state}`)}` }))]} placeholder={w("scc.new_option")} aria-label={w("scc.existing")} />
              </Field>
              <LockNote text={form.recon_no && !editable ? w("scc.locked_note", { no: form.recon_no }) : null} />
              <Group legend={w("so.g.master")}>
                <Field label={w("scr.f.chalan")} required hint={w("od.ed.pick_left")}>
                  <Select value={form.chalan_no} onValueChange={(v) => pick(v)} options={outstanding.map((c) => ({ value: c.chalan_no, label: `${c.chalan_no} · ${c.party}` }))} placeholder={outstanding.length ? w("od.ed.select") : w("od.ed.no_records")} disabled={!editable || Boolean(form.recon_no)} aria-label={w("scr.f.chalan")} />
                </Field>
                <Field label={w("scc.f.recon_no")}>
                  <Input value={form.recon_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("scc.f.recon_no")} />
                </Field>
                <Field label={w("sct.f.chalan")}>
                  <Input value={source?.chalan_no ?? ""} readOnly aria-label={w("sct.f.chalan")} />
                </Field>
                <Field label={w("scd.side.party")}>
                  <Input value={source?.party ?? ""} readOnly aria-label={w("scd.side.party")} />
                </Field>
                <Field label={w("scc.f.recon_date")} required>
                  <Input type="date" value={form.recon_date} onChange={(e) => set("recon_date", e.target.value)} disabled={!editable} aria-label={w("scc.f.recon_date")} />
                </Field>
                <Field label={w("cp.f.status")}>
                  <Input value={form.state ? w(`scc.state.${form.state}`) : ""} placeholder={w("scc.f.state_ph")} readOnly aria-label={w("cp.f.status")} />
                </Field>
                <Field label={w("od.f.remarks")} className="sm:col-span-3">
                  <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!editable} aria-label={w("od.f.remarks")} />
                </Field>
              </Group>
            </EntryHead>
            <EditGrid
              w={w}
              title={w("dc.lines.title")}
              hint={w("scc.lines.hint")}
              editable={editable}
              addLabel={null}
              emptyNote={w("scc.lines.empty")}
              rows={form.lines}
              setRows={(v) => set("lines", v)}
              cols={[
                { key: "info", label: w("scc.col.line"), read: (r) => line(r.delivery_line_id)?.label ?? "—" },
                { key: "sent", label: w("cp.col.delivered"), align: "right", read: (r) => qty(line(r.delivery_line_id)?.sent) },
                { key: "received", label: w("scp.col.received"), align: "right", read: (r) => qty(line(r.delivery_line_id)?.received) },
                { key: "rejected", label: w("scr.col.rejected"), align: "right", read: (r) => qty(line(r.delivery_line_id)?.rejected) },
                { key: "returned", label: w("cp.col.returned"), align: "right", read: (r) => qty(line(r.delivery_line_id)?.returned) },
                { key: "short_qty", label: w("scc.col.short"), type: "number", align: "right" },
                { key: "balance", label: w("dc.col.balance"), align: "right", read: (r) => qty((line(r.delivery_line_id)?.out ?? 0) - (Number(r.short_qty) || 0)) },
              ]}
              summaries={[[w("scc.lines.short"), qty(shortTotal)]]}
            />
            <Foot w={w} note={w("scc.footer")} status={w("scc.action_status")} editable={editable} pending={pending} onBack={() => setTab("list")} onClear={() => (setForm(blankForm()), setSource(null))} onSave={save} saveLabel={w("scc.save")} />
          </div>
        </div>
      ) : (
        <>
          <Filters
            w={w}
            note={w("scc.f.note")}
            value={filter}
            onChange={setFilter}
            fields={[
              { name: "no", label: w("scc.f.recon_no") },
              { name: "chalan", label: w("sct.f.chalan") },
              { name: "customer", label: w("dc.f.customer"), type: "select", options: customers },
              { name: "state", label: w("od.f.state"), type: "select", options: SCC_STATES.map((s) => ({ value: s, label: w(`scc.state.${s}`) })) },
              { name: "from", label: w("od.f.from"), type: "date" },
              { name: "to", label: w("od.f.to"), type: "date" },
            ]}
          />
          <Tiles
            items={[
              [w("scc.tile.all"), shown.length],
              [w("scc.state.PENDING"), shown.filter((r) => r.state === "PENDING").length, "warning"],
              [w("scc.tile.short"), qty(shown.reduce((a, r) => a + r.short, 0)), "critical"],
            ]}
          />
          <Register
            w={w}
            rows={shown}
            refKey="recon_no"
            exportName="sc-reconciliations"
            onDone={reload}
            actions={actions}
            emptyTitle={w("scc.empty_title")}
            emptyText={w("od.empty_text")}
            columns={[
              { key: "recon_no", label: w("scc.f.recon_no"), type: "mono" },
              { key: "recon_date", label: w("od.f.date"), type: "date" },
              { key: "chalan_no", label: w("sct.f.chalan"), type: "mono" },
              { key: "party", label: w("scd.side.party") },
              { key: "delivered", label: w("cp.col.delivered"), type: "number", align: "right" },
              { key: "received", label: w("scp.col.received"), type: "number", align: "right" },
              { key: "rejected", label: w("scr.col.rejected"), type: "number", align: "right" },
              { key: "returned", label: w("cp.col.returned"), type: "number", align: "right" },
              { key: "short", label: w("scc.col.short"), type: "number", align: "right" },
              { key: "excess", label: w("scc.col.excess"), type: "number", align: "right" },
              { key: "balance", label: w("dc.col.balance"), type: "number", align: "right" },
              { key: "state_label", label: w("od.f.state"), type: "badge" },
              { key: "reconciled_by", label: w("scc.col.reconciled_by") },
            ]}
          />
        </>
      )}
    </>
  );
}

export { ScDeliveryDesk, ScReceiptDesk, ScPendingDesk, ScReturnDesk, ScReconDesk };
