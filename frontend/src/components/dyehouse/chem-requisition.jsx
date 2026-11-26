"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { ArrowLeft, FilePlus2, Layers, Plus, RotateCcw, Save, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { buttonVariants } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { RecordsGrid } from "@/components/erp/records-grid";
import { UomSelect } from "@/components/erp/uom-select";
import { useT } from "@/lib/i18n";

const KINDS = ["BATCH_PROGRAM", "MC_WASH", "FINISHING", "REPROCESS"];
const PROCESS = ["RE_MATCHING", "RE_WASH", "RE_DYEING", "STRIPPING", "SOFTENER"];
const today = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" });
const qty = (v) => Number(v || 0).toLocaleString("en-US", { minimumFractionDigits: 3, maximumFractionDigits: 3 });
const noEntry = { item_id: "", required_qty: "", uom: "", remarks: "" };

/** A requisition as the server returns it, shaped for the form. */
const toForm = (r) => ({
  req_no: r.req_no,
  req_for: r.req_for,
  req_date: r.req_date,
  req_type: r.req_type ?? "",
  process_type: r.process_type ?? "",
  party_id: r.party_id ?? "",
  store_id: r.store_id ?? "",
  batch_id: r.batch_id ?? "",
  machine_id: r.machine_id ?? "",
  colour_id: r.colour_id ?? "",
  buyer_id: r.buyer_id ?? "",
  order_id: r.order_id ?? "",
  remarks: r.remarks ?? "",
  status: r.status,
  approval: r.approval,
  lines: r.lines.map((l) => ({ item_id: l.item_id, item: l.item, required_qty: String(l.required_qty), uom: l.uom ?? "", remarks: l.remarks ?? "" })),
});

/**
 * Dye House → Requisition (AnnotechERP 17/05): a dye and chemical requisition
 * as Brickland lays it out — an existing one to load back or a new one; the
 * Requisition (number issued on save, date, the store's wording, company and
 * sub-store; on the Print desk what it is raised for, and the process type of
 * a re-process); What it is for (batch — which names its machine, shade,
 * buyer and order — or those picked by hand); and the Dyes & Chemicals, one
 * line per item entered and Added under it, the total their sum. A
 * requisition the store has issued, or a re-process the desk has decided,
 * reads only.
 *
 * @param {{ kind?: string, requisitions: object[], options: object, canManage: boolean, load: Function, save: Function, backHref: string, initialNo?: string }} props
 */
