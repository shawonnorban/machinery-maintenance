import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { RateContractDetail } from "@/components/procurement/rate-contract-detail";
import { getT } from "@/lib/i18n-server";
import { activateRateContract, cancelRateContract } from "./actions";

/** Mirrors `RateContractApiController::show` (Procurement spec §14). */
export default async function RateContractPage({ params }) {
  const { contractId } = await params;

  const [contract, t, tc] = await Promise.all([
    apiFetch(`/rate-contracts/${contractId}`),
    getT("procurement"),
    getT("common"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("rate_contracts"), href: "/procurement/rate-contracts" },
          { label: contract.contract_no },
        ]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {contract.contract_no}
            <StatusBadge status={contract.status} label={t(`contract_statuses.${contract.status}`)} />
          </span>
        }
        description={`${contract.vendor?.name ?? ""}${contract.title ? ` · ${contract.title}` : ""}`}
        actions={
          <Link href="/procurement/rate-contracts" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeft /> {tc("back")}
          </Link>
        }
      />

      <RateContractDetail
        contract={contract}
        actions={{
          activate: activateRateContract.bind(null, contractId),
          cancel: cancelRateContract.bind(null, contractId),
        }}
      />
    </>
  );
}
