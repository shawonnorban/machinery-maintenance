"use client";

import { Tabs as BaseTabs } from "@base-ui/react/tabs";
import { cn } from "@/lib/utils";

/** docs/UI-DESIGN-SYSTEM.md §4: section switching within a page. */
function Tabs({ className, ...props }) {
  return <BaseTabs.Root data-slot="tabs" className={cn("flex flex-col gap-4", className)} {...props} />;
}

function TabsList({ className, ...props }) {
  return (
    <div className="overflow-x-auto border-b border-border">
      <BaseTabs.List className={cn("relative flex w-max min-w-full gap-1", className)} {...props} />
    </div>
  );
}

function TabsTab({ className, ...props }) {
  return (
    <BaseTabs.Tab
      className={cn(
        "relative shrink-0 whitespace-nowrap px-3 py-2 text-sm font-medium text-foreground-muted outline-none transition-colors duration-150 ease-out",
        "hover:text-foreground data-[selected]:text-brand",
        "focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-brand/50",
        className,
      )}
      {...props}
    />
  );
}

function TabsIndicator({ className, ...props }) {
  return (
    <BaseTabs.Indicator
      className={cn(
        "absolute bottom-0 left-0 h-0.5 w-[var(--active-tab-width)] translate-x-[var(--active-tab-left)]",
        "bg-brand transition-all duration-200 ease-out",
        className,
      )}
      {...props}
    />
  );
}

function TabsPanel({ className, ...props }) {
  return <BaseTabs.Panel className={cn("outline-none", className)} {...props} />;
}

export { Tabs, TabsList, TabsTab, TabsIndicator, TabsPanel };
