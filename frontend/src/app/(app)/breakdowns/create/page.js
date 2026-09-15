import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { ReportBreakdownForm } from "@/components/breakdowns/report-breakdown-form";

/** Mirrors `BreakdownController::create()`'s own `formOptions()` — assets, production lines, failure codes, reason codes. */
export default async function ReportBreakdownPage({ searchParams }) {
  const params = await searchParams;
  const options = await apiFetch("/breakdowns/create-form-options");

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Breakdowns", href: "/breakdowns" }, { label: "Report" }]} title="Report breakdown" />

      <Card>
        <CardBody>
          <ReportBreakdownForm options={options} initialAssetId={params.asset_id ?? ""} />
        </CardBody>
      </Card>
    </>
  );
}
