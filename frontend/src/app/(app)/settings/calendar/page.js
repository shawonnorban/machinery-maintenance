import { CalendarClock } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { FactoryPicker } from "@/components/calendar/factory-picker";
import { CalendarForm } from "@/components/calendar/calendar-form";
import { ShiftForm } from "@/components/calendar/shift-form";
import { ShiftsList } from "@/components/calendar/shifts-list";
import { HolidayForm } from "@/components/calendar/holiday-form";
import { HolidaysList } from "@/components/calendar/holidays-list";
import { getT } from "@/lib/i18n-server";
import { setCalendar, createShift, endShift, createHoliday, deleteHoliday } from "./actions";

/**
 * Mirrors `CalendarController::index` (SRS 7). Availability divides downtime
 * by scheduled operating time, and a maintenance date that falls on a rest
 * day is moved to the next working one — both read what this screen writes.
 */
export default async function CalendarPage({ searchParams }) {
  const params = await searchParams;
  const today = new Date().toISOString().slice(0, 10);

  const [factories, t, tn] = await Promise.all([
    apiFetch("/factories?per_page=100"),
    getT("calendar"),
    getT("nav"),
  ]);

  const factory = factories.find((f) => f.id === params.factory_id) ?? factories[0] ?? null;

  const [calendarData, shifts, holidays] = factory
    ? await Promise.all([
        apiFetch(`/factories/${factory.id}/calendar`),
        apiFetch(`/factories/${factory.id}/shifts`),
        apiFetch(`/factories/${factory.id}/holidays`),
      ])
    : [null, [], []];

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("calendar") }]}
        title={t("calendar")}
        description={t("intro")}
      />

      {factories.length > 1 ? (
        <div className="mb-4">
          <FactoryPicker factories={factories} value={factory?.id} />
        </div>
      ) : null}

      {!factory ? (
        <EmptyState
          icon={<CalendarClock />}
          title={t("no_factory")}
          description={t("no_factory_hint")}
        />
      ) : (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-5">
          <div className="lg:col-span-2">
            <Card>
              <CardHeader>
                <CardTitle>{t("working_week")}</CardTitle>
              </CardHeader>
              <CardBody>
                <CalendarForm
                  factoryId={factory.id}
                  calendar={calendarData.calendar}
                  today={today}
                  action={setCalendar.bind(null, factory.id)}
                />
              </CardBody>
            </Card>
          </div>

          <div className="flex flex-col gap-4 lg:col-span-3">
            <Card>
              <CardHeader>
                <CardTitle>{t("shifts")}</CardTitle>
              </CardHeader>
              <CardBody className="flex flex-col gap-4">
                <ShiftsList shifts={shifts} endShift={endShift} />
                <ShiftForm factoryId={factory.id} today={today} action={createShift.bind(null, factory.id)} />
              </CardBody>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>{t("holidays")}</CardTitle>
              </CardHeader>
              <CardBody className="flex flex-col gap-4">
                <HolidaysList holidays={holidays} deleteHoliday={deleteHoliday} />
                <HolidayForm factoryId={factory.id} action={createHoliday.bind(null, factory.id)} />
              </CardBody>
            </Card>
          </div>
        </div>
      )}
    </>
  );
}
