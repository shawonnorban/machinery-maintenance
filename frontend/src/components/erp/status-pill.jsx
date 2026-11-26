import { CheckCircle2, CircleSlash } from "lucide-react";
import { cn } from "@/lib/utils";

/** Brickland's status cell: a green "Active" pill with a tick, or a grey one. */
function StatusPill({ active, label }) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium [&_svg]:size-3.5",
        active ? "bg-success-subtle text-success" : "bg-surface-muted text-foreground-muted",
      )}
    >
      {active ? <CheckCircle2 /> : <CircleSlash />}
      {label}
    </span>
  );
}

export { StatusPill };
