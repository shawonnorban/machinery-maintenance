import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { MasterDataTable } from "@/components/settings/master-data-table";
import { getT } from "@/lib/i18n-server";
import { createRow, updateRow, setActive, deleteRow } from "./actions";

/**
 * One screen for every master-data type — mirrors `MasterDataController::
 * show()`, driven entirely by the `meta.schema`/`meta.reference_options`
 * `MasterDataApiController::show()` now returns (added this pass; nothing
 * before it exposed a type's field definitions or lookup options to an
 * API caller, only to the web's own Blade rendering).
 */
export default async function MasterDataTypePage({ params }) {
  const { type } = await params;

  const [response, t, tn] = await Promise.all([
    apiFetch(`/master-data/${type}`, { includeMeta: true }),
    getT("masterdata"),
    getT("nav"),
  ]);
  const { schema, reference_options: referenceOptions } = response.meta;

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("master_data"), href: "/settings/master-data" }, { label: schema.title }]}
        title={schema.title}
      />

      <MasterDataTable
        typeKey={type}
        rows={response.data}
        schema={schema}
        referenceOptions={referenceOptions}
        actions={{ createRow, updateRow, setActive, deleteRow }}
      />
    </>
  );
}
