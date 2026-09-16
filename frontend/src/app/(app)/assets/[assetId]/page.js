import Link from "next/link";
import { ArrowLeft, Pencil } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { StatusBadge, formatStatus } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { AssetDetailTabs } from "@/components/assets/asset-detail-tabs";
import { ChangeStatusModal } from "@/components/assets/change-status-modal";
import { TransferRequestModal } from "@/components/assets/transfer-request-modal";
import { QrCard } from "@/components/assets/qr-card";
import {
  changeStatus, requestTransfer, approveTransfer, receiveTransfer, rejectTransfer, postCost, reverseCost, regenerateQr,
  uploadDocument, deleteDocument, recordReading,
} from "./actions";

const CRITICALITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };
const CRITICALITY_BORDER = { CRITICAL: "border-l-danger", HIGH: "border-l-warning", MEDIUM: "border-l-info", LOW: "border-l-border-strong" };

/**
 * Mirrors `asset::assets.show.blade.php`'s core (overview, status history,
 * maintenance history, status change, transfer, the QR token card, the
 * documents card — ported as a tab here rather than a standalone card, per
 * the Work Order attachments tab precedent).
 *
 * Only three calls up front — the asset itself, the header actions' own
 * form options, and the QR card — rather than the twelve this page used
 * to fire in one `Promise.all`. Everything else (status/maintenance
 * history, transfers, meters, costs, documents) is fetched by its own tab,
 * lazily, the first time it's actually opened (`AssetDetailTabs`'s own
 * note has why): that original all-at-once load took long enough on this
 * product's actual shared hosting that a slow mobile connection timed out
 * before the page ever painted, confirmed live.
 */
export default async function AssetDetailPage({ params }) {
  const { assetId } = await params;

  const [asset, formOptions, qr] = await Promise.all([
    apiFetch(`/assets/${assetId}`),
    apiFetch("/assets/form-options"),
    apiFetch(`/assets/${assetId}/qr`),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Assets", href: "/assets" }, { label: asset.asset_code }]}
        title={asset.asset_code}
        description={asset.name}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <ChangeStatusModal
              assetId={assetId}
              currentStatus={asset.status}
              version={asset.version}
              action={changeStatus.bind(null, assetId)}
            />
            <TransferRequestModal
              currentFactoryId={asset.factory?.id}
              version={asset.version}
              locations={formOptions.locations}
              action={requestTransfer.bind(null, assetId)}
            />
            <Link href={`/assets/${assetId}/edit`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <Pencil /> Edit
            </Link>
            <Link href="/assets" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <ArrowLeft /> Back
            </Link>
          </div>
        }
      />

      <Card className={cn("mb-6 flex flex-wrap items-center gap-x-8 gap-y-3 border-l-4 p-4", CRITICALITY_BORDER[asset.criticality] ?? "border-l-border-strong")}>
        <SummaryItem label="Status">
          <StatusBadge status={asset.status} />
        </SummaryItem>
        <SummaryItem label="Criticality">
          <Badge variant={CRITICALITY_TONE[asset.criticality] ?? "neutral"}>{formatStatus(asset.criticality)}</Badge>
        </SummaryItem>
        <SummaryItem label="Factory">
          <span className="text-sm text-foreground">{asset.factory?.name ?? "—"}</span>
        </SummaryItem>
        <SummaryItem label="Location">
          <span className="text-sm text-foreground">{asset.location?.name ?? "—"}</span>
        </SummaryItem>
      </Card>

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-12">
        <div className="lg:col-span-8">
          <AssetDetailTabs
            asset={asset}
            transferActions={{
              approve: approveTransfer.bind(null, assetId),
              receive: receiveTransfer.bind(null, assetId),
              reject: rejectTransfer.bind(null, assetId),
            }}
            meteringActions={{ recordReading: recordReading.bind(null, assetId) }}
            costActions={{
              postAction: postCost.bind(null, assetId),
              reverseAction: reverseCost.bind(null, assetId),
            }}
            documentActions={{
              uploadAction: uploadDocument.bind(null, assetId),
              deleteAction: deleteDocument.bind(null, assetId),
            }}
          />
        </div>

        <div className="lg:col-span-4">
          <QrCard assetId={assetId} qr={qr} regenerateAction={regenerateQr.bind(null, assetId)} />
        </div>
      </div>
    </>
  );
}

function SummaryItem({ label, children }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs font-medium text-foreground-muted">{label}</span>
      {children}
    </div>
  );
}
