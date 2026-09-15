"use client";

import { Modal } from "@/components/ui/modal";
import { FactoryForm } from "@/components/factories/factory-form";

/** `key={factory?.id ?? "create"}` at the call site remounts this fresh per target, the same reason `RowFormModal` needs it — `FactoryForm`'s own state is seeded once from `factory` at mount. */
function FactoryFormModal({ open, onOpenChange, factory, action }) {
  return (
    <Modal open={open} onOpenChange={onOpenChange} title={factory ? `Edit ${factory.name}` : "New factory"}>
      <FactoryForm factory={factory} action={action} onDone={() => onOpenChange(false)} onCancel={() => onOpenChange(false)} />
    </Modal>
  );
}

export { FactoryFormModal };
