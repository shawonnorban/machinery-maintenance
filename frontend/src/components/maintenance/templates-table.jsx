"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";

/** Mirrors `TemplateController::index` — every checklist this company can see, its own and the platform's shared ones. */
function TemplatesTable({ templates, meta }) {
  const router = useRouter();

  return (
    <DataTable
      columns={[
        {
          key: "name",
          header: "Template",
          render: (t) => (
            <div>
              <Link href={`/maintenance/templates/${t.id}`} className="font-medium text-brand hover:underline">
                {t.name}
              </Link>
              <div className="text-xs text-foreground-muted">{t.code}</div>
            </div>
          ),
        },
        { key: "asset_type", header: "Asset type", render: (t) => t.asset_type ?? "—" },
        { key: "maintenance_type", header: "Maintenance type", render: (t) => t.maintenance_type ?? "—" },
        { key: "versions_count", header: "Versions", align: "right", render: (t) => t.versions_count ?? 0 },
        {
          key: "is_editable",
          header: "Source",
          render: (t) => <StatusBadge status={t.is_editable ? "ACTIVE" : "INACTIVE"} label={t.is_editable ? "Own" : "Platform"} />,
        },
      ]}
      rows={templates}
      rowKey={(t) => t.id}
      emptyTitle="No checklists yet."
      emptyDescription="Write one so a factory floor stops running against a checklist meant for a different machine."
      rowActions={(t) => [{ label: "View", onSelect: () => router.push(`/maintenance/templates/${t.id}`) }]}
      pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
      onPageChange={(nextPage) => router.push(`/maintenance/templates?page=${nextPage}`)}
    />
  );
}

export { TemplatesTable };
