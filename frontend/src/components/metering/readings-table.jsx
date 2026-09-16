"use client";

import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * Column `render` functions and `rowKey` can't cross the Server→Client
 * boundary (only rendered JSX elements can) — this exists purely so the
 * detail page (a Server Component) can hand `readings` over as plain,
 * serializable data and let the column definitions live entirely on the
 * client side that actually needs to call them per row.
 */
function ReadingsTable({ readings }) {
  const t = useT("metering");

  return (
    <DataTable
      columns={[
        {
          key: "reading_at",
          header: t("read_at"),
          render: (reading) => <FormattedDateTime value={reading.reading_at} />,
        },
        { key: "value", header: t("value"), align: "right", render: (reading) => formatNumber(reading.value) },
        {
          key: "delta",
          header: t("consumed"),
          align: "right",
          render: (reading) =>
            reading.is_reset_baseline ? <Badge variant="warning">{t("replacement")}</Badge> : formatNumber(reading.delta),
        },
        {
          key: "source",
          header: t("source"),
          render: (reading) => (
            <>
              {reading.source}
              {reading.notes ? <div className="text-xs text-foreground-muted">{reading.notes}</div> : null}
            </>
          ),
        },
      ]}
      rows={readings}
      rowKey={(reading) => reading.id}
      emptyTitle={t("no_readings")}
    />
  );
}

export { ReadingsTable };
