"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { format } from "date-fns";
import { Pencil } from "lucide-react";
import { Modal } from "@/components/ui/modal";
import { FormField } from "@/components/ui/form-field";
import { DateTimeField } from "@/components/ui/date-time-field";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";

/**
 * Mirrors `breakdown::breakdowns._chain.blade.php` — the seven-timestamp
 * chain (SRS 17), shown as a chain rather than two fields, because
 * response time and repair time answer different questions and a record
 * with only "down at" and "up at" cannot separate a slow maintenance team
 * from a slow reporting culture. Editable because a machine that stopped
 * at 21:50 and was reported at 06:10 is real, and forcing "now" onto every
 * stamp would make the whole chain fiction — the API's own `breakdown.
 * breakdown.repair` gate on submit is what actually enforces who may
 * correct one, not a client-side guess (the edit button is always shown
 * while the breakdown is open, same as everywhere else in this file).
 */
const CHAIN_FIELDS = [
  ["failure_at", "Machine stopped"],
  ["reported_at", "Reported"],
  ["acknowledged_at", "Acknowledged"],
  ["technician_arrival_at", "Technician arrived"],
  ["repair_started_at", "Repair started"],
  ["repair_completed_at", "Repair completed"],
  ["production_resumed_at", "Production resumed"],
];

function BreakdownTimelineTab({ timestamps, isTerminal, action }) {
  const [editingField, setEditingField] = useState(null);

  return (
    <div className="overflow-hidden rounded-sm border border-border">
      <table className="w-full text-sm">
        <tbody className="divide-y divide-border">
          {CHAIN_FIELDS.map(([field, label]) => (
            <tr key={field}>
              <td className="w-56 p-3 text-foreground-muted">{label}</td>
              <td className={timestamps[field] ? "p-3 font-medium text-foreground" : "p-3 text-foreground-muted"}>
                {timestamps[field] ? <FormattedDateTime value={timestamps[field]} /> : "Not recorded"}
              </td>
              <td className="w-12 p-3 text-right">
                {!isTerminal ? (
                  <button
                    type="button"
                    onClick={() => setEditingField(field)}
                    className="text-foreground-muted hover:text-foreground"
                    aria-label={`Correct ${label}`}
                    title="Correct a time"
                  >
                    <Pencil className="size-4" />
                  </button>
                ) : null}
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      <CorrectTimestampModal
        key={editingField ?? "none"}
        field={editingField}
        label={CHAIN_FIELDS.find(([f]) => f === editingField)?.[1]}
        value={editingField ? timestamps[editingField] : null}
        onOpenChange={(open) => !open && setEditingField(null)}
        action={action}
      />
    </div>
  );
}

/** `DateTimeField`'s value carries no timezone of its own — pre-filled and read back on the browser's own local clock, same as the report-breakdown form. */
function toLocalInputValue(iso) {
  if (!iso) return "";
  return format(new Date(iso), "yyyy-MM-dd'T'HH:mm");
}

function CorrectTimestampModal({ field, label, value, onOpenChange, action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Time corrected", type: "success" });
      queueMicrotask(() => onOpenChange(false));
      router.refresh();
    }
    // See breakdown-actions.jsx's AssignModal for why toastManager/router/
    // onOpenChange are left out — including them re-fires this effect (and
    // its own router.refresh()) on every render once state settles.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    startTransition(() => dispatch(formData));
  }

  return (
    <Modal open={field !== null} onOpenChange={onOpenChange} title={label ?? ""}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <input type="hidden" name="field" value={field ?? ""} />
        <FormField label="Correct a time" required error={state?.errors?.value?.[0]}>
          {(fieldProps) => (
            <DateTimeField {...fieldProps} name="value" defaultValue={toLocalInputValue(value)} />
          )}
        </FormField>
        {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit" loading={pending}>
            Save
          </Button>
        </div>
      </form>
    </Modal>
  );
}

export { BreakdownTimelineTab };
