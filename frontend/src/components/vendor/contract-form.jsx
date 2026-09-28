"use client";

import { useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useT } from "@/lib/i18n";

/**
 * Mirrors `contracts/create.blade.php` — an AMC is as often written over a
 * whole line as over one machine, so scope is exactly one of three shapes
 * (`ManageServiceContract::create` rejects naming none or more than one).
 */
function ContractForm({ vendors, assets, factories, action }) {
  const t = useT("vendor");
  const tc = useT("common");
  const TYPE_OPTIONS = [
    { value: "AMC", label: t("contract_type_amc") },
    { value: "CALIBRATION", label: t("contract_type_calibration") },
    { value: "INSPECTION", label: t("contract_type_inspection") },
    { value: "SUPPORT", label: t("contract_type_support") },
  ];
  const SCOPE_OPTIONS = [
    { value: "asset", label: t("scope_asset") },
    { value: "factory", label: t("scope_factory") },
    { value: "list", label: t("scope_list") },
  ];
  const [state, dispatch, pending] = useActionState(action, null);
  const [scope, setScope] = useState("asset");
  const router = useRouter();

  return (
    <form action={dispatch} className="flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <FormField label={t("vendor")} required error={state?.errors?.vendor_id?.[0]}>
          {(fieldProps) => <Select {...fieldProps} name="vendor_id" options={vendors.map((v) => ({ value: v.id, label: v.name }))} />}
        </FormField>
        <FormField label={t("contract_type")} required>
          {(fieldProps) => <Select {...fieldProps} name="contract_type" defaultValue="AMC" options={TYPE_OPTIONS} />}
        </FormField>
        <FormField label={t("contract_number")} helperText={t("contract_number_hint")}>
          {(fieldProps) => <Input {...fieldProps} name="contract_number" placeholder={t("contract_number_placeholder")} />}
        </FormField>
      </div>

      <div className="rounded-sm border border-border p-4">
        <FormField label={t("scope")} required error={state?.errors?.scope?.[0]}>
          {(fieldProps) => <Select {...fieldProps} options={SCOPE_OPTIONS} value={scope} onValueChange={setScope} />}
        </FormField>

        <div className="mt-3">
          {scope === "asset" ? (
            <FormField label={t("machine")}>
              {(fieldProps) => (
                <Select {...fieldProps} name="asset_id" options={assets.map((a) => ({ value: a.id, label: `${a.asset_code} — ${a.name}` }))} />
              )}
            </FormField>
          ) : null}

          {scope === "factory" ? (
            <FormField label={t("factory")}>
              {(fieldProps) => <Select {...fieldProps} name="factory_id" options={factories.map((f) => ({ value: f.id, label: f.name }))} />}
            </FormField>
          ) : null}

          {scope === "list" ? (
            <div>
              <span className="mb-1.5 block text-sm font-medium text-foreground">{t("machines")}</span>
              <div className="max-h-48 overflow-y-auto rounded-sm border border-border-strong p-2">
                {assets.map((asset) => (
                  <label key={asset.id} className="flex items-center gap-2 py-1 text-sm text-foreground">
                    <Checkbox name="asset_ids" value={asset.id} />
                    {asset.asset_code} — {asset.name}
                  </label>
                ))}
              </div>
            </div>
          ) : null}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <FormField label={t("start_date")} required error={state?.errors?.start_date?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="date" name="start_date" required />}
        </FormField>
        <FormField label={t("end_date")} required error={state?.errors?.end_date?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="date" name="end_date" required />}
        </FormField>
        <FormField label={t("renewal_date")}>
          {(fieldProps) => <Input {...fieldProps} type="date" name="renewal_date" />}
        </FormField>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <FormField label={t("value")}>
          {(fieldProps) => <Input {...fieldProps} type="number" step="0.01" min="0" name="value" />}
        </FormField>
        <FormField label={t("visits_per_year")}>
          {(fieldProps) => <Input {...fieldProps} type="number" min="0" max="365" name="visits_per_year" />}
        </FormField>
        <FormField label={t("response_time_hours")}>
          {(fieldProps) => <Input {...fieldProps} type="number" min="0" name="response_time_hours" />}
        </FormField>
      </div>

      <FormField label={t("coverage")}>
        {(fieldProps) => <Textarea {...fieldProps} name="coverage" rows={3} />}
      </FormField>

      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          {tc("save")}
        </Button>
      </div>
    </form>
  );
}

export { ContractForm };
