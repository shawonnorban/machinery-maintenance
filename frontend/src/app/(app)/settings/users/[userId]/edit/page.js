import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { UserForm } from "@/components/users/user-form";
import { getT } from "@/lib/i18n-server";
import { updateUser } from "../actions";

export default async function EditUserPage({ params }) {
  const { userId } = await params;

  const [user, roles, factories, departments, productionLines, t, tc, tn] = await Promise.all([
    apiFetch(`/users/${userId}`),
    apiFetch("/roles?per_page=100"),
    apiFetch("/factories?per_page=100"),
    apiFetch("/master-data/departments?per_page=200"),
    apiFetch("/master-data/production-lines?per_page=200"),
    getT("user"),
    getT("common"),
    getT("nav"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: tn("settings") },
          { label: t("users"), href: "/settings/users" },
          { label: user.name, href: `/settings/users/${userId}` },
          { label: tc("edit") },
        ]}
        title={t("edit_user_named", { name: user.name })}
      />

      <Card>
        <CardBody>
          <UserForm user={user} roles={roles} factories={factories} departments={departments} productionLines={productionLines} action={updateUser.bind(null, userId)} />
        </CardBody>
      </Card>
    </>
  );
}
