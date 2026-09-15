"use client";

import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { formatQuantity, formatNumber } from "@/lib/format";

/** Own Client Component for the same reason as every other detail-page table this session: `DataTable`'s column `render` functions can't cross the Server→Client boundary. */
function LowStockTable({ parts }) {
  return (
    <DataTable
      columns={[
        {
          key: "part_number",
          header: "Part",
          render: (p) => (
            <Link href={`/inventory/parts/${p.id}`} className="font-medium text-brand hover:underline">
              {p.part_number}
              <div className="text-xs font-normal text-foreground-muted">{p.name}</div>
            </Link>
          ),
        },
        { key: "category", header: "Category", render: (p) => p.category?.name ?? "—" },
        { key: "on_hand", header: "On hand", align: "right", render: (p) => formatQuantity(p.on_hand, p.unit) },
        { key: "reorder_level", header: "Reorder level", align: "right", render: (p) => formatNumber(p.reorder_level) },
        {
          key: "is_critical_spare",
          header: "Critical",
          render: (p) => (p.is_critical_spare ? <Badge variant="danger">Critical</Badge> : null),
        },
      ]}
      rows={parts}
      rowKey={(p) => p.id}
      emptyTitle="Nothing is low on stock."
    />
  );
}

export { LowStockTable };
