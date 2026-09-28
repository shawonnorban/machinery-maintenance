import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { RateContractsTable } from "@/components/procurement/rate-contracts-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `RateContractApiController::index` (Procurement spec §14). */
export default async function RateContractsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [contracts, permissions, t] = await Promise.all([
    apiFetch(`/rate-contracts?${query.toString()}`, { includeMeta: true }),
    apiFetch("/auth/permissions"),
    getT("procurement"),
  ]);

  const canManage = new Set(permissions.permissions).has("procurement.contract.manage");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("rate_contracts") }]}
        title={t("rate_contracts")}
        description={t("contract_hint")}
        actions={
          canManage ? (
            <Link href="/procurement/rate-contracts/create" className={cn(buttonVariants({ size: "sm" }))}>
              <Plus /> {t("new_contract")}
            </Link>
          ) : null
        }
      />

      <RateContractsTable rows={contracts.data} meta={contracts.meta} page={page} search={search} status={status} />
    </>
  );
}