function ChemRequisitionEditor({ kind = null, requisitions, options, canManage, load, save, backHref, initialNo = null }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const toastManager = useToastManager();
  const r = (k, p) => t(`req.${k}`, p);
  const blank = () => ({ req_no: "", req_for: kind ?? "", req_date: today(), req_type: kind ? r(`reqtype.${kind}`) : "", process_type: "", party_id: "", store_id: "", batch_id: "", machine_id: "", colour_id: "", buyer_id: "", order_id: "", remarks: "", status: "WAITING", approval: "", lines: [] });
  const [form, setForm] = useState(blank);
  const [entry, setEntry] = useState(noEntry);
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
        setForm(toForm(res.requisition));
        setEntry(noEntry);
      } else warn(res?.message);
    });
  }

  const set = (field, value) => setForm((f) => ({ ...f, [field]: value }));
  const setLine = (i, patch) => setForm((f) => ({ ...f, lines: f.lines.map((l, j) => (j === i ? { ...l, ...patch } : l)) }));
  const clear = () => {
    setForm(blank());
    setEntry(noEntry);
  };
  /** A batch names its machine, shade, buyer and order. */
  function pickBatch(id) {
    const b = options.batches.find((x) => x.value === id);
    setForm((f) => ({ ...f, batch_id: id, ...(b ? { machine_id: b.machine_id ?? f.machine_id, colour_id: b.colour_id ?? f.colour_id, buyer_id: b.buyer_id ?? f.buyer_id, order_id: b.order_id ?? f.order_id } : {}) }));
  }
  function pickKind(k) {
    setForm((f) => ({ ...f, req_for: k, req_type: k ? r(`reqtype.${k}`) : "", process_type: k === "REPROCESS" ? f.process_type : "" }));
  }

  function addLine() {
    if (!entry.item_id || !(Number(entry.required_qty) > 0)) return warn(r("ed.line_incomplete"));
    const item = options.chemicals.find((x) => x.value === entry.item_id);
    setForm((f) => ({ ...f, lines: [...f.lines, { ...entry, item: item?.label ?? "" }] }));
    setEntry(noEntry);
  }

  function submit() {
    startTransition(async () => {
      const payload = {
        ...(form.req_no ? {} : { req_for: form.req_for || null }),
        req_date: form.req_date || null,
        req_type: form.req_type || null,
        process_type: form.process_type || null,
        party_id: form.party_id || null,
        store_id: form.store_id || null,
        batch_id: form.batch_id || null,
        machine_id: form.machine_id || null,
        colour_id: form.colour_id || null,
        buyer_id: form.buyer_id || null,
        order_id: form.order_id || null,
        remarks: form.remarks || null,
        lines: form.lines.map((l) => ({ item_id: l.item_id || null, required_qty: l.required_qty === "" ? null : Number(l.required_qty), uom: l.uom || null, remarks: l.remarks || null })),
      };
      const res = await save(form.req_no || null, payload);
      if (res?.status === "success") {
        setForm(toForm(res.requisition));
        toastManager.add({ title: r("ed.saved", { no: res.requisition.req_no }), type: "success" });
        router.refresh();
      } else warn(res?.errors ? Object.values(res.errors).flat()[0] : res?.message);
    });
  }

  const shownKind = form.req_for || kind;
  const kindLabel = shownKind ? r(`kind.${shownKind}`) : "";
  const locked = form.req_no && (form.status !== "WAITING" || form.approval === "REJECTED");
  const editable = canManage && !locked;
  const mine = requisitions.filter((x) => !kind || x.req_for === kind);
  const total = form.lines.reduce((s, l) => s + (Number(l.required_qty) || 0), 0);
  const ordersOf = options.orders.filter((o) => !form.buyer_id || o.buyer_id === form.buyer_id);

  const field = (label, content, { required = false, hint = null, className } = {}) => (
    <label className={cn("flex flex-col gap-1.5", className)}>
      <span className="text-[13px] font-semibold">
        {label}
        {required ? <span className="ml-0.5 text-danger">*</span> : null}
      </span>
      {content}
      {hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
    </label>
  );
  const pick = (value, onChange, list, placeholder, extra = {}) => <Select value={value} onValueChange={(v) => onChange(v ?? "")} options={[{ value: "", label: "—" }, ...list]} placeholder={placeholder} disabled={!editable} {...extra} />;
  const group = (legend, children) => (
    <fieldset className="rounded-lg border border-border p-4">
      <legend className="px-1 text-[13px] font-semibold">{legend}</legend>
      <div className="grid gap-4 sm:grid-cols-3">{children}</div>
    </fieldset>
  );

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h2 className="flex items-center gap-2 text-base font-semibold">
              <Layers className="size-4" /> {kind ? r(`tab.${{ BATCH_PROGRAM: "new_batch", MC_WASH: "new_mc", FINISHING: "new_fin", REPROCESS: "new_rp" }[kind]}`) : r("tab.raise")}
            </h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{r("ed.hint")}</p>
          </div>
          <span className="shrink-0 rounded-full bg-brand-subtle px-2.5 py-0.5 text-xs font-semibold text-brand">{form.req_no ? r("ed.editing") : r("ed.new_badge")}</span>
        </div>
        {field(
          kind ? r("ed.existing", { kind: kindLabel }) : r("ed.existing_any"),
          <Select
            value={form.req_no}
            onValueChange={(v) => (v ? open(v) : clear())}
            options={[{ value: "", label: kind ? r("ed.new_option", { kind: kindLabel }) : r("ed.new_any") }, ...mine.map((x) => ({ value: x.req_no, label: `${x.req_no} · ${x.req_date} · ${r(`opt.status.${x.status}`)}` }))]}
            placeholder={kind ? r("ed.new_option", { kind: kindLabel }) : r("ed.new_any")}
          />,
          { hint: r("ed.existing_hint") },
        )}
        {locked ? <p className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{r("ed.closed_hint", { no: form.req_no, status: form.approval === "REJECTED" ? r("opt.approval.REJECTED") : r(`opt.status.${form.status}`) })}</p> : null}
        {group(
          r("ed.section_req"),
          <>
            {field(r("ed.req_no"), <Input value={form.req_no} placeholder={r("ed.req_no_ph")} readOnly />, { hint: r("ed.req_no_hint") })}
            {field(r("ed.req_date"), <Input type="date" value={form.req_date} onChange={(e) => set("req_date", e.target.value)} disabled={!editable} />, { required: true })}
            {!kind
              ? field(r("ed.raised_for"), <Select value={form.req_for} onValueChange={(v) => pickKind(v ?? "")} options={KINDS.map((k) => ({ value: k, label: r(`kind.${k}`) }))} placeholder={r("ed.select")} disabled={!editable || Boolean(form.req_no)} />, {
                  required: true,
                  hint: r("ed.raised_for_hint"),
                })
              : null}
            {field(r("ed.req_type"), <Input value={form.req_type} onChange={(e) => set("req_type", e.target.value)} maxLength={120} disabled={!editable} />, { required: true, hint: r("ed.req_type_hint") })}
            {shownKind === "REPROCESS" || !kind
              ? field(
                  r("ed.process_type"),
                  <Select value={form.process_type} onValueChange={(v) => set("process_type", v ?? "")} options={PROCESS.map((p) => ({ value: p, label: r(`opt.process.${p}`) }))} placeholder={r("ed.select")} disabled={!editable || shownKind !== "REPROCESS"} />,
                  { required: shownKind === "REPROCESS", hint: r("ed.process_hint") },
                )
              : null}
            {field(r("ed.company"), pick(form.party_id, (v) => set("party_id", v), options.companies, r("ed.select_company")))}
            {field(r("ed.store"), <Select value={form.store_id} onValueChange={(v) => set("store_id", v ?? "")} options={options.stores} placeholder={r("ed.select_store")} disabled={!editable} />, { required: true, hint: r("ed.store_hint") })}
          </>,
        )}
        {group(
          r("ed.section_for"),
          <>
            {field(r("ed.batch"), pick(form.batch_id, pickBatch, options.batches.map((b) => ({ value: b.value, label: `${b.label} · ${b.qty} kg${b.colour ? ` · ${b.colour}` : ""}` })), r("ed.select_batch")), { className: "sm:col-span-3", hint: options.batches.length ? null : r("ed.no_choices") })}
            {field(r("ed.machine"), pick(form.machine_id, (v) => set("machine_id", v), options.machines, r("ed.select_machine")), { hint: options.machines.length ? null : r("ed.no_choices") })}
            {field(r("ed.colour"), pick(form.colour_id, (v) => set("colour_id", v), options.colours, r("ed.select_colour")))}
            {field(r("ed.buyer"), pick(form.buyer_id, (v) => set("buyer_id", v), options.buyers, r("ed.select_buyer")))}
            {field(r("ed.order"), pick(form.order_id, (v) => set("order_id", v), ordersOf, r("ed.select_style")))}
            {field(r("ed.remarks"), <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={3} disabled={!editable} />, { className: "sm:col-span-3" })}
          </>,
        )}
      </div>

      <div className="rounded-lg border border-border bg-surface p-5">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0 max-w-2xl flex-1">
            <h3 className="text-base font-semibold">{r("ed.section_lines")}</h3>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{r("ed.lines_hint")}</p>
          </div>
          {editable ? (
            <div className="flex shrink-0 gap-2">
              <Button variant="outline" size="sm" onClick={addLine}>
                <Plus /> {r("ed.add_item")}
              </Button>
              <Button variant="outline" size="sm" onClick={() => setEntry(noEntry)}>
                {r("ed.cancel")}
              </Button>
            </div>
          ) : null}
        </div>
        {editable ? (
          <div className="mt-3 grid gap-4 sm:grid-cols-3">
            {field(r("ed.item"), <Select value={entry.item_id} onValueChange={(v) => setEntry((x) => ({ ...x, item_id: v ?? "" }))} options={options.chemicals} placeholder={r("ed.select_item")} />)}
            {field(r("ed.required_qty"), <Input type="number" min="0" step="0.001" value={entry.required_qty} onChange={(e) => setEntry((x) => ({ ...x, required_qty: e.target.value }))} />)}
            {field(r("ed.unit"), <UomSelect value={entry.uom} onChange={(v) => setEntry((x) => ({ ...x, uom: v }))} aria-label={r("ed.unit")} />)}
            {field(r("ed.line_remarks"), <Input value={entry.remarks} onChange={(e) => setEntry((x) => ({ ...x, remarks: e.target.value }))} maxLength={255} />)}
          </div>
        ) : null}
        {form.lines.length === 0 ? (
          <div className="flex flex-col items-center gap-2 px-4 py-8 text-center">
            <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
              <FilePlus2 className="size-5" />
            </span>
            <p className="text-sm font-semibold text-foreground">{r("ed.no_lines")}</p>
            <p className="max-w-sm text-[13px] text-foreground-muted">{kindLabel ? r("ed.no_lines_hint", { kind: kindLabel }) : r("ed.no_lines_any")}</p>
          </div>
        ) : (
          <>
            <div className="mt-4 overflow-x-auto rounded-md border border-border">
              <table className="w-full min-w-max text-sm">
                <thead>
                  <tr className="bg-brand text-left text-brand-foreground">
                    <th className="px-3 py-2.5 font-semibold">{r("ed.item")}</th>
                    <th className="px-3 py-2.5 text-right font-semibold">{r("ed.required_qty")}</th>
                    <th className="px-3 py-2.5 font-semibold">{r("ed.unit")}</th>
                    <th className="px-3 py-2.5 font-semibold">{r("ed.line_remarks")}</th>
                    <th className="w-10 px-3 py-2.5" />
                  </tr>
                </thead>
                <tbody>
                  {form.lines.map((l, i) => (
                    <tr key={i} className="border-b border-border last:border-0">
                      <td className="px-3 py-1.5">
                        <Select
                          value={l.item_id}
                          onValueChange={(v) => v && setLine(i, { item_id: v, item: options.chemicals.find((x) => x.value === v)?.label ?? "" })}
                          options={options.chemicals.some((x) => x.value === l.item_id) ? options.chemicals : [{ value: l.item_id, label: l.item ?? l.item_id }, ...options.chemicals]}
                          placeholder="—"
                          disabled={!editable}
                          className="w-64"
                          aria-label={`${r("ed.item")} ${i + 1}`}
                        />
                      </td>
                      <td className="px-3 py-1.5">
                        <Input type="number" min="0" step="0.001" value={l.required_qty} onChange={(e) => setLine(i, { required_qty: e.target.value })} disabled={!editable} className="ml-auto w-28 text-right" aria-label={`${r("ed.required_qty")} ${i + 1}`} />
                      </td>
                      <td className="px-3 py-1.5">
                        <UomSelect value={l.uom} onChange={(v) => setLine(i, { uom: v })} disabled={!editable} compact className="w-28" aria-label={`${r("ed.unit")} ${i + 1}`} />
                      </td>
                      <td className="px-3 py-1.5">
                        <Input value={l.remarks} onChange={(e) => setLine(i, { remarks: e.target.value })} maxLength={255} disabled={!editable} className="w-56" aria-label={`${r("ed.line_remarks")} ${i + 1}`} />
                      </td>
                      <td className="px-3 py-1.5">
                        {editable ? (
                          <Button variant="ghost" size="sm" onClick={() => setForm((f) => ({ ...f, lines: f.lines.filter((_, j) => j !== i) }))} aria-label={r("ed.remove")}>
                            <Trash2 />
                          </Button>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="mt-3 flex items-center gap-2 text-[13px] text-foreground-muted">
              {r("ed.required")} <span className="rounded-md bg-surface-muted px-2 py-0.5 font-semibold text-foreground tabular-nums">{qty(total)}</span>
            </p>
          </>
        )}
      </div>

      <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface p-5">
        <Link href={backHref} className={cn(buttonVariants({ variant: "outline" }))}>
          <ArrowLeft /> {r("ed.back")}
        </Link>
        <p className="min-w-0 flex-1 text-[13px] text-foreground-muted">{shownKind === "REPROCESS" ? r("ed.footer_rp") : r("ed.footer")}</p>
        {editable ? (
          <>
            <Button variant="outline" onClick={clear} disabled={pending}>
              <RotateCcw /> {r("ed.clear")}
            </Button>
            <Button onClick={submit} loading={pending}>
              <Save /> {kind ? r("ed.save", { kind: kindLabel }) : r("ed.save_any")}
            </Button>
          </>
        ) : null}
      </div>
    </div>
  );
}

/**
 * A register whose rows carry Brickland's confirmed actions — the approval
 * desk's Approve / Reject, the Print desk's 10-Issue and Delete, Dye Batch
 * Re-Process's Back — each asking first, in Brickland's words (":no" is the
 * row's number), and refreshing the page once done. `when` keeps an action to
 * the rows whose `field` holds one of `in` (a server page cannot pass a function).
 *
 * @param {{ rows: object[], columns: object[], exportName: string, refKey: string, actions: { key: string, label: string, title: string, text: string, done: string, run: Function, when?: { field: string, in: string[] }, destructive?: boolean }[], edit?: object, remove?: Function }} props
 */
function ActionRegister({ rows, columns, exportName, refKey, actions, edit, remove }) {
  const router = useRouter();
  const toastManager = useToastManager();
  const t = useT("dyehouse");
  const [asking, setAsking] = useState(null);
  const [pending, startTransition] = useTransition();
  const fill = (text, row) => text.replaceAll(":no", row[refKey] ?? "");

  function go() {
    const { action, row } = asking;
    startTransition(async () => {
      const res = await action.run(row[refKey]);
      setAsking(null);
      if (res?.status === "success") {
        toastManager.add({ title: fill(action.done, row), type: "success" });
        router.refresh();
      } else toastManager.add({ title: res?.errors ? Object.values(res.errors).flat()[0] : res?.message, type: "danger" });
    });
  }

  return (
    <>
      <RecordsGrid
        exportName={exportName}
        rows={rows}
        columns={columns}
        edit={edit}
        remove={remove}
        rowActions={actions.map((a) => ({ key: a.key, label: a.label, onClick: (row) => setAsking({ action: a, row }), disabled: (row) => (a.when ? !a.when.in.includes(row[a.when.field]) : false) }))}
      />
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking ? fill(asking.action.title, asking.row) : ""}
        description={asking ? fill(asking.action.text, asking.row) : ""}
        confirmLabel={asking?.action.label}
        cancelLabel={t("req.ap.cancel")}
        destructive={asking?.action.destructive ?? false}
        loading={pending}
        onConfirm={go}
      />
    </>
  );
}

export { ChemRequisitionEditor, ActionRegister };
