"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ArrowRight, FilePlus2, Share2 } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { buttonVariants } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { Field, Group } from "@/components/dyehouse/chem-store";
import { printDocument } from "@/lib/print-document";
import { cn } from "@/lib/utils";
import { DeskTabs, EditGrid, EntryHead, Filters, Foot, LockNote, OrderFlow, Register, SideList, Tiles, blank, day, firstError, has, key, listTab, money, qty, today, useSs, within } from "./shared";

/*
 * Sales & Shipment → Order & Delivery → Sales Order Entry (AnnotechERP
 * 21/02), as Brickland lays it out under the "Order to Shipment · Step 1 of
 * 12" strip: three tabs switching in place — Sales Order List (Searching
 * Data, the tiles, the register: View, Print, S/C Prog, Amend), Sales Order
 * Entry (Master Data, Terms, Shipment, Details Data, then the item lines, the
 * GMT Size | Product grid under them and the Other Particular grid; saved
 * together as a draft) and S/C Programme (an order picked on the left, its
 * lines going out to a sub-contractor).
 */

const num = (v) => (v === "" || v === null || v === undefined ? null : Number(v));

/** The printed sales order (View / Print). */
function printOrder(w, o, autoPrint, title = null) {
  const sl = (n) => o.lines.find((l) => l.sl_no === n);
  printDocument({
    title: title ?? w("so.print.title"),
    docNo: o.so_no,
    subtitle: `${w(`od.approval.${o.approval_status}`)}${o.version ? ` · ${w("so.print.rev", { n: o.version })}` : ""}`,
    autoPrint,
    orientation: "landscape",
    meta: [
      [w("so.f.customer"), o.customer],
      [w("so.f.receive_date"), day(o.receive_date)],
      [w("so.f.po_wo_no"), o.po_wo_no],
      [w("so.f.bill_to"), o.bill_to],
      [w("so.f.delivery_to"), o.delivery_to],
      [w("so.f.delivery_target_date"), day(o.delivery_target_date)],
      [w("so.f.program_by"), o.program_by],
      [w("so.f.program_to"), o.program_to],
      [w("so.f.shipment_date"), day(o.shipment_date)],
      [w("so.f.payment_method"), w(`od.pay.${o.payment_method}`)],
      [w("so.f.currency"), `${o.currency} @ ${o.conv_rate}`],
      [w("so.f.mode_of_shipment"), o.mode_of_shipment],
      [w("so.f.payment_term"), o.payment_term],
      [w("so.f.incoterm"), o.incoterm],
      [w("so.f.destination_country"), o.country],
      [w("so.f.delivery_address"), o.delivery_address],
    ],
    sections: [
      {
        title: w("so.lines.title"),
        columns: ["sl", "buyer", "order", "style", "section", "item", "gmt_color", "rate", "unit"].map((k) => ({ label: w(`so.col.${k}`), align: k === "rate" ? "right" : "left" })),
        rows: o.lines.map((l) => [l.sl_no, l.buyer, l.order_ref, l.style_ref, l.section, l.item, l.gmt_color, money(l.rate), l.uom]),
      },
      {
        title: w("so.sizes.title"),
        columns: ["sl", "item", "size", "p_size", "qty", "rate", "amount"].map((k) => ({ label: w(`so.col.${k}`), align: ["qty", "rate", "amount", "p_size"].includes(k) ? "right" : "left" })),
        rows: o.sizes.map((s) => [s.sl_no, `${sl(s.sl_no)?.item ?? "—"} · ${sl(s.sl_no)?.gmt_color ?? ""}`, s.size, qty(s.pcs_per_size), qty(s.qty), money(s.rate), money(s.qty * s.rate)]),
        foot: [[w("so.print.total"), "", "", "", qty(o.sizes.reduce((a, s) => a + s.qty, 0)), "", money(o.sizes.reduce((a, s) => a + s.qty * s.rate, 0))]],
      },
    ],
    notes: [o.particulars.length ? `${w("so.part.title")}: ${o.particulars.map((p) => `#${p.sl_no} ${p.particular} — ${p.value}`).join("; ")}` : null, o.others_term, o.remarks],
    signatures: true,
  });
}

function SalesOrderDesk({ initial, programmes: initialProgrammes, options, canManage, query, act }) {
  const w = useSs();
  const router = useRouter();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [programmes, setProgrammes] = useState(initialProgrammes);
  const [tab, setTab] = useState(canManage && query?.tab === "sc-programme" ? "sc" : canManage && query?.so ? "entry" : "list");
  const [filter, setFilter] = useState({});
  const [pending, startTransition] = useTransition();
  const warn = (title) => toastManager.add({ title, type: "danger" });
  const opt = (list, prefix) => list.map((v) => ({ value: v, label: prefix ? w(`${prefix}.${v}`) : v }));
  const reload = () =>
    startTransition(async () => {
      const [a, b] = await Promise.all([act.read("/sales-shipment/orders"), act.read("/sales-shipment/sc-programmes")]);
      if (a?.status === "success") setRows(a.data);
      if (b?.status === "success") setProgrammes(b.data);
    });

  /* ── Entry ── */
  const blankOrder = () => ({
    so_no: "", search_for: "SALES_ORDER", is_inter_company: false, is_embroidery: false, so_type: "GENERAL", customer_id: "", receive_date: today(), program_by: "", program_to: "",
    bill_to_id: "", delivery_target_date: "", delivery_to_id: "", same_as_bill_to: false, delivery_address: "", payment_method: "CASH", currency_id: "", conv_rate: "1", payment_term: "",
    mode_of_shipment: "By Van", po_wo_no: "", others_term: "", pr_po_ref: "", remarks: "", additional_qty_pct: "", season: "", department: "", incoterm: "", destination_country_id: "",
    destination_port: "", shipment_date: "", item_id: "", is_poly_item: false, approval_status: "DRAFT", state: "OPEN", lines: [], sizes: [], particulars: [],
  });
  const toForm = (o) => ({
    ...blankOrder(),
    ...Object.fromEntries(Object.entries(o).map(([k, v]) => [k, v ?? ""])),
    conv_rate: String(o.conv_rate),
    additional_qty_pct: o.additional_qty_pct ? String(o.additional_qty_pct) : "",
    lines: o.lines.map((l) => ({ ...Object.fromEntries(Object.entries(l).map(([k, v]) => [k, v ?? ""])), _k: key() })),
    sizes: o.sizes.map((s) => ({ ...s, _k: key() })),
    particulars: o.particulars.map((p) => ({ ...p, _k: key() })),
  });
  const [form, setForm] = useState(blankOrder);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && form.approval_status === "DRAFT" && form.state === "OPEN";
  const leadTime = form.receive_date && form.delivery_target_date ? Math.round((new Date(form.delivery_target_date) - new Date(form.receive_date)) / 86400000) : 0;
  const open = (no) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/orders/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      setForm(toForm(res.data));
      setTab("entry");
    });
  const addressOf = (id) => options.customers.find((c) => c.value === id)?.address ?? "";
  const save = () =>
    startTransition(async () => {
      const head = Object.fromEntries(
        ["search_for", "so_type", "customer_id", "receive_date", "program_by", "program_to", "bill_to_id", "delivery_target_date", "delivery_to_id", "delivery_address", "payment_method", "currency_id",
          "payment_term", "mode_of_shipment", "po_wo_no", "others_term", "pr_po_ref", "remarks", "season", "department", "incoterm", "destination_country_id", "destination_port", "shipment_date", "item_id"].map((k) => [k, blank(form[k])]),
      );
      const payload = {
        ...head,
        is_inter_company: form.is_inter_company, is_embroidery: form.is_embroidery, is_poly_item: form.is_poly_item, same_as_bill_to: form.same_as_bill_to,
        conv_rate: num(form.conv_rate), additional_qty_pct: num(form.additional_qty_pct),
        lines: form.lines.map((l) => ({ sl_no: num(l.sl_no), buyer: blank(l.buyer), order_ref: blank(l.order_ref), style_ref: blank(l.style_ref), wo_ref: blank(l.wo_ref), section_id: blank(l.section_id),
          item_id: blank(l.item_id), gmt_color: blank(l.gmt_color), product_color: blank(l.product_color), rate: num(l.rate) ?? 0, uom: blank(l.uom), specification: blank(l.specification),
          code: blank(l.code), gross_weight: num(l.gross_weight), net_weight: num(l.net_weight) })),
        sizes: form.sizes.map((s) => ({ sl_no: num(s.sl_no), size: blank(s.size), pcs_per_size: num(s.pcs_per_size), qty: num(s.qty) })),
        particulars: form.particulars.map((p) => ({ sl_no: num(p.sl_no), particular_id: blank(p.particular_id), value: blank(p.value) })),
      };
      const res = await act.write(form.so_no ? "PUT" : "POST", form.so_no ? `/sales-shipment/orders/${encodeURIComponent(form.so_no)}` : "/sales-shipment/orders", payload);
      if (res?.status !== "success") return warn(firstError(res));
      setForm(toForm(res.data));
      toastManager.add({ title: w("so.saved", { no: res.data.so_no }), type: "success" });
      reload();
    });
  const nextSl = () => Math.max(0, ...form.lines.map((l) => Number(l.sl_no) || 0)) + 1;
  const lastSl = () => (form.lines.length ? form.lines[form.lines.length - 1].sl_no : "");
  const sizeTotal = form.sizes.reduce((a, s) => a + (Number(s.qty) || 0), 0);

  /* ── S/C Programme ── */
  const blankSc = () => ({ sc_no: "", so_no: "", party_id: "", sc_date: today(), target_date: "", process: "", remarks: "", lines: [] });
  const [sc, setSc] = useState(blankSc);
  const [scSource, setScSource] = useState([]);
  const scSet = (k, v) => setSc((f) => ({ ...f, [k]: v }));
  const confirmed = rows.filter((r) => r.approval_status === "CONFIRMED" && r.state === "OPEN");
  const pickScOrder = (so, keepLines = null) =>
    startTransition(async () => {
      if (!so) return (setScSource([]), scSet("lines", []));
      const res = await act.read(`/sales-shipment/sc-programmes/source/${encodeURIComponent(so)}`);
      if (res?.status !== "success") return warn(firstError(res));
      setScSource(res.data);
      setSc((f) => ({ ...f, so_no: so, lines: keepLines ?? res.data.filter((l) => l.due > 0).map((l) => ({ _k: key(), size_id: l.size_id, qty: String(l.due), rate: "" })) }));
    });
  // A link in (?so=SO-… or ?tab=sc-programme&so=SO-…) opens that order once.
  useEffect(() => {
    if (!canManage || !query?.so) return;
    if (query.tab === "sc-programme") pickScOrder(query.so);
    else open(query.so);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const openSc = (no) =>
    startTransition(async () => {
      if (!no) return (setSc(blankSc()), setScSource([]));
      const res = await act.read(`/sales-shipment/sc-programmes/${encodeURIComponent(no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      const p = res.data;
      setSc({ sc_no: p.sc_no, so_no: p.so_no, party_id: p.party_id, sc_date: p.sc_date, target_date: p.target_date, process: p.process, remarks: p.remarks ?? "", lines: [] });
      pickScOrder(p.so_no, p.lines.map((l) => ({ _k: key(), size_id: l.size_id, qty: String(l.qty), rate: String(l.rate) })));
    });
  const saveSc = () =>
    startTransition(async () => {
      const payload = { so_no: blank(sc.so_no), party_id: blank(sc.party_id), sc_date: blank(sc.sc_date), target_date: blank(sc.target_date), process: blank(sc.process), remarks: blank(sc.remarks),
        lines: sc.lines.map((l) => ({ size_id: l.size_id, qty: num(l.qty), rate: num(l.rate) })) };
      const res = await act.write(sc.sc_no ? "PUT" : "POST", sc.sc_no ? `/sales-shipment/sc-programmes/${encodeURIComponent(sc.sc_no)}` : "/sales-shipment/sc-programmes", payload);
      if (res?.status !== "success") return warn(firstError(res));
      setSc((f) => ({ ...f, sc_no: res.data.sc_no }));
      toastManager.add({ title: w("sc.saved", { no: res.data.sc_no }), type: "success" });
      reload();
    });
  const scLineOpts = scSource.map((l) => ({ value: l.size_id, label: l.label }));
  const scTotal = sc.lines.reduce((a, l) => a + (Number(l.qty) || 0), 0);

  /* ── List ── */
  const shown = useMemo(
    () =>
      rows
        .filter((r) => has(r.ref_buyer, filter.buyer) && has(r.ref_style, filter.style) && has(r.ref_style, filter.order) && (!filter.section || r.section_ids.includes(filter.section)))
        .filter((r) => has(r.so_no, filter.so) && has(r.sc_nos, filter.sc) && has(r.po_wo_no, filter.po) && (!filter.customer || r.customer_id === filter.customer))
        .filter((r) => within(r.creation_date, filter.from, filter.to) && (!filter.type || r.so_type === filter.type) && (!filter.target || r.target_date === filter.target))
        .map((r) => ({ ...r, so_type_label: w(`od.so_type.${r.so_type}`), pay_label: w(`od.pay.${r.pay_method}`), challan_label: w(`so.challan.${r.challan_status}`),
          approval_label: r.state === "CLOSED" ? w("od.state.CLOSED") : w(`od.approval.${r.approval_status}`), shipment_label: w(`so.shipment.${r.shipment_status}`) })),
    [rows, filter, w],
  );
  const viewRow = (row, autoPrint) =>
    startTransition(async () => {
      const res = await act.read(`/sales-shipment/orders/${encodeURIComponent(row.so_no)}`);
      if (res?.status !== "success") return warn(firstError(res));
      printOrder(w, res.data, autoPrint);
    });
  const live = (r) => r.approval_status === "CONFIRMED" && r.state === "OPEN";
  const actions = [
    { key: "view", label: w("od.reg.view"), open: (r) => viewRow(r, false) },
    { key: "print", label: w("od.reg.print"), open: (r) => viewRow(r, true) },
    ...(canManage
      ? [
          { key: "sc", label: w("so.reg.sc_prog"), when: live, open: (r) => (setSc(blankSc()), setTab("sc"), pickScOrder(r.so_no)) },
          { key: "amend", label: w("so.reg.amend"), when: live, open: (r) => router.push(`/sales-shipment/so-amendment?so=${encodeURIComponent(r.so_no)}`) },
        ]
      : []),
  ];

  const tabs = [listTab(w("so.list_tab"), rows.length), ...(canManage ? [["entry", w("so.entry_tab"), FilePlus2], ["sc", w("sc.tab"), Share2]] : [])];
  const customers = options.customers.map(({ value, label }) => ({ value, label }));

  return (
    <>
      <OrderFlow w={w} active="sales-order" />
      <DeskTabs tab={tab} setTab={setTab} items={tabs} />
      {tab === "entry" && canManage ? (
        <div className="flex flex-col gap-4">
          <EntryHead title={w("so.entry.title")} hint={w("so.entry.hint")}>
            <Field label={w("so.existing")} hint={w("od.ed.existing_hint")}>
              <Select value={form.so_no} onValueChange={(v) => (v ? open(v) : setForm(blankOrder()))} options={[{ value: "", label: w("so.new_option") }, ...rows.map((r) => ({ value: r.so_no, label: `${r.so_no} · ${r.customer} · ${w(`od.approval.${r.approval_status}`)}` }))]} placeholder={w("so.new_option")} aria-label={w("so.existing")} />
            </Field>
            <LockNote text={form.so_no && !editable ? w("so.locked", { no: form.so_no, status: form.state === "CLOSED" ? w("od.state.CLOSED") : w(`od.approval.${form.approval_status}`) }) : null} />
            <Group legend={w("so.g.master")}>
              <Field label={w("so.f.search_for")} className="sm:col-span-3">
                <div className="flex gap-5" role="radiogroup" aria-label={w("so.f.search_for")}>
                  {["PURCHASE_ORDER", "SALES_ORDER"].map((v) => (
                    <label key={v} className="flex items-center gap-2 text-sm">
                      <input type="radio" name="search_for" checked={form.search_for === v} onChange={() => set("search_for", v)} disabled={!editable} className="accent-brand" /> {w(`so.search_for.${v}`)}
                    </label>
                  ))}
                </div>
              </Field>
              {[["is_inter_company", "so.f.is_inter_company"], ["is_embroidery", "so.f.is_embroidery"]].map(([k, l]) => (
                <label key={k} className="flex items-center gap-2 self-end pb-2 text-sm font-semibold">
                  <Checkbox checked={form[k]} onCheckedChange={(v) => set(k, v === true)} disabled={!editable} aria-label={w(l)} /> {w(l)}
                </label>
              ))}
              <Field label={w("so.f.so_type")}>
                <Select value={form.so_type} onValueChange={(v) => set("so_type", v ?? "GENERAL")} options={opt(options.so_types, "od.so_type")} disabled={!editable} aria-label={w("so.f.so_type")} />
              </Field>
              <Field label={w("od.f.company")} required>
                <Input value={options.company} readOnly aria-label={w("od.f.company")} />
              </Field>
              <Field label={w("so.f.so_no")} hint={w("so.f.so_no_hint")}>
                <Input value={form.so_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("so.f.so_no")} />
              </Field>
              <Field label={w("so.f.customer")} required>
                <Select
                  value={form.customer_id}
                  onValueChange={(v) => setForm((f) => ({ ...f, customer_id: v ?? "", bill_to_id: f.bill_to_id || v || "", delivery_to_id: f.delivery_to_id || v || "", delivery_address: f.delivery_address || addressOf(v) }))}
                  options={customers}
                  placeholder={w("od.ed.select")}
                  disabled={!editable}
                  aria-label={w("so.f.customer")}
                />
              </Field>
              <Field label={w("so.f.receive_date")} required>
                <Input type="date" value={form.receive_date} onChange={(e) => set("receive_date", e.target.value)} disabled={!editable} aria-label={w("so.f.receive_date")} />
              </Field>
              <Field label={w("so.f.program_by")} required>
                <Input value={form.program_by} onChange={(e) => set("program_by", e.target.value)} maxLength={120} disabled={!editable} aria-label={w("so.f.program_by")} />
              </Field>
              <Field label={w("so.f.program_to")} required>
                <Input value={form.program_to} onChange={(e) => set("program_to", e.target.value)} maxLength={120} disabled={!editable} aria-label={w("so.f.program_to")} />
              </Field>
              <Field label={w("so.f.bill_to")} required>
                <Select value={form.bill_to_id} onValueChange={(v) => set("bill_to_id", v ?? "")} options={customers} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("so.f.bill_to")} />
              </Field>
              <Field label={w("so.f.delivery_target_date")} required>
                <Input type="date" value={form.delivery_target_date} onChange={(e) => set("delivery_target_date", e.target.value)} disabled={!editable} aria-label={w("so.f.delivery_target_date")} />
              </Field>
              <Field label={w("so.f.delivery_to")} required={!form.same_as_bill_to}>
                <Select value={form.same_as_bill_to ? form.bill_to_id : form.delivery_to_id} onValueChange={(v) => setForm((f) => ({ ...f, delivery_to_id: v ?? "", delivery_address: addressOf(v) || f.delivery_address }))} options={customers} placeholder={w("od.ed.select")} disabled={!editable || form.same_as_bill_to} aria-label={w("so.f.delivery_to")} />
              </Field>
              <label className="flex items-start gap-2 self-end pb-1">
                <Checkbox checked={form.same_as_bill_to} onCheckedChange={(v) => set("same_as_bill_to", v === true)} disabled={!editable} className="mt-0.5" aria-label={w("so.f.same_as_bill_to")} />
                <span>
                  <span className="block text-sm font-semibold">{w("so.f.same_as_bill_to")}</span>
                  <span className="block text-xs text-foreground-muted">{w("so.f.same_as_bill_to_hint")}</span>
                </span>
              </label>
              <Field label={w("so.f.lead_time")} hint={w("so.f.lead_time_hint")}>
                <Input value={`${leadTime} ${w("od.ed.days")}`} readOnly aria-label={w("so.f.lead_time")} />
              </Field>
              <Field label={w("so.f.delivery_address")} className="sm:col-span-3">
                <Textarea value={form.delivery_address} onChange={(e) => set("delivery_address", e.target.value)} rows={2} disabled={!editable} aria-label={w("so.f.delivery_address")} />
              </Field>
            </Group>
            <Group legend={w("so.g.terms")}>
              <Field label={w("so.f.payment_method")} required>
                <Select value={form.payment_method} onValueChange={(v) => set("payment_method", v ?? "")} options={opt(options.pay_methods, "od.pay")} disabled={!editable} aria-label={w("so.f.payment_method")} />
              </Field>
              <Field label={w("so.f.currency")} required>
                <Select value={form.currency_id} onValueChange={(v) => set("currency_id", v ?? "")} options={options.currencies} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("so.f.currency")} />
              </Field>
              <Field label={w("so.f.conv_rate")} hint={w("so.f.conv_rate_hint")}>
                <Input type="number" min="0" step="any" value={form.conv_rate} onChange={(e) => set("conv_rate", e.target.value)} disabled={!editable} aria-label={w("so.f.conv_rate")} />
              </Field>
              <Field label={w("so.f.payment_term")}>
                <Select value={form.payment_term} onValueChange={(v) => set("payment_term", v ?? "")} options={[{ value: "", label: "—" }, ...opt(options.pay_terms)]} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("so.f.payment_term")} />
              </Field>
              <Field label={w("so.f.mode_of_shipment")} required>
                <Select value={form.mode_of_shipment} onValueChange={(v) => set("mode_of_shipment", v ?? "")} options={opt(options.ship_modes)} disabled={!editable} aria-label={w("so.f.mode_of_shipment")} />
              </Field>
              <Field label={w("so.f.po_wo_no")} required>
                <Input value={form.po_wo_no} onChange={(e) => set("po_wo_no", e.target.value)} placeholder={w("so.f.po_wo_ph")} maxLength={60} disabled={!editable} aria-label={w("so.f.po_wo_no")} />
              </Field>
              <Field label={w("so.f.others_term")}>
                <Textarea value={form.others_term} onChange={(e) => set("others_term", e.target.value)} rows={2} disabled={!editable} aria-label={w("so.f.others_term")} />
              </Field>
              <Field label={w("so.f.pr_po_ref")}>
                <Textarea value={form.pr_po_ref} onChange={(e) => set("pr_po_ref", e.target.value)} rows={2} placeholder="BUYER | STYLE | ORDER#" disabled={!editable} aria-label={w("so.f.pr_po_ref")} />
              </Field>
              <Field label={w("od.f.remarks")}>
                <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!editable} aria-label={w("od.f.remarks")} />
              </Field>
              <Field label={w("so.f.additional_qty")} hint={w("so.f.additional_qty_hint")}>
                <Input type="number" min="0" max="100" step="any" value={form.additional_qty_pct} onChange={(e) => set("additional_qty_pct", e.target.value)} placeholder={w("so.f.additional_qty_ph")} disabled={!editable} aria-label={w("so.f.additional_qty")} />
              </Field>
            </Group>
            <Group legend={w("so.g.shipment")}>
              <Field label={w("so.f.season")}>
                <Input value={form.season} onChange={(e) => set("season", e.target.value)} placeholder="SS27, AW26…" maxLength={40} disabled={!editable} aria-label={w("so.f.season")} />
              </Field>
              <Field label={w("so.f.department")}>
                <Input value={form.department} onChange={(e) => set("department", e.target.value)} placeholder={w("so.f.department_ph")} maxLength={60} disabled={!editable} aria-label={w("so.f.department")} />
              </Field>
              <Field label={w("so.f.incoterm")}>
                <Select value={form.incoterm} onValueChange={(v) => set("incoterm", v ?? "")} options={[{ value: "", label: "—" }, ...opt(options.incoterms)]} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("so.f.incoterm")} />
              </Field>
              <Field label={w("so.f.destination_country")}>
                <Select value={form.destination_country_id} onValueChange={(v) => set("destination_country_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.countries]} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("so.f.destination_country")} />
              </Field>
              <Field label={w("so.f.destination_port")}>
                <Input value={form.destination_port} onChange={(e) => set("destination_port", e.target.value)} maxLength={80} disabled={!editable} aria-label={w("so.f.destination_port")} />
              </Field>
              <Field label={w("so.f.shipment_date")} required hint={w("so.f.shipment_date_hint")}>
                <Input type="date" value={form.shipment_date} onChange={(e) => set("shipment_date", e.target.value)} disabled={!editable} aria-label={w("so.f.shipment_date")} />
              </Field>
            </Group>
            <Group legend={w("so.g.details")}>
              <Field label={w("so.f.items")} hint={w("so.f.items_hint")}>
                <Select value={form.item_id} onValueChange={(v) => set("item_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.items]} placeholder={w("od.ed.select")} disabled={!editable} aria-label={w("so.f.items")} />
              </Field>
              <label className="flex items-start gap-2 self-center">
                <Checkbox checked={form.is_poly_item} onCheckedChange={(v) => set("is_poly_item", v === true)} disabled={!editable} className="mt-0.5" aria-label={w("so.f.is_poly")} />
                <span>
                  <span className="block text-sm font-semibold">{w("so.f.is_poly")}</span>
                  <span className="block text-xs text-foreground-muted">{w("so.f.is_poly_hint")}</span>
                </span>
              </label>
            </Group>
          </EntryHead>
          <EditGrid
            w={w}
            title={w("so.lines.title")}
            hint={w("so.lines.hint")}
            editable={editable}
            addLabel={w("so.lines.add")}
            onAdd={() => set("lines", [...form.lines, { _k: key(), sl_no: String(nextSl()), buyer: "", order_ref: "", style_ref: "", wo_ref: "", section_id: "", item_id: form.item_id, gmt_color: "", product_color: "", rate: "", uom: "", specification: "", code: "", gross_weight: "", net_weight: "" }])}
            emptyNote={w("so.lines.empty")}
            rows={form.lines}
            setRows={(v) => set("lines", v)}
            cols={[
              { key: "sl_no", label: w("so.col.sl"), type: "number", width: 70 },
              { key: "buyer", label: w("so.col.buyer"), max: 160 },
              { key: "order_ref", label: w("so.col.order"), max: 120 },
              { key: "style_ref", label: w("so.col.style"), max: 120 },
              { key: "wo_ref", label: w("so.col.wo_ref"), max: 120, width: 120 },
              { key: "section_id", label: w("so.col.section"), type: "select", options: options.sections },
              { key: "item_id", label: w("so.col.item"), type: "select", options: options.items, width: 200 },
              { key: "gmt_color", label: w("so.col.gmt_color"), max: 120, width: 120 },
              { key: "product_color", label: w("so.col.product_color"), max: 120, width: 120 },
              { key: "rate", label: w("so.col.rate"), type: "number", align: "right" },
              { key: "uom", label: w("so.col.unit"), type: "select", options: options.uoms, width: 130 },
              { key: "specification", label: w("so.col.specification"), max: 300 },
              { key: "code", label: w("so.col.code"), max: 60, width: 100 },
              { key: "gross_weight", label: w("so.col.gross_wt"), type: "number", align: "right", width: 100 },
              { key: "net_weight", label: w("so.col.net_wt"), type: "number", align: "right", width: 100 },
            ]}
          />
          <EditGrid
            w={w}
            title={w("so.sizes.title")}
            hint={w("so.sizes.hint")}
            editable={editable}
            addLabel={w("so.sizes.add")}
            onAdd={() => set("sizes", [...form.sizes, { _k: key(), sl_no: String(lastSl()), size: "", pcs_per_size: "1", qty: "" }])}
            emptyNote={w("so.sizes.empty")}
            rows={form.sizes}
            setRows={(v) => set("sizes", v)}
            cols={[
              { key: "sl_no", label: w("so.col.sl"), type: "number", width: 70 },
              { key: "size", label: w("so.col.size"), max: 60, width: 220 },
              { key: "pcs_per_size", label: w("so.col.p_size"), type: "number", align: "right", placeholder: "1" },
              { key: "qty", label: w("so.col.qty"), type: "number", align: "right" },
            ]}
            summaries={[
              [w("so.sizes.total"), qty(sizeTotal)],
              [w("so.sizes.rows"), form.sizes.length],
            ]}
          />
          <EditGrid
            w={w}
            title={w("so.part.title")}
            hint={w("so.part.hint")}
            editable={editable}
            addLabel={w("so.part.add")}
            onAdd={() => set("particulars", [...form.particulars, { _k: key(), sl_no: String(lastSl()), particular_id: "", value: "" }])}
            emptyNote={w("so.part.empty")}
            rows={form.particulars}
            setRows={(v) => set("particulars", v)}
            cols={[
              { key: "sl_no", label: w("so.col.sl"), type: "number", width: 70 },
              { key: "particular_id", label: w("so.col.particular"), type: "select", options: options.particulars, width: 220 },
              { key: "value", label: w("so.col.value"), max: 200, width: 260 },
            ]}
          />
          <Foot w={w} note={w("so.footer")} status={w("so.action_status")} editable={editable} pending={pending} onBack={() => setTab("list")} onClear={() => setForm(blankOrder())} onSave={save} saveLabel={w("so.save")} />
        </div>
      ) : tab === "sc" && canManage ? (
        <div className="grid gap-4 xl:grid-cols-[340px_1fr]">
          <SideList
            w={w}
            title={w("sc.side.title")}
            rows={confirmed}
            refKey="so_no"
            columns={[
              { key: "so_no", label: w("sc.side.so_no"), mono: true },
              { key: "customer", label: w("sc.side.customer") },
              { key: "so_qty", label: w("sc.side.qty"), align: "right" },
            ]}
            searchPlaceholder={w("sc.side.search")}
            filterLabel={w("sc.side.customer")}
            filterOptions={customers}
            filterPlaceholder={w("sc.side.any")}
            picked={sc.so_no}
            onPick={(r) => (sc.sc_no ? warn(w("sc.order_fixed")) : pickScOrder(r.so_no))}
          />
          <div className="flex min-w-0 flex-col gap-4">
            <EntryHead title={w("sc.title")} hint={w("sc.hint")}>
              <Field label={w("sc.existing")} hint={w("od.ed.existing_hint")}>
                <Select value={sc.sc_no} onValueChange={(v) => openSc(v)} options={[{ value: "", label: w("sc.new_option") }, ...programmes.map((p) => ({ value: p.sc_no, label: `${p.sc_no} · ${p.so_no} · ${p.party}` }))]} placeholder={w("sc.new_option")} aria-label={w("sc.existing")} />
              </Field>
              <Group legend={w("sc.g.programme")}>
                <Field label={w("sc.f.order")} required>
                  <Select value={sc.so_no} onValueChange={(v) => pickScOrder(v)} options={confirmed.map((r) => ({ value: r.so_no, label: `${r.so_no} · ${r.customer}` }))} placeholder={confirmed.length ? w("od.ed.select") : w("od.ed.no_records")} disabled={Boolean(sc.sc_no)} aria-label={w("sc.f.order")} />
                </Field>
                <Field label={w("sc.f.sc_no")}>
                  <Input value={sc.sc_no} placeholder={w("od.ed.auto_gen")} readOnly aria-label={w("sc.f.sc_no")} />
                </Field>
                <Field label={w("od.f.company")} required>
                  <Input value={options.company} readOnly aria-label={w("od.f.company")} />
                </Field>
                <Field label={w("sc.f.party")} required>
                  <Select value={sc.party_id} onValueChange={(v) => scSet("party_id", v ?? "")} options={customers} placeholder={w("od.ed.select")} aria-label={w("sc.f.party")} />
                </Field>
                <Field label={w("sc.f.sc_date")} required>
                  <Input type="date" value={sc.sc_date} onChange={(e) => scSet("sc_date", e.target.value)} aria-label={w("sc.f.sc_date")} />
                </Field>
                <Field label={w("sc.f.target_date")} required>
                  <Input type="date" value={sc.target_date} onChange={(e) => scSet("target_date", e.target.value)} aria-label={w("sc.f.target_date")} />
                </Field>
                <Field label={w("sc.f.process")} required>
                  <Input value={sc.process} onChange={(e) => scSet("process", e.target.value)} placeholder={w("sc.f.process_ph")} maxLength={80} aria-label={w("sc.f.process")} />
                </Field>
                <Field label={w("od.f.remarks")} className="sm:col-span-2">
                  <Textarea value={sc.remarks} onChange={(e) => scSet("remarks", e.target.value)} rows={2} aria-label={w("od.f.remarks")} />
                </Field>
              </Group>
            </EntryHead>
            <EditGrid
              w={w}
              title={w("sc.lines.title")}
              hint={w("sc.lines.hint")}
              editable
              addLabel={scLineOpts.length ? w("sc.lines.add") : null}
              onAdd={() => scSet("lines", [...sc.lines, { _k: key(), size_id: "", qty: "", rate: "" }])}
              emptyNote={w("sc.lines.empty")}
              rows={sc.lines}
              setRows={(v) => scSet("lines", v)}
              cols={[
                { key: "size_id", label: w("sc.col.line"), type: "select", options: scLineOpts, width: 300 },
                { key: "qty", label: w("sc.col.qty"), type: "number", align: "right" },
                { key: "rate", label: w("sc.col.rate"), type: "number", align: "right" },
                { key: "amount", label: w("sc.col.amount"), align: "right", read: (r) => money((Number(r.qty) || 0) * (Number(r.rate) || 0)) },
              ]}
              summaries={[[w("sc.lines.total"), qty(scTotal)]]}
            />
            <Foot w={w} note={w("sc.footer")} status={w("sc.action_status")} editable pending={pending} onBack={() => setTab("list")} onClear={() => (setSc(blankSc()), setScSource([]))} onSave={saveSc} saveLabel={w("sc.save")} />
          </div>
        </div>
      ) : (
        <>
          <Filters
            w={w}
            note={w("so.f.note")}
            value={filter}
            onChange={setFilter}
            extra={
              <Link href="/sales-shipment/delivery-chalan" className={cn(buttonVariants({ variant: "outline" }))}>
                {w("so.go_challans")} <ArrowRight />
              </Link>
            }
            fields={[
              { name: "buyer", label: w("so.col.buyer"), placeholder: `${w("so.col.buyer")}…` },
              { name: "style", label: w("so.col.style"), placeholder: `${w("so.col.style")}…` },
              { name: "order", label: w("so.col.order"), placeholder: `${w("so.col.order")}…` },
              { name: "section", label: w("so.col.section"), type: "select", options: options.sections },
              { name: "so", label: w("so.f.so_no"), placeholder: "SO-…" },
              { name: "sc", label: w("so.f.sc_no"), placeholder: "SC-…" },
              { name: "po", label: w("so.f.po_wo_filter"), placeholder: `${w("so.f.po_wo_filter")}…` },
              { name: "customer", label: w("so.f.customer"), type: "select", options: customers },
              { name: "from", label: w("so.f.from"), type: "date" },
              { name: "to", label: w("so.f.to"), type: "date" },
              { name: "type", label: w("so.f.so_type"), type: "select", options: opt(options.so_types, "od.so_type") },
              { name: "target", label: w("so.f.target"), type: "date" },
            ]}
          />
          <Tiles
            items={[
              [w("so.tile.orders"), shown.length],
              [w("so.tile.ordered"), qty(shown.reduce((a, r) => a + r.so_qty, 0))],
              [w("so.tile.delivered"), qty(shown.reduce((a, r) => a + r.delivery_qty, 0)), "good"],
              [w("so.tile.waiting"), shown.filter((r) => r.challan_status === "WAITING").length, "warning"],
            ]}
          />
          <Register
            w={w}
            rows={shown}
            refKey="so_no"
            exportName="sales-orders"
            onDone={reload}
            actions={actions}
            emptyTitle={w("so.empty_title")}
            emptyText={w("od.empty_text")}
            columns={[
              { key: "so_type_label", label: w("so.col.so_type") },
              { key: "section", label: w("so.col.section") },
              { key: "ref_buyer", label: w("so.col.buyer"), group: w("so.grp.ref") },
              { key: "ref_style", label: w("so.col.order_style"), group: w("so.grp.ref") },
              { key: "so_no", label: w("so.f.so_no"), type: "mono", group: w("so.grp.so_info") },
              { key: "po_wo_no", label: w("so.col.po_wo"), group: w("so.grp.so_info") },
              { key: "creation_date", label: w("so.col.creation_date"), type: "date", group: w("so.grp.details") },
              { key: "so_qty", label: w("so.col.so_qty"), type: "number", align: "right", group: w("so.grp.details") },
              { key: "delivery_qty", label: w("so.col.delivery_qty"), type: "number", align: "right", group: w("so.grp.details") },
              { key: "due_qty", label: w("so.col.due_qty"), type: "number", align: "right", group: w("so.grp.details") },
              { key: "chalan_no", label: w("so.col.chalan_no"), type: "mono", group: w("so.grp.details") },
              { key: "chalan_date", label: w("so.col.chalan_date"), type: "date", group: w("so.grp.details") },
              { key: "company", label: w("od.f.company"), group: w("so.grp.party") },
              { key: "customer", label: w("so.f.customer"), group: w("so.grp.party") },
              { key: "delivery_address", label: w("so.col.delivery_addr") },
              { key: "pay_label", label: w("so.col.pay_method") },
              { key: "target_date", label: w("so.col.target"), type: "date" },
              { key: "is_revised", label: w("so.col.is_revised"), type: "yesno", align: "center" },
              { key: "challan_label", label: w("so.col.challan_status"), type: "badge" },
              { key: "approval_label", label: w("so.col.approval"), type: "badge" },
              { key: "shipped_qty", label: w("so.col.shipped_qty"), type: "number", align: "right" },
              { key: "shipment_label", label: w("so.col.shipment_status") },
              { key: "total_value", label: w("so.col.total_value"), type: "number", align: "right" },
            ]}
          />
        </>
      )}
    </>
  );
}

export { SalesOrderDesk, printOrder };
