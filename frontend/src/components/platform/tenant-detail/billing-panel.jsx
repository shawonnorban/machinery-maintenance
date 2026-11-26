"use client";

import { useActionState, useState, useTransition } from "react";
import { Plus, FileCheck, Wallet, Ban, FileText, Receipt } from "lucide-react";
import { Panel, PTable, Facts, fmtDate } from "@/components/platform/ui";
import { Card, CardHeader, CardTitle, CardBody, CardFooter } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { DatePicker } from "@/components/ui/date-picker";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { StatusBadge } from "@/components/ui/status-badge";
import { formatCurrency } from "@/lib/format";
import { useToastManager } from "@/components/ui/toast";

const BILLING_CYCLES = [
  { value: "MONTHLY", label: "Monthly" },
  { value: "QUARTERLY", label: "Quarterly" },
  { value: "YEARLY", label: "Yearly" },
];
const OVERAGE_POLICIES = [
  { value: "BLOCK", label: "Block" },
  { value: "ALLOW_AND_BILL", label: "Allow and bill" },
  { value: "WARN_ONLY", label: "Warn only" },
];
const PAYMENT_METHODS = ["BANK_TRANSFER", "CASH", "CHEQUE", "CARD", "MOBILE", "GATEWAY"].map((m) => ({
  value: m,
  label: m.replace("_", " "),
}));

/**
 * A customer's contracts and invoices (SRS 40). "No mandatory fixed
 * packages" — every term is set per customer, so the contract form takes a
 * full set of terms, not a plan key (mirrors `_contract.blade.php`/
 * `_invoices.blade.php`).
 */
function BillingPanel({ company, contracts, invoices, contractAction, entitlementsAction, invoiceActions }) {
  const currentContract = contracts[0] ?? null;

  return (
    <div className="flex flex-col gap-4">
      <ContractCard company={company} currentContract={currentContract} contracts={contracts} contractAction={contractAction} entitlementsAction={entitlementsAction} />
      <InvoicesCard company={company} currentContract={currentContract} invoices={invoices} invoiceActions={invoiceActions} />
    </div>
  );
}

function ContractCard({ company, currentContract, contracts, contractAction, entitlementsAction }) {
  const [formOpen, setFormOpen] = useState(!currentContract);

  return (
    <Panel
      title="Contract"
      subtitle={currentContract ? `${currentContract.contract_number} · every term set for this customer` : "No contract yet — the terms below make the first one"}
      icon={FileText}
      action={
        currentContract && !formOpen ? (
          <>
            <EntitlementsForm contract={currentContract} action={entitlementsAction} />
            <Button size="sm" variant="outline" onClick={() => setFormOpen(true)}>
              <Plus /> New contract
            </Button>
          </>
        ) : null
      }
      bodyClassName="flex flex-col gap-4 p-5"
    >
      {currentContract ? (
        <Facts
          items={[
            ["Status", <StatusBadge key="s" status={currentContract.status} />],
            ["Amount", `${formatCurrency(currentContract.amount, currentContract.currency)} / ${currentContract.billing_cycle.toLowerCase()}`],
            ["Started", fmtDate(currentContract.start_date)],
            ["Ends", currentContract.end_date ? fmtDate(currentContract.end_date) : "Open-ended"],
            ["Factories", currentContract.included_factories ?? "Unlimited"],
            ["Assets", currentContract.included_assets ?? "Unlimited"],
            ["Users", currentContract.included_users ?? "Unlimited"],
            ["Overage policy", currentContract.overage_policy.replaceAll("_", " ")],
          ]}
        />
      ) : null}
      {formOpen ? <ContractForm company={company} action={contractAction} onCancel={currentContract ? () => setFormOpen(false) : null} /> : null}
      {contracts.length > 1 ? (
        <div>
          <p className="mb-2 text-[13px] font-semibold text-foreground">Contract history ({contracts.length})</p>
          <div className="overflow-hidden rounded-md border border-border">
            <PTable
              dense
              rows={contracts}
              rowKey={(c) => c.id}
              columns={[
                { key: "contract_number", label: "Contract No", render: (c) => <span className="font-mono">{c.contract_number}</span> },
                { key: "status", label: "Status", render: (c) => <StatusBadge status={c.status} /> },
                { key: "amount", label: "Amount", align: "right", render: (c) => formatCurrency(c.amount, c.currency) },
                { key: "period", label: "Period", render: (c) => `${fmtDate(c.start_date)} → ${c.end_date ? fmtDate(c.end_date) : "open"}` },
              ]}
            />
          </div>
        </div>
      ) : null}
    </Panel>
  );
}

