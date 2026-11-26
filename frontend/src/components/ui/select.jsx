"use client";

import { useMemo, useRef, useState } from "react";
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
 * With no `placeholder`, the label of an option whose value is "" stands in
 * for it (a filter's "All …"), else "Select…".
 *
 * `size="sm"`: the dense grid look (a 28px box, 11px text) for a select inside
 * a sheet's table cell — Pre-Budget's accessory lines and suppliers.
 *
 * @param {{ options: { value: string, label: string }[], placeholder?: string, className?: string, size?: "md" | "sm" }} props
 */
function Select({ options, placeholder, className, size = "md", onOpenChange, "aria-label": ariaLabel, ...props }) {
  const sm = size === "sm";
  const searchBox = useRef(null);
  const [query, setQuery] = useState("");
  // A filter whose "everything" choice is the empty value ("All statuses")
  // shows that label while nothing is picked, not a bare "Select…".
  const shown = placeholder ?? options.find((option) => option.value === "")?.label ?? "Select…";
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
        // The search box takes the typing as the list opens: the list moves focus to its selected item first,
        // so the box is focused a moment later (autoFocus alone lost to it — typing jumped through the list).
        else if (searchable) setTimeout(() => searchBox.current?.focus(), 60);
        onOpenChange?.(open, eventDetails);
      }}
    >
      <BaseSelect.Trigger
        data-slot="select-trigger"
        // The accessible name belongs on the button a person reaches, not on the (unrendered) root.
        aria-label={ariaLabel}
        className={cn(
          sm
            ? "flex h-7 w-full min-w-0 items-center justify-between gap-1 rounded border border-border-strong bg-white px-1.5 text-[11px] text-foreground outline-none"
            : "flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-sm border border-border-strong bg-surface px-3 text-sm text-foreground outline-none transition-colors duration-150 ease-out",
          "focus-visible:border-brand focus-visible:ring-2 focus-visible:ring-brand/50",
          "disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-surface-muted",
          "data-[placeholder]:text-foreground-subtle",
          className,
        )}
      >
        {/* One line, cut with an ellipsis: the trigger is a fixed height, so a long label (a grid cell's sample) would spill out of it. */}
        <BaseSelect.Value placeholder={shown} className="min-w-0 truncate text-left" />
        <BaseSelect.Icon>
          <ChevronDown className={cn(sm ? "size-3.5" : "size-4", "shrink-0 text-foreground-muted")} />
        </BaseSelect.Icon>
      </BaseSelect.Trigger>
      <BaseSelect.Portal>
        <BaseSelect.Positioner className="z-50 outline-none" sideOffset={4} alignItemWithTrigger={false}>
          <BaseSelect.Popup
            // A letter typed while an item has focus goes to the search box, not the list's jump-to-match.
            onKeyDownCapture={(event) => {
              if (!searchable || event.target === searchBox.current || event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey) return;
              event.preventDefault();
              event.stopPropagation();
              setQuery((q) => q + event.key);
              searchBox.current?.focus();
            }}
            className={cn(
              "flex max-h-72 min-w-[var(--anchor-width)] flex-col overflow-hidden rounded-sm border border-border",
              "bg-surface shadow-md",
            )}
          >
            {searchable ? (
              <div className="flex items-center gap-2 border-b border-border px-2.5 py-1.5">
                <Search className="size-4 shrink-0 text-foreground-muted" />
                <input
                  ref={searchBox}
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
                      "flex cursor-pointer items-center justify-between gap-2 rounded-sm text-foreground",
                      sm ? "px-2 py-1 text-[12px]" : "px-3 py-2 text-sm",
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
