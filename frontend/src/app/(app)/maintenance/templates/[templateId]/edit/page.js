import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { TemplateForm } from "@/components/maintenance/template-form";
import { getT } from "@/lib/i18n-server";
import { updateTemplate } from "./actions";

export default async function EditMaintenanceTemplatePage({ params }) {
  const { templateId } = await params;

  const [template, options, t, tc] = await Promise.all([
    apiFetch(`/maintenance-templates/${templateId}`),
    apiFetch("/maintenance-templates/form-options"),
    getT("maintenance"),
    getT("common"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("maintenance") },
          { label: t("templates_page_title"), href: "/maintenance/templates" },
          { label: template.name, href: `/maintenance/templates/${templateId}` },
          { label: tc("edit") },
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
