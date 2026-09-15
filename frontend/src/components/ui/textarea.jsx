import { cn } from "@/lib/utils";

/** docs/UI-DESIGN-SYSTEM.md §4: same border/radius/focus ring as Input. */
function Textarea({ className, rows = 4, ...props }) {
  return (
    <textarea
      rows={rows}
      data-slot="textarea"
      className={cn(
        "flex w-full min-w-0 rounded-sm border border-border-strong bg-surface px-3 py-2 text-sm text-foreground",
        "placeholder:text-foreground-subtle outline-none transition-colors duration-150 ease-out resize-y",
        "focus-visible:border-brand focus-visible:ring-2 focus-visible:ring-brand/50",
        "disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-surface-muted",
        "aria-invalid:border-danger aria-invalid:focus-visible:ring-danger/50",
        className,
      )}
      {...props}
    />
  );
}

export { Textarea };
