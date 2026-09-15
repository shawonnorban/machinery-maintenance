"use client";

import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatNumber } from "@/lib/format";

/**
 * Column `render` functions and `rowKey` can't cross the Server→Client
 * boundary (only rendered JSX elements can) — this exists purely so the
 * detail page (a Server Component) can hand `readings` over as plain,
 * serializable data and let the column definitions live entirely on the
 * client side that actually needs to call them per row.
 */
function ReadingsTable({ readings }) {
  return (
    <DataTable
      columns={[
        {
          key: "reading_at",
          header: "Read at",
          render: (reading) => <FormattedDateTime value={reading.reading_at} />,
        },
        { key: "value", header: "Reading", align: "right", render: (reading) => formatNumber(reading.value) },
        {
          key: "delta",
          header: "Since last",
          align: "right",
          render: (reading) =>
            reading.is_reset_baseline ? <Badge variant="warning">Replaced</Badge> : formatNumber(reading.delta),
        },
        {
          key: "source",
          header: "Source",
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
      emptyTitle="No readings yet."
    />
  );
}

export { ReadingsTable };
