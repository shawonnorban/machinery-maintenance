"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { FilePen } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useToastManager } from "@/components/ui/toast";
import { Field, Group } from "@/components/dyehouse/chem-store";
import { printDocument } from "@/lib/print-document";
import { DeskTabs, EditGrid, EntryHead, Filters, Foot, LockNote, Register, SideList, Tiles, blank, day, firstError, has, key, listTab, money, qty, today, useSs, within } from "./shared";

/*
 * Sales & Shipment → Order & Delivery → Sales Order Amendment (AnnotechERP
 * 21/02), as Brickland lays it out: Amendment List (Searching Data, the
 * tiles, the register: Edit, Print, Submit, Approve, Reject, Apply, Cancel)
 * and Sales Order Amendment Entry — a confirmed order picked on the left,
 * its sizes dropping in with today's quantity and rate beside the revised
 * ones, the revised delivery and shipment dates and the reason.
 */

const STATES = ["DRAFT", "SUBMITTED", "APPROVED", "APPLIED", "REJECTED", "CANCELLED"];

function printAmendment(w, a) {
  printDocument({
    title: w("am.print.title"),
    docNo: a.amend_no,
    subtitle: `${a.so_no} · ${w(`am.state.${a.state}`)} · ${w("am.print.version", { n: a.version })}`,
    meta: [
      [w("am.f.order"), a.so_no],
      [w("am.f.customer"), a.customer],
      [w("am.f.amend_date"), day(a.amend_date)],
      [w("am.f.prev_target"), day(a.prev_target_date)],
      [w("am.f.revised_target"), day(a.revised_target_date)],
      [w("am.f.prev_shipment"), day(a.prev_shipment_date)],
      [w("am.f.revised_shipment"), a.revised_shipment_date ? day(a.revised_shipment_date) : w("am.print.unchanged")],
      [w("am.col.requested_by"), a.requested_by],
      [w("am.col.approved_by"), a.approved],
    ],
    sections: [
      {
        columns: ["line", "prev_qty", "revised_qty", "prev_rate", "revised_rate", "amount"].map((k) => ({ label: w(`am.col.${k}`), align: k === "line" ? "left" : "right" })),
        rows: a.lines.map((l) => [l.label, qty(l.prev_qty), qty(l.revised_qty), money(l.prev_rate), money(l.revised_rate), money(l.revised_qty * l.revised_rate)]),
        foot: [[w("so.print.total"), qty(a.lines.reduce((s, l) => s + l.prev_qty, 0)), qty(a.lines.reduce((s, l) => s + l.revised_qty, 0)), "", "", money(a.lines.reduce((s, l) => s + l.revised_qty * l.revised_rate, 0))]],
      },
    ],
    notes: [`${w("am.f.reason")}: ${a.reason}`, a.remarks],
    signatures: true,
  });
}

