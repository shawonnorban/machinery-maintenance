import Link from "next/link";
import { ArrowLeft, Pencil } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { UserStatusToggle } from "@/components/users/user-status-toggle";
import { RoleAssignments } from "@/components/users/role-assignments";
import { ResetPasswordButton } from "@/components/users/reset-password-button";
import { RemoveUserButton } from "@/components/users/remove-user-button";
import { getT } from "@/lib/i18n-server";
import { activateUser, deactivateUser, assignRole, removeRoleAssignment, resetPassword, removeUser } from "./actions";

/**
 * Mirrors `user::users.edit`'s core: profile, status, role assignments.
 * Profile fields (name/phone/locale) edit through `/settings/users/
 * [userId]/edit` (`UserApiController::update()`, which also resubmits
 * `roles`/`factory_id` in the same call per `ManageCompanyUser::
 * syncRoles()`'s shape); this page's own role management stays on the
 * granular add/remove endpoints for adding or removing one assignment at
 * a time without touching the rest.
 */
export default async function UserDetailPage({ params }) {
  const { userId } = await params;

  const [user, roles, factories, t, tc, tn] = await Promise.all([
    apiFetch(`/users/${userId}`),
    apiFetch("/roles?per_page=100"),
    apiFetch("/factories?per_page=100"),
    getT("user"),
    getT("common"),
    getT("nav"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("users"), href: "/settings/users" }, { label: user.name }]}
        title={user.name}
        description={user.email}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <UserStatusToggle userId={userId} status={user.status} activate={activateUser} deactivate={deactivateUser} />
            <ResetPasswordButton userId={userId} action={resetPassword} />
            <Link href={`/settings/users/${userId}/edit`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <Pencil /> {tc("edit")}
            </Link>
            <Link href="/settings/users" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <ArrowLeft /> {tc("back")}
            </Link>
            <RemoveUserButton userId={userId} userName={user.name} action={removeUser} />
          </div>
        }
      />

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{t("profile")}</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <Field label={t("status")}>
              <StatusBadge status={user.status} label={user.status === "ACTIVE" ? t("active") : t("suspended")} />
            </Field>
            <Field label={t("phone")}>{user.phone ?? "—"}</Field>
            <Field label={t("language")}>{user.locale === "bn" ? "বাংলা" : "English"}</Field>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t("roles")}</CardTitle>
          </CardHeader>
          <CardBody>
            <RoleAssignments
              userId={userId}
              assignments={user.role_assignments}
              roles={roles}
              factories={factories}
              assign={assignRole}
              remove={removeRoleAssignment}
            />
          </CardBody>
        </Card>
      </div>
    </>
  );
}

function Field({ label, children }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs font-medium text-foreground-muted">{label}</span>
      <span className="text-sm text-foreground">{children}</span>
    </div>
  );
}
