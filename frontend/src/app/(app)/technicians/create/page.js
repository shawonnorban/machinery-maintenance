import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
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
      <ScreenHeader
        parentUrl="/technicians"
        title={t("new_technician_title")}
      />

      <TechnicianForm
            factories={options.factories}
            departments={options.departments}
            productionLines={options.production_lines}
            users={options.users}
            employees={options.employees}
            action={createTechnician}
          />
    </>
  );
}
