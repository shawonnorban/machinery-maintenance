import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { PlanForm } from "@/components/maintenance/plan-form";
import { getT } from "@/lib/i18n-server";
import { createPlan } from "./actions";
import { previewPlan } from "../actions";

export default async function CreateMaintenancePlanPage() {
  const [options, t] = await Promise.all([
    apiFetch("/maintenance-plans/form-options"),
    getT("maintenance"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("maintenance") }, { label: t("plans"), href: "/maintenance/plans" }, { label: t("new_plan") }]}
        title={t("new_plan_title")}
      />

      <Card>
        <CardBody>
          <PlanForm options={options} action={createPlan} previewAction={previewPlan} />
        </CardBody>
      </Card>
    </>
  );
}
