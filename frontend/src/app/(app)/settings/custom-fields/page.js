import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { CustomFieldTypePicker } from "@/components/settings/custom-field-type-picker";
import { CustomFieldDefinitionsTable } from "@/components/settings/custom-field-definitions-table";
import { FIELDABLE_TYPES } from "@/lib/custom-field-types";
import { getT } from "@/lib/i18n-server";
import { createDefinition, updateDefinition, setActive, deleteDefinition } from "./actions";

/**
 * Admin screen for `CustomFieldDefinition` — one `fieldable_type` at a time,
 * switched via `?type=`, mirroring `master-data`'s own type-scoped screens
 * without needing a matching Next.js dynamic segment per entity (the list
 * of entity types is fixed and small, unlike master data's two dozen).
 */
export default async function CustomFieldsPage({ searchParams }) {
  const params = await searchParams;
  const type = FIELDABLE_TYPES.includes(params.type) ? params.type : FIELDABLE_TYPES[0];

  const [rows, t, tn] = await Promise.all([
    apiFetch(`/custom-fields/definitions?fieldable_type=${type}`),
    getT("custom_field"),
    getT("nav"),
  ]);

  const labels = Object.fromEntries(FIELDABLE_TYPES.map((entityType) => [entityType, t(`entity_${entityType}`)]));

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("custom_fields") }]}
        title={t("custom_fields")}
        description={t("page_description")}
      />

      <div className="mb-4 max-w-xs">
        <CustomFieldTypePicker value={type} labels={labels} />
      </div>

      <CustomFieldDefinitionsTable
        fieldableType={type}
        rows={rows}
        actions={{ createDefinition, updateDefinition, setActive, deleteDefinition }}
      />
    </>
  );
}
