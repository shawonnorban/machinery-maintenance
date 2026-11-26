import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { ReportBreakdownForm } from "@/components/breakdowns/report-breakdown-form";
import { getT } from "@/lib/i18n-server";

/** Mirrors `BreakdownController::create()`'s own `formOptions()` — assets, production lines, failure codes, reason codes. */
export default async function ReportBreakdownPage({ searchParams }) {
  const params = await searchParams;
  const [options, t] = await Promise.all([apiFetch("/breakdowns/create-form-options"), getT("breakdown")]);

  return (
    <>
      <ScreenHeader parentUrl="/breakdowns" title={t("report_breakdown")} />

      <ReportBreakdownForm options={options} initialAssetId={params.asset_id ?? ""} />
    </>
  );
}
