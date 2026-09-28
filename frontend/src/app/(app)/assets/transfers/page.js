import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { TransfersTable } from "@/components/assets/transfers-table";
import { getT } from "@/lib/i18n-server";
import { approveTransfer, receiveTransfer, rejectTransfer } from "./actions";

/** Mirrors `AssetTransferController::index`. */
export default async function AssetTransfersPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);

  const [transfers, t] = await Promise.all([
    apiFetch(`/transfers?page=${page}`, { includeMeta: true }),
    getT("asset"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("assets") }, { label: t("transfers") }]}
        title={t("asset_transfers_title")}
        description={t("asset_transfers_description")}
      />

      <TransfersTable transfers={transfers.data} meta={transfers.meta} actions={{ approveTransfer, receiveTransfer, rejectTransfer }} />
    </>
  );
}
