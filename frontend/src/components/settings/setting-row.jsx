"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const LEVEL_VARIANT = { COMPANY: "brand", FACTORY: "info", PLATFORM: "neutral" };

/**
 * One key/value row from `CompanySettingsController`/`SettingsApiController` (SRS 20, ADR-054).
 *
 * A row edits at exactly one level at a time — company-wide when no factory
 * is selected, that one factory's override when one is — mirroring the web
 * screen exactly rather than trying to show every level at once.
 */
const LEVEL_LABEL_KEY = { COMPANY: "source_company", FACTORY: "source_factory", PLATFORM: "source_platform" };

function SettingRow({ definition, value, level, editableHere, factoryId, updateAction, resetAction }) {
  const t = useT("settings");
  const tc = useT("common");
  const [state, dispatch, pending] = useActionState(updateAction, null);
  const [resetting, setResetting] = useState(false);
  const [draft, setDraft] = useState(() => toDraft(definition.value_type, value));
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("saved", { name: definition.name }), type: "success" });
    } else if (state?.status === "error") {
      toastManager.add({ title: state.message ?? t("could_not_save"), type: "danger" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success"/"error", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state, definition.name]);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("value", draft);
    startTransition(() => dispatch(formData));
  }

  async function handleReset() {
    setResetting(true);
    const result = await resetAction();
    setResetting(false);
    if (result?.status === "success") {
      toastManager.add({ title: t("setting_reset_toast", { name: definition.name }), type: "success" });
    } else if (result?.status === "error") {
      toastManager.add({ title: result.message ?? t("could_not_reset"), type: "danger" });
    }
  }

  const hasOwnAnswerHere = factoryId ? level === "FACTORY" : level !== "PLATFORM";

  return (
    <div className="flex flex-col gap-2.5 rounded-sm border-b border-border px-2 py-3 transition-colors last:border-b-0 hover:bg-surface-muted/60">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-medium text-foreground">{definition.name}</p>
          {definition.description ? (
            <p className="text-xs text-foreground-muted">{definition.description}</p>
          ) : null}
        </div>
        <Badge variant={LEVEL_VARIANT[level] ?? "neutral"} className="shrink-0">
          {t(LEVEL_LABEL_KEY[level] ?? "source_platform")}
        </Badge>
      </div>

      {editableHere ? (
        <form onSubmit={handleSubmit} className="flex flex-wrap items-end gap-2">
          <div className="w-full sm:w-64">
            <FormField label={t("value")} error={state?.errors?.value?.[0]}>
              {(fieldProps) => <FieldInput {...fieldProps} definition={definition} value={draft} onChange={setDraft} t={t} />}
            </FormField>
          </div>
          <div className="flex shrink-0 gap-2">
            <Button type="submit" size="sm" variant="outline" loading={pending}>
              {tc("save")}
            </Button>
            {factoryId && hasOwnAnswerHere ? (
              <Button type="button" size="sm" variant="ghost" loading={resetting} onClick={handleReset}>
                {t("follow_company_again")}
              </Button>
            ) : null}
          </div>
        </form>
      ) : (
        <p className="text-sm text-foreground-muted">
          {formatDisplay(definition.value_type, value, t)} — {t("not_editable_at_level")}
        </p>
      )}
    </div>
  );
}

function FieldInput({ definition, value, onChange, t, ...fieldProps }) {
  if (definition.value_type === "BOOL") {
    return (
      <label className="flex h-9 items-center gap-2 text-sm text-foreground">
        <Checkbox checked={value === "true"} onCheckedChange={(checked) => onChange(checked ? "true" : "false")} />
        {value === "true" ? t("on") : t("off")}
      </label>
    );
  }

  if (definition.value_type === "ENUM") {
    return (
      <Select
        {...fieldProps}
        options={(definition.allowed_values ?? []).map((v) => ({ value: v, label: v }))}
        value={value}
        onValueChange={onChange}
      />
    );
  }

  return (
    <Input
      {...fieldProps}
      type={definition.value_type === "INT" || definition.value_type === "DECIMAL" ? "number" : "text"}
      step={definition.value_type === "DECIMAL" ? "0.0001" : undefined}
      value={value}
      onChange={(event) => onChange(event.target.value)}
      placeholder={definition.value_type === "LIST" ? t("comma_separated") : undefined}
    />
  );
}

function toDraft(valueType, value) {
  if (valueType === "BOOL") return value ? "true" : "false";
  if (valueType === "LIST") return Array.isArray(value) ? value.join(", ") : "";
  return value ?? "";
}

function formatDisplay(valueType, value, t) {
  if (valueType === "BOOL") return value ? t("on") : t("off");
  if (valueType === "LIST") return Array.isArray(value) ? value.join(", ") : String(value ?? "");
  return String(value ?? "");
}

export { SettingRow };
