import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
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
      <PageHeader
        breadcrumb={[{ label: t("maintenance") }, { label: t("templates_page_title"), href: "/maintenance/templates" }, { label: t("new_checklist") }]}
        title={t("new_checklist_template_title")}
      />

      <Card>
        <CardBody>
          <TemplateForm options={options} action={createTemplate} />
        </CardBody>
      </Card>
    </>
  );
}
