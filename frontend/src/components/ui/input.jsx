import { cn } from "@/lib/utils";

/**
 * docs/UI-DESIGN-SYSTEM.md §4/§6: one height (36px — shrunk from the design
 * doc's original 40px per direct request), one radius, one focus ring,
 * shared with Select/DatePicker/FileInput. Always full width — never pass
 * a width class here, size the wrapping element instead.
 */
function Input({ className, type = "text", ...props }) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "flex h-9 w-full min-w-0 rounded-sm border border-border-strong bg-surface px-3 text-sm text-foreground",
        "placeholder:text-foreground-subtle outline-none transition-colors duration-150 ease-out",
        "focus-visible:border-brand focus-visible:ring-2 focus-visible:ring-brand/50",
        "disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-surface-muted",
        "aria-invalid:border-danger aria-invalid:focus-visible:ring-danger/50",
        className,
      )}
      {...props}
    />
  );
}

export { Input };
