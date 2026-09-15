import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { LabelSheet } from "@/components/assets/label-sheet";
import { LabelFilters } from "@/components/assets/label-filters";

/** Mirrors `AssetLabelController::index` — explicit `ids[]`, else factory/status filters, capped at 200. */
export default async function AssetLabelsPage({ searchParams }) {
  const params = await searchParams;
  const rawIds = params["ids[]"] ?? params.ids;
  const ids = rawIds ? (Array.isArray(rawIds) ? rawIds : [rawIds]) : [];
  const factoryId = params.factory_id ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams();
  for (const id of ids) query.append("ids[]", id);
  if (factoryId) query.set("factory_id", factoryId);
  if (status) query.set("status", status);

  const hasCriteria = ids.length > 0 || factoryId || status;

  // /assets/form-options rather than /factories directly — the latter
  // needs settings.factory.manage, a tier above asset.asset.view_any
  // (the permission this whole screen and its own bulk endpoint are
  // gated on), the same over-privileged-dropdown mistake already found
  // and fixed for Reports/Technicians/Maintenance Plans this session.
  const [result, options] = await Promise.all([
    hasCriteria ? apiFetch(`/assets/labels?${query.toString()}`) : null,
    apiFetch("/assets/form-options"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Assets", href: "/assets" }, { label: "Print Labels" }]}
        title="Print labels"
        description="A QR label per machine — the code, the asset code and its name, nothing else."
      />

      <LabelFilters factories={options.factories} factoryId={factoryId} status={status} hasIds={ids.length > 0} />

      {result ? <LabelSheet labels={result.labels} truncated={result.truncated} /> : null}
    </>
  );
}
