"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { StatCard } from "@/components/ui/stat-card";
import { Card, CardHeader, CardTitle, CardBody, CardFooter } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { DateTimeField } from "@/components/ui/date-time-field";
import { formatCurrency } from "@/lib/format";

const SOURCE_OPTIONS = [
  { value: "EXTERNAL_SERVICE", label: "External service" },
  { value: "VENDOR", label: "Vendor" },
  { value: "TRANSPORT", label: "Transport" },
  { value: "MANUAL", label: "Manual" },
];

// Written by the system from the work order underneath — posting one by
// hand would charge the machine twice, so the form never offers them.
const NOT_MANUALLY_POSTABLE = ["LABOR", "PARTS"];

/**
 * Mirrors `costing::costs.show.blade.php` — lifecycle KPIs, spend by
 * category, the post-a-cost form (shown only when the API says the caller
 * can post), and the entry ledger with a reverse action per eligible row.
 *
 * Lives in its own client component rather than inline in the tabs file
 * because it owns its own mutation state (post form, reverse-per-row) —
 * the plain `lifecycle`/`entries`/`categories` data still crosses from the
 * server page as props.
 */
function CostsTab({ assetId, lifecycle, entries, categories, postAction, reverseAction }) {
  const postableCategories = categories.filter((c) => !NOT_MANUALLY_POSTABLE.includes(c.code));

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label="Acquisition" value={formatCurrency(lifecycle.acquisition, lifecycle.currency)} />
        <StatCard label="Total spend" value={formatCurrency(lifecycle.total_spend, lifecycle.currency)} />
        <StatCard label="Lifetime total" value={formatCurrency(lifecycle.lifetime_total, lifecycle.currency)} />
        <StatCard
          label="Spend vs. value"
          value={lifecycle.spend_against_value_percent === null ? "N/A" : `${lifecycle.spend_against_value_percent}%`}
        />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-5">
        <div className="flex flex-col gap-4 lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle>By category</CardTitle>
            </CardHeader>
            <CardBody className="p-0">
              {lifecycle.by_category.length === 0 ? (
                <p className="p-5 text-sm text-foreground-muted">No entries yet.</p>
              ) : (
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-xs text-foreground-muted">
                      <th className="px-5 pt-4 pb-2">Category</th>
                      <th className="px-5 pt-4 pb-2 text-right">Entries</th>
                      <th className="px-5 pt-4 pb-2 text-right">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    {lifecycle.by_category.map((row) => (
                      <tr key={row.cost_category_id} className="border-t border-border">
                        <td className="px-5 py-2">{row.category ?? "—"}</td>
                        <td className="px-5 py-2 text-right text-foreground-muted">{row.entries}</td>
                        <td className="px-5 py-2 text-right">{formatCurrency(row.total, lifecycle.currency)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </CardBody>
            <CardFooter>
              <p className="text-xs text-foreground-muted">Depreciation is not included in these figures.</p>
            </CardFooter>
          </Card>

          {lifecycle.can_post ? (
            <PostCostForm categories={postableCategories} currency={lifecycle.currency} action={postAction} />
          ) : null}
        </div>

        <div className="lg:col-span-3">
          <Card>
            <CardHeader>
              <CardTitle>Costs</CardTitle>
              <span className="text-sm text-foreground-muted">{lifecycle.entry_count}</span>
            </CardHeader>
            <CardBody className="p-0">
              {entries.length === 0 ? (
                <p className="p-5 text-sm text-foreground-muted">Nothing posted against this machine yet.</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="text-left text-xs text-foreground-muted">
                        <th className="px-5 pt-4 pb-2">Occurred</th>
                        <th className="px-5 pt-4 pb-2">Category</th>
                        <th className="px-5 pt-4 pb-2">Source</th>
                        <th className="px-5 pt-4 pb-2">Description</th>
                        <th className="px-5 pt-4 pb-2 text-right">Amount</th>
                        <th className="px-5 pt-4 pb-2"></th>
                      </tr>
                    </thead>
                    <tbody>
                      {entries.map((entry) => {
                        const isDerived = ["LABOR", "PARTS"].includes(entry.source_type);
                        return (
                          <tr key={entry.id} className={`border-t border-border ${entry.is_reversal ? "bg-warning-subtle" : ""}`}>
                            <td className="px-5 py-2 whitespace-nowrap">
                              <FormattedDateTime value={entry.occurred_at} />
                            </td>
                            <td className="px-5 py-2">{entry.category ?? "—"}</td>
                            <td className="px-5 py-2">
                              {entry.source_type}
                              {isDerived ? <div className="text-xs text-foreground-muted">Derived</div> : null}
                            </td>
                            <td className="px-5 py-2 text-xs">
                              {entry.description ?? "—"}
                              {entry.work_order ? (
                                <div>
                                  <Link href={`/work-orders/${entry.work_order.id}`} className="text-brand hover:underline">
                                    {entry.work_order.work_order_number}
                                  </Link>
                                </div>
                              ) : null}
                            </td>
                            <td className={`px-5 py-2 text-right whitespace-nowrap ${entry.is_reversal ? "text-danger" : ""}`}>
                              {formatCurrency(entry.amount, entry.currency)}
                            </td>
                            <td className="px-5 py-2">
                              {lifecycle.can_reverse && !entry.is_reversal && !isDerived ? (
                                <ReverseCostCell entryId={entry.id} reverseAction={reverseAction} />
                              ) : null}
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </CardBody>
            <CardFooter>
              <p className="text-xs text-foreground-muted">A correction is a reversal row, never an edit — the original stays exactly as posted.</p>
            </CardFooter>
          </Card>
        </div>
      </div>
    </div>
  );
}

function PostCostForm({ categories, currency, action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const toastManager = useToastManager();

  const [categoryId, setCategoryId] = useState(categories[0]?.id ?? "");
  const [amount, setAmount] = useState("");
  const [entryCurrency, setEntryCurrency] = useState(currency ?? "BDT");
  const [sourceType, setSourceType] = useState("VENDOR");
  const [occurredAt, setOccurredAt] = useState("");
  const [description, setDescription] = useState("");
  const [invoiceReference, setInvoiceReference] = useState("");

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Cost posted", type: "success" });
      queueMicrotask(() => {
        setAmount("");
        setOccurredAt("");
        setDescription("");
        setInvoiceReference("");
      });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("cost_category_id", categoryId);
    formData.set("amount", amount);
    formData.set("currency", entryCurrency);
    formData.set("source_type", sourceType);
    if (occurredAt) formData.set("occurred_at", occurredAt);
    if (description) formData.set("description", description);
    if (invoiceReference) formData.set("invoice_reference", invoiceReference);
    startTransition(() => dispatch(formData));
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Post a cost</CardTitle>
      </CardHeader>
      <CardBody>
        <form onSubmit={handleSubmit} className="flex flex-col gap-3">
          <FormField label="Category" required error={state?.errors?.cost_category_id?.[0]}>
            {(fieldProps) => (
              <Select
                {...fieldProps}
                value={categoryId}
                onValueChange={setCategoryId}
                options={categories.map((c) => ({ value: c.id, label: c.name }))}
                placeholder="Select a category"
              />
            )}
          </FormField>

          <div className="grid grid-cols-2 gap-3">
            <FormField label="Amount" required error={state?.errors?.amount?.[0]}>
              {(fieldProps) => (
                <Input {...fieldProps} type="number" step="0.0001" value={amount} onChange={(e) => setAmount(e.target.value)} required />
              )}
            </FormField>
            <FormField label="Currency" required error={state?.errors?.currency?.[0]}>
              {(fieldProps) => (
                <Input {...fieldProps} maxLength={3} value={entryCurrency} onChange={(e) => setEntryCurrency(e.target.value)} required />
              )}
            </FormField>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <FormField label="Source" required>
              {(fieldProps) => <Select {...fieldProps} value={sourceType} onValueChange={setSourceType} options={SOURCE_OPTIONS} />}
            </FormField>
            <FormField label="Occurred at" error={state?.errors?.occurred_at?.[0]}>
              {(fieldProps) => <DateTimeField {...fieldProps} value={occurredAt} onChange={setOccurredAt} />}
            </FormField>
          </div>

          <FormField label="Description" error={state?.errors?.description?.[0]}>
            {(fieldProps) => <Input {...fieldProps} maxLength={255} value={description} onChange={(e) => setDescription(e.target.value)} />}
          </FormField>

          <FormField label="Invoice reference" error={state?.errors?.invoice_reference?.[0]}>
            {(fieldProps) => (
              <Input {...fieldProps} maxLength={255} value={invoiceReference} onChange={(e) => setInvoiceReference(e.target.value)} />
            )}
          </FormField>

          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

          <div>
            <Button type="submit" loading={pending}>
              Post cost
            </Button>
          </div>
        </form>
      </CardBody>
    </Card>
  );
}

function ReverseCostCell({ entryId, reverseAction }) {
  const [reason, setReason] = useState("");
  const [pending, setPending] = useState(false);
  const router = useRouter();
  const toastManager = useToastManager();

  async function handleReverse() {
    if (!reason.trim()) return;
    setPending(true);
    const result = await reverseAction(entryId, reason);
    setPending(false);
    if (result?.status === "success") {
      toastManager.add({ title: "Reversed", type: "success" });
      router.refresh();
    } else if (result?.status === "error") {
      toastManager.add({ title: result.message, type: "danger" });
    }
  }

  return (
    <div className="flex items-center gap-1">
      <Input
        value={reason}
        onChange={(e) => setReason(e.target.value)}
        placeholder="Reason"
        className="h-8 w-32 text-xs"
      />
      <Button variant="outline" size="sm" loading={pending} disabled={!reason.trim()} onClick={handleReverse}>
        Reverse
      </Button>
    </div>
  );
}

export { CostsTab };
