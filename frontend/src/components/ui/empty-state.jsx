import { cn } from "@/lib/utils";

/** docs/UI-DESIGN-SYSTEM.md §4/§5: icon, message, optional action — never a blank rectangle. */
function EmptyState({ icon, title, description, action, className }) {
  return (
    <div className={cn("flex flex-col items-center justify-center gap-3 px-6 py-12 text-center", className)}>
      {icon ? (
        <span className="flex size-12 items-center justify-center rounded-full bg-surface-muted text-foreground-muted [&_svg]:size-6">
          {icon}
        </span>
      ) : null}
      <div className="flex flex-col gap-1">
        <p className="text-sm font-medium text-foreground">{title}</p>
        {description ? <p className="text-sm text-foreground-muted">{description}</p> : null}
      </div>
      {action ? <div className="mt-1">{action}</div> : null}
    </div>
  );
}

export { EmptyState };
