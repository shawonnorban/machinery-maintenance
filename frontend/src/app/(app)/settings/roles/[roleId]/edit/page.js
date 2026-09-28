import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { RoleForm } from "@/components/roles/role-form";
import { DeleteRoleButton } from "@/components/roles/delete-role-button";
import { getT } from "@/lib/i18n-server";
import { updateRole, deleteRole } from "../actions";

export default async function EditRolePage({ params }) {
  const { roleId } = await params;

  const [role, permissionGroups, t, tn] = await Promise.all([
    apiFetch(`/roles/${roleId}`),
    apiFetch("/permissions"),
    getT("user"),
    getT("nav"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("roles"), href: "/settings/roles" }, { label: role.name }]}
        title={role.name}
        actions={!role.is_system ? <DeleteRoleButton roleName={role.name} action={deleteRole.bind(null, roleId)} /> : null}
      />

      {role.is_system ? (
        <Alert variant="info" title={t("seeded_role")} className="mb-4">
          {t("seeded_role_alert")}
        </Alert>
      ) : null}

      <Card>
        <CardBody>
          <RoleForm role={role} permissionGroups={permissionGroups} action={updateRole.bind(null, roleId)} readOnly={role.is_system} />
        </CardBody>
      </Card>
    </>
  );
}
