"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Select } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatQuantity } from "@/lib/format";
import { useT } from "@/lib/i18n";

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
  const t = useT("metering");

  const STATUS_OPTIONS = [
    { value: "ACTIVE", label: t("active") },
    { value: "INACTIVE", label: t("inactive") },
  ];

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
            header: t("asset"),
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
            header: t("meter"),
            render: (meter) => (
              <div className="flex items-center gap-2">
                {meter.type?.name}
                {!meter.type?.is_cumulative ? <Badge variant="neutral">{t("non_cumulative")}</Badge> : null}
              </div>
            ),
          },
          {
            key: "current_value",
            header: t("current_value"),
            align: "right",
            render: (meter) => formatQuantity(meter.current_value, meter.type?.unit),
          },
          {
            key: "last_reading_at",
            header: t("last_read_at"),
            render: (meter) =>
              meter.last_reading_at ? (
                <span className={isStale(meter) ? "font-semibold text-danger" : undefined}>
                  <FormattedDateTime value={meter.last_reading_at} />
                  {isStale(meter) ? <div className="text-xs font-normal">{t("stale")}</div> : null}
                </span>
              ) : (
                <span className="text-foreground-muted">{t("never_read")}</span>
              ),
          },
        ]}
        rows={meters}
        rowKey={(meter) => meter.id}
        emptyTitle={t("no_meters")}
        emptyDescription={t("no_meters_hint")}
        rowActions={(meter) => [
          { label: t("record_reading"), onSelect: () => router.push(`/metering/${meter.id}`) },
        ]}
        pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
        onPageChange={(nextPage) => navigate({ page: nextPage })}
      />
    </div>
  );
}

export { MetersTable };