function AmendmentDesk({ initial, options, canManage, canApprove, query, act }) {
  const w = useSs();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [orders, setOrders] = useState([]);
  const [tab, setTab] = useState(canManage && query?.so ? "entry" : "list");
  const [filter, setFilter] = useState({});
  const [pending, startTransition] = useTransition();
  const warn = (title) => toastManager.add({ title, type: "danger" });
  const blankForm = () => ({ amend_no: "", so_no: "", amend_date: today(), reason: "", remarks: "", revised_target_date: "", revised_shipment_date: "", state: "DRAFT", lines: [] });
  const [form, setForm] = useState(blankForm);
  const [source, setSource] = useState(null);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && form.state === "DRAFT";
  const reload = () =>
    startTransition(async () => {
      const [a, b] = await Promise.all([act.read("/sales-shipment/amendments"), act.read("/sales-shipment/amendments/orders")]);
      if (a?.status === "success") setRows(a.data);
      if (b?.status === "success") setOrders(b.data);
    });
  useEffect(() => {
    act.read("/sales-shipment/amendments/orders").then((r) => r?.status === "success" && setOrders(r.data));
    if (canManage && query?.so) pick(query.so);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const pick = (so, lines = null) =>
    startTransition(async () => {
      if (!so) return (setSource(null), set("lines", []));
      const res = await act.read(`/sales-shipment/amendments/source/${encodeURIComponent(so)}`);
      if (res?.status !== "success") return warn(firstError(res));
      setSource(res.data);
      setForm((f) => ({
        ...f,
        so_no: so,
        revised_target_date: f.revised_target_date || res.data.target_date,
        lines: lines ?? res.data.sizes.map((s) => ({ _k: key(), size_id: s.size_id, revised_qty: String(s.qty), revised_rate: String(s.rate) })),
      }));
    });
  const open = (no) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/amendments/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const a = res.data;
      setForm({ amend_no: a.amend_no, so_no: a.so_no, amend_date: a.amend_date, reason: a.reason, remarks: a.remarks ?? "", revised_target_date: a.revised_target_date, revised_shipment_date: a.revised_shipment_date ?? "", state: a.state,
        lines: a.lines.map((l) => ({ _k: key(), size_id: l.size_id, revised_qty: String(l.revised_qty), revised_rate: String(l.revised_rate), prev_qty: l.prev_qty, prev_rate: l.prev_rate, label: l.label })) });
      setSource(a.source ?? { so_no: a.so_no, target_date: a.prev_target_date, shipment_date: a.prev_shipment_date, sizes: a.lines.map((l) => ({ size_id: l.size_id, label: l.label, qty: l.prev_qty, rate: l.prev_rate })) });
      setTab("entry");
    });
  const save = () =>
    startTransition(async () => {
      const payload = { so_no: blank(form.so_no), amend_date: blank(form.amend_date), reason: blank(form.reason), remarks: blank(form.remarks), revised_target_date: blank(form.revised_target_date),
        revised_shipment_date: blank(form.revised_shipment_date), lines: form.lines.filter((l) => l.size_id).map((l) => ({ size_id: l.size_id, revised_qty: l.revised_qty === "" ? null : Number(l.revised_qty), revised_rate: l.revised_rate === "" ? null : Number(l.revised_rate) })) };
      const res = await act.write(form.amend_no ? "PUT" : "POST", form.amend_no ? `/sales-shipment/amendments/${encodeURIComponent(form.amend_no)}` : "/sales-shipment/amendments", payload);
      if (res?.status !== "success") return warn(firstError(res));
      setForm((f) => ({ ...f, amend_no: res.data.amend_no, state: res.data.state }));
      toastManager.add({ title: w("am.saved", { no: res.data.amend_no }), type: "success" });
      reload();
    });
  const size = (id) => source?.sizes.find((s) => s.size_id === id);
  const sizeOpts = (source?.sizes ?? []).map((s) => ({ value: s.size_id, label: s.label }));
  const revisedQty = form.lines.reduce((a, l) => a + (Number(l.revised_qty) || 0), 0);
  const revisedValue = form.lines.reduce((a, l) => a + (Number(l.revised_qty) || 0) * (Number(l.revised_rate) || 0), 0);

  const shown = useMemo(
    () =>
      rows
        .filter((r) => has(r.amend_no, filter.no) && has(r.so_no, filter.so) && (!filter.customer || r.customer_id === filter.customer) && (!filter.state || r.state === filter.state) && within(r.amend_date, filter.from, filter.to))
        .map((r) => ({ ...r, state_label: w(`am.state.${r.state}`) })),
    [rows, filter, w],
  );
  const step = (action) => (no) => act.write("POST", `/sales-shipment/amendments/${encodeURIComponent(no)}/${action}`);
  const is = (...s) => (r) => s.includes(r.state);
  const view = (r) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/amendments/${encodeURIComponent(r.amend_no)}`);
      if (res?.status === "success") printAmendment(w, res.data);
      else warn(firstError(res));
    });
  const actions = [
    ...(canManage ? [{ key: "edit", label: w("od.reg.edit"), when: is("DRAFT"), open: (r) => open(r.amend_no) }] : []),
    { key: "print", label: w("od.reg.print"), open: view },
    ...(canManage ? [{ key: "submit", label: w("od.reg.submit"), when: is("DRAFT"), confirm: w("am.confirm.submit"), done: w("am.done.submit"), run: step("submit") }] : []),
    ...(canApprove
      ? [
          { key: "approve", label: w("od.reg.approve"), when: is("SUBMITTED"), confirm: w("am.confirm.approve"), done: w("am.done.approve"), run: step("approve") },
          { key: "reject", label: w("od.reg.reject"), when: is("SUBMITTED"), confirm: w("am.confirm.reject"), done: w("am.done.reject"), run: step("reject"), destructive: true },
          { key: "apply", label: w("am.reg.apply"), when: is("APPROVED"), confirm: w("am.confirm.apply"), done: w("am.done.apply"), run: step("apply") },
        ]
      : []),
    ...(canManage ? [{ key: "cancel", label: w("od.reg.cancel"), when: is("DRAFT", "SUBMITTED", "APPROVED"), confirm: w("am.confirm.cancel"), done: w("am.done.cancel"), run: step("cancel"), destructive: true }] : []),
  ];
  const customers = options.customers.map(({ value, label }) => ({ value, label }));

  return (
    <>
      <DeskTabs tab={tab} setTab={setTab} items={[listTab(w("am.list_tab"), rows.length), ...(canManage ? [["entry", w("am.entry_tab"), FilePen]] : [])]} />
      {tab === "entry" && canManage ? (
        <div className="grid gap-4 xl:grid-cols-[340px_1fr]">
          <SideList
            w={w}
            title={w("am.side.title")}
            rows={orders}
            refKey="so_no"
            columns={[
              { key: "so_no", label: w("sc.side.so_no"), mono: true },
              { key: "customer", label: w("sc.side.customer") },
              { key: "qty", label: w("sc.side.qty"), align: "right" },
              { key: "version", label: w("am.side.rev"), align: "right" },
            ]}
            searchPlaceholder={w("sc.side.search")}
            filterLabel={w("sc.side.customer")}
            filterOptions={customers}
            filterPlaceholder={w("sc.side.any")}
            picked={form.so_no}
            onPick={(r) => (form.amend_no ? warn(w("am.order_fixed")) : pick(r.so_no))}
          />
          <div className="flex min-w-0 flex-col gap-4">
            <EntryHead title={w("am.title")} hint={w("am.hint")}>
              <Field label={w("am.existing")} hint={w("od.ed.existing_hint")}>
                <Select value={form.amend_no} onValueChange={(v) => (v ? open(v) : (setForm(blankForm()), setSource(null)))} options={[{ value: "", label: w("am.new_option") }, ...rows.map((r) => ({ value: r.amend_no, label: `${r.amend_no} · ${r.so_no} · ${w(`am.state.${r.state}`)}` }))]} placeholder={w("am.new_option")} aria-label={w("am.existing")} />
              </Field>
              <LockNote text={form.amend_no && !editable ? w("am.locked", { no: form.amend_no, status: w(`am.state.${form.state}`) }) : null} />
              <Group legend={w("am.g.amendment")}>
                <Field label={w("am.f.order")} required hint={w("od.ed.pick_left")}>
                  <Select value={form.so_no} onValueChange={(v) => pick(v)} options={orders.map((o) => ({ value: o.so_no, label: `${o.so_no} · ${o.customer}` }))} placeholder={orders.length ? w("od.ed.select") : w("od.ed.no_records")} disabled={!editable || Boolean(form.amend_no)} aria-label={w("am.f.order")} />
                </Field>
                <Field label={w("am.f.amend_no")}>
                  <Input value={form.amend_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("am.f.amend_no")} />
                </Field>
                <Field label={w("am.f.amend_date")} required>
                  <Input type="date" value={form.amend_date} onChange={(e) => set("amend_date", e.target.value)} disabled={!editable} aria-label={w("am.f.amend_date")} />
                </Field>
                <Field label={w("am.f.reason")} required className="sm:col-span-2">
                  <Textarea value={form.reason} onChange={(e) => set("reason", e.target.value)} rows={2} placeholder={w("am.f.reason_ph")} disabled={!editable} aria-label={w("am.f.reason")} />
                </Field>
                <Field label={w("od.f.remarks")}>
                  <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!editable} aria-label={w("od.f.remarks")} />
                </Field>
              </Group>
              <Group legend={w("am.g.dates")}>
                <Field label={w("am.f.prev_target")}>
                  <Input value={source ? day(source.target_date) : ""} readOnly aria-label={w("am.f.prev_target")} />
                </Field>
                <Field label={w("am.f.revised_target")} required>
                  <Input type="date" value={form.revised_target_date} onChange={(e) => set("revised_target_date", e.target.value)} disabled={!editable} aria-label={w("am.f.revised_target")} />
                </Field>
                <Field label={w("am.f.prev_shipment")}>
                  <Input value={source ? day(source.shipment_date) : ""} readOnly aria-label={w("am.f.prev_shipment")} />
                </Field>
                <Field label={w("am.f.revised_shipment")} hint={w("am.f.revised_shipment_hint")}>
                  <Input type="date" value={form.revised_shipment_date} onChange={(e) => set("revised_shipment_date", e.target.value)} disabled={!editable} aria-label={w("am.f.revised_shipment")} />
                </Field>
              </Group>
            </EntryHead>
            <EditGrid
              w={w}
              title={w("am.lines.title")}
              hint={w("am.lines.hint")}
              editable={editable}
              addLabel={sizeOpts.length ? w("am.lines.add") : null}
              onAdd={() => set("lines", [...form.lines, { _k: key(), size_id: "", revised_qty: "", revised_rate: "" }])}
              emptyNote={w("am.lines.empty")}
              rows={form.lines}
              setRows={(v) => set("lines", v)}
              cols={[
                { key: "size_id", label: w("am.col.size"), type: "select", options: sizeOpts, width: 120, onPick: (v) => ({ revised_qty: String(size(v)?.qty ?? ""), revised_rate: String(size(v)?.rate ?? "") }) },
                { key: "line", label: w("am.col.line"), read: (r) => size(r.size_id)?.label ?? r.label ?? "—" },
                { key: "prev_qty", label: w("am.col.prev_qty"), align: "right", read: (r) => qty(r.prev_qty ?? size(r.size_id)?.qty) },
                { key: "revised_qty", label: w("am.col.revised_qty"), type: "number", align: "right" },
                { key: "prev_rate", label: w("am.col.prev_rate"), align: "right", read: (r) => money(r.prev_rate ?? size(r.size_id)?.rate) },
                { key: "revised_rate", label: w("am.col.revised_rate"), type: "number", align: "right" },
                { key: "amount", label: w("am.col.amount"), align: "right", read: (r) => money((Number(r.revised_qty) || 0) * (Number(r.revised_rate) || 0)) },
              ]}
              summaries={[
                [w("am.col.revised_qty"), qty(revisedQty)],
                [w("am.lines.value"), money(revisedValue)],
              ]}
            />
            <Foot w={w} note={w("am.footer")} status={w("am.action_status")} editable={editable} pending={pending} onBack={() => setTab("list")} onClear={() => (setForm(blankForm()), setSource(null))} onSave={save} saveLabel={w("am.save")} />
          </div>
        </div>
      ) : (
        <>
          <Filters
            w={w}
            note={w("am.f.note")}
            value={filter}
            onChange={setFilter}
            fields={[
              { name: "no", label: w("am.f.amend_no"), placeholder: `${w("am.f.amend_no")}…` },
              { name: "so", label: w("so.f.so_no"), placeholder: "SO-…" },
              { name: "customer", label: w("am.f.customer"), type: "select", options: customers },
              { name: "state", label: w("od.f.state"), type: "select", options: STATES.map((s) => ({ value: s, label: w(`am.state.${s}`) })) },
              { name: "from", label: w("od.f.from"), type: "date" },
              { name: "to", label: w("od.f.to"), type: "date" },
            ]}
          />
          <Tiles
            items={[
              [w("am.tile.all"), shown.length],
              [w("am.tile.awaiting"), shown.filter((r) => r.state === "SUBMITTED").length, "warning"],
              [w("am.tile.approved"), shown.filter((r) => r.state === "APPROVED").length, "accent"],
              [w("am.tile.applied"), shown.filter((r) => r.state === "APPLIED").length, "good"],
            ]}
          />
          <Register
            w={w}
            rows={shown}
            refKey="amend_no"
            exportName="sales-order-amendments"
            onDone={reload}
            actions={actions}
            emptyTitle={w("am.empty_title")}
            emptyText={w("od.empty_text")}
            columns={[
              { key: "amend_no", label: w("am.f.amend_no"), type: "mono" },
              { key: "amend_date", label: w("od.f.date"), type: "date" },
              { key: "so_no", label: w("so.f.so_no"), type: "mono" },
              { key: "customer", label: w("am.f.customer") },
              { key: "version", label: w("am.col.version"), type: "number", align: "right" },
              { key: "prev_qty", label: w("am.col.previous"), type: "number", align: "right", group: "qty", groupLabel: w("am.grp.qty") },
              { key: "revised_qty", label: w("am.col.revised"), type: "number", align: "right", group: "qty", groupLabel: w("am.grp.qty") },
              { key: "prev_value", label: w("am.col.previous"), type: "number", align: "right", group: "value", groupLabel: w("am.grp.value") },
              { key: "revised_value", label: w("am.col.revised"), type: "number", align: "right", group: "value", groupLabel: w("am.grp.value") },
              { key: "prev_shipment", label: w("am.col.previous"), type: "date", group: "ship", groupLabel: w("am.grp.shipment") },
              { key: "revised_shipment", label: w("am.col.revised"), type: "date", group: "ship", groupLabel: w("am.grp.shipment") },
              { key: "reason", label: w("am.f.reason") },
              { key: "requested_by", label: w("am.col.requested_by") },
              { key: "approved_by", label: w("am.col.approved_by") },
              { key: "state_label", label: w("od.f.state"), type: "badge" },
            ]}
          />
        </>
      )}
    </>
  );
}

export { AmendmentDesk };
