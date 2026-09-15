"use client";

import { useMemo, useState } from "react";
import { Select as BaseSelect } from "@base-ui/react/select";
import { Check, ChevronDown, Search } from "lucide-react";
import { cn } from "@/lib/utils";

// Past this many options, hunting by eye stops working (found live: a
// 29-entry factory location list with none of the "Line 3"/"Dye M 1"
// labels sorted any way a person would guess) — a search box earns its
// keep. Below it, one adds a step for no benefit.
const SEARCH_THRESHOLD = 8;

/**
 * docs/UI-DESIGN-SYSTEM.md §4: same height/radius/focus ring as Input.
 * Always full width — size the wrapping element, never pass a width class.
 *
 * `alignItemWithTrigger={false}`: Base UI's default popup positioning
 * stretches the popup to visually align the selected (or, with nothing
 * selected yet, the first) item under the trigger — for a long list this
 * computes a popup as tall as the viewport and its inline height wins
 * over this component's own `max-h-72`, not just on first paint (found
 * live, the Location field: the list rendered edge-to-edge with no
 * scroll boundary). Turning it off falls back to a normal anchored
 * dropdown below the trigger, which respects max-height and collision
 * detection like every other popup in this design system.
 *
 * @param {{ options: { value: string, label: string }[], placeholder?: string, className?: string }} props
 */
function Select({ options, placeholder = "Select…", className, onOpenChange, ...props }) {
  const [query, setQuery] = useState("");
  const searchable = options.length > SEARCH_THRESHOLD;

  const visibleOptions = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (!searchable || needle === "") return options;
    return options.filter((option) => option.label.toLowerCase().includes(needle));
  }, [options, query, searchable]);

  return (
    <BaseSelect.Root
      items={options}
      {...props}
      onOpenChange={(open, eventDetails) => {
        if (!open) setQuery("");
        onOpenChange?.(open, eventDetails);
      }}
    >
      <BaseSelect.Trigger
        data-slot="select-trigger"
        className={cn(
          "flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-sm border border-border-strong",
          "bg-surface px-3 text-sm text-foreground outline-none transition-colors duration-150 ease-out",
          "focus-visible:border-brand focus-visible:ring-2 focus-visible:ring-brand/50",
          "disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-surface-muted",
          "data-[placeholder]:text-foreground-subtle",
          className,
        )}
      >
        <BaseSelect.Value placeholder={placeholder} />
        <BaseSelect.Icon>
          <ChevronDown className="size-4 text-foreground-muted" />
        </BaseSelect.Icon>
      </BaseSelect.Trigger>
      <BaseSelect.Portal>
        <BaseSelect.Positioner className="z-50 outline-none" sideOffset={4} alignItemWithTrigger={false}>
          <BaseSelect.Popup
            className={cn(
              "flex max-h-72 min-w-[var(--anchor-width)] flex-col overflow-hidden rounded-sm border border-border",
              "bg-surface shadow-md",
            )}
          >
            {searchable ? (
              <div className="flex items-center gap-2 border-b border-border px-2.5 py-1.5">
                <Search className="size-4 shrink-0 text-foreground-muted" />
                <input
                  autoFocus
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                  // Only printable-character keys are swallowed here — Base
                  // UI's own listbox also listens for these at the popup
                  // level to jump-to-match by typing, which would otherwise
                  // fight this input for every keystroke. Arrow keys, Enter
                  // and Escape are left alone so keyboard selection and
                  // closing the popup still work while this input has focus.
                  onKeyDown={(event) => {
                    if (event.key.length === 1 || event.key === "Backspace" || event.key === "Delete") {
                      event.stopPropagation();
                    }
                  }}
                  placeholder="Search…"
                  className="h-6 w-full bg-transparent text-sm text-foreground outline-none placeholder:text-foreground-subtle"
                />
              </div>
            ) : null}
            <div className="overflow-y-auto p-1">
              {visibleOptions.length === 0 ? (
                <p className="px-3 py-2 text-sm text-foreground-subtle">No matches</p>
              ) : (
                visibleOptions.map((option) => (
                  <BaseSelect.Item
                    key={option.value}
                    value={option.value}
                    className={cn(
                      "flex cursor-pointer items-center justify-between gap-2 rounded-sm px-3 py-2 text-sm text-foreground",
                      "outline-none data-[highlighted]:bg-surface-muted",
                    )}
                  >
                    <BaseSelect.ItemText>{option.label}</BaseSelect.ItemText>
                    <BaseSelect.ItemIndicator>
                      <Check className="size-4 text-brand" />
                    </BaseSelect.ItemIndicator>
                  </BaseSelect.Item>
                ))
              )}
            </div>
          </BaseSelect.Popup>
        </BaseSelect.Positioner>
      </BaseSelect.Portal>
    </BaseSelect.Root>
  );
}

export { Select };
