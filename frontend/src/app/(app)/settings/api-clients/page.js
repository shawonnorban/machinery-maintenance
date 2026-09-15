import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { ApiClientsTable } from "@/components/settings/api-clients-table";
import { rotateSecret, revokeClient } from "./actions";

/** Mirrors `ApiClientController::index` (API 4.2, SRS 43). */
export default async function ApiClientsPage() {
  const clients = await apiFetch("/api-clients");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Settings" }, { label: "API clients" }]}
        title="API clients"
        description="A machine's credentials — narrower than the person who created them."
        actions={
          <Link href="/settings/api-clients/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> New credential
          </Link>
        }
      />

      <ApiClientsTable clients={clients} actions={{ rotateSecret, revokeClient }} />
    </>
  );
}
