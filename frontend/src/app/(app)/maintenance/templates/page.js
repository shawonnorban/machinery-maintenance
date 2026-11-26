import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { TemplatesTable } from "@/components/maintenance/templates-table";
import { getT } from "@/lib/i18n-server";
import { perPageQuery } from "@/lib/per-page";

/** Mirrors `TemplateController::index` (SRS 12). */
export default async function MaintenanceTemplatesPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);

  const [templates, t] = await Promise.all([
    apiFetch(`/maintenance-templates?page=${page}${await perPageQuery("/maintenance/templates")}`, { includeMeta: true }),
    getT("maintenance"),
  ]);

  return (
    <>
      <ScreenHeader
        url="/maintenance/templates"
        title={t("templates_page_title")}
        description={t("templates_page_description")}
        actions={
          <Link href="/maintenance/templates/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {t("new_checklist")}
          </Link>
        }
      />

      <TemplatesTable templates={templates.data} meta={templates.meta} />
    </>
  );
}
