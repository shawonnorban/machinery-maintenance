"use client";

import { ArrowLeft, Save } from "lucide-react";
import { startTransition, useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { FormActions, FormSection } from "@/components/ui/form-section";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useT } from "@/lib/i18n";

/**
 * Mirrors `inventory::parts._form.blade.php` — one form for creating and
 * editing, so a field cannot be added to one and forgotten in the other.
 * No opening quantity here on purpose: stock enters through the ledger, so
 * every unit has a movement behind it.
 */
function SparePartForm({ part = null, categories, manufacturers, uoms = [], action }) {
  const t = useT("inventory");
  const tc = useT("common");
  const isEdit = part !== null;
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);

  const [partNumber, setPartNumber] = useState(part?.part_number ?? "");
  const [unit, setUnit] = useState(part?.unit ?? "PCS");
  const [name, setName] = useState(part?.name ?? "");
  const [categoryId, setCategoryId] = useState(part?.category?.id ?? "");
  const [brand, setBrand] = useState(part?.brand ?? "");
  const [manufacturerId, setManufacturerId] = useState(part?.manufacturer?.id ?? "");
  const [notes, setNotes] = useState(part?.notes ?? "");
  const [minimumStock, setMinimumStock] = useState(part?.minimum_stock ?? "0");
  const [reorderLevel, setReorderLevel] = useState(part?.reorder_level ?? "0");
  const [leadTimeDays, setLeadTimeDays] = useState(part?.lead_time_days ?? "");
  const [shelfLifeDays, setShelfLifeDays] = useState(part?.shelf_life_days ?? "");
  const [isCriticalSpare, setIsCriticalSpare] = useState(part?.is_critical_spare ?? false);
  const [hazardous, setHazardous] = useState(part?.hazardous ?? false);

  // The stock unit picker reads the UOM master; an edited part whose unit is
  // no longer active still shows its own so the form does not silently
  // change it on save.
  const unitOptions = uoms.map((u) => ({ value: u.code, label: `${u.code} — ${u.name}` }));
  if (unit && !unitOptions.some((o) => o.value === unit)) unitOptions.unshift({ value: unit, label: unit });

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("part_number", partNumber);
    formData.set("unit", unit);
    formData.set("name", name);
    if (categoryId) formData.set("category_id", categoryId);
    if (brand) formData.set("brand", brand);
    if (manufacturerId) formData.set("manufacturer_id", manufacturerId);
    if (notes) formData.set("notes", notes);
    formData.set("minimum_stock", minimumStock || "0");
    formData.set("reorder_level", reorderLevel || "0");
    if (leadTimeDays) formData.set("lead_time_days", leadTimeDays);
    if (shelfLifeDays) formData.set("shelf_life_days", shelfLifeDays);
    formData.set("is_critical_spare", isCriticalSpare ? "1" : "0");
    formData.set("hazardous", hazardous ? "1" : "0");
    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      {state?.status === "error" && !state.errors ? (
        <Alert variant="danger">{state.message}</Alert>
      ) : null}

      <div>
        <FormSection legend={t("details")}>
            <div className="grid gap-x-8 gap-y-3 lg:grid-cols-2">
              <FormField label={t("part_number")} required error={state?.errors?.part_number?.[0]}>
                {(fieldProps) => <Input {...fieldProps} value={partNumber} onChange={(e) => setPartNumber(e.target.value)} maxLength={64} required />}
              </FormField>
              <FormField label={t("unit")} required>
                {(fieldProps) => <Select {...fieldProps} value={unit} onValueChange={setUnit} options={unitOptions} />}
              </FormField>
            </div>

            <FormField label={t("name")} required error={state?.errors?.name?.[0]}>
              {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required />}
            </FormField>

            <div className="grid gap-x-8 gap-y-3 lg:grid-cols-2">
              <FormField label={t("category")} error={state?.errors?.category_id?.[0]}>
                {(fieldProps) => (
                  <Select
                    {...fieldProps}
                    value={categoryId}
                    onValueChange={setCategoryId}
                    options={[{ value: "", label: "—" }, ...categories.map((c) => ({ value: c.id, label: c.name }))]}
                  />
                )}
              </FormField>
              <FormField label={t("brand")} error={state?.errors?.brand?.[0]}>
                {(fieldProps) => <Input {...fieldProps} value={brand} onChange={(e) => setBrand(e.target.value)} maxLength={255} />}
              </FormField>
            </div>

            <FormField label={t("manufacturer")} error={state?.errors?.manufacturer_id?.[0]}>
              {(fieldProps) => (
                <Select
                  {...fieldProps}
                  value={manufacturerId}
                  onValueChange={setManufacturerId}
                  options={[{ value: "", label: "—" }, ...manufacturers.map((m) => ({ value: m.id, label: m.name }))]}
                />
              )}
            </FormField>

            <FormField label={t("notes")} error={state?.errors?.notes?.[0]}>
              {(fieldProps) => <Textarea {...fieldProps} value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} maxLength={2000} />}
            </FormField>
        </FormSection>
      </div>

      <div>
        <FormSection legend={t("stock")}>
            <div className="grid gap-x-8 gap-y-3 lg:grid-cols-2">
              <FormField label={t("minimum_stock")} error={state?.errors?.minimum_stock?.[0]}>
                {(fieldProps) => (
                  <Input {...fieldProps} type="number" step="0.0001" min="0" value={minimumStock} onChange={(e) => setMinimumStock(e.target.value)} />
                )}
              </FormField>
              <FormField label={t("reorder_level")} error={state?.errors?.reorder_level?.[0]}>
                {(fieldProps) => (
                  <Input {...fieldProps} type="number" step="0.0001" min="0" value={reorderLevel} onChange={(e) => setReorderLevel(e.target.value)} />
                )}
              </FormField>
            </div>
            <p className="text-xs text-foreground-muted sm:pl-[11.25rem]">{t("reorder_hint")}</p>

            <div className="grid gap-x-8 gap-y-3 lg:grid-cols-2">
              <FormField label={t("lead_time_days")} error={state?.errors?.lead_time_days?.[0]}>
                {(fieldProps) => (
                  <Input {...fieldProps} type="number" min="0" max="3650" value={leadTimeDays} onChange={(e) => setLeadTimeDays(e.target.value)} />
                )}
              </FormField>
              <FormField label={t("shelf_life_days")} error={state?.errors?.shelf_life_days?.[0]}>
                {(fieldProps) => (
                  <Input {...fieldProps} type="number" min="0" max="36500" value={shelfLifeDays} onChange={(e) => setShelfLifeDays(e.target.value)} />
                )}
              </FormField>
            </div>

            <div className="flex flex-col gap-2">
              <label className="flex items-center gap-2 sm:ml-[11.25rem] text-sm text-foreground">
                <Checkbox checked={isCriticalSpare} onCheckedChange={setIsCriticalSpare} />
                {t("is_critical_spare")}
              </label>
              <p className="pl-7 text-xs text-foreground-muted sm:ml-[11.25rem]">{t("critical_hint")}</p>

              <label className="flex items-center gap-2 sm:ml-[11.25rem] text-sm text-foreground">
                <Checkbox checked={hazardous} onCheckedChange={setHazardous} />
                {t("hazardous")}
              </label>
            </div>

            <p className="text-xs text-foreground-muted">{t("no_opening_quantity_hint")}</p>
        </FormSection>
      </div>

      <FormActions>
        <Button type="button" variant="outline" onClick={() => router.back()}>
          <ArrowLeft /> {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          <Save /> {isEdit ? t("save_changes") : t("create_part")}
        </Button>
      </FormActions>
    </form>
  );
}

export { SparePartForm };
