"use client";

import { useActionState, useState, useTransition } from "react";
import { Plus, FileCheck, Wallet, Ban } from "lucide-react";
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
    <div className="flex flex-col gap-5">
      <ContractCard company={company} currentContract={currentContract} contracts={contracts} contractAction={contractAction} entitlementsAction={entitlementsAction} />
      <InvoicesCard company={company} currentContract={currentContract} invoices={invoices} invoiceActions={invoiceActions} />
    </div>
  );
}

function ContractCard({ company, currentContract, contracts, contractAction, entitlementsAction }) {
  const [formOpen, setFormOpen] = useState(!currentContract);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Contract</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-4">
        {currentContract ? (
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <Field label="Status"><StatusBadge status={currentContract.status} /></Field>
            <Field label="Amount">{formatCurrency(currentContract.amount, currentContract.currency)} / {currentContract.billing_cycle.toLowerCase()}</Field>
            <Field label="Started">{currentContract.start_date}</Field>
            <Field label="Ends">{currentContract.end_date ?? "—"}</Field>
            <Field label="Factories">{currentContract.included_factories ?? "Unlimited"}</Field>
            <Field label="Assets">{currentContract.included_assets ?? "Unlimited"}</Field>
            <Field label="Users">{currentContract.included_users ?? "Unlimited"}</Field>
            <Field label="Overage policy">{currentContract.overage_policy.replace("_", " ")}</Field>
          </div>
        ) : (
          <p className="text-sm text-foreground-muted">No contract yet.</p>
        )}

        {currentContract && !formOpen ? (
          <div className="flex gap-2">
            <EntitlementsForm contract={currentContract} action={entitlementsAction} />
            <Button size="sm" variant="outline" onClick={() => setFormOpen(true)}>
              New contract
            </Button>
          </div>
        ) : null}

        {formOpen ? <ContractForm company={company} action={contractAction} onCancel={currentContract ? () => setFormOpen(false) : null} /> : null}

        {contracts.length > 1 ? (
          <details className="mt-2">
            <summary className="cursor-pointer text-xs font-medium text-foreground-muted">
              Contract history ({contracts.length})
            </summary>
            <div className="mt-2 flex flex-col divide-y divide-border">
              {contracts.map((c) => (
                <div key={c.id} className="flex items-center justify-between gap-3 py-2 text-xs">
                  <span className="font-mono">{c.contract_number}</span>
                  <StatusBadge status={c.status} />
                  <span className="text-foreground-muted">{c.start_date} – {c.end_date ?? "open"}</span>
                </div>
              ))}
            </div>
          </details>
        ) : null}
      </CardBody>
    </Card>
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
    <Card>
      <CardHeader>
        <CardTitle>Invoices</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-3">
        {invoices.length === 0 ? (
          <p className="text-sm text-foreground-muted">No invoices yet.</p>
        ) : (
          <div className="flex flex-col divide-y divide-border">
            {invoices.map((invoice) => (
              <InvoiceRow key={invoice.id} invoice={invoice} invoiceActions={invoiceActions} />
            ))}
          </div>
        )}
      </CardBody>
      <CardFooter>
        <Button size="sm" variant="outline" onClick={() => setDraftOpen(true)} disabled={!currentContract}>
          <Plus /> Draft invoice
        </Button>
        {!currentContract ? <span className="ml-2 text-xs text-foreground-subtle">Needs a contract first</span> : null}
      </CardFooter>

      <DraftInvoiceModal open={draftOpen} onOpenChange={setDraftOpen} action={invoiceActions.draft} />
    </Card>
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

function InvoiceRow({ invoice, invoiceActions }) {
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
    <div className="flex flex-wrap items-center justify-between gap-3 py-3">
      <div>
        <p className="text-sm font-medium text-foreground">{invoice.invoice_number}</p>
        <p className="text-xs text-foreground-muted">
          {invoice.issue_date} · due {invoice.due_date} · {formatCurrency(invoice.total, invoice.currency)}
          {invoice.balance_due !== "0.0000" && invoice.status !== "VOID" ? (
            <> · balance {formatCurrency(invoice.balance_due, invoice.currency)}</>
          ) : null}
        </p>
      </div>
      <div className="flex items-center gap-2">
        <StatusBadge status={invoice.is_overdue ? "OVERDUE" : invoice.status} />
        {invoice.status === "DRAFT" ? (
          <Button size="sm" variant="outline" loading={pending} onClick={issue}>
            <FileCheck /> Issue
          </Button>
        ) : null}
        {["ISSUED", "PARTIALLY_PAID", "OVERDUE"].includes(invoice.status) ? (
          <Button size="sm" variant="outline" onClick={() => setPayOpen(true)}>
            <Wallet /> Record payment
          </Button>
        ) : null}
        {invoice.status !== "VOID" && invoice.status !== "PAID" ? (
          <Button size="sm" variant="outline" onClick={() => setVoidOpen(true)}>
            <Ban /> Void
          </Button>
        ) : null}
      </div>

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

function Field({ label, children }) {
  return (
    <div>
      <p className="text-xs font-medium text-foreground-muted">{label}</p>
      <p className="text-sm text-foreground">{children}</p>
    </div>
  );
}

export { BillingPanel };
