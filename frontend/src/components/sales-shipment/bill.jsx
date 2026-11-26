"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { ReceiptText } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useToastManager } from "@/components/ui/toast";
import { Field, Group } from "@/components/dyehouse/chem-store";
import { printDocument } from "@/lib/print-document";
import { DeskTabs, EditGrid, EntryHead, Filters, Foot, LockNote, Register, SideList, Tiles, blank, day, firstError, has, key, listTab, money, qty, today, useSs, within } from "./shared";

/*
 * Sales & Shipment → Order & Delivery → Delivery Bill Verification
 * (AnnotechERP 21/02), as Brickland lays it out: Check Bill List (Searching
 * Data, the tiles, the register: Edit, Print, Verify, Approve, Mark Paid,
 * Reject, Delete) and Check Bill Entry — delivered challans picked on the
 * left drop their lines in with the delivered and already-billed quantity
 * beside the box you bill; Bill Info, the remarks template, Action Status
 * (68-Save As Draft / 69-Submit For Approval).
 */

const STATES = ["DRAFT", "WAITING_FOR_APPROVAL", "VERIFIED", "APPROVED", "PAID", "REJECTED"];
const n = (v) => (v === "" || v === null || v === undefined ? null : Number(v));

function printBill(w, b) {
  printDocument({
    title: w("cb.print.title"),
    docNo: b.bill_no,
    subtitle: w(`cb.state.${b.state}`),
    orientation: "landscape",
    meta: [
      [w("cb.f.customer"), b.customer],
      [w("so.f.delivery_address"), b.address],
      [w("cb.f.bill_date"), day(b.bill_date)],
      [w("so.f.currency"), `${b.currency} @ ${b.loc_exchange_rate}`],
      [w("cb.col.verified_by"), b.verified],
      [w("cb.print.approved"), b.approved],
    ],
    sections: [
      {
        columns: ["challan", "item", "delv", "unit", "bill_qty", "rate", "amount", "amount_bdt"].map((k) => ({ label: w(`cb.col.${k}`), align: ["delv", "bill_qty", "rate", "amount", "amount_bdt"].includes(k) ? "right" : "left" })),
        rows: b.lines.map((l) => [l.challan_info, l.label, qty(l.delivered), l.unit, qty(l.qty), money(l.rate), money(l.qty * l.rate), money(l.qty * l.rate * l.exchange_rate)]),
        foot: [
          [w("cb.lines.gross"), "", "", "", "", "", money(b.gross), money(b.gross_bdt)],
          [w("cb.f.deduction"), "", "", "", "", "", money(b.deduction), money(b.deduction * b.loc_exchange_rate)],
          [w("cb.lines.net"), "", "", "", "", "", money(b.net), money(b.net_bdt)],
        ],
      },
    ],
    notes: [b.remarks],
    signatures: true,
  });
}

