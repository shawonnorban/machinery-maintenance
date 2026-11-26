import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { PlansTable } from "@/components/maintenance/plans-table";
import { getT } from "@/lib/i18n-server";
import { activatePlan, deactivatePlan, deletePlan } from "./actions";
import { applyPerPage } from "@/lib/per-page";

/** Mirrors `PlanController::index`. */
export default async function MaintenancePlansPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const active = params.active ?? "";
  const search = params.search ?? "";

  const query = new URLSearchParams({ page: String(page) });
  await applyPerPage(query, "/maintenance/plans");
  if (active) query.set("active", active);
  if (search) query.set("search", search);

  const [plans, t] = await Promise.all([
    apiFetch(`/maintenance-plans?${query.toString()}`, { includeMeta: true }),
    getT("maintenance"),
  ]);

  return (
    <>
      <ScreenHeader
        url="/maintenance/plans"
        title={t("plans")}
        description={t("plans_page_description")}
      />

      <PlansTable
        addHref="/maintenance/plans/create"
        plans={plans.data}
        meta={plans.meta}
        page={page}
        active={active} search={search}
        actions={{ activatePlan, deactivatePlan, deletePlan }}
      />
    </>
  );
}
