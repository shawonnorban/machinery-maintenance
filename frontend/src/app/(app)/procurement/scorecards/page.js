import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { SupplierScorecardsPanel } from "@/components/procurement/supplier-scorecards-panel";
import { getT } from "@/lib/i18n-server";
import { calculateScorecards } from "./actions";

/** Mirrors `SupplierScorecardApiController::index` (Procurement spec §15). */
export default async function SupplierScorecardsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);

  const [cards, permissions, t] = await Promise.all([
    apiFetch(`/supplier-scorecards?${query.toString()}`, { includeMeta: true }),
    apiFetch("/auth/permissions"),
    getT("procurement"),
  ]);

  const canCalculate = new Set(permissions.permissions).has("procurement.scorecard.calculate");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("supplier_scorecards") }]}
        title={t("supplier_scorecards")}
        description={t("scorecard_hint")}
      />

      <SupplierScorecardsPanel
        rows={cards.data}
        meta={cards.meta}
        page={page}
        search={search}
        canCalculate={canCalculate}
        onCalculate={calculateScorecards}
      />
    </>
  );
}
