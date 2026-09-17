"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Tabs, TabsList, TabsTab, TabsIndicator, TabsPanel } from "@/components/ui/tabs";
import { Card, CardBody } from "@/components/ui/card";
import { DataTable } from "@/components/ui/data-table";
import { Skeleton } from "@/components/ui/skeleton";
import { ErrorState } from "@/components/ui/error-state";
import { StatusBadge } from "@/components/ui/status-badge";
import { TransferActionsMenu } from "@/components/assets/transfer-actions-menu";
import { CostsTab } from "@/components/assets/costs-tab";
import { MeteringTab } from "@/components/assets/metering-tab";
import { DocumentsTab } from "@/components/assets/documents-tab";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useLazyTabData } from "@/lib/use-lazy-tab-data";
import { useT } from "@/lib/i18n";
import {
  getStatusHistory, getMaintenanceHistory, getTransferHistory, getMeters, getCostsData, getDocumentsData,
} from "@/app/(app)/assets/[assetId]/actions";

const TAB_VALUES = ["overview", "status-history", "maintenance-history", "transfers", "metering", "costs", "documents"];

/**
 * All tabs live in one Client Component (rather than the detail page
 * itself building `DataTable` columns) because the column `render`
 * functions can't cross the Server→Client boundary — only the plain,
 * serializable `asset` data can, and this is where those functions get
 * built instead.
 *
 * Every tab but Overview fetches its own data lazily, on its own first
 * render, rather than the page fetching all seven up front: `TabsPanel`
 * (base-ui, `keepMounted` false by default) never mounts an inactive
 * panel's children at all, so a tab nobody opens costs nothing. This
 * exists because the page's original all-at-once fetch (asset plus six
 * more, several of them expensive — cost ledger, lifecycle cost, document
 * list, meters) took long enough on this product's actual shared hosting
 * that a slow mobile connection timed out before the page ever painted
 * (confirmed live) — the fix is fetching only what the visible tab needs.
 *
 * `?tab=` is read once for the *initial* tab only (`Tabs defaultValue`,
 * uncontrolled) — this is what lets a scanned QR code's `log_meter`
 * action land straight on the Metering tab instead of Overview. It is
 * deliberately not kept in sync on every click via `router.push`: a tab
 * switch needs no server round trip (each tab now fetches its own data
 * exactly once, itself), and driving it through the router anyway is
 * exactly what caused a real, reproduced Next.js dev-mode bug elsewhere in
 * this app (a query-only navigation on a page full of `cache: "no-store"`
 * fetches can silently fail to commit — see the platform console's own
 * `tenant-tabs.jsx` for the same fix).
 */
function AssetDetailTabs({ asset, transferActions, meteringActions, costActions, documentActions }) {
  const t = useT("asset");
  const searchParams = useSearchParams();
  const requestedTab = searchParams.get("tab");
  const initialTab = TAB_VALUES.includes(requestedTab) ? requestedTab : "overview";

  return (
    <Card>
      <CardBody>
        <Tabs defaultValue={initialTab}>
          <TabsList>
            <TabsTab value="overview">{t("overview")}</TabsTab>
            <TabsTab value="status-history">{t("history")}</TabsTab>
            <TabsTab value="maintenance-history">{t("maintenance_history")}</TabsTab>
            <TabsTab value="transfers">{t("transfers")}</TabsTab>
            <TabsTab value="metering">{t("metering")}</TabsTab>
            <TabsTab value="costs">{t("costs")}</TabsTab>
            <TabsTab value="documents">{t("documents")}</TabsTab>
            <TabsIndicator />
          </TabsList>

          <TabsPanel value="overview">
            <Overview asset={asset} />
          </TabsPanel>

          <TabsPanel value="status-history">
            <StatusHistoryPanel assetId={asset.id} />
          </TabsPanel>

          <TabsPanel value="maintenance-history">
            <MaintenanceHistoryPanel assetId={asset.id} />
          </TabsPanel>

          <TabsPanel value="transfers">
            <TransfersPanel assetId={asset.id} transferActions={transferActions} />
          </TabsPanel>

          <TabsPanel value="metering">
            <MeteringPanel assetId={asset.id} meteringActions={meteringActions} />
          </TabsPanel>

          <TabsPanel value="costs">
            <CostsPanel assetId={asset.id} costActions={costActions} />
          </TabsPanel>

          <TabsPanel value="documents">
            <DocumentsPanel assetId={asset.id} documentActions={documentActions} />
          </TabsPanel>
        </Tabs>
      </CardBody>
    </Card>
  );
}

