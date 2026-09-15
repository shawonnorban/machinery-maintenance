import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { NumberingRow } from "@/components/settings/numbering-row";
import { updateFormat, resetFormat } from "./actions";

/**
 * How this company numbers its documents (SRS 52) — mirrors the web
 * `NumberingController::index` (net-new API, `NumberingApiController`,
 * built this pass to back it).
 */
export default async function NumberingPage() {
  const rows = await apiFetch("/numbering");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Settings" }, { label: "Numbering" }]}
        title="Document numbering"
        description="What this company's work orders, breakdowns and transfers are called. A change here renumbers nothing — it takes effect when the counter next restarts."
      />

      <Card>
        <CardBody>
          {rows.map((row) => (
            <NumberingRow
              key={row.document_type}
              row={row}
              updateAction={updateFormat.bind(null, row.document_type)}
              resetAction={resetFormat.bind(null, row.document_type)}
            />
          ))}
        </CardBody>
      </Card>
    </>
  );
}
