"use client";

import { useActionState, useEffect, useState } from "react";
import { useFormStatus } from "react-dom";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useToastManager } from "@/components/ui/toast";

function SubmitButton() {
  const { pending } = useFormStatus();
  return (
    <Button type="submit" loading={pending}>
      Request transfer
    </Button>
  );
}

/**
 * Mirrors `AssetTransferController::create`/`::store`. The API decides
 * whether this is a same-factory move (auto-received on submit) or a
 * cross-factory request (needs the destination factory to approve/
 * receive) based on the chosen location's own factory — nothing here
 * needs to know which case it is.
 *
 * @param {{ currentFactoryId: string, version: number, locations: object[], action: (prevState: any, formData: FormData) => Promise<any> }} props
 */
function TransferRequestModal({ version, locations, action }) {
  const [open, setOpen] = useState(false);
  const [state, formAction] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Transfer requested", type: "success" });
      queueMicrotask(() => setOpen(false));
      router.refresh();
    }
    // See ChangeStatusModal's own comment on why toastManager/router are
    // left out of the dependency array — including them causes an
    // infinite loop once state first becomes "success".
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <>
      <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
        Request transfer
      </Button>

      <Modal open={open} onOpenChange={setOpen} title="Request transfer" description="Move this machine to a different location or factory.">
        <form action={formAction} className="flex flex-col gap-4">
          <input type="hidden" name="version" value={version} />

          <FormField label="Destination location" required error={state?.errors?.to_location_id?.[0]}>
            {(fieldProps) => (
              <Select
                {...fieldProps}
                name="to_location_id"
                placeholder="Select a location"
                options={locations.map((location) => ({ value: location.id, label: location.name }))}
              />
            )}
          </FormField>

          <FormField label="Reason" required error={state?.errors?.reason?.[0]}>
            {(fieldProps) => <Input {...fieldProps} name="reason" type="text" maxLength={255} required />}
          </FormField>

          <FormField label="Notes" error={state?.errors?.notes?.[0]}>
            {(fieldProps) => <Textarea {...fieldProps} name="notes" rows={2} maxLength={2000} />}
          </FormField>

          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <SubmitButton />
          </div>
        </form>
      </Modal>
    </>
  );
}

export { TransferRequestModal };
