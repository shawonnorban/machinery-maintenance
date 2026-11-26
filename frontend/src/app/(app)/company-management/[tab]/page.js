import { notFound } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { getLocale } from "next-intl/server";
import { ErpPageHeader } from "@/components/layout/erp-page-header";
import { ScreenTabs } from "@/components/erp/screen-tabs";
import { RecordsGrid } from "@/components/erp/records-grid";
import { screenContext } from "@/lib/navigation";
import { CompanyGrid } from "@/components/company/company-grid";
import { CompanyProfileForm } from "@/components/company/company-profile-form";
import { CompanyLogoForm } from "@/components/settings/company-logo-form";
import { CompanyDocuments } from "@/components/company/company-documents";
import { MachineMaster, MachineAllocation } from "@/components/company/machine-register";
import { COMPANY_TABS, findCompanyTab } from "@/components/company/company-tabs";
import { getT } from "@/lib/i18n-server";
import * as actions from "./actions";

const DOCUMENT_TYPES = ["TRADE_LICENSE", "TIN", "BIN", "BOND_LICENSE", "FIRE_LICENSE", "ENVIRONMENT", "OTHER"];

/** A grid field in the shape the master-data schema uses, for the tabs over their own APIs. */
function field(t, name, type, extra = {}) {
  return { name, type, label: t(`fields.${name}`), required: false, options: [], option_labels: {}, in_list: true, default: null, auto: false, ...extra };
}

function factorySchema(t) {
  return {
    fields: [
      // The factory's code may change (user, 2026-11-20): up to 5 letters / digits; what keeps it as text follows.
      field(t, "code", "CODE", { required: true, editable: true, max: 5 }),
      field(t, "name", "TEXT", { required: true }),
      field(t, "address", "TEXTAREA"),
      field(t, "contact", "TEXT"),
      field(t, "established_date", "DATE"),
      field(t, "area_sqft", "NUMBER"),
      field(t, "remarks", "TEXTAREA"),
      // Not a column on Brickland's grid, but the factory API needs it on
      // every save; carried along so Save never drops it.
      field(t, "timezone", "ENUM", { required: true, in_list: false, options: ["Asia/Dhaka"], option_labels: { "Asia/Dhaka": "Asia/Dhaka" }, default: "Asia/Dhaka" }),
    ],
    computed: [],
  };
}

function documentSchema(t) {
  return {
    fields: [
      field(t, "document_type", "ENUM", {
        required: true,
        options: DOCUMENT_TYPES,
        option_labels: Object.fromEntries(DOCUMENT_TYPES.map((type) => [type, t(`document_types.${type}`)])),
      }),
      field(t, "title", "TEXT", { required: true }),
      field(t, "document_no", "TEXT"),
      field(t, "issue_date", "DATE"),
      field(t, "expiry_date", "DATE"),
      field(t, "issued_by", "TEXT"),
      field(t, "status", "ENUM", {
        options: ["ACTIVE", "INACTIVE"],
        option_labels: { ACTIVE: t("status.ACTIVE"), INACTIVE: t("status.INACTIVE") },
        default: "ACTIVE",
      }),
    ],
    computed: [],
  };
}

/**
 * The read-only Records tab over a screen's rows: the grid's own columns, with
 * reference ids shown by name and enums by label.
 */
function recordsOf(schema, rows, referenceOptions = {}) {
  const fields = schema.fields.filter((f) => f.in_list !== false && f.type !== "TEXTAREA");
  const columns = fields.map((f) => {
    const base = { key: f.name, label: f.label };
    if (f.name === "status") return { ...base, type: "status", labels: f.option_labels };
    if (f.type === "CODE") return { ...base, type: "mono" };
    if (f.type === "NUMBER") return { ...base, type: "number" };
    if (f.type === "BOOLEAN") return { ...base, type: "yesno" };
    if (f.type === "DATE") return { ...base, type: "date" };
    if (f.type === "ENUM") return { ...base, type: "badge", labels: f.option_labels };
    return base;
  });
  const shown = rows.map((row) => {
    const copy = { ...row };
    for (const f of fields) {
      if ((f.type === "REFERENCE" || f.type === "BELONGS_TO") && row[f.name]) {
        copy[f.name] = (referenceOptions[f.name] ?? []).find((o) => o.id === row[f.name])?.label ?? row[f.name];
      }
    }
    return copy;
  });
  return { columns: [...columns, ...(schema.computed ?? []).map((c) => ({ key: c.name, label: c.label, type: "number" }))], rows: shown };
}

