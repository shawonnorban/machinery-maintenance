"use client";

import { useMemo, useState, useTransition } from "react";
import { ArrowLeft, ClipboardList, PackageMinus, PackagePlus, RotateCcw, Save, Search, Undo2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EditorHead, Field, Group, LinesCard, usePaper } from "@/components/dyehouse/chem-store";
import { useWords } from "@/components/assembly/finishing";

/*
 * Assembly Floor → Accessories Store (AnnotechERP 20/08), as Brickland lays
 * its three screens out: each a list tab and an entry tab that switch in
 * place — Trims & Accessories Receipt (MRR List / Daily Trims Receive: Key
 * Information, Against, Notes, Receive Detail with Add to Grid, Save MRR),
 * Trims & Accessories Issue (Issue List / Trims/Accessories Issue, with the
 * store's stock beside each item) and the Trims Receipt Return Register
 * (Return List / Receipt Return against a completed MRR). The list's row
 * actions move a paper on: Complete / Cancel, Submit / Approve / Reject.
 */

const today = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" });
const blankText = (v) => (v === "" || v === undefined ? null : v);
const firstError = (res) => (res?.errors ? Object.values(res.errors).flat()[0] : res?.message);
const qty = (v) => Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: 3 });
const has = (v, q) => !q || String(v ?? "").toLowerCase().includes(String(q).toLowerCase());
const within = (d, from, to) => (!from || d >= from) && (!to || d <= to);

/** The list / entry tabs over each screen, switching in place. */
function DeskTabs({ tab, setTab, listLabel, entryLabel, count, canManage, icon: Icon }) {
  const items = [["list", `${listLabel} ${count}`, ClipboardList], ...(canManage ? [["entry", entryLabel, Icon]] : [])];
  return (
    <div className="mb-4 flex flex-wrap gap-2" role="tablist">
      {items.map(([key, label, I]) => (
        <button
          key={key}
          type="button"
          role="tab"
          aria-selected={tab === key}
          onClick={() => setTab(key)}
          className={cn(
            "flex h-9 items-center gap-1.5 rounded-lg border px-3 text-[13px] font-semibold transition-colors",
            tab === key ? "border-brand bg-brand text-brand-foreground" : "border-border bg-surface text-foreground hover:bg-surface-muted",
          )}
        >
          <I className="size-4" /> {label}
        </button>
      ))}
    </div>
  );
}

/** A filter card over a list: the fields, Search and Refresh. */
function Filters({ w, fields, value, onChange, onSearch, onRefresh }) {
  const [draft, setDraft] = useState(value);
  return (
    <section className="mb-4 rounded-lg border border-border bg-surface p-5">
      <h3 className="mb-3 text-[15px] font-semibold">{w("ta.f.title")}</h3>
      <form
        className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        onSubmit={(e) => {
          e.preventDefault();
          onChange(draft);
          onSearch?.(draft);
        }}
      >
        {fields.map((f) => (
          <Field key={f.name} label={f.label}>
            {f.type === "select" ? (
              <Select value={draft[f.name] ?? ""} onValueChange={(v) => setDraft({ ...draft, [f.name]: v ?? "" })} options={[{ value: "", label: w("ta.f.all") }, ...f.options]} placeholder={w("ta.f.all")} />
            ) : (
              <Input type={f.type === "date" ? "date" : "text"} value={draft[f.name] ?? ""} placeholder={f.placeholder} onChange={(e) => setDraft({ ...draft, [f.name]: e.target.value })} />
            )}
          </Field>
        ))}
        <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
          <Button type="submit">
            <Search /> {w("ta.f.search")}
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => {
              setDraft({});
              onChange({});
              onRefresh?.();
            }}
          >
            <RotateCcw /> {w("ta.f.refresh")}
          </Button>
        </div>
      </form>
    </section>
  );
}

/** The editor's last card: Back To List, the footnote, Clear and Save. */
function Foot({ w, note, editable, pending, onBack, onClear, onSave, saveLabel }) {
  return (
    <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface p-5">
      <Button variant="outline" onClick={onBack}>
        <ArrowLeft /> {w("ta.ed.back")}
      </Button>
      <p className="min-w-0 flex-1 text-[13px] text-foreground-muted">{note}</p>
      {editable ? (
        <>
          <Button variant="outline" onClick={onClear} disabled={pending}>
            <RotateCcw /> {w("ta.ed.clear")}
          </Button>
          <Button onClick={onSave} loading={pending}>
            <Save /> {saveLabel}
          </Button>
        </>
      ) : null}
    </div>
  );
}

