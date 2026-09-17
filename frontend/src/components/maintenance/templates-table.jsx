"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { useT } from "@/lib/i18n";

/** Mirrors `TemplateController::index` — every checklist this company can see, its own and the platform's shared ones. */
function TemplatesTable({ templates, meta }) {
  const t = useT("maintenance");
  const tc = useT("common");
  const router = useRouter();

  return (
    <DataTable
      columns={[
        {
          key: "name",
          header: t("template"),
          render: (row) => (
            <div>
              <Link href={`/maintenance/templates/${row.id}`} className="font-medium text-brand hover:underline">
                {row.name}
              </Link>
              <div className="text-xs text-foreground-muted">{row.code}</div>
            </div>
          ),
        },
        { key: "asset_type", header: t("asset_type"), render: (row) => row.asset_type ?? "—" },
        { key: "maintenance_type", header: t("maintenance_type"), render: (row) => row.maintenance_type ?? "—" },
        { key: "versions_count", header: t("versions"), align: "right", render: (row) => row.versions_count ?? 0 },
        {
          key: "is_editable",
          header: t("source"),
          render: (row) => <StatusBadge status={row.is_editable ? "ACTIVE" : "INACTIVE"} label={row.is_editable ? t("own") : t("platform")} />,
        },
      ]}
      rows={templates}
      rowKey={(row) => row.id}
      emptyTitle={t("no_templates")}
      emptyDescription={t("no_checklists_hint")}
      rowActions={(row) => [{ label: tc("view"), onSelect: () => router.push(`/maintenance/templates/${row.id}`) }]}
      pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
      onPageChange={(nextPage) => router.push(`/maintenance/templates?page=${nextPage}`)}
    />
  );
}

export { TemplatesTable };
