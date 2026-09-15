"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Tabs, TabsList, TabsTab, TabsIndicator, TabsPanel } from "@/components/ui/tabs";
import { Card, CardBody } from "@/components/ui/card";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge, formatStatus } from "@/components/ui/status-badge";
import { TransferActionsMenu } from "@/components/assets/transfer-actions-menu";
import { CostsTab } from "@/components/assets/costs-tab";
import { MeteringTab } from "@/components/assets/metering-tab";
import { DocumentsTab } from "@/components/assets/documents-tab";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";

const TAB_VALUES = ["overview", "status-history", "maintenance-history", "transfers", "metering", "costs", "documents"];

/**
 * All tabs live in one Client Component (rather than the detail page
 * itself building `DataTable` columns) because the column `render`
 * functions can't cross the Server→Client boundary — only the plain,
 * serializable `asset`/`statusHistory`/`maintenanceHistory`/`transfers`
 * data can, and this is where those functions get built instead.
 *
 * `?tab=` is read once for the *initial* tab only (`Tabs defaultValue`,
 * uncontrolled) — this is what lets a scanned QR code's `log_meter`
 * action land straight on the Metering tab instead of Overview. It is
 * deliberately not kept in sync on every click via `router.push`: this
 * page's own data is all fetched once up front, so a tab switch needs no
 * server round trip, and driving it through the router anyway is exactly
 * what caused a real, reproduced Next.js dev-mode bug elsewhere in this
 * app (a query-only navigation on a page full of `cache: "no-store"`
 * fetches can silently fail to commit — see the platform console's own
 * `tenant-tabs.jsx`for the same fix).
 */
function AssetDetailTabs({ asset, statusHistory, maintenanceHistory, transfers, transferActions, meters, meteringActions, costs, documents }) {
  const searchParams = useSearchParams();
  const requestedTab = searchParams.get("tab");
  const initialTab = TAB_VALUES.includes(requestedTab) ? requestedTab : "overview";

  return (
    <Card>
      <CardBody>
        <Tabs defaultValue={initialTab}>
          <TabsList>
            <TabsTab value="overview">Overview</TabsTab>
            <TabsTab value="status-history">Status history</TabsTab>
            <TabsTab value="maintenance-history">Maintenance history</TabsTab>
            <TabsTab value="transfers">Transfers</TabsTab>
            <TabsTab value="metering">Metering</TabsTab>
            <TabsTab value="costs">Costs</TabsTab>
            <TabsTab value="documents">Documents</TabsTab>
            <TabsIndicator />
          </TabsList>

          <TabsPanel value="overview">
            <Overview asset={asset} />
          </TabsPanel>

          <TabsPanel value="status-history">
            <DataTable
              columns={[
                {
                  key: "changed_at",
                  header: "Changed at",
                  render: (row) => <FormattedDateTime value={row.changed_at} />,
                },
                {
                  key: "transition",
                  header: "Transition",
                  render: (row) => (
                    <span className="flex items-center gap-2">
                      {row.from_status ? <StatusBadge status={row.from_status} /> : <span className="text-foreground-muted">—</span>}
                      <span className="text-foreground-muted">→</span>
                      <StatusBadge status={row.to_status} />
                    </span>
                  ),
                },
                { key: "changed_by", header: "Changed by", render: (row) => row.changed_by?.name ?? "System" },
                { key: "reason", header: "Reason", render: (row) => row.reason ?? "—" },
              ]}
              rows={statusHistory}
              rowKey={(row) => row.id}
              emptyTitle="No status changes recorded yet."
            />
          </TabsPanel>

          <TabsPanel value="maintenance-history">
            <DataTable
              columns={[
                {
                  key: "work_order_number",
                  header: "Work order",
                  render: (row) => (
                    <Link href={`/work-orders/${row.id}`} className="font-medium text-brand hover:underline">
                      {row.work_order_number}
                    </Link>
                  ),
                },
                { key: "type", header: "Type", render: (row) => row.type ?? "—" },
                { key: "status", header: "Status", render: (row) => <StatusBadge status={row.status} /> },
                { key: "priority", header: "Priority", render: (row) => formatStatus(row.priority) },
                {
                  key: "scheduled_start",
                  header: "Scheduled",
                  render: (row) => <FormattedDateTime value={row.scheduled_start} mode="date" />,
                },
              ]}
              rows={maintenanceHistory}
              rowKey={(row) => row.id}
              emptyTitle="No work orders recorded against this machine yet."
            />
          </TabsPanel>

          <TabsPanel value="transfers">
            <DataTable
              columns={[
                { key: "transfer_number", header: "Transfer" },
                {
                  key: "route",
                  header: "Route",
                  render: (row) => (
                    <span className="flex items-center gap-2">
                      {row.from_factory?.name ?? "—"}
                      <span className="text-foreground-muted">→</span>
                      {row.to_factory?.name} · {row.to_location?.name}
                    </span>
                  ),
                },
                { key: "status", header: "Status", render: (row) => <StatusBadge status={row.status} /> },
                { key: "reason", header: "Reason", render: (row) => row.reason ?? "—" },
                {
                  key: "requested_at",
                  header: "Requested",
                  render: (row) => <FormattedDateTime value={row.requested_at} mode="date" />,
                },
                {
                  key: "actions",
                  header: "",
                  align: "right",
                  render: (row) => <TransferActionsMenu transfer={row} actions={transferActions} />,
                },
              ]}
              rows={transfers}
              rowKey={(row) => row.id}
              emptyTitle="No transfers recorded yet."
            />
          </TabsPanel>

          <TabsPanel value="metering">
            <MeteringTab meters={meters} actions={meteringActions} />
          </TabsPanel>

          <TabsPanel value="costs">
            <CostsTab
              assetId={asset.id}
              lifecycle={costs.lifecycle}
              entries={costs.entries}
              categories={costs.categories}
              postAction={costs.postAction}
              reverseAction={costs.reverseAction}
            />
          </TabsPanel>

          <TabsPanel value="documents">
            <DocumentsTab
              documents={documents.items}
              uploadAction={documents.uploadAction}
              deleteAction={documents.deleteAction}
              canManage={documents.canManage}
            />
          </TabsPanel>
        </Tabs>
      </CardBody>
    </Card>
  );
}

function Overview({ asset }) {
  return (
    <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
      <Field label="Type">{asset.type?.name ?? "—"}</Field>
      <Field label="Category">{asset.category?.name ?? "—"}</Field>
      <Field label="Manufacturer">{asset.manufacturer?.name ?? "—"}</Field>
      <Field label="Model">{asset.model ?? "—"}</Field>
      <Field label="Serial number">{asset.serial_number ?? "—"}</Field>
      <Field label="Parent asset">
        {asset.parent ? (
          <Link href={`/assets/${asset.parent.id}`} className="text-brand hover:underline">
            {asset.parent.asset_code}
          </Link>
        ) : (
          "—"
        )}
      </Field>
      <Field label="Commissioned">{asset.commissioning_date ?? "—"}</Field>
      <Field label="Warranty">
        {asset.warranty?.end ? (
          <span className={asset.warranty.active ? "text-success" : "text-foreground-muted"}>
            Until {asset.warranty.end}
            {asset.warranty.active ? " (active)" : " (expired)"}
          </span>
        ) : (
          "—"
        )}
      </Field>
      {asset.description ? (
        <div className="sm:col-span-2">
          <Field label="Description">{asset.description}</Field>
        </div>
      ) : null}
    </div>
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

export { AssetDetailTabs };
