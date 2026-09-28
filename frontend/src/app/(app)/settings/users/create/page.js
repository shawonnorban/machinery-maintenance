import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { UserForm } from "@/components/users/user-form";
import { getT } from "@/lib/i18n-server";
import { createUser } from "./actions";

export default async function CreateUserPage() {
  const [roles, factories, departments, productionLines, t, tn] = await Promise.all([
    apiFetch("/roles?per_page=100"),
    apiFetch("/factories?per_page=100"),
    apiFetch("/master-data/departments?per_page=200"),
    apiFetch("/master-data/production-lines?per_page=200"),
    getT("user"),
    getT("nav"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("users"), href: "/settings/users" }, { label: t("invite") }]}
        title={t("invite_user")}
      />

      <Card>
        <CardBody>
          <UserForm roles={roles} factories={factories} departments={departments} productionLines={productionLines} action={createUser} />
        </CardBody>
      </Card>
    </>
  );
}
