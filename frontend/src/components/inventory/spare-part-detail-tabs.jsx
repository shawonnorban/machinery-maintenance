"use client";

import { Tabs, TabsList, TabsTab, TabsIndicator, TabsPanel } from "@/components/ui/tabs";
import { Card, CardBody } from "@/components/ui/card";
import { DataTable } from "@/components/ui/data-table";
import { ReverseTransactionButton } from "@/components/inventory/reverse-transaction-button";
import { CompatibilityTab } from "@/components/inventory/compatibility-tab";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { formatNumber } from "@/lib/format";

/**
 * One Client Component for every tab, same reason as `AssetDetailTabs`:
 * `DataTable` column `render` functions can't cross the Server→Client
 * boundary — only the plain `part`/`stock`/`transactions` data can.
 */
function SparePartDetailTabs({ part, stock, transactions, reverseAction, compatibility }) {
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
                  render: (row) => <ReverseTransactionButton action={reverseAction.bind(null, row.id)} />,
                },
              ]}
              rows={transactions}
              rowKey={(row) => row.id}
              emptyTitle="No transactions recorded yet."
            />
          </TabsPanel>

          <TabsPanel value="compatibility">
            <CompatibilityTab
              compatibility={compatibility.rows}
              assetModels={compatibility.assetModels}
              otherParts={compatibility.otherParts}
              addAction={compatibility.addAction}
              deleteAction={compatibility.deleteAction}
            />
          </TabsPanel>
        </Tabs>
      </CardBody>
    </Card>
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
