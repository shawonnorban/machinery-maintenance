import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { TechniciansTable } from "@/components/technicians/technicians-table";
import { getT } from "@/lib/i18n-server";
import { toggleTechnician, deleteTechnician } from "./actions";

/** Mirrors `TechnicianController::index` (SRS 25). */
export default async function TechniciansPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.search ?? "";
  const factoryId = params.factory_id ?? "";
  const departmentId = params.department_id ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (factoryId) query.set("factory_id", factoryId);
  if (departmentId) query.set("department_id", departmentId);

  const [technicians, options, t] = await Promise.all([
    apiFetch(`/technicians?${query.toString()}`, { includeMeta: true }),
    apiFetch("/technicians/form-options"),
    getT("technician"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("technicians") }]}
        title={t("technicians")}
        description={t("intro")}
        actions={
          <Link href="/technicians/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {t("new_technician")}
          </Link>
        }
      />

      <TechniciansTable
        technicians={technicians.data}
        meta={technicians.meta}
        page={page}
        search={search}
        factoryId={factoryId}
        departmentId={departmentId}
        factories={options.factories}
        departments={options.departments}
        actions={{ toggleTechnician, deleteTechnician }}
      />
    </>
  );
}
