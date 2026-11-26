import { Activity, Archive, CalendarClock, Database, HardDrive, Layers, Mail, MessageSquare, Server } from "lucide-react";
import { cn } from "@/lib/utils";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";

const ICONS = { database: Database, cache: Layers, queue: Activity, storage: HardDrive, scheduler: CalendarClock, mail: Mail, sms: MessageSquare, backup: Archive, app: Server };

const TONES = {
  OK: "border-success/30 bg-success-subtle text-success",
  WARNING: "border-warning/30 bg-warning-subtle text-warning",
  DOWN: "border-danger/30 bg-danger-subtle text-danger",
};

const ISO = /^\d{4}-\d{2}-\d{2}T/;

/**
 * Settings → System Health Monitor (AnnotechERP 03/04): one card per moving
 * part, its state as a coloured pill and a short reading. A reading that is a
 * moment ("last ran at") is shown in the reader's own format.
 *
 * @param {{ cards: { key: string, status: "OK"|"WARNING"|"DOWN", value: string, detail?: string|null }[], labels: { names: object, statuses: object, overall: object } }} props
 */
function HealthCards({ cards, labels }) {
  const overall = cards.some((c) => c.status === "DOWN") ? "DOWN" : cards.some((c) => c.status === "WARNING") ? "WARNING" : "OK";

  return (
    <div className="flex flex-col gap-4">
      <div className={cn("rounded-lg border px-5 py-4 text-sm font-semibold", TONES[overall])}>{labels.overall[overall]}</div>
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {cards.map((card) => {
          const Icon = ICONS[card.key] ?? Server;
          return (
            <div key={card.key} className="flex flex-col gap-3 rounded-lg border border-border bg-surface p-5">
              <div className="flex items-center justify-between gap-2">
                <span className="flex items-center gap-2.5 text-sm font-semibold text-foreground">
                  <span className="flex size-9 items-center justify-center rounded-lg bg-brand-subtle text-brand">
                    <Icon className="size-4.5" />
                  </span>
                  {labels.names[card.key] ?? card.key}
                </span>
                <span className={cn("rounded-full border px-2.5 py-0.5 text-xs font-semibold", TONES[card.status])}>{labels.statuses[card.status]}</span>
              </div>
              <div className="text-lg font-semibold text-foreground tabular-nums">{ISO.test(card.value) ? <FormattedDateTime value={card.value} /> : card.value}</div>
              {card.detail ? <p className="text-xs text-foreground-muted">{card.detail}</p> : null}
            </div>
          );
        })}
      </div>
    </div>
  );
}

export { HealthCards };
