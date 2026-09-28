import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { BondLicenceDetail } from "@/components/compliance/bond-licence-detail";
import { getT } from "@/lib/i18n-server";
import { setBondLicenceStatus } from "./actions";

/** Mirrors `BondComplianceApiController::show` (Shared Systems spec §3.1). */
export default async function BondLicencePage({ params }) {
  const { licenceId } = await params;

  const [licence, t, tc] = await Promise.all([
    apiFetch(`/bond-licences/${licenceId}`),
    getT("compliance"),
    getT("common"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("compliance") },
          { label: t("bond_licences"), href: "/compliance/bond-licences" },
          { label: licence.licence_no },
        ]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {licence.licence_no}
            <StatusBadge status={licence.status} label={t(`licence_statuses.${licence.status}`)} />
          </span>
        }
        description={`${licence.issuing_office}${licence.factory ? ` · ${licence.factory.name}` : ""}`}
        actions={
          <Link href="/compliance/bond-licences" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeft /> {tc("back")}
          </Link>
        }
      />

      <BondLicenceDetail
        licence={licence}
        actions={{ setStatus: setBondLicenceStatus.bind(null, licenceId) }}
      />
    </>
  );
}
