import { redirect } from "next/navigation";
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
/** The organisation lists live in Company Management (Brickland); their old addresses go there. */
const COMPANY_TABS = {
  buildings: "building",
  floors: "floor",
  departments: "department",
  sections: "section",
  "production-lines": "line",
  workstations: "workstation",
  designations: "designation",
};

export default async function MasterDataTypePage({ params }) {
  const { type } = await params;
  if (COMPANY_TABS[type]) redirect(`/company-management/${COMPANY_TABS[type]}`);
  // Grades are kept in HR & Payroll → Pay Grade.
  if (type === "grades") redirect("/hr-payroll/grade");
  // Units, stores and categories are kept on General Store's screens (one list each).
  const GENERAL_STORE = { uoms: "gs-units", stores: "gs-stores", "item-categories": "gs-categories", "spare-part-categories": "gs-categories" };
  if (GENERAL_STORE[type]) redirect(`/general-store/${GENERAL_STORE[type]}`);
  // GL accounts are Finance & Ledger's Chart of Account.
  if (type === "gl-accounts") redirect("/finance-ledger/chart-of-account");

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
