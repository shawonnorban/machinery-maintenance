import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { LandedCostsTable } from "@/components/procurement/landed-costs-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `LandedCostApiController::index` (Procurement spec §11). */
export default async function LandedCostsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [vouchers, permissions, t] = await Promise.all([
    apiFetch(`/landed-costs?${query.toString()}`, { includeMeta: true }),
    apiFetch("/auth/permissions"),
    getT("procurement"),
  ]);

  const canManage = new Set(permissions.permissions).has("procurement.landed_cost.manage");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("landed_costs") }]}
        title={t("landed_costs")}
        description={t("landed_cost_hint")}
        actions={
          canManage ? (
            <Link href="/procurement/landed-costs/create" className={cn(buttonVariants({ size: "sm" }))}>
              <Plus /> {t("new_landed_cost")}
            </Link>
          ) : null
        }
      />

      <LandedCostsTable rows={vouchers.data} meta={vouchers.meta} page={page} search={search} status={status} />
    </>
  );
}
