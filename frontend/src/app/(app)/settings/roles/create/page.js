import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { RoleForm } from "@/components/roles/role-form";
import { getT } from "@/lib/i18n-server";
import { createRole } from "./actions";

export default async function CreateRolePage() {
  const [permissionGroups, roles, t, tc, tn] = await Promise.all([
    apiFetch("/permissions"),
    apiFetch("/roles?per_page=100"),
    getT("user"),
    getT("common"),
    getT("nav"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("roles"), href: "/settings/roles" }, { label: tc("new") }]}
        title={t("new_role")}
      />

      <Card>
        <CardBody>
          <RoleForm permissionGroups={permissionGroups} cloneableRoles={roles.map((r) => ({ id: r.id, name: r.name }))} action={createRole} />
        </CardBody>
      </Card>
    </>
  );
}
