import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { RequisitionsTable } from "@/components/procurement/requisitions-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `PurchaseRequisitionApiController::index` (Procurement spec §5). */
export default async function PurchaseRequisitionsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [requisitions, permissions, t] = await Promise.all([
    apiFetch(`/purchase-requisitions?${query.toString()}`, { includeMeta: true }),
    apiFetch("/auth/permissions"),
    getT("procurement"),
  ]);

  const canCreate = new Set(permissions.permissions).has("procurement.requisition.create");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("purchase_requisitions") }]}
        title={t("purchase_requisitions")}
        actions={
          canCreate ? (
            <Link href="/procurement/requisitions/create" className={cn(buttonVariants({ size: "sm" }))}>
              <Plus /> {t("new_requisition")}
            </Link>
          ) : null
        }
      />

      <RequisitionsTable rows={requisitions.data} meta={requisitions.meta} page={page} search={search} status={status} />
    </>
  );
}
