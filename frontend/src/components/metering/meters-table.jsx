"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatQuantity } from "@/lib/format";

const STATUS_OPTIONS = [
  { value: "ACTIVE", label: "Active" },
  { value: "INACTIVE", label: "Inactive" },
];

const STALE_AFTER_DAYS = 14;

function isStale(meter) {
  if (!meter.last_reading_at) {
    return true;
  }
  const days = (Date.now() - new Date(meter.last_reading_at).getTime()) / 86_400_000;
  return days > STALE_AFTER_DAYS;
}

/**
 * Behaviour mirrors `metering::meters.index.blade.php`: a stale row (never
 * read, or untouched for a fortnight) is visually flagged — that's the one
 * making a usage-based plan quietly wrong. Status filter and pagination
 * navigate via the URL (page.js re-fetches server-side on each change)
 * rather than calling the API from here directly.
 */
function MetersTable({ meters, meta, page, status, assetId }) {
  const router = useRouter();

  function navigate(next) {
    const params = new URLSearchParams({ page: String(next.page ?? page), status: next.status ?? status });
    if (assetId) params.set("asset_id", assetId);
    router.push(`/metering?${params.toString()}`);
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="max-w-[200px]">
        <Select options={STATUS_OPTIONS} value={status} onValueChange={(value) => navigate({ status: value, page: 1 })} />
      </div>

      <DataTable
        columns={[
          {
            key: "asset",
            header: "Asset",
            render: (meter) => (
              <div>
                <Link href={`/metering/${meter.id}`} className="font-medium text-brand hover:underline">
                  {meter.asset?.asset_code}
                </Link>
                <div className="text-xs text-foreground-muted">{meter.asset?.name}</div>
              </div>
            ),
          },
          {
            key: "meter",
            header: "Meter",
            render: (meter) => (
              <div className="flex items-center gap-2">
                {meter.type?.name}
                {!meter.type?.is_cumulative ? <Badge variant="neutral">May go down</Badge> : null}
              </div>
            ),
          },
          {
            key: "current_value",
            header: "Current reading",
            align: "right",
            render: (meter) => formatQuantity(meter.current_value, meter.type?.unit),
          },
          {
            key: "last_reading_at",
            header: "Last read",
            render: (meter) =>
              meter.last_reading_at ? (
                <span className={isStale(meter) ? "font-semibold text-danger" : undefined}>
                  <FormattedDateTime value={meter.last_reading_at} />
                  {isStale(meter) ? <div className="text-xs font-normal">Not read for over a fortnight</div> : null}
                </span>
              ) : (
                <span className="text-foreground-muted">Never read</span>
              ),
          },
        ]}
        rows={meters}
        rowKey={(meter) => meter.id}
        emptyTitle="No meters fitted."
        emptyDescription="Fit a meter to a machine from its own screen. Usage-based maintenance has nothing to hang off until one exists."
        rowActions={(meter) => [
          { label: "Record reading", onSelect: () => router.push(`/metering/${meter.id}`) },
        ]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { MetersTable };
