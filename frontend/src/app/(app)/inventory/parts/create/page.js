import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { SparePartForm } from "@/components/inventory/spare-part-form";
import { getT } from "@/lib/i18n-server";
import { createSparePart } from "./actions";

export default async function CreateSparePartPage() {
  const [formOptions, t, tc] = await Promise.all([
    apiFetch("/spare-parts/form-options"),
    getT("inventory"),
    getT("common"),
  ]);

  return (
    <>
      <ScreenHeader
        parentUrl="/inventory/parts"
        title={t("new_part")}
      />

      <SparePartForm categories={formOptions.categories} manufacturers={formOptions.manufacturers} uoms={formOptions.uoms} action={createSparePart} />
    </>
  );
}