/**
 * Company Management (AnnotechERP 01), as Brickland lays it out: the page
 * header ("NN of 14"), a tab strip of "Records N" and the module's sibling
 * screens, then the screen — the shared inline grid for master data, its own
 * form or grid for the rest. `?tab=records` shows the rows read-only, with
 * search, sort, pager and export.
 */
export default async function CompanyManagementTabPage({ params, searchParams }) {
  const { tab: tabKey } = await params;
  const query = await searchParams;
  const tab = findCompanyTab(tabKey);
  if (!tab) notFound();

  const [t, te, locale, context] = await Promise.all([getT("company"), getT("erp"), getLocale(), screenContext(`/company-management/${tabKey}`)]);

  let body;
  let count = 0;
  let records = null;

  if (tab.type) {
    const response = await apiFetch(`/master-data/${tab.type}?per_page=100`, { includeMeta: true });
    count = response.meta?.total ?? response.data.length;
    records = recordsOf(response.meta.schema, response.data, response.meta.reference_options);
    body = (
      <CompanyGrid
        tabKey={tab.key}
        typeKey={tab.type}
        rows={response.data}
        schema={response.meta.schema}
        referenceOptions={response.meta.reference_options}
        actions={{ createRow: actions.createRow, saveRows: actions.saveRows, deleteRow: actions.deleteRow }}
      />
    );
  } else if (tab.kind === "profile") {
    const profile = await apiFetch("/company/profile");
    count = 1;
    body = (
      <div className="flex flex-col gap-4">
        <CompanyLogoForm logoUrl={profile.logo_url} actions={{ upload: actions.uploadLogo, remove: actions.removeLogo }} />
        <CompanyProfileForm profile={profile} action={actions.updateProfile} />
      </div>
    );
  } else if (tab.kind === "factory") {
    const factories = await apiFetch("/factories?per_page=100");
    count = factories.length;
    records = recordsOf(factorySchema(t), factories);
    body = (
      <CompanyGrid
        tabKey="factory"
        typeKey="factories"
        rows={factories}
        schema={factorySchema(t)}
        referenceOptions={{}}
        actions={{ createRow: actions.createFactory, saveRows: actions.saveFactories, deleteRow: actions.deleteFactory }}
      />
    );
  } else if (tab.kind === "documents") {
    const documents = await apiFetch("/company/documents");
    count = documents.length;
    records = recordsOf(documentSchema(t), documents);
    body = (
      <CompanyDocuments
        rows={documents}
        schema={documentSchema(t)}
        actions={{
          createDocument: actions.createDocument,
          saveDocuments: actions.saveDocuments,
          deleteDocument: actions.deleteDocument,
          listDocumentFiles: actions.listDocumentFiles,
          uploadDocumentFile: actions.uploadDocumentFile,
        }}
      />
    );
  } else {
    const machines = await apiFetch("/company/machines");
    count = machines.length;
    body = tab.kind === "machines" ? <MachineMaster machines={machines} saveMachines={actions.saveMachines} /> : <MachineAllocation machines={machines} />;
  }

  const showRecords = query.tab === "records" && records !== null;
  const tabs = [
    ...(records !== null ? [{ key: "records", label: te("records"), icon: "Table2", count, href: `/company-management/${tab.key}?tab=records` }] : []),
    ...COMPANY_TABS.map((item) => ({ key: item.key, label: t(`tabs.${item.key}`), icon: "ListChecks", href: `/company-management/${item.key}` })),
  ];

  return (
    <>
      <ErpPageHeader context={context} title={context ? undefined : t(`tabs.${tab.key}`)} description={t(`descriptions.${tab.key}`)} icon={context ? undefined : "Building2"} moduleCode="COMPANY" locale={locale} />
      <ScreenTabs basePath={`/company-management/${tab.key}`} active={showRecords ? "records" : tab.key} tabs={tabs} />
      {showRecords ? <RecordsGrid exportName={tab.key} rows={records.rows} columns={records.columns} /> : body}
    </>
  );
}
