import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { TechnicianForm } from "@/components/technicians/technician-form";
import { createTechnician } from "./actions";

export default async function CreateTechnicianPage() {
  const options = await apiFetch("/technicians/form-options");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Technicians", href: "/technicians" }, { label: "New technician" }]}
        title="New technician"
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
