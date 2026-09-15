import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { TemplateForm } from "@/components/maintenance/template-form";
import { updateTemplate } from "./actions";

export default async function EditMaintenanceTemplatePage({ params }) {
  const { templateId } = await params;

  const [template, options] = await Promise.all([
    apiFetch(`/maintenance-templates/${templateId}`),
    apiFetch("/maintenance-templates/form-options"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: "Maintenance" },
          { label: "Templates", href: "/maintenance/templates" },
          { label: template.name, href: `/maintenance/templates/${templateId}` },
          { label: "Edit" },
        ]}
        title={template.name}
      />

      <Card>
        <CardBody>
          <TemplateForm template={template} options={options} action={updateTemplate.bind(null, templateId)} />
        </CardBody>
      </Card>
    </>
  );
}
