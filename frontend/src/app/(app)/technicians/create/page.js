import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { TechnicianForm } from "@/components/technicians/technician-form";
import { getT } from "@/lib/i18n-server";
import { createTechnician } from "./actions";

export default async function CreateTechnicianPage() {
  const [options, t] = await Promise.all([
    apiFetch("/technicians/form-options"),
    getT("technician"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("technicians"), href: "/technicians" }, { label: t("new_technician_title") }]}
        title={t("new_technician_title")}
      />

      <Card>
        <CardBody>
          <TechnicianForm
            factories={options.factories}
            departments={options.departments}
            productionLines={options.production_lines}
            users={options.users}
            action={createTechnician}
          />
        </CardBody>
      </Card>
    </>
  );
}
