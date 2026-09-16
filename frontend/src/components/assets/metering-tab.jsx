import Link from "next/link";
import { Gauge } from "lucide-react";
import { Card, CardBody } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { RecordReadingForm } from "@/components/metering/record-reading-form";
import { formatQuantity } from "@/lib/format";

/**
 * The meters attached to this machine, each with its own record-reading
 * form right here — this is what makes a scanned QR code's "Log meter
 * reading" action (`ScanApiController`'s `log_meter`, landing on
 * `?tab=metering`) actually go somewhere, instead of the asset detail
 * page it used to point at having no metering UI at all. Full reading
 * history stays on the meter's own page (`/metering/[meterId]`) rather
 * than being re-fetched and duplicated here — one meter rarely has more
 * than a handful of active meters, so a link out is cheap and this tab
 * stays about the one thing a technician standing at the machine actually
 * came to do: log a number.
 */
function MeteringTab({ meters, actions }) {
  if (meters.length === 0) {
    return (
      <EmptyState
        icon={<Gauge />}
        title="No meters on this machine"
        description="Attach one from the Metering screen to start tracking readings here."
      />
    );
  }

  return (
    <div className="flex flex-col gap-4">
      {meters.map((meter) => (
        <Card key={meter.id}>
          <CardBody className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="flex flex-col gap-1">
              <Link href={`/metering/${meter.id}`} className="font-medium text-brand hover:underline">
                {meter.type?.name}
              </Link>
              <span className="tabular text-2xl font-semibold text-foreground">
                {formatQuantity(meter.current_value, meter.type?.unit)}
              </span>
              <span className="text-xs text-foreground-muted">
                {meter.last_reading_at ? (
                  <>
                    Last read <FormattedDateTime value={meter.last_reading_at} />
                  </>
                ) : (
                  "Never read"
                )}
              </span>
            </div>

            <div className="w-full sm:max-w-xs">
              <RecordReadingForm action={actions.recordReading.bind(null, meter.id)} meterId={meter.id} />
            </div>
          </CardBody>
        </Card>
      ))}
    </div>
  );
}

export { MeteringTab };
