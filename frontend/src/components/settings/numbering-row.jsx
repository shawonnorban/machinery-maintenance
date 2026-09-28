"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const RESET_LABEL_KEY = { MONTHLY: "resets_monthly", YEARLY: "resets_yearly", NEVER: "resets_never" };

/**
 * One document type from `NumberingApiController::index` (SRS 52). A format
 * is easy to get subtly wrong and impossible to correct for numbers already
 * issued, so this always shows a live sample and how many are issued
 * already before anyone changes anything.
 */
function NumberingRow({ row, updateAction, resetAction }) {
  const t = useT("numbering");
  const tc = useT("common");
  const typeLabel = t(`types.${row.document_type}`);
  const [state, dispatch, pending] = useActionState(updateAction, null);
  const [resetting, setResetting] = useState(false);
  const [format, setFormat] = useState(row.format);
  const [padding, setPadding] = useState(String(row.padding));
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("saved", { type: typeLabel }), type: "success" });
    } else if (state?.status === "error" && !state.errors) {
      toastManager.add({ title: state.message ?? t("could_not_save"), type: "danger" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success"/"error", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("format", format);
    formData.set("padding", padding);
    startTransition(() => dispatch(formData));
  }

  async function handleReset() {
    setResetting(true);
    const result = await resetAction();
    setResetting(false);
    if (result?.status === "success") {
      toastManager.add({ title: t("reset_done", { type: typeLabel }), type: "success" });
    } else if (result?.status === "error") {
      toastManager.add({ title: result.message ?? t("could_not_reset"), type: "danger" });
    }
  }

  return (
    <div className="flex flex-col gap-3 border-b border-border py-4 last:border-b-0">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-foreground">{typeLabel}</p>
          <p className="text-xs text-foreground-muted">
            {t(RESET_LABEL_KEY[row.reset] ?? "resets_never")} · {t("issued_count", { count: row.issued })}
          </p>
        </div>
        {row.is_default ? <Badge variant="neutral">{t("default_format")}</Badge> : <Badge variant="brand">{t("customised")}</Badge>}
      </div>

      <form onSubmit={handleSubmit} className="flex flex-wrap items-end gap-2">
        <div className="w-64">
          <FormField label={t("format")} error={state?.errors?.format?.[0]}>
            {(fieldProps) => <Input {...fieldProps} value={format} onChange={(e) => setFormat(e.target.value)} />}
          </FormField>
        </div>
        <div className="w-24">
          <FormField label={t("padding")} error={state?.errors?.padding?.[0]}>
            {(fieldProps) => (
              <Input {...fieldProps} type="number" min={1} max={10} value={padding} onChange={(e) => setPadding(e.target.value)} />
            )}
          </FormField>
        </div>
        <Button type="submit" size="sm" variant="outline" loading={pending}>
          {tc("save")}
        </Button>
        {!row.is_default ? (
          <Button type="button" size="sm" variant="ghost" loading={resetting} onClick={handleReset}>
            {t("reset")}
          </Button>
        ) : null}
      </form>

      <p className="text-xs text-foreground-muted">
        {t("next_number_would_be")} <span className="font-mono text-foreground">{row.sample}</span>. {t("placeholders")}
      </p>
    </div>
  );
}

export { NumberingRow };
