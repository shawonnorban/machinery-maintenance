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
import { useT } from "@/lib/i18n";

function SubmitButton() {
  const t = useT("asset");
  const { pending } = useFormStatus();
  return (
    <Button type="submit" loading={pending}>
      {t("request_transfer")}
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
  const t = useT("asset");
  const tc = useT("common");
  const [open, setOpen] = useState(false);
  const [state, formAction] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("transfer_requested_toast"), type: "success" });
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
        {t("request_transfer")}
      </Button>

      <Modal open={open} onOpenChange={setOpen} title={t("request_transfer")} description={t("request_transfer_hint")}>
        <form action={formAction} className="flex flex-col gap-4">
          <input type="hidden" name="version" value={version} />

          <FormField label={t("destination")} required error={state?.errors?.to_location_id?.[0]}>
            {(fieldProps) => (
              <Select
                {...fieldProps}
                name="to_location_id"
                placeholder={t("select_a_location")}
                options={locations.map((location) => ({ value: location.id, label: location.name }))}
              />
            )}
          </FormField>

          <FormField label={t("reason")} required error={state?.errors?.reason?.[0]}>
            {(fieldProps) => <Input {...fieldProps} name="reason" type="text" maxLength={255} required />}
          </FormField>

          <FormField label={t("notes")} error={state?.errors?.notes?.[0]}>
            {(fieldProps) => <Textarea {...fieldProps} name="notes" rows={2} maxLength={2000} />}
          </FormField>

          {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              {tc("cancel")}
            </Button>
            <SubmitButton />
          </div>
        </form>
      </Modal>
    </>
  );
}

export { TransferRequestModal };
