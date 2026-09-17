"use client";

import { Modal } from "@/components/ui/modal";
import { LocationForm } from "@/components/locations/location-form";
import { useT } from "@/lib/i18n";

/** `key={location?.id ?? "create"}` at the call site remounts this fresh per target — `LocationForm`'s own cascading dropdown state is seeded once from `location` at mount. */
function LocationFormModal({ open, onOpenChange, location, factories, buildings, floors, departments, sections, productionLines, workstations, action }) {
  const t = useT("asset");

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={location ? t("edit_location") : t("new_location")} className="max-w-2xl">
      <LocationForm
        location={location}
        factories={factories}
        buildings={buildings}
        floors={floors}
        departments={departments}
        sections={sections}
        productionLines={productionLines}
        workstations={workstations}
        action={action}
        onDone={() => onOpenChange(false)}
        onCancel={() => onOpenChange(false)}
      />
    </Modal>
  );
}

export { LocationFormModal };
