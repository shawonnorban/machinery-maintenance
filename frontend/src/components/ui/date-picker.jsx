"use client";

import { useState } from "react";
import { Popover } from "@base-ui/react/popover";
import { DayPicker } from "react-day-picker";
import { format, parse, isValid } from "date-fns";
import { CalendarIcon } from "lucide-react";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";

const VALUE_FORMAT = "yyyy-MM-dd";

/**
 * docs/UI-DESIGN-SYSTEM.md §4: "the only date control — a calendar popover,
 * never `<input type="date">`." Values cross the boundary as `YYYY-MM-DD`
 * strings, never `Date` objects — the `Date` conversion happens only inside
 * this component, never at the call site.
 *
 * Always full width — never pass a width class, size the wrapping element.
 *
 * `placeholder` defaults to a translated "Pick a date" — no call site in
 * the app currently overrides it, so this is the one leaf UI primitive
 * that reads `useT` itself rather than taking pre-translated text as a
 * prop, the same as every other component in this design system.
 *
 * @param {{ value: string | null, onChange: (value: string | null) => void, placeholder?: string, disabled?: boolean, className?: string }} props
 */
function DatePicker({ value, onChange, placeholder, disabled, className, ...rest }) {
  const t = useT("common");
  const [open, setOpen] = useState(false);
  const selected = value ? parse(value, VALUE_FORMAT, new Date()) : undefined;
  // Fixed dd/MM/yyyy rather than `date-fns`'s locale-dependent "PP" — the
  // one format everyone on the factory floor reads the same way, instead
  // of quietly following whatever the server or browser locale happens
  // to resolve to (found live: "PP" renders as "Sep 14, 2026", the exact
  // US month-first ordering that caused the confusion in the first
  // place, just spelled out instead of slashed).
  const displayValue = selected && isValid(selected) ? format(selected, "dd/MM/yyyy") : null;

  return (
    <Popover.Root open={open} onOpenChange={setOpen}>
      <Popover.Trigger
        disabled={disabled}
        {...rest}
        className={cn(
          "flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-sm border border-border-strong",
          "bg-surface px-3 text-sm outline-none transition-colors duration-150 ease-out",
          displayValue ? "text-foreground" : "text-foreground-subtle",
          "focus-visible:border-brand focus-visible:ring-2 focus-visible:ring-brand/50",
          "disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-surface-muted",
          className,
        )}
      >
        <span className="truncate">{displayValue ?? placeholder ?? t("pick_a_date")}</span>
        <CalendarIcon className="size-4 shrink-0 text-foreground-muted" />
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Positioner sideOffset={4} className="z-50 outline-none">
          <Popover.Popup className="rounded-sm border border-border bg-surface p-2 shadow-md">
            <DayPicker
              mode="single"
              selected={selected && isValid(selected) ? selected : undefined}
              onSelect={(date) => {
                onChange(date ? format(date, VALUE_FORMAT) : null);
                setOpen(false);
              }}
              autoFocus
            />
          </Popover.Popup>
        </Popover.Positioner>
      </Popover.Portal>
    </Popover.Root>
  );
}

export { DatePicker };
