import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { TemplatesTable } from "@/components/maintenance/templates-table";

/** Mirrors `TemplateController::index` (SRS 12). */
export default async function MaintenanceTemplatesPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);

  const templates = await apiFetch(`/maintenance-templates?page=${page}`, { includeMeta: true });

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Maintenance" }, { label: "Templates" }]}
        title="Checklist templates"
        description="What a technician actually works through — versioned, so a published one is frozen the moment it's signed against."
        actions={
          <Link href="/maintenance/templates/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> New checklist
          </Link>
        }
      />

      <TemplatesTable templates={templates.data} meta={templates.meta} />
    </>
  );
}
