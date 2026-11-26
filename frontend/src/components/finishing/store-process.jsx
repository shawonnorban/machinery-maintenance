"use client";

import { useRouter } from "next/navigation";
import { PackageCheck, Scissors } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Field, Group, EditorFoot, LinesCard, usePaper } from "@/components/dyehouse/chem-store";
import { useT } from "@/lib/i18n";

/*
 * Finishing Line → Fabric Store (AnnotechERP 18/05): Brickland's entry tabs
 * for the delivery to the finished fabric store (one order's batch lots,
 * each carrying what the store racks it by) and the cutting floor's
 * requisition (lots the store holds, asked and released).
 */

const today = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" });
const kg = (v) => Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: 3 });
const blank = (v) => (v === "" || v === undefined ? null : v);
const num = (v) => (v === "" || v === null || v === undefined ? null : Number(v));
const str = (v) => (v === null || v === undefined ? "" : String(v));
const firstError = (res) => (res?.errors ? Object.values(res.errors).flat()[0] : res?.message);
const labelOf = (list, v) => list.find((x) => x.value === v)?.label ?? "";

/** The editor's top card: title, explanation, badge and Existing select. */
const lenText = (v) => Number(v).toLocaleString("en-US", { maximumFractionDigits: 3 });

function Head({ c, kind, icon: Icon, form, existing, onPick, badge, children }) {
  return (
    <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="flex items-center gap-2 text-base font-semibold">
            <Icon className="size-4" /> {c(`${kind}.title`)}
          </h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{c(`${kind}.hint`)}</p>
        </div>
        <span className="shrink-0 rounded-full bg-brand-subtle px-2.5 py-0.5 text-xs font-semibold text-brand">{badge}</span>
      </div>
      <Field label={c(`${kind}.existing`)} hint={c("ed.existing_hint")}>
        <Select value={form.ref ?? ""} onValueChange={(v) => onPick(v ?? "")} options={[{ value: "", label: c(`${kind}.new_option`) }, ...existing]} placeholder={c(`${kind}.new_option`)} />
      </Field>
      {children}
    </div>
  );
}

/**
 * Delivery to Finish Fabric Store: the receiving store and remarks, then one
 * line per batch lot — a batch Ready for Delivery (its order, colour, lot,
 * dia, kilos and rolls come with it), the fabric type, details, shade, GSM,
 * dia, grey against finish quantity, rate and AOP. Every lot of one order.
 *
 * @param {{ deliveries: object[], options: object, canManage: boolean, load: Function, save: Function, backHref: string, initialNo?: string }} props
 */
