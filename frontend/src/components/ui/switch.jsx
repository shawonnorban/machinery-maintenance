"use client";

import { Switch as BaseSwitch } from "@base-ui/react/switch";
import { cn } from "@/lib/utils";

function Switch({ className, ...props }) {
  return (
    <BaseSwitch.Root
      data-slot="switch"
      className={cn(
        "flex h-6 w-10 shrink-0 items-center rounded-full border border-border-strong bg-surface-muted p-0.5",
        "outline-none transition-colors duration-150 ease-out",
        "data-[checked]:bg-brand data-[checked]:border-brand",
        "focus-visible:ring-2 focus-visible:ring-brand/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background",
        "disabled:cursor-not-allowed disabled:opacity-50",
        className,
      )}
      {...props}
    >
      <BaseSwitch.Thumb
        className={cn(
          "size-4.5 rounded-full bg-white shadow-xs transition-transform duration-150 ease-out",
          "data-[checked]:translate-x-4",
        )}
      />
    </BaseSwitch.Root>
  );
}

export { Switch };
