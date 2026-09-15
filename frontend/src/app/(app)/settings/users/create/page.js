import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { UserForm } from "@/components/users/user-form";
import { createUser } from "./actions";

export default async function CreateUserPage() {
  const [roles, factories, departments, productionLines] = await Promise.all([
    apiFetch("/roles?per_page=100"),
    apiFetch("/factories?per_page=100"),
    apiFetch("/master-data/departments?per_page=200"),
    apiFetch("/master-data/production-lines?per_page=200"),
  ]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Settings" }, { label: "Users", href: "/settings/users" }, { label: "Invite" }]} title="Invite user" />

      <Card>
        <CardBody>
          <UserForm roles={roles} factories={factories} departments={departments} productionLines={productionLines} action={createUser} />
        </CardBody>
      </Card>
    </>
  );
}
