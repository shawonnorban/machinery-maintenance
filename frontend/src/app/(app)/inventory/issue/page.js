import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { IssueReturnForm } from "@/components/inventory/issue-return-form";
import { issuePart, returnPart } from "./actions";

/** Mirrors `StockController::issue`/`returnStock` — a consumable moving with no work order behind it, distinct from the per-work-order parts tab. */
export default async function IssueReturnPage() {
  const [parts, bins] = await Promise.all([
    apiFetch("/spare-parts?per_page=200"),
    apiFetch("/master-data/bins?per_page=200"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Inventory" }, { label: "Issue / Return" }]}
        title="Issue / Return"
        description="A consumable leaving or coming back to the store with no work order behind it."
      />

      <Card>
        <CardBody>
          <IssueReturnForm parts={parts} bins={bins} issueAction={issuePart} returnAction={returnPart} />
        </CardBody>
      </Card>
    </>
  );
}
