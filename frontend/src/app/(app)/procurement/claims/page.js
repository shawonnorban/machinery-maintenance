import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { SupplierClaimsPanel } from "@/components/procurement/supplier-claims-panel";
import { getT } from "@/lib/i18n-server";
import {
  createSupplierClaim,
  acceptSupplierClaim,
  settleSupplierClaim,
  rejectSupplierClaim,
  withdrawSupplierClaim,
} from "./actions";

/** Mirrors `SupplierClaimApiController::index` (Procurement spec §12). */
export default async function SupplierClaimsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [claims, options, permissions, t] = await Promise.all([
    apiFetch(`/supplier-claims?${query.toString()}`, { includeMeta: true }),
    apiFetch("/goods-receipts/form-options"),
    apiFetch("/auth/permissions"),
    getT("procurement"),
  ]);

  const canManage = new Set(permissions.permissions).has("procurement.claim.manage");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("supplier_claims") }]}
        title={t("supplier_claims")}
        description={t("claim_hint")}
      />

      <SupplierClaimsPanel
        rows={claims.data}
        meta={claims.meta}
        page={page}
        search={search}
        status={status}
        factories={options.factories ?? []}
        vendors={options.vendors ?? []}
        canManage={canManage}
        actions={{
          create: createSupplierClaim,
          accept: acceptSupplierClaim,
          settle: settleSupplierClaim,
          reject: rejectSupplierClaim,
          withdraw: withdrawSupplierClaim,
        }}
      />
    </>
  );
}
