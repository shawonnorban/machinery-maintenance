"use client";

import { Tabs, TabsList, TabsTab, TabsIndicator, TabsPanel } from "@/components/ui/tabs";
import { Card, CardBody } from "@/components/ui/card";
import { DataTable } from "@/components/ui/data-table";
import { Skeleton } from "@/components/ui/skeleton";
import { ErrorState } from "@/components/ui/error-state";
import { ReverseTransactionButton } from "@/components/inventory/reverse-transaction-button";
import { CompatibilityTab } from "@/components/inventory/compatibility-tab";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatNumber } from "@/lib/format";
import { useLazyTabData, withRefetch } from "@/lib/use-lazy-tab-data";
import { addCompatibility, deleteCompatibility, getTransactions, getCompatibilityData } from "@/app/(app)/inventory/parts/[partId]/actions";

/**
 * One Client Component for every tab, same reason as `AssetDetailTabs`:
 * `DataTable` column `render` functions can't cross the Server→Client
 * boundary — only the plain `part`/`stock` data can. Transactions and
 * Compatibility fetch their own data lazily, the first time each is
 * actually opened, for the same reason `AssetDetailTabs` does: the
 * page's original all-at-once fetch (seven concurrent calls, several of
 * them not cheap — the full transaction ledger, the whole spare-parts
 * catalog for cross-referencing) was slow enough on this product's
 * actual shared hosting to time out on a poor connection before the
 * page ever painted.
 */
function SparePartDetailTabs({ part, partId, stock, reverseAction }) {
  return (
    <Card>
      <CardBody>
        <Tabs defaultValue="overview">
          <TabsList>
            <TabsTab value="overview">Overview</TabsTab>
            <TabsTab value="stock">Stock</TabsTab>
            <TabsTab value="transactions">Transactions</TabsTab>
            <TabsTab value="compatibility">Compatibility</TabsTab>
            <TabsIndicator />
          </TabsList>

          <TabsPanel value="overview">
            <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
              <Field label="Unit">{part.unit}</Field>
              <Field label="Brand">{part.brand ?? "—"}</Field>
              <Field label="Manufacturer">{part.manufacturer ?? "—"}</Field>
              <Field label="Minimum stock">{formatNumber(part.minimum_stock)}</Field>
              <Field label="Reorder level">{formatNumber(part.reorder_level)}</Field>
              <Field label="Lead time">{part.lead_time_days ? `${part.lead_time_days} days` : "—"}</Field>
              <Field label="Shelf life">{part.shelf_life_days ? `${part.shelf_life_days} days` : "—"}</Field>
              {part.notes ? (
                <div className="sm:col-span-2">
                  <Field label="Notes">{part.notes}</Field>
                </div>
              ) : null}
            </div>
          </TabsPanel>

          <TabsPanel value="stock">
            <div className="mb-4 flex items-baseline gap-2">
              <span className="text-xs text-foreground-muted">Total on hand:</span>
              <span className="tabular text-lg font-semibold text-foreground">
                {formatNumber(stock.total_on_hand)} {stock.unit}
              </span>
            </div>
            <DataTable
              columns={[
                { key: "bin", header: "Bin", render: (row) => (row.in_transit ? `${row.bin} (in transit)` : row.bin) },
                { key: "on_hand", header: "On hand", align: "right", render: (row) => formatNumber(row.on_hand) },
                { key: "reserved", header: "Reserved", align: "right", render: (row) => formatNumber(row.reserved) },
                { key: "available", header: "Available", align: "right", render: (row) => formatNumber(row.available) },
              ]}
              rows={stock.locations}
              rowKey={(row) => row.bin_id}
              emptyTitle="No stock recorded for this part yet."
            />
          </TabsPanel>

          <TabsPanel value="transactions">
            <TransactionsPanel partId={partId} reverseAction={reverseAction} />
          </TabsPanel>

          <TabsPanel value="compatibility">
            <CompatibilityPanel partId={partId} />
          </TabsPanel>
        </Tabs>
      </CardBody>
    </Card>
  );
}

/** Shared shape every lazy panel below renders while its one fetch is in flight or failed. */
function TabLoadState({ loading, error, onRetry, rows = 4 }) {
  if (loading) {
    return (
      <div className="flex flex-col gap-2">
        {Array.from({ length: rows }, (_, index) => (
          <Skeleton key={index} className="h-10 w-full" />
        ))}
      </div>
    );
  }

  return <ErrorState description={error.message} onRetry={onRetry} />;
}

function TransactionsPanel({ partId, reverseAction }) {
  const { data, loading, error, refetch } = useLazyTabData(() => getTransactions(partId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} />;
  }

  return (
    <DataTable
      columns={[
        {
          key: "transaction_at",
          header: "Date",
          render: (row) => <FormattedDateTime value={row.transaction_at} />,
        },
        { key: "transaction_type", header: "Type" },
        { key: "bin", header: "Bin", render: (row) => row.bin ?? "—" },
        { key: "signed_quantity", header: "Quantity", align: "right", render: (row) => formatNumber(row.signed_quantity) },
        { key: "balance_after", header: "Balance after", align: "right", render: (row) => formatNumber(row.balance_after) },
        { key: "work_order", header: "Work order", render: (row) => row.work_order ?? "—" },
        {
          key: "notes",
          header: "Notes",
          render: (row) => (
            <>
              {row.notes ?? "—"}
              {row.reverses ? <div className="text-xs text-foreground-muted">Reverses #{row.reverses}</div> : null}
            </>
          ),
        },
        {
          key: "actions",
          header: "",
          align: "right",
          render: (row) => <ReverseTransactionButton action={withRefetch(reverseAction.bind(null, row.id), refetch)} />,
        },
      ]}
      rows={data}
      rowKey={(row) => row.id}
      emptyTitle="No transactions recorded yet."
    />
  );
}

function CompatibilityPanel({ partId }) {
  const { data, loading, error, refetch } = useLazyTabData(() => getCompatibilityData(partId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} rows={3} />;
  }

  return (
    <CompatibilityTab
      compatibility={data.rows}
      assetModels={data.assetModels}
      otherParts={data.otherParts}
      addAction={withRefetch(addCompatibility.bind(null, partId), refetch)}
      deleteAction={withRefetch(deleteCompatibility.bind(null, partId), refetch)}
    />
  );
}

function Field({ label, children }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs font-medium text-foreground-muted">{label}</span>
      <span className="text-sm text-foreground">{children}</span>
    </div>
  );
}

export { SparePartDetailTabs };
