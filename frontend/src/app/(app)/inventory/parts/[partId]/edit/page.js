import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { SparePartForm } from "@/components/inventory/spare-part-form";
import { updateSparePart } from "./actions";

export default async function EditSparePartPage({ params }) {
  const { partId } = await params;

  const [part, formOptions] = await Promise.all([
    apiFetch(`/spare-parts/${partId}`),
    apiFetch("/spare-parts/form-options"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Spare Parts", href: "/inventory/parts" }, { label: part.part_number, href: `/inventory/parts/${partId}` }, { label: "Edit" }]}
        title={`Edit ${part.part_number}`}
      />

      <SparePartForm part={part} categories={formOptions.categories} action={updateSparePart.bind(null, partId)} />
    </>
  );
}
