import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
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
      <ScreenHeader
        parentUrl="/technicians"
        title={technician.name}
      />

      <div className="flex flex-col gap-6">
        <TechnicianForm
              technician={technician}
              factories={options.factories}
              departments={options.departments}
              productionLines={options.production_lines}
              users={options.users}
            employees={options.employees}
              action={updateTechnician.bind(null, technicianId)}
            />

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