/** The register under a list: the rows, their actions, and the confirm before a step. */
function Register({ w, rows, columns, refKey, actions, exportName, onDone }) {
  const toastManager = useToastManager();
  const [asking, setAsking] = useState(null);
  const [pending, startTransition] = useTransition();
  const fill = (text, row) => text.replaceAll(":no", row[refKey] ?? "");
  const run = (action, row, extra) =>
    startTransition(async () => {
      const res = await action.run(row[refKey], extra);
      setAsking(null);
      if (res?.status === "success") {
        toastManager.add({ title: fill(action.done, row), type: "success" });
        onDone?.();
      } else toastManager.add({ title: firstError(res), type: "danger" });
    });
  const click = (a, row) => {
    if (a.open) return a.open(row);
    if (a.prompt) {
      const reason = window.prompt(fill(a.prompt, row));
      if (reason) run(a, row, { reason });
      return;
    }
    setAsking({ action: a, row });
  };
  return (
    <>
      <RecordsGrid
        exportName={exportName}
        rows={rows}
        rowKey={refKey}
        columns={columns}
        rowActions={actions.map((a) => ({ key: a.key, label: a.label, onClick: (row) => click(a, row), disabled: (row) => !a.when.includes(row.status) }))}
      />
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking ? fill(asking.action.title, asking.row) : ""}
        description={asking ? fill(asking.action.text, asking.row) : ""}
        confirmLabel={asking?.action.label}
        cancelLabel={w("ta.reg.keep")}
        destructive={asking?.action.destructive ?? false}
        loading={pending}
        onConfirm={() => run(asking.action, asking.row)}
      />
    </>
  );
}

const lineToForm = (l) => ({ item_id: l.item_id, item: l.item ?? "", description: l.description ?? "", uom: l.uom ?? "", qty: String(l.qty), rate: l.rate ? String(l.rate) : "", remarks: l.remarks ?? "", in_store: l.in_store });
const linePayload = (l) => ({ item_id: l.item_id || null, description: blankText(l.description), uom: blankText(l.uom), qty: l.qty === "" ? null : Number(l.qty), rate: l.rate === "" ? null : Number(l.rate), remarks: blankText(l.remarks) });

/** Item, Description, UoM, Qty, Rate and Line Remarks — and, on an issue, the store's stock. */
function useItemLines(paper, options, w, extraCols = []) {
  const noEntry = { item_id: "", description: "", uom: "", qty: "", rate: "", remarks: "" };
  const pick = (v) => {
    const it = options.items.find((x) => x.value === v);
    return { item: it?.label ?? "", ...(it?.uom ? { uom: it.uom } : {}), ...(it?.description ? { description: it.description } : {}) };
  };
  const cols = [
    { key: "item_id", label: w("ta.ed.item"), type: "item", options: options.items, onPick: pick },
    { key: "description", label: w("ta.ed.description"), type: "text" },
    { key: "uom", label: w("ta.ed.uom"), type: "uom" },
    { key: "qty", label: w("ta.ed.qty"), type: "number" },
    { key: "rate", label: w("ta.ed.rate"), type: "number" },
    { key: "remarks", label: w("ta.ed.line_remarks"), type: "text" },
    ...extraCols,
  ];
  return { entry: paper.entry ?? noEntry, cols, pick };
}

/* ───────────────────────── Receipt ───────────────────────── */

