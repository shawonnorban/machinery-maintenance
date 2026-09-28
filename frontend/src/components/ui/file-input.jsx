import { cn } from "@/lib/utils";

/**
 * The bare `<input type="file">` renders as the browser's own unstyled
 * "Browse…" button + filename text, out of step with every other control in
 * the design system. Tailwind's `file:` pseudo-element variant styles just
 * the native button part — no JS drag/drop reimplementation needed to get
 * a bordered, rounded, on-brand picker out of a real file input.
 */
function FileInput({ className, ...props }) {
  return (
    <input
      type="file"
      data-slot="file-input"
      className={cn(
        "flex h-9 w-full min-w-0 items-center rounded-sm border border-border-strong bg-surface text-sm text-foreground-muted",
        "outline-none transition-colors duration-150 ease-out",
        "file:mr-3 file:h-full file:cursor-pointer file:rounded-l-sm file:border-0 file:border-r file:border-border-strong",
        "file:bg-brand-subtle file:px-3 file:text-sm file:font-medium file:text-brand-hover",
        "hover:file:bg-brand/20",
        "focus-visible:border-brand focus-visible:ring-2 focus-visible:ring-brand/50",
        "disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-surface-muted",
        className,
      )}
      {...props}
    />
  );
}

export { FileInput };