function ContractForm({ company, action, onCancel }) {
  const [state, dispatch, pending] = useActionState(action, null);

  return (
    <form action={dispatch} className="flex flex-col gap-3 rounded-sm border border-border p-4">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormField label="Contract number" required error={state?.errors?.contract_number?.[0]}>
          {(p) => <Input {...p} name="contract_number" maxLength={32} required />}
        </FormField>
        <FormField label="Billing cycle" required>
          {() => <Select name="billing_cycle" defaultValue="MONTHLY" options={BILLING_CYCLES} />}
        </FormField>
        <FormField label="Start date" required error={state?.errors?.start_date?.[0]}>
          {() => <StartDateField />}
        </FormField>
        <FormField label="End date (optional)">{() => <EndDateField />}</FormField>
        <FormField label="Amount" required error={state?.errors?.amount?.[0]}>
          {(p) => <Input {...p} name="amount" type="number" step="0.01" min="0" required />}
        </FormField>
        <FormField label="Currency" required>
          {(p) => <Input {...p} name="currency" defaultValue={company.base_currency} maxLength={3} required />}
        </FormField>
        <FormField label="Grace period (days)" required>
          {(p) => <Input {...p} name="grace_period_days" type="number" min="0" max="180" defaultValue={14} required />}
        </FormField>
        <FormField label="Overage policy" required>
          {() => <Select name="overage_policy" defaultValue="WARN_ONLY" options={OVERAGE_POLICIES} />}
        </FormField>
        <FormField label="Included factories" helperText="Blank = unlimited">
          {(p) => <Input {...p} name="included_factories" type="number" min="0" />}
        </FormField>
        <FormField label="Included assets" helperText="Blank = unlimited">
          {(p) => <Input {...p} name="included_assets" type="number" min="0" />}
        </FormField>
        <FormField label="Included users" helperText="Blank = unlimited">
          {(p) => <Input {...p} name="included_users" type="number" min="0" />}
        </FormField>
        <FormField label="Trial ends (optional)">{() => <TrialEndField />}</FormField>
      </div>
      <label className="flex items-center gap-2 text-sm text-foreground">
        <Checkbox name="auto_renew" defaultChecked /> Auto-renew
      </label>
      <FormField label="Notes">{(p) => <Textarea {...p} name="notes" rows={2} maxLength={2000} />}</FormField>
      <div className="flex justify-end gap-2">
        {onCancel ? (
          <Button type="button" variant="outline" onClick={onCancel}>
            Cancel
          </Button>
        ) : null}
        <Button type="submit" loading={pending}>
          Save contract
        </Button>
      </div>
    </form>
  );
}

function StartDateField() {
  const [value, setValue] = useState(new Date().toISOString().slice(0, 10));
  return (
    <>
      <input type="hidden" name="start_date" value={value ?? ""} />
      <DatePicker value={value} onChange={setValue} />
    </>
  );
}
function EndDateField() {
  const [value, setValue] = useState(null);
  return (
    <>
      <input type="hidden" name="end_date" value={value ?? ""} />
      <DatePicker value={value} onChange={setValue} />
    </>
  );
}
function TrialEndField() {
  const [value, setValue] = useState(null);
  return (
    <>
      <input type="hidden" name="trial_end" value={value ?? ""} />
      <DatePicker value={value} onChange={setValue} />
    </>
  );
}

