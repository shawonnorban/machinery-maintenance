import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { ApiClientForm } from "@/components/settings/api-client-form";
import { createApiClient } from "./actions";

export default async function CreateApiClientPage() {
  const options = await apiFetch("/api-clients/form-options");

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Settings" }, { label: "API clients", href: "/settings/api-clients" }, { label: "New credential" }]} title="New machine credential" />

      <Card>
        <CardBody>
          <ApiClientForm modules={options.modules} action={createApiClient} />
        </CardBody>
      </Card>
    </>
  );
}
