"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";

const MODE_OPTIONS = [
  { value: "SHIFT_BASED", label: "Shift based" },
  { value: "CONTINUOUS", label: "Continuous (never stops)" },
];

const WEEKDAYS = [
  { value: 1, label: "Mon" },
  { value: 2, label: "Tue" },
  { value: 3, label: "Wed" },
  { value: 4, label: "Thu" },
  { value: 5, label: "Fri" },
  { value: 6, label: "Sat" },
  { value: 7, label: "Sun" },
];

/**
 * Mirrors `calendar::calendar.index.blade.php`'s working-week form. A
 * calendar is superseded from a date rather than edited — see the Server
 * Action for why — so this always reads as "set a new one", never "edit the
 * current one", even while a current one is shown above it.
 *
 * @param {{ factoryId: string, calendar: object | null, today: string, action: Function }} props
 *   `action` is the setCalendar Server Action pre-bound to a factory id.
 */
function CalendarForm({ factoryId, calendar, today, action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const [mode, setMode] = useState(calendar?.operating_mode ?? "SHIFT_BASED");
  const [offDays, setOffDays] = useState(calendar?.weekly_off_days ?? []);
  const [effectiveFrom, setEffectiveFrom] = useState(today);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "The working week is in force", type: "success" });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function toggleDay(day) {
    setOffDays((previous) => (previous.includes(day) ? previous.filter((d) => d !== day) : [...previous, day]));
  }

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("operating_mode", mode);
    for (const day of offDays) formData.append("weekly_off_days", String(day));
    formData.set("effective_from", effectiveFrom ?? today);
    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      {calendar ? (
        <div className="text-sm">
          <p className="font-medium text-foreground">
            {calendar.operating_mode === "CONTINUOUS" ? "Continuous (never stops)" : "Shift based"}
          </p>
          <p className="text-xs text-foreground-muted">In force since {calendar.effective_from}</p>
        </div>
      ) : (
        <p className="text-sm text-warning">
          No working week set yet, so availability is being measured against a default one.
        </p>
      )}

      <FormField label="Operating mode" required>
        {(fieldProps) => <Select {...fieldProps} options={MODE_OPTIONS} value={mode} onValueChange={setMode} />}
      </FormField>

      {mode === "SHIFT_BASED" ? (
        <div>
          <span className="mb-1.5 block text-sm font-medium text-foreground">Weekly off</span>
          <div className="flex flex-wrap gap-3">
            {WEEKDAYS.map((day) => (
              <label key={day.value} className="flex items-center gap-1.5 text-sm text-foreground">
                <Checkbox checked={offDays.includes(day.value)} onCheckedChange={() => toggleDay(day.value)} />
                {day.label}
              </label>
            ))}
          </div>
          {state?.errors?.weekly_off_days?.[0] ? (
            <p className="mt-1 text-xs text-danger">{state.errors.weekly_off_days[0]}</p>
          ) : null}
        </div>
      ) : (
        <p className="text-xs text-foreground-muted">Runs every day of the week.</p>
      )}

      <FormField label="In force from" required error={state?.errors?.effective_from?.[0]}>
        {() => <DatePicker value={effectiveFrom} onChange={setEffectiveFrom} />}
      </FormField>

      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

      <div>
        <Button type="submit" loading={pending}>
          Put in force
        </Button>
      </div>
    </form>
  );
}

export { CalendarForm };
