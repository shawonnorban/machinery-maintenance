import { AlertTriangle, CalendarDays, CalendarRange, CircleCheck, CircleX, Clock, Hourglass, ListChecks, RotateCcw, UserCheck } from "lucide-react";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";

const TILES = [
  ["total", ListChecks, "bg-success-subtle text-success"],
  ["pending", Hourglass, "bg-warning-subtle text-warning"],
  ["pending_on_me", UserCheck, "bg-success-subtle text-success"],
  ["approved", CircleCheck, "bg-brand-subtle text-brand"],
  ["rejected", CircleX, "bg-danger-subtle text-danger"],
  ["returned", RotateCcw, "bg-danger-subtle text-danger"],
  ["overdue", AlertTriangle, "bg-warning-subtle text-warning"],
  ["today", CalendarDays, "bg-brand-subtle text-brand"],
  ["this_month", CalendarRange, "bg-danger-subtle text-danger"],
  ["avg_turnaround_hours", Clock, "bg-success-subtle text-success"],
];

function Panel({ title, hint, children, empty, isEmpty }) {
  return (
    <section className="rounded-lg border border-border bg-surface p-5">
      <h3 className="text-base font-semibold text-foreground">{title}</h3>
      {hint ? <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p> : null}
      <div className="mt-4">{isEmpty ? <p className="text-sm text-foreground-muted">{empty}</p> : children}</div>
    </section>
  );
}

/** Under an hour reads in minutes: "0 h" says nothing about a quick decision. */
function duration(hours, labels) {
  if (hours === null || hours === undefined) return "—";
  return hours < 1 ? `${Math.round(hours * 60)} ${labels.minutes}` : `${hours} ${labels.hours}`;
}

function Bars({ items, label = (x) => x }) {
  const max = Math.max(1, ...items.map((i) => i.count));
  return (
    <ul className="flex flex-col gap-2">
      {items.map((item) => (
        <li key={item.label} className="text-sm">
          <div className="flex justify-between gap-2">
            <span className="truncate text-foreground">{label(item.label)}</span>
            <span className="font-semibold tabular-nums">{item.count}</span>
          </div>
          <div className="mt-1 h-1.5 rounded-full bg-surface-muted">
            <div className="h-1.5 rounded-full bg-brand" style={{ width: `${(item.count / max) * 100}%` }} />
          </div>
        </li>
      ))}
    </ul>
  );
}

/**
 * Approval Center → Request Dashboard (AnnotechERP 04/03), as Brickland lays
 * it out: ten counters, then requests by module, status, priority, buyer,
 * department, requester and approver, the reasons behind refusals and returns,
 * turnaround by type and the oldest overdue requests.
 *
 * @param {{ data: object, labels: { tiles: object, panels: object, hints: object, empty: string, statuses: object, priorities: object, entities: object, hours: string, count: string, avg: string, max: string, days: string, request_to: string, due: string, nothing_overdue: string, nothing_decided: string } }} props
 */
function RequestDashboard({ data, labels }) {
  const tile = (key) => (key === "avg_turnaround_hours" ? duration(data.tiles[key], labels) : data.tiles[key]);
  const panels = [
    ["by_module", (x) => labels.entities[x] ?? x],
    ["by_status", (x) => labels.statuses[x] ?? x],
    ["by_priority", (x) => labels.priorities[x] ?? x],
    ["by_buyer"],
    ["by_department"],
    ["by_requester"],
    ["by_approver"],
    ["rejected_reasons"],
    ["revision_reasons"],
  ];

  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        {TILES.map(([key, Icon, tone]) => (
          <div key={key} className="flex items-center gap-3 rounded-lg border border-border bg-surface p-4">
            <span className={`flex size-10 shrink-0 items-center justify-center rounded-lg ${tone}`}>
              <Icon className="size-5" />
            </span>
            <span className="min-w-0">
              <span className="block truncate text-[13px] text-foreground-muted">{labels.tiles[key]}</span>
              <span className="block text-xl font-semibold text-foreground tabular-nums">{tile(key)}</span>
            </span>
          </div>
        ))}
      </div>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {panels.map(([key, label]) => (
          <Panel key={key} title={labels.panels[key]} hint={labels.hints[key]} empty={labels.empty} isEmpty={data[key].length === 0}>
            <Bars items={data[key]} label={label} />
          </Panel>
        ))}
      </div>

      <div className="grid gap-4 lg:grid-cols-[3fr_2fr]">
        <Panel title={labels.panels.turnaround} hint={labels.hints.turnaround} empty={labels.nothing_decided} isEmpty={data.turnaround.length === 0}>
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-xs text-foreground-muted">
                <th className="py-1.5">{labels.type}</th>
                <th className="py-1.5 text-right">{labels.count}</th>
                <th className="py-1.5 text-right">{labels.avg}</th>
                <th className="py-1.5 text-right">{labels.max}</th>
              </tr>
            </thead>
            <tbody>
              {data.turnaround.map((row) => (
                <tr key={row.label} className="border-t border-border">
                  <td className="py-1.5">{labels.entities[row.label] ?? row.label}</td>
                  <td className="py-1.5 text-right tabular-nums">{row.count}</td>
                  <td className="py-1.5 text-right tabular-nums">{duration(row.avg_hours, labels)}</td>
                  <td className="py-1.5 text-right tabular-nums">{duration(row.max_hours, labels)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Panel>
        <Panel title={labels.panels.overdue} hint={labels.hints.overdue} empty={labels.nothing_overdue} isEmpty={data.overdue.length === 0}>
          <ul className="flex flex-col divide-y divide-border">
            {data.overdue.map((row) => (
              <li key={row.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                <span className="min-w-0">
                  <span className="block truncate font-medium text-foreground">
                    {row.request_no ?? "—"} · {row.title}
                  </span>
                  <span className="block text-xs text-foreground-muted">
                    {labels.request_to}: {row.request_to ?? "—"} · {labels.due}: <FormattedDateTime value={row.due_at} mode="date" />
                  </span>
                </span>
                <span className="shrink-0 rounded-md bg-danger-subtle px-2 py-0.5 text-xs font-semibold text-danger">{labels.days.replace(":count", String(row.days))}</span>
              </li>
            ))}
          </ul>
        </Panel>
      </div>
    </div>
  );
}

export { RequestDashboard };
