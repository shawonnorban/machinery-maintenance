import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { LabelSheet } from "@/components/assets/label-sheet";
import { LabelFilters } from "@/components/assets/label-filters";
import { getT } from "@/lib/i18n-server";

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
  const [result, options, t] = await Promise.all([
    hasCriteria ? apiFetch(`/assets/labels?${query.toString()}`) : null,
    apiFetch("/assets/form-options"),
    getT("asset"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("assets"), href: "/assets" }, { label: t("print_labels") }]}
        title={t("print_labels")}
        description={t("print_labels_description")}
      />

      <LabelFilters factories={options.factories} factoryId={factoryId} status={status} hasIds={ids.length > 0} />

      {result ? <LabelSheet labels={result.labels} truncated={result.truncated} /> : null}
    </>
  );
}