function EntitlementsForm({ contract, action }) {
  const [open, setOpen] = useState(false);
  const [state, dispatch, pending] = useActionState(action, null);

  return (
    <>
      <Button size="sm" variant="outline" onClick={() => setOpen(true)}>
        Edit limits
      </Button>
      <Modal open={open} onOpenChange={setOpen} title="Edit entitlements" description="The numbers somebody actually adjusts day to day, without superseding the whole contract.">
        <form action={dispatch} className="flex flex-col gap-3">
          {state?.status === "error" ? <Alert variant="danger">{state.message}</Alert> : null}
          <FormField label="Included factories" helperText="Blank = unlimited">
            {(p) => <Input {...p} name="included_factories" type="number" min="0" defaultValue={contract.included_factories ?? ""} />}
          </FormField>
          <FormField label="Included assets" helperText="Blank = unlimited">
            {(p) => <Input {...p} name="included_assets" type="number" min="0" defaultValue={contract.included_assets ?? ""} />}
          </FormField>
          <FormField label="Included users" helperText="Blank = unlimited">
            {(p) => <Input {...p} name="included_users" type="number" min="0" defaultValue={contract.included_users ?? ""} />}
          </FormField>
          <FormField label="Overage policy" required>
            {() => <Select name="overage_policy" defaultValue={contract.overage_policy} options={OVERAGE_POLICIES} />}
          </FormField>
          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" loading={pending}>
              Save
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}

function InvoicesCard({ company, currentContract, invoices, invoiceActions }) {
  const [draftOpen, setDraftOpen] = useState(false);

  return (
    <Panel
      title="Invoices"
      subtitle={`${invoices.length} invoice${invoices.length === 1 ? "" : "s"}${currentContract ? "" : " · a contract comes first"}`}
      icon={Receipt}
      action={
        <Button size="sm" onClick={() => setDraftOpen(true)} disabled={!currentContract}>
          <Plus /> Draft invoice
        </Button>
      }
    >
      <PTable
        rows={invoices}
        rowKey={(inv) => inv.id}
        empty="No invoices yet."
        columns={[
          { key: "invoice_number", label: "Invoice No", render: (inv) => <b className="font-mono">{inv.invoice_number}</b> },
          { key: "issue_date", label: "Issued", render: (inv) => fmtDate(inv.issue_date) },
          { key: "due_date", label: "Due", render: (inv) => fmtDate(inv.due_date) },
          { key: "total", label: "Total", align: "right", render: (inv) => formatCurrency(inv.total, inv.currency) },
          {
            key: "balance_due",
            label: "Balance",
            align: "right",
            render: (inv) => (inv.status === "VOID" || Number(inv.balance_due) === 0 ? <span className="text-foreground-muted">—</span> : <b className="text-danger">{formatCurrency(inv.balance_due, inv.currency)}</b>),
          },
          { key: "status", label: "Status", render: (inv) => <StatusBadge status={inv.is_overdue ? "OVERDUE" : inv.status} /> },
          { key: "act", label: "", align: "right", render: (inv) => <InvoiceActions invoice={inv} invoiceActions={invoiceActions} /> },
        ]}
      />
      <DraftInvoiceModal open={draftOpen} onOpenChange={setDraftOpen} action={invoiceActions.draft} />
    </Panel>
  );
}

function DraftInvoiceModal({ open, onOpenChange, action }) {
  const [state, dispatch, pending] = useActionState(action, null);

  return (
    <Modal open={open} onOpenChange={onOpenChange} title="Draft an invoice" description="A draft can be corrected; issuing is the point of no return for its totals.">
      <form action={dispatch} className="flex flex-col gap-3">
        {state?.status === "error" ? <Alert variant="danger">{state.message}</Alert> : null}
        <FormField label="Period start" required>{() => <PeriodStartField />}</FormField>
        <FormField label="Period end" required>{() => <PeriodEndField />}</FormField>
        <FormField label="Tax rate (%)" helperText="Optional">
          {(p) => <Input {...p} name="tax_rate" type="number" step="0.01" min="0" max="100" />}
        </FormField>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit" loading={pending}>
            Draft invoice
          </Button>
        </div>
      </form>
    </Modal>
  );
}
function PeriodStartField() {
  const [value, setValue] = useState(null);
  return (
    <>
      <input type="hidden" name="period_start" value={value ?? ""} />
      <DatePicker value={value} onChange={setValue} />
    </>
  );
}
function PeriodEndField() {
  const [value, setValue] = useState(null);
  return (
    <>
      <input type="hidden" name="period_end" value={value ?? ""} />
      <DatePicker value={value} onChange={setValue} />
    </>
  );
}

function InvoiceActions({ invoice, invoiceActions }) {
  const [pending, startTransition] = useTransition();
  const [payOpen, setPayOpen] = useState(false);
  const [voidOpen, setVoidOpen] = useState(false);
  const toastManager = useToastManager();

  function issue() {
    startTransition(async () => {
      const result = await invoiceActions.issue(invoice.id);
      if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  return (
    <div className="flex items-center justify-end gap-1.5">
      {invoice.status === "DRAFT" ? (
        <Button size="sm" variant="outline" loading={pending} onClick={issue}>
          <FileCheck /> Issue
        </Button>
      ) : null}
      {["ISSUED", "PARTIALLY_PAID", "OVERDUE"].includes(invoice.status) ? (
        <Button size="sm" variant="outline" onClick={() => setPayOpen(true)}>
          <Wallet /> Payment
        </Button>
      ) : null}
      {invoice.status !== "VOID" && invoice.status !== "PAID" ? (
        <Button size="sm" variant="outline" onClick={() => setVoidOpen(true)} aria-label="Void" title="Void">
          <Ban />
        </Button>
      ) : null}
      <PayInvoiceModal open={payOpen} onOpenChange={setPayOpen} invoice={invoice} action={invoiceActions.pay} />
      <VoidInvoiceModal open={voidOpen} onOpenChange={setVoidOpen} invoice={invoice} action={invoiceActions.void} />
    </div>
  );
}

function PayInvoiceModal({ open, onOpenChange, invoice, action }) {
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function submit(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    startTransition(async () => {
      const result = await action(invoice.id, formData);
      if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      } else {
        onOpenChange(false);
      }
    });
  }

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={`Record a payment for ${invoice.invoice_number}`}>
      <form onSubmit={submit} className="flex flex-col gap-3">
        <FormField label="Amount" required>
          {(p) => <Input {...p} name="amount" type="number" step="0.01" min="0.01" max={invoice.balance_due} defaultValue={invoice.balance_due} required />}
        </FormField>
        <FormField label="Method" required>
          {() => <Select name="method" defaultValue="BANK_TRANSFER" options={PAYMENT_METHODS} />}
        </FormField>
        <FormField label="Reference">{(p) => <Input {...p} name="payment_reference" maxLength={255} />}</FormField>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit" loading={pending}>
            Record payment
          </Button>
        </div>
      </form>
    </Modal>
  );
}

function VoidInvoiceModal({ open, onOpenChange, invoice, action }) {
  const [reason, setReason] = useState("");
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function submit() {
    startTransition(async () => {
      const result = await action(invoice.id, reason);
      if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      } else {
        onOpenChange(false);
      }
    });
  }

  return (
    <Modal
      open={open}
      onOpenChange={onOpenChange}
      title={`Void ${invoice.invoice_number}?`}
      description="Voided rather than deleted, with the reason kept — an invoice number never simply disappears."
      footer={
        <>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button variant="danger" loading={pending} disabled={reason.trim().length < 5} onClick={submit}>
            Void invoice
          </Button>
        </>
      }
    >
      <FormField label="Reason" required>
        {(p) => <Textarea {...p} value={reason} onChange={(e) => setReason(e.target.value)} rows={2} minLength={5} maxLength={500} required />}
      </FormField>
    </Modal>
  );
}

export { BillingPanel };
