import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { TemplateForm } from "@/components/maintenance/template-form";
import { createTemplate } from "./actions";

export default async function CreateMaintenanceTemplatePage() {
  const options = await apiFetch("/maintenance-templates/form-options");

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Maintenance" }, { label: "Templates", href: "/maintenance/templates" }, { label: "New checklist" }]} title="New checklist template" />

      <Card>
        <CardBody>
          <TemplateForm options={options} action={createTemplate} />
        </CardBody>
      </Card>
    </>
  );
}
