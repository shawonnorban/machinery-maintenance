import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { UsersTable } from "@/components/users/users-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `UserApiController::index` — membership of this company, not the account. */
export default async function UsersPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);
  const search = params.search ?? "";
  const status = params.status ?? "";

  const query = new URLSearchParams({ page: String(page) });
  if (search) query.set("search", search);
  if (status) query.set("status", status);

  const [users, t, tn] = await Promise.all([
    apiFetch(`/users?${query.toString()}`, { includeMeta: true }),
    getT("user"),
    getT("nav"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("users") }]}
        title={t("users")}
        description={t("users_intro")}
        actions={
          <Link href="/settings/users/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {t("invite_user")}
          </Link>
        }
      />

      <UsersTable users={users.data} meta={users.meta} page={page} search={search} status={status} />
    </>
  );
}
