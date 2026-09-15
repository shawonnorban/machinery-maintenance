"use client";

import { useActionState, useEffect, useState } from "react";
import { useFormStatus } from "react-dom";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { useToastManager } from "@/components/ui/toast";
import { formatStatus } from "@/components/ui/status-badge";
import { ASSET_TRANSITIONS } from "@/lib/asset-transitions";

function SubmitButton() {
  const { pending } = useFormStatus();
  return (
    <Button type="submit" loading={pending}>
      Change status
    </Button>
  );
}

/**
 * The status list offered here is `ASSET_TRANSITIONS[asset.status]` — a
 * courtesy that hides a move the API would refuse anyway, not the
 * enforcement itself (UI-DESIGN-SYSTEM.md §4 rule 4). The API re-checks
 * the identical table and is what a client bypassing this dropdown would
 * still hit.
 *
 * @param {{ assetId: string, currentStatus: string, version: number, action: (prevState: any, formData: FormData) => Promise<any> }} props
 */
function ChangeStatusModal({ assetId, currentStatus, version, action }) {
  const [open, setOpen] = useState(false);
  const [state, formAction] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();
  const options = ASSET_TRANSITIONS[currentStatus] ?? [];

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Status updated", type: "success" });
      queueMicrotask(() => setOpen(false));
      router.refresh();
    }
    // toastManager/router are deliberately excluded from the dependency
    // array: neither is a stable reference across renders, so including
    // them re-fires this effect (and its own router.refresh()) every
    // render once state first becomes "success" — a real infinite loop,
    // confirmed live elsewhere in the app as duplicate toasts stacking up
    // and a "Maximum update depth exceeded" crash.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  if (options.length === 0) {
    return null;
  }

  return (
    <>
      <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
        Change status
      </Button>

      <Modal open={open} onOpenChange={setOpen} title="Change status" description={`Currently ${formatStatus(currentStatus)}.`}>
        <form action={formAction} className="flex flex-col gap-4">
          <input type="hidden" name="version" value={version} />

          <FormField label="New status" required error={state?.errors?.status?.[0]}>
            {(fieldProps) => (
              <Select
                {...fieldProps}
                name="status"
                placeholder="Select a status"
                options={options.map((status) => ({ value: status, label: formatStatus(status) }))}
              />
            )}
          </FormField>

          <FormField label="Reason" error={state?.errors?.reason?.[0]}>
            {(fieldProps) => <Input {...fieldProps} name="reason" type="text" maxLength={255} />}
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

export { ChangeStatusModal };
