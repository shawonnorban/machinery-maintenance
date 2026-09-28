"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { formatQuantity, formatCurrency } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * Column `render` functions can't cross the Server→Client boundary, so this
 * client component owns the column definitions; the server page just hands
 * over the current page of `balances` as plain data.
 */
function StockBalancesTable({ balances, meta, search, binId }) {
  const t = useT("inventory");
  const router = useRouter();

  function navigate(page) {
    const params = new URLSearchParams({ page: String(page) });
    if (search) params.set("search", search);
    if (binId) params.set("bin_id", binId);
    router.push(`/inventory/stock?${params.toString()}`);
  }

  return (
    <DataTable
      columns={[
        {
          key: "spare_part",
          header: t("part"),
          render: (row) =>
            row.spare_part ? (
              <div>
                <Link href={`/inventory/parts/${row.spare_part.id}`} className="font-medium text-brand hover:underline">
                  {row.spare_part.part_number}
                </Link>
                <div className="text-xs text-foreground-muted">{row.spare_part.name}</div>
              </div>
            ) : (
              "—"
            ),
        },
        { key: "bin", header: t("bin"), render: (row) => row.bin?.full_path ?? "—" },
        { key: "on_hand", header: t("on_hand"), align: "right", render: (row) => formatQuantity(row.on_hand, row.spare_part?.unit) },
        { key: "reserved", header: t("reserved"), align: "right", render: (row) => formatQuantity(row.reserved, row.spare_part?.unit) },
        {
          key: "available",
          header: t("available"),
          align: "right",
          render: (row) => (
            <span className={Number(row.available) <= 0 ? "text-danger" : undefined}>
              {formatQuantity(row.available, row.spare_part?.unit)}
            </span>
          ),
        },
        {
          key: "low",
          header: "",
          render: (row) =>
            row.spare_part && Number(row.on_hand) <= Number(row.spare_part.reorder_level ?? 0) ? (
              <Badge variant="warning">{t("low")}</Badge>
            ) : null,
        },
        {
          key: "total_value",
          header: t("total_value"),
          align: "right",
          render: (row) => formatCurrency(row.total_value, row.currency),
        },
      ]}
      rows={balances}
      rowKey={(row) => row.id}
      emptyTitle={t("no_stock")}
      pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
      onPageChange={navigate}
    />
  );
}

export { StockBalancesTable };
