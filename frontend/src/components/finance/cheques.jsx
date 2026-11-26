"use client";

import { useEffect, useState, useTransition } from "react";
import { ArrowLeft, Ban, FileText, Plus, Printer, RotateCcw, Save, Stamp, Table2 } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { printDocument } from "@/lib/print-document";
import { blank, day, EditGrid, Filters, firstError, has, money, Register, within } from "@/components/sales-shipment/shared";
import { Box } from "@/components/tradeexport/operations";
import { address, Field, opts, Tabs, uid, useW } from "@/components/finance/vouchers";

/*
 * Finance & Ledger → Cheque Management (AnnotechERP 23/05), as Brickland lays
 * the screens out:
 *
 * - Cheque Issue Entry: Cheque Issue List (searched) and Cheque Issue — the
 *   account, a leaf off one of its books, the payee and amount, and the lines
 *   it settles by control head (a requisition or bill reference showing what
 *   it has already been settled for). Cheque Print is recorded once.
 * - Cheque Cancellation Entry: the cancelled leaves, searched, with New
 *   cheque cancellation in a dialog.
 */

const P = "/accounting/cheques";
const TYPES = ["MONEY_REQUISITION", "BILL_PAYMENT", "SUPPLIER_PAYMENT", "ADVANCE", "SALARY", "UTILITY", "OTHERS"];
const n = (v) => Number(v || 0);
const blankLine = () => ({ _k: uid(), control_head_id: "", issue_for: "", issue_ref: "", currency: "BDT", req_amt: "", amount: "", remarks: "" });
const blankIssue = (opt) => ({ bank_account_id: "", cheque_leaf_id: "", issue_date: opt.today, place_date: "", issue_type: "", issue_to_head_id: "", pay_to: "", issue_for: "", charges: "0", delivered: false, amount: "", acc_payee: false, fiscal_year_id: "", purpose: "", narration: "", issued_by: "", lines: [] });
const toForm = (d) => ({
  id: d.id,
  issue_ref: d.issue_ref,
  printed: d.printed,
  cancelled: d.cancelled,
  cheque_no: d.cheque_no,
  bank_account_id: d.bank_account_id,
  cheque_leaf_id: d.cheque_leaf_id,
  issue_date: d.issue_date,
  place_date: d.place_date ?? "",
  issue_type: d.issue_type,
  issue_to_head_id: d.issue_to_head_id,
  pay_to: d.pay_to,
  issue_for: d.issue_for ?? "",
  charges: String(d.charges ?? 0),
  delivered: d.delivered,
  amount: String(d.amount),
  acc_payee: d.acc_payee,
  fiscal_year_id: d.fiscal_year_id ?? "",
  purpose: d.purpose ?? "",
  narration: d.narration ?? "",
  issued_by: d.issued_by_id ?? "",
  lines: d.lines.map((l) => ({ _k: uid(), control_head_id: l.control_head_id, issue_for: l.issue_for ?? "", issue_ref: l.issue_ref ?? "", currency: l.currency, req_amt: l.req_amt === null ? "" : String(l.req_amt), amount: String(l.amount), remarks: l.remarks ?? "" })),
});

function printIssue(w, f, opt) {
  const label = (list, v) => opt[list].find((o) => o.value === v)?.label ?? "—";
  const acc = opt.accounts.find((a) => a.value === f.bank_account_id);
  printDocument({
    title: w("cq.print_title"),
    docNo: f.issue_ref,
    meta: [
      [w("cq.f.account"), acc?.label ?? "—"],
      [w("cq.f.bank"), [acc?.bank, acc?.branch].filter(Boolean).join(" — ") || "—"],
      [w("cq.f.cheque_no"), f.cheque_no ?? "—"],
      [w("cq.f.issue_date"), day(f.issue_date)],
      [w("cq.f.issue_type"), f.issue_type ? w(`cq.type.${f.issue_type}`) : "—"],
      [w("cq.f.pay_to"), f.pay_to],
      [w("cq.f.amount"), money(f.amount)],
      [w("cq.f.acc_payee"), w(`yn.${f.acc_payee ? "YES" : "NO"}`)],
      [w("cq.f.issue_to_head"), label("heads", f.issue_to_head_id)],
    ],
    sections: [
      {
        title: w("cq.box.detail"),
        columns: [{ label: "#", align: "right" }, { label: w("cq.col.head") }, { label: w("cq.col.issue_for") }, { label: w("cq.col.issue_ref") }, { label: w("cq.col.amount"), align: "right" }, { label: w("cq.col.remarks") }],
        rows: f.lines.map((l, i) => [i + 1, label("heads", l.control_head_id), l.issue_for || "—", l.issue_ref || "—", money(l.amount), l.remarks || "—"]),
        foot: [[w("cq.settled"), "", "", "", money(f.lines.reduce((a, l) => a + n(l.amount), 0)), ""]],
      },
    ],
    notes: [f.purpose, f.narration].filter(Boolean),
    signatures: true,
  });
}

