"use client";

import { useActionState, useEffect, useRef } from "react";
import { useFormStatus } from "react-dom";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { DateTimeField } from "@/components/ui/date-time-field";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { saveDraft, flush } from "@/lib/offline/queue";

function SubmitButton() {
  const { pending } = useFormStatus();
  return (
    <Button type="submit" loading={pending}>
      Save reading
    </Button>
  );
}

/**
 * Mirrors `metering::meters.show.blade.php`'s reading form exactly: value,
 * an optional reading_at (defaults to now, on the factory's clock — the
 * server, not this component, resolves what "now" means for that factory),
 * and a note.
 *
 * @param {{
 *   action: (prevState: any, formData: FormData) => Promise<any>,
 *   meterId: string,
 * }} props
 *   `action` is the recordReading Server Action pre-bound to a meter id
 *   (`recordReading.bind(null, meterId)`) by the page that renders this.
 *   `meterId` is passed separately because the offline fallback below talks
 *   to `/api/offline-relay` directly rather than through the Server Action,
 *   and needs the endpoint to name itself.
 */
function RecordReadingForm({ action, meterId }) {
  const toastManager = useToastManager();

  // Wrapped so `useActionState`'s action always settles into a state object
  // rather than rejecting — a reading taken while walking the floor, where
  // signal drops mid-submit, is exactly the case this exists for (mirrors
  // `WorkOrderActions`'s `ReasonModal` wrapper).
  async function wrappedAction(previousState, formData) {
    try {
      return await action(previousState, formData);
    } catch {
      const readingAt = formData.get("reading_at");

      await saveDraft({
        endpoint: `/meters/${meterId}/readings`,
        payload: {
          value: formData.get("value"),
          reading_at: readingAt ? new Date(readingAt).toISOString() : null,
          notes: formData.get("notes") || null,
        },
        label: `Meter reading — ${meterId}`,
      });
      flush();

      return { status: "queued" };
    }
  }

  const [state, formAction] = useActionState(wrappedAction, null);
  const formRef = useRef(null);
  const readingAtRef = useRef(null);

  useEffect(() => {
    if (state?.status === "success") {
      formRef.current?.reset();
      toastManager.add({ title: "Reading recorded", type: "success" });
    } else if (state?.status === "queued") {
      formRef.current?.reset();
      toastManager.add({
        title: "Reading saved on this device",
        description: "Sending now — check the sync icon if you're offline.",
        type: "success",
      });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  // Set client-side, after mount, rather than as a `defaultValue` computed
  // during render: "now" depends on the browser's own clock/timezone, and
  // computing it during a Client Component's *server*-rendered pass (still
  // SSR'd once before hydration) risks a value that doesn't match what the
  // client recomputes a moment later.
  useEffect(() => {
    readingAtRef.current?.setIfEmpty(nowForInput());
  }, []);

  return (
    <form ref={formRef} action={formAction} className="flex flex-col gap-4">
      <p className="font-semibold text-foreground">Record a reading</p>

      <div className="grid grid-cols-2 gap-3">
        <FormField label="Reading" required error={state?.errors?.value?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="value" type="number" step="0.0001" min="0" required autoFocus />}
        </FormField>

        <FormField label="Read at" error={state?.errors?.reading_at?.[0]}>
          {(fieldProps) => <DateTimeField {...fieldProps} ref={readingAtRef} name="reading_at" />}
        </FormField>
      </div>

      <FormField label="Note" error={state?.errors?.notes?.[0]}>
        {(fieldProps) => <Textarea {...fieldProps} name="notes" rows={2} maxLength={500} />}
      </FormField>

      {state?.status === "error" && !state.errors ? (
        <p className="text-sm text-danger">{state.message}</p>
      ) : null}

      <SubmitButton />
    </form>
  );
}

// The web form defaults this to "now" on the factory's own configured
// timezone (TenantTimezone::forInput()), not the browser's. This uses the
// browser's local time instead — a known, minor deviation, acceptable
// since the person filling this in is standing at the machine and shares
// the factory's clock in every case that matters; revisit if a remote
// data-entry role ever needs otherwise.
function nowForInput() {
  const now = new Date();
  now.setSeconds(0, 0);
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
  return now.toISOString().slice(0, 16);
}

export { RecordReadingForm };
