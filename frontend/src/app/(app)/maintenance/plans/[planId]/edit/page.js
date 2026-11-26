import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
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
      <ScreenHeader
        parentUrl="/maintenance/plans" trail={[{ label: plan.name, href: `/maintenance/plans/${planId}` }]}
        title={plan.name}
      />

      <PlanForm plan={plan} options={options} action={updatePlan.bind(null, planId)} previewAction={previewPlan} />
    </>
  );
}
