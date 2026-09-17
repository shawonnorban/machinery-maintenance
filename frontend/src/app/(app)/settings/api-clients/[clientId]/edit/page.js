import { notFound } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { ApiClientForm } from "@/components/settings/api-client-form";
import { getT } from "@/lib/i18n-server";
import { updateScopes } from "../actions";

export default async function EditApiClientPage({ params }) {
  const { clientId } = await params;

  const [clients, options, t, tn] = await Promise.all([
    apiFetch("/api-clients"),
    apiFetch("/api-clients/form-options"),
    getT("api"),
    getT("nav"),
  ]);

  const client = clients.find((c) => c.id === clientId);
  if (!client) notFound();

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("api_clients"), href: "/settings/api-clients" }, { label: client.name }]}
        title={client.name}
        description={t("narrowing_hint")}
      />

      <Card>
        <CardBody>
          <ApiClientForm client={client} modules={options.modules} action={updateScopes.bind(null, clientId)} />
        </CardBody>
      </Card>
    </>
  );
}
