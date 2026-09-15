import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { PlanForm } from "@/components/maintenance/plan-form";
import { createPlan } from "./actions";
import { previewPlan } from "../actions";

export default async function CreateMaintenancePlanPage() {
  const options = await apiFetch("/maintenance-plans/form-options");

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Maintenance" }, { label: "Plans", href: "/maintenance/plans" }, { label: "New plan" }]} title="New maintenance plan" />

      <Card>
        <CardBody>
          <PlanForm options={options} action={createPlan} previewAction={previewPlan} />
        </CardBody>
      </Card>
    </>
  );
}
