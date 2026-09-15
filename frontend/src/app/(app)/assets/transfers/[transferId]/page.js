import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { TransferActions } from "@/components/assets/transfer-actions";
import { approveTransfer, receiveTransfer, rejectTransfer } from "../actions";

/** Mirrors `AssetTransferApiController::show` — no equivalent page exists on the web (everything happens from the pending-queue index instead); this is a genuinely new, more discoverable detail view for the same data. */
export default async function AssetTransferDetailPage({ params }) {
  const { transferId } = await params;

  const transfer = await apiFetch(`/transfers/${transferId}`);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Assets", href: "/assets" }, { label: "Transfers", href: "/assets/transfers" }, { label: transfer.transfer_number }]}
        title={transfer.transfer_number}
        description={`${transfer.from_factory?.name ?? "—"} → ${transfer.to_factory?.name ?? "—"}`}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <StatusBadge status={transfer.status} />
            <Link href="/assets/transfers" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <ArrowLeft /> Back
            </Link>
          </div>
        }
      />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <div className="flex flex-col gap-4 lg:col-span-8">
          <Card>
            <CardHeader>
              <CardTitle>Machine</CardTitle>
            </CardHeader>
            <CardBody>
              <dl className="flex flex-col gap-2 text-sm">
                <Row label="Machine">
                  <Link href={`/assets/${transfer.asset?.id}`} className="text-brand hover:underline">
                    {transfer.asset?.asset_code}
                  </Link>{" "}
                  — {transfer.asset?.name}
                </Row>
                <Row label="From factory">{transfer.from_factory?.name ?? "—"}</Row>
                <Row label="To factory">{transfer.to_factory?.name ?? "—"}</Row>
                <Row label="To location">{transfer.to_location?.name ?? "—"}</Row>
                <Row label="Reason">{transfer.reason}</Row>
              </dl>
            </CardBody>
          </Card>

          {transfer.notes ? (
            <Card>
              <CardHeader>
                <CardTitle>Notes</CardTitle>
              </CardHeader>
              <CardBody>
                <p className="text-sm text-foreground">{transfer.notes}</p>
              </CardBody>
            </Card>
          ) : null}

          {transfer.status === "REJECTED" && transfer.rejection_reason ? (
            <Card>
              <CardHeader>
                <CardTitle>Rejection reason</CardTitle>
              </CardHeader>
              <CardBody>
                <p className="text-sm text-danger">{transfer.rejection_reason}</p>
              </CardBody>
            </Card>
          ) : null}
        </div>

        <div className="flex flex-col gap-4 lg:col-span-4">
          <TransferActions
            transfer={transfer}
            actions={{
              approve: approveTransfer.bind(null, transferId),
              receive: receiveTransfer.bind(null, transferId),
              reject: rejectTransfer.bind(null, transferId),
            }}
          />

          <Card>
            <CardHeader>
              <CardTitle>History</CardTitle>
            </CardHeader>
            <CardBody className="flex flex-col gap-2 text-sm">
              <HistoryRow label="Requested" at={transfer.requested_at} />
              <HistoryRow label="Approved" at={transfer.approved_at} />
              <HistoryRow label="Rejected" at={transfer.rejected_at} />
              <HistoryRow label="Received" at={transfer.received_at} />
            </CardBody>
          </Card>
        </div>
      </div>
    </>
  );
}

function Row({ label, children }) {
  return (
    <div className="flex justify-between gap-4">
      <dt className="shrink-0 text-foreground-muted">{label}</dt>
      <dd className="text-right text-foreground">{children}</dd>
    </div>
  );
}

function HistoryRow({ label, at }) {
  if (!at) return null;
  return (
    <div className="flex items-center justify-between">
      <span className="text-foreground-muted">{label}</span>
      <span>
        <FormattedDateTime value={at} />
      </span>
    </div>
  );
}
