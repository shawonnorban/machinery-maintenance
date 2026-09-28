import { cn } from "@/lib/utils";

/** A coloured accent + icon reads faster than plain uppercase text alone, and separates the three dashboards from each other at a glance. */
function DashboardSectionHeader({ icon, title, tone = "brand" }) {
  const toneClass = {
    brand: "bg-brand-subtle text-brand-hover",
    success: "bg-success-subtle text-success",
    warning: "bg-warning-subtle text-warning",
    info: "bg-info-subtle text-info",
  }[tone];

  return (
    <div className="flex items-center gap-2.5">
      <span className={cn("flex size-7 items-center justify-center rounded-sm [&_svg]:size-4", toneClass)}>{icon}</span>
      <h2 className="text-sm font-semibold tracking-wide text-foreground uppercase">{title}</h2>
    </div>
  );
}

export { DashboardSectionHeader };
