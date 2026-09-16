import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { ReportBreakdownForm } from "@/components/breakdowns/report-breakdown-form";
import { getT } from "@/lib/i18n-server";

/** Mirrors `BreakdownController::create()`'s own `formOptions()` — assets, production lines, failure codes, reason codes. */
export default async function ReportBreakdownPage({ searchParams }) {
  const params = await searchParams;
  const [options, t] = await Promise.all([apiFetch("/breakdowns/create-form-options"), getT("breakdown")]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: t("breakdowns"), href: "/breakdowns" }, { label: t("report") }]} title={t("report_breakdown")} />

      <Card>
        <CardBody>
          <ReportBreakdownForm options={options} initialAssetId={params.asset_id ?? ""} />
        </CardBody>
      </Card>
    </>
  );
}
