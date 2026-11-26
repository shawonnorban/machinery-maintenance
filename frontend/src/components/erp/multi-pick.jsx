"use client";

import { useMemo, useState } from "react";
import { Popover } from "@base-ui/react/popover";
import { ChevronDown, Search, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Checkbox } from "@/components/ui/checkbox";

/**
 * A select that takes many values, shown as tags — Brickland's "Floor List"
 * and "Buyer" pickers. Same height, border and focus ring as Select.
 *
 * @param {{ options: { value: string, label: string }[], value: string[], onChange: (v: string[]) => void, placeholder?: string, disabled?: boolean, ariaLabel?: string }} props
 */
function MultiPick({ options, value, onChange, placeholder = "Select…", disabled = false, ariaLabel }) {
  const [query, setQuery] = useState("");
  const chosen = useMemo(() => new Set(value), [value]);
  const labels = useMemo(() => Object.fromEntries(options.map((o) => [o.value, o.label])), [options]);
  const visible = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return needle ? options.filter((o) => o.label.toLowerCase().includes(needle)) : options;
  }, [options, query]);
  const toggle = (v, on) => onChange(on ? [...value, v] : value.filter((x) => x !== v));

  return (
    <Popover.Root onOpenChange={(open) => !open && setQuery("")}>
      <Popover.Trigger
        disabled={disabled}
        aria-label={ariaLabel}
        className={cn(
          "flex min-h-9 w-full min-w-0 items-center justify-between gap-2 rounded-sm border border-border-strong bg-surface px-2 py-1 text-left text-sm",
          "outline-none transition-colors duration-150 focus-visible:border-brand focus-visible:ring-2 focus-visible:ring-brand/50",
          "disabled:cursor-not-allowed disabled:bg-surface-muted disabled:opacity-50",
        )}
      >
        <span className="flex min-w-0 flex-wrap gap-1">
          {value.length === 0 ? <span className="px-1 text-foreground-subtle">{placeholder}</span> : null}
          {value.map((v) => (
            <span key={v} className="inline-flex items-center gap-1 rounded-sm bg-brand-subtle px-1.5 py-0.5 text-xs font-medium text-brand">
              {labels[v] ?? v}
              {!disabled ? (
                <X
                  className="size-3 cursor-pointer"
                  onClick={(e) => {
                    e.stopPropagation();
                    toggle(v, false);
                  }}
                />
              ) : null}
            </span>
          ))}
        </span>
        <ChevronDown className="size-4 shrink-0 text-foreground-muted" />
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Positioner align="start" sideOffset={4} className="z-50 outline-none">
          <Popover.Popup className="flex max-h-72 min-w-[var(--anchor-width)] flex-col overflow-hidden rounded-sm border border-border bg-surface shadow-md">
            {options.length > 8 ? (
              <div className="flex items-center gap-2 border-b border-border px-2.5 py-1.5">
                <Search className="size-4 shrink-0 text-foreground-muted" />
                <input autoFocus value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search…" className="h-6 w-full bg-transparent text-sm outline-none placeholder:text-foreground-subtle" />
              </div>
            ) : null}
            <div className="overflow-y-auto p-1">
              {visible.length === 0 ? <p className="px-3 py-2 text-sm text-foreground-subtle">No matches</p> : null}
              {visible.map((o) => (
                <label key={o.value} className="flex cursor-pointer items-center gap-2 rounded-sm px-3 py-2 text-sm hover:bg-surface-muted">
                  <Checkbox checked={chosen.has(o.value)} onCheckedChange={(c) => toggle(o.value, c === true)} />
                  {o.label}
                </label>
              ))}
            </div>
          </Popover.Popup>
        </Popover.Positioner>
      </Popover.Portal>
    </Popover.Root>
  );
}

export { MultiPick };
