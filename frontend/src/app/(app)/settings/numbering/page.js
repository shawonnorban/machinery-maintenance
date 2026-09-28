import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { NumberingRow } from "@/components/settings/numbering-row";
import { getT } from "@/lib/i18n-server";
import { updateFormat, resetFormat } from "./actions";

/**
 * How this company numbers its documents (SRS 52) — mirrors the web
 * `NumberingController::index` (net-new API, `NumberingApiController`,
 * built this pass to back it).
 */
export default async function NumberingPage() {
  const [rows, t, tn] = await Promise.all([apiFetch("/numbering"), getT("numbering"), getT("nav")]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("numbering") }]}
        title={t("numbering")}
        description={t("intro")}
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
