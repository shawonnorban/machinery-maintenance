import { Clock, AlertTriangle, CalendarClock } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { StatCard } from "@/components/ui/stat-card";
import { SchedulesTable } from "@/components/maintenance/schedules-table";
import { completeSchedule, skipSchedule, rescheduleSchedule } from "./actions";

/** Mirrors `ScheduleController::index` — concrete due/overdue/completed occurrences of a maintenance plan. */
export default async function MaintenanceSchedulePage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (status) query.set("status", status);

  const [schedules, counts] = await Promise.all([
    apiFetch(`/maintenance-schedules?${query.toString()}`, { includeMeta: true }),
    // Mirrors `ScheduleController::index()`'s own KPI tile row — dropped
    // when this list was first ported, restored via its own endpoint since
    // it isn't scoped to the current filter/page.
    apiFetch("/maintenance-schedules/counts"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Maintenance" }, { label: "Schedule" }]}
        title="Maintenance schedule"
        description="What's due, overdue, skipped, or already completed."
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard label="Due" value={counts.due} icon={<Clock />} tone="warning" />
        <StatCard label="Overdue" value={counts.overdue} icon={<AlertTriangle />} tone="danger" />
        <StatCard label="Planned" value={counts.planned} icon={<CalendarClock />} tone="brand" />
      </div>

      <SchedulesTable
        schedules={schedules.data}
        meta={schedules.meta}
        page={page}
        status={status}
        actions={{ completeSchedule, skipSchedule, rescheduleSchedule }}
      />
    </>
  );
}