/** Trims & Accessories Receipt: MRR List and Daily Trims Receive. */
function TrimsReceiptDesk({ initial, options, canManage, act }) {
  const w = useWords();
  const c = (k, p) => w(`ta.${k}`, p);
  const [tab, setTab] = useState("list");
  const [rows, setRows] = useState(initial);
  const [filter, setFilter] = useState({});
  const blank = () => ({ mrr_no: "", mrr_date: today(), store_id: "", supplier_id: "", req_type: "", pay_method: "", pr_no: "", po_no: "", remarks: "", status: "DRAFT", lines: [] });
  const toForm = (r) => ({ ...blank(), ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v ?? ""])), lines: r.lines.map(lineToForm) });
  const paper = usePaper({
    blank,
    toForm,
    load: async (no) => {
      const res = await act.receipt(no);
      return { ...res, receipt: res?.data };
    },
    initialNo: null,
    docKey: "receipt",
  });
  const { form, setForm, pending, startTransition, warn } = paper;
  const { entry, cols, pick } = useItemLines(paper, options, w);
  const editable = canManage && form.status === "DRAFT";
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const reload = () =>
    startTransition(async () => {
      const res = await act.receipts();
      if (res?.status === "success") setRows(res.data);
    });
  const add = () => {
    if (!entry.item_id || !(Number(entry.qty) > 0)) return warn(c("ed.line_incomplete"));
    setForm((f) => ({ ...f, lines: [...f.lines, { ...entry, ...pick(entry.item_id), uom: entry.uom || pick(entry.item_id).uom || "", description: entry.description || pick(entry.item_id).description || "" }] }));
    paper.setEntry(null);
  };
  const save = () =>
    startTransition(async () => {
      const payload = { ...Object.fromEntries(["mrr_date", "store_id", "supplier_id", "req_type", "pay_method", "pr_no", "po_no", "remarks"].map((k) => [k, blankText(form[k])])), lines: form.lines.map(linePayload) };
      const res = await act.saveReceipt(form.mrr_no || null, payload);
      if (res?.status === "success") {
        setForm(toForm(res.data));
        paper.toastManager.add({ title: c("rc.saved", { no: res.data.mrr_no }), type: "success" });
        reload();
      } else warn(firstError(res));
    });
  const open = (row) => {
    paper.open(row.mrr_no);
    setTab("entry");
  };
  const shown = rows
    .filter((r) => has(r.mrr_no, filter.no) && within(r.mrr_date, filter.from, filter.to) && (!filter.store || r.store_id === filter.store) && (!filter.supplier || r.supplier_id === filter.supplier))
    .filter((r) => (!filter.pay || r.pay_method === filter.pay) && has(r.req_type, filter.reqtype) && has(r.pr_no, filter.pr) && has(r.po_no, filter.po) && (!filter.status || r.status === filter.status))
    .map((r) => ({ ...r, status_label: c(`status.${r.status}`), pay_label: c(`pay.${r.pay_method}`) }));
  const actions = [
    ...(canManage ? [{ key: "edit", label: c("reg.edit"), when: ["DRAFT"], open }] : []),
    ...(canManage ? [{ key: "complete", label: c("reg.complete"), title: c("reg.complete_title"), text: c("reg.complete_rc_text"), done: c("reg.completed"), run: (no) => act.receiptAction(no, "complete"), when: ["DRAFT"] }] : []),
    ...(canManage ? [{ key: "cancel", label: c("reg.cancel"), title: c("reg.cancel_title"), text: c("reg.cancel_text"), done: c("reg.cancelled"), run: (no) => act.receiptAction(no, "cancel"), when: ["DRAFT"], destructive: true }] : []),
  ];
  const payOpts = options.pay_methods.map((p) => ({ value: p, label: c(`pay.${p}`) }));

  return (
    <>
      <DeskTabs tab={tab} setTab={setTab} listLabel={c("rc.list_tab")} entryLabel={c("rc.entry_tab")} count={rows.length} canManage={canManage} icon={PackagePlus} />
      {tab === "entry" && canManage ? (
        <div className="flex flex-col gap-4">
          <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
            <EditorHead title={c("rc.title")} hint={c("rc.hint")} editing={Boolean(form.mrr_no)} c={c} />
            <Field label={c("rc.existing")} hint={c("ed.existing_hint")}>
              <Select value={form.mrr_no} onValueChange={(v) => (v ? paper.open(v) : setForm(blank()))} options={[{ value: "", label: c("rc.new_option") }, ...rows.map((x) => ({ value: x.mrr_no, label: `${x.mrr_no} · ${x.mrr_date} · ${c(`status.${x.status}`)}` }))]} placeholder={c("rc.new_option")} />
            </Field>
            {form.mrr_no && !editable ? <p className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{c("ed.locked", { no: form.mrr_no, status: c(`status.${form.status}`) })}</p> : null}
            <Group legend={c("ed.key_info")}>
              <Field label={c("rc.mrr_no")} hint={c("ed.no_hint")}>
                <Input value={form.mrr_no} placeholder={c("ed.auto_gen")} readOnly />
              </Field>
              <Field label={c("rc.mrr_date")} required>
                <Input type="date" value={form.mrr_date} onChange={(e) => set("mrr_date", e.target.value)} disabled={!editable} />
              </Field>
              <Field label={c("ed.company")} required>
                <Input value={options.company ?? ""} readOnly />
              </Field>
              <Field label={c("ed.store")} required>
                <Select value={form.store_id} onValueChange={(v) => set("store_id", v ?? "")} options={options.stores} placeholder={c("ed.select_store")} disabled={!editable} />
              </Field>
            </Group>
            <Group legend={c("ed.against")}>
              <Field label={c("rc.supplier")}>
                <Select value={form.supplier_id} onValueChange={(v) => set("supplier_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.suppliers]} placeholder={c("rc.select_supplier")} disabled={!editable} />
              </Field>
              <Field label={c("rc.req_type")}>
                <Input value={form.req_type} onChange={(e) => set("req_type", e.target.value)} placeholder={c("rc.req_type_ph")} maxLength={80} disabled={!editable} />
              </Field>
              <Field label={c("rc.pay_method")} required>
                <Select value={form.pay_method} onValueChange={(v) => set("pay_method", v ?? "")} options={payOpts} placeholder={c("ed.select")} disabled={!editable} />
              </Field>
              <Field label={c("rc.pr")}>
                <Input value={form.pr_no} onChange={(e) => set("pr_no", e.target.value)} maxLength={40} disabled={!editable} />
              </Field>
              <Field label={c("rc.po")}>
                <Input value={form.po_no} onChange={(e) => set("po_no", e.target.value)} maxLength={40} disabled={!editable} />
              </Field>
            </Group>
            <Group legend={c("ed.notes")}>
              <Field label={c("ed.remarks")} className="sm:col-span-3">
                <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={3} disabled={!editable} />
              </Field>
            </Group>
          </div>
          <LinesCard
            c={c}
            title={c("rc.lines")}
            hint={c("rc.lines_hint")}
            cols={cols}
            entry={entry}
            setEntry={(e) => paper.setEntry(e)}
            lines={form.lines}
            setLines={(lines) => setForm((f) => ({ ...f, lines }))}
            editable={editable}
            onAdd={add}
            emptyHint={c("ed.no_lines_hint")}
            totalLabel={c("ed.total_qty")}
            totalKey="qty"
          />
          <Foot w={w} note={c("rc.footer")} editable={editable} pending={pending} onBack={() => setTab("list")} onClear={() => setForm(blank())} onSave={save} saveLabel={c("rc.save")} />
        </div>
      ) : (
        <>
          <Filters
            w={w}
            value={filter}
            onChange={setFilter}
            onRefresh={reload}
            fields={[
              { name: "no", label: c("rc.mrr_no"), placeholder: "MRR-…" },
              { name: "from", label: c("rc.from"), type: "date" },
              { name: "to", label: c("rc.to"), type: "date" },
              { name: "store", label: c("ed.store"), type: "select", options: options.stores },
              { name: "supplier", label: c("rc.supplier"), type: "select", options: options.suppliers },
              { name: "pay", label: c("rc.pay_method"), type: "select", options: payOpts },
              { name: "reqtype", label: c("rc.req_type") },
              { name: "pr", label: c("rc.pr") },
              { name: "po", label: c("rc.po") },
              { name: "status", label: c("ed.status"), type: "select", options: ["DRAFT", "COMPLETED", "CANCELLED"].map((s) => ({ value: s, label: c(`status.${s}`) })) },
            ]}
          />
          <Register
            w={w}
            rows={shown}
            refKey="mrr_no"
            exportName="trims-receipts"
            onDone={reload}
            actions={actions}
            columns={[
              { key: "mrr_no", label: c("col.mrr_no"), type: "mono" },
              { key: "mrr_date", label: c("col.mrr_date"), type: "date" },
              { key: "store", label: c("col.store") },
              { key: "supplier", label: c("col.supplier") },
              { key: "req_type", label: c("col.req_type") },
              { key: "pay_label", label: c("col.pay_method") },
              { key: "pr_no", label: c("col.pr") },
              { key: "po_no", label: c("col.po") },
              { key: "items", label: c("col.items"), type: "number", align: "right" },
              { key: "qty", label: c("col.qty"), type: "number", align: "right" },
              { key: "value", label: c("col.value"), type: "number", align: "right" },
              { key: "status_label", label: c("col.status"), type: "badge" },
            ]}
          />
        </>
      )}
    </>
  );
}

/* ───────────────────────── Issue ───────────────────────── */

/** Trims & Accessories Issue: Issue List and Trims/Accessories Issue. */
function TrimsIssueDesk({ initial, options, canManage, canApprove, act }) {
  const w = useWords();
  const c = (k, p) => w(`ta.${k}`, p);
  const [tab, setTab] = useState("list");
  const [rows, setRows] = useState(initial);
  const [filter, setFilter] = useState({});
  const [stock, setStock] = useState({});
  const blank = () => ({ issue_no: "", issue_date: today(), store_id: "", requisition_no: "", order_id: "", line_id: "", remarks: "", status: "DRAFT", lines: [] });
  const toForm = (r) => ({ ...blank(), ...Object.fromEntries(Object.entries(r).map(([k, v]) => [k, v ?? ""])), lines: r.lines.map(lineToForm) });
  const loadStock = (storeId) => {
    if (!storeId) return setStock({});
    act.stock(storeId).then((res) => res?.status === "success" && setStock(Object.fromEntries(res.data.map((s) => [s.item_id, s.qty]))));
  };
  const paper = usePaper({
    blank,
    toForm,
    load: async (no) => {
      const res = await act.issue(no);
      if (res?.status === "success") loadStock(res.data.store_id);
      return { ...res, issue: res.data };
    },
    initialNo: null,
    docKey: "issue",
  });
  const { form, setForm, pending, startTransition, warn } = paper;
  const { entry, cols, pick } = useItemLines(paper, options, w, [{ key: "in_store", label: c("ed.in_store"), type: "readonly" }]);
  const lines = useMemo(() => form.lines.map((l) => ({ ...l, in_store: qty(stock[l.item_id] ?? 0) })), [form.lines, stock]);
  const editable = canManage && form.status === "DRAFT";
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const reload = () =>
    startTransition(async () => {
      const res = await act.issues();
      if (res?.status === "success") setRows(res.data);
    });
  const add = () => {
    if (!entry.item_id || !(Number(entry.qty) > 0)) return warn(c("ed.line_incomplete"));
    setForm((f) => ({ ...f, lines: [...f.lines, { ...entry, ...pick(entry.item_id), uom: entry.uom || pick(entry.item_id).uom || "", description: entry.description || pick(entry.item_id).description || "" }] }));
    paper.setEntry(null);
  };
  const save = () =>
    startTransition(async () => {
      const payload = { ...Object.fromEntries(["issue_date", "store_id", "requisition_no", "order_id", "line_id", "remarks"].map((k) => [k, blankText(form[k])])), lines: form.lines.map(linePayload) };
      const res = await act.saveIssue(form.issue_no || null, payload);
      if (res?.status === "success") {
        setForm(toForm(res.data));
        paper.toastManager.add({ title: c("is.saved", { no: res.data.issue_no }), type: "success" });
        reload();
      } else warn(firstError(res));
    });
  const open = (row) => {
    paper.open(row.issue_no);
    setTab("entry");
  };
  const shown = rows
    .filter((r) => (!filter.store || r.store_id === filter.store) && within(r.issue_date, filter.from, filter.to) && (!filter.status || r.status === filter.status) && has(r.issue_no, filter.no))
    .map((r) => ({ ...r, status_label: c(`status.${r.status}`) }));
  const actions = [
    ...(canManage ? [{ key: "edit", label: c("reg.edit"), when: ["DRAFT"], open }] : []),
    ...(canManage ? [{ key: "submit", label: c("reg.submit"), title: c("reg.submit_title"), text: c("reg.submit_text"), done: c("reg.submitted"), run: (no) => act.issueAction(no, "submit"), when: ["DRAFT"] }] : []),
    ...(canApprove ? [{ key: "approve", label: c("reg.approve"), title: c("reg.approve_title"), text: c("reg.approve_text"), done: c("reg.approved"), run: (no) => act.issueAction(no, "approve"), when: ["SUBMITTED"] }] : []),
    ...(canApprove ? [{ key: "reject", label: c("reg.reject"), prompt: c("reg.reject_why"), done: c("reg.rejected"), run: (no, extra) => act.issueAction(no, "reject", extra), when: ["SUBMITTED"] }] : []),
  ];
  const statusOpts = ["DRAFT", "SUBMITTED", "APPROVED", "REJECTED"].map((s) => ({ value: s, label: c(`status.${s}`) }));

  return (
    <>
      <DeskTabs tab={tab} setTab={setTab} listLabel={c("is.list_tab")} entryLabel={c("is.entry_tab")} count={rows.length} canManage={canManage} icon={PackageMinus} />
      {tab === "entry" && canManage ? (
        <div className="flex flex-col gap-4">
          <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
            <EditorHead title={c("is.title")} hint={c("is.hint")} editing={Boolean(form.issue_no)} c={c} />
            <Field label={c("is.existing")} hint={c("ed.existing_hint")}>
              <Select value={form.issue_no} onValueChange={(v) => (v ? paper.open(v) : setForm(blank()))} options={[{ value: "", label: c("is.new_option") }, ...rows.map((x) => ({ value: x.issue_no, label: `${x.issue_no} · ${x.issue_date} · ${c(`status.${x.status}`)}` }))]} placeholder={c("is.new_option")} />
            </Field>
            {form.issue_no && !editable ? <p className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{c("ed.locked", { no: form.issue_no, status: c(`status.${form.status}`) })}</p> : null}
            <Group legend={c("ed.key_info")}>
              <Field label={c("is.issue_no")} hint={c("ed.no_hint")}>
                <Input value={form.issue_no} placeholder={c("ed.auto_gen")} readOnly />
              </Field>
              <Field label={c("is.issue_date")} required>
                <Input type="date" value={form.issue_date} onChange={(e) => set("issue_date", e.target.value)} disabled={!editable} />
              </Field>
              <Field label={c("ed.company")} required>
                <Input value={options.company ?? ""} readOnly />
              </Field>
              <Field label={c("ed.store")} required>
                <Select
                  value={form.store_id}
                  onValueChange={(v) => {
                    set("store_id", v ?? "");
                    loadStock(v);
                  }}
                  options={options.stores}
                  placeholder={c("ed.select_store")}
                  disabled={!editable}
                />
              </Field>
            </Group>
            <Group legend={c("ed.against")}>
              <Field label={c("is.requisition")}>
                <Input value={form.requisition_no} onChange={(e) => set("requisition_no", e.target.value)} maxLength={40} disabled={!editable} />
              </Field>
              <Field label={c("is.order")}>
                <Select value={form.order_id} onValueChange={(v) => set("order_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.orders]} placeholder={c("ed.select")} disabled={!editable} />
              </Field>
              <Field label={c("is.line")}>
                <Select value={form.line_id} onValueChange={(v) => set("line_id", v ?? "")} options={[{ value: "", label: "—" }, ...options.lines]} placeholder={c("ed.select")} disabled={!editable} />
              </Field>
            </Group>
            <Group legend={c("ed.notes")}>
              <Field label={c("ed.remarks")} className="sm:col-span-3">
                <Textarea value={form.remarks} onChange={(e) => set("remarks", e.target.value)} rows={3} disabled={!editable} />
              </Field>
            </Group>
          </div>
          <LinesCard
            c={c}
            title={c("is.lines")}
            hint={c("is.lines_hint")}
            cols={cols}
            entry={{ ...entry, in_store: entry.item_id ? qty(stock[entry.item_id] ?? 0) : "" }}
            setEntry={(e) => paper.setEntry(e)}
            lines={lines}
            setLines={(next) => setForm((f) => ({ ...f, lines: next }))}
            editable={editable}
            onAdd={add}
            emptyHint={c("ed.no_lines_hint")}
            totalLabel={c("ed.total_qty")}
            totalKey="qty"
          />
          <Foot w={w} note={c("is.footer")} editable={editable} pending={pending} onBack={() => setTab("list")} onClear={() => setForm(blank())} onSave={save} saveLabel={c("is.save")} />
        </div>
      ) : (
        <>
          <Filters
            w={w}
            value={filter}
            onChange={setFilter}
            onRefresh={reload}
            fields={[
              { name: "no", label: c("is.issue_no"), placeholder: "ISS-…" },
              { name: "store", label: c("ed.store"), type: "select", options: options.stores },
              { name: "from", label: c("is.from"), type: "date" },
              { name: "to", label: c("is.to"), type: "date" },
              { name: "status", label: c("ed.status"), type: "select", options: statusOpts },
            ]}
          />
          <Register
            w={w}
            rows={shown}
            refKey="issue_no"
            exportName="trims-issues"
            onDone={reload}
            actions={actions}
            columns={[
              { key: "issue_no", label: c("col.issue_no"), type: "mono" },
              { key: "issue_date", label: c("col.issue_date"), type: "date" },
              { key: "store", label: c("col.store") },
              { key: "requisition_no", label: c("col.requisition") },
              { key: "order", label: c("col.order") },
              { key: "line", label: c("col.line") },
              { key: "items", label: c("col.items"), type: "number", align: "right" },
              { key: "qty", label: c("col.qty"), type: "number", align: "right" },
              { key: "status_label", label: c("col.status"), type: "badge" },
              { key: "reject_reason", label: c("col.reject_reason") },
            ]}
          />
        </>
      )}
    </>
  );
}

/* ───────────────────────── Receipt return ───────────────────────── */

/** Trims Receipt Return Register: Return List and Receipt Return against a completed MRR. */
function TrimsReturnDesk({ initial, receipts, options, canManage, act }) {
  const w = useWords();
  const toastManager = useToastManager();
  const c = (k, p) => w(`ta.${k}`, p);
  const [tab, setTab] = useState("list");
  const [rows, setRows] = useState(initial);
  const [filter, setFilter] = useState({});
  const blank = () => ({ return_no: "", return_date: today(), mrr_no: "", reason: "", remarks: "", status: "DRAFT" });
  const [form, setForm] = useState(blank);
  const [source, setSource] = useState(null);
  const [give, setGive] = useState({});
  const [pending, startTransition] = useTransition();
  const editable = canManage && form.status === "DRAFT";
  const warn = (title) => toastManager.add({ title, type: "danger" });
  const counted = receipts.filter((r) => r.status === "COMPLETED");
  const reload = () =>
    startTransition(async () => {
      const res = await act.returns();
      if (res?.status === "success") setRows(res.data);
    });
  const loadSource = (mrr, except, lines = null) =>
    startTransition(async () => {
      if (!mrr) return setSource(null);
      const res = await act.returnSource(mrr, except);
      if (res?.status !== "success") return warn(firstError(res));
      setSource(res.data);
      setGive(lines ? Object.fromEntries(lines.map((l) => [l.receipt_line_id, { qty: String(l.qty), remarks: l.remarks ?? "" }])) : {});
    });
  const open = (no) =>
    startTransition(async () => {
      const res = await act.return(no);
      if (res?.status !== "success") return warn(firstError(res));
      const r = res.data;
      setForm({ return_no: r.return_no, return_date: r.return_date, mrr_no: r.mrr_no, reason: r.reason, remarks: r.remarks ?? "", status: r.status });
      loadSource(r.mrr_no, r.return_no, r.lines);
      setTab("entry");
    });
  const save = () =>
    startTransition(async () => {
      const lines = Object.entries(give)
        .filter(([, v]) => Number(v.qty) > 0)
        .map(([id, v]) => ({ receipt_line_id: id, qty: Number(v.qty), remarks: blankText(v.remarks) }));
      const res = await act.saveReturn(form.return_no || null, { return_date: form.return_date, mrr_no: form.mrr_no || null, reason: form.reason || null, remarks: blankText(form.remarks), lines });
      if (res?.status !== "success") return warn(firstError(res));
      setForm((f) => ({ ...f, return_no: res.data.return_no, status: res.data.status }));
      toastManager.add({ title: c("rt.saved", { no: res.data.return_no }), type: "success" });
      reload();
    });
  const shown = rows
    .filter((r) => has(r.return_no, filter.no) && has(r.mrr_no, filter.mrr) && within(r.return_date, filter.from, filter.to) && (!filter.status || r.status === filter.status))
    .map((r) => ({ ...r, status_label: c(`status.${r.status}`), reason_label: c(`reason.${r.reason}`) }));
  const actions = [
    ...(canManage ? [{ key: "edit", label: c("reg.edit"), when: ["DRAFT"], open: (row) => open(row.return_no) }] : []),
    ...(canManage ? [{ key: "complete", label: c("reg.complete"), title: c("reg.complete_title"), text: c("reg.complete_rt_text"), done: c("reg.completed"), run: (no) => act.returnAction(no, "complete"), when: ["DRAFT"] }] : []),
    ...(canManage ? [{ key: "cancel", label: c("reg.cancel"), title: c("reg.cancel_title"), text: c("reg.cancel_text"), done: c("reg.cancelled"), run: (no) => act.returnAction(no, "cancel"), when: ["DRAFT"], destructive: true }] : []),
  ];
  const total = Object.values(give).reduce((s, v) => s + (Number(v.qty) || 0), 0);

  return (
    <>
      <DeskTabs tab={tab} setTab={setTab} listLabel={c("rt.list_tab")} entryLabel={c("rt.entry_tab")} count={rows.length} canManage={canManage} icon={Undo2} />
      {tab === "entry" && canManage ? (
        <div className="flex flex-col gap-4">
          <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-5">
            <EditorHead title={c("rt.title")} hint={c("rt.hint")} editing={Boolean(form.return_no)} c={c} />
            {form.return_no && !editable ? <p className="rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{c("ed.locked", { no: form.return_no, status: c(`status.${form.status}`) })}</p> : null}
            <Group legend={c("ed.key_info")}>
              <Field label={c("rt.return_no")} hint={c("ed.no_hint")}>
                <Input value={form.return_no} placeholder={c("ed.auto_gen")} readOnly />
              </Field>
              <Field label={c("rt.return_date")} required>
                <Input type="date" value={form.return_date} onChange={(e) => setForm({ ...form, return_date: e.target.value })} disabled={!editable} />
              </Field>
              <Field label={c("rt.mrr")} required hint={c("rt.mrr_hint")}>
                <Select
                  value={form.mrr_no}
                  onValueChange={(v) => {
                    setForm({ ...form, mrr_no: v ?? "" });
                    loadSource(v, form.return_no || null);
                  }}
                  options={counted.map((r) => ({ value: r.mrr_no, label: `${r.mrr_no} · ${r.mrr_date} · ${r.supplier ?? r.store}` }))}
                  placeholder={c("rt.select_mrr")}
                  disabled={!editable || Boolean(form.return_no)}
                />
              </Field>
              <Field label={c("rt.reason")} required>
                <Select value={form.reason} onValueChange={(v) => setForm({ ...form, reason: v ?? "" })} options={options.reasons.map((r) => ({ value: r, label: c(`reason.${r}`) }))} placeholder={c("ed.select")} disabled={!editable} />
              </Field>
              <Field label={c("ed.remarks")} className="sm:col-span-2">
                <Input value={form.remarks} onChange={(e) => setForm({ ...form, remarks: e.target.value })} maxLength={2000} disabled={!editable} />
              </Field>
            </Group>
          </div>
          <div className="rounded-lg border border-border bg-surface p-5">
            <h3 className="text-base font-semibold">{c("rt.lines")}</h3>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{source ? c("rt.lines_of", { no: source.mrr_no, store: source.store ?? "—", supplier: source.supplier ?? "—" }) : c("rt.pick_first")}</p>
            {source ? (
              <div className="mt-4 overflow-x-auto rounded-md border border-border">
                <table className="w-full min-w-max text-sm">
                  <thead>
                    <tr className="bg-brand text-left text-brand-foreground">
                      {["item", "uom", "received", "returned", "returnable", "now", "line_remarks"].map((k) => (
                        <th key={k} className={cn("px-3 py-2.5 font-semibold", ["received", "returned", "returnable", "now"].includes(k) && "text-right")}>
                          {c(`rt.col.${k}`)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {source.lines.map((l) => (
                      <tr key={l.id} className="border-b border-border last:border-0">
                        <td className="px-3 py-1.5">{l.item}</td>
                        <td className="px-3 py-1.5">{l.uom ?? "—"}</td>
                        <td className="px-3 py-1.5 text-right tabular-nums">{qty(l.received)}</td>
                        <td className="px-3 py-1.5 text-right tabular-nums">{qty(l.returned)}</td>
                        <td className="px-3 py-1.5 text-right font-semibold tabular-nums">{qty(l.returnable)}</td>
                        <td className="px-3 py-1.5">
                          <Input
                            type="number"
                            min="0"
                            step="0.001"
                            value={give[l.id]?.qty ?? ""}
                            onChange={(e) => setGive({ ...give, [l.id]: { ...(give[l.id] ?? {}), qty: e.target.value } })}
                            disabled={!editable || l.returnable <= 0}
                            className="ml-auto w-28 text-right"
                            aria-label={`${c("rt.col.now")} ${l.item}`}
                          />
                        </td>
                        <td className="px-3 py-1.5">
                          <Input value={give[l.id]?.remarks ?? ""} onChange={(e) => setGive({ ...give, [l.id]: { ...(give[l.id] ?? {}), remarks: e.target.value } })} disabled={!editable} maxLength={255} className="w-44" aria-label={`${c("rt.col.line_remarks")} ${l.item}`} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : null}
            {source ? <p className="mt-3 text-[13px] text-foreground-muted">{c("ed.total_qty")} <span className="rounded-md bg-surface-muted px-2 py-0.5 font-semibold text-foreground tabular-nums">{qty(total)}</span></p> : null}
          </div>
          <Foot
            w={w}
            note={c("rt.footer")}
            editable={editable}
            pending={pending}
            onBack={() => setTab("list")}
            onClear={() => {
              setForm(blank());
              setSource(null);
              setGive({});
            }}
            onSave={save}
            saveLabel={c("rt.save")}
          />
        </div>
      ) : (
        <>
          <Filters
            w={w}
            value={filter}
            onChange={setFilter}
            onRefresh={reload}
            fields={[
              { name: "no", label: c("rt.return_no"), placeholder: "RTN-…" },
              { name: "mrr", label: c("rt.mrr"), placeholder: "MRR-…" },
              { name: "from", label: c("rt.from"), type: "date" },
              { name: "to", label: c("rt.to"), type: "date" },
              { name: "status", label: c("ed.status"), type: "select", options: ["DRAFT", "COMPLETED", "CANCELLED"].map((s) => ({ value: s, label: c(`status.${s}`) })) },
            ]}
          />
          <Register
            w={w}
            rows={shown}
            refKey="return_no"
            exportName="trims-receipt-returns"
            onDone={reload}
            actions={actions}
            columns={[
              { key: "return_no", label: c("col.return_no"), type: "mono" },
              { key: "return_date", label: c("col.return_date"), type: "date" },
              { key: "mrr_no", label: c("col.mrr_no"), type: "mono" },
              { key: "supplier", label: c("col.supplier") },
              { key: "store", label: c("col.store") },
              { key: "reason_label", label: c("col.reason") },
              { key: "items", label: c("col.items"), type: "number", align: "right" },
              { key: "qty", label: c("col.qty"), type: "number", align: "right" },
              { key: "status_label", label: c("col.status"), type: "badge" },
            ]}
          />
        </>
      )}
    </>
  );
}

export { TrimsReceiptDesk, TrimsIssueDesk, TrimsReturnDesk };
