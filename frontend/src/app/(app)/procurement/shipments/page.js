import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { ShipmentsBoard } from "@/components/procurement/shipments-board";
import { getT } from "@/lib/i18n-server";

/** Mirrors `ImportDocumentApiController::shipments` (Procurement spec §10). */
export default async function ShipmentsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";
  const mode = params.mode ?? "";
  // Defaults to what is still coming: a board of everything ever shipped
  // is a history, not a morning's work.
  const inFlight = params.in_flight === undefined ? true : params.in_flight === "1";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);
  if (mode) query.set("mode", mode);
  if (inFlight) query.set("in_flight", "1");

  const [shipments, t] = await Promise.all([
    apiFetch(`/shipments?${query.toString()}`, { includeMeta: true }),
    getT("procurement"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("shipments") }]}
        title={t("shipments")}
        description={t("eta_hint")}
      />

      <ShipmentsBoard
        rows={shipments.data}
        meta={shipments.meta}
        page={page}
        search={search}
        status={status}
        mode={mode}
        inFlight={inFlight}
      />
    </>
  );
}
