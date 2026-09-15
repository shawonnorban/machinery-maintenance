import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { UsersTable } from "@/components/users/users-table";

/** Mirrors `UserApiController::index` — membership of this company, not the account. */
export default async function UsersPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.search ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const users = await apiFetch(`/users?${query.toString()}`, { includeMeta: true });

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Settings" }, { label: "Users" }]}
        title="Users"
        description="Who belongs to this company, and what they may do."
        actions={
          <Link href="/settings/users/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> Invite user
          </Link>
        }
      />

      <UsersTable users={users.data} meta={users.meta} page={page} search={search} status={status} />
    </>
  );
}
