"use client";

import { useActionState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";

const TYPE_OPTIONS = [
  { value: "MANUFACTURER", label: "Manufacturer" },
  { value: "EXTENDED", label: "Extended" },
  { value: "SERVICE", label: "Service" },
];

/** Mirrors `warranties/create.blade.php`. */
function WarrantyForm({ assets, vendors, assetId, action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const router = useRouter();

  return (
    <form action={dispatch} className="flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label="Asset" required error={state?.errors?.asset_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="asset_id"
              defaultValue={assetId ?? ""}
              options={assets.map((a) => ({ value: a.id, label: `${a.asset_code} — ${a.name}` }))}
            />
          )}
        </FormField>

        <FormField label="Vendor">
          {(fieldProps) => (
            <Select {...fieldProps} name="vendor_id" options={vendors.map((v) => ({ value: v.id, label: v.name }))} />
          )}
        </FormField>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <FormField label="Type" required>
          {(fieldProps) => <Select {...fieldProps} name="warranty_type" defaultValue="MANUFACTURER" options={TYPE_OPTIONS} />}
        </FormField>
        <FormField label="Start date" required error={state?.errors?.start_date?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="date" name="start_date" required />}
        </FormField>
        <FormField label="End date" required error={state?.errors?.end_date?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="date" name="end_date" required />}
        </FormField>
      </div>

      <FormField label="Reference">
        {(fieldProps) => <Input {...fieldProps} name="reference" />}
      </FormField>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label="Coverage">
          {(fieldProps) => <Textarea {...fieldProps} name="coverage" rows={3} />}
        </FormField>
        <FormField label="Exclusions" helperText="What this warranty does not pay for.">
          {(fieldProps) => <Textarea {...fieldProps} name="exclusions" rows={3} />}
        </FormField>
      </div>

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

export { WarrantyForm };
