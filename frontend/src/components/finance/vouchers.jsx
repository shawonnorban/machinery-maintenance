"use client";

import { useEffect, useState, useTransition } from "react";
import { FileText, Plus, RotateCcw, Save, Stamp, Table2, Undo2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { printDocument } from "@/lib/print-document";
import { blank, day, EditGrid, Filters, firstError, has, money, Register, within } from "@/components/sales-shipment/shared";
import { Box } from "@/components/tradeexport/operations";

/*
 * Finance & Ledger → Voucher Entry (AnnotechERP 23/02), as Brickland lays the
 * screens out, each with its own tab strip (switching in place, no reload):
 *
 * - Draft Voucher Entry: Records (the drafts, searched) and the entry — a
 *   header and its lines saved together, Existing draft voucher to load one
 *   back; approving commits it to posting.
 * - Voucher Posting Control: the committed vouchers, Post / Unpost (company
 *   on the left, its vouchers on the right) and New voucher, which only says
 *   where vouchers are raised.
 * - Statutory Voucher Register: posted lines on a compliance ledger head.
 */

const P = "/accounting/vouchers";
const TYPES = ["BPV", "BRV", "CPV", "CRV", "CV", "BV", "JV"];
const SOURCES = ["FINANCE", "COMMERCIAL", "STORE", "HR", "SYSTEM"];
const ACTIONS = ["CREATE_VOUCHER", "SUBMIT_FOR_APPROVAL", "APPROVED", "REJECTED", "ON_HOLD"];
const POSTING = ["DRAFT", "UNPOSTED", "POSTED", "CANCELLED"];
const FOR_TYPES = ["GENERAL", "SUPPLIER", "BUYER", "EMPLOYEE", "PARTY"];
const n = (v) => Number(v || 0);
const uid = () => Math.random().toString(36).slice(2);

function useW() {
  const t = useT("accounting");
  return (k, p) => t(`vo.${k}`, p);
}

const opts = (w, group, values) => values.map((v) => ({ value: v, label: w(`${group}.${v}`) }));
const textF = (name, label, field) => ({ name, label, test: (r, v) => has(field(r), v) });
const selF = (name, label, options, field) => ({ name, label, type: "select", options, test: (r, v) => !v || field(r) === v });
const dateF = (name, label, field, side) => ({ name, label, type: "date", test: (r, v) => (side === "from" ? within(field(r), v, null) : within(field(r), null, v)) });

/** Brickland's tab strip, switching in place. */
function Tabs({ tabs, active, onPick }) {
  return (
    <div className="flex flex-wrap gap-1 border-b border-border" role="tablist">
      {tabs.map((t) => (
        <button
          key={t.key}
          type="button"
          role="tab"
          aria-selected={active === t.key}
          onClick={() => onPick(t.key)}
          className={cn("-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors", active === t.key ? "border-brand text-brand" : "border-transparent text-foreground-muted hover:text-foreground")}
        >
          {t.icon}
          {t.label}
          {t.count !== undefined ? <span className="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] tabular-nums">{t.count}</span> : null}
        </button>
      ))}
    </div>
  );
}

const address = (key, no) => {
  const url = new URL(window.location.href);
  if (no) url.searchParams.set(key, no);
  else url.searchParams.delete(key);
  window.history.replaceState(null, "", url);
};

/* ───────────────────────── Draft Voucher Entry ───────────────────────── */

const blankLine = () => ({ _k: uid(), account_id: "", cost_center_id: "", bill_no: "", note: "", ref_no: "", currency: "BDT", rate: "1", value: "", debit: "", credit: "" });
const blankVoucher = (opt) => ({ voucher_date: opt.today, voucher_type: "", trx_source: "", bill_no: "", voucher_for_type: "GENERAL", voucher_for: "", narration: "", action_status: "CREATE_VOUCHER", lines: [] });

function VoucherForm({ w, form, set, opt, locked, lastNo, full }) {
  const dr = form.lines.reduce((a, l) => a + n(l.debit), 0);
  const cr = form.lines.reduce((a, l) => a + n(l.credit), 0);
  const currency = (code) => opt.currencies.find((c) => c.value === code);
  return (
    <>
      <div className="grid gap-x-6 gap-y-3 lg:grid-cols-3">
        <Field label={w("f.company")} req>
          <Select value={opt.companies[0]?.value ?? ""} disabled options={opt.companies} aria-label={w("f.company")} />
        </Field>
        <Field label={w("f.voucher_date")} req>
          <Input type="date" value={form.voucher_date ?? ""} disabled={locked} onChange={(e) => set("voucher_date", e.target.value)} aria-label={w("f.voucher_date")} />
        </Field>
        <Field label={w("f.voucher_ref")}>
          <Input value={form.voucher_ref_no ?? ""} placeholder={w("issued_on_save")} disabled readOnly aria-label={w("f.voucher_ref")} />
        </Field>
        <Field label={w("f.voucher_type")} req>
          <Select value={form.voucher_type ?? ""} disabled={locked} onValueChange={(v) => set("voucher_type", v ?? "")} options={opts(w, "vtype", TYPES)} placeholder={w("select")} aria-label={w("f.voucher_type")} />
        </Field>
        <Field label={w("f.source_ref")}>
          <Select value={form.trx_source ?? ""} disabled={locked} onValueChange={(v) => set("trx_source", v ?? "")} options={[{ value: "", label: w("select") }, ...opts(w, "source", SOURCES)]} placeholder={w("select")} aria-label={w("f.source_ref")} />
        </Field>
        <Field label={w("f.last_voucher")}>
          <Input value={lastNo ?? ""} placeholder={w("none_yet")} disabled readOnly aria-label={w("f.last_voucher")} />
        </Field>
        {full ? (
          <>
            <Field label={w("f.bill_no")}>
              <Input value={form.bill_no ?? ""} maxLength={48} disabled={locked} onChange={(e) => set("bill_no", e.target.value)} aria-label={w("f.bill_no")} />
            </Field>
            <Field label={w("f.voucher_for_type")}>
              <Select value={form.voucher_for_type ?? ""} disabled={locked} onValueChange={(v) => set("voucher_for_type", v ?? "")} options={opts(w, "for_type", FOR_TYPES)} placeholder={w("select")} aria-label={w("f.voucher_for_type")} />
            </Field>
            <Field label={w("f.voucher_for")}>
              <Input value={form.voucher_for ?? ""} maxLength={160} disabled={locked} onChange={(e) => set("voucher_for", e.target.value)} aria-label={w("f.voucher_for")} />
            </Field>
          </>
        ) : null}
        <Field label={w("f.narration")} wide>
          <textarea rows={3} value={form.narration ?? ""} disabled={locked} onChange={(e) => set("narration", e.target.value)} aria-label={w("f.narration")} className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm disabled:bg-surface-muted" />
        </Field>
        <Field label={w("f.action_status")} req>
          <Select value={form.action_status ?? ""} disabled={locked} onValueChange={(v) => set("action_status", v ?? "")} options={opts(w, "action", ACTIONS)} aria-label={w("f.action_status")} />
        </Field>
      </div>
      <div className="mt-4">
        <EditGrid
          w={w}
          title={w("box.lines")}
          hint={w("box.lines_hint")}
          editable={!locked}
          addLabel={w("add_line")}
          onAdd={() => set("lines", [...form.lines, blankLine()])}
          rows={form.lines}
          setRows={(v) => set("lines", v)}
          emptyNote={w("no_lines")}
          cols={[
            { key: "account_id", label: w("col.account_head"), type: "select", options: opt.accounts, width: 240 },
            { key: "cost_center_id", label: w("col.cost_center"), type: "select", options: [{ value: "", label: "—" }, ...opt.cost_centers], width: 180 },
            { key: "bill_no", label: w("col.bill_no"), max: 48, width: 110 },
            { key: "note", label: w("col.narration"), max: 500, width: 180 },
            { key: "ref_no", label: w("col.ref"), max: 48, width: 90 },
            { key: "currency", label: w("col.currency"), type: "select", options: opt.currencies, width: 100, onPick: (v) => ({ rate: String(currency(v)?.rate ?? "") }) },
            { key: "rate", label: w("col.rate"), type: "number", align: "right", width: 90 },
            { key: "value", label: w("col.value"), type: "number", align: "right" },
            { key: "debit", label: w("col.dr"), type: "number", align: "right" },
            { key: "credit", label: w("col.cr"), type: "number", align: "right" },
          ]}
        />
        <p className={cn("mt-2 text-right text-sm font-semibold", Math.abs(dr - cr) > 0.005 && "text-danger")}>{w("totals", { dr: money(dr), cr: money(cr), diff: money(dr - cr) })}</p>
      </div>
    </>
  );
}

function Field({ label, req, wide, children }) {
  return (
    <label className={cn("flex min-w-0 flex-col gap-1.5", wide && "lg:col-span-2")}>
      <span className="text-[13px] font-semibold">
        {label}
        {req ? <span className="text-danger"> *</span> : null}
      </span>
      {children}
    </label>
  );
}

const toVoucherForm = (d) => ({
  id: d.id,
  voucher_ref_no: d.voucher_ref_no,
  voucher_no: d.voucher_no,
  posting_status: d.posting_status,
  bill_no: d.bill_no ?? "",
  voucher_for_type: d.voucher_for_type ?? "GENERAL",
  voucher_for: d.voucher_for ?? "",
  voucher_date: d.voucher_date,
  voucher_type: d.voucher_type,
  trx_source: d.trx_source ?? "",
  narration: d.narration ?? "",
  action_status: d.action_status,
  lines: d.lines.map((l) => ({ _k: uid(), account_id: l.account_id, cost_center_id: l.cost_center_id ?? "", bill_no: l.bill_no ?? "", note: l.note ?? "", ref_no: l.ref_no ?? "", currency: l.currency, rate: String(l.rate), value: l.value === null ? "" : String(l.value), debit: l.debit ? String(l.debit) : "", credit: l.credit ? String(l.credit) : "" })),
});

function voucherPayload(f, commit = false) {
  return {
    ...(commit ? { commit: true, bill_no: blank(f.bill_no), voucher_for_type: f.voucher_for_type || "GENERAL", voucher_for: blank(f.voucher_for) } : {}),
    voucher_date: f.voucher_date || null,
    voucher_type: f.voucher_type || null,
    trx_source: f.trx_source || null,
    narration: blank(f.narration),
    action_status: f.action_status || null,
    lines: f.lines.map((l) => ({ account_id: l.account_id || null, cost_center_id: l.cost_center_id || null, bill_no: blank(l.bill_no), note: blank(l.note), ref_no: blank(l.ref_no), currency: l.currency || "BDT", rate: l.rate === "" ? null : l.rate, value: l.value === "" ? null : l.value, debit: l.debit === "" ? 0 : l.debit, credit: l.credit === "" ? 0 : l.credit })),
  };
}

/**
 * Draft Voucher Entry (`mode="draft"`, 23/02) and Voucher Entry (`mode="voucher"`, 23/03): the same
 * desk — Records and the entry. A draft is saved with only its reference and commits on approval; a
 * voucher commits on save, numbered, amendable until posted, its posted copy shown read-only.
 */
function VoucherDesk({ mode, initial, opt, canManage, act, query }) {
  const w = useW();
  const toastManager = useToastManager();
  const draft = mode === "draft";
  const refKey = draft ? "voucher_ref_no" : "voucher_no";
  const [rows, setRows] = useState(initial);
  const [tab, setTab] = useState(query?.no ? "entry" : "records");
  const [filter, setFilter] = useState({});
  const [form, setForm] = useState(blankVoucher(opt));
  const [lastNo, setLastNo] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const reload = () => act.read(draft ? `${P}?scope=draft` : P).then((r) => r?.status === "success" && setRows(r.data));
  const reset = () => {
    setForm(blankVoucher(opt));
    address("no", null);
  };
  const load = (id) =>
    startTransition(async () => {
      if (!id) return reset();
      const res = await act.read(`${P}/${id}`);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      setForm(toVoucherForm(res.data));
      address("no", res.data[refKey]);
    });
  useEffect(() => {
    const hit = query?.no && initial.find((r) => r[refKey] === query.no);
    if (hit) load(hit.id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  useEffect(() => {
    if (!form.voucher_type) return setLastNo(null);
    act.read(`${P}/last-no?type=${form.voucher_type}`).then((r) => r?.status === "success" && setLastNo(r.data.voucher_no));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.voucher_type, form.voucher_no]);
  const save = () =>
    startTransition(async () => {
      const res = await act.write(form.id ? "PUT" : "POST", form.id ? `${P}/${form.id}` : P, voucherPayload(form, !draft));
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
      reload();
      if (draft && res.data.posting_status !== "DRAFT") {
        toastManager.add({ title: w("approved_note", { no: res.data.voucher_no }), type: "success" });
        return reset();
      }
      toastManager.add({ title: draft ? w("saved") : w("voucher_saved", { no: res.data.voucher_no }), type: "success" });
      setForm(toVoucherForm(res.data));
      address("no", res.data[refKey]);
    });
  const filters = [
    textF("no", w("f.voucher_no_f"), (r) => r.voucher_no),
    textF("ref", w("f.voucher_ref_f"), (r) => r.voucher_ref_no),
    ...(draft ? [] : [textF("bill", w("f.bill_no_f"), (r) => r.bill_no)]),
    selF("type", w("f.voucher_type"), opts(w, "vtype", TYPES), (r) => r.voucher_type),
    selF("company", w("f.company"), opt.companies, () => opt.companies[0]?.value),
    selF("source", w("f.trx_source"), opts(w, "source", SOURCES), (r) => r.trx_source),
    ...(draft ? [] : [selF("for_type", w("f.voucher_for_type"), opts(w, "for_type", FOR_TYPES), (r) => r.voucher_for_type), textF("for", w("f.voucher_for"), (r) => r.voucher_for)]),
    selF("status", w("f.posting_status"), opts(w, "posting", POSTING), (r) => r.posting_status),
    dateF("from", w("f.from"), (r) => r.voucher_date, "from"),
    dateF("to", w("f.to"), (r) => r.voucher_date, "to"),
  ];
  const shown = rows
    .map((r) => ({ ...r, voucher_ref: [r.voucher_no, r.voucher_ref_no].filter(Boolean).join(" · "), type_source: [w(`vtype.${r.voucher_type}`), r.trx_source ? w(`source.${r.trx_source}`) : null].filter(Boolean).join(" · "), post_label: w(`posting.${r.posting_status}`) }))
    .filter((r) => filters.every((f) => f.test(r, filter[f.name])));
  const open = rows.filter((r) => (draft ? true : r.posting_status === "UNPOSTED"));
  const locked = !canManage || (!draft && form.posting_status && form.posting_status !== "UNPOSTED");
  return (
    <div className="flex flex-col gap-4">
      <Tabs
        active={tab}
        onPick={setTab}
        tabs={[{ key: "records", label: w("tab.records"), icon: <Table2 className="size-4" />, count: rows.length }, ...(canManage || form.id ? [{ key: "entry", label: w(draft ? "tab.draft_entry" : "tab.voucher_entry"), icon: <Plus className="size-4" /> }] : [])]}
      />
      {tab === "entry" && (canManage || form.id) ? (
        <>
          {locked && form.id ? <p className="rounded-lg bg-warning-subtle px-4 py-2 text-sm text-warning">{w("posted_note", { no: form.voucher_no })}</p> : null}
          <Box legend={w(draft ? "box.draft_entry" : "box.voucher_entry")} grid={false}>
            <p className="mb-3 text-[13px] text-foreground-muted">{w("box.draft_entry_hint")}</p>
            <div className="mb-4 max-w-3xl">
              <Field label={w(draft ? "f.existing_draft" : "f.existing_voucher")}>
                <Select
                  value={form.id ?? ""}
                  onValueChange={(v) => load(v)}
                  options={[{ value: "", label: w(draft ? "new_draft" : "new_voucher_opt") }, ...(form.id && !open.some((r) => r.id === form.id) ? [{ value: form.id, label: form.voucher_no ?? form.voucher_ref_no }] : []), ...open.map((r) => ({ value: r.id, label: `${r[refKey]} · ${day(r.voucher_date)} · ${w(`vtype.${r.voucher_type}`)}` }))]}
                  aria-label={w(draft ? "f.existing_draft" : "f.existing_voucher")}
                />
              </Field>
              <p className="mt-1 text-xs text-foreground-muted">{w(draft ? "existing_hint" : "existing_voucher_hint")}</p>
            </div>
            <VoucherForm w={w} form={form} set={set} opt={opt} locked={locked} lastNo={lastNo} full={!draft} />
          </Box>
          <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface p-4">
            <p className="min-w-0 flex-1 text-[13px] text-foreground-muted">{w("draft_note")}</p>
            <Button variant="outline" disabled={pending} onClick={reset}>
              <RotateCcw /> {w("clear")}
            </Button>
            {!locked ? (
              <Button onClick={save} loading={pending}>
                <Save /> {w(draft ? "save_draft" : "save_voucher")}
              </Button>
            ) : null}
          </div>
        </>
      ) : (
        <>
          <Filters w={w} value={filter} onChange={setFilter} fields={filters} note={w(draft ? "filter_note" : "voucher_filter_note")} />
          <Register
            w={w}
            rows={shown}
            refKey={refKey}
            exportName={draft ? "draft-vouchers" : "vouchers"}
            onDone={reload}
            emptyTitle={w("empty")}
            emptyText=""
            actions={[
              {
                key: "edit",
                label: canManage ? w("edit") : w("open"),
                open: (r) => {
                  setTab("entry");
                  load(r.id);
                },
              },
              ...(canManage ? [{ key: "delete", label: w("delete"), when: (r) => r.posting_status === "DRAFT" || r.posting_status === "UNPOSTED", confirm: w("confirm.delete"), done: w("done.delete"), destructive: true, run: (no, _e, r) => act.write("DELETE", `${P}/${r.id}`) }] : []),
            ]}
            columns={[
              { key: "voucher_ref", label: w("col.voucher_ref"), type: "mono" },
              { key: "voucher_date", label: w("col.date"), type: "date" },
              { key: "company", label: w("col.company") },
              { key: "type_source", label: w("col.type_source") },
              ...(draft ? [] : [{ key: "bill_no", label: w("col.bill_no_cap") }]),
              { key: "amount", label: w("col.amount"), type: "number", align: "right" },
              { key: "post_label", label: w("col.post_status"), type: "badge" },
              { key: "prepared_by", label: w("col.prepared_by") },
              { key: "updated_at", label: w("col.last_update") },
            ]}
          />
          {draft ? null : <p className="text-xs text-foreground-muted">{w("voucher_register_note")}</p>}
        </>
      )}
    </div>
  );
}

const DraftVoucherDesk = (props) => <VoucherDesk mode="draft" {...props} />;
const VoucherEntryDesk = (props) => <VoucherDesk mode="voucher" {...props} />;

/* ───────────────────────── Voucher Posting Control ───────────────────────── */

function PostingDesk({ initial, board, opt, canPost, act }) {
  const w = useW();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [data, setData] = useState(board);
  const [tab, setTab] = useState("records");
  const [filter, setFilter] = useState({});
  const [company, setCompany] = useState(null);
  const [q, setQ] = useState("");
  const [asking, setAsking] = useState(null);
  const [pending, startTransition] = useTransition();
  const reload = () => {
    act.read(P).then((r) => r?.status === "success" && setRows(r.data));
    act.read(`${P}/posting`).then((r) => r?.status === "success" && setData(r.data));
  };
  const run = (kind, v) =>
    startTransition(async () => {
      const res = await act.write("POST", `${P}/${v.id}/${kind}`);
      setAsking(null);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
      toastManager.add({ title: w(`done.${kind}`, { no: v.voucher_no }), type: "success" });
      reload();
    });
  const filters = [
    textF("no", w("f.voucher_no_f"), (r) => r.voucher_no),
    textF("ref", w("f.voucher_ref_f"), (r) => r.voucher_ref_no),
    textF("bill", w("f.bill_no_f"), (r) => r.bill_no),
    selF("type", w("f.voucher_type"), opts(w, "vtype", TYPES), (r) => r.voucher_type),
    selF("company", w("f.company"), opt.companies, () => opt.companies[0]?.value),
    selF("source", w("f.trx_source"), opts(w, "source", SOURCES), (r) => r.trx_source),
    selF("for_type", w("f.voucher_for_type"), opts(w, "for_type", FOR_TYPES), (r) => r.voucher_for_type),
    textF("for", w("f.voucher_for"), (r) => r.voucher_for),
    selF("status", w("f.posting_status"), opts(w, "posting", POSTING), (r) => r.posting_status),
    dateF("from", w("f.from"), (r) => r.voucher_date, "from"),
    dateF("to", w("f.to"), (r) => r.voucher_date, "to"),
  ];
  const shown = rows.map((r) => ({ ...r, post_label: w(`posting.${r.posting_status}`), type_label: w(`vtype.${r.voucher_type}`), balanced_label: w(`yn.${r.balanced ? "YES" : "NO"}`) })).filter((r) => filters.every((f) => f.test(r, filter[f.name])));
  const columns = [
    { key: "post_label", label: w("col.post_status"), type: "badge" },
    { key: "voucher_no", label: w("col.voucher_no"), type: "mono" },
    { key: "bill_no", label: w("col.bill_no_cap") },
    { key: "voucher_ref_no", label: w("col.ref_no"), type: "mono" },
    { key: "voucher_date", label: w("col.date"), type: "date" },
    { key: "type_label", label: w("col.type") },
    { key: "company", label: w("col.company") },
    { key: "created_at", label: w("col.created_at") },
    { key: "prepared_by", label: w("col.prepared_by") },
    { key: "updated_at", label: w("col.updated_at") },
    { key: "updated_by", label: w("col.updated_by") },
    { key: "amount", label: w("col.amount"), type: "number", align: "right" },
    { key: "balanced_label", label: w("col.balanced") },
    { key: "posted_at", label: w("col.posted") },
  ];
  const pdf = () =>
    printDocument({
      title: w("print_register"),
      orientation: "landscape",
      sections: [{ columns: columns.map((c) => ({ label: c.label, align: c.align })), rows: shown.map((r) => columns.map((c) => (c.type === "date" ? day(r[c.key]) : c.type === "number" ? { v: money(r[c.key]), align: "right" } : (r[c.key] ?? "—")))) }],
    });
  const vouchers = (data.vouchers ?? []).filter((v) => !q || has(v.voucher_no, q) || has(v.ref_no, q));
  return (
    <div className="flex flex-col gap-4">
      <Tabs
        active={tab}
        onPick={setTab}
        tabs={[
          { key: "records", label: w("tab.post_list"), icon: <Table2 className="size-4" />, count: rows.length },
          { key: "board", label: w("tab.post_unpost"), icon: <Stamp className="size-4" /> },
          { key: "new", label: w("tab.new_voucher"), icon: <Plus className="size-4" /> },
        ]}
      />
      {tab === "records" ? (
        <>
          <Filters
            w={w}
            value={filter}
            onChange={setFilter}
            fields={filters}
            note={w("filter_note")}
            extra={
              <Button type="button" variant="outline" onClick={pdf} disabled={!shown.length}>
                <FileText /> {w("pdf")}
              </Button>
            }
          />
          <Register w={w} rows={shown} refKey="voucher_no" exportName="voucher-posting" onDone={reload} emptyTitle={w("empty")} emptyText="" actions={[]} columns={columns} />
        </>
      ) : tab === "board" ? (
        <div className="grid gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
          <aside className="flex flex-col gap-3 self-start rounded-lg border border-border bg-surface p-4">
            <div className="flex items-center justify-between">
              <h3 className="text-[15px] font-semibold">{w("board.company")}</h3>
              <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs">{data.companies.length}</span>
            </div>
            {data.companies.map((c) => (
              <button key={c.id} type="button" onClick={() => setCompany(c.id)} className={cn("flex items-center justify-between rounded-md border border-border px-3 py-2 text-left text-sm", company === c.id ? "border-brand bg-brand-subtle" : "hover:bg-surface-muted")}>
                <span>
                  <span className="block font-semibold">{c.label}</span>
                  <span className="text-xs text-foreground-muted">{w("board.count", { n: c.count })}</span>
                </span>
                <span className="tabular-nums">{c.count}</span>
              </button>
            ))}
          </aside>
          <section className="min-w-0 rounded-lg border border-border bg-surface p-4">
            <h3 className="text-[15px] font-semibold">{w("board.vouchers")}</h3>
            <p className="mb-3 text-[13px] text-foreground-muted">{w("board.vouchers_hint")}</p>
            {!company ? (
              <p className="py-10 text-center text-sm text-foreground-muted">{w("board.pick")}</p>
            ) : (
              <>
                <div className="mb-3 max-w-sm">
                  <Input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={w("search_dots")} aria-label={w("search_dots")} />
                </div>
                <div className="overflow-x-auto rounded-md border border-border">
                  <table className="w-full min-w-max text-sm">
                    <thead>
                      <tr className="bg-brand text-left text-brand-foreground">
                        {["voucher_no", "ref_no", "bill_no_cap", "date", "type", "dr", "cr", "post_status", "prepared_by", "action"].map((k) => (
                          <th key={k} className={cn("px-3 py-2 font-semibold whitespace-nowrap", ["dr", "cr"].includes(k) && "text-right")}>
                            {w(`col.${k}`)}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {vouchers.length ? (
                        vouchers.map((v) => (
                          <tr key={v.id} className="border-b border-border last:border-0">
                            <td className="px-3 py-1.5 font-mono text-xs">{v.voucher_no}</td>
                            <td className="px-3 py-1.5 font-mono text-xs">{v.ref_no}</td>
                            <td className="px-3 py-1.5">{v.bill_no ?? "—"}</td>
                            <td className="px-3 py-1.5">{day(v.date)}</td>
                            <td className="px-3 py-1.5">{w(`vtype.${v.type}`)}</td>
                            <td className="px-3 py-1.5 text-right tabular-nums">{money(v.debit)}</td>
                            <td className="px-3 py-1.5 text-right tabular-nums">{money(v.credit)}</td>
                            <td className="px-3 py-1.5">{w(`posting.${v.status}`)}</td>
                            <td className="px-3 py-1.5">{v.prepared_by ?? "—"}</td>
                            <td className="px-3 py-1.5">
                              {canPost && v.status === "UNPOSTED" ? (
                                <Button size="sm" onClick={() => run("post", v)} disabled={pending} aria-label={`${w("post")} ${v.voucher_no}`}>
                                  <Stamp /> {w("post")}
                                </Button>
                              ) : null}
                              {canPost && v.status === "POSTED" ? (
                                <Button size="sm" variant="outline" className="text-danger" onClick={() => setAsking(v)} disabled={pending} aria-label={`${w("unpost")} ${v.voucher_no}`}>
                                  <Undo2 /> {w("unpost")}
                                </Button>
                              ) : null}
                            </td>
                          </tr>
                        ))
                      ) : (
                        <tr>
                          <td colSpan={10} className="px-3 py-6 text-center text-foreground-muted">
                            {w("empty")}
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
                <p className="mt-3 text-xs text-foreground-muted">{w("board.note")}</p>
              </>
            )}
          </section>
        </div>
      ) : (
        <Box legend={w("tab.new_voucher")} grid={false}>
          <p className="text-sm text-foreground-muted">{w("new_voucher_note")}</p>
          <div className="mt-4 flex gap-3">
            <Button variant="outline" onClick={() => setTab("records")}>
              <RotateCcw /> {w("reset")}
            </Button>
            <Button onClick={() => toastManager.add({ title: w("new_voucher_note"), type: "warning" })}>
              <Save /> {w("save_voucher")}
            </Button>
          </div>
        </Box>
      )}
      <ConfirmDialog
        open={asking !== null}
        onOpenChange={(o) => !o && setAsking(null)}
        title={asking ? `${w("unpost")} ${asking.voucher_no}` : ""}
        description={w("confirm.unpost")}
        confirmLabel={w("unpost")}
        cancelLabel={w("od.reg.keep")}
        destructive
        loading={pending}
        onConfirm={() => run("unpost", asking)}
      />
    </div>
  );
}

/* ───────────────────────── Statutory Voucher Register ───────────────────────── */

function StatutoryRegister({ initial }) {
  const w = useW();
  return (
    <div className="flex flex-col gap-3">
      <Register
        w={w}
        rows={initial.map((r, i) => ({ ...r, _key: `${r.voucher}-${i}` }))}
        refKey="_key"
        exportName="statutory-vouchers"
        emptyTitle={w("empty")}
        emptyText=""
        actions={[]}
        columns={[
          { key: "voucher", label: w("col.voucher"), type: "mono" },
          { key: "date", label: w("col.date"), type: "date" },
          { key: "company", label: w("col.company") },
          { key: "ledger", label: w("col.ledger_head") },
          { key: "amount", label: w("col.amount"), type: "number", align: "right" },
          { key: "pct", label: w("col.compliance_pct"), type: "number", align: "right" },
          { key: "qualifying", label: w("col.qualifying"), type: "number", align: "right" },
        ]}
      />
      <p className="text-xs text-foreground-muted">{w("statutory_note")}</p>
    </div>
  );
}

export { DraftVoucherDesk, VoucherEntryDesk, PostingDesk, StatutoryRegister, Tabs, Field, useW, address, textF, selF, dateF, opts, uid };
