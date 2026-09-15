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
import { setCalendar, createShift, endShift, createHoliday, deleteHoliday } from "./actions";

/**
 * Mirrors `CalendarController::index` (SRS 7). Availability divides downtime
 * by scheduled operating time, and a maintenance date that falls on a rest
 * day is moved to the next working one — both read what this screen writes.
 */
export default async function CalendarPage({ searchParams }) {
  const params = await searchParams;
  const today = new Date().toISOString().slice(0, 10);

  const factories = await apiFetch("/factories?per_page=100");

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
        breadcrumb={[{ label: "Settings" }, { label: "Calendar and shifts" }]}
        title="Calendar and shifts"
        description="When this factory is running. Availability divides downtime by scheduled operating time, and a maintenance date that falls on a rest day is moved to the next working one — both read what is set here."
      />

      {factories.length > 1 ? (
        <div className="mb-4">
          <FactoryPicker factories={factories} value={factory?.id} />
        </div>
      ) : null}

      {!factory ? (
        <EmptyState
          icon={<CalendarClock />}
          title="No factory to configure."
          description="Add a factory first; a calendar belongs to one."
        />
      ) : (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-5">
          <div className="lg:col-span-2">
            <Card>
              <CardHeader>
                <CardTitle>Working week</CardTitle>
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
                <CardTitle>Shifts</CardTitle>
              </CardHeader>
              <CardBody className="flex flex-col gap-4">
                <ShiftsList shifts={shifts} endShift={endShift} />
                <ShiftForm factoryId={factory.id} today={today} action={createShift.bind(null, factory.id)} />
              </CardBody>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Holidays and exceptions</CardTitle>
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
