"use client";

import { useState } from "react";
import { Plus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { EntryForm } from "@/components/erp/entry-form";

/**
 * A button that opens an entry form in a dialog ("New Institute" beside a
 * picker): Brickland's small add-on dialogs, which create a row without
 * leaving the screen.
 *
 * @param {{ label: string, title: string, description?: string, fields: object[], action: Function, submitLabel: string, variant?: string }} props
 */
function DialogFormButton({ label, title, description, fields, action, submitLabel, variant = "outline" }) {
  const [open, setOpen] = useState(false);
  return (
    <>
      <Button variant={variant} onClick={() => setOpen(true)}>
        <Plus /> {label}
      </Button>
      {open ? (
        <Modal open onOpenChange={setOpen} title={title} description={description} className="max-w-2xl">
          <EntryForm dialog fields={fields} action={action} submitLabel={submitLabel} onDone={() => setOpen(false)} />
        </Modal>
      ) : null}
    </>
  );
}

export { DialogFormButton };
