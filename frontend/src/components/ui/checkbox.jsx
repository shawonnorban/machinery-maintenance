"use client";

import { Checkbox as BaseCheckbox } from "@base-ui/react/checkbox";
import { Check } from "lucide-react";
import { cn } from "@/lib/utils";

/** docs/UI-DESIGN-SYSTEM.md §4: one height, one radius, one focus ring. */
function Checkbox({ className, ...props }) {
  return (
    <BaseCheckbox.Root
      data-slot="checkbox"
      className={cn(
        "peer flex size-5 shrink-0 items-center justify-center rounded-sm border border-border-strong bg-surface",
        "outline-none transition-colors duration-150 ease-out",
        "data-[checked]:bg-brand data-[checked]:border-brand data-[checked]:text-brand-foreground",
        "focus-visible:ring-2 focus-visible:ring-brand/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background",
        "disabled:cursor-not-allowed disabled:opacity-50",
        className,
      )}
      {...props}
    >
      <BaseCheckbox.Indicator className="flex items-center justify-center">
        <Check className="size-3.5" strokeWidth={3} />
      </BaseCheckbox.Indicator>
    </BaseCheckbox.Root>
  );
}

export { Checkbox };
