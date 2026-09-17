"use client";

import { startTransition, useActionState, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select } from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Shared by `/assets/create` and `/assets/[assetId]/edit` — mirrors
 * `assets/_form.blade.php` field-for-field, including its two edit-only
 * rules: `asset_code` becomes read-only (it's printed on the machine and
 * referenced by every historical record), and factory/location become
 * fixed (moving between factories is a Transfer, recorded rather than
 * silently overwritten — see the Transfers tab, not this form). Fully
 * controlled rather than native `name`-attribute submission, since the
 * type→category and factory→location cascades (mirroring the web's own
 * `data-type`/`data-factory` JS filtering) need the values in JS state
 * regardless; submission builds `FormData` by hand instead of relying on
 * a native form post.
 *
 * @param {{
 *   asset?: object | null,
 *   options: { types: object[], categories: object[], manufacturers: object[], factories: object[], locations: object[], criticalities: string[] },
 *   action: (prevState: any, formData: FormData) => Promise<any>,
 * }} props
 */
function AssetForm({ asset = null, options, action }) {
  const t = useT("asset");
  const tc = useT("common");
  const isEdit = asset !== null;
  const router = useRouter();
  const toastManager = useToastManager();
  const [state, dispatch, pending] = useActionState(action, null);

  const [assetCode, setAssetCode] = useState(asset?.asset_code ?? "");
  const [name, setName] = useState(asset?.name ?? "");
  const [typeId, setTypeId] = useState(asset?.type?.id ?? "");
  const [categoryId, setCategoryId] = useState(asset?.category?.id ?? "");
  const [criticality, setCriticality] = useState(asset?.criticality ?? "MEDIUM");
  const [manufacturerId, setManufacturerId] = useState(asset?.manufacturer?.id ?? "");
  const [serialNumber, setSerialNumber] = useState(asset?.serial_number ?? "");
  const [barcode, setBarcode] = useState(asset?.barcode ?? "");
  const [factoryId, setFactoryId] = useState(asset?.factory?.id ?? "");
  const [locationId, setLocationId] = useState(asset?.location?.id ?? "");
  const [description, setDescription] = useState(asset?.description ?? "");
  const [purchaseDate, setPurchaseDate] = useState(asset?.purchase_date ?? null);
  const [installationDate, setInstallationDate] = useState(asset?.installation_date ?? null);
  const [commissioningDate, setCommissioningDate] = useState(asset?.commissioning_date ?? null);
  const [acquisitionCost, setAcquisitionCost] = useState("");
  const [installationCost, setInstallationCost] = useState("");
  const [currency, setCurrency] = useState("BDT");
  const [warrantyStart, setWarrantyStart] = useState(asset?.warranty?.start ?? null);
  const [warrantyEnd, setWarrantyEnd] = useState(asset?.warranty?.end ?? null);

  const categoryOptions = useMemo(
    () => options.categories.filter((category) => !typeId || category.asset_type_id === typeId),
    [options.categories, typeId],
  );
  const locationOptions = useMemo(
    () => options.locations.filter((location) => !factoryId || location.factory_id === factoryId),
    [options.locations, factoryId],
  );

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: isEdit ? t("asset_saved_toast") : t("asset_created_toast"), type: "success" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state, isEdit]);

  function handleSubmit(event) {
    event.preventDefault();

    const formData = new FormData();
    if (!isEdit) formData.set("asset_code", assetCode);
    formData.set("name", name);
    formData.set("asset_type_id", typeId);
    formData.set("asset_category_id", categoryId);
    formData.set("criticality", criticality);
    if (manufacturerId) formData.set("manufacturer_id", manufacturerId);
    if (serialNumber) formData.set("serial_number", serialNumber);
    if (barcode) formData.set("barcode", barcode);
    formData.set("current_factory_id", factoryId);
    formData.set("asset_location_id", locationId);
    if (description) formData.set("description", description);
    if (purchaseDate) formData.set("purchase_date", purchaseDate);
    if (installationDate) formData.set("installation_date", installationDate);
    if (commissioningDate) formData.set("commissioning_date", commissioningDate);
    if (acquisitionCost) formData.set("acquisition_cost", acquisitionCost);
    if (installationCost) formData.set("installation_cost", installationCost);
    if (acquisitionCost || installationCost) formData.set("currency", currency);
    if (warrantyStart) formData.set("warranty_start", warrantyStart);
    if (warrantyEnd) formData.set("warranty_end", warrantyEnd);
    if (isEdit) formData.set("version", asset.version);

    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {state?.status === "error" && !state.errors ? <Alert variant="danger">{state.message}</Alert> : null}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <FormField label={t("asset_code")} required helperText={isEdit ? t("asset_code_readonly_hint") : undefined} error={state?.errors?.asset_code?.[0]}>
          {(fieldProps) => (
            <Input {...fieldProps} value={assetCode} onChange={(e) => setAssetCode(e.target.value)} disabled={isEdit} required maxLength={64} />
          )}
        </FormField>

        <FormField label={t("name")} required className="sm:col-span-2" error={state?.errors?.name?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} required maxLength={255} />}
        </FormField>

        <FormField label={t("type")} required error={state?.errors?.asset_type_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={typeId}
              onValueChange={(value) => {
                setTypeId(value);
                setCategoryId("");
              }}
              placeholder={t("select_a_type")}
              options={options.types.map((type) => ({ value: type.id, label: type.name }))}
            />
          )}
        </FormField>

        <FormField label={t("category")} required error={state?.errors?.asset_category_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={categoryId}
              onValueChange={setCategoryId}
              placeholder={t("select_a_category")}
              options={categoryOptions.map((c) => ({ value: c.id, label: c.name }))}
            />
          )}
        </FormField>

        <FormField label={t("criticality")} required>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={criticality}
              onValueChange={setCriticality}
              options={options.criticalities.map((c) => ({ value: c, label: t(`criticality_${c.toLowerCase()}`) }))}
            />
          )}
        </FormField>

        <FormField label={t("manufacturer")}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={manufacturerId}
              onValueChange={setManufacturerId}
              placeholder={t("select_a_manufacturer")}
              options={options.manufacturers.map((m) => ({ value: m.id, label: m.name }))}
            />
          )}
        </FormField>

        <FormField label={t("serial_number")} error={state?.errors?.serial_number?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={serialNumber} onChange={(e) => setSerialNumber(e.target.value)} maxLength={128} />}
        </FormField>

        <FormField label={t("barcode")}>
          {(fieldProps) => <Input {...fieldProps} value={barcode} onChange={(e) => setBarcode(e.target.value)} maxLength={64} />}
        </FormField>

        <FormField
          label={t("factory")}
          required
          helperText={isEdit ? t("factory_readonly_hint") : undefined}
          error={state?.errors?.current_factory_id?.[0]}
        >
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={factoryId}
              onValueChange={(value) => {
                setFactoryId(value);
                setLocationId("");
              }}
              placeholder={t("select_a_factory")}
              disabled={isEdit}
              options={options.factories.map((f) => ({ value: f.id, label: f.name }))}
            />
          )}
        </FormField>

        <FormField label={t("location")} required error={state?.errors?.asset_location_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              value={locationId}
              onValueChange={setLocationId}
              placeholder={t("select_a_location")}
              disabled={isEdit}
              options={locationOptions.map((l) => ({ value: l.id, label: l.full_path || l.name }))}
            />
          )}
        </FormField>
      </div>

      <FormField label={t("description")}>
        {(fieldProps) => <Textarea {...fieldProps} value={description} onChange={(e) => setDescription(e.target.value)} rows={2} maxLength={5000} />}
      </FormField>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <FormField label={t("purchase_date")}>{() => <DatePicker value={purchaseDate} onChange={setPurchaseDate} />}</FormField>
        <FormField label={t("installation_date")}>{() => <DatePicker value={installationDate} onChange={setInstallationDate} />}</FormField>
        <FormField label={t("commissioning_date")}>{() => <DatePicker value={commissioningDate} onChange={setCommissioningDate} />}</FormField>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <FormField label={t("acquisition_cost")} error={state?.errors?.acquisition_cost?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="number" step="0.0001" min="0" value={acquisitionCost} onChange={(e) => setAcquisitionCost(e.target.value)} />}
        </FormField>
        <FormField label={t("installation_cost")}>
          {(fieldProps) => <Input {...fieldProps} type="number" step="0.0001" min="0" value={installationCost} onChange={(e) => setInstallationCost(e.target.value)} />}
        </FormField>
        <FormField label={t("currency")} error={state?.errors?.currency?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={currency} onChange={(e) => setCurrency(e.target.value)} maxLength={3} />}
        </FormField>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField label={t("warranty_start")}>{() => <DatePicker value={warrantyStart} onChange={setWarrantyStart} />}</FormField>
        <FormField label={t("warranty_end")} error={state?.errors?.warranty_end?.[0]}>
          {() => <DatePicker value={warrantyEnd} onChange={setWarrantyEnd} />}
        </FormField>
      </div>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" loading={pending}>
          {isEdit ? t("save_changes") : t("create_asset")}
        </Button>
      </div>
    </form>
  );
}

export { AssetForm };
