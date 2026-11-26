import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { SparePartsTable } from "@/components/inventory/spare-parts-table";
import { getT } from "@/lib/i18n-server";
import { applyPerPage } from "@/lib/per-page";

/**
 * The spare-parts catalogue (docs/03-API-Specification.md §13; behaviour
 * mirrors `SparePartApiController::index`, itself the API-side mirror of
 * `SparePartController::index` per ADR-003) — what a part is, not what's
 * on the shelf right now (that's the detail page's Stock tab).
 */
export default async function SparePartsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.search ?? "";
  const active = params.active ?? "";
  const sort = params.sort ?? "part_number";
  const direction = params.direction ?? "asc";

  const query = new URLSearchParams({ page: String(page), sort, direction });
  await applyPerPage(query, "/inventory/parts");
  if (search) query.set("search", search);
  if (active) query.set("active", active);

  const [parts, t] = await Promise.all([
    apiFetch(`/spare-parts?${query.toString()}`, { includeMeta: true }),
    getT("inventory"),
  ]);

  return (
    <>
      <ScreenHeader
        url="/inventory/parts"
        title={t("spare_parts")}
        description={t("spare_parts_page_description")}
      />

      <SparePartsTable
        addHref="/inventory/parts/create"
        parts={parts.data}
        meta={parts.meta}
        page={page}
        search={search}
        active={active}
        sort={sort}
        direction={direction}
      />
    </>
  );
}
