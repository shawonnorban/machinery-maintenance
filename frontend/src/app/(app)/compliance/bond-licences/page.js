import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { BondLicencesTable } from "@/components/compliance/bond-licences-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `BondComplianceApiController::index` (Shared Systems spec §3.1). */
export default async function BondLicencesPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [licences, permissions, t] = await Promise.all([
    apiFetch(`/bond-licences?${query.toString()}`, { includeMeta: true }),
    apiFetch("/auth/permissions"),
    getT("compliance"),
  ]);

  const canManage = new Set(permissions.permissions).has("compliance.bond.manage");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("compliance") }, { label: t("bond_licences") }]}
        title={t("bond_licences")}
        description={t("licence_hint")}
        actions={
          canManage ? (
            <Link href="/compliance/bond-licences/create" className={cn(buttonVariants({ size: "sm" }))}>
              <Plus /> {t("new_licence")}
            </Link>
          ) : null
        }
      />

      <BondLicencesTable rows={licences.data} meta={licences.meta} page={page} search={search} status={status} />
    </>
  );
}
