"use client";

import { useRouter } from "next/navigation";
import Link from "next/link";
import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { useT } from "@/lib/i18n";

/**
 * Column `render` functions can't cross the Server→Client boundary, so
 * this client component owns the column definitions; the server page
 * just hands over the current page of `roles` as plain data.
 */
function RolesTable({ roles, meta }) {
  const t = useT("user");
  const tc = useT("common");
  const router = useRouter();

  function onPageChange(page) {
    router.push(`/settings/roles?page=${page}`);
  }

  return (
    <DataTable
      columns={[
        {
          key: "name",
          header: t("role"),
          render: (role) => (
            <div>
              <Link href={`/settings/roles/${role.id}/edit`} className="font-medium text-brand hover:underline">
                {role.name}
              </Link>
              <div className="text-xs text-foreground-muted">{role.code}</div>
            </div>
          ),
        },
        {
          key: "scope",
          header: t("scope"),
          render: (role) => (
            <Badge variant={role.scope === "FACTORY" ? "info" : "neutral"}>{role.scope === "FACTORY" ? t("factory_scope") : t("company_scope")}</Badge>
          ),
        },
        { key: "permissions_count", header: t("permissions"), align: "right" },
        { key: "holders_count", header: t("holders"), align: "right" },
        {
          key: "status",
          header: "",
          render: (role) => (role.is_system ? <Badge variant="neutral">{t("seeded_clone_hint")}</Badge> : null),
        },
      ]}
      rows={roles}
      rowKey={(role) => role.id}
      emptyTitle={t("no_roles_found")}
      rowActions={(role) => [
        { label: role.is_system ? tc("view") : tc("edit"), onSelect: () => router.push(`/settings/roles/${role.id}/edit`) },
      ]}
      pagination={{ page: meta.current_page, perPage: meta.per_page, total: meta.total }}
      onPageChange={onPageChange}
    />
  );
}

export { RolesTable };
