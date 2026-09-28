import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { SupplierAdvancesPanel } from "@/components/procurement/supplier-advances-panel";
import { getT } from "@/lib/i18n-server";
import {
  createSupplierAdvance,
  adjustSupplierAdvance,
  refundSupplierAdvance,
  cancelSupplierAdvance,
} from "./actions";

/** Mirrors `SupplierAdvanceApiController::index` (Procurement spec §13). */
export default async function SupplierAdvancesPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [advances, options, permissions, t] = await Promise.all([
    apiFetch(`/supplier-advances?${query.toString()}`, { includeMeta: true }),
    apiFetch("/goods-receipts/form-options"),
    apiFetch("/auth/permissions"),
    getT("procurement"),
  ]);

  const canManage = new Set(permissions.permissions).has("procurement.advance.manage");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("supplier_advances") }]}
        title={t("supplier_advances")}
        description={t("advance_hint")}
      />

      <SupplierAdvancesPanel
        rows={advances.data}
        meta={advances.meta}
        page={page}
        search={search}
        status={status}
        factories={options.factories ?? []}
        vendors={options.vendors ?? []}
        canManage={canManage}
        actions={{
          create: createSupplierAdvance,
          adjust: adjustSupplierAdvance,
          refund: refundSupplierAdvance,
          cancel: cancelSupplierAdvance,
        }}
      />
    </>
  );
}
