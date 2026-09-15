import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { WebhooksTable } from "@/components/webhooks/webhooks-table";
import { enableEndpoint, pauseEndpoint, deleteEndpoint } from "./actions";

/** Mirrors `WebhookController::index` (API 28, SRS 43). */
export default async function WebhooksPage() {
  const endpoints = await apiFetch("/webhooks");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Settings" }, { label: "Webhooks" }]}
        title="Webhooks"
        description="Outgoing integrations — where this system tells another one what just happened."
        actions={
          <Link href="/settings/webhooks/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> New endpoint
          </Link>
        }
      />

      <WebhooksTable endpoints={endpoints} actions={{ enableEndpoint, pauseEndpoint, deleteEndpoint }} />
    </>
  );
}
