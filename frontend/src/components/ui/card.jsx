import { cn } from "@/lib/utils";

/**
 * docs/UI-DESIGN-SYSTEM.md §1/§4: header (title, description, actions),
 * body, footer. rounded-sm (12px), p-5, shadow-xs resting / shadow-sm
 * hovered.
 */
function Card({ className, ...props }) {
  return (
    <div
      data-slot="card"
      className={cn(
        "rounded-sm border border-border bg-surface shadow-xs transition-shadow duration-150 ease-out",
        className,
      )}
      {...props}
    />
  );
}

function CardHeader({ className, ...props }) {
  return (
    <div
      data-slot="card-header"
      className={cn("flex items-start justify-between gap-4 p-5 pb-0", className)}
      {...props}
    />
  );
}

function CardTitleGroup({ className, ...props }) {
  return <div data-slot="card-title-group" className={cn("space-y-1", className)} {...props} />;
}

function CardTitle({ className, ...props }) {
  return (
    <h3 data-slot="card-title" className={cn("text-sm font-semibold text-foreground", className)} {...props} />
  );
}

function CardDescription({ className, ...props }) {
  return (
    <p
      data-slot="card-description"
      className={cn("text-xs text-foreground-muted", className)}
      {...props}
    />
  );
}

function CardActions({ className, ...props }) {
  return <div data-slot="card-actions" className={cn("flex items-center gap-2", className)} {...props} />;
}

function CardBody({ className, ...props }) {
  return <div data-slot="card-body" className={cn("p-5", className)} {...props} />;
}

function CardFooter({ className, ...props }) {
  return (
    <div
      data-slot="card-footer"
      className={cn("flex items-center gap-2 border-t border-border p-5", className)}
      {...props}
    />
  );
}

export { Card, CardHeader, CardTitleGroup, CardTitle, CardDescription, CardActions, CardBody, CardFooter };
