import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { PlanForm } from "@/components/maintenance/plan-form";
import { getT } from "@/lib/i18n-server";
import { updatePlan } from "../actions";
import { previewPlan } from "../../actions";

export default async function EditMaintenancePlanPage({ params }) {
  const { planId } = await params;

  const [plan, options, t, tc] = await Promise.all([
    apiFetch(`/maintenance-plans/${planId}`),
    apiFetch("/maintenance-plans/form-options"),
    getT("maintenance"),
    getT("common"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("maintenance") },
          { label: t("plans"), href: "/maintenance/plans" },
          { label: plan.name, href: `/maintenance/plans/${planId}` },
          { label: tc("edit") },
        ]}
        title={plan.name}
      />

      <Card>
        <CardBody>
          <PlanForm plan={plan} options={options} action={updatePlan.bind(null, planId)} previewAction={previewPlan} />
        </CardBody>
      </Card>
    </>
  );
}
