import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { CuttingProgramsTable } from "@/components/production/cutting-programs-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `CuttingProgramApiController::index` (Master Data spec §6). */
export default async function CuttingProgramsPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.q ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [programs, t] = await Promise.all([
    apiFetch(`/cutting-programs?${query.toString()}`, { includeMeta: true }),
    getT("production"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("production") }, { label: t("cutting_programs") }]}
        title={t("cutting_programs")}
      />

      <CuttingProgramsTable rows={programs.data} meta={programs.meta} page={page} search={search} status={status} />
    </>
  );
}
