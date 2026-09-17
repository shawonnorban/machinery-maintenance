import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { TransfersTable } from "@/components/inventory/transfers-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `InventoryTransferApiController::index` — either end of the move: the sending factory sees it leave, the receiving one sees it coming. */
export default async function InventoryTransfersPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (status) query.set("status", status);

  const [transfers, t] = await Promise.all([
    apiFetch(`/inventory-transfers?${query.toString()}`, { includeMeta: true }),
    getT("inventory"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("transfers") }]}
        title={t("transfers")}
        description={t("transfers_intro")}
        actions={
          <Link href="/inventory/transfers/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {t("new_transfer")}
          </Link>
        }
      />

      <TransfersTable transfers={transfers.data} meta={transfers.meta} page={page} status={status} />
    </>
  );
}
