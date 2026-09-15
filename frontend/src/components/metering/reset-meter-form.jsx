"use client";

import { useActionState, useEffect, useRef, useState } from "react";
import { useFormStatus } from "react-dom";
import { ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";

function SubmitButton() {
  const { pending } = useFormStatus();
  return (
    <Button type="submit" variant="danger" size="sm" loading={pending}>
      Record replacement
    </Button>
  );
}

/**
 * Mirrors the web's `<details>` disclosure for "the meter was replaced" —
 * the one legitimate way a cumulative reading goes down. Collapsed by
 * default on purpose: this is a rare, consequential action next to the
 * routine one above it, not a peer control.
 *
 * @param {{ action: (prevState: any, formData: FormData) => Promise<any> }} props
 *   `action` is the resetMeter Server Action pre-bound to a meter id
 *   (`resetMeter.bind(null, meterId)`) by the page that renders this.
 */
function ResetMeterForm({ action }) {
  const [open, setOpen] = useState(false);
  const [state, formAction] = useActionState(action, null);
  const formRef = useRef(null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      formRef.current?.reset();
      toastManager.add({ title: "Meter replacement recorded", type: "success" });
      queueMicrotask(() => setOpen(false));
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <div className="border-t border-border pt-4">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        className="flex items-center gap-1 text-xs text-foreground-muted hover:text-foreground"
      >
        <ChevronRight className={cn("size-3.5 transition-transform duration-150", open && "rotate-90")} />
        The meter was replaced
      </button>

      {open ? (
        <form ref={formRef} action={formAction} className="mt-3 flex flex-col gap-3">
          <FormField label="Reading on the new meter" required error={state?.errors?.new_value?.[0]}>
            {(fieldProps) => <Input {...fieldProps} name="new_value" type="number" step="0.0001" min="0" defaultValue="0" required />}
          </FormField>

          <FormField label="Why" required error={state?.errors?.reason?.[0]}>
            {(fieldProps) => <Input {...fieldProps} name="reason" type="text" maxLength={500} required />}
          </FormField>

          {state?.status === "error" && !state.errors ? (
            <p className="text-sm text-danger">{state.message}</p>
          ) : null}

          <div>
            <SubmitButton />
          </div>
        </form>
      ) : null}
    </div>
  );
}

export { ResetMeterForm };
