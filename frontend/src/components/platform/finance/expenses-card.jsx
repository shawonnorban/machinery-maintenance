"use client";

import { useActionState, useState, useTransition } from "react";
import { Plus, Trash2 } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody, CardFooter } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { formatCurrency } from "@/lib/format";

const CATEGORIES = ["HOSTING", "DOMAIN", "SOFTWARE", "MARKETING", "EQUIPMENT", "PROFESSIONAL_FEES", "BANK_CHARGES", "OTHER"].map(
  (c) => ({ value: c, label: c.replace("_", " ") }),
);

/** Platform running costs — hosting, domains, software, and the rest of what keeps the business itself running. */
function ExpensesCard({ expenses, meta, storeAction, removeAction }) {
  const [formOpen, setFormOpen] = useState(false);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Expenses ({meta.total})</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col divide-y divide-border p-0">
        {expenses.length === 0 ? (
          <p className="p-5 text-sm text-foreground-muted">No expenses recorded yet.</p>
        ) : (
          expenses.map((expense) => <ExpenseRow key={expense.id} expense={expense} removeAction={removeAction} />)
        )}
      </CardBody>
      <CardFooter>
        <Button size="sm" variant="outline" onClick={() => setFormOpen((v) => !v)}>
          <Plus /> {formOpen ? "Cancel" : "Record expense"}
        </Button>
      </CardFooter>
      {formOpen ? <ExpenseForm action={storeAction} onDone={() => setFormOpen(false)} /> : null}
    </Card>
  );
}

function ExpenseRow({ expense, removeAction }) {
  const [confirming, setConfirming] = useState(false);
  const [pending, startTransition] = useTransition();

  return (
    <div className="flex items-center justify-between gap-3 p-4">
      <div>
        <p className="text-sm font-medium text-foreground">{expense.description}</p>
        <p className="text-xs text-foreground-muted">
          {expense.spent_on} · {expense.category.replace("_", " ")}
          {expense.vendor ? ` · ${expense.vendor}` : ""}
        </p>
      </div>
      <div className="flex items-center gap-3">
        <span className="text-sm tabular text-foreground">{formatCurrency(expense.amount, expense.currency)}</span>
        <Button size="sm" variant="outline" onClick={() => setConfirming(true)}>
          <Trash2 />
        </Button>
      </div>
      <ConfirmDialog
        open={confirming}
        onOpenChange={setConfirming}
        title="Remove this expense?"
        confirmLabel="Remove"
        loading={pending}
        onConfirm={() => startTransition(async () => { await removeAction(expense.id); setConfirming(false); })}
      />
    </div>
  );
}

function ExpenseForm({ action, onDone }) {
  async function wrapped(previousState, formData) {
    const result = await action(previousState, formData);
    if (result?.status === "success") onDone();
    return result;
  }

  const [state, dispatch, pending] = useActionState(wrapped, null);
  const [spentOn, setSpentOn] = useState(new Date().toISOString().slice(0, 10));

  return (
    <div className="border-t border-border p-5">
      {state?.status === "error" ? <Alert variant="danger" className="mb-3">{state.message}</Alert> : null}
      <form action={dispatch} className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <FormField label="Date" required>
          {() => (
            <>
              <input type="hidden" name="spent_on" value={spentOn ?? ""} />
              <DatePicker value={spentOn} onChange={setSpentOn} />
            </>
          )}
        </FormField>
        <FormField label="Category" required>
          {() => <Select name="category" defaultValue="HOSTING" options={CATEGORIES} />}
        </FormField>
        <FormField label="Amount" required error={state?.errors?.amount?.[0]}>
          {(p) => <Input {...p} name="amount" type="number" step="0.01" min="0.01" required />}
        </FormField>
        <FormField label="Currency" required>
          {(p) => <Input {...p} name="currency" defaultValue="BDT" maxLength={3} required />}
        </FormField>
        <FormField label="Description" required className="sm:col-span-2">
          {(p) => <Input {...p} name="description" maxLength={255} required />}
        </FormField>
        <FormField label="Vendor">{(p) => <Input {...p} name="vendor" maxLength={255} />}</FormField>
        <FormField label="Reference">{(p) => <Input {...p} name="reference" maxLength={64} />}</FormField>
        <div className="flex items-end">
          <Button type="submit" loading={pending}>
            Save expense
          </Button>
        </div>
      </form>
    </div>
  );
}

export { ExpensesCard };
