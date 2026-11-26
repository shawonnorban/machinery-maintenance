"use client";

import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";
import { clockFormat } from "@/lib/clock";

/*
 * What the Production Plan screens share (AnnotechERP 12/02): dates and
 * quantities as Brickland writes them (05-Nov-2026, 7,920), the plan status
 * pill, a tile, a section card, the current forecast tiles, and the Gantt the
 * plan result, a locked plan and Gantt / Timeline draw.
 */

const card = "rounded-xl border border-border bg-surface";
const MONTHS = [
  "Jan",
  "Feb",
  "Mar",
  "Apr",
  "May",
  "Jun",
  "Jul",
  "Aug",
  "Sep",
  "Oct",
  "Nov",
  "Dec",
];
const WEEKDAYS = ["Su", "Mo", "Tu", "We", "Th", "Fr", "Sa"];

/** "2026-11-05" → "05-Nov-2026". */
const dmy = (iso) => {
  if (!iso) return "—";
  const [y, m, d] = String(iso).slice(0, 10).split("-");
  return `${d}-${MONTHS[Number(m) - 1]}-${y}`;
};
/** "2026-11-05" → "05-Nov". */
const dm = (iso) => (iso ? dmy(iso).slice(0, 6) : "—");
const num = (n) =>
  n === null || n === undefined || n === ""
    ? "—"
    : Number(n).toLocaleString("en-US", { maximumFractionDigits: 2 });
const stamp = (iso) =>
  iso
    ? clockFormat({
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        timeZone: "Asia/Dhaka",
      }).format(new Date(iso))
    : "—";
