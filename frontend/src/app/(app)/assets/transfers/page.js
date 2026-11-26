import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { TransfersTable } from "@/components/assets/transfers-table";
import { getT } from "@/lib/i18n-server";
import { approveTransfer, receiveTransfer, rejectTransfer } from "./actions";
import { perPageQuery } from "@/lib/per-page";

/** Mirrors `AssetTransferController::index`. */
export default async function AssetTransfersPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);

  const [transfers, t] = await Promise.all([
    apiFetch(`/transfers?page=${page}${await perPageQuery("/assets/transfers")}`, { includeMeta: true }),
    getT("asset"),
  ]);

  return (
    <>
      <ScreenHeader
        url="/assets/transfers"
        title={t("asset_transfers_title")}
        description={t("asset_transfers_description")}
      />

      <TransfersTable transfers={transfers.data} meta={transfers.meta} actions={{ approveTransfer, receiveTransfer, rejectTransfer }} />
    </>
  );
}
