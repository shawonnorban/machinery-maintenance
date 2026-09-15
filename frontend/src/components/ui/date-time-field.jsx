"use client";

import { forwardRef, useEffect, useImperativeHandle, useState } from "react";
import { DatePicker } from "@/components/ui/date-picker";
import { cn } from "@/lib/utils";

/**
 * The Date and Time halves of what used to be one `<input type="datetime-
 * local">` control (found live: on several mobile browsers its date half
 * either doesn't render at all or gives no visible way to open it, and it
 * always follows the device locale's own date order — mm/dd on an en-US
 * phone — with no way to pin it to dd/mm). The date half is this app's own
 * `DatePicker` (already dd/MM/yyyy, already a real touch target); the time
 * half stays a native `<input type="time">`, which every mobile browser
 * already renders as a proper wheel/clock picker on its own.
 *
 * Keeps the exact value shape a native `datetime-local` input produced —
 * `"YYYY-MM-DDTHH:mm"`, no timezone, the caller's own local clock — so
 * every existing call site keeps working unchanged: controlled
 * (`value`/`onChange`), or uncontrolled (`name` + `defaultValue`, read back
 * through `FormData`/a native form submit via a hidden input carrying the
 * combined value under `name`).
 *
 * @param {{
 *   name?: string,
 *   value?: string,
 *   defaultValue?: string,
 *   onChange?: (value: string) => void,
 *   disabled?: boolean,
 *   id?: string,
 *   className?: string,
 * }} props
 */
const DateTimeField = forwardRef(function DateTimeField(
  { name, value, defaultValue, onChange, disabled, id, className, ...rest },
  ref,
) {
  const isControlled = value !== undefined;
  const [internal, setInternal] = useState(() => splitValue(defaultValue ?? value ?? ""));
  const current = isControlled ? splitValue(value) : internal;

  useEffect(() => {
    if (isControlled) setInternal(splitValue(value));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value]);

  function commit(next) {
    if (!isControlled) setInternal(next);
    onChange?.(combineValue(next));
  }

  useImperativeHandle(ref, () => ({
    // Used only by the one caller (metering's reading form) that fills
    // "now" in after mount, rather than as a `defaultValue` computed
    // during render — the browser's own clock/timezone can't be read
    // deterministically on the server. Named for what it does rather than
    // exposing a raw setter, since the "only if still blank" check is the
    // part every caller actually wants and would otherwise re-implement.
    setIfEmpty(nextValue) {
      setInternal((prev) => (prev.date ? prev : splitValue(nextValue)));
    },
  }));

  return (
    <div className={cn("grid grid-cols-[1fr_auto] gap-2", className)}>
      <DatePicker
        id={id}
        value={current.date || null}
        onChange={(date) => commit({ date: date ?? "", time: current.time })}
        disabled={disabled}
        {...rest}
      />
      <input
        type="time"
        value={current.time}
        onChange={(event) => commit({ date: current.date, time: event.target.value })}
        disabled={disabled}
        aria-label="Time"
        className={cn(
          "h-9 w-[7.5rem] min-w-0 rounded-sm border border-border-strong bg-surface px-2 text-sm text-foreground",
          "outline-none transition-colors duration-150 ease-out",
          "focus-visible:border-brand focus-visible:ring-2 focus-visible:ring-brand/50",
          "disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-surface-muted",
        )}
      />
      {name ? <input type="hidden" name={name} value={combineValue(current)} readOnly /> : null}
    </div>
  );
});

function combineValue({ date, time }) {
  return date ? `${date}T${time || "00:00"}` : "";
}

function splitValue(value) {
  if (!value) return { date: "", time: "" };
  const [date, time] = value.split("T");
  return { date: date ?? "", time: time ?? "" };
}

export { DateTimeField };