function IssueForm({ w, form, set, opt, act, locked, used }) {
  const [leaves, setLeaves] = useState([]);
  const acc = opt.accounts.find((a) => a.value === form.bank_account_id);
  useEffect(() => {
    if (!form.bank_account_id) return setLeaves([]);
    act.read(`${P}/leaves?account=${form.bank_account_id}${form.cheque_leaf_id ? `&keep=${form.cheque_leaf_id}` : ""}`).then((r) => r?.status === "success" && setLeaves(r.data));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.bank_account_id, form.id]);
  const sel = (k, label, options, o = {}) => (
    <Field label={label} req={o.req}>
      <Select value={form[k] ?? ""} disabled={locked} onValueChange={(v) => (o.onPick ? o.onPick(v ?? "") : set(k, v ?? ""))} options={options} placeholder={o.placeholder ?? w("select")} aria-label={label} />
    </Field>
  );
  const inp = (k, label, type = "text", o = {}) => (
    <Field label={label} req={o.req}>
      <Input type={type} step={type === "number" ? "any" : undefined} value={form[k] ?? ""} disabled={locked} onChange={(e) => set(k, e.target.value)} placeholder={o.placeholder} aria-label={label} />
    </Field>
  );
  const chk = (k, label) => (
    <label className="flex items-center gap-2 self-end pb-2 text-sm font-semibold">
      <Checkbox checked={Boolean(form[k])} disabled={locked} onCheckedChange={(v) => set(k, v === true)} aria-label={label} /> {label}
    </label>
  );
  const area = (k, label) => (
    <Field label={label}>
      <textarea rows={2} value={form[k] ?? ""} disabled={locked} onChange={(e) => set(k, e.target.value)} aria-label={label} className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm disabled:bg-surface-muted" />
    </Field>
  );
  const settled = form.lines.reduce((a, l) => a + n(l.amount), 0);
  return (
    <>
      <Box legend={w("cq.box.master")} grid={false}>
        <div className="grid gap-x-6 gap-y-3 lg:grid-cols-3">
          {sel("bank_account_id", w("cq.f.account"), opt.accounts, { req: true, placeholder: w("cq.pick_account"), onPick: (v) => set("bank_account_id", v) || set("cheque_leaf_id", "") })}
          <Field label={w("f.company")} req>
            <Select value={opt.companies[0]?.value ?? ""} disabled options={opt.companies} aria-label={w("f.company")} />
          </Field>
          <Field label={w("cq.f.issue_ref")}>
            <Input value={form.issue_ref ?? ""} placeholder={w("generated_on_save")} disabled readOnly aria-label={w("cq.f.issue_ref")} />
          </Field>
          <Field label={w("cq.f.bank")}>
            <Input value={acc?.bank ?? ""} disabled readOnly aria-label={w("cq.f.bank")} />
          </Field>
          <Field label={w("cq.f.branch")}>
            <Input value={acc?.branch ?? ""} disabled readOnly aria-label={w("cq.f.branch")} />
          </Field>
          {sel("cheque_leaf_id", w("cq.f.cheque_no"), leaves, { req: true, placeholder: w("cq.pick_leaf") })}
          {inp("issue_date", w("cq.f.issue_date"), "date", { req: true })}
          {inp("place_date", w("cq.f.place_date"), "date")}
          {sel("issue_type", w("cq.f.issue_type"), opts(w, "cq.type", TYPES), { req: true, placeholder: w("cq.pick_type") })}
          {sel("issue_to_head_id", w("cq.f.issue_to_head"), opt.heads, { req: true, placeholder: w("cq.pick_head") })}
          {inp("pay_to", w("cq.f.pay_to"), "text", { req: true })}
          {inp("issue_for", w("cq.f.issue_for"), "text", { placeholder: w("cq.employee_id") })}
          {inp("charges", w("cq.f.charges"), "number")}
          {chk("delivered", w("cq.f.delivered"))}
          {inp("amount", w("cq.f.amount"), "number", { req: true })}
          {chk("acc_payee", w("cq.f.acc_payee"))}
          {sel("fiscal_year_id", w("cq.f.year"), [{ value: "", label: w("cq.pick_year") }, ...opt.years])}
          {sel("issued_by", w("cq.f.issued_by"), [{ value: "", label: w("select") }, ...opt.users])}
          {area("purpose", w("cq.f.purpose"))}
          {area("narration", w("f.narration"))}
        </div>
      </Box>
      <EditGrid
        w={w}
        title={w("cq.box.detail")}
        hint={w("cq.box.detail_hint")}
        editable={!locked}
        addLabel={w("add_line")}
        onAdd={() => set("lines", [...form.lines, blankLine()])}
        rows={form.lines}
        setRows={(v) => set("lines", v)}
        emptyNote={w("cq.no_lines")}
        cols={[
          { key: "control_head_id", label: w("cq.col.head"), type: "select", options: opt.heads, width: 240 },
          { key: "issue_for", label: w("cq.col.issue_for"), max: 120, width: 150 },
          { key: "issue_ref", label: w("cq.col.issue_ref"), max: 48, width: 120 },
          { key: "currency", label: w("col.currency"), read: (r) => r.currency || "BDT" },
          { key: "req_amt", label: w("cq.col.req_amt"), type: "number", align: "right" },
          { key: "used", label: w("cq.col.used_amt"), align: "right", read: (r) => (r.issue_ref ? money(used[r.issue_ref] ?? 0) : "—") },
          { key: "balance", label: w("cq.col.balance"), align: "right", read: (r) => (r.issue_ref && r.req_amt !== "" ? money(n(r.req_amt) - (used[r.issue_ref] ?? 0)) : "—") },
          { key: "amount", label: w("cq.col.amount"), type: "number", align: "right" },
          { key: "remarks", label: w("cq.col.remarks"), max: 300, width: 160 },
        ]}
      />
      <p className="text-right text-sm font-semibold">{w("cq.settled_line", { settled: money(settled), amount: money(form.amount) })}</p>
    </>
  );
}

