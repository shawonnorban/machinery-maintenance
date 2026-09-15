import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { MasterDataTable } from "@/components/settings/master-data-table";
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

  const response = await apiFetch(`/master-data/${type}`, { includeMeta: true });
  const { schema, reference_options: referenceOptions } = response.meta;

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Settings" }, { label: "Master Data", href: "/settings/master-data" }, { label: schema.title }]} title={schema.title} />

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
