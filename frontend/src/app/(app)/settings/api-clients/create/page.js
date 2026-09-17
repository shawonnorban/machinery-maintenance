import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { ApiClientForm } from "@/components/settings/api-client-form";
import { getT } from "@/lib/i18n-server";
import { createApiClient } from "./actions";

export default async function CreateApiClientPage() {
  const [options, t, tn] = await Promise.all([apiFetch("/api-clients/form-options"), getT("api"), getT("nav")]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("api_clients"), href: "/settings/api-clients" }, { label: t("new_credential") }]}
        title={t("new_machine_credential")}
      />

      <Card>
        <CardBody>
          <ApiClientForm modules={options.modules} action={createApiClient} />
        </CardBody>
      </Card>
    </>
  );
}
