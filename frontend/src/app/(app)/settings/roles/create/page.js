import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { RoleForm } from "@/components/roles/role-form";
import { createRole } from "./actions";

export default async function CreateRolePage() {
  const [permissionGroups, roles] = await Promise.all([
    apiFetch("/permissions"),
    apiFetch("/roles?per_page=100"),
  ]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Settings" }, { label: "Roles", href: "/settings/roles" }, { label: "New" }]} title="New role" />

      <Card>
        <CardBody>
          <RoleForm permissionGroups={permissionGroups} cloneableRoles={roles.map((r) => ({ id: r.id, name: r.name }))} action={createRole} />
        </CardBody>
      </Card>
    </>
  );
}
