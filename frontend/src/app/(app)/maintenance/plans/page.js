import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { PlansTable } from "@/components/maintenance/plans-table";
import { getT } from "@/lib/i18n-server";
import { activatePlan, deactivatePlan, deletePlan } from "./actions";

/** Mirrors `PlanController::index`. */
export default async function MaintenancePlansPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const active = params.active ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (active) query.set("active", active);

  const [plans, t] = await Promise.all([
    apiFetch(`/maintenance-plans?${query.toString()}`, { includeMeta: true }),
    getT("maintenance"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("maintenance") }, { label: t("plans") }]}
        title={t("plans")}
        description={t("plans_page_description")}
        actions={
          <Link href="/maintenance/plans/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {t("new_plan")}
          </Link>
        }
      />

      <PlansTable
        plans={plans.data}
        meta={plans.meta}
        page={page}
        active={active}
        actions={{ activatePlan, deactivatePlan, deletePlan }}
      />
    </>
  );
}
