import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { RolesTable } from "@/components/roles/roles-table";

/** Mirrors `RoleController::index`, extended from read-only to full CRUD — the web never had a write form of its own (`ManageRole` was API-only). */
export default async function RolesPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);

  const roles = await apiFetch(`/roles?page=${page}&per_page=25`, { includeMeta: true });

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Settings" }, { label: "Users", href: "/settings/users" }, { label: "Roles" }]}
        title="Roles"
        description="What each role can do. Seeded roles are not editable — clone one to customize it."
        actions={
          <Link href="/settings/roles/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> New role
          </Link>
        }
      />

      <RolesTable roles={roles.data} meta={roles.meta} />
    </>
  );
}
