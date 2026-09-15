import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody, CardHeader, CardTitle } from "@/components/ui/card";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { RecordReadingForm } from "@/components/metering/record-reading-form";
import { ResetMeterForm } from "@/components/metering/reset-meter-form";
import { ReadingsTable } from "@/components/metering/readings-table";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { recordReading, resetMeter } from "./actions";
import { formatQuantity } from "@/lib/format";

/**
 * Mirrors `metering::meters.show.blade.php`: current value, the record-
 * reading form, the replace-meter disclosure (gated on permission the same
 * way the API itself gates the underlying action — a 403 on submit, not a
 * client-side guess, is what actually enforces this), and up to 100 recent
 * readings.
 */
export default async function MeterDetailPage({ params }) {
  const { meterId } = await params;

  const [meter, readings] = await Promise.all([
    apiFetch(`/meters/${meterId}`),
    apiFetch(`/meters/${meterId}/readings?per_page=100`, { includeMeta: false }),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Meters", href: "/metering" }, { label: meter.asset?.asset_code }]}
        title={meter.type?.name}
        description={`${meter.asset?.asset_code} — ${meter.asset?.name}`}
        actions={
          <Link href="/metering" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeft /> Back
          </Link>
        }
      />

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-12">
        <div className="lg:col-span-5">
          <Card>
            <CardBody className="flex flex-col gap-1">
              <span className="text-xs text-foreground-muted">Current reading</span>
              <span className="tabular text-3xl font-semibold text-foreground">
                {formatQuantity(meter.current_value, meter.type?.unit)}
              </span>
              <span className="text-xs text-foreground-muted">
                {meter.last_reading_at ? <>Last read <FormattedDateTime value={meter.last_reading_at} /></> : "Never read"}
              </span>
            </CardBody>

            <CardBody className="flex flex-col gap-4 border-t border-border">
              <RecordReadingForm action={recordReading.bind(null, meterId)} />
              <ResetMeterForm action={resetMeter.bind(null, meterId)} />
            </CardBody>
          </Card>
        </div>

        <div className="lg:col-span-7">
          <Card>
            <CardHeader>
              <CardTitle>Reading history</CardTitle>
            </CardHeader>
            <CardBody>
              <ReadingsTable readings={readings} />
            </CardBody>
          </Card>
        </div>
      </div>
    </>
  );
}
