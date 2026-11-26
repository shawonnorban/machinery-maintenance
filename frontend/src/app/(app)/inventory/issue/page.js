import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { Card, CardBody } from "@/components/ui/card";
import { IssueReturnForm } from "@/components/inventory/issue-return-form";
import { IssueReturnHistory } from "@/components/inventory/issue-return-history";
import { getT } from "@/lib/i18n-server";
import { issuePart, returnPart } from "./actions";
import { applyPerPage } from "@/lib/per-page";

/** Mirrors `StockController::issue`/`returnStock` — a consumable moving with no work order behind it, distinct from the per-work-order parts tab. */
export default async function IssueReturnPage({ searchParams }) {
  const params = await searchParams;
  const page = Math.max(1, Number(params.page ?? 1) || 1);
  const type = ["ISSUE", "RETURN"].includes(params.type) ? params.type : "";
  const history = new URLSearchParams({ page: String(page), per_page: "15" });
  await applyPerPage(history, "/inventory/issue");
  if (type) history.set("type", type);

  const [parts, bins, log, t] = await Promise.all([
    apiFetch("/spare-parts?per_page=200"),
    apiFetch("/master-data/bins?per_page=200"),
    apiFetch(`/inventory-transactions/issue-return?${history.toString()}`, { includeMeta: true }),
    getT("inventory"),
  ]);

  return (
    <>
      <ScreenHeader
        url="/inventory/issue"
        title={t("issue_return")}
        description={t("issue_return_intro")}
      />

      <Card>
        <CardBody>
          <IssueReturnForm parts={parts} bins={bins} issueAction={issuePart} returnAction={returnPart} />
        </CardBody>
      </Card>

      <div className="mt-6">
        <IssueReturnHistory rows={log.data} meta={log.meta} type={type} />
      </div>
    </>
  );
}
