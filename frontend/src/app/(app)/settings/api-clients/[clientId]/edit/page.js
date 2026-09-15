import { notFound } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { ApiClientForm } from "@/components/settings/api-client-form";
import { updateScopes } from "../actions";

export default async function EditApiClientPage({ params }) {
  const { clientId } = await params;

  const [clients, options] = await Promise.all([
    apiFetch("/api-clients"),
    apiFetch("/api-clients/form-options"),
  ]);

  const client = clients.find((c) => c.id === clientId);
  if (!client) notFound();

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Settings" }, { label: "API clients", href: "/settings/api-clients" }, { label: client.name }]}
        title={client.name}
        description="Narrowing removes the wider access from every token already minted."
      />

      <Card>
        <CardBody>
          <ApiClientForm client={client} modules={options.modules} action={updateScopes.bind(null, clientId)} />
        </CardBody>
      </Card>
    </>
  );
}