/** Shared shape every lazy panel below renders while its one fetch is in flight or failed. */
function TabLoadState({ loading, error, onRetry, rows = 6 }) {
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

function StatusHistoryPanel({ assetId }) {
  const t = useT("asset");
  const { data, loading, error, refetch } = useLazyTabData(() => getStatusHistory(assetId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} />;
  }

  return (
    <DataTable
      columns={[
        {
          key: "changed_at",
          header: t("changed_at"),
          render: (row) => <FormattedDateTime value={row.changed_at} />,
        },
        {
          key: "transition",
          header: t("transition"),
          render: (row) => (
            <span className="flex items-center gap-2">
              {row.from_status ? (
                <StatusBadge status={row.from_status} label={t(`status_${row.from_status.toLowerCase()}`)} />
              ) : (
                <span className="text-foreground-muted">—</span>
              )}
              <span className="text-foreground-muted">→</span>
              <StatusBadge status={row.to_status} label={t(`status_${row.to_status.toLowerCase()}`)} />
            </span>
          ),
        },
        { key: "changed_by", header: t("changed_by"), render: (row) => row.changed_by?.name ?? t("system") },
        { key: "reason", header: t("reason"), render: (row) => row.reason ?? "—" },
      ]}
      rows={data}
      rowKey={(row) => row.id}
      emptyTitle={t("no_history")}
    />
  );
}

function MaintenanceHistoryPanel({ assetId }) {
  const t = useT("asset");
  const { data, loading, error, refetch } = useLazyTabData(() => getMaintenanceHistory(assetId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} />;
  }

  return (
    <DataTable
      columns={[
        {
          key: "work_order_number",
          header: t("work_order"),
          render: (row) => (
            <Link href={`/work-orders/${row.id}`} className="font-medium text-brand hover:underline">
              {row.work_order_number}
            </Link>
          ),
        },
        { key: "type", header: t("type"), render: (row) => row.type ?? "—" },
        {
          key: "status",
          header: t("status"),
          render: (row) => <StatusBadge status={row.status} label={t(`status_${row.status?.toLowerCase()}`)} />,
        },
        { key: "priority", header: t("priority"), render: (row) => t(`criticality_${row.priority?.toLowerCase()}`) },
        {
          key: "scheduled_start",
          header: t("scheduled"),
          render: (row) => <FormattedDateTime value={row.scheduled_start} mode="date" />,
        },
      ]}
      rows={data}
      rowKey={(row) => row.id}
      emptyTitle={t("no_maintenance_history")}
    />
  );
}

function TransfersPanel({ assetId, transferActions }) {
  const t = useT("asset");
  const { data, loading, error, refetch } = useLazyTabData(() => getTransferHistory(assetId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} />;
  }

  return (
    <DataTable
      columns={[
        { key: "transfer_number", header: t("transfer") },
        {
          key: "route",
          header: t("route"),
          render: (row) => (
            <span className="flex items-center gap-2">
              {row.from_factory?.name ?? "—"}
              <span className="text-foreground-muted">→</span>
              {row.to_factory?.name} · {row.to_location?.name}
            </span>
          ),
        },
        {
          key: "status",
          header: t("status"),
          render: (row) => <StatusBadge status={row.status} label={t(`transfer_status_${row.status?.toLowerCase()}`)} />,
        },
        { key: "reason", header: t("reason"), render: (row) => row.reason ?? "—" },
        {
          key: "requested_at",
          header: t("requested"),
          render: (row) => <FormattedDateTime value={row.requested_at} mode="date" />,
        },
        {
          key: "actions",
          header: "",
          align: "right",
          render: (row) => <TransferActionsMenu transfer={row} actions={transferActions} onMutated={refetch} />,
        },
      ]}
      rows={data}
      rowKey={(row) => row.id}
      emptyTitle={t("no_transfers")}
    />
  );
}

function MeteringPanel({ assetId, meteringActions }) {
  const { data, loading, error, refetch } = useLazyTabData(() => getMeters(assetId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} rows={2} />;
  }

  return <MeteringTab meters={data} actions={meteringActions} />;
}

function CostsPanel({ assetId, costActions }) {
  const { data, loading, error, refetch } = useLazyTabData(() => getCostsData(assetId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} rows={4} />;
  }

  return (
    <CostsTab
      assetId={assetId}
      lifecycle={data.lifecycle}
      entries={data.entries}
      categories={data.categories}
      postAction={costActions.postAction}
      reverseAction={costActions.reverseAction}
      onMutated={refetch}
    />
  );
}

function DocumentsPanel({ assetId, documentActions }) {
  const { data, loading, error, refetch } = useLazyTabData(() => getDocumentsData(assetId));

  if (loading || error) {
    return <TabLoadState loading={loading} error={error} onRetry={refetch} rows={3} />;
  }

  return (
    <DocumentsTab
      documents={data.items}
      uploadAction={documentActions.uploadAction}
      deleteAction={documentActions.deleteAction}
      canManage={data.canManage}
      onMutated={refetch}
    />
  );
}

function Overview({ asset }) {
  const t = useT("asset");

  return (
    <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
      <Field label={t("type")}>{asset.type?.name ?? "—"}</Field>
      <Field label={t("category")}>{asset.category?.name ?? "—"}</Field>
      <Field label={t("manufacturer")}>{asset.manufacturer?.name ?? "—"}</Field>
      <Field label={t("model")}>{asset.model ?? "—"}</Field>
      <Field label={t("serial_number")}>{asset.serial_number ?? "—"}</Field>
      <Field label={t("parent_asset")}>
        {asset.parent ? (
          <Link href={`/assets/${asset.parent.id}`} className="text-brand hover:underline">
            {asset.parent.asset_code}
          </Link>
        ) : (
          "—"
        )}
      </Field>
      <Field label={t("commissioning_date")}>{asset.commissioning_date ?? "—"}</Field>
      <Field label={t("warranty")}>
        {asset.warranty?.end ? (
          <span className={asset.warranty.active ? "text-success" : "text-foreground-muted"}>
            {t("warranty_until", { date: asset.warranty.end })}{" "}
            {asset.warranty.active ? t("warranty_active_suffix") : t("warranty_expired_suffix")}
          </span>
        ) : (
          "—"
        )}
      </Field>
      {asset.description ? (
        <div className="sm:col-span-2">
          <Field label={t("description")}>{asset.description}</Field>
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
