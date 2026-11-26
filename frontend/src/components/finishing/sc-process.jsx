"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { FileSpreadsheet, ReceiptText, RotateCcw, Search, Truck, PackageOpen } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button, buttonVariants } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { RecordsGrid } from "@/components/erp/records-grid";
import { Field, EditorFoot, LinesCard, usePaper } from "@/components/dyehouse/chem-store";
import { useT } from "@/lib/i18n";

/*
 * Finishing Line → S/C Process (AnnotechERP 18/03): the delivery challan,
 * the finished receipt and the process bill (one editor each, as Brickland's
 * entry tab), their register with its row actions, and the party balance.
 */

const today = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" });
const kg = (v) => Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: 3 });
const money = (v) => Number(v || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const blank = (v) => (v === "" || v === undefined ? null : v);
const num = (v) => (v === "" || v === null || v === undefined ? null : Number(v));
const str = (v) => (v === null || v === undefined ? "" : String(v));
const firstError = (res) => (res?.errors ? Object.values(res.errors).flat()[0] : res?.message);
const labelOf = (list, v) => list.find((x) => x.value === v)?.label ?? "";

/** The words a screen reads: `sc.<k>`, its own Add button among them. */
function useWords(addKey) {
  const t = useT("finishing");
  return (k, p) => (k === "ed.add_item" ? t(`sc.${addKey}`) : t(`sc.${k}`, p));
}

/** The editor's top card: its title, explanation, badge and the Existing select. */
function Head({ c, kind, icon, form, existing, onPick, children }) {
  const Icon = icon;
  return (
    <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="flex items-center gap-2 text-base font-semibold">
            <Icon className="size-4" /> {c(`${kind}.title`)}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{c(`${kind}.hint`)}</p>
        </div>
        <span className="shrink-0 rounded-full bg-brand-subtle px-2.5 py-0.5 text-xs font-semibold text-brand">{form.status && form.status !== "DRAFT" ? c(`opt.${kind}_status.${form.status}`) : form.ref ? c("ed.editing") : c("ed.new_badge")}</span>
      </div>
      <Field label={c(`${kind}.existing`)} hint={c("ed.existing_hint")}>
        <Select value={form.ref ?? ""} onValueChange={(v) => onPick(v ?? "")} options={[{ value: "", label: c(`${kind}.new_option`) }, ...existing]} placeholder={c(`${kind}.new_option`)} />
      </Field>
      <div className="grid gap-4 sm:grid-cols-3">{children}</div>
    </div>
  );
}

/**
 * S/C Program Challan: the challan's header, then one line per shade — a
 * batch on the floor (its buyer, order, style, colour, dia and kilos fill in)
 * or a plain lot — with the process asked for and the rate (the process's,
 * unless the party quotes otherwise). Saving keeps it a draft.
 */
function ScChallanEditor({ challans, options, canManage, load, save, backHref, initialNo = null }) {
  const c = useWords("challan.add");
  const router = useRouter();
  const fresh = () => ({ ref: null, status: "DRAFT", challan_date: today(), company: options.companies[0]?.value ?? "", supplier_id: "", store_id: "", gate_pass_no: "", vehicle_no: "", remarks: "", lines: [] });
  const dia = (v) => (v ? c(`opt.dia.${v}`) : "");
  const toForm = (x) => ({
    ref: x.challan_no,
    status: x.status,
    challan_date: x.challan_date,
    company: options.companies[0]?.value ?? "",
    supplier_id: x.supplier_id,
    store_id: x.store_id,
    gate_pass_no: str(x.gate_pass_no),
    vehicle_no: str(x.vehicle_no),
    remarks: str(x.remarks),
    lines: x.lines.map((l) => ({ ...l, receipt_id: l.receipt_id ?? "", buyer_id: l.buyer_id ?? "", order_id: l.order_id ?? "", dia_type: l.dia_type ?? "", dia_label: dia(l.dia_type), ...Object.fromEntries(["style", "colour", "description", "lot_no", "qty_kg", "rolls", "rate", "remarks"].map((k) => [k, str(l[k])])) })),
  });
  const { form, setForm, entry, setEntry, pending, startTransition, warn, open, toastManager } = usePaper({ blank: fresh, toForm, load, initialNo, docKey: "doc" });
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && form.status === "DRAFT";
  const none = { receipt_id: "", buyer_id: "", style: "", order_id: "", colour: "", process_id: "", description: "", lot_no: "", dia_type: "", qty_kg: "", rolls: "", rate: "", remarks: "" };
  const e = entry ?? none;
  const dias = ["OPEN", "TUBE"].map((v) => ({ value: v, label: dia(v) }));

  function add() {
    if (!e.process_id || !(Number(e.qty_kg) > 0) || (!e.receipt_id && !e.lot_no && !e.description)) return warn(c("challan.line_incomplete"));
    setForm((f) => ({
      ...f,
      lines: [...f.lines, { ...e, batch: e.receipt_id ? labelOf(options.cards, e.receipt_id) : "—", buyer: labelOf(options.buyers, e.buyer_id), order_no: labelOf(options.orders, e.order_id).split(" [")[0], process: labelOf(options.processes, e.process_id), dia_label: dia(e.dia_type) }],
    }));
    setEntry(null);
  }

  function submit() {
    startTransition(async () => {
      const res = await save(form.ref, {
        challan_date: blank(form.challan_date),
        supplier_id: blank(form.supplier_id),
        store_id: blank(form.store_id),
        gate_pass_no: blank(form.gate_pass_no),
        vehicle_no: blank(form.vehicle_no),
        remarks: blank(form.remarks),
        lines: form.lines.map((l) => ({ receipt_id: blank(l.receipt_id), buyer_id: blank(l.buyer_id), order_id: blank(l.order_id), style: blank(l.style), colour: blank(l.colour), process_id: blank(l.process_id), description: blank(l.description), lot_no: blank(l.lot_no), dia_type: blank(l.dia_type), qty_kg: num(l.qty_kg), rolls: num(l.rolls), rate: num(l.rate), remarks: blank(l.remarks) })),
      });
      if (res?.status === "success") {
        setForm(toForm(res.doc));
        toastManager.add({ title: c("challan.saved", { no: res.doc.challan_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  const pickCard = (v) => {
    const k = options.cards.find((x) => x.value === v);
    return k ? { buyer_id: k.buyer_id ?? "", order_id: k.order_id ?? "", style: str(k.style), colour: str(k.colour), dia_type: k.dia_type ?? "", qty_kg: str(k.qty), rolls: str(k.rolls) } : {};
  };
  const total = form.lines.reduce((s, l) => s + (Number(l.qty_kg) || 0), 0);

  return (
    <div className="flex flex-col gap-4">
      <Head c={c} kind="challan" icon={Truck} form={form} existing={challans.map((x) => ({ value: x.challan_no, label: `${x.challan_no} · ${x.supplier ?? ""} · ${c(`opt.challan_status.${x.status}`)}` }))} onPick={(v) => (v ? open(v) : (setForm(fresh()), setEntry(null)))}>
        <Field label={c("challan.date")} required>
          <Input type="date" value={form.challan_date} onChange={(ev) => set("challan_date", ev.target.value)} disabled={!editable} />
        </Field>
        <Field label={c("f.company")} required>
          <Select value={form.company} onValueChange={(v) => set("company", v ?? "")} options={options.companies} placeholder={c("f.select_company")} disabled={!editable} />
        </Field>
        <Field label={c("f.supplier")} required>
          <Select value={form.supplier_id} onValueChange={(v) => set("supplier_id", v ?? "")} options={options.suppliers} placeholder={c("f.select_supplier")} disabled={!editable} />
        </Field>
        <Field label={c("f.store")} required>
          <Select value={form.store_id} onValueChange={(v) => set("store_id", v ?? "")} options={options.stores} placeholder={c("f.select_store")} disabled={!editable} />
        </Field>
        <Field label={c("f.gate_pass_no")} hint={c("challan.gate_hint")}>
          <Input value={form.gate_pass_no} onChange={(ev) => set("gate_pass_no", ev.target.value)} maxLength={40} disabled={!editable} />
        </Field>
        <Field label={c("f.vehicle_no")}>
          <Input value={form.vehicle_no} onChange={(ev) => set("vehicle_no", ev.target.value)} maxLength={40} disabled={!editable} />
        </Field>
        <Field label={c("f.remarks")} className="sm:col-span-3">
          <Textarea value={form.remarks} onChange={(ev) => set("remarks", ev.target.value)} rows={3} disabled={!editable} />
        </Field>
      </Head>
      <LinesCard
        c={c}
        title={c("challan.lines")}
        hint={c("challan.lines_hint")}
        cols={[
          { key: "receipt_id", label: c("f.batch"), type: "item", lineType: "readonly", labelKey: "batch", options: options.cards, placeholder: c("f.select_batch"), width: "w-72", onPick: pickCard },
          { key: "buyer_id", label: c("f.buyer"), type: "item", lineType: "readonly", labelKey: "buyer", options: options.buyers, placeholder: c("f.select_buyer") },
          { key: "style", label: c("f.style"), type: "text", max: 120 },
          { key: "order_id", label: c("f.order"), type: "item", lineType: "readonly", labelKey: "order_no", options: options.orders, placeholder: c("f.select_order"), onPick: (v) => ({ style: str(options.orders.find((o) => o.value === v)?.style) }) },
          { key: "colour", label: c("f.colour"), type: "text", max: 120 },
          { key: "process_id", label: c("f.process"), type: "item", lineType: "readonly", labelKey: "process", options: options.processes, placeholder: c("f.select_process"), onPick: (v) => ({ rate: str(options.processes.find((p) => p.value === v)?.rate) }) },
          { key: "description", label: c("f.goods"), type: "text" },
          { key: "lot_no", label: c("f.lot_no"), type: "text", max: 40 },
          { key: "dia_type", label: c("f.dia_type"), type: "item", lineType: "readonly", labelKey: "dia_label", options: dias, placeholder: c("f.select_dia") },
          { key: "qty_kg", label: c("f.qty_kg"), type: "number" },
          { key: "rolls", label: c("f.rolls"), type: "number" },
          { key: "rate", label: c("f.rate"), type: "number" },
          { key: "remarks", label: c("f.line_remarks"), type: "text" },
        ]}
        entry={e}
        setEntry={setEntry}
        lines={form.lines}
        setLines={(lines) => setForm((f) => ({ ...f, lines }))}
        editable={editable}
        onAdd={add}
        emptyHint={c("challan.no_lines_hint")}
      />
      {form.lines.length ? <p className="px-1 text-[13px] text-foreground-muted tabular-nums">{c("challan.total", { kg: kg(total), lines: form.lines.length })}</p> : null}
      <EditorFoot c={c} backHref={backHref} note={editable || !form.ref ? c("challan.footer") : c("challan.footer_locked")} editable={editable} pending={pending} onClear={() => (setForm(fresh()), setEntry(null))} onSave={submit} saveLabel={c("challan.save")} />
    </div>
  );
}

/**
 * Receive S/C Finish Fabric at Store: the challan it answers (its party
 * fills in), the store, then one line per challan line answered — what came
 * back, the rolls and the short weight the party disputes. Saving keeps it a draft.
 */
function ScReceiptEditor({ receipts, options, canManage, load, save, backHref, initialNo = null }) {
  const c = useWords("receipt.add");
  const router = useRouter();
  const fresh = () => ({ ref: null, status: "DRAFT", receive_date: today(), company: options.companies[0]?.value ?? "", challan_id: "", supplier_id: "", store_id: "", gate_pass_no: "", remarks: "", lines: [] });
  const toForm = (x) => ({
    ref: x.mrr_no,
    status: x.status,
    receive_date: x.receive_date,
    company: options.companies[0]?.value ?? "",
    challan_id: x.challan_id ?? "",
    supplier_id: x.supplier_id,
    store_id: x.store_id,
    gate_pass_no: str(x.gate_pass_no),
    remarks: str(x.remarks),
    lines: x.lines.map((l) => ({ ...l, challan_line_id: l.challan_line_id ?? "", ...Object.fromEntries(["description", "lot_no", "recv_kg", "rolls", "short_kg", "remarks"].map((k) => [k, str(l[k])])) })),
  });
  const { form, setForm, entry, setEntry, pending, startTransition, warn, open, toastManager } = usePaper({ blank: fresh, toForm, load, initialNo, docKey: "doc" });
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && form.status === "DRAFT";
  const none = { challan_line_id: "", description: "", lot_no: "", recv_kg: "", rolls: "", short_kg: "", remarks: "" };
  const e = entry ?? none;
  const challan = options.challans.find((x) => x.value === form.challan_id);
  const challanLines = (challan?.lines ?? []).map((l) => ({ ...l, label: `${l.label} · ${c("receipt.left", { kg: kg(l.balance) })}` }));

  function add() {
    if (!(Number(e.recv_kg) > 0 || Number(e.short_kg) > 0)) return warn(c("receipt.line_incomplete"));
    setForm((f) => ({ ...f, lines: [...f.lines, { ...e, batch: e.challan_line_id ? labelOf(challan?.lines ?? [], e.challan_line_id).split(" · ").slice(0, 2).join(" · ") : "—" }] }));
    setEntry(null);
  }

  function submit() {
    startTransition(async () => {
      const res = await save(form.ref, {
        receive_date: blank(form.receive_date),
        challan_id: blank(form.challan_id),
        supplier_id: blank(form.supplier_id),
        store_id: blank(form.store_id),
        gate_pass_no: blank(form.gate_pass_no),
        remarks: blank(form.remarks),
        lines: form.lines.map((l) => ({ challan_line_id: blank(l.challan_line_id), description: blank(l.description), lot_no: blank(l.lot_no), recv_kg: num(l.recv_kg) ?? 0, rolls: num(l.rolls), short_kg: num(l.short_kg) ?? 0, remarks: blank(l.remarks) })),
      });
      if (res?.status === "success") {
        setForm(toForm(res.doc));
        toastManager.add({ title: c("receipt.saved", { no: res.doc.mrr_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  const pickLine = (v) => {
    const l = challan?.lines.find((x) => x.value === v);
    return l ? { description: str(l.description), lot_no: str(l.lot_no), recv_kg: str(Math.max(0, l.balance)), rolls: str(l.rolls) } : {};
  };
  const recv = form.lines.reduce((s, l) => s + (Number(l.recv_kg) || 0), 0);
  const short = form.lines.reduce((s, l) => s + (Number(l.short_kg) || 0), 0);

  return (
    <div className="flex flex-col gap-4">
      <Head c={c} kind="receipt" icon={PackageOpen} form={form} existing={receipts.map((x) => ({ value: x.mrr_no, label: `${x.mrr_no} · ${x.supplier ?? ""} · ${c(`opt.receipt_status.${x.status}`)}` }))} onPick={(v) => (v ? open(v) : (setForm(fresh()), setEntry(null)))}>
        <Field label={c("receipt.date")} required>
          <Input type="date" value={form.receive_date} onChange={(ev) => set("receive_date", ev.target.value)} disabled={!editable} />
        </Field>
        <Field label={c("f.company")} required>
          <Select value={form.company} onValueChange={(v) => set("company", v ?? "")} options={options.companies} placeholder={c("f.select_company")} disabled={!editable} />
        </Field>
        <Field label={c("f.challan_no")} hint={c("receipt.challan_hint")}>
          <Select
            value={form.challan_id}
            onValueChange={(v) => setForm((f) => ({ ...f, challan_id: v ?? "", supplier_id: options.challans.find((x) => x.value === v)?.supplier_id ?? f.supplier_id, lines: v === f.challan_id ? f.lines : [] }))}
            options={[{ value: "", label: c("f.select_challan") }, ...options.challans]}
            placeholder={c("f.select_challan")}
            disabled={!editable}
          />
        </Field>
        <Field label={c("f.party")} required>
          <Select value={form.supplier_id} onValueChange={(v) => set("supplier_id", v ?? "")} options={options.suppliers} placeholder={c("f.select_party")} disabled={!editable || Boolean(form.challan_id)} />
        </Field>
        <Field label={c("f.store")} required>
          <Select value={form.store_id} onValueChange={(v) => set("store_id", v ?? "")} options={options.stores} placeholder={c("f.select_store")} disabled={!editable} />
        </Field>
        <Field label={c("f.gate_pass_no")}>
          <Input value={form.gate_pass_no} onChange={(ev) => set("gate_pass_no", ev.target.value)} maxLength={40} disabled={!editable} />
        </Field>
        <Field label={c("f.remarks")} className="sm:col-span-3">
          <Textarea value={form.remarks} onChange={(ev) => set("remarks", ev.target.value)} rows={3} disabled={!editable} />
        </Field>
      </Head>
      <LinesCard
        c={c}
        title={c("receipt.lines")}
        hint={c("receipt.lines_hint")}
        cols={[
          { key: "challan_line_id", label: c("f.batch"), type: "item", lineType: "readonly", labelKey: "batch", options: challanLines, placeholder: c("f.select_batch"), width: "w-80", onPick: pickLine },
          { key: "description", label: c("f.description"), type: "text" },
          { key: "lot_no", label: c("f.lot_no"), type: "text", max: 40 },
          { key: "recv_kg", label: c("f.recv_kg"), type: "number" },
          { key: "rolls", label: c("f.rolls"), type: "number" },
          { key: "short_kg", label: c("f.short_kg"), type: "number" },
          { key: "remarks", label: c("f.line_remarks"), type: "text" },
        ]}
        entry={e}
        setEntry={setEntry}
        lines={form.lines}
        setLines={(lines) => setForm((f) => ({ ...f, lines }))}
        editable={editable}
        onAdd={add}
        emptyHint={c("receipt.no_lines_hint")}
      />
      {form.lines.length ? <p className="px-1 text-[13px] text-foreground-muted tabular-nums">{c("receipt.total", { recv: kg(recv), short: kg(short) })}</p> : null}
      <EditorFoot c={c} backHref={backHref} note={editable || !form.ref ? c("receipt.footer") : c("receipt.footer_locked")} editable={editable} pending={pending} onClear={() => (setForm(fresh()), setEntry(null))} onSave={submit} saveLabel={c("receipt.save")} />
    </div>
  );
}

/**
 * S/C Program Bill: the party's own bill number and revision, then one line
 * per process charged against the challan it was done under — the rate the
 * process's unless the party quotes otherwise, the amount quantity × rate,
 * never typed. Rewritten while draft or submitted.
 */
function ScBillEditor({ bills, options, canManage, load, save, backHref, initialNo = null }) {
  const c = useWords("bill.add");
  const router = useRouter();
  const fresh = () => ({ ref: null, status: "DRAFT", bill_date: today(), bill_no: "", company: options.companies[0]?.value ?? "", supplier_id: "", ref_bill_no: "", rev_no: "", rev_description: "", remarks: "", lines: [] });
  const toForm = (x) => ({
    ref: `${x.bill_no}|${x.party_code ?? ""}`,
    status: x.status,
    bill_date: x.bill_date,
    bill_no: x.bill_no,
    company: options.companies[0]?.value ?? "",
    supplier_id: x.supplier_id,
    ref_bill_no: str(x.ref_bill_no),
    rev_no: str(x.rev_no),
    rev_description: str(x.rev_description),
    remarks: str(x.remarks),
    lines: x.lines.map((l) => ({ ...l, ...Object.fromEntries(["description", "qty_kg", "rate", "remarks"].map((k) => [k, str(l[k])])) })),
  });
  const { form, setForm, entry, setEntry, pending, startTransition, warn, open, toastManager } = usePaper({ blank: fresh, toForm, load, initialNo, docKey: "doc" });
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && ["DRAFT", "SUBMITTED"].includes(form.status);
  const none = { challan_id: "", process_id: "", description: "", qty_kg: "", rate: "", remarks: "" };
  const e = entry ?? none;
  const challans = options.challans.filter((x) => !form.supplier_id || x.supplier_id === form.supplier_id).map((x) => ({ value: x.value, label: x.challan_no }));

  function add() {
    if (!e.challan_id || !e.process_id || !(Number(e.qty_kg) > 0)) return warn(c("bill.line_incomplete"));
    setForm((f) => ({ ...f, lines: [...f.lines, { ...e, challan: labelOf(challans, e.challan_id), process: labelOf(options.processes, e.process_id) }] }));
    setEntry(null);
  }

  function submit() {
    startTransition(async () => {
      const res = await save(form.ref, {
        bill_date: blank(form.bill_date),
        bill_no: blank(form.bill_no),
        supplier_id: blank(form.supplier_id),
        ref_bill_no: blank(form.ref_bill_no),
        rev_no: blank(form.rev_no),
        rev_description: blank(form.rev_description),
        remarks: blank(form.remarks),
        lines: form.lines.map((l) => ({ challan_id: l.challan_id, process_id: l.process_id, description: blank(l.description), qty_kg: num(l.qty_kg), rate: num(l.rate), remarks: blank(l.remarks) })),
      });
      if (res?.status === "success") {
        setForm(toForm(res.doc));
        toastManager.add({ title: c("bill.saved", { no: res.doc.bill_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  const lines = form.lines.map((l) => ({ ...l, amount: money((Number(l.qty_kg) || 0) * (Number(l.rate) || 0)) }));
  const total = form.lines.reduce((s, l) => s + (Number(l.qty_kg) || 0) * (Number(l.rate) || 0), 0);

  return (
    <div className="flex flex-col gap-4">
      <Head c={c} kind="bill" icon={ReceiptText} form={form} existing={bills.map((x) => ({ value: `${x.bill_no}|${x.party_code ?? ""}`, label: `${x.bill_no} · ${x.supplier ?? ""} · ${c(`opt.bill_status.${x.status}`)}` }))} onPick={(v) => (v ? open(v) : (setForm(fresh()), setEntry(null)))}>
        <Field label={c("bill.date")} required>
          <Input type="date" value={form.bill_date} onChange={(ev) => set("bill_date", ev.target.value)} disabled={!editable} />
        </Field>
        <Field label={c("bill.no")} required>
          <Input value={form.bill_no} onChange={(ev) => set("bill_no", ev.target.value)} maxLength={40} disabled={!editable} />
        </Field>
        <Field label={c("f.company")} required>
          <Select value={form.company} onValueChange={(v) => set("company", v ?? "")} options={options.companies} placeholder={c("f.select_company")} disabled={!editable} />
        </Field>
        <Field label={c("f.supplier")} required>
          <Select value={form.supplier_id} onValueChange={(v) => setForm((f) => ({ ...f, supplier_id: v ?? "", lines: v === f.supplier_id ? f.lines : [] }))} options={options.suppliers} placeholder={c("f.select_supplier")} disabled={!editable} />
        </Field>
        <Field label={c("bill.ref_no")}>
          <Input value={form.ref_bill_no} onChange={(ev) => set("ref_bill_no", ev.target.value)} maxLength={40} disabled={!editable} />
        </Field>
        <Field label={c("bill.rev_no")}>
          <Input value={form.rev_no} onChange={(ev) => set("rev_no", ev.target.value)} maxLength={16} disabled={!editable} />
        </Field>
        <Field label={c("bill.rev_description")}>
          <Input value={form.rev_description} onChange={(ev) => set("rev_description", ev.target.value)} maxLength={255} disabled={!editable} />
        </Field>
        <Field label={c("f.remarks")} className="sm:col-span-3">
          <Textarea value={form.remarks} onChange={(ev) => set("remarks", ev.target.value)} rows={3} disabled={!editable} />
        </Field>
      </Head>
      <LinesCard
        c={c}
        title={c("bill.lines")}
        hint={c("bill.lines_hint")}
        cols={[
          { key: "challan_id", label: c("f.challan"), type: "item", lineType: "readonly", labelKey: "challan", options: challans, placeholder: c("f.select_challan_short") },
          { key: "process_id", label: c("f.process"), type: "item", lineType: "readonly", labelKey: "process", options: options.processes, placeholder: c("f.select_process"), onPick: (v) => ({ rate: str(options.processes.find((p) => p.value === v)?.rate) }) },
          { key: "description", label: c("f.description"), type: "text" },
          { key: "qty_kg", label: c("f.qty_kg"), type: "number" },
          { key: "rate", label: c("f.rate"), type: "number" },
          { key: "amount", label: c("f.amount"), type: "readonly" },
          { key: "remarks", label: c("f.line_remarks"), type: "text" },
        ]}
        entry={{ ...e, amount: money((Number(e.qty_kg) || 0) * (Number(e.rate) || 0)) }}
        setEntry={(x) => setEntry(x ? (({ amount, ...rest }) => rest)(x) : null)}
        lines={lines}
        setLines={(next) => setForm((f) => ({ ...f, lines: next.map(({ amount, ...rest }) => rest) }))}
        editable={editable}
        onAdd={add}
        emptyHint={c("bill.no_lines_hint")}
      />
      {form.lines.length ? <p className="px-1 text-[13px] text-foreground-muted tabular-nums">{c("bill.total", { amount: money(total) })}</p> : null}
      <EditorFoot c={c} backHref={backHref} note={editable || !form.ref ? c("bill.footer") : c("bill.footer_locked")} editable={editable} pending={pending} onClear={() => (setForm(fresh()), setEntry(null))} onSave={submit} saveLabel={c("bill.save")} />
    </div>
  );
}

/**
 * A register: the grid and its row actions — an `href` one opens the row's
 * paper (":no" and ":party" filled in), the rest ask first in Brickland's
 * words and refresh once done. `when` keeps an action to the rows whose
 * `field` holds one of `in`.
 */
function ScRegister({ rows, columns, exportName, refKey, labelKey, actions, emptyState }) {
  const c = useWords("challan.add");
  const router = useRouter();
  const toastManager = useToastManager();
  const [asking, setAsking] = useState(null);
  const [pending, startTransition] = useTransition();
  const fill = (text, row) => text.replaceAll(":no", encodeURIComponent(row[labelKey ?? refKey] ?? "")).replaceAll(":party", encodeURIComponent(row.party_code ?? ""));
  const say = (text, row) => text.replaceAll(":no", row[labelKey ?? refKey] ?? "");

  function go() {
    const { action, row } = asking;
    startTransition(async () => {
      const res = await action.run(row[refKey]);
      setAsking(null);
      if (res?.status === "success") {
        toastManager.add({ title: say(action.done, row), type: "success" });
        router.refresh();
      } else toastManager.add({ title: firstError(res), type: "danger" });
    });
  }

  return (
    <>
      <RecordsGrid
        exportName={exportName}
        rows={rows}
        rowKey={refKey}
        columns={columns}
        emptyState={emptyState}
        rowActions={actions.map((a) => ({ key: a.key, label: a.label, onClick: (row) => (a.href ? router.push(fill(a.href, row)) : setAsking({ action: a, row })), disabled: (row) => (a.when ? !a.when.in.includes(row[a.when.field]) : false) }))}
      />
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking ? say(asking.action.title, asking.row) : ""}
        description={asking ? say(asking.action.text, asking.row) : ""}
        confirmLabel={asking?.action.label}
        cancelLabel={c("reg.keep")}
        destructive={asking?.action.destructive ?? false}
        loading={pending}
        onConfirm={go}
      />
    </>
  );
}

/**
 * Sub-Contract Party Balance: what each processor still holds — every posted
 * challan line, delivered against received and short, counted on the server
 * as you look. Show reads the search from the url; Excel writes the same rows.
 */
function ScPartyBalance({ rows, query, options, base }) {
  const c = useWords("challan.add");
  const router = useRouter();
  const [party, setParty] = useState(query.party ?? "");
  const [challan, setChallan] = useState(query.challan ?? "");
  const [onlyOpen, setOnlyOpen] = useState(query.open === "1");
  const qs = new URLSearchParams(Object.entries({ party: query.party, challan: query.challan, open: query.open }).filter(([, v]) => v)).toString();

  function show() {
    const p = new URLSearchParams(Object.entries({ party, challan: challan.trim(), open: onlyOpen ? "1" : "" }).filter(([, v]) => v));
    router.push(p.toString() ? `${base}?${p}` : base);
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <div>
          <h2 className="flex items-center gap-2 text-base font-semibold">
            <Search className="size-4" /> {c("balance.search")}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{c("balance.hint")}</p>
        </div>
        <div className="grid gap-4 sm:grid-cols-3">
          <Field label={c("f.party")}>
            <Select value={party} onValueChange={(v) => setParty(v ?? "")} options={[{ value: "", label: c("f.select_party_short") }, ...options.suppliers]} placeholder={c("f.select_party_short")} />
          </Field>
          <Field label={c("f.challan_no")}>
            <Input value={challan} onChange={(ev) => setChallan(ev.target.value)} onKeyDown={(ev) => ev.key === "Enter" && show()} maxLength={40} />
          </Field>
          <label className="flex items-center gap-2 self-end pb-2 text-sm font-medium">
            <Checkbox checked={onlyOpen} onCheckedChange={(v) => setOnlyOpen(Boolean(v))} /> {c("balance.open_only")}
          </label>
        </div>
        <div className="flex flex-wrap items-center gap-2 border-t border-border pt-4">
          <Button onClick={show}>
            <Search /> {c("balance.show")}
          </Button>
          {rows.length ? (
            <a href={`/api/finishing/sc-balance${qs ? `?${qs}` : ""}`} className={cn(buttonVariants({ variant: "outline" }))}>
              <FileSpreadsheet /> {c("balance.excel")}
            </a>
          ) : (
            <Button variant="outline" disabled>
              <FileSpreadsheet /> {c("balance.excel")}
            </Button>
          )}
          <Button
            variant="outline"
            className="ml-auto"
            onClick={() => {
              setParty("");
              setChallan("");
              setOnlyOpen(false);
              router.push(base);
            }}
          >
            <RotateCcw /> {c("balance.reset")}
          </Button>
        </div>
      </div>
      <RecordsGrid
        exportName="sc-party-balance"
        rows={rows}
        emptyState={{ title: c("balance.empty"), text: c("balance.empty_hint") }}
        columns={[
          { key: "supplier", label: c("col.supplier") },
          { key: "challan_no", label: c("col.challan_no"), type: "mono" },
          { key: "challan_date", label: c("col.challan_date"), type: "date" },
          { key: "batch_no", label: c("col.batch_no"), type: "mono" },
          { key: "order_no", label: c("col.order_no") },
          { key: "colour", label: c("col.colour") },
          { key: "process", label: c("col.process") },
          { key: "lot_no", label: c("col.lot_no") },
          { key: "qty_kg", label: c("col.qty_kg"), type: "number", align: "right" },
          { key: "received_kg", label: c("col.received_kg"), type: "number", align: "right" },
          { key: "short_kg", label: c("col.short_kg"), type: "number", align: "right" },
          { key: "balance_kg", label: c("col.balance_kg"), type: "number", align: "right" },
          { key: "days_out", label: c("col.days_out"), type: "number", align: "right" },
        ]}
      />
    </div>
  );
}

export { ScChallanEditor, ScReceiptEditor, ScBillEditor, ScRegister, ScPartyBalance };
