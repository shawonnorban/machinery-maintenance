import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { IssueReturnForm } from "@/components/inventory/issue-return-form";
import { getT } from "@/lib/i18n-server";
import { issuePart, returnPart } from "./actions";

/** Mirrors `StockController::issue`/`returnStock` — a consumable moving with no work order behind it, distinct from the per-work-order parts tab. */
export default async function IssueReturnPage() {
  const [parts, bins, t] = await Promise.all([
    apiFetch("/spare-parts?per_page=200"),
    apiFetch("/master-data/bins?per_page=200"),
    getT("inventory"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("inventory") }, { label: t("issue_return") }]}
        title={t("issue_return")}
        description={t("issue_return_intro")}
      />

      <Card>
        <CardBody>
          <IssueReturnForm parts={parts} bins={bins} issueAction={issuePart} returnAction={returnPart} />
        </CardBody>
      </Card>
    </>
  );
}
