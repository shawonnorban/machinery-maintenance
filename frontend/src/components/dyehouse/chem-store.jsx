"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { ArrowLeft, FilePlus2, FlaskConical, PackageCheck, Plus, RotateCcw, Save, Trash2, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button, buttonVariants } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { RecordsGrid } from "@/components/erp/records-grid";
import { UomSelect } from "@/components/erp/uom-select";
import { useT } from "@/lib/i18n";

const today = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" });
const qty = (v) => Number(v || 0).toLocaleString("en-US", { minimumFractionDigits: 3, maximumFractionDigits: 3 });
const money = (v) => Number(v || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const blankText = (v) => (v === "" || v === undefined ? null : v);
const firstError = (res) => (res?.errors ? Object.values(res.errors).flat()[0] : res?.message);

/** Brickland's labelled field: the label (with * when required), the control, a hint under it. */
function Field({ label, required = false, hint = null, className, children }) {
  return (
    <label className={cn("flex flex-col gap-1.5", className)}>
      <span className="text-[13px] font-semibold">
        {label}
        {required ? <span className="ml-0.5 text-danger">*</span> : null}
      </span>
      {children}
      {hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
    </label>
  );
}

function Group({ legend, children }) {
  return (
    <fieldset className="rounded-lg border border-border p-4">
      <legend className="px-1 text-[13px] font-semibold">{legend}</legend>
      <div className="grid gap-4 sm:grid-cols-3">{children}</div>
    </fieldset>
  );
}

/** The editor's top card: title, explanation and New / Editing badge. */
function EditorHead({ title, hint, editing, c }) {
  return (
    <div className="flex items-start justify-between gap-3">
      <div>
        <h2 className="flex items-center gap-2 text-base font-semibold">
          <FlaskConical className="size-4" /> {title}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p>
      </div>
      <span className="shrink-0 rounded-full bg-brand-subtle px-2.5 py-0.5 text-xs font-semibold text-brand">{editing ? c("ed.editing") : c("ed.new_badge")}</span>
    </div>
  );
}

function NoLines({ c, hint }) {
  return (
    <div className="flex flex-col items-center gap-2 px-4 py-8 text-center">
      <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
        <FilePlus2 className="size-5" />
      </span>
      <p className="text-sm font-semibold text-foreground">{c("ed.no_lines")}</p>
      <p className="max-w-sm text-[13px] text-foreground-muted">{hint}</p>
    </div>
  );
}

/** The editor's last card: Back To List, the footnote, Clear and Save. */
function EditorFoot({ c, backHref, note, editable, pending, onClear, onSave, saveLabel }) {
  return (
    <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface p-5">
      <Link href={backHref} className={cn(buttonVariants({ variant: "outline" }))}>
        <ArrowLeft /> {c("ed.back")}
      </Link>
      <p className="min-w-0 flex-1 text-[13px] text-foreground-muted">{note}</p>
      {editable ? (
        <>
          <Button variant="outline" onClick={onClear} disabled={pending}>
            <RotateCcw /> {c("ed.clear")}
          </Button>
          <Button onClick={onSave} loading={pending}>
            <Save /> {saveLabel}
          </Button>
        </>
      ) : null}
    </div>
  );
}

/**
 * A lines card: the entry row (Add Item / Cancel) over a table of what has
 * been added, every cell editable while the paper is. `cols` names each
 * column: `{ key, label, type: "item"|"text"|"date"|"datetime"|"number"|"check"|"uom"|"readonly", options?, width? }`.
 */
function LinesCard({ c, title, hint, cols, entry, setEntry, lines, setLines, editable, onAdd, emptyHint, totalLabel, totalKey }) {
  const cell = (col, value, onChange, aria, compact = false) => {
    if (col.type === "readonly") return <span className={cn("block px-1 text-sm", col.mono && "font-mono")}>{value || "—"}</span>;
    if (col.type === "item")
      return (
        <Select
          value={value}
          onValueChange={(v) => v && onChange(v)}
          options={col.options}
          placeholder={col.placeholder ?? c("ed.select_item")}
          disabled={!editable}
          className={compact ? (col.width ?? "w-64") : undefined}
          aria-label={aria}
        />
      );
    if (col.type === "check") return <Checkbox checked={value === true || value === "1"} onCheckedChange={(v) => onChange(v === true)} disabled={!editable} aria-label={aria} />;
    if (col.type === "uom") return <UomSelect value={value} onChange={onChange} disabled={!editable} compact={compact} className={compact ? "w-28" : undefined} aria-label={aria} />;
    return (
      <Input
        type={col.type === "number" ? "number" : col.type === "date" ? "date" : col.type === "datetime" ? "datetime-local" : "text"}
        min={col.type === "number" ? "0" : undefined}
        step={col.type === "number" ? "0.001" : undefined}
        value={value ?? ""}
        onChange={(e) => onChange(e.target.value)}
        maxLength={col.type === "text" ? (col.max ?? 255) : undefined}
        placeholder={col.placeholder}
        disabled={!editable}
        className={compact ? cn(col.width ?? (col.type === "number" ? "w-28" : col.type === "date" ? "w-40" : col.type === "datetime" ? "w-52" : "w-44"), col.type === "number" && "ml-auto text-right") : undefined}
        aria-label={aria}
      />
    );
  };
  const total = totalKey ? lines.reduce((s, l) => s + (Number(l[totalKey]) || 0), 0) : null;

  return (
    <div className="rounded-lg border border-border bg-surface p-5">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0 max-w-2xl flex-1">
          <h3 className="text-base font-semibold">{title}</h3>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p>
        </div>
        {editable ? (
          <div className="flex shrink-0 gap-2">
            <Button variant="outline" size="sm" onClick={onAdd}>
              <Plus /> {c("ed.add_item")}
            </Button>
            <Button variant="outline" size="sm" onClick={() => setEntry(null)}>
              {c("ed.cancel")}
            </Button>
          </div>
        ) : null}
      </div>
      {editable ? (
        <div className="mt-3 grid gap-4 sm:grid-cols-3">
          {cols.map((col) => (
            <Field key={col.key} label={col.label}>
              {cell(col, entry[col.key], (v) => setEntry({ ...entry, [col.key]: v, ...(col.onPick ? col.onPick(v) : {}) }), col.label)}
            </Field>
          ))}
        </div>
      ) : null}
      {lines.length === 0 ? (
        <NoLines c={c} hint={emptyHint} />
      ) : (
        <>
          <div className="mt-4 overflow-x-auto rounded-md border border-border">
            <table className="w-full min-w-max text-sm">
              <thead>
                <tr className="bg-brand text-left text-brand-foreground">
                  {cols.map((col) => (
                    <th key={col.key} className={cn("px-3 py-2.5 font-semibold", col.type === "number" && "text-right")}>
                      {col.label}
                    </th>
                  ))}
                  <th className="w-10 px-3 py-2.5" />
                </tr>
              </thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={i} className="border-b border-border last:border-0">
                    {cols.map((col) => (
                      <td key={col.key} className="px-3 py-1.5">
                        {cell(col.lineType ? { ...col, type: col.lineType } : col, col.type === "item" && col.lineType === "readonly" ? l[col.labelKey] : l[col.key], (v) => setLines(lines.map((x, j) => (j === i ? { ...x, [col.key]: v, ...(col.onPick ? col.onPick(v) : {}) } : x))), `${col.label} ${i + 1}`, true)}
                      </td>
                    ))}
                    <td className="px-3 py-1.5">
                      {editable ? (
                        <Button variant="ghost" size="sm" onClick={() => setLines(lines.filter((_, j) => j !== i))} aria-label={c("ed.remove")}>
                          <Trash2 />
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {totalKey ? (
            <p className="mt-3 flex items-center gap-2 text-[13px] text-foreground-muted">
              {totalLabel} <span className="rounded-md bg-surface-muted px-2 py-0.5 font-semibold text-foreground tabular-nums">{qty(total)}</span>
            </p>
          ) : null}
        </>
      )}
    </div>
  );
}

/** The editor state every paper shares: load an existing one back, clear, add a line, save. */
function usePaper({ blank, toForm, load, initialNo, docKey }) {
  const toastManager = useToastManager();
  const [form, setForm] = useState(blank);
  const [entry, setEntryState] = useState(null);
  const [pending, startTransition] = useTransition();
  const warn = (title) => toastManager.add({ title, type: "danger" });

  useEffect(() => {
    if (initialNo) open(initialNo);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialNo]);

  function open(no) {
    startTransition(async () => {
      const res = await load(no);
      if (res?.status === "success") {
        setForm(toForm(res[docKey]));
        setEntryState(null);
      } else warn(res?.message);
    });
  }

  return { form, setForm, entry, setEntry: setEntryState, pending, startTransition, warn, open, toastManager };
}

/**
 * Daily Dye & Chemical Receipt, Dye Chemical Loan Receipt and Chemical Loan
 * Return from Party (AnnotechERP 17/06) — one editor, told apart by `kind`:
 * the supplier challan on the header (a loan coming back carries its return
 * type and who received it instead of the purchase paperwork) and the drums
 * it arrived with underneath, one line per lot. Saving books nothing in: the
 * store finalises the receipt on the register. A receipt past draft reads only.
 *
 * @param {{ kind: "PURCHASE"|"LOAN_IN"|"LOAN_BACK", receipts: object[], options: object, canManage: boolean, load: Function, save: Function, backHref: string, initialNo?: string }} props
 */
function ChemReceiptEditor({ kind, receipts, options, canManage, load, save, backHref, initialNo = null }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const c = (k, p) => t(`chem.${k}`, p);
  const blank = () => ({ doc_no: "", doc_date: today(), party_id: "", store_id: "", supplier_id: "", source: "", challan_no: "", lc_no: "", pr_no: "", po_no: "", req_type: "", pay_method: "", return_type: "", received_by: "", remarks: "", status: "DRAFT", lines: [] });
  const toForm = (r) => ({ ...blank(), ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v ?? ""])), lines: r.lines.map((l) => ({ ...l, exp_date: l.exp_date ?? "", qty: String(l.qty), rate_bdt: String(l.rate_bdt ?? ""), uom: l.uom ?? "", remarks: l.remarks ?? "" })) });
  const paper = usePaper({ blank, toForm, load, initialNo, docKey: "receipt" });
  const { form, setForm, pending, startTransition, warn } = paper;
  const noEntry = { item_id: "", lot_no: "", exp_date: "", qty: "", uom: "", rate_bdt: "", remarks: "" };
  const entry = paper.entry ?? noEntry;
  const editable = canManage && form.status === "DRAFT";
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const loan = kind !== "PURCHASE";
  const back = kind === "LOAN_BACK";
  const itemPick = (v) => {
    const it = options.chemicals.find((x) => x.value === v);
    return { item: it?.label ?? "", ...(it?.uom ? { uom: it.uom } : {}) };
  };

  function add() {
    if (!entry.item_id || !entry.lot_no.trim() || !(Number(entry.qty) > 0)) return warn(c("rc.line_incomplete"));
    setForm((f) => ({ ...f, lines: [...f.lines, { ...entry, ...itemPick(entry.item_id), uom: entry.uom || itemPick(entry.item_id).uom || "" }] }));
    paper.setEntry(null);
  }

  function submit() {
    startTransition(async () => {
      const payload = {
        ...(form.doc_no ? {} : { kind }),
        ...Object.fromEntries(["doc_date", "party_id", "store_id", "supplier_id", "source", "challan_no", "lc_no", "pr_no", "po_no", "req_type", "pay_method", "return_type", "received_by", "remarks"].map((k) => [k, blankText(form[k])])),
        lines: form.lines.map((l) => ({ item_id: l.item_id || null, lot_no: l.lot_no || null, exp_date: blankText(l.exp_date), qty: l.qty === "" ? null : Number(l.qty), uom: blankText(l.uom), rate_bdt: l.rate_bdt === "" ? null : Number(l.rate_bdt), remarks: blankText(l.remarks) })),
      };
      const res = await save(form.doc_no || null, payload);
      if (res?.status === "success") {
        setForm(toForm(res.receipt));
        paper.toastManager.add({ title: c("ed.saved", { no: res.receipt.doc_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  const mine = receipts.filter((r) => r.kind === kind);
  const k = (key) => c(`rc.${key}.${kind}`);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <EditorHead title={k("title")} hint={c("rc.hint")} editing={Boolean(form.doc_no)} c={c} />
        <Field label={k("existing")} hint={c("ed.existing_hint")}>
          <Select value={form.doc_no} onValueChange={(v) => (v ? paper.open(v) : setForm(blank()))} options={[{ value: "", label: k("new_option") }, ...mine.map((x) => ({ value: x.doc_no, label: `${x.doc_no} · ${x.doc_date} · ${c(`opt.rc_status.${x.status}`)}` }))]} placeholder={k("new_option")} />
        </Field>
        {form.doc_no && !editable ? <p className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{c("ed.locked", { no: form.doc_no, status: c(`opt.rc_status.${form.status}`) })}</p> : null}
        <Group legend={c("rc.section_head")}>
          <Field label={k("no")} hint={c("ed.no_hint")}>
            <Input value={form.doc_no} placeholder={c("ed.no_ph")} readOnly />
          </Field>
          <Field label={k("date")} required>
            <Input type="date" value={form.doc_date} onChange={(e) => set("doc_date", e.target.value)} disabled={!editable} />
          </Field>
          <Field label={c("ed.company")}>
            <Select value={form.party_id} onValueChange={(v) => set("party_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.companies]} placeholder={c("ed.select_company")} disabled={!editable} />
          </Field>
          <Field label={back ? c("rc.store_back") : c("ed.store")} required>
            <Select value={form.store_id} onValueChange={(v) => set("store_id", v ?? "")} options={options.stores} placeholder={c("ed.select_store")} disabled={!editable} />
          </Field>
          <Field label={k("supplier")} required={loan} hint={loan ? k("supplier_hint") : null}>
            <Select value={form.supplier_id} onValueChange={(v) => set("supplier_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.suppliers]} placeholder={c("ed.select_supplier")} disabled={!editable} />
          </Field>
          {back ? (
            <>
              <Field label={c("rc.return_type")} required>
                <Select value={form.return_type} onValueChange={(v) => set("return_type", v ?? "")} options={["FULL", "PARTIAL"].map((x) => ({ value: x, label: c(`opt.loan_back.${x}`) }))} placeholder={c("ed.select")} disabled={!editable} />
              </Field>
              <Field label={c("rc.received_by")}>
                <Input value={form.received_by} onChange={(e) => set("received_by", e.target.value)} maxLength={80} disabled={!editable} />
              </Field>
            </>
          ) : (
            <Field label={c("rc.source")} required hint={c("rc.source_hint")}>
              <Select value={form.source} onValueChange={(v) => set("source", v ?? "")} options={["LOCAL", "IMPORT"].map((x) => ({ value: x, label: c(`opt.source.${x}`) }))} placeholder={c("ed.select")} disabled={!editable} />
            </Field>
          )}
        </Group>
        <Group legend={c("rc.section_paper")}>
          {!back ? (
            <>
              <Field label={c("rc.challan_no")}>
                <Input value={form.challan_no} onChange={(e) => set("challan_no", e.target.value)} maxLength={60} disabled={!editable} />
              </Field>
              <Field label={c("rc.lc_no")}>
                <Input value={form.lc_no} onChange={(e) => set("lc_no", e.target.value)} maxLength={60} disabled={!editable} />
              </Field>
              {!loan ? (
                <>
                  <Field label={c("rc.pr")}>
                    <Input value={form.pr_no} onChange={(e) => set("pr_no", e.target.value)} placeholder="PRQ-2026080012" maxLength={40} disabled={!editable} />
                  </Field>
                  <Field label={c("rc.po")}>
                    <Input value={form.po_no} onChange={(e) => set("po_no", e.target.value)} placeholder="PO-2026080034" maxLength={40} disabled={!editable} />
                  </Field>
                  <Field label={c("rc.req_type")}>
                    <Input value={form.req_type} onChange={(e) => set("req_type", e.target.value)} placeholder={c("rc.req_type_ph")} maxLength={80} disabled={!editable} />
                  </Field>
                </>
              ) : null}
              <Field label={c("rc.pay_method")}>
                <Input value={form.pay_method} onChange={(e) => set("pay_method", e.target.value)} placeholder={c("rc.pay_method_ph")} maxLength={60} disabled={!editable} />
              </Field>
            </>
          ) : null}
          <Field label={c("ed.remarks")} className="sm:col-span-3">
            <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={3} disabled={!editable} />
          </Field>
        </Group>
      </div>
      <LinesCard
        c={c}
        title={k("section_lines")}
        hint={c("rc.lines_hint")}
        cols={[
          { key: "item_id", label: c("ed.item"), type: "item", options: options.chemicals, onPick: itemPick },
          { key: "lot_no", label: c("ed.lot"), type: "text", max: 40, placeholder: "1102250014" },
          { key: "exp_date", label: c("ed.exp_date"), type: "date" },
          { key: "qty", label: c("ed.qty"), type: "number" },
          { key: "uom", label: c("ed.uom"), type: "uom" },
          { key: "rate_bdt", label: c("ed.rate_bdt"), type: "number" },
          { key: "remarks", label: c("ed.line_remarks"), type: "text" },
        ]}
        entry={entry}
        setEntry={(e) => paper.setEntry(e)}
        lines={form.lines}
        setLines={(lines) => setForm((f) => ({ ...f, lines }))}
        editable={editable}
        onAdd={add}
        emptyHint={c("rc.no_lines_hint")}
        totalLabel={c("ed.total_qty")}
        totalKey="qty"
      />
      <EditorFoot c={c} backHref={backHref} note={back ? c("rc.footer_back") : c("rc.footer")} editable={editable} pending={pending} onClear={() => setForm(blank())} onSave={submit} saveLabel={k("save")} />
    </div>
  );
}

/**
 * Dye & Chemical Receipt Return (17/06): the receipt the goods came in on —
 * picking it fills the supplier and the store, because they are the
 * receipt's own — and the lots going back, each no more than is left of what
 * that receipt line brought in. Off stock once finalised on the register.
 *
 * @param {{ returns: object[], receipts: object[], options: object, canManage: boolean, load: Function, save: Function, loadSource: Function, backHref: string, initialNo?: string }} props
 */
function ChemReturnEditor({ returns, receipts, options, canManage, load, save, loadSource, backHref, initialNo = null }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const c = (k, p) => t(`chem.${k}`, p);
  const blank = () => ({ return_no: "", return_date: today(), mrr_no: "", return_type: "", party_id: "", supplier: "", store: "", remarks: "", status: "DRAFT", lines: [] });
  const toForm = (r) => ({ ...blank(), ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v ?? ""])), lines: r.lines.map((l) => ({ ...l, qty: String(l.qty), remarks: l.remarks ?? "" })) });
  const paper = usePaper({ blank, toForm, load, initialNo, docKey: "return" });
  const { form, setForm, pending, startTransition, warn } = paper;
  const [source, setSource] = useState(null);
  const noEntry = { receipt_line_id: "", qty: "", remarks: "", lot_no: "", uom: "" };
  const entry = paper.entry ?? noEntry;
  const editable = canManage && form.status === "DRAFT";
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  // The receipt's lots, for the line picker, once the receipt is known.
  useEffect(() => {
    if (!form.mrr_no) return;
    let live = true;
    loadSource(form.mrr_no, form.return_no || null).then((res) => {
      if (live && res?.status === "success") setSource(res.source);
    });
    return () => {
      live = false;
    };
  }, [form.mrr_no, form.return_no, loadSource]);

  function pickMrr(no) {
    setSource(null);
    const r = receipts.find((x) => x.doc_no === no);
    setForm((f) => ({ ...f, mrr_no: no, supplier: r?.supplier ?? "", store: r?.store ?? "", party_id: r?.party_id ?? f.party_id, lines: [] }));
  }
  const lineOpts = (source?.lines ?? []).map((l) => ({ value: l.id, label: `${l.item} · ${l.lot_no} · ${c("rt.left", { qty: qty(l.returnable) })}` }));
  const linePick = (id) => {
    const l = source?.lines.find((x) => x.id === id);
    return l ? { item: l.item, lot_no: l.lot_no, uom: l.uom ?? "" } : {};
  };

  function add() {
    if (!entry.receipt_line_id || !(Number(entry.qty) > 0)) return warn(c("rt.line_incomplete"));
    setForm((f) => ({ ...f, lines: [...f.lines, { ...entry, ...linePick(entry.receipt_line_id) }] }));
    paper.setEntry(null);
  }

  function submit() {
    startTransition(async () => {
      const payload = {
        return_date: blankText(form.return_date),
        mrr_no: blankText(form.mrr_no),
        return_type: blankText(form.return_type),
        party_id: blankText(form.party_id),
        remarks: blankText(form.remarks),
        lines: form.lines.map((l) => ({ receipt_line_id: l.receipt_line_id, qty: l.qty === "" ? null : Number(l.qty), remarks: blankText(l.remarks) })),
      };
      const res = await save(form.return_no || null, payload);
      if (res?.status === "success") {
        setForm(toForm(res.return));
        paper.toastManager.add({ title: c("ed.saved", { no: res.return.return_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <EditorHead title={c("rt.title")} hint={c("rt.hint")} editing={Boolean(form.return_no)} c={c} />
        <Field label={c("rt.existing")} hint={c("ed.existing_hint")}>
          <Select value={form.return_no} onValueChange={(v) => (v ? paper.open(v) : setForm(blank()))} options={[{ value: "", label: c("rt.new_option") }, ...returns.map((x) => ({ value: x.return_no, label: `${x.return_no} · ${x.return_date} · ${c(`opt.rt_status.${x.status}`)}` }))]} placeholder={c("rt.new_option")} />
        </Field>
        {form.return_no && !editable ? <p className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{c("ed.locked", { no: form.return_no, status: c(`opt.rt_status.${form.status}`) })}</p> : null}
        <Group legend={c("rt.section_head")}>
          <Field label={c("rt.no")} hint={c("ed.no_hint")}>
            <Input value={form.return_no} placeholder={c("ed.no_ph")} readOnly />
          </Field>
          <Field label={c("rt.date")} required>
            <Input type="date" value={form.return_date} onChange={(e) => set("return_date", e.target.value)} disabled={!editable} />
          </Field>
          <Field label={c("rt.mrr")} required hint={receipts.length ? c("rt.mrr_hint") : c("rt.no_mrr")}>
            <Select value={form.mrr_no} onValueChange={(v) => pickMrr(v ?? "")} options={receipts.map((r) => ({ value: r.doc_no, label: `${r.doc_no} · ${r.doc_date}${r.supplier ? ` · ${r.supplier}` : ""}` }))} placeholder={c("rt.select_mrr")} disabled={!editable || form.lines.length > 0} />
          </Field>
          <Field label={c("rt.type")} required>
            <Select value={form.return_type} onValueChange={(v) => set("return_type", v ?? "")} options={["SUPPLIER", "DAMAGE", "EXCESS", "WRONG_ITEM"].map((x) => ({ value: x, label: c(`opt.rt_type.${x}`) }))} placeholder={c("rt.select_type")} disabled={!editable} />
          </Field>
          <Field label={c("ed.company")}>
            <Select value={form.party_id} onValueChange={(v) => set("party_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.companies]} placeholder={c("ed.select_company")} disabled={!editable} />
          </Field>
          <Field label={c("rt.supplier")}>
            <Input value={form.supplier} placeholder={c("ed.select_supplier")} readOnly />
          </Field>
          <Field label={c("ed.store")}>
            <Input value={form.store} placeholder={c("ed.select_store")} readOnly />
          </Field>
          <Field label={c("ed.remarks")} className="sm:col-span-3">
            <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={3} disabled={!editable} />
          </Field>
        </Group>
      </div>
      <LinesCard
        c={c}
        title={c("rt.section_lines")}
        hint={c("rt.lines_hint")}
        cols={[
          { key: "receipt_line_id", labelKey: "item", label: c("ed.item"), type: "item", lineType: "readonly", options: lineOpts, placeholder: c("rt.select_line"), onPick: linePick },
          { key: "lot_no", label: c("ed.lot"), type: "readonly", mono: true },
          { key: "qty", label: c("ed.qty"), type: "number" },
          { key: "uom", label: c("ed.uom"), type: "readonly" },
          { key: "remarks", label: c("ed.line_remarks"), type: "text" },
        ]}
        entry={entry}
        setEntry={(e) => paper.setEntry(e)}
        lines={form.lines}
        setLines={(lines) => setForm((f) => ({ ...f, lines }))}
        editable={editable}
        onAdd={add}
        emptyHint={c("rt.no_lines_hint")}
        totalLabel={c("ed.total_qty")}
        totalKey="qty"
      />
      <EditorFoot c={c} backHref={backHref} note={c("rt.footer")} editable={editable} pending={pending} onClear={() => setForm(blank())} onSave={submit} saveLabel={c("rt.save")} />
    </div>
  );
}

const PARTY_KINDS = ["SALE", "LOAN_OUT", "LOAN_RETURN"];

/**
 * Every chemical issue paper (17/06), told apart by `kind`: Chemical Store
 * Transfer Issue (which store asks, which gives — the receiving store, and
 * the gate pass the security desk writes later), Print Chemical Issue Note
 * (buyer, style, print type), Machine Wash & Finishing Issue, Batch
 * Programme Chemical Issue (batch, machine, process, against a re-process),
 * Dye & Chemical Sales (Sales To and a rate per line), Chemical Loan Issue
 * Indent and Chemical Loan Return Indent (the party). The header says what
 * the chemicals are for, the grid what leaves the shelf, lot by lot or from
 * the soonest to expire. Nothing comes off stock until the store issues
 * against it on the register; once verified or issued it reads only.
 *
 * @param {{ kind: string, issues: object[], options: object, canManage: boolean, load: Function, save: Function, backHref: string, initialNo?: string }} props
 */
function ChemIssueEditor({ kind, issues, options, canManage, load, save, backHref, initialNo = null }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const c = (k, p) => t(`chem.${k}`, p);
  const blank = () => ({ issue_no: "", issue_date: today(), req_type: kind === "TRANSFER" ? c("is.transfer.CENTRAL_TO_SUB") : c(`is.reqtype.${kind}`), party_id: "", store_id: "", to_store_id: "", gate_pass: "", buyer_id: "", order_id: "", print_type: "", batch_id: "", machine_id: "", process_type: "", is_reprocess: false, supplier_id: "", remarks: "", status: kind === "TRANSFER" ? "WAITING_VERIFICATION" : "WAITING", lines: [] });
  const toForm = (r) => ({ ...blank(), ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v ?? ""])), is_reprocess: Boolean(r.is_reprocess), lines: r.lines.map((l) => ({ ...l, lot_no: l.lot_no ?? "", required_qty: String(l.required_qty), rate_bdt: l.rate_bdt === null ? "" : String(l.rate_bdt), uom: l.uom ?? "", remarks: l.remarks ?? "" })) });
  const paper = usePaper({ blank, toForm, load, initialNo, docKey: "issue" });
  const { form, setForm, pending, startTransition, warn } = paper;
  const noEntry = { item_id: "", lot_no: "", required_qty: "", uom: "", rate_bdt: "", remarks: "" };
  const entry = paper.entry ?? noEntry;
  const editable = canManage && ["WAITING", "WAITING_VERIFICATION"].includes(form.status);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const itemPick = (v) => {
    const it = options.chemicals.find((x) => x.value === v);
    return { item: it?.label ?? "", ...(it?.uom ? { uom: it.uom } : {}) };
  };
  const sale = kind === "SALE";

  function add() {
    if (!entry.item_id || !(Number(entry.required_qty) > 0) || (sale && entry.rate_bdt === "")) return warn(sale ? c("is.line_incomplete_sale") : c("is.line_incomplete"));
    setForm((f) => ({ ...f, lines: [...f.lines, { ...entry, ...itemPick(entry.item_id), uom: entry.uom || itemPick(entry.item_id).uom || "" }] }));
    paper.setEntry(null);
  }

  function submit() {
    startTransition(async () => {
      const payload = {
        ...(form.issue_no ? {} : { kind }),
        issue_date: blankText(form.issue_date),
        req_type: blankText(form.req_type),
        party_id: blankText(form.party_id),
        store_id: blankText(form.store_id),
        to_store_id: kind === "TRANSFER" ? blankText(form.to_store_id) : null,
        gate_pass: kind === "TRANSFER" ? blankText(form.gate_pass) : null,
        buyer_id: kind === "PRINT" ? blankText(form.buyer_id) : null,
        order_id: kind === "PRINT" ? blankText(form.order_id) : null,
        print_type: kind === "PRINT" ? blankText(form.print_type) : null,
        batch_id: kind === "BATCH" ? blankText(form.batch_id) : null,
        machine_id: kind === "BATCH" ? blankText(form.machine_id) : null,
        process_type: kind === "BATCH" ? blankText(form.process_type) : null,
        is_reprocess: kind === "BATCH" ? form.is_reprocess : false,
        supplier_id: PARTY_KINDS.includes(kind) ? blankText(form.supplier_id) : null,
        remarks: blankText(form.remarks),
        lines: form.lines.map((l) => ({ item_id: l.item_id || null, lot_no: blankText(l.lot_no), required_qty: l.required_qty === "" ? null : Number(l.required_qty), uom: blankText(l.uom), rate_bdt: l.rate_bdt === "" ? null : Number(l.rate_bdt), remarks: blankText(l.remarks) })),
      };
      const res = await save(form.issue_no || null, payload);
      if (res?.status === "success") {
        setForm(toForm(res.issue));
        paper.toastManager.add({ title: c("ed.saved", { no: res.issue.issue_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  const mine = issues.filter((x) => x.kind === kind);
  const ordersOf = options.orders.filter((o) => !form.buyer_id || o.buyer_id === form.buyer_id);
  const k = (key) => c(`is.${key}.${kind}`);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <EditorHead title={k("title")} hint={c("is.hint")} editing={Boolean(form.issue_no)} c={c} />
        <Field label={kind === "TRANSFER" ? c("is.existing_transfer") : c("is.existing")} hint={c("ed.existing_hint")}>
          <Select value={form.issue_no} onValueChange={(v) => (v ? paper.open(v) : setForm(blank()))} options={[{ value: "", label: kind === "TRANSFER" ? c("is.new_transfer") : c("is.new_option") }, ...mine.map((x) => ({ value: x.issue_no, label: `${x.issue_no} · ${x.issue_date} · ${c(`opt.is_status.${x.status}`)}` }))]} placeholder={kind === "TRANSFER" ? c("is.new_transfer") : c("is.new_option")} />
        </Field>
        {form.issue_no && !editable ? <p className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{c("ed.locked", { no: form.issue_no, status: c(`opt.is_status.${form.status}`) })}</p> : null}
        <Group legend={k("section")}>
          <Field label={c("is.no")} hint={c("ed.no_hint")}>
            <Input value={form.issue_no} placeholder={c("ed.no_ph")} readOnly />
          </Field>
          <Field label={c("is.date")} required>
            <Input type="date" value={form.issue_date} onChange={(e) => set("issue_date", e.target.value)} disabled={!editable} />
          </Field>
          {kind === "TRANSFER" ? (
            <Field label={c("is.req_type")} required hint={c("is.transfer_hint")}>
              <Select value={form.req_type} onValueChange={(v) => set("req_type", v ?? "")} options={["CENTRAL_TO_SUB", "SUB_TO_SUB"].map((x) => ({ value: c(`is.transfer.${x}`), label: c(`is.transfer.${x}`) }))} placeholder={c("ed.select")} disabled={!editable} />
            </Field>
          ) : (
            <Field label={c("is.req_type")} required hint={c("is.req_type_hint")}>
              <Input value={form.req_type} onChange={(e) => set("req_type", e.target.value)} maxLength={120} disabled={!editable} />
            </Field>
          )}
          <Field label={c("is.from_store")} required>
            <Select value={form.store_id} onValueChange={(v) => set("store_id", v ?? "")} options={options.stores} placeholder={c("ed.select_store")} disabled={!editable} />
          </Field>
          {kind === "TRANSFER" ? (
            <Field label={c("is.to_store")} required>
              <Select value={form.to_store_id} onValueChange={(v) => set("to_store_id", v ?? "")} options={options.stores.filter((s) => s.value !== form.store_id)} placeholder={c("ed.select_store")} disabled={!editable} />
            </Field>
          ) : null}
          <Field label={c("ed.company")}>
            <Select value={form.party_id} onValueChange={(v) => set("party_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.companies]} placeholder={c("ed.select_company")} disabled={!editable} />
          </Field>
          {kind === "TRANSFER" ? (
            <Field label={c("is.gate_pass")} hint={c("is.gate_pass_hint")}>
              <Input value={form.gate_pass} onChange={(e) => set("gate_pass", e.target.value)} maxLength={40} disabled={!editable} />
            </Field>
          ) : null}
          {kind === "PRINT" ? (
            <>
              <Field label={c("is.buyer")}>
                <Select value={form.buyer_id} onValueChange={(v) => setForm((f) => ({ ...f, buyer_id: v ?? "", order_id: "" }))} options={[{ value: "", label: "—" }, ...options.buyers]} placeholder={c("is.select_buyer")} disabled={!editable} />
              </Field>
              <Field label={c("is.style")}>
                <Select value={form.order_id} onValueChange={(v) => set("order_id", v ?? "")} options={[{ value: "", label: "—" }, ...ordersOf]} placeholder={c("is.select_style")} disabled={!editable} />
              </Field>
              <Field label={c("is.print_type")}>
                <Input value={form.print_type} onChange={(e) => set("print_type", e.target.value)} placeholder="Pigment" maxLength={60} disabled={!editable} />
              </Field>
            </>
          ) : null}
          {kind === "BATCH" ? (
            <>
              <Field label={c("is.batch")} hint={options.batches.length ? null : c("ed.no_choices")}>
                <Select
                  value={form.batch_id}
                  onValueChange={(v) => {
                    const b = options.batches.find((x) => x.value === v);
                    setForm((f) => ({ ...f, batch_id: v ?? "", machine_id: b?.machine_id ?? f.machine_id }));
                  }}
                  options={[{ value: "", label: "—" }, ...options.batches.map((b) => ({ value: b.value, label: `${b.label} · ${qty(b.qty)} kg` }))]}
                  placeholder={c("is.select_batch")}
                  disabled={!editable}
                />
              </Field>
              <Field label={c("is.machine")} hint={options.machines.length ? null : c("ed.no_choices")}>
                <Select value={form.machine_id} onValueChange={(v) => set("machine_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.machines]} placeholder={c("is.select_machine")} disabled={!editable} />
              </Field>
              <Field label={c("is.process_type")}>
                <Input value={form.process_type} onChange={(e) => set("process_type", e.target.value)} placeholder="Dyeing" maxLength={60} disabled={!editable} />
              </Field>
              <label className="flex items-start gap-2 sm:col-span-3">
                <Checkbox checked={form.is_reprocess} onCheckedChange={(v) => set("is_reprocess", v === true)} disabled={!editable} aria-label={c("is.reprocess")} />
                <span className="flex flex-col">
                  <span className="text-[13px] font-semibold">{c("is.reprocess")}</span>
                  <span className="text-xs text-foreground-muted">{c("is.reprocess_hint")}</span>
                </span>
              </label>
            </>
          ) : null}
          {PARTY_KINDS.includes(kind) ? (
            <Field label={k("party")} required hint={k("party_hint")}>
              <Select value={form.supplier_id} onValueChange={(v) => set("supplier_id", v ?? "")} options={options.suppliers} placeholder={c("is.select_party")} disabled={!editable} />
            </Field>
          ) : null}
          <Field label={c("ed.remarks")} className="sm:col-span-3">
            <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={3} disabled={!editable} />
          </Field>
        </Group>
      </div>
      <LinesCard
        c={c}
        title={kind === "TRANSFER" ? c("is.section_lines_transfer") : c("is.section_lines")}
        hint={c("is.lines_hint")}
        cols={[
          { key: "item_id", label: c("ed.item"), type: "item", options: options.chemicals, onPick: itemPick },
          { key: "lot_no", label: c("ed.lot"), type: "text", max: 40, placeholder: c("is.lot_ph") },
          { key: "required_qty", label: c("is.required_qty"), type: "number" },
          { key: "uom", label: c("ed.uom"), type: "uom" },
          ...(sale ? [{ key: "rate_bdt", label: c("ed.rate_bdt"), type: "number" }] : []),
          { key: "remarks", label: c("ed.line_remarks"), type: "text" },
        ]}
        entry={entry}
        setEntry={(e) => paper.setEntry(e)}
        lines={form.lines}
        setLines={(lines) => setForm((f) => ({ ...f, lines }))}
        editable={editable}
        onAdd={add}
        emptyHint={c("is.no_lines_hint")}
        totalLabel={c("is.required")}
        totalKey="required_qty"
      />
      <EditorFoot c={c} backHref={backHref} note={kind === "TRANSFER" ? c("is.footer_transfer") : sale ? c("is.footer_sale") : c("is.footer")} editable={editable} pending={pending} onClear={() => setForm(blank())} onSave={submit} saveLabel={kind === "TRANSFER" ? c("is.save_transfer") : c("is.save")} />
    </div>
  );
}

/**
 * Chemical Comparative Statement (17/06): the statement (date, chemical
 * category, unit, MOU code, the dollar rate), the suppliers quoting, and per
 * item every supplier's rate side by side — in taka, or in dollars priced at
 * the statement's rate — the cheapest marked, and the supplier chosen. A
 * draft (or a rejected statement, which goes back to draft) is edited; once
 * submitted it waits on the approver.
 *
 * @param {{ statements: object[], options: object, canManage: boolean, load: Function, save: Function, backHref: string, initialNo?: string }} props
 */
function ChemCsEditor({ statements, options, canManage, load, save, backHref, initialNo = null }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const c = (k, p) => t(`chem.${k}`, p);
  const blank = () => ({ cs_no: "", cs_date: today(), category_id: "", factory_id: "", mou_code: "", usd_rate: "", remarks: "", status: "DRAFT", currency: "BDT", suppliers: [], lines: [] });
  const toForm = (r) => {
    const usd = r.lines.some((l) => l.quotes.some((q) => q.rate_usd !== null));
    return {
      ...blank(),
      ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v ?? ""])),
      usd_rate: r.usd_rate === null ? "" : String(r.usd_rate),
      currency: usd ? "USD" : "BDT",
      suppliers: r.suppliers,
      lines: r.lines.map((l) => ({ item_id: l.item_id, item: l.item, qty: String(l.qty), uom: l.uom ?? "", chosen_supplier_id: l.chosen_supplier_id ?? "", rates: Object.fromEntries(l.quotes.map((q) => [q.supplier_id, String(usd ? (q.rate_usd ?? "") : q.rate_bdt)])) })),
    };
  };
  const paper = usePaper({ blank, toForm, load, initialNo, docKey: "statement" });
  const { form, setForm, pending, startTransition, warn } = paper;
  const [adding, setAdding] = useState({ item_id: "", qty: "", uom: "" });
  const [supplierPick, setSupplierPick] = useState("");
  const editable = canManage && ["DRAFT", "REJECTED"].includes(form.status);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const setLine = (i, patch) => setForm((f) => ({ ...f, lines: f.lines.map((l, j) => (j === i ? { ...l, ...patch } : l)) }));
  const usd = form.currency === "USD";
  const bdtOf = (v) => (v === "" || v === undefined ? null : usd ? (Number(form.usd_rate) > 0 ? Number(v) * Number(form.usd_rate) : null) : Number(v));

  function addSupplier() {
    if (!supplierPick || form.suppliers.some((s) => s.value === supplierPick)) return;
    const s = options.suppliers.find((x) => x.value === supplierPick);
    setForm((f) => ({ ...f, suppliers: [...f.suppliers, { value: supplierPick, label: s?.label ?? supplierPick }] }));
    setSupplierPick("");
  }
  function dropSupplier(id) {
    setForm((f) => ({ ...f, suppliers: f.suppliers.filter((s) => s.value !== id), lines: f.lines.map((l) => ({ ...l, chosen_supplier_id: l.chosen_supplier_id === id ? "" : l.chosen_supplier_id, rates: Object.fromEntries(Object.entries(l.rates).filter(([k]) => k !== id)) })) }));
  }
  function addItem() {
    if (!adding.item_id || !(Number(adding.qty) > 0)) return warn(c("cs.line_incomplete"));
    const it = options.chemicals.find((x) => x.value === adding.item_id);
    setForm((f) => ({ ...f, lines: [...f.lines, { item_id: adding.item_id, item: it?.label ?? "", qty: adding.qty, uom: adding.uom || it?.uom || "", chosen_supplier_id: "", rates: {} }] }));
    setAdding({ item_id: "", qty: "", uom: "" });
  }

  function submit() {
    startTransition(async () => {
      const payload = {
        cs_date: blankText(form.cs_date),
        category_id: blankText(form.category_id),
        factory_id: blankText(form.factory_id),
        mou_code: blankText(form.mou_code),
        usd_rate: form.usd_rate === "" ? null : Number(form.usd_rate),
        remarks: blankText(form.remarks),
        lines: form.lines.map((l) => ({
          item_id: l.item_id,
          qty: l.qty === "" ? null : Number(l.qty),
          uom: blankText(l.uom),
          chosen_supplier_id: blankText(l.chosen_supplier_id),
          quotes: form.suppliers.filter((s) => (l.rates[s.value] ?? "") !== "").map((s) => (usd ? { supplier_id: s.value, rate_usd: Number(l.rates[s.value]) } : { supplier_id: s.value, rate_bdt: Number(l.rates[s.value]) })),
        })),
      };
      const res = await save(form.cs_no || null, payload);
      if (res?.status === "success") {
        setForm(toForm(res.statement));
        paper.toastManager.add({ title: c("ed.saved", { no: res.statement.cs_no }), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <EditorHead title={c("cs.title")} hint={c("cs.hint")} editing={Boolean(form.cs_no)} c={c} />
        <Field label={c("cs.existing")} hint={c("ed.existing_hint")}>
          <Select value={form.cs_no} onValueChange={(v) => (v ? paper.open(v) : setForm(blank()))} options={[{ value: "", label: c("cs.new_option") }, ...statements.map((x) => ({ value: x.cs_no, label: `${x.cs_no} · ${x.cs_date} · ${c(`opt.cs_status.${x.status}`)}` }))]} placeholder={c("cs.new_option")} />
        </Field>
        {form.cs_no && !editable ? <p className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{c("ed.locked", { no: form.cs_no, status: c(`opt.cs_status.${form.status}`) })}</p> : null}
        {form.status === "REJECTED" && form.decision_note ? <p className="rounded-md bg-danger-subtle px-3 py-2 text-sm text-danger">{c("cs.rejected_note", { note: form.decision_note })}</p> : null}
        <Group legend={c("cs.section_head")}>
          <Field label={c("cs.no")} hint={c("ed.no_hint")}>
            <Input value={form.cs_no} placeholder={c("ed.no_ph")} readOnly />
          </Field>
          <Field label={c("cs.date")} required>
            <Input type="date" value={form.cs_date} onChange={(e) => set("cs_date", e.target.value)} disabled={!editable} />
          </Field>
          <Field label={c("cs.category")}>
            <Select value={form.category_id} onValueChange={(v) => set("category_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.categories]} placeholder={c("ed.select_category")} disabled={!editable} />
          </Field>
          <Field label={c("cs.factory")} hint={c("cs.factory_hint")}>
            <Select value={form.factory_id} onValueChange={(v) => set("factory_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.factories]} placeholder={c("ed.select")} disabled={!editable} />
          </Field>
          <Field label={c("cs.mou")} hint={c("cs.mou_hint")}>
            <Input value={form.mou_code} onChange={(e) => set("mou_code", e.target.value)} maxLength={40} disabled={!editable} />
          </Field>
          <Field label={c("cs.usd")} hint={c("cs.usd_hint")}>
            <Input type="number" min="0" step="0.01" value={form.usd_rate} onChange={(e) => set("usd_rate", e.target.value)} placeholder="123" disabled={!editable} />
          </Field>
          <Field label={c("ed.remarks")} className="sm:col-span-3">
            <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={2} disabled={!editable} />
          </Field>
        </Group>
      </div>

      <div className="rounded-lg border border-border bg-surface p-5">
        <h3 className="text-base font-semibold">{c("cs.section_quotes")}</h3>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{c("cs.quotes_hint")}</p>
        {editable ? (
          <div className="mt-3 grid items-end gap-4 sm:grid-cols-4">
            <Field label={c("cs.supplier")} className="sm:col-span-2">
              <Select value={supplierPick} onValueChange={(v) => setSupplierPick(v ?? "")} options={options.suppliers.filter((s) => !form.suppliers.some((x) => x.value === s.value))} placeholder={c("ed.select_supplier")} />
            </Field>
            <Button variant="outline" onClick={addSupplier} disabled={!supplierPick}>
              <Plus /> {c("cs.add_supplier")}
            </Button>
            <Field label={c("cs.quoted_in")}>
              <Select value={form.currency} onValueChange={(v) => v && set("currency", v)} options={["BDT", "USD"].map((x) => ({ value: x, label: c(`cs.currency.${x}`) }))} />
            </Field>
          </div>
        ) : null}
        <div className="mt-3 flex flex-wrap gap-2">
          {form.suppliers.length === 0 ? <span className="text-[13px] text-foreground-muted">{c("cs.no_suppliers")}</span> : null}
          {form.suppliers.map((s) => (
            <span key={s.value} className="inline-flex items-center gap-1 rounded-full bg-surface-muted px-2.5 py-1 text-xs font-semibold">
              {s.label}
              {editable ? (
                <button type="button" onClick={() => dropSupplier(s.value)} aria-label={c("cs.drop_supplier", { name: s.label })} className="text-foreground-muted hover:text-danger">
                  <X className="size-3.5" />
                </button>
              ) : null}
            </span>
          ))}
        </div>
      </div>

      <div className="rounded-lg border border-border bg-surface p-5">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0 max-w-2xl flex-1">
            <h3 className="text-base font-semibold">{c("cs.section_items")}</h3>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{c("cs.items_hint")}</p>
          </div>
          {editable ? (
            <Button variant="outline" size="sm" onClick={addItem}>
              <Plus /> {c("ed.add_item")}
            </Button>
          ) : null}
        </div>
        {editable ? (
          <div className="mt-3 grid gap-4 sm:grid-cols-3">
            <Field label={c("ed.item")}>
              <Select value={adding.item_id} onValueChange={(v) => setAdding((a) => ({ ...a, item_id: v ?? "", uom: options.chemicals.find((x) => x.value === v)?.uom ?? a.uom }))} options={options.chemicals.filter((x) => !form.category_id || x.category_id === form.category_id)} placeholder={c("ed.select_item")} />
            </Field>
            <Field label={c("ed.qty")}>
              <Input type="number" min="0" step="0.001" value={adding.qty} onChange={(e) => setAdding((a) => ({ ...a, qty: e.target.value }))} />
            </Field>
            <Field label={c("ed.uom")}>
              <UomSelect value={adding.uom} onChange={(v) => setAdding((a) => ({ ...a, uom: v }))} aria-label={c("ed.uom")} />
            </Field>
          </div>
        ) : null}
        {form.lines.length === 0 ? (
          <NoLines c={c} hint={c("cs.no_lines_hint")} />
        ) : (
          <div className="mt-4 overflow-x-auto rounded-md border border-border">
            <table className="w-full min-w-max text-sm">
              <thead>
                <tr className="bg-brand text-left text-brand-foreground">
                  <th className="px-3 py-2.5 font-semibold">{c("ed.item")}</th>
                  <th className="px-3 py-2.5 text-right font-semibold">{c("ed.qty")}</th>
                  <th className="px-3 py-2.5 font-semibold">{c("ed.uom")}</th>
                  {form.suppliers.map((s) => (
                    <th key={s.value} className="px-3 py-2.5 text-right font-semibold">
                      {s.label}
                      <span className="block text-[11px] font-normal opacity-80">{c(`cs.rate_in.${form.currency}`)}</span>
                    </th>
                  ))}
                  <th className="px-3 py-2.5 font-semibold">{c("cs.chosen")}</th>
                  <th className="w-10 px-3 py-2.5" />
                </tr>
              </thead>
              <tbody>
                {form.lines.map((l, i) => {
                  const priced = form.suppliers.map((s) => [s.value, bdtOf(l.rates[s.value])]).filter(([, v]) => v !== null);
                  const low = priced.length ? Math.min(...priced.map(([, v]) => v)) : null;
                  return (
                    <tr key={i} className="border-b border-border last:border-0">
                      <td className="px-3 py-1.5">{l.item}</td>
                      <td className="px-3 py-1.5">
                        <Input type="number" min="0" step="0.001" value={l.qty} onChange={(e) => setLine(i, { qty: e.target.value })} disabled={!editable} className="ml-auto w-24 text-right" aria-label={`${c("ed.qty")} ${i + 1}`} />
                      </td>
                      <td className="px-3 py-1.5">
                        <UomSelect value={l.uom} onChange={(v) => setLine(i, { uom: v })} disabled={!editable} compact className="w-24" aria-label={`${c("ed.uom")} ${i + 1}`} />
                      </td>
                      {form.suppliers.map((s) => {
                        const bdt = bdtOf(l.rates[s.value]);
                        return (
                          <td key={s.value} className={cn("px-3 py-1.5", bdt !== null && bdt === low && "bg-success-subtle")}>
                            <Input
                              type="number"
                              min="0"
                              step="0.0001"
                              value={l.rates[s.value] ?? ""}
                              onChange={(e) => setLine(i, { rates: { ...l.rates, [s.value]: e.target.value } })}
                              disabled={!editable}
                              className="ml-auto w-28 text-right"
                              aria-label={`${s.label} ${i + 1}`}
                            />
                            {usd && bdt !== null ? <span className="mt-0.5 block text-right text-[11px] text-foreground-muted">৳ {money(bdt)}</span> : null}
                          </td>
                        );
                      })}
                      <td className="px-3 py-1.5">
                        <Select
                          value={l.chosen_supplier_id}
                          onValueChange={(v) => setLine(i, { chosen_supplier_id: v ?? "" })}
                          options={[{ value: "", label: "—" }, ...form.suppliers.filter((s) => (l.rates[s.value] ?? "") !== "")]}
                          placeholder={c("cs.select_chosen")}
                          disabled={!editable}
                          className="w-52"
                          aria-label={`${c("cs.chosen")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-3 py-1.5">
                        {editable ? (
                          <Button variant="ghost" size="sm" onClick={() => setForm((f) => ({ ...f, lines: f.lines.filter((_, j) => j !== i) }))} aria-label={c("ed.remove")}>
                            <Trash2 />
                          </Button>
                        ) : null}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
      <EditorFoot c={c} backHref={backHref} note={c("cs.footer")} editable={editable} pending={pending} onClear={() => setForm(blank())} onSave={submit} saveLabel={c("cs.save")} />
    </div>
  );
}

/**
 * A chemical store register (17/06): the grid, and Brickland's row actions —
 * each asking first in Brickland's words (":no" is the row's number) and
 * refreshing once done; an `href` action opens the row's paper instead
 * (":no" filled in); `dialog: "issue"` opens the Issue sheet — every line
 * with what is asked, issued, left and in the store, and what the store gives
 * now. `when` keeps an action to the rows whose `field` holds one of `in`.
 *
 * `labelKey` names the row in the questions (":no") when the reference the action is run with is not what a person reads.
 *
 * @param {{ rows: object[], columns: object[], exportName: string, refKey: string, labelKey?: string, actions: object[], loadIssue?: Function, issueStock?: Function }} props
 */
function ChemRegister({ rows, columns, exportName, refKey, labelKey, actions, loadIssue, issueStock }) {
  const router = useRouter();
  const toastManager = useToastManager();
  const t = useT("dyehouse");
  const c = (k, p) => t(`chem.${k}`, p);
  const [asking, setAsking] = useState(null);
  const [sheet, setSheet] = useState(null);
  const [give, setGive] = useState({});
  const [date, setDate] = useState(today());
  const [gatePass, setGatePass] = useState("");
  const [pending, startTransition] = useTransition();
  const fill = (text, row) => text.replaceAll(":no", row[labelKey ?? refKey] ?? "");
  const warn = (title) => toastManager.add({ title, type: "danger" });

  function act(a, row) {
    if (a.href) return router.push(fill(a.href, row));
    if (a.dialog === "issue") {
      startTransition(async () => {
        const res = await loadIssue(row[refKey]);
        if (res?.status === "success") {
          setSheet(res.issue);
          setGive(Object.fromEntries(res.issue.lines.map((l) => [l.id, String(Math.max(0, Math.min(l.balance_qty, l.in_store)))])));
          setDate(today());
          setGatePass(res.issue.gate_pass ?? "");
        } else warn(res?.message);
      });
      return;
    }
    setAsking({ action: a, row });
  }

  function go() {
    const { action, row } = asking;
    startTransition(async () => {
      const res = await action.run(row[refKey]);
      setAsking(null);
      if (res?.status === "success") {
        toastManager.add({ title: fill(action.done, row), type: "success" });
        router.refresh();
      } else warn(firstError(res));
    });
  }

  function issueNow() {
    const lines = sheet.lines.map((l) => ({ id: l.id, qty: Number(give[l.id] || 0) })).filter((l) => l.qty > 0);
    if (!lines.length) return warn(c("dlg.nothing"));
    startTransition(async () => {
      const res = await issueStock(sheet.issue_no, { issue_date: date, gate_pass: sheet.kind === "TRANSFER" ? gatePass || null : null, lines });
      if (res?.status === "success") {
        toastManager.add({ title: c("dlg.done", { no: sheet.issue_no }), type: "success" });
        setSheet(null);
        router.refresh();
      } else warn(firstError(res));
    });
  }

  return (
    <>
      <RecordsGrid
        exportName={exportName}
        rows={rows}
        rowKey={refKey}
        columns={columns}
        rowActions={actions.map((a) => ({ key: a.key, label: a.label, onClick: (row) => act(a, row), disabled: (row) => (a.when ? !a.when.in.includes(row[a.when.field]) : false) }))}
      />
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking ? fill(asking.action.title, asking.row) : ""}
        description={asking ? fill(asking.action.text, asking.row) : ""}
        confirmLabel={asking?.action.label}
        cancelLabel={c("reg.keep")}
        destructive={asking?.action.destructive ?? false}
        loading={pending}
        onConfirm={go}
      />
      <Modal open={sheet !== null} onOpenChange={(o) => !o && setSheet(null)} title={sheet ? c("dlg.title", { no: sheet.issue_no }) : ""} description={c("dlg.hint")} className="max-h-[92vh] max-w-4xl overflow-y-auto">
        {sheet ? (
          <div className="flex flex-col gap-4 text-sm">
            <p className="text-foreground-muted">
              {sheet.store}
              {sheet.to_store ? ` → ${sheet.to_store}` : ""} · {sheet.req_type}
            </p>
            <div className="overflow-x-auto rounded-md border border-border">
              <table className="w-full min-w-max text-sm">
                <thead>
                  <tr className="bg-brand text-left text-brand-foreground">
                    <th className="px-3 py-2 font-semibold">{c("ed.item")}</th>
                    <th className="px-3 py-2 font-semibold">{c("ed.lot")}</th>
                    <th className="px-3 py-2 text-right font-semibold">{c("dlg.required")}</th>
                    <th className="px-3 py-2 text-right font-semibold">{c("dlg.issued")}</th>
                    <th className="px-3 py-2 text-right font-semibold">{c("dlg.balance")}</th>
                    <th className="px-3 py-2 text-right font-semibold">{c("dlg.in_store")}</th>
                    <th className="px-3 py-2 text-right font-semibold">{c("dlg.now")}</th>
                  </tr>
                </thead>
                <tbody>
                  {sheet.lines.map((l) => (
                    <tr key={l.id} className="border-b border-border last:border-0">
                      <td className="px-3 py-1.5">{l.item}</td>
                      <td className="px-3 py-1.5 font-mono">{l.lot_no ?? c("dlg.fefo")}</td>
                      <td className="px-3 py-1.5 text-right tabular-nums">
                        {qty(l.required_qty)} {l.uom ?? ""}
                      </td>
                      <td className="px-3 py-1.5 text-right tabular-nums">{qty(l.issued_qty)}</td>
                      <td className="px-3 py-1.5 text-right tabular-nums">{qty(l.balance_qty)}</td>
                      <td className={cn("px-3 py-1.5 text-right tabular-nums", l.in_store < l.balance_qty && "text-danger")}>{qty(l.in_store)}</td>
                      <td className="px-3 py-1.5">
                        <Input type="number" min="0" step="0.001" value={give[l.id] ?? ""} onChange={(e) => setGive((g) => ({ ...g, [l.id]: e.target.value }))} disabled={l.balance_qty <= 0} className="ml-auto w-28 text-right" aria-label={`${c("dlg.now")} ${l.item}`} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="flex flex-wrap items-end justify-end gap-3 border-t border-border pt-4">
              <label className="flex flex-col gap-1.5">
                <span className="text-[13px] font-semibold">{c("dlg.date")}</span>
                <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="w-44" />
              </label>
              {sheet.kind === "TRANSFER" ? (
                <label className="flex flex-col gap-1.5">
                  <span className="text-[13px] font-semibold">{c("is.gate_pass")}</span>
                  <Input value={gatePass} onChange={(e) => setGatePass(e.target.value)} maxLength={40} className="w-44" />
                </label>
              ) : null}
              <Button variant="outline" onClick={() => setSheet(null)} disabled={pending}>
                {c("dlg.close")}
              </Button>
              <Button onClick={issueNow} loading={pending}>
                <PackageCheck /> {c("dlg.save")}
              </Button>
            </div>
          </div>
        ) : null}
      </Modal>
    </>
  );
}

export { ChemReceiptEditor, ChemReturnEditor, ChemIssueEditor, ChemCsEditor, ChemRegister, Field, Group, EditorHead, EditorFoot, LinesCard, usePaper };
