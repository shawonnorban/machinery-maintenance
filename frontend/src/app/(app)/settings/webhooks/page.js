import Link from "next/link";
import { Plus } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { WebhooksTable } from "@/components/webhooks/webhooks-table";
import { getT } from "@/lib/i18n-server";
import { enableEndpoint, pauseEndpoint, deleteEndpoint } from "./actions";

/** Mirrors `WebhookController::index` (API 28, SRS 43). */
export default async function WebhooksPage() {
  const [endpoints, t, tn] = await Promise.all([apiFetch("/webhooks"), getT("webhook"), getT("nav")]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("webhooks") }]}
        title={t("webhooks")}
        description={t("page_description")}
        actions={
          <Link href="/settings/webhooks/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {t("new_endpoint")}
          </Link>
        }
      />

      <WebhooksTable endpoints={endpoints} actions={{ enableEndpoint, pauseEndpoint, deleteEndpoint }} />
    </>
  );
}
