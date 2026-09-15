import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { SparePartForm } from "@/components/inventory/spare-part-form";
import { createSparePart } from "./actions";

export default async function CreateSparePartPage() {
  const formOptions = await apiFetch("/spare-parts/form-options");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Spare Parts", href: "/inventory/parts" }, { label: "New" }]}
        title="New spare part"
      />

      <SparePartForm categories={formOptions.categories} action={createSparePart} />
    </>
  );
}
