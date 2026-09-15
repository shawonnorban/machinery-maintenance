import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { SparePartsTable } from "@/components/inventory/spare-parts-table";

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
  if (search) query.set("search", search);
  if (active) query.set("active", active);

  const parts = await apiFetch(`/spare-parts?${query.toString()}`, { includeMeta: true });

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Spare Parts" }]}
        title="Spare Parts"
        description="The catalogue of every part the stores carry."
        actions={
          <Link href="/inventory/parts/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> New part
          </Link>
        }
      />

      <SparePartsTable
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
