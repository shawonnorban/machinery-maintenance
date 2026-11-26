import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { TemplateForm } from "@/components/maintenance/template-form";
import { getT } from "@/lib/i18n-server";
import { createTemplate } from "./actions";

export default async function CreateMaintenanceTemplatePage() {
  const [options, t] = await Promise.all([
    apiFetch("/maintenance-templates/form-options"),
    getT("maintenance"),
  ]);

  return (
    <>
      <ScreenHeader
        parentUrl="/maintenance/templates"
        title={t("new_checklist_template_title")}
      />

      <TemplateForm options={options} action={createTemplate} />
    </>
  );
}
