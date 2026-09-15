"use client";

import { useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";

const TYPE_OPTIONS = [
  { value: "AMC", label: "AMC" },
  { value: "CALIBRATION", label: "Calibration" },
  { value: "INSPECTION", label: "Inspection" },
  { value: "SUPPORT", label: "Support" },
];

const SCOPE_OPTIONS = [
  { value: "asset", label: "One machine" },
  { value: "factory", label: "Whole factory" },
  { value: "list", label: "Several machines" },
];

/**
 * Mirrors `contracts/create.blade.php` — an AMC is as often written over a
 * whole line as over one machine, so scope is exactly one of three shapes
 * (`ManageServiceContract::create` rejects naming none or more than one).
 */
function ContractForm({ vendors, assets, factories, action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const [scope, setScope] = useState("asset");
  const router = useRouter();

  return (
    <form action={dispatch} className="flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <FormField label="Vendor" required error={state?.errors?.vendor_id?.[0]}>
          {(fieldProps) => <Select {...fieldProps} name="vendor_id" options={vendors.map((v) => ({ value: v.id, label: v.name }))} />}
        </FormField>
        <FormField label="Contract type" required>
          {(fieldProps) => <Select {...fieldProps} name="contract_type" defaultValue="AMC" options={TYPE_OPTIONS} />}
        </FormField>
        <FormField label="Contract number" helperText="Leave blank to auto-number.">
          {(fieldProps) => <Input {...fieldProps} name="contract_number" placeholder="AMC-2026-0001" />}
        </FormField>
      </div>

      <div className="rounded-sm border border-border p-4">
        <FormField label="Scope" required error={state?.errors?.scope?.[0]}>
          {(fieldProps) => <Select {...fieldProps} options={SCOPE_OPTIONS} value={scope} onValueChange={setScope} />}
        </FormField>

        <div className="mt-3">
          {scope === "asset" ? (
            <FormField label="Machine">
              {(fieldProps) => (
                <Select {...fieldProps} name="asset_id" options={assets.map((a) => ({ value: a.id, label: `${a.asset_code} — ${a.name}` }))} />
              )}
            </FormField>
          ) : null}

          {scope === "factory" ? (
            <FormField label="Factory">
              {(fieldProps) => <Select {...fieldProps} name="factory_id" options={factories.map((f) => ({ value: f.id, label: f.name }))} />}
            </FormField>
          ) : null}

          {scope === "list" ? (
            <div>
              <span className="mb-1.5 block text-sm font-medium text-foreground">Machines</span>
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
        <FormField label="Start date" required error={state?.errors?.start_date?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="date" name="start_date" required />}
        </FormField>
        <FormField label="End date" required error={state?.errors?.end_date?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="date" name="end_date" required />}
        </FormField>
        <FormField label="Renewal reminder date">
          {(fieldProps) => <Input {...fieldProps} type="date" name="renewal_date" />}
        </FormField>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <FormField label="Value">
          {(fieldProps) => <Input {...fieldProps} type="number" step="0.01" min="0" name="value" />}
        </FormField>
        <FormField label="Visits per year">
          {(fieldProps) => <Input {...fieldProps} type="number" min="0" max="365" name="visits_per_year" />}
        </FormField>
        <FormField label="Response time (hours)">
          {(fieldProps) => <Input {...fieldProps} type="number" min="0" name="response_time_hours" />}
        </FormField>
      </div>

      <FormField label="Coverage">
        {(fieldProps) => <Textarea {...fieldProps} name="coverage" rows={3} />}
      </FormField>

      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          Cancel
        </Button>
        <Button type="submit" loading={pending}>
          Save
        </Button>
      </div>
    </form>
  );
}

export { ContractForm };
