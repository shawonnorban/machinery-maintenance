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
import { useT } from "@/lib/i18n";

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
function CostsTab({ assetId, lifecycle, entries, categories, postAction, reverseAction, onMutated }) {
  const t = useT("asset");
  const postableCategories = categories.filter((c) => !NOT_MANUALLY_POSTABLE.includes(c.code));

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label={t("acquisition")} value={formatCurrency(lifecycle.acquisition, lifecycle.currency)} />
        <StatCard label={t("total_spend")} value={formatCurrency(lifecycle.total_spend, lifecycle.currency)} />
        <StatCard label={t("lifetime_total")} value={formatCurrency(lifecycle.lifetime_total, lifecycle.currency)} />
        <StatCard
          label={t("spend_vs_value")}
          value={lifecycle.spend_against_value_percent === null ? t("not_applicable") : `${lifecycle.spend_against_value_percent}%`}
        />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-5">
        <div className="flex flex-col gap-4 lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle>{t("by_category")}</CardTitle>
            </CardHeader>
            <CardBody className="p-0">
              {lifecycle.by_category.length === 0 ? (
                <p className="p-5 text-sm text-foreground-muted">{t("no_cost_entries")}</p>
              ) : (
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-xs text-foreground-muted">
                      <th className="px-5 pt-4 pb-2">{t("category")}</th>
                      <th className="px-5 pt-4 pb-2 text-right">{t("entries")}</th>
                      <th className="px-5 pt-4 pb-2 text-right">{t("amount")}</th>
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
              <p className="text-xs text-foreground-muted">{t("depreciation_note")}</p>
            </CardFooter>
          </Card>

          {lifecycle.can_post ? (
            <PostCostForm categories={postableCategories} currency={lifecycle.currency} action={postAction} onMutated={onMutated} />
          ) : null}
        </div>

        <div className="lg:col-span-3">
          <Card>
            <CardHeader>
              <CardTitle>{t("costs")}</CardTitle>
              <span className="text-sm text-foreground-muted">{lifecycle.entry_count}</span>
            </CardHeader>
            <CardBody className="p-0">
              {entries.length === 0 ? (
                <p className="p-5 text-sm text-foreground-muted">{t("nothing_posted_yet")}</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="text-left text-xs text-foreground-muted">
                        <th className="px-5 pt-4 pb-2">{t("occurred")}</th>
                        <th className="px-5 pt-4 pb-2">{t("category")}</th>
                        <th className="px-5 pt-4 pb-2">{t("source")}</th>
                        <th className="px-5 pt-4 pb-2">{t("description")}</th>
                        <th className="px-5 pt-4 pb-2 text-right">{t("amount")}</th>
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
                              {t(`source_${entry.source_type?.toLowerCase()}`)}
                              {isDerived ? <div className="text-xs text-foreground-muted">{t("derived")}</div> : null}
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
                                <ReverseCostCell entryId={entry.id} reverseAction={reverseAction} onMutated={onMutated} />
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
              <p className="text-xs text-foreground-muted">{t("reversal_note")}</p>
            </CardFooter>
          </Card>
        </div>
      </div>
    </div>
  );
}

function PostCostForm({ categories, currency, action, onMutated }) {
  const t = useT("asset");
  const SOURCE_OPTIONS = [
    { value: "EXTERNAL_SERVICE", label: t("source_external_service") },
    { value: "VENDOR", label: t("source_vendor") },
    { value: "TRANSPORT", label: t("source_transport") },
    { value: "MANUAL", label: t("source_manual") },
  ];
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
      toastManager.add({ title: t("cost_posted_toast"), type: "success" });
      queueMicrotask(() => {
        setAmount("");
        setOccurredAt("");
        setDescription("");
        setInvoiceReference("");
      });
      onMutated?.();
    }
    // toastManager/onMutated are not stable references across renders —
    // including them re-fires this effect every render once state first
    // becomes "success", stacking duplicate toasts and refetches.
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
        <CardTitle>{t("post_a_cost")}</CardTitle>
      </CardHeader>
      <CardBody>
        <form onSubmit={handleSubmit} className="flex flex-col gap-3">
          <FormField label={t("category")} required error={state?.errors?.cost_category_id?.[0]}>
            {(fieldProps) => (
              <Select
                {...fieldProps}
                value={categoryId}
                onValueChange={setCategoryId}
                options={categories.map((c) => ({ value: c.id, label: c.name }))}
                placeholder={t("select_a_category")}
              />
            )}
          </FormField>

          <div className="grid grid-cols-2 gap-3">
            <FormField label={t("amount")} required error={state?.errors?.amount?.[0]}>
              {(fieldProps) => (
                <Input {...fieldProps} type="number" step="0.0001" value={amount} onChange={(e) => setAmount(e.target.value)} required />
              )}
            </FormField>
            <FormField label={t("currency")} required error={state?.errors?.currency?.[0]}>
              {(fieldProps) => (
                <Input {...fieldProps} maxLength={3} value={entryCurrency} onChange={(e) => setEntryCurrency(e.target.value)} required />
              )}
            </FormField>
          </div>

          <FormField label={t("source")} required>
            {(fieldProps) => <Select {...fieldProps} value={sourceType} onValueChange={setSourceType} options={SOURCE_OPTIONS} />}
          </FormField>

          {/* Its own row, not paired in a 2-col grid — `DateTimeField` is
              itself a two-part date+time layout, and this card runs only
              2/5 of the tab's width (`lg:col-span-2`) on top of that; halving
              it again left the date button a few px wide and its "Pick a
              date" label wrapping onto three lines. */}
          <FormField label={t("occurred_at")} error={state?.errors?.occurred_at?.[0]}>
            {(fieldProps) => <DateTimeField {...fieldProps} value={occurredAt} onChange={setOccurredAt} />}
          </FormField>

          <FormField label={t("description")} error={state?.errors?.description?.[0]}>
            {(fieldProps) => <Input {...fieldProps} maxLength={255} value={description} onChange={(e) => setDescription(e.target.value)} />}
          </FormField>

          <FormField label={t("invoice_reference")} error={state?.errors?.invoice_reference?.[0]}>
            {(fieldProps) => (
              <Input {...fieldProps} maxLength={255} value={invoiceReference} onChange={(e) => setInvoiceReference(e.target.value)} />
            )}
          </FormField>

          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

          <div>
            <Button type="submit" loading={pending}>
              {t("post_cost")}
            </Button>
          </div>
        </form>
      </CardBody>
    </Card>
  );
}

function ReverseCostCell({ entryId, reverseAction, onMutated }) {
  const t = useT("asset");
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
      toastManager.add({ title: t("reversed_toast"), type: "success" });
      router.refresh();
      onMutated?.();
    } else if (result?.status === "error") {
      toastManager.add({ title: result.message, type: "danger" });
    }
  }

  return (
    <div className="flex items-center gap-1">
      <Input
        value={reason}
        onChange={(e) => setReason(e.target.value)}
        placeholder={t("reason")}
        className="h-8 w-32 text-xs"
      />
      <Button variant="outline" size="sm" loading={pending} disabled={!reason.trim()} onClick={handleReverse}>
        {t("reverse")}
      </Button>
    </div>
  );
}

export { CostsTab };
