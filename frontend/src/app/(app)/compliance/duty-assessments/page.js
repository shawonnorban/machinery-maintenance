import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { DutyAssessmentsPanel } from "@/components/compliance/duty-assessments-panel";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { assessDuty } from "./actions";

/** Mirrors `BondComplianceApiController::assessments` (Shared Systems spec §3.4). */
export default async function DutyAssessmentsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const reason = params.reason ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (reason) query.set("reason", reason);

  const [assessments, permissions, t] = await Promise.all([
    apiFetch(`/duty-assessments?${query.toString()}`, { includeMeta: true }),
    apiFetch("/auth/permissions"),
    getT("compliance"),
  ]);

  const canAssess = new Set(permissions.permissions).has("compliance.duty.assess");

  // The dialog's lists are only worth fetching for someone who can open
  // it, and the endpoint would refuse a plain viewer anyway.
  const [options, itemOptions, licences] = canAssess
    ? await Promise.all([
        apiFetch("/bond-licences/form-options"),
        apiFetch("/purchase-requisitions/form-options"),
        apiFetch("/bond-licences?status=ACTIVE&per_page=100", { includeMeta: true }),
      ])
    : [{}, {}, { data: [] }];

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("compliance") }, { label: t("duty_assessments") }]}
        title={t("duty_assessments")}
        description={t("duty_hint")}
      />

      <DutyAssessmentsPanel
        rows={assessments.data}
        meta={assessments.meta}
        page={page}
        search={search}
        reason={reason}
        options={options}
        licences={licences.data ?? []}
        departments={itemOptions.item_departments ?? []}
        searchItems={searchItems}
        canAssess={canAssess}
        onAssess={assessDuty}
      />
    </>
  );
}
