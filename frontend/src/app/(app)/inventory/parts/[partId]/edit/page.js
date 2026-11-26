import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
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
      <ScreenHeader
        parentUrl="/inventory/parts" trail={[{ label: part.part_number, href: `/inventory/parts/${partId}` }]}
        title={t("edit_part_title", { number: part.part_number })}
      />

      <SparePartForm
        part={part}
        categories={formOptions.categories}
        manufacturers={formOptions.manufacturers}
        uoms={formOptions.uoms}
        action={updateSparePart.bind(null, partId)}
      />
    </>
  );
}
