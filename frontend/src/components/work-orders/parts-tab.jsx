"use client";

import { useActionState, useEffect, useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { useToastManager } from "@/components/ui/toast";
import { formatQuantity, formatCurrency } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * Mirrors `inventory::work-orders._parts.blade.php` — issued, fitted and
 * returned are shown separately because they are separate facts. The
 * "outstanding" column is the one that matters: stock taken from the
 * store that is neither in the machine nor back on the shelf. Reservations
 * are not shown here (deliberately out of this pass's scope).
 */
function PartsTab({ lines, spareParts, bins, isTerminal, showCosts, currency, actions }) {
  const t = useT("work_order");
  // Base UI's Select re-derives its own internal state off `items` identity —
  // building this array inline in each render (as the two forms below used
  // to) hands it a new reference every time either form re-renders (e.g. on
  // every keystroke or pending-state flip), which was enough to send it into
  // "Maximum update depth exceeded". Computed once here and kept stable for
  // as long as the underlying list doesn't change.
  const sparePartOptions = useMemo(
    () => spareParts.map((p) => ({ value: p.id, label: `${p.part_number} — ${p.name}` })),
    [spareParts],
  );
  const binOptions = useMemo(() => bins.map((b) => ({ value: b.id, label: b.full_path })), [bins]);

  return (
    <div className="flex flex-col gap-4">
      <div className="overflow-x-auto rounded-sm border border-border">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border text-left text-xs text-foreground-muted">
              <th className="px-4 py-3">{t("part")}</th>
              <th className="px-4 py-3 text-right">{t("requested")}</th>
              <th className="px-4 py-3 text-right">{t("issued")}</th>
              <th className="px-4 py-3 text-right">{t("consumed")}</th>
              <th className="px-4 py-3 text-right">{t("returned")}</th>
              <th className="px-4 py-3 text-right">{t("outstanding")}</th>
              {showCosts ? <th className="px-4 py-3 text-right">{t("unit_cost")}</th> : null}
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody>
            {lines.length === 0 ? (
              <tr>
                <td colSpan={showCosts ? 8 : 7} className="px-4 py-6 text-center text-foreground-muted">
                  {t("no_parts")}
                </td>
              </tr>
            ) : (
              lines.map((line) => (
                <PartLineRow
                  key={line.id}
                  line={line}
                  isTerminal={isTerminal}
                  showCosts={showCosts}
                  currency={currency}
                  binOptions={binOptions}
                  actions={actions}
                />
              ))
            )}
          </tbody>
        </table>
      </div>

      {!isTerminal ? (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
          <RequestPartForm sparePartOptions={sparePartOptions} action={actions.requestPart} />
          <IssuePartForm sparePartOptions={sparePartOptions} binOptions={binOptions} action={actions.issuePart} />
        </div>
      ) : null}
    </div>
  );
}

function outstanding(line) {
  return (Number(line.quantity_issued) - Number(line.quantity_consumed) - Number(line.quantity_returned)).toFixed(4);
}

function PartLineRow({ line, isTerminal, showCosts, currency, binOptions, actions }) {
  const t = useT("work_order");
  const remaining = outstanding(line);
  const hasOutstanding = Number(remaining) > 0;
  const [consumeQty, setConsumeQty] = useState(remaining);
  const [returnQty, setReturnQty] = useState(remaining);
  const [issueBinId, setIssueBinId] = useState("");
  const [issueQty, setIssueQty] = useState(line.quantity_requested);
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  function run(promise, label) {
    startTransition(async () => {
      const result = await promise;
      if (result?.status === "success") {
        toastManager.add({ title: label, type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <tr className={`border-t border-border ${hasOutstanding ? "bg-warning-subtle" : ""}`}>
      <td className="px-4 py-3">
        <Link href={`/inventory/parts/${line.spare_part?.id}`} className="text-brand hover:underline">
          {line.spare_part?.part_number}
        </Link>
        <div className="text-xs text-foreground-muted">{line.spare_part?.name}</div>
        {line.status === "REQUESTED" ? <Badge variant="warning">{t("awaiting_store")}</Badge> : null}
        {line.status === "CANCELLED" ? <Badge variant="neutral">{t("status_cancelled")}</Badge> : null}
      </td>
      <td className="px-4 py-3 text-right">{formatQuantity(line.quantity_requested, line.spare_part?.unit)}</td>
      <td className="px-4 py-3 text-right">{formatQuantity(line.quantity_issued, line.spare_part?.unit)}</td>
      <td className="px-4 py-3 text-right">{formatQuantity(line.quantity_consumed, line.spare_part?.unit)}</td>
      <td className="px-4 py-3 text-right">{formatQuantity(line.quantity_returned, line.spare_part?.unit)}</td>
      <td className={`px-4 py-3 text-right ${hasOutstanding ? "font-semibold text-danger" : "text-foreground-muted"}`}>
        {formatQuantity(remaining, line.spare_part?.unit)}
      </td>
      {showCosts ? <td className="px-4 py-3 text-right">{line.unit_cost ? formatCurrency(line.unit_cost, currency) : "—"}</td> : null}
      <td className="px-4 py-3">
        {line.status === "REQUESTED" && !isTerminal ? (
          <div className="flex flex-wrap items-center justify-end gap-1">
            <Select
              value={issueBinId}
              onValueChange={setIssueBinId}
              options={binOptions}
              placeholder={t("bin")}
              className="h-8 w-36 text-xs"
            />
            <Input
              value={issueQty}
              onChange={(e) => setIssueQty(e.target.value)}
              type="number"
              step="0.0001"
              min="0.0001"
              className="h-8 w-20 text-xs"
            />
            <Button
              size="sm"
              loading={pending}
              disabled={!issueBinId}
              onClick={() => run(actions.issueRequestedPart(line.id, issueBinId, issueQty), t("issued"))}
            >
              {t("issue")}
            </Button>
          </div>
        ) : null}
        {hasOutstanding && !isTerminal ? (
          <div className="flex justify-end gap-2">
            <div className="flex items-center gap-1">
              <Input
                value={consumeQty}
                onChange={(e) => setConsumeQty(e.target.value)}
                type="number"
                step="0.0001"
                min="0.0001"
                max={remaining}
                className="h-8 w-20 text-xs"
              />
              <Button
                size="sm"
                variant="outline"
                loading={pending}
                onClick={() => run(actions.consumePart(line.id, consumeQty), t("consumed"))}
              >
                {t("consume")}
              </Button>
            </div>
            <div className="flex items-center gap-1">
              <Input
                value={returnQty}
                onChange={(e) => setReturnQty(e.target.value)}
                type="number"
                step="0.0001"
                min="0.0001"
                max={remaining}
                className="h-8 w-20 text-xs"
              />
              <Button
                size="sm"
                variant="outline"
                loading={pending}
                onClick={() => run(actions.returnPart(line.id, returnQty), t("returned"))}
              >
                {t("return")}
              </Button>
            </div>
          </div>
        ) : null}
      </td>
    </tr>
  );
}

function RequestPartForm({ sparePartOptions, action }) {
  const t = useT("work_order");
  const [state, dispatch, pending] = useActionState(action, null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") toastManager.add({ title: t("requested"), type: "success" });
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <form action={dispatch} className="flex flex-col gap-3 rounded-sm border border-border p-4">
      <p className="text-sm font-semibold text-foreground">{t("request_a_part")}</p>
      <p className="text-xs text-foreground-muted">{t("request_a_part_hint")}</p>
      <FormField label={t("part")} required error={state?.errors?.spare_part_id?.[0]}>
        {(fieldProps) => <Select {...fieldProps} name="spare_part_id" options={sparePartOptions} placeholder={t("select_a_part")} />}
      </FormField>
      <FormField label={t("quantity")} required error={state?.errors?.quantity?.[0]}>
        {(fieldProps) => <Input {...fieldProps} name="quantity" type="number" step="0.0001" min="0.0001" defaultValue="1" required />}
      </FormField>
      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
      <div>
        <Button type="submit" variant="outline" loading={pending}>
          {t("request")}
        </Button>
      </div>
    </form>
  );
}

function IssuePartForm({ sparePartOptions, binOptions, action }) {
  const t = useT("work_order");
  const [state, dispatch, pending] = useActionState(action, null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") toastManager.add({ title: t("issued"), type: "success" });
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <form action={dispatch} className="flex flex-col gap-3 rounded-sm border border-border p-4">
      <p className="text-sm font-semibold text-foreground">{t("issue_a_part")}</p>
      <p className="text-xs text-foreground-muted">{t("issue_a_part_hint")}</p>
      <FormField label={t("part")} required error={state?.errors?.spare_part_id?.[0]}>
        {(fieldProps) => <Select {...fieldProps} name="spare_part_id" options={sparePartOptions} placeholder={t("select_a_part")} />}
      </FormField>
      <FormField label={t("bin")} required error={state?.errors?.bin_id?.[0]}>
        {(fieldProps) => <Select {...fieldProps} name="bin_id" options={binOptions} placeholder={t("select_a_bin")} />}
      </FormField>
      <FormField label={t("quantity")} required error={state?.errors?.quantity?.[0]}>
        {(fieldProps) => <Input {...fieldProps} name="quantity" type="number" step="0.0001" min="0.0001" required />}
      </FormField>
      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
      <div>
        <Button type="submit" loading={pending}>
          {t("issue")}
        </Button>
      </div>
    </form>
  );
}

export { PartsTab };