function ChequeIssueDesk({ initial, opt, canManage, act, query }) {
  const w = useW();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [tab, setTab] = useState(query?.no ? "entry" : "records");
  const [filter, setFilter] = useState({});
  const [form, setForm] = useState(blankIssue(opt));
  const [used, setUsed] = useState({});
  const [asking, setAsking] = useState(false);
  const [pending, startTransition] = useTransition();
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const reload = () => act.read(P).then((r) => r?.status === "success" && setRows(r.data));
  const refs = [...new Set(form.lines.map((l) => l.issue_ref.trim()).filter(Boolean))];
  useEffect(() => {
    const t = setTimeout(() => {
      refs.forEach((ref) => act.read(`${P}/used?ref=${encodeURIComponent(ref)}${form.id ? `&except=${form.id}` : ""}`).then((r) => r?.status === "success" && setUsed((u) => ({ ...u, [ref]: r.data.used }))));
    }, 400);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [refs.join("|"), form.id]);
  const reset = () => {
    setForm(blankIssue(opt));
    setUsed({});
    address("no", null);
  };
  const open = (id) =>
    startTransition(async () => {
      const res = await act.read(`${P}/${id}`);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "danger" });
      setForm(toForm(res.data));
      setUsed({});
      setTab("entry");
      address("no", res.data.issue_ref);
    });
  useEffect(() => {
    const hit = query?.no && initial.find((r) => r.issue_ref === query.no);
    if (hit) open(hit.id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const save = () =>
    startTransition(async () => {
      const keys = ["bank_account_id", "cheque_leaf_id", "issue_date", "place_date", "issue_type", "issue_to_head_id", "pay_to", "issue_for", "charges", "amount", "fiscal_year_id", "purpose", "narration", "issued_by"];
      const body = { ...Object.fromEntries(keys.map((k) => [k, blank(form[k] ?? "")])), delivered: Boolean(form.delivered), acc_payee: Boolean(form.acc_payee), lines: form.lines.map((l) => ({ control_head_id: l.control_head_id || null, issue_for: blank(l.issue_for), issue_ref: blank(l.issue_ref), req_amt: l.req_amt === "" ? null : l.req_amt, amount: blank(l.amount), remarks: blank(l.remarks) })) };
      const res = await act.write(form.id ? "PUT" : "POST", form.id ? `${P}/${form.id}` : P, body);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
      toastManager.add({ title: w("cq.saved", { no: res.data.issue_ref }), type: "success" });
      setForm(toForm(res.data));
      address("no", res.data.issue_ref);
      reload();
    });
  const chequePrint = () =>
    startTransition(async () => {
      const res = await act.write("POST", `${P}/${form.id}/print`);
      setAsking(false);
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
      toastManager.add({ title: w("cq.printed_done", { no: res.data.issue_ref }), type: "success" });
      setForm(toForm(res.data));
      reload();
    });
  const filters = [
    { name: "company", label: w("f.company"), type: "select", options: opt.companies, test: () => true },
    { name: "bank", label: w("cq.f.bank_name"), type: "select", options: opt.banks, test: (r, v) => !v || r.bank_id === v },
    { name: "branch", label: w("cq.f.branch_name"), type: "select", options: opt.branches, test: (r, v) => !v || r.branch_id === v },
    { name: "ref", label: w("cq.f.issue_ref"), test: (r, v) => has(r.issue_ref, v) },
    { name: "cheque", label: w("cq.f.cheque_no"), test: (r, v) => has(r.cheque_no, v) },
    { name: "account", label: w("cq.f.account_no"), test: (r, v) => has(r.account_no, v) },
    { name: "final", label: w("cq.f.with_finalized"), type: "select", options: [{ value: "yes", label: w("yn.YES") }, { value: "no", label: w("yn.NO") }], test: (r, v) => !v || r.final === (v === "yes") },
    { name: "from", label: w("cq.f.from_issue"), type: "date", test: (r, v) => within(r.issue_date, v, null) },
    { name: "to", label: w("cq.f.to_issue"), type: "date", test: (r, v) => within(r.issue_date, null, v) },
    { name: "line", label: w("cq.f.line_ref"), test: (r, v) => has(r.issue_refs, v) },
  ];
  const yn = (v) => w(`yn.${v ? "YES" : "NO"}`);
  const shown = rows.map((r) => ({ ...r, printed_label: yn(r.printed), payee_label: yn(r.acc_payee), final_label: r.cancelled ? w("cq.cancelled") : yn(r.final) })).filter((r) => filters.every((f) => f.test(r, filter[f.name])));
  const locked = !canManage || form.printed || form.cancelled;
  return (
    <div className="flex flex-col gap-4">
      <Tabs active={tab} onPick={setTab} tabs={[{ key: "records", label: w("cq.tab.list"), icon: <Table2 className="size-4" />, count: rows.length }, ...(canManage || form.id ? [{ key: "entry", label: w("cq.tab.entry"), icon: <Plus className="size-4" /> }] : [])]} />
      {tab === "records" ? (
        <>
          <Filters
            w={w}
            value={filter}
            onChange={setFilter}
            fields={filters}
            note={w("cq.filter_note")}
            extra={
              canManage ? (
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => {
                    reset();
                    setTab("entry");
                  }}
                >
                  <Plus /> {w("add_new")}
                </Button>
              ) : null
            }
          />
          <Register
            w={w}
            rows={shown}
            refKey="issue_ref"
            exportName="cheque-issues"
            onDone={reload}
            emptyTitle={w("empty")}
            emptyText=""
            actions={[
              {
                key: "print",
                label: w("print"),
                open: async (r) => {
                  const res = await act.read(`${P}/${r.id}`);
                  if (res?.status === "success") printIssue(w, toForm(res.data), opt);
                },
              },
              { key: "details", label: w("cq.details"), open: (r) => open(r.id) },
              ...(canManage ? [{ key: "delete", label: w("delete"), when: (r) => !r.locked, confirm: w("confirm.delete"), done: w("done.delete"), destructive: true, run: (no, _e, r) => act.write("DELETE", `${P}/${r.id}`) }] : []),
            ]}
            columns={[
              { key: "printed_label", label: w("cq.col.printed"), type: "badge" },
              { key: "payee_label", label: w("cq.col.acc_payee"), type: "badge" },
              { key: "final_label", label: w("cq.col.finalized"), type: "badge" },
              { key: "issue_ref", label: w("cq.f.issue_ref"), type: "mono" },
              { key: "issue_refs", label: w("cq.col.issue_refs") },
              { key: "issue_date", label: w("cq.col.issued"), type: "date" },
              { key: "account_no", label: w("cq.f.account_no") },
              { key: "cheque_no", label: w("cq.f.cheque_no"), type: "mono" },
              { key: "company", label: w("col.company") },
              { key: "bank", label: w("cq.f.bank") },
              { key: "branch", label: w("cq.f.branch") },
              { key: "pay_to", label: w("cq.col.pay_to") },
              { key: "amount", label: w("cq.col.amount"), type: "number", align: "right" },
              { key: "purpose", label: w("cq.f.purpose") },
              { key: "narration", label: w("f.narration") },
              { key: "issued_by", label: w("cq.f.issued_by") },
              { key: "updated_by", label: w("cq.col.updated_by") },
            ]}
          />
          <p className="text-xs text-foreground-muted">{w("cq.form_note")}</p>
        </>
      ) : (
        <>
          {form.cancelled ? <p className="rounded-lg bg-danger-subtle px-4 py-2 text-sm text-danger">{w("cq.cancelled_note", { no: form.issue_ref })}</p> : form.printed ? <p className="rounded-lg bg-warning-subtle px-4 py-2 text-sm text-warning">{w("cq.printed_note", { no: form.issue_ref })}</p> : null}
          <div className="max-w-3xl">
            <Field label={w("cq.f.existing")}>
              <Select value={form.id ?? ""} onValueChange={(v) => (v ? open(v) : reset())} options={[{ value: "", label: w("cq.new_issue") }, ...rows.map((r) => ({ value: r.id, label: `${r.issue_ref} · ${r.cheque_no ?? ""} · ${r.pay_to}` }))]} aria-label={w("cq.f.existing")} />
            </Field>
          </div>
          <IssueForm w={w} form={form} set={set} opt={opt} act={act} locked={locked} used={used} />
          <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface p-4">
            <p className="min-w-0 flex-1 text-[13px] text-foreground-muted">{form.id && !form.printed ? w("cq.print_warning") : w("cq.form_note")}</p>
            {form.id ? (
              <>
                {canManage && !form.printed && !form.cancelled ? (
                  <Button variant="outline" className="text-danger" onClick={() => setAsking(true)} disabled={pending}>
                    <Stamp /> {w("cq.cheque_print")}
                  </Button>
                ) : null}
                <Button variant="outline" onClick={() => printIssue(w, form, opt)}>
                  <Printer /> {w("print")}
                </Button>
              </>
            ) : null}
            <Button
              variant="outline"
              onClick={() => {
                setTab("records");
                reload();
              }}
              disabled={pending}
            >
              <ArrowLeft /> {w("back")}
            </Button>
            {!locked ? (
              <>
                <Button variant="outline" onClick={reset} disabled={pending}>
                  <RotateCcw /> {w("clear")}
                </Button>
                <Button onClick={save} loading={pending}>
                  <Save /> {w("cq.save")}
                </Button>
              </>
            ) : null}
          </div>
        </>
      )}
      <ConfirmDialog open={asking} onOpenChange={(o) => !o && setAsking(false)} title={w("cq.cheque_print")} description={w("cq.confirm_print")} confirmLabel={w("cq.cheque_print")} cancelLabel={w("od.reg.keep")} destructive loading={pending} onConfirm={chequePrint} />
    </div>
  );
}

/* ───────────────────────── Cheque Cancellation ───────────────────────── */

function ChequeCancelDesk({ initial, opt, canManage, act }) {
  const w = useW();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(initial);
  const [filter, setFilter] = useState({});
  const [dialog, setDialog] = useState(null);
  const [leaves, setLeaves] = useState([]);
  const [pending, startTransition] = useTransition();
  const reload = () => act.read(`${P}/cancellations`).then((r) => r?.status === "success" && setRows(r.data));
  const openNew = () => {
    setDialog({ cheque_leaf_id: "", issue_ref: "", remarks: "" });
    act.read(`${P}/cancellations/leaves`).then((r) => r?.status === "success" && setLeaves(r.data));
  };
  const save = () =>
    startTransition(async () => {
      const res = await act.write("POST", `${P}/cancellations`, { cheque_leaf_id: dialog.cheque_leaf_id || null, issue_ref: blank(dialog.issue_ref), remarks: blank(dialog.remarks) });
      if (res?.status !== "success") return toastManager.add({ title: firstError(res), type: "warning" });
      toastManager.add({ title: w("cq.cancel_done", { no: res.data.cheque_no }), type: "success" });
      setDialog(null);
      reload();
    });
  const filters = [
    { name: "company", label: w("cq.f.company_name"), type: "select", options: opt.companies, test: () => true },
    { name: "bank", label: w("cq.f.bank"), type: "select", options: opt.banks, test: (r, v) => !v || r.bank_id === v },
    { name: "branch", label: w("cq.f.bank_branch"), type: "select", options: opt.branches, test: (r, v) => !v || r.branch_id === v },
    { name: "type", label: w("cq.f.account_type"), test: (r, v) => has(r.account_type, v) },
    { name: "book", label: w("cq.f.book_no"), test: (r, v) => has(r.book_no, v) },
    { name: "from", label: w("cq.f.from_receive"), type: "date", test: (r, v) => within(r.receive_date, v, null) },
    { name: "to", label: w("cq.f.to_receive"), type: "date", test: (r, v) => within(r.receive_date, null, v) },
  ];
  const shown = rows.map((r) => ({ ...r, active_label: w(`yn.${r.active ? "YES" : "NO"}`) })).filter((r) => filters.every((f) => f.test(r, filter[f.name])));
  const columns = [
    { key: "book_no", label: w("cq.f.book_no") },
    { key: "cheque_no", label: w("cq.f.cheque_no"), type: "mono" },
    { key: "cancelled_no", label: w("cq.col.cancelled_no"), type: "mono" },
    { key: "issue_ref", label: w("cq.col.issue_ref") },
    { key: "account_no", label: w("cq.f.account_no") },
    { key: "account_type", label: w("cq.f.account_type") },
    { key: "remarks", label: w("cq.col.remarks") },
    { key: "active_label", label: w("cq.col.active"), type: "badge" },
  ];
  const pdf = () =>
    printDocument({ title: w("cq.cancel_register"), orientation: "landscape", sections: [{ columns: columns.map((c) => ({ label: c.label })), rows: shown.map((r) => columns.map((c) => r[c.key] ?? "—")) }] });
  return (
    <div className="flex flex-col gap-4">
      <Filters
        w={w}
        value={filter}
        onChange={setFilter}
        fields={filters}
        note={w("cq.cancel_filter_note")}
        extra={
          <>
            {canManage ? (
              <Button type="button" variant="outline" onClick={openNew}>
                <Ban /> {w("cq.new_cancel")}
              </Button>
            ) : null}
            <Button type="button" variant="outline" onClick={pdf} disabled={!shown.length}>
              <FileText /> {w("pdf")}
            </Button>
          </>
        }
      />
      <Register
        w={w}
        rows={shown}
        refKey="id"
        exportName="cheque-cancellations"
        onDone={reload}
        emptyTitle={w("empty")}
        emptyText=""
        actions={canManage ? [{ key: "restore", label: w("cq.restore"), confirm: w("cq.confirm_restore"), done: w("cq.restored"), destructive: true, run: (no, _e, r) => act.write("DELETE", `${P}/cancellations/${r.id}`) }] : []}
        columns={columns}
      />
      <p className="text-xs text-foreground-muted">{w("cq.cancel_form_note")}</p>
      <Modal open={dialog !== null} onOpenChange={(o) => !o && setDialog(null)} title={w("cq.new_cancel")} className="max-w-lg">
        {dialog ? (
          <div className="flex flex-col gap-3">
            <Field label={w("cq.f.cheque_no")} req>
              <Select
                value={dialog.cheque_leaf_id}
                onValueChange={(v) => {
                  const leaf = leaves.find((l) => l.value === v);
                  setDialog({ ...dialog, cheque_leaf_id: v ?? "", issue_ref: leaf?.issue_ref ?? dialog.issue_ref });
                }}
                options={leaves}
                placeholder={w("select")}
                aria-label={w("cq.f.cheque_no")}
              />
            </Field>
            <Field label={w("cq.col.issue_ref")}>
              <Input value={dialog.issue_ref} maxLength={48} onChange={(e) => setDialog({ ...dialog, issue_ref: e.target.value })} aria-label={w("cq.col.issue_ref")} />
            </Field>
            <Field label={w("cq.col.remarks")} req>
              <textarea rows={3} value={dialog.remarks} onChange={(e) => setDialog({ ...dialog, remarks: e.target.value })} aria-label={w("cq.col.remarks")} className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm" />
            </Field>
            <div className="flex justify-end gap-3">
              <Button variant="outline" onClick={() => setDialog({ cheque_leaf_id: "", issue_ref: "", remarks: "" })} disabled={pending}>
                <RotateCcw /> {w("reset")}
              </Button>
              <Button onClick={save} loading={pending}>
                <Save /> {w("cq.save_cancel")}
              </Button>
            </div>
          </div>
        ) : null}
      </Modal>
    </div>
  );
}

export { ChequeIssueDesk, ChequeCancelDesk };
