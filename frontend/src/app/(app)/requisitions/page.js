import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { FloorRequisitionsTable } from "@/components/requisition/floor-requisitions-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `FloorRequisitionApiController::index` — a requester sees their own, the store sees everyone's. */
export default async function FloorRequisitionsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [requisitions, permissions, t] = await Promise.all([
    apiFetch(`/floor-requisitions?${query.toString()}`, { includeMeta: true }),
    apiFetch("/auth/permissions"),
    getT("requisition"),
  ]);

  const canCreate = new Set(permissions.permissions).has("requisition.floor.create");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("floor_requisitions") }]}
        title={t("floor_requisitions")}
        actions={
          canCreate ? (
            <Link href="/requisitions/create" className={cn(buttonVariants({ size: "sm" }))}>
              <Plus /> {t("new_requisition")}
            </Link>
          ) : null
        }
      />

      <FloorRequisitionsTable rows={requisitions.data} meta={requisitions.meta} page={page} search={search} status={status} />
    </>
  );
}
