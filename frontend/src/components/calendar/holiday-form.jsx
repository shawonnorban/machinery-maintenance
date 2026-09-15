"use client";

import { startTransition, useActionState, useEffect, useState } from "react";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { DatePicker } from "@/components/ui/date-picker";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";

/** Mirrors `calendar::calendar.index.blade.php`'s "add a holiday" form. */
function HolidayForm({ factoryId, action }) {
  const [state, dispatch, pending] = useActionState(action, null);
  const [date, setDate] = useState(null);
  const [name, setName] = useState("");
  const [isWorkingDay, setIsWorkingDay] = useState(false);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Added to the calendar", type: "success" });
      queueMicrotask(() => {
        setDate(null);
        setName("");
        setIsWorkingDay(false);
      });
    }
    // toastManager is not a stable reference across renders — including it
    // re-fires this effect every render once state first becomes
    // "success", stacking duplicate toasts.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("date", date ?? "");
    formData.set("name", name);
    formData.set("is_working_day", isWorkingDay ? "1" : "0");
    startTransition(() => dispatch(formData));
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-end">
      <FormField label="Date" required error={state?.errors?.date?.[0]} className="sm:w-40">
        {() => <DatePicker value={date} onChange={setDate} />}
      </FormField>

      <FormField label="Occasion" required error={state?.errors?.name?.[0]} className="sm:flex-1">
        {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required />}
      </FormField>

      <label className="flex items-center gap-1.5 pb-2.5 text-sm whitespace-nowrap text-foreground">
        <Checkbox checked={isWorkingDay} onCheckedChange={setIsWorkingDay} />
        Working day
      </label>

      <Button type="submit" loading={pending}>
        Add
      </Button>

      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
    </form>
  );
}

export { HolidayForm };
