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
import { useT } from "@/lib/i18n";
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
  const t = useT("inventory");
  const tc = useT("common");

  return (
    <Card>
      <CardBody>
        <Tabs defaultValue="overview">
          <TabsList>
            <TabsTab value="overview">{tc("overview")}</TabsTab>
            <TabsTab value="stock">{t("stock")}</TabsTab>
            <TabsTab value="transactions">{t("transactions")}</TabsTab>
            <TabsTab value="compatibility">{t("compatibility_tab")}</TabsTab>
            <TabsIndicator />
          </TabsList>

          <TabsPanel value="overview">
            <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
              <Field label={t("unit")}>{part.unit}</Field>
              <Field label={t("brand")}>{part.brand ?? "—"}</Field>
              <Field label={t("manufacturer")}>{part.manufacturer ?? "—"}</Field>
              <Field label={t("minimum_stock")}>{formatNumber(part.minimum_stock)}</Field>
              <Field label={t("reorder_level")}>{formatNumber(part.reorder_level)}</Field>
              <Field label={t("lead_time")}>{part.lead_time_days ? t("days_count", { count: part.lead_time_days }) : "—"}</Field>
              <Field label={t("shelf_life")}>{part.shelf_life_days ? t("days_count", { count: part.shelf_life_days }) : "—"}</Field>
              {part.notes ? (
                <div className="sm:col-span-2">
                  <Field label={t("notes")}>{part.notes}</Field>
                </div>
              ) : null}
            </div>
          </TabsPanel>

          <TabsPanel value="stock">
            <div className="mb-4 flex items-baseline gap-2">
              <span className="text-xs text-foreground-muted">{t("total_on_hand")}</span>
              <span className="tabular text-lg font-semibold text-foreground">
                {formatNumber(stock.total_on_hand)} {stock.unit}
              </span>
            </div>
            <DataTable
              columns={[
                { key: "bin", header: t("bin"), render: (row) => (row.in_transit ? `${row.bin} (${t("in_transit")})` : row.bin) },
                { key: "on_hand", header: t("on_hand"), align: "right", render: (row) => formatNumber(row.on_hand) },
                { key: "reserved", header: t("reserved"), align: "right", render: (row) => formatNumber(row.reserved) },
                { key: "available", header: t("available"), align: "right", render: (row) => formatNumber(row.available) },
              ]}
              rows={stock.locations}
              rowKey={(row) => row.bin_id}
              emptyTitle={t("no_stock")}
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
  const t = useT("inventory");
  const { data, loading, error, refetch } = useLazyTabData(() => getTransactions(partId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} />;
  }

  return (
    <DataTable
      columns={[
        {
          key: "transaction_at",
          header: t("date"),
          render: (row) => <FormattedDateTime value={row.transaction_at} />,
        },
        { key: "transaction_type", header: t("transaction_type"), render: (row) => t(`type_${row.transaction_type?.toLowerCase()}`) },
        { key: "bin", header: t("bin"), render: (row) => row.bin ?? "—" },
        { key: "signed_quantity", header: t("quantity"), align: "right", render: (row) => formatNumber(row.signed_quantity) },
        { key: "balance_after", header: t("balance_after"), align: "right", render: (row) => formatNumber(row.balance_after) },
        { key: "work_order", header: t("work_order"), render: (row) => row.work_order ?? "—" },
        {
          key: "notes",
          header: t("notes"),
          render: (row) => (
            <>
              {row.notes ?? "—"}
              {row.reverses ? <div className="text-xs text-foreground-muted">{t("reverses_hash", { id: row.reverses })}</div> : null}
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
      emptyTitle={t("no_transactions")}
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
