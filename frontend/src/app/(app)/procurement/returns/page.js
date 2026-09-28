import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { PurchaseReturnsTable } from "@/components/procurement/purchase-returns-table";
import { getT } from "@/lib/i18n-server";

/**
 * Mirrors `PurchaseReturnApiController::index` (Procurement spec §12).
 * A return is raised against the receipt it came in on, so there is no
 * "new" button here — it starts on the goods receipt.
 */
export default async function PurchaseReturnsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [returns, t] = await Promise.all([
    apiFetch(`/purchase-returns?${query.toString()}`, { includeMeta: true }),
    getT("procurement"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("purchase_returns") }]}
        title={t("purchase_returns")}
        description={t("return_hint")}
      />

      <PurchaseReturnsTable rows={returns.data} meta={returns.meta} page={page} search={search} status={status} />
    </>
  );
}
