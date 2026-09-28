import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { TechnicianForm } from "@/components/technicians/technician-form";
import { TechnicianSkills } from "@/components/technicians/technician-skills";
import { getT } from "@/lib/i18n-server";
import { updateTechnician, addSkill, removeSkill } from "../actions";

export default async function EditTechnicianPage({ params }) {
  const { technicianId } = await params;

  const [technician, options, t] = await Promise.all([
    apiFetch(`/technicians/${technicianId}`),
    apiFetch("/technicians/form-options"),
    getT("technician"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("technicians"), href: "/technicians" }, { label: technician.name }]}
        title={technician.name}
      />

      <div className="flex flex-col gap-6">
        <Card>
          <CardBody>
            <TechnicianForm
              technician={technician}
              factories={options.factories}
              departments={options.departments}
              productionLines={options.production_lines}
              users={options.users}
              action={updateTechnician.bind(null, technicianId)}
            />
          </CardBody>
        </Card>

        <TechnicianSkills
          technicianId={technicianId}
          skills={technician.skills ?? []}
          proficiencies={options.proficiencies}
          actions={{ addSkill, removeSkill }}
        />
      </div>
    </>
  );
}
