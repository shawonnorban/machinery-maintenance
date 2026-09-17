"use client";

import { startTransition, useActionState, useEffect, useRef, useState } from "react";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { DatePicker } from "@/components/ui/date-picker";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const WEEKDAY_KEYS = [
  { value: 1, key: "monday_short" },
  { value: 2, key: "tuesday_short" },
  { value: 3, key: "wednesday_short" },
  { value: 4, key: "thursday_short" },
  { value: 5, key: "friday_short" },
  { value: 6, key: "saturday_short" },
  { value: 7, key: "sunday_short" },
];

const DEFAULT_DAYS = [1, 2, 3, 4, 6, 7];

/** Mirrors `calendar::calendar.index.blade.php`'s "add a shift" form. */
function ShiftForm({ factoryId, today, action }) {
  const t = useT("calendar");
  const [state, dispatch, pending] = useActionState(action, null);
  const [name, setName] = useState("");
  const [code, setCode] = useState("");
  const [startTime, setStartTime] = useState("08:00");
  const [endTime, setEndTime] = useState("20:00");
  const [days, setDays] = useState(DEFAULT_DAYS);
  const [isOvertime, setIsOvertime] = useState(false);
  const [effectiveFrom, setEffectiveFrom] = useState(today);
  const formRef = useRef(null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("shift_saved"), type: "success" });
      queueMicrotask(() => {
        setName("");
        setCode("");
        setStartTime("08:00");
        setEndTime("20:00");
        setDays(DEFAULT_DAYS);
        setIsOvertime(false);
        setEffectiveFrom(today);
      });
    }
    // toastManager is not a stable reference across renders — the
    // eslint-disable above was already needed, but toastManager staying in
    // this array meant the underlying infinite-loop risk (it re-fires this
    // effect every render once state first becomes "success") was never
    // actually fixed, only the lint warning about it was silenced.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function toggleDay(day) {
    setDays((previous) => (previous.includes(day) ? previous.filter((d) => d !== day) : [...previous, day]));
  }

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData();
    formData.set("name", name);
    formData.set("code", code);
    formData.set("start_time", startTime);
    formData.set("end_time", endTime);
    for (const day of days) formData.append("days_of_week", String(day));
    formData.set("is_overtime", isOvertime ? "1" : "0");
    formData.set("effective_from", effectiveFrom ?? today);
    startTransition(() => dispatch(formData));
  }

  return (
    <form ref={formRef} onSubmit={handleSubmit} className="flex flex-col gap-3 border-t border-border pt-4">
      <p className="text-sm font-semibold text-foreground">{t("add_shift")}</p>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <FormField label={t("shift_name")} required error={state?.errors?.name?.[0]}>
          {(fieldProps) => <Input {...fieldProps} value={name} onChange={(e) => setName(e.target.value)} maxLength={255} required />}
        </FormField>

        <FormField label={t("code")} required error={state?.errors?.code?.[0]}>
          {(fieldProps) => (
            <Input {...fieldProps} className="uppercase" value={code} onChange={(e) => setCode(e.target.value)} maxLength={32} required />
          )}
        </FormField>

        <FormField label={t("starts")} required error={state?.errors?.start_time?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="time" value={startTime} onChange={(e) => setStartTime(e.target.value)} required />}
        </FormField>

        <FormField label={t("ends")} required error={state?.errors?.end_time?.[0]}>
          {(fieldProps) => <Input {...fieldProps} type="time" value={endTime} onChange={(e) => setEndTime(e.target.value)} required />}
        </FormField>
      </div>

      <div>
        <span className="mb-1.5 block text-sm font-medium text-foreground">{t("days")}</span>
        <div className="flex flex-wrap gap-3">
          {WEEKDAY_KEYS.map((day) => (
            <label key={day.value} className="flex items-center gap-1.5 text-sm text-foreground">
              <Checkbox checked={days.includes(day.value)} onCheckedChange={() => toggleDay(day.value)} />
              {t(day.key)}
            </label>
          ))}
        </div>
        {state?.errors?.days_of_week?.[0] ? <p className="mt-1 text-xs text-danger">{state.errors.days_of_week[0]}</p> : null}
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
        <FormField label={t("in_force_from")} required error={state?.errors?.effective_from?.[0]}>
          {() => <DatePicker value={effectiveFrom} onChange={setEffectiveFrom} />}
        </FormField>

        <label className="flex items-center gap-1.5 pb-2.5 text-sm text-foreground">
          <Checkbox checked={isOvertime} onCheckedChange={setIsOvertime} />
          {t("is_overtime")}
        </label>

        <Button type="submit" loading={pending}>
          {t("add_shift")}
        </Button>
      </div>

      {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}
    </form>
  );
}

export { ShiftForm };
