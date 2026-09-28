import { cn } from "@/lib/utils";

/** docs/UI-DESIGN-SYSTEM.md §4: a loading placeholder matching the real content's shape. */
function Skeleton({ className, ...props }) {
  return (
    <div
      data-slot="skeleton"
      className={cn("animate-pulse rounded-sm bg-surface-muted", className)}
      {...props}
    />
  );
}

export { Skeleton };
