import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { RolesTable } from "@/components/roles/roles-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `RoleController::index`, extended from read-only to full CRUD — the web never had a write form of its own (`ManageRole` was API-only). */
export default async function RolesPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);

  const [roles, t, tn] = await Promise.all([
    apiFetch(`/roles?page=${page}&per_page=25`, { includeMeta: true }),
    getT("user"),
    getT("nav"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("users"), href: "/settings/users" }, { label: t("roles") }]}
        title={t("roles")}
        description={t("roles_intro")}
        actions={
          <Link href="/settings/roles/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {t("new_role")}
          </Link>
        }
      />

      <RolesTable roles={roles.data} meta={roles.meta} />
    </>
  );
}
