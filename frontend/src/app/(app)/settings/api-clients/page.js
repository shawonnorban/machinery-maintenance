import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { ApiClientsTable } from "@/components/settings/api-clients-table";
import { getT } from "@/lib/i18n-server";
import { rotateSecret, revokeClient } from "./actions";

/** Mirrors `ApiClientController::index` (API 4.2, SRS 43). */
export default async function ApiClientsPage() {
  const [clients, t, tn] = await Promise.all([apiFetch("/api-clients"), getT("api"), getT("nav")]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("api_clients") }]}
        title={t("api_clients")}
        description={t("api_clients_intro")}
        actions={
          <Link href="/settings/api-clients/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {t("new_credential")}
          </Link>
        }
      />

      <ApiClientsTable clients={clients} actions={{ rotateSecret, revokeClient }} />
    </>
  );
}
