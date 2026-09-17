import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { SparePartForm } from "@/components/inventory/spare-part-form";
import { getT } from "@/lib/i18n-server";
import { updateSparePart } from "./actions";

export default async function EditSparePartPage({ params }) {
  const { partId } = await params;

  const [part, formOptions, t, tc] = await Promise.all([
    apiFetch(`/spare-parts/${partId}`),
    apiFetch("/spare-parts/form-options"),
    getT("inventory"),
    getT("common"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("spare_parts"), href: "/inventory/parts" },
          { label: part.part_number, href: `/inventory/parts/${partId}` },
          { label: tc("edit") },
        ]}
        title={t("edit_part_title", { number: part.part_number })}
      />

      <SparePartForm part={part} categories={formOptions.categories} action={updateSparePart.bind(null, partId)} />
    </>
  );
}
