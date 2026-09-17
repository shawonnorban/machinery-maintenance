import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
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
      <PageHeader
        breadcrumb={[{ label: t("spare_parts"), href: "/inventory/parts" }, { label: tc("new") }]}
        title={t("new_part")}
      />

      <SparePartForm categories={formOptions.categories} action={createSparePart} />
    </>
  );
}
