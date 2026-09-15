import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { TransfersTable } from "@/components/assets/transfers-table";
import { approveTransfer, receiveTransfer, rejectTransfer } from "./actions";

/** Mirrors `AssetTransferController::index`. */
export default async function AssetTransfersPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);

  const transfers = await apiFetch(`/transfers?page=${page}`, { includeMeta: true });

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Assets" }, { label: "Transfers" }]}
        title="Asset transfers"
        description="Every machine move waiting on an approval or a receipt, across every factory."
      />

      <TransfersTable transfers={transfers.data} meta={transfers.meta} actions={{ approveTransfer, receiveTransfer, rejectTransfer }} />
    </>
  );
}
