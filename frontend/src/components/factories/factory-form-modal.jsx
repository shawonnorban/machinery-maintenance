"use client";

import { Modal } from "@/components/ui/modal";
import { FactoryForm } from "@/components/factories/factory-form";
import { useT } from "@/lib/i18n";

/** `key={factory?.id ?? "create"}` at the call site remounts this fresh per target, the same reason `RowFormModal` needs it — `FactoryForm`'s own state is seeded once from `factory` at mount. */
function FactoryFormModal({ open, onOpenChange, factory, action }) {
  const t = useT("settings");

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={factory ? t("edit_factory_named", { name: factory.name }) : t("new_factory")}>
      <FactoryForm factory={factory} action={action} onDone={() => onOpenChange(false)} onCancel={() => onOpenChange(false)} />
    </Modal>
  );
}

export { FactoryFormModal };
