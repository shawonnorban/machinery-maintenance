import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { TransferActions } from "@/components/inventory/transfer-actions";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { approveTransfer, rejectTransfer, dispatchTransfer, receiveTransfer } from "./actions";

/** Mirrors `InventoryTransferApiController::show` — no equivalent page exists on the web (everything happens from its index instead); this is a genuinely new, more discoverable detail view for the same data. */
export default async function TransferDetailPage({ params }) {
  const { transferId } = await params;

  const [transfer, formOptions] = await Promise.all([
    apiFetch(`/inventory-transfers/${transferId}`),
    apiFetch("/inventory-transfers/form-options"),
  ]);

  const destinationBins = formOptions.bins.filter((b) => b.factory_id === transfer.to_factory?.id);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Transfers", href: "/inventory/transfers" }, { label: transfer.transfer_number }]}
        title={transfer.transfer_number}
        description={`${transfer.from_factory?.name ?? "—"} → ${transfer.to_factory?.name ?? "—"}`}
        actions={<StatusBadge status={transfer.status} />}
      />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <div className="flex flex-col gap-4 lg:col-span-8">
          <Card>
            <CardHeader>
              <CardTitle>Items</CardTitle>
            </CardHeader>
            <CardBody className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-xs text-foreground-muted">
                      <th className="px-5 pt-4 pb-2">Part</th>
                      <th className="px-5 pt-4 pb-2 text-right">Requested</th>
                      <th className="px-5 pt-4 pb-2 text-right">Dispatched</th>
                      <th className="px-5 pt-4 pb-2 text-right">Received</th>
                      <th className="px-5 pt-4 pb-2 text-right">Variance</th>
                    </tr>
                  </thead>
                  <tbody>
                    {transfer.items.map((item) => (
                      <tr key={item.id} className="border-t border-border">
                        <td className="px-5 py-2">
                          {item.spare_part?.part_number}
                          <div className="text-xs text-foreground-muted">{item.spare_part?.name}</div>
                        </td>
                        <td className="px-5 py-2 text-right">{item.quantity_requested}</td>
                        <td className="px-5 py-2 text-right">{item.quantity_dispatched ?? "—"}</td>
                        <td className="px-5 py-2 text-right">{item.quantity_received ?? "—"}</td>
                        <td className="px-5 py-2 text-right">{item.quantity_variance ?? "—"}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
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
        </div>

        <div className="flex flex-col gap-4 lg:col-span-4">
          <TransferActions
            transfer={transfer}
            destinationBins={destinationBins}
            actions={{
              approve: approveTransfer.bind(null, transferId),
              reject: rejectTransfer.bind(null, transferId),
              dispatch: dispatchTransfer.bind(null, transferId),
              receive: receiveTransfer.bind(null, transferId),
            }}
          />

          <Card>
            <CardHeader>
              <CardTitle>History</CardTitle>
            </CardHeader>
            <CardBody className="flex flex-col gap-2 text-sm">
              <HistoryRow label="Requested" at={transfer.created_at} />
              <HistoryRow label="Approved" at={transfer.approved_at} />
              <HistoryRow label="Rejected" at={transfer.rejected_at} />
              <HistoryRow label="Dispatched" at={transfer.dispatched_at} />
              <HistoryRow label="Received" at={transfer.received_at} />
            </CardBody>
          </Card>
        </div>
      </div>
    </>
  );
}

function HistoryRow({ label, at }) {
  if (!at) return null;
  return (
    <div className="flex items-center justify-between">
      <span className="text-foreground-muted">{label}</span>
      <span><FormattedDateTime value={at} /></span>
    </div>
  );
}