const addDays = (iso, n) => {
  const d = new Date(`${iso}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + n);
  return d.toISOString().slice(0, 10);
};
const daysBetween = (a, b) =>
  Math.round(
    (new Date(`${b}T00:00:00Z`) - new Date(`${a}T00:00:00Z`)) / 86400000,
  );
const weekday = (iso) => WEEKDAYS[new Date(`${iso}T00:00:00Z`).getUTCDay()];
const today = () =>
  new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Dhaka" }).format(
    new Date(),
  );

const firstError = (res) =>
  Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message;
/** A line's [hourly, daily] capacity, as the API works it out. */
const capacity = (mp, hours, eff, smv) => {
  if (!smv) return [0, 0];
  const hourly = Math.floor(
    (Number(mp) * 60 * (Number(eff) / 100)) / smv + 1e-9,
  );
  return [hourly, Math.floor(hourly * Number(hours) + 1e-9)];
};
/** The address follows what is on screen, without a reload. */
const keepUrl = (query) =>
  window.history.replaceState(
    null,
    "",
    `${window.location.pathname}?${new URLSearchParams(query)}`,
  );

const TONE = {
  PENDING: "bg-warning-subtle text-warning",
  DRAFT: "bg-info-subtle text-info",
  LOCKED: "bg-success-subtle text-success",
  REVISED: "bg-surface-muted text-foreground-muted",
  AWAITING: "bg-warning-subtle text-warning",
  APPROVED: "bg-success-subtle text-success",
  REJECTED: "bg-danger-subtle text-danger",
  NOT_STARTED: "bg-surface-muted text-foreground-muted",
  ON_TRACK: "bg-success-subtle text-success",
  AHEAD: "bg-success-subtle text-success",
  DELAYED: "bg-warning-subtle text-warning",
  AT_RISK: "bg-danger-subtle text-danger",
  COMPLETED: "bg-success-subtle text-success",
  PLANNED: "bg-info-subtle text-info",
  NOT_SAVED: "bg-surface-muted text-foreground-muted",
  CONFIRMED: "bg-success-subtle text-success",
};

function Pill({ tone, children, className }) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap",
        TONE[tone] ?? "bg-surface-muted text-foreground-muted",
        className,
      )}
    >
      {children}
    </span>
  );
}

function Tile({ label, value, sub, tone }) {
  return (
    <div className="rounded-lg border border-border bg-surface-muted px-4 py-3">
      <p className="text-xs font-semibold text-foreground-muted">{label}</p>
      <p
        className={cn(
          "mt-1 text-lg font-semibold tabular-nums text-foreground",
          tone === "danger" && "text-danger",
          tone === "success" && "text-success",
          tone === "warning" && "text-warning",
        )}
      >
        {value}
      </p>
      {sub ? <p className="text-xs text-foreground-muted">{sub}</p> : null}
    </div>
  );
}

/** A numbered section card ("3 Order Information (Auto Fetch)"). */
function Section({ step, title, tag, hint, action, children, className }) {
  return (
    <section className={cn(card, "min-w-0", className)}>
      <div className="flex flex-wrap items-start justify-between gap-3 px-5 pt-5">
        <div>
          <h2 className="flex items-center gap-2 text-base font-semibold text-foreground">
            {step ? (
              <span className="flex size-6 items-center justify-center rounded-full bg-brand text-xs text-brand-foreground">
                {step}
              </span>
            ) : null}
            {title}
            {tag ? (
              <span className="text-[13px] font-normal text-foreground-muted">
                {tag}
              </span>
            ) : null}
          </h2>
          {hint ? (
            <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p>
          ) : null}
        </div>
        {action}
      </div>
      <div className="p-5">{children}</div>
    </section>
  );
}

/**
 * Where a locked plan stands (the current forecast): order quantity, made,
 * remaining, planned so far, original and forecast completion, shipment,
 * variance, days and capacity left, extra capacity needed and status — on
 * Master Plan with the note under it (`note`), on Daily Plan without.
 *
 * @param {{ f: object, note?: boolean }} props
 */
function ForecastTiles({ f, note }) {
  const t = useT("planning");
  const p = (k, x) => t(`plan.${k}`, x);
  const pcs = (n) => `${num(n)} ${p("mp.pcs")}`;
  const variance =
    f.variance_days > 0
      ? p("fc.late", { n: f.variance_days })
      : f.variance_days < 0
        ? p("fc.early", { n: -f.variance_days })
        : p("fc.on_time");

  return (
    <>
      <div className="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <Tile label={p("fc.order_qty")} value={pcs(f.order_qty)} />
        <Tile label={p("fc.produced")} value={pcs(f.produced)} />
        <Tile label={p("fc.remaining")} value={pcs(f.remaining)} />
        <Tile
          label={p("fc.planned")}
          value={pcs(f.planned_till_date)}
          sub={
            f.planned_till_date
              ? p("fc.vs_plan", { n: num(f.vs_plan) })
              : undefined
          }
        />
        <Tile label={p("fc.original")} value={dmy(f.original_completion)} />
        <Tile
          label={p("fc.forecast")}
          value={dmy(f.current_forecast)}
          tone={f.status === "AT_RISK" ? "danger" : undefined}
        />
        <Tile label={p("fc.ship")} value={dmy(f.ship_date)} />
        <Tile
          label={p("fc.variance")}
          value={variance}
          tone={f.variance_days > 0 ? "danger" : "success"}
        />
        <Tile label={p("fc.rem_days")} value={f.remaining_days} />
        <Tile
          label={p("fc.cap_deadline")}
          value={pcs(f.capacity_to_deadline)}
          sub={p("fc.sewing_by", { date: dmy(f.last_sewing) })}
        />
        <Tile
          label={p("fc.extra")}
          value={f.extra_capacity ? pcs(f.extra_capacity) : p("fc.none")}
          tone={f.extra_capacity ? "danger" : undefined}
        />
        <div className="rounded-lg border border-border bg-surface-muted px-4 py-3">
          <p className="text-xs font-semibold text-foreground-muted">
            {p("fc.status")}
          </p>
          <Pill tone={f.status} className="mt-1.5">
            {p(`fc.statuses.${f.status}`)}
          </Pill>
          <p className="mt-1 text-xs text-foreground-muted">{f.percent}%</p>
        </div>
      </div>
      {note ? (
        <p
          className={cn(
            "mt-3 text-sm",
            f.extra_capacity ? "text-danger" : "text-foreground-muted",
          )}
        >
          {f.extra_capacity
            ? p("fc.risk_note", {
                qty: num(f.extra_capacity),
                date: dmy(f.last_sewing),
              })
            : p("fc.ok_note")}
        </p>
      ) : null}
    </>
  );
}

const BAR = {
  ACCESSORIES_BOOKING: "bg-[var(--series-1)]",
  ACCESSORIES_INHOUSE: "bg-[var(--series-2)]",
  FABRIC_BOOKING: "bg-[var(--series-1)]/70",
  FABRIC_INHOUSE: "bg-warning",
  CUTTING: "bg-pink-500",
  SEWING: "bg-[var(--series-3)]",
  FINISHING: "bg-violet-400",
  PACKING: "bg-rose-400",
  SHIPMENT: "bg-danger",
};

const GRID = (span) => ({
  display: "grid",
  gridTemplateColumns: `11rem repeat(${span}, minmax(1.6rem, 1fr))`,
});

/** The days a Gantt runs across: `from`–`to`, or the activities' own span. */
function ganttDays(activities, from, to) {
  const start =
    from ??
    activities.reduce(
      (m, a) => (a.start_date < m ? a.start_date : m),
      activities[0].start_date,
    );
  const end =
    to ??
    activities.reduce(
      (m, a) => (a.end_date > m ? a.end_date : m),
      activities[0].end_date,
    );
  const span = daysBetween(start, end) + 1;
  return {
    start,
    end,
    span,
    days: Array.from({ length: span }, (_, i) => addDays(start, i)),
  };
}

/** The day heading of a Gantt (Fridays shaded, today marked). */
function GanttHead({ days, label }) {
  const now = today();
  return (
    <div style={GRID(days.length)}>
      <div className="border-b border-border px-2 py-1.5 text-xs font-semibold uppercase text-foreground-muted">
        {label}
      </div>
      {days.map((d) => (
        <div
          key={d}
          className={cn(
            "border-b border-border py-1 text-center text-[10px] leading-tight text-foreground-muted",
            weekday(d) === "Fr" && "bg-surface-muted",
            d === now && "bg-brand-subtle font-semibold text-brand",
          )}
        >
          {Number(d.slice(8))}
          <br />
          {weekday(d)}
        </div>
      ))}
    </div>
  );
}

/**
 * One row per activity, its bar clipped to the days shown: ◂ where it began
 * earlier, ▸ where it runs on, "ended …" / "starts …" where it falls outside;
 * today as a dashed line; the dates, length and basis on hover.
 */
function GanttBars({ activities, start, end, span }) {
  const t = useT("planning");
  const p = (k, x) => t(`plan.${k}`, x);
  const now = today();
  const todayAt =
    now >= start && now <= end
      ? `${((daysBetween(start, now) + 0.5) / span) * 100}%`
      : null;
  return (
    <div style={GRID(span)}>
      {activities.map((a) => {
        const s = a.start_date < start ? start : a.start_date;
        const e = a.end_date > end ? end : a.end_date;
        const visible = a.end_date >= start && a.start_date <= end;
        const name = p(`activities.${a.activity}`);
        const title = p("gantt.bar", {
          activity: name,
          from: dmy(a.start_date),
          to: dmy(a.end_date),
          days: p(a.duration === 1 ? "gantt.day" : "gantt.days", {
            n: a.duration,
          }),
          based: a.based_on ? p(`based.${a.based_on}`) : "—",
        });
        return (
          <div key={a.activity} className="contents">
            <div className="border-b border-border px-2 py-1.5 text-[13px] text-foreground">
              {name}
            </div>
            <div
              className="relative border-b border-border"
              style={{ gridColumn: `2 / span ${span}` }}
            >
              {todayAt ? (
                <span
                  className="absolute inset-y-0 border-l border-dashed border-foreground-muted/60"
                  style={{ left: todayAt }}
                  aria-hidden
                />
              ) : null}
              {visible ? (
                <div
                  className={cn(
                    "absolute top-1.5 flex h-4 items-center justify-between rounded px-0.5 text-[10px] text-white",
                    BAR[a.activity] ?? "bg-brand",
                  )}
                  style={{
                    left: `${(daysBetween(start, s) / span) * 100}%`,
                    width: `${((daysBetween(s, e) + 1) / span) * 100}%`,
                  }}
                  title={title}
                >
                  <span>{a.start_date < start ? "◂" : ""}</span>
                  <span>{a.end_date > end ? "▸" : ""}</span>
                </div>
              ) : (
                <span
                  className={cn(
                    "absolute top-1 text-[11px] text-foreground-muted",
                    a.end_date < start ? "left-2" : "right-2",
                  )}
                  title={title}
                >
                  {a.end_date < start
                    ? `◂ ${p("gantt.ended", { date: dmy(a.end_date) })}`
                    : `${p("gantt.starts", { date: dmy(a.start_date) })} ▸`}
                </span>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}

/**
 * Activities as bars across days — the whole plan, or one month
 * (`from`–`to`), with today marked and a note where a bar runs past the edge.
 *
 * @param {{ activities: object[], from?: string, to?: string }} props
 */
function Gantt({ activities, from, to }) {
  const t = useT("planning");
  if (!activities?.length) return null;
  const { start, end, span, days } = ganttDays(activities, from, to);

  return (
    <div className="overflow-x-auto">
      <div className="min-w-[720px]">
        <GanttHead days={days} label={t("plan.mp.activity")} />
        <GanttBars
          activities={activities}
          start={start}
          end={end}
          span={span}
        />
      </div>
    </div>
  );
}

export {
  card,
  dmy,
  dm,
  num,
  stamp,
  addDays,
  daysBetween,
  weekday,
  today,
  Pill,
  Tile,
  Section,
  Gantt,
  GanttHead,
  GanttBars,
  ganttDays,
  ForecastTiles,
  MONTHS,
  firstError,
  capacity,
  keepUrl,
};