function BillDesk({ initial, options, canManage, canApprove, act }) {
  const w = useSs();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [challans, setChallans] = useState([]);
  const [tab, setTab] = useState("list");
  const [filter, setFilter] = useState({});
  const [pending, startTransition] = useTransition();
  const warn = (title) => toastManager.add({ title, type: "danger" });
  const template = w("cb.f.remarks_default");
  const blankForm = () => ({ bill_no: "", customer_id: "", currency_id: "", loc_exchange_rate: "1", bill_date: today(), deduction: "0", remarks: template, action_status: "69", state: "DRAFT", lines: [] });
  const [form, setForm] = useState(blankForm);
  const [src, setSrc] = useState({});
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && ["DRAFT", "REJECTED"].includes(form.state);
  const reload = () =>
    startTransition(async () => {
      const [a, b] = await Promise.all([act.read("/sales-shipment/bills"), act.read("/sales-shipment/bills/challans")]);
      if (a?.status === "success") setRows(a.data);
      if (b?.status === "success") setChallans(b.data);
    });
  useEffect(() => {
    act.read("/sales-shipment/bills/challans").then((r) => r?.status === "success" && setChallans(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const pick = (c) =>
    startTransition(async () => {
      if (form.customer_id && form.customer_id !== c.customer_id) return warn(w("cb.other_customer", { no: c.chalan_no }));
      const res = await act.read(`/sales-shipment/bills/source/${encodeURIComponent(c.chalan_no)}${form.bill_no ? `?except=${encodeURIComponent(form.bill_no)}` : ""}`);
      if (res?.status !== "success") return warn(firstError(res));
      setSrc((s) => ({ ...s, ...Object.fromEntries(res.data.map((l) => [l.challan_line_id, l])) }));
      setForm((f) => {
        const have = new Set(f.lines.map((l) => l.challan_line_id));
        const add = res.data.filter((l) => l.billable > 0 && !have.has(l.challan_line_id)).map((l) => ({ _k: key(), challan_line_id: l.challan_line_id, qty: String(l.billable), rate: String(l.rate), exchange_rate: f.loc_exchange_rate }));
        return { ...f, customer_id: c.customer_id, currency_id: f.currency_id || c.currency_id, lines: [...f.lines, ...add] };
      });
    });
  const open = (no) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/bills/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const b = res.data;
      setSrc(Object.fromEntries(b.lines.map((l) => [l.challan_line_id, l])));
      setForm({ bill_no: b.bill_no, customer_id: b.customer_id, currency_id: b.currency_id, loc_exchange_rate: String(b.loc_exchange_rate), bill_date: b.bill_date, deduction: String(b.deduction), remarks: b.remarks ?? "",
        action_status: "69", state: b.state, lines: b.lines.map((l) => ({ _k: key(), challan_line_id: l.challan_line_id, qty: String(l.qty), rate: String(l.rate), exchange_rate: String(l.exchange_rate) })) });
      setTab("entry");
    });
  const save = () =>
    startTransition(async () => {
      const payload = { customer_id: blank(form.customer_id), currency_id: blank(form.currency_id), loc_exchange_rate: n(form.loc_exchange_rate), bill_date: blank(form.bill_date), deduction: n(form.deduction) ?? 0,
        remarks: blank(form.remarks), action_status: form.action_status, lines: form.lines.filter((l) => l.challan_line_id).map((l) => ({ challan_line_id: l.challan_line_id, qty: n(l.qty), rate: n(l.rate), exchange_rate: n(l.exchange_rate) })) };
      const res = await act.write(form.bill_no ? "PUT" : "POST", form.bill_no ? `/sales-shipment/bills/${encodeURIComponent(form.bill_no)}` : "/sales-shipment/bills", payload);
      if (res?.status !== "success") return warn(firstError(res));
      setForm((f) => ({ ...f, bill_no: res.data.bill_no, state: res.data.state, remarks: res.data.remarks ?? "" }));
      toastManager.add({ title: w(res.data.state === "DRAFT" ? "cb.saved" : "cb.submitted", { no: res.data.bill_no }), type: "success" });
      reload();
    });
  const lineOpts = Object.values(src).map((l) => ({ value: l.challan_line_id, label: `${l.chalan_no} · ${l.label}` }));
  const amt = (l) => (Number(l.qty) || 0) * (Number(l.rate) || 0);
  const gross = form.lines.reduce((a, l) => a + amt(l), 0);
  const grossBdt = form.lines.reduce((a, l) => a + amt(l) * (Number(l.exchange_rate) || 0), 0);

  const shown = useMemo(
    () =>
      rows
        .filter((r) => has(r.bill_no, filter.no) && (!filter.customer || r.customer_id === filter.customer) && (!filter.state || r.state === filter.state) && within(r.bill_date, filter.from, filter.to))
        .filter((r) => !filter.finalized || (filter.finalized === "yes") === r.finalized)
        .map((r) => ({ ...r, state_label: w(`cb.state.${r.state}`) })),
    [rows, filter, w],
  );
  const step = (action) => (no) => act.write("POST", `/sales-shipment/bills/${encodeURIComponent(no)}/${action}`);
  const is = (...s) => (r) => s.includes(r.state);
  const view = (r) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/bills/${encodeURIComponent(r.bill_no)}`);
      if (res?.status === "success") printBill(w, res.data);
      else warn(firstError(res));
    });
  const actions = [
    ...(canManage ? [{ key: "edit", label: w("od.reg.edit"), when: is("DRAFT", "REJECTED"), open: (r) => open(r.bill_no) }] : []),
    { key: "print", label: w("od.reg.print"), open: view },
    ...(canApprove
      ? [
          { key: "verify", label: w("cb.reg.verify"), when: is("WAITING_FOR_APPROVAL"), confirm: w("cb.confirm.verify"), done: w("cb.done.verify"), run: step("verify") },
          { key: "approve", label: w("od.reg.approve"), when: is("VERIFIED"), confirm: w("cb.confirm.approve"), done: w("cb.done.approve"), run: step("approve") },
          { key: "pay", label: w("cb.reg.pay"), when: is("APPROVED"), confirm: w("cb.confirm.pay"), done: w("cb.done.pay"), run: step("pay") },
          { key: "reject", label: w("od.reg.reject"), when: is("WAITING_FOR_APPROVAL", "VERIFIED"), confirm: w("cb.confirm.reject"), done: w("cb.done.reject"), run: step("reject"), destructive: true },
        ]
      : []),
    ...(canManage ? [{ key: "delete", label: w("od.reg.delete"), when: is("DRAFT", "REJECTED"), confirm: w("od.confirm.delete"), done: w("od.done.delete"), run: (no) => act.write("DELETE", `/sales-shipment/bills/${encodeURIComponent(no)}`), destructive: true }] : []),
  ];
  const customers = options.customers.map(({ value: v, label }) => ({ value: v, label }));
  const company = [{ value: "c", label: options.company }];

  return (
    <>
      <DeskTabs tab={tab} setTab={setTab} items={[listTab(w("cb.list_tab"), rows.length), ...(canManage ? [["entry", w("cb.entry_tab"), ReceiptText]] : [])]} />
      {tab === "entry" && canManage ? (
        <div className="grid gap-4 xl:grid-cols-[340px_1fr]">
          <SideList
            w={w}
            title={w("cb.side.title")}
            rows={challans.filter((c) => c.unbilled > 0 || form.lines.some((l) => src[l.challan_line_id]?.chalan_no === c.chalan_no))}
            refKey="chalan_no"
            columns={[
              { key: "chalan_no", label: w("dr.side.chalan_no"), mono: true },
              { key: "so_no", label: w("sc.side.so_no"), mono: true },
              { key: "customer", label: w("sc.side.customer") },
              { key: "unbilled", label: w("cb.side.unbilled"), align: "right" },
            ]}
            searchPlaceholder={w("dr.side.search")}
            filterLabel={w("sc.side.customer")}
            filterOptions={customers}
            filterPlaceholder={w("dr.side.any")}
            onPick={(c) => (editable ? pick(c) : null)}
          />
          <div className="flex min-w-0 flex-col gap-4">
            <EntryHead title={w("cb.title")} hint={w("cb.hint")}>
              <Field label={w("cb.existing")} hint={w("od.ed.existing_hint")}>
                <Select value={form.bill_no} onValueChange={(v) => (v ? open(v) : (setForm(blankForm()), setSrc({})))} options={[{ value: "", label: w("cb.new_option") }, ...rows.map((r) => ({ value: r.bill_no, label: `${r.bill_no} · ${r.customer} · ${w(`cb.state.${r.state}`)}` }))]} placeholder={w("cb.new_option")} aria-label={w("cb.existing")} />
              </Field>
              <LockNote text={form.bill_no && !editable ? w("cb.locked", { no: form.bill_no, status: w(`cb.state.${form.state}`) }) : null} />
              <Group legend={w("cb.g.info")}>
                <Field label={w("od.f.company")} required>
                  <Input value={options.company} readOnly aria-label={w("od.f.company")} />
                </Field>
                <Field label={w("cb.f.customer")} required>
                  <Select value={form.customer_id} onValueChange={(v) => (form.lines.length ? warn(w("cb.customer_fixed")) : set("customer_id", v ?? ""))} options={customers} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("cb.f.customer")} />
                </Field>
                <Field label={w("so.f.currency")} required>
                  <Select value={form.currency_id} onValueChange={(v) => set("currency_id", v ?? "")} options={options.currencies} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("so.f.currency")} />
                </Field>
                <Field label={w("cb.f.loc_rate")} required hint={w("cb.f.loc_rate_hint")}>
                  <Input type="number" min="0" step="any" value={form.loc_exchange_rate} onChange={(e) => setForm((f) => ({ ...f, loc_exchange_rate: e.target.value, lines: f.lines.map((l) => (l.exchange_rate === f.loc_exchange_rate ? { ...l, exchange_rate: e.target.value } : l)) }))} disabled={!editable} aria-label={w("cb.f.loc_rate")} />
                </Field>
                <Field label={w("cb.f.bill_no")}>
                  <Input value={form.bill_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("cb.f.bill_no")} />
                </Field>
                <Field label={w("cb.f.bill_date")} required>
                  <Input type="date" value={form.bill_date} onChange={(e) => set("bill_date", e.target.value)} disabled={!editable} aria-label={w("cb.f.bill_date")} />
                </Field>
                <Field label={w("cb.f.deduction")} hint={w("cb.f.deduction_hint")}>
                  <Input type="number" min="0" step="any" value={form.deduction} onChange={(e) => set("deduction", e.target.value)} disabled={!editable} aria-label={w("cb.f.deduction")} />
                </Field>
              </Group>
              <Group legend={w("cb.g.details")}>
                <Field label={w("od.f.remarks")} hint={w("cb.f.remarks_hint")} className="sm:col-span-2">
                  <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={3} disabled={!editable} aria-label={w("od.f.remarks")} />
                </Field>
                <Field label={w("cb.f.action_status")} required hint={w("cb.f.action_status_hint")}>
                  <Select value={form.action_status} onValueChange={(v) => set("action_status", v ?? "69")} options={[{ value: "68", label: w("cb.action.68") }, { value: "69", label: w("cb.action.69") }]} disabled={!editable} aria-label={w("cb.f.action_status")} />
                </Field>
              </Group>
            </EntryHead>
            <EditGrid
              w={w}
              title={w("cb.lines.title")}
              hint={w("cb.lines.hint")}
              editable={editable}
              addLabel={lineOpts.length ? w("dc.lines.add") : null}
              onAdd={() => set("lines", [...form.lines, { _k: key(), challan_line_id: "", qty: "", rate: "", exchange_rate: form.loc_exchange_rate }])}
              emptyNote={w("cb.lines.empty")}
              rows={form.lines}
              setRows={(v) => set("lines", v)}
              cols={[
                { key: "challan_line_id", label: w("cb.col.line"), type: "select", options: lineOpts, width: 200, onPick: (v) => ({ rate: String(src[v]?.rate ?? ""), qty: String(src[v]?.billable ?? "") }) },
                { key: "challan", label: w("cb.col.challan"), read: (r) => src[r.challan_line_id]?.challan_info ?? "—" },
                { key: "item", label: w("cb.col.item"), read: (r) => src[r.challan_line_id]?.label ?? "—" },
                { key: "delv", label: w("cb.col.delv"), align: "right", read: (r) => qty(src[r.challan_line_id]?.delivered) },
                { key: "unit", label: w("cb.col.unit"), align: "center", read: (r) => src[r.challan_line_id]?.unit ?? "—" },
                { key: "prev", label: w("cb.col.prev"), align: "right", read: (r) => qty(src[r.challan_line_id]?.prev) },
                { key: "qty", label: w("cb.col.bill_qty"), type: "number", align: "right" },
                { key: "rate", label: w("cb.col.rate"), type: "number", align: "right" },
                { key: "amount", label: w("cb.col.amount"), align: "right", read: (r) => money(amt(r)) },
                { key: "amount_bdt", label: w("cb.col.amount_bdt"), align: "right", read: (r) => money(amt(r) * (Number(r.exchange_rate) || 0)) },
                { key: "exchange_rate", label: w("cb.col.bdt_rate"), type: "number", align: "right" },
              ]}
              summaries={[
                [w("cb.lines.gross"), money(gross)],
                [w("cb.lines.gross_bdt"), money(grossBdt)],
                [w("cb.lines.net"), money(gross - (Number(form.deduction) || 0))],
              ]}
            />
            <Foot w={w} note={w("cb.footer")} editable={editable} pending={pending} onBack={() => setTab("list")} onClear={() => (setForm(blankForm()), setSrc({}))} onSave={save} saveLabel={w("cb.save")} />
          </div>
        </div>
      ) : (
        <>
          <Filters
            w={w}
            note={w("cb.f.note")}
            value={filter}
            onChange={setFilter}
            fields={[
              { name: "no", label: w("cb.col.bill_no") },
              { name: "company", label: w("od.f.company"), type: "select", options: company },
              { name: "customer", label: w("cb.f.customer"), type: "select", options: customers },
              { name: "state", label: w("cb.f.status"), type: "select", options: STATES.map((s) => ({ value: s, label: w(`cb.state.${s}`) })) },
              { name: "finalized", label: w("cb.f.finalized"), type: "select", options: [{ value: "yes", label: w("od.yes") }, { value: "no", label: w("od.no") }] },
              { name: "from", label: w("od.f.from"), type: "date" },
              { name: "to", label: w("od.f.to"), type: "date" },
            ]}
          />
          <Tiles
            items={[
              [w("cb.tile.all"), shown.length],
              [w("cb.tile.verify"), shown.filter((r) => r.state === "WAITING_FOR_APPROVAL").length, "warning"],
              [w("cb.tile.approve"), shown.filter((r) => r.state === "VERIFIED").length, "info"],
              [w("cb.tile.value"), money(shown.filter((r) => r.state !== "REJECTED").reduce((a, r) => a + r.net_amount_bdt, 0))],
            ]}
          />
          <Register
            w={w}
            rows={shown}
            refKey="bill_no"
            exportName="check-bills"
            onDone={reload}
            actions={actions}
            emptyTitle={w("cb.empty_title")}
            emptyText={w("od.empty_text")}
            columns={[
              { key: "bill_no", label: w("cb.col.bill_no"), type: "mono" },
              { key: "customer", label: w("cb.f.customer") },
              { key: "company", label: w("od.f.company") },
              { key: "bill_date", label: w("cb.f.bill_date"), type: "date" },
              { key: "target_date", label: w("so.col.target"), type: "date" },
              { key: "finalized", label: w("cb.col.finalized"), type: "yesno", align: "center" },
              { key: "gross_amount", label: w("cb.col.gross"), type: "number", align: "right" },
              { key: "deduction", label: w("cb.f.deduction"), type: "number", align: "right" },
              { key: "net_amount", label: w("cb.col.net"), type: "number", align: "right" },
              { key: "net_amount_bdt", label: w("cb.col.net_bdt"), type: "number", align: "right" },
              { key: "verified_by", label: w("cb.col.verified_by") },
              { key: "state_label", label: w("cb.f.status"), type: "badge" },
            ]}
          />
        </>
      )}
    </>
  );
}

export { BillDesk };