function FinStoreDeliveryEditor({ deliveries, options, canManage, load, save, backHref, initialNo = null }) {
  const t = useT("finishing");
  const unitWord = t(`store.len.${options.len_unit ?? "YDS"}`);
  const router = useRouter();
  const c = (k, p) => (k === "ed.add_item" ? t("store.delivery.add") : t(`store.${k}`, p));
  const fresh = () => ({ ref: null, status: "DRAFT", receive_date: today(), company: options.companies[0]?.value ?? "", store_id: "", remarks: "", lines: [] });
  const dia = (v) => (v ? c(`opt.dia.${v}`) : "");
  const toForm = (x) => ({
    ref: x.delivery_no,
    status: x.status,
    receive_date: x.receive_date,
    company: options.companies[0]?.value ?? "",
    store_id: x.store_id,
    remarks: str(x.remarks),
    lines: x.lines.map((l) => ({ ...l, order_id: l.order_id ?? "", fabric_type: l.fabric_type ?? "", dia_type: l.dia_type ?? "", dia_label: dia(l.dia_type), aop: Boolean(l.aop), ...Object.fromEntries(["colour", "fabric_details", "item_code", "lot_no", "tag_batch_no", "shade_no", "fin_gsm", "fin_dia", "grey_qty", "finish_qty", "finish_len", "rolls", "rate", "remarks"].map((k) => [k, str(l[k])])) })),
  });
  const { form, setForm, entry, setEntry, pending, startTransition, warn, open, toastManager } = usePaper({ blank: fresh, toForm, load, initialNo, docKey: "doc" });
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && form.status === "DRAFT";
  const none = { receipt_id: "", order_id: "", fabric_type: "", colour: "", fabric_details: "", item_code: "", lot_no: "", tag_batch_no: "", shade_no: "", dia_type: "", fin_gsm: "", fin_dia: "", grey_qty: "", finish_qty: "", finish_len: "", rolls: "", rate: "", aop: false, remarks: "" };
  const e = entry ?? none;
  const dias = ["OPEN", "TUBE"].map((v) => ({ value: v, label: dia(v) }));

  const pickCard = (v) => {
    const k = options.cards.find((x) => x.value === v);
    return k ? { order_id: k.order_id ?? "", colour: str(k.colour), lot_no: str(k.lot_no), tag_batch_no: str(k.batch_no), dia_type: k.dia_type ?? "", grey_qty: str(k.qty), finish_qty: str(k.qty), rolls: str(k.rolls) } : {};
  };

  function add() {
    if (!e.receipt_id || !(Number(e.grey_qty) > 0) || !(Number(e.finish_qty) > 0)) return warn(c("delivery.line_incomplete"));
    setForm((f) => ({ ...f, lines: [...f.lines, { ...e, batch: labelOf(options.cards, e.receipt_id), order_no: labelOf(options.orders, e.order_id).split(" [")[0], dia_label: dia(e.dia_type) }] }));
    setEntry(null);
  }

  function submit() {
    startTransition(async () => {
      const res = await save(form.ref, {
        receive_date: blank(form.receive_date),
        store_id: blank(form.store_id),
        remarks: blank(form.remarks),
        lines: form.lines.map((l) => ({
          receipt_id: l.receipt_id,
          order_id: blank(l.order_id),
          fabric_type: blank(l.fabric_type),
          colour: blank(l.colour),
          fabric_details: blank(l.fabric_details),
          item_code: blank(l.item_code),
          lot_no: blank(l.lot_no),
          tag_batch_no: blank(l.tag_batch_no),
          shade_no: blank(l.shade_no),
          dia_type: blank(l.dia_type),
          fin_gsm: num(l.fin_gsm),
          fin_dia: num(l.fin_dia),
          grey_qty: num(l.grey_qty),
          finish_qty: num(l.finish_qty),
          finish_len: num(l.finish_len),
          rolls: num(l.rolls),
          rate: num(l.rate),
          aop: Boolean(l.aop),
          remarks: blank(l.remarks),
        })),
      });
      if (res?.status === "success") {
        setForm(toForm(res.doc));
        toastManager.add({ title: c("delivery.saved", { no: res.doc.delivery_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  const grey = form.lines.reduce((s, l) => s + (Number(l.grey_qty) || 0), 0);
  const finish = form.lines.reduce((s, l) => s + (Number(l.finish_qty) || 0), 0);
  const finishLen = form.lines.reduce((s, l) => s + (Number(l.finish_len) || 0), 0);

  return (
    <div className="flex flex-col gap-4">
      <Head
        c={c}
        kind="delivery"
        icon={PackageCheck}
        form={form}
        badge={form.status !== "DRAFT" ? c(`opt.delivery_status.${form.status}`) : form.ref ? c("ed.editing") : c("ed.new_badge")}
        existing={deliveries.map((x) => ({ value: x.delivery_no, label: `${x.delivery_no} · ${x.order_no ?? "—"} · ${c(`opt.delivery_status.${x.status}`)}` }))}
        onPick={(v) => (v ? open(v) : (setForm(fresh()), setEntry(null)))}
      >
        <Group legend={c("delivery.master")}>
          <Field label={c("delivery.date")} required>
            <Input type="date" value={form.receive_date} onChange={(ev) => set("receive_date", ev.target.value)} disabled={!editable} />
          </Field>
          <Field label={c("f.company")} required>
            <Select value={form.company} onValueChange={(v) => set("company", v ?? "")} options={options.companies} placeholder={c("f.select_company")} disabled={!editable} />
          </Field>
          <Field label={c("delivery.store")} required>
            <Select value={form.store_id} onValueChange={(v) => set("store_id", v ?? "")} options={options.stores} placeholder={c("f.select_store")} disabled={!editable} />
          </Field>
        </Group>
        <Group legend={c("delivery.remarks_section")}>
          <Field label={c("f.remarks")} className="sm:col-span-3">
            <Textarea value={form.remarks} onChange={(ev) => set("remarks", ev.target.value)} rows={2} disabled={!editable} />
          </Field>
        </Group>
      </Head>
      <LinesCard
        c={c}
        title={c("delivery.lines")}
        hint={c("delivery.lines_hint")}
        cols={[
          { key: "receipt_id", label: c("f.batch_no"), type: "item", lineType: "readonly", labelKey: "batch", options: options.cards, placeholder: c("f.select_batch"), width: "w-72", onPick: pickCard },
          { key: "order_id", label: c("f.order"), type: "item", lineType: "readonly", labelKey: "order_no", options: options.orders, placeholder: c("f.select_order") },
          { key: "fabric_type", label: c("f.fabric_type"), type: "item", options: options.fabric_types, placeholder: c("f.select_ft"), width: "w-44" },
          { key: "colour", label: c("f.colour"), type: "text", max: 120 },
          { key: "fabric_details", label: c("f.fabric_details"), type: "text" },
          { key: "item_code", label: c("f.item_code"), type: "text", max: 40 },
          { key: "lot_no", label: c("f.lot_no"), type: "text", max: 40 },
          { key: "tag_batch_no", label: c("f.tag_batch_no"), type: "text", max: 40 },
          { key: "shade_no", label: c("f.shade_no"), type: "text", max: 40 },
          { key: "dia_type", label: c("f.dia_type"), type: "item", lineType: "readonly", labelKey: "dia_label", options: dias, placeholder: c("f.select_dia") },
          { key: "fin_gsm", label: c("f.fin_gsm"), type: "number" },
          { key: "fin_dia", label: c("f.fin_dia"), type: "number" },
          { key: "grey_qty", label: c("f.grey_qty"), type: "number" },
          { key: "finish_qty", label: c("f.finish_qty"), type: "number" },
          { key: "finish_len", label: c("f.finish_len", { u: unitWord }), type: "number" },
          { key: "rolls", label: c("f.rolls"), type: "number" },
          { key: "rate", label: c("f.rate"), type: "number" },
          { key: "aop", label: c("f.aop"), type: "check" },
          { key: "remarks", label: c("f.line_remarks"), type: "text" },
        ]}
        entry={e}
        setEntry={setEntry}
        lines={form.lines}
        setLines={(lines) => setForm((f) => ({ ...f, lines }))}
        editable={editable}
        onAdd={add}
        emptyHint={c("delivery.no_lines_hint")}
      />
      {form.lines.length ? <p className="px-1 text-[13px] text-foreground-muted tabular-nums">{c("delivery.totals", { lots: form.lines.length, grey: kg(grey), finish: kg(finish) })}{finishLen ? ` · ${c("delivery.len_total", { len: lenText(finishLen), u: unitWord })}` : ""}</p> : null}
      <EditorFoot c={c} backHref={backHref} note={editable || !form.ref ? c("delivery.footer") : c("delivery.footer_locked")} editable={editable} pending={pending} onClear={() => (setForm(fresh()), setEntry(null))} onSave={submit} saveLabel={c("delivery.save")} />
    </div>
  );
}

/**
 * Finish Fabric Issue to Cutting: the requisition's header and status, then
 * one line per lot the store holds — what cutting asked for and what the
 * store released, never beyond the lot's balance.
 *
 * @param {{ requisitions: object[], options: object, canManage: boolean, load: Function, save: Function, backHref: string, initialNo?: string }} props
 */
function FinCutRequisitionEditor({ requisitions, options, canManage, load, save, backHref, initialNo = null }) {
  const t = useT("finishing");
  const unitWord = t(`store.len.${options.len_unit ?? "YDS"}`);
  const router = useRouter();
  const c = (k, p) => (k === "ed.add_item" ? t("store.req.add") : t(`store.${k}`, p));
  const fresh = () => ({ ref: null, status: "", saved_status: "DRAFT", req_no: "", req_date: today(), company: options.companies[0]?.value ?? "", cut_prog_ref: "", store_id: "", buyer_id: "", style: "", colour: "", batch_no: "", action: "", remarks: "", lines: [] });
  const toForm = (x) => ({
    ...Object.fromEntries(["req_no", "req_date", "cut_prog_ref", "store_id", "buyer_id", "style", "colour", "batch_no", "action", "status", "remarks"].map((k) => [k, str(x[k])])),
    ref: x.req_no,
    saved_status: x.status,
    company: options.companies[0]?.value ?? "",
    lines: x.lines.map((l) => ({ ...l, ...Object.fromEntries(["description", "lot_no", "batch_no", "req_qty", "issue_qty", "req_len", "issue_len", "rolls", "remarks"].map((k) => [k, str(l[k])])) })),
  });
  const { form, setForm, entry, setEntry, pending, startTransition, warn, open, toastManager } = usePaper({ blank: fresh, toForm, load, initialNo, docKey: "doc" });
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const editable = canManage && !["COMPLETE", "CANCELLED"].includes(form.saved_status);
  const none = { lot_id: "", description: "", lot_no: "", batch_no: "", req_len: "", issue_len: "", req_qty: "", issue_qty: "", rolls: "", remarks: "" };
  const e = entry ?? none;
  const lots = options.lots.filter((l) => !form.store_id || l.store_id === form.store_id);
  const statuses = ["DRAFT", "PARTIAL", "COMPLETE", "CANCELLED"].map((v) => ({ value: v, label: c(`opt.req_status.${v}`) }));

  const pickLot = (v) => {
    const l = options.lots.find((x) => x.value === v);
    // A lot kept by length asks for its length; the kilos follow on the server.
    return l ? { description: str(l.description), lot_no: str(l.lot_no), batch_no: str(l.batch_no), req_len: str(l.balance_len), req_qty: l.balance_len === null || l.balance_len === undefined ? str(l.balance_kg) : "" } : {};
  };

  function add() {
    if (!e.lot_id || !(Number(e.req_len) > 0 || Number(e.req_qty) > 0)) return warn(c("req.line_incomplete"));
    setForm((f) => ({ ...f, lines: [...f.lines, { ...e, lot: labelOf(options.lots, e.lot_id) }] }));
    setEntry(null);
  }

  function submit() {
    startTransition(async () => {
      const res = await save(form.ref, {
        req_no: blank(form.req_no),
        req_date: blank(form.req_date),
        cut_prog_ref: blank(form.cut_prog_ref),
        store_id: blank(form.store_id),
        buyer_id: blank(form.buyer_id),
        style: blank(form.style),
        colour: blank(form.colour),
        batch_no: blank(form.batch_no),
        action: blank(form.action),
        status: blank(form.status),
        remarks: blank(form.remarks),
        lines: form.lines.map((l) => ({ lot_id: l.lot_id, description: blank(l.description), lot_no: blank(l.lot_no), batch_no: blank(l.batch_no), req_len: num(l.req_len), issue_len: num(l.issue_len), req_qty: num(l.req_qty), issue_qty: num(l.issue_qty) ?? (num(l.issue_len) ? null : 0), rolls: num(l.rolls), remarks: blank(l.remarks) })),
      });
      if (res?.status === "success") {
        setForm(toForm(res.doc));
        toastManager.add({ title: c("req.saved", { no: res.doc.req_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  const asked = form.lines.reduce((s, l) => s + (Number(l.req_qty) || 0), 0);
  const issued = form.lines.reduce((s, l) => s + (Number(l.issue_qty) || 0), 0);
  const askedLen = form.lines.reduce((s, l) => s + (Number(l.req_len) || 0), 0);
  const issuedLen = form.lines.reduce((s, l) => s + (Number(l.issue_len) || 0), 0);

  return (
    <div className="flex flex-col gap-4">
      <Head
        c={c}
        kind="req"
        icon={Scissors}
        form={form}
        badge={form.ref ? c(`opt.req_status.${form.saved_status}`) : c("ed.new_badge")}
        existing={requisitions.map((x) => ({ value: x.req_no, label: `${x.req_no} · ${x.store ?? ""} · ${c(`opt.req_status.${x.status}`)}` }))}
        onPick={(v) => (v ? open(v) : (setForm(fresh()), setEntry(null)))}
      >
        <div className="grid gap-4 sm:grid-cols-3">
          <Field label={c("req.date")} required>
            <Input type="date" value={form.req_date} onChange={(ev) => set("req_date", ev.target.value)} disabled={!editable} />
          </Field>
          <Field label={c("req.no")} required>
            <Input value={form.req_no} onChange={(ev) => set("req_no", ev.target.value)} maxLength={40} disabled={!editable} />
          </Field>
          <Field label={c("req.unit")} required>
            <Select value={form.company} onValueChange={(v) => set("company", v ?? "")} options={options.companies} placeholder={c("req.select_unit")} disabled={!editable} />
          </Field>
          <Field label={c("req.cut_prog_ref")}>
            <Input value={form.cut_prog_ref} onChange={(ev) => set("cut_prog_ref", ev.target.value)} maxLength={40} disabled={!editable} />
          </Field>
          <Field label={c("f.store")} required>
            <Select value={form.store_id} onValueChange={(v) => setForm((f) => ({ ...f, store_id: v ?? "", lines: v === f.store_id ? f.lines : [] }))} options={options.stores} placeholder={c("f.select_store")} disabled={!editable} />
          </Field>
          <Field label={c("f.buyer")}>
            <Select value={form.buyer_id} onValueChange={(v) => set("buyer_id", v ?? "")} options={[{ value: "", label: c("f.select_buyer") }, ...options.buyers]} placeholder={c("f.select_buyer")} disabled={!editable} />
          </Field>
          <Field label={c("f.work_style")}>
            <Input value={form.style} onChange={(ev) => set("style", ev.target.value)} maxLength={120} disabled={!editable} />
          </Field>
          <Field label={c("f.colour")}>
            <Input value={form.colour} onChange={(ev) => set("colour", ev.target.value)} maxLength={120} disabled={!editable} />
          </Field>
          <Field label={c("f.batch_no")}>
            <Input value={form.batch_no} onChange={(ev) => set("batch_no", ev.target.value)} maxLength={40} disabled={!editable} />
          </Field>
          <Field label={c("req.action")}>
            <Input value={form.action} onChange={(ev) => set("action", ev.target.value)} maxLength={120} disabled={!editable} />
          </Field>
          <Field label={c("f.status")} required hint={c("req.status_hint")}>
            <Select value={form.status} onValueChange={(v) => set("status", v ?? "")} options={statuses} placeholder={c("f.select_dia")} disabled={!editable} />
          </Field>
          <Field label={c("f.remarks")} className="sm:col-span-3">
            <Textarea value={form.remarks} onChange={(ev) => set("remarks", ev.target.value)} rows={2} disabled={!editable} />
          </Field>
        </div>
      </Head>
      <LinesCard
        c={c}
        title={c("req.lines")}
        hint={c("req.lines_hint")}
        cols={[
          { key: "lot_id", label: c("req.lot"), type: "item", lineType: "readonly", labelKey: "lot", options: lots, placeholder: c("req.select_lot"), width: "w-80", onPick: pickLot },
          { key: "description", label: c("f.description"), type: "text" },
          { key: "lot_no", label: c("f.lot_no"), type: "text", max: 40 },
          { key: "batch_no", label: c("f.batch_no"), type: "text", max: 40 },
          { key: "req_len", label: c("req.req_len", { u: unitWord }), type: "number" },
          { key: "issue_len", label: c("req.issue_len", { u: unitWord }), type: "number" },
          { key: "req_qty", label: c("req.req_qty"), type: "number" },
          { key: "issue_qty", label: c("req.issue_qty"), type: "number" },
          { key: "rolls", label: c("f.rolls"), type: "number" },
          { key: "remarks", label: c("f.line_remarks"), type: "text" },
        ]}
        entry={e}
        setEntry={setEntry}
        lines={form.lines}
        setLines={(lines) => setForm((f) => ({ ...f, lines }))}
        editable={editable}
        onAdd={add}
        emptyHint={c("req.no_lines_hint")}
      />
      {form.lines.length ? <p className="px-1 text-[13px] text-foreground-muted tabular-nums">{c("req.totals", { asked: kg(asked), issued: kg(issued) })}{askedLen ? ` · ${c("req.len_totals", { asked: lenText(askedLen), issued: lenText(issuedLen), u: unitWord })}` : ""}</p> : null}
      <EditorFoot c={c} backHref={backHref} note={editable ? c("req.footer") : c("req.footer_locked")} editable={editable} pending={pending} onClear={() => (setForm(fresh()), setEntry(null))} onSave={submit} saveLabel={c("req.save")} />
    </div>
  );
}

export { FinStoreDeliveryEditor, FinCutRequisitionEditor };
