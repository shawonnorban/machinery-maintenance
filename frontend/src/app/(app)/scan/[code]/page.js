import Link from "next/link";
import { QrCode, AlertTriangle, Wrench, Gauge, ArrowLeftRight, Eye } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { StatusBadge, formatStatus } from "@/components/ui/status-badge";
import { Badge } from "@/components/ui/badge";
import { EmptyState } from "@/components/ui/empty-state";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const CRITICALITY_TONE = { CRITICAL: "danger", HIGH: "warning", MEDIUM: "info", LOW: "neutral" };

const ACTION_ICON = { view: Eye, report_breakdown: AlertTriangle, log_meter: Gauge, transfer: ArrowLeftRight };
const ACTION_VARIANT = { primary: "primary", danger: "danger", secondary: "outline" };

/**
 * Where a scanned machine QR code lands (SRS 8, Data Dictionary 5.2) —
 * replaces Blade's `scan/asset.blade.php`. One card, one stack of large
 * buttons: this is used one-handed, standing next to the machine that was
 * just scanned, not browsed. Every action already carries this asset's id
 * (`ScanApiController::asset()`'s own `actions` payload) so the technician
 * never has to pick the machine again from a list.
 */
export default async function ScanAssetPage({ params }) {
  const { code } = await params;

  let data;
  try {
    data = await apiFetch(`/scan/assets/${code}`);
  } catch (error) {
    if (error instanceof ApiError && (error.status === 404 || error.status === 403)) {
      return <NotFound />;
    }
    throw error;
  }

  const { asset, actions } = data;

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Scan" }]} title="Scanned machine" />

      <div className="mx-auto flex max-w-md flex-col gap-5">
        <Card className="border-l-4 border-l-brand">
          <CardBody className="flex flex-col gap-3">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-xs font-medium text-foreground-muted">{asset.asset_code}</p>
                <h2 className="text-lg font-semibold text-foreground">{asset.name}</h2>
              </div>
              <StatusBadge status={asset.status} />
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <Badge variant={CRITICALITY_TONE[asset.criticality] ?? "neutral"}>{formatStatus(asset.criticality)}</Badge>
              {asset.type ? <span className="text-sm text-foreground-muted">{asset.type}</span> : null}
            </div>

            <p className="text-sm text-foreground-muted">{asset.location ?? asset.factory}</p>
          </CardBody>
        </Card>

        <div>
          <p className="mb-3 text-sm font-medium text-foreground-muted">What would you like to do?</p>

          {actions.length === 0 ? (
            <EmptyState
              icon={<QrCode />}
              title="Nothing you can do here"
              description="You don't hold a permission this machine's actions need."
            />
          ) : (
            <div className="flex flex-col gap-3">
              {actions.map((action) => {
                const Icon = ACTION_ICON[action.key] ?? Wrench;
                return (
                  <Link
                    key={action.key}
                    href={action.route}
                    className={cn(
                      buttonVariants({ variant: ACTION_VARIANT[action.tone] ?? "outline", size: "lg" }),
                      "justify-start",
                    )}
                  >
                    <Icon /> {action.label}
                  </Link>
                );
              })}
            </div>
          )}
        </div>
      </div>
    </>
  );
}

function NotFound() {
  return (
    <div className="mx-auto max-w-md">
      <Card>
        <CardBody>
          <EmptyState
            icon={<QrCode />}
            title="This code doesn't match any machine you can reach"
            description="It may belong to a different company, or the label may be damaged. Ask your supervisor if this seems wrong."
          />
        </CardBody>
      </Card>
    </div>
  );
}
