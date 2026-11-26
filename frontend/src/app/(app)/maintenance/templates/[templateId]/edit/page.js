import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
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
      <ScreenHeader
        parentUrl="/maintenance/templates" trail={[{ label: template.name, href: `/maintenance/templates/${templateId}` }]}
        title={template.name}
      />

      <TemplateForm template={template} options={options} action={updateTemplate.bind(null, templateId)} />
    </>
  );
}
