import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
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
      <ScreenHeader
        parentUrl="/maintenance/plans"
        title={t("new_plan_title")}
      />

      <PlanForm options={options} action={createPlan} previewAction={previewPlan} />
    </>
  );
}
