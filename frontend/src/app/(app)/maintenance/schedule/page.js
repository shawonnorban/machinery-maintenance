import { Clock, AlertTriangle, CalendarClock } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { StatCard } from "@/components/ui/stat-card";
import { SchedulesTable } from "@/components/maintenance/schedules-table";
import { getT } from "@/lib/i18n-server";
import { completeSchedule, skipSchedule, rescheduleSchedule } from "./actions";
import { applyPerPage } from "@/lib/per-page";

/** Mirrors `ScheduleController::index` — concrete due/overdue/completed occurrences of a maintenance plan. */
export default async function MaintenanceSchedulePage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  await applyPerPage(query, "/maintenance/schedule");
  if (status) query.set("status", status);

  const [schedules, counts, t] = await Promise.all([
    apiFetch(`/maintenance-schedules?${query.toString()}`, { includeMeta: true }),
    // Mirrors `ScheduleController::index()`'s own KPI tile row — dropped
    // when this list was first ported, restored via its own endpoint since
    // it isn't scoped to the current filter/page.
    apiFetch("/maintenance-schedules/counts"),
    getT("maintenance"),
  ]);

  return (
    <>
      <ScreenHeader
        url="/maintenance/schedule"
        title={t("schedule_page_title")}
        description={t("schedule_page_description")}
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard label={t("filter_due")} value={counts.due} icon={<Clock />} tone="warning" />
        <StatCard label={t("filter_overdue")} value={counts.overdue} icon={<AlertTriangle />} tone="danger" />
        <StatCard label={t("filter_planned")} value={counts.planned} icon={<CalendarClock />} tone="brand" />
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
