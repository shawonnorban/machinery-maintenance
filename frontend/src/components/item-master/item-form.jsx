"use client";

import { startTransition, useActionState, useState } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useT } from "@/lib/i18n";

const EMPTY_FABRIC = { fabric_type: "", composition: "", gsm: "", width: "", construction: "", finish: "", shrinkage: "", pantone: "", color_fastness: "" };
const EMPTY_YARN = { count: "", type: "", fiber: "", twist: "", color: "" };
const EMPTY_CHEMICAL = { expiry_tracking: false, hazard_info: "", batch_tracking: false };

function blank(option) {
  return [{ value: "", label: "—" }, ...option];
}

/** Controlled inputs want "" not null. */
function nullsToEmpty(block) {
  if (!block) return {};
  return Object.fromEntries(Object.entries(block).map(([key, value]) => [key, value ?? ""]));
}

/**
 * One form for creating and editing a library item. The item type drives
 * the rest: picking a department fills it from the department's default
 * (still editable), and FABRIC/YARN/CHEMICAL each open their own spec
 * section. Categories cascade — sub-category shows the children of the
 * chosen category, detail category the children of that.
 */
function ItemForm({ item = null, options, action }) {
  const t = useT("itemmaster");
  const tc = useT("common");
  const isEdit = item !== null;
  const router = useRouter();
  const [state, dispatch, pending] = useActionState(action, null);

  const [itemCode, setItemCode] = useState(item?.item_code ?? "");
  const [name, setName] = useState(item?.name ?? "");
  const [description, setDescription] = useState(item?.description ?? "");
  const [departmentId, setDepartmentId] = useState(item?.item_department?.id ?? "");
  const [itemType, setItemType] = useState(item?.item_type ?? "GENERAL");
  const [trackingMode, setTrackingMode] = useState(item?.tracking_mode ?? "QUANTITY");
  const [categoryId, setCategoryId] = useState(item?.category?.id ?? "");
  const [subCategoryId, setSubCategoryId] = useState(item?.sub_category?.id ?? "");
  const [detailCategoryId, setDetailCategoryId] = useState(item?.detail_category?.id ?? "");
  const [uomId, setUomId] = useState(item?.uom?.id ?? "");
  const [purchaseUomId, setPurchaseUomId] = useState(item?.purchase_uom?.id ?? "");
  const [model, setModel] = useState(item?.model ?? "");
  const [brand, setBrand] = useState(item?.brand ?? "");
  const [manufacturerId, setManufacturerId] = useState(item?.manufacturer?.id ?? "");
  const [origin, setOrigin] = useState(item?.origin ?? "");
  const [defaultStoreId, setDefaultStoreId] = useState(item?.default_store?.id ?? "");
  const [hsCodeId, setHsCodeId] = useState(item?.hs_code?.id ?? "");
  const [defaultVendorId, setDefaultVendorId] = useState(item?.default_vendor?.id ?? "");
  const [minimumStock, setMinimumStock] = useState(item?.minimum_stock ?? "0");
  const [reorderLevel, setReorderLevel] = useState(item?.reorder_level ?? "0");
  const [isReturnable, setIsReturnable] = useState(item?.is_returnable ?? false);
  const [hazardous, setHazardous] = useState(item?.hazardous ?? false);
  const [fabric, setFabric] = useState({ ...EMPTY_FABRIC, ...nullsToEmpty(item?.fabric) });
  const [yarn, setYarn] = useState({ ...EMPTY_YARN, ...nullsToEmpty(item?.yarn) });
  const [chemical, setChemical] = useState({ ...EMPTY_CHEMICAL, ...nullsToEmpty(item?.chemical) });

  const categories = options.categories ?? [];
  const topCategories = categories.filter((c) => !c.parent_id);
  const subCategories = categories.filter((c) => c.parent_id && c.parent_id === categoryId);
  const detailCategories = categories.filter((c) => c.parent_id && c.parent_id === subCategoryId);

  const uomOptions = (options.uoms ?? []).map((u) => ({ value: u.id, label: `${u.code} — ${u.name}` }));
  if (item?.uom && !uomOptions.some((o) => o.value === item.uom.id)) uomOptions.unshift({ value: item.uom.id, label: item.uom.code });

  function pickDepartment(id) {
    setDepartmentId(id);
    const department = (options.item_departments ?? []).find((d) => d.id === id);
    if (department?.default_item_type) setItemType(department.default_item_type);
  }

  function pickCategory(id) {
    setCategoryId(id);
    setSubCategoryId("");
    setDetailCategoryId("");
  }

  function pickSubCategory(id) {
    setSubCategoryId(id);
    setDetailCategoryId("");
  }

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("item_code", itemCode);
    formData.set("name", name);
    if (description) formData.set("description", description);
    formData.set("item_type", itemType);
    formData.set("tracking_mode", trackingMode);
    if (departmentId) formData.set("item_department_id", departmentId);
    if (categoryId) formData.set("category_id", categoryId);
    if (subCategoryId) formData.set("sub_category_id", subCategoryId);
    if (detailCategoryId) formData.set("detail_category_id", detailCategoryId);
    formData.set("uom_id", uomId);
    if (purchaseUomId) formData.set("purchase_uom_id", purchaseUomId);
    if (model) formData.set("model", model);
    if (brand) formData.set("brand", brand);
    if (manufacturerId) formData.set("manufacturer_id", manufacturerId);
    if (origin) formData.set("origin", origin);
    if (defaultStoreId) formData.set("default_store_id", defaultStoreId);
    if (hsCodeId) formData.set("hs_code_id", hsCodeId);
    if (defaultVendorId) formData.set("default_vendor_id", defaultVendorId);
    formData.set("minimum_stock", minimumStock || "0");
    formData.set("reorder_level", reorderLevel || "0");
    formData.set("is_returnable", isReturnable ? "1" : "0");
    formData.set("hazardous", hazardous ? "1" : "0");

    if (itemType === "FABRIC") {
      formData.set("fabric._present", "1");
      for (const [key, value] of Object.entries(fabric)) formData.set(`fabric.${key}`, value ?? "");
    }
    if (itemType === "YARN") {
      formData.set("yarn._present", "1");
      for (const [key, value] of Object.entries(yarn)) formData.set(`yarn.${key}`, value ?? "");
    }
    if (itemType === "CHEMICAL") {
      formData.set("chemical._present", "1");
      formData.set("chemical.expiry_tracking", chemical.expiry_tracking ? "1" : "0");
      formData.set("chemical.batch_tracking", chemical.batch_tracking ? "1" : "0");
      formData.set("chemical.hazard_info", chemical.hazard_info ?? "");
    }

    startTransition(() => dispatch(formData));
  }

  const error = (key) => state?.errors?.[key]?.[0];

  return (
    <form onSubmit={handleSubmit} className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      {state?.status === "error" && !state.errors ? (
        <div className="lg:col-span-12">
          <Alert variant="danger">{state.message}</Alert>
        </div>
      ) : null}

      <div className="flex flex-col gap-4 lg:col-span-7">
        <Card>
          <CardHeader>
            <CardTitle>{t("details")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <FormField label={t("department")} error={error("item_department_id")}>
                {(fieldProps) => (
                  <Select {...fieldProps} value={departmentId} onValueChange={pickDepartment} options={blank((options.item_departments ?? []).map((d) => ({ value: d.id, label: d.name })))} />
                )}
              </FormField>
              <FormField label={t("item_type")} required helperText={departmentId ? t("item_type_hint") : undefined} error={error("item_type")}>
                {(fieldProps) => (
                  <Select {...fieldProps} value={itemType} onValueChange={setItemType} options={(options.item_types ?? []).map((type) => ({ value: type, label: t(`item_types.${type}`) }))} />
                )}
              </FormField>
            </div>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <FormField label={t("item_code")} required error={error("item_code")}>
                {(fieldProps) => <Input {...fieldProps} value={itemCode} onChange={(e) => setItemCode(e.target.value)} maxLength={64} required />}
              </FormField>
              <FormField label={t("unit")} required error={error("uom_id")}>
                {(fieldProps) => <Select {...fieldProps} value={uomId} onValueChange={setUomId} options={blank(uomOptions)} />}
              </FormField>
            </div>

            <FormField label={t("name")} required error={error("name")}>
              {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required />}
            </FormField>

            <FormField label={t("description")} error={error("description")}>
              {(fieldProps) => <Textarea {...fieldProps} value={description} onChange={(e) => setDescription(e.target.value)} rows={2} maxLength={2000} />}
            </FormField>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("classification")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <FormField label={t("category")} error={error("category_id")}>
                {(fieldProps) => <Select {...fieldProps} value={categoryId} onValueChange={pickCategory} options={blank(topCategories.map((c) => ({ value: c.id, label: c.name })))} />}
              </FormField>
              <FormField label={t("sub_category")} error={error("sub_category_id")}>
                {(fieldProps) => (
                  <Select {...fieldProps} value={subCategoryId} onValueChange={pickSubCategory} disabled={subCategories.length === 0} options={blank(subCategories.map((c) => ({ value: c.id, label: c.name })))} />
                )}
              </FormField>
              <FormField label={t("detail_category")} error={error("detail_category_id")}>
                {(fieldProps) => (
                  <Select {...fieldProps} value={detailCategoryId} onValueChange={setDetailCategoryId} disabled={detailCategories.length === 0} options={blank(detailCategories.map((c) => ({ value: c.id, label: c.name })))} />
                )}
              </FormField>
            </div>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <FormField label={t("brand")} error={error("brand")}>
                {(fieldProps) => <Input {...fieldProps} value={brand} onChange={(e) => setBrand(e.target.value)} maxLength={255} />}
              </FormField>
              <FormField label={t("model")} error={error("model")}>
                {(fieldProps) => <Input {...fieldProps} value={model} onChange={(e) => setModel(e.target.value)} maxLength={100} />}
              </FormField>
              <FormField label={t("origin")} error={error("origin")}>
                {(fieldProps) => <Input {...fieldProps} value={origin} onChange={(e) => setOrigin(e.target.value)} maxLength={100} />}
              </FormField>
            </div>

            <FormField label={t("manufacturer")} error={error("manufacturer_id")}>
              {(fieldProps) => <Select {...fieldProps} value={manufacturerId} onValueChange={setManufacturerId} options={blank((options.manufacturers ?? []).map((m) => ({ value: m.id, label: m.name })))} />}
            </FormField>
          </CardBody>
        </Card>

        {itemType === "FABRIC" ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("fabric_spec")}</CardTitle>
            </CardHeader>
            <CardBody className="flex flex-col gap-4">
              <p className="text-xs text-foreground-muted">{t("fabric_spec_hint")}</p>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <FormField label={t("fabric_type")} error={error("fabric.fabric_type")}>
                  {(fieldProps) => (
                    <Select {...fieldProps} value={fabric.fabric_type} onValueChange={(v) => setFabric({ ...fabric, fabric_type: v })} options={blank((options.fabric_types ?? []).map((ft) => ({ value: ft, label: t(`fabric_types.${ft}`) })))} />
                  )}
                </FormField>
                <FormField label={t("gsm")} required error={error("fabric.gsm")}>
                  {(fieldProps) => <Input {...fieldProps} type="number" step="0.01" min="0" value={fabric.gsm} onChange={(e) => setFabric({ ...fabric, gsm: e.target.value })} />}
                </FormField>
                <FormField label={t("width")} required error={error("fabric.width")}>
                  {(fieldProps) => <Input {...fieldProps} type="number" step="0.01" min="0" value={fabric.width} onChange={(e) => setFabric({ ...fabric, width: e.target.value })} />}
                </FormField>
              </div>
              <FormField label={t("composition")} required error={error("fabric.composition")}>
                {(fieldProps) => <Input {...fieldProps} value={fabric.composition} onChange={(e) => setFabric({ ...fabric, composition: e.target.value })} maxLength={255} />}
              </FormField>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FormField label={t("construction")} error={error("fabric.construction")}>
                  {(fieldProps) => <Input {...fieldProps} value={fabric.construction} onChange={(e) => setFabric({ ...fabric, construction: e.target.value })} maxLength={255} />}
                </FormField>
                <FormField label={t("finish")} error={error("fabric.finish")}>
                  {(fieldProps) => <Input {...fieldProps} value={fabric.finish} onChange={(e) => setFabric({ ...fabric, finish: e.target.value })} maxLength={255} />}
                </FormField>
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <FormField label={t("shrinkage")} error={error("fabric.shrinkage")}>
                  {(fieldProps) => <Input {...fieldProps} value={fabric.shrinkage} onChange={(e) => setFabric({ ...fabric, shrinkage: e.target.value })} maxLength={64} />}
                </FormField>
                <FormField label={t("pantone")} error={error("fabric.pantone")}>
                  {(fieldProps) => <Input {...fieldProps} value={fabric.pantone} onChange={(e) => setFabric({ ...fabric, pantone: e.target.value })} maxLength={32} />}
                </FormField>
                <FormField label={t("color_fastness")} error={error("fabric.color_fastness")}>
                  {(fieldProps) => <Input {...fieldProps} value={fabric.color_fastness} onChange={(e) => setFabric({ ...fabric, color_fastness: e.target.value })} maxLength={64} />}
                </FormField>
              </div>
            </CardBody>
          </Card>
        ) : null}

        {itemType === "YARN" ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("yarn_spec")}</CardTitle>
            </CardHeader>
            <CardBody className="flex flex-col gap-4">
              <p className="text-xs text-foreground-muted">{t("yarn_spec_hint")}</p>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <FormField label={t("yarn_count")} required error={error("yarn.count")}>
                  {(fieldProps) => <Input {...fieldProps} value={yarn.count} onChange={(e) => setYarn({ ...yarn, count: e.target.value })} maxLength={32} />}
                </FormField>
                <FormField label={t("fiber")} required error={error("yarn.fiber")}>
                  {(fieldProps) => <Input {...fieldProps} value={yarn.fiber} onChange={(e) => setYarn({ ...yarn, fiber: e.target.value })} maxLength={255} />}
                </FormField>
                <FormField label={t("yarn_type")} error={error("yarn.type")}>
                  {(fieldProps) => <Input {...fieldProps} value={yarn.type} onChange={(e) => setYarn({ ...yarn, type: e.target.value })} maxLength={64} />}
                </FormField>
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FormField label={t("twist")} error={error("yarn.twist")}>
                  {(fieldProps) => <Input {...fieldProps} value={yarn.twist} onChange={(e) => setYarn({ ...yarn, twist: e.target.value })} maxLength={32} />}
                </FormField>
                <FormField label={t("color")} error={error("yarn.color")}>
                  {(fieldProps) => <Input {...fieldProps} value={yarn.color} onChange={(e) => setYarn({ ...yarn, color: e.target.value })} maxLength={64} />}
                </FormField>
              </div>
            </CardBody>
          </Card>
        ) : null}

        {itemType === "CHEMICAL" ? (
          <Card>
            <CardHeader>
              <CardTitle>{t("chemical_spec")}</CardTitle>
            </CardHeader>
            <CardBody className="flex flex-col gap-4">
              <div className="flex flex-col gap-2">
                <label className="flex items-center gap-2 text-sm text-foreground">
                  <Checkbox checked={chemical.expiry_tracking} onCheckedChange={(v) => setChemical({ ...chemical, expiry_tracking: v })} />
                  {t("expiry_tracking")}
                </label>
                <label className="flex items-center gap-2 text-sm text-foreground">
                  <Checkbox checked={chemical.batch_tracking} onCheckedChange={(v) => setChemical({ ...chemical, batch_tracking: v })} />
                  {t("batch_tracking")}
                </label>
              </div>
              <FormField label={t("hazard_info")} error={error("chemical.hazard_info")}>
                {(fieldProps) => <Textarea {...fieldProps} value={chemical.hazard_info} onChange={(e) => setChemical({ ...chemical, hazard_info: e.target.value })} rows={2} maxLength={2000} />}
              </FormField>
            </CardBody>
          </Card>
        ) : null}
      </div>

      <div className="flex flex-col gap-4 lg:col-span-5">
        <Card>
          <CardHeader>
            <CardTitle>{t("stock_policy")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <FormField label={t("tracking_mode")} required error={error("tracking_mode")}>
                {(fieldProps) => <Select {...fieldProps} value={trackingMode} onValueChange={setTrackingMode} options={(options.tracking_modes ?? []).map((mode) => ({ value: mode, label: t(`tracking_modes.${mode}`) }))} />}
              </FormField>
              <FormField label={t("default_store")} error={error("default_store_id")}>
                {(fieldProps) => <Select {...fieldProps} value={defaultStoreId} onValueChange={setDefaultStoreId} options={blank((options.stores ?? []).map((s) => ({ value: s.id, label: s.warehouse ? `${s.name} — ${s.warehouse}` : s.name })))} />}
              </FormField>
            </div>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <FormField label={t("minimum_stock")} error={error("minimum_stock")}>
                {(fieldProps) => <Input {...fieldProps} type="number" step="0.0001" min="0" value={minimumStock} onChange={(e) => setMinimumStock(e.target.value)} />}
              </FormField>
              <FormField label={t("reorder_level")} error={error("reorder_level")}>
                {(fieldProps) => <Input {...fieldProps} type="number" step="0.0001" min="0" value={reorderLevel} onChange={(e) => setReorderLevel(e.target.value)} />}
              </FormField>
            </div>
            <p className="-mt-2 text-xs text-foreground-muted">{t("reorder_hint")}</p>
            <div className="flex flex-col gap-2">
              <label className="flex items-center gap-2 text-sm text-foreground">
                <Checkbox checked={isReturnable} onCheckedChange={setIsReturnable} />
                {t("is_returnable")}
              </label>
              <p className="pl-7 text-xs text-foreground-muted">{t("returnable_hint")}</p>
              <label className="flex items-center gap-2 text-sm text-foreground">
                <Checkbox checked={hazardous} onCheckedChange={setHazardous} />
                {t("hazardous")}
              </label>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("sourcing")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <FormField label={t("purchase_uom")} helperText={t("purchase_uom_hint")} error={error("purchase_uom_id")}>
              {(fieldProps) => <Select {...fieldProps} value={purchaseUomId} onValueChange={setPurchaseUomId} options={blank((options.uoms ?? []).map((u) => ({ value: u.id, label: `${u.code} — ${u.name}` })))} />}
            </FormField>
            <FormField label={t("hs_code")} error={error("hs_code_id")}>
              {(fieldProps) => <Select {...fieldProps} value={hsCodeId} onValueChange={setHsCodeId} options={blank((options.hs_codes ?? []).map((h) => ({ value: h.id, label: `${h.code} — ${h.description}` })))} />}
            </FormField>
            <FormField label={t("default_vendor")} error={error("default_vendor_id")}>
              {(fieldProps) => <Select {...fieldProps} value={defaultVendorId} onValueChange={setDefaultVendorId} options={blank((options.vendors ?? []).map((v) => ({ value: v.id, label: v.name })))} />}
            </FormField>
          </CardBody>
        </Card>

        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => router.back()}>
            {tc("cancel")}
          </Button>
          <Button type="submit" loading={pending}>
            {isEdit ? t("save_changes") : t("create_item")}
          </Button>
        </div>
      </div>
    </form>
  );
}

export { ItemForm };
