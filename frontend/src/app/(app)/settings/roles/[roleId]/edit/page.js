import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { RoleForm } from "@/components/roles/role-form";
import { DeleteRoleButton } from "@/components/roles/delete-role-button";
import { updateRole, deleteRole } from "../actions";

export default async function EditRolePage({ params }) {
  const { roleId } = await params;

  const [role, permissionGroups] = await Promise.all([
    apiFetch(`/roles/${roleId}`),
    apiFetch("/permissions"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Settings" }, { label: "Roles", href: "/settings/roles" }, { label: role.name }]}
        title={role.name}
        actions={!role.is_system ? <DeleteRoleButton roleName={role.name} action={deleteRole.bind(null, roleId)} /> : null}
      />

      {role.is_system ? (
        <Alert variant="info" title="Seeded role" className="mb-4">
          Seeded roles aren&apos;t editable — clone this one from the New role screen to build a customized version.
          What&apos;s shown below is exactly what it grants.
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
