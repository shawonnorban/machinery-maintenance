import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { FactoriesTable } from "@/components/factories/factories-table";
import { createFactory, updateFactory, toggleFactory, deleteFactory } from "./actions";

/** Mirrors `FactoryController::index` — the unit everything else in the product is scoped to. Create/edit happen in a modal over this list rather than a separate page. */
export default async function FactoriesPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.search ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const factories = await apiFetch(`/factories?${query.toString()}`, { includeMeta: true });

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Settings" }, { label: "Factories" }]}
        title="Factories"
        description="The units everything else in the product is scoped to."
      />

      <FactoriesTable
        factories={factories.data}
        meta={factories.meta}
        page={page}
        search={search}
        status={status}
        actions={{ createFactory, updateFactory, toggleFactory, deleteFactory }}
      />
    </>
  );
}
