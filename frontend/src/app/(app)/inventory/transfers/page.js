import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { TransfersTable } from "@/components/inventory/transfers-table";
import { getT } from "@/lib/i18n-server";
import { applyPerPage } from "@/lib/per-page";

/** Mirrors `InventoryTransferApiController::index` — either end of the move: the sending factory sees it leave, the receiving one sees it coming. */
export default async function InventoryTransfersPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  await applyPerPage(query, "/inventory/transfers");
  if (status) query.set("status", status);

  const [transfers, t] = await Promise.all([
    apiFetch(`/inventory-transfers?${query.toString()}`, { includeMeta: true }),
    getT("inventory"),
  ]);

  return (
    <>
      <ScreenHeader
        url="/inventory/transfers"
        title={t("transfers")}
        description={t("transfers_intro")}
      />

      <TransfersTable addHref="/inventory/transfers/create" transfers={transfers.data} meta={transfers.meta} page={page} status={status} />
    </>
  );
}
