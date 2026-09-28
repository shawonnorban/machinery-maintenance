import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { PurchaseReturnDetail } from "@/components/procurement/purchase-return-detail";
import { getT } from "@/lib/i18n-server";
import { submitPurchaseReturn, dispatchPurchaseReturn, cancelPurchaseReturn } from "./actions";

/** Mirrors `PurchaseReturnApiController::show` (Procurement spec §12). */
export default async function PurchaseReturnPage({ params }) {
  const { returnId } = await params;

  const [purchaseReturn, t, tc] = await Promise.all([
    apiFetch(`/purchase-returns/${returnId}`),
    getT("procurement"),
    getT("common"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("purchase_returns"), href: "/procurement/returns" },
          { label: purchaseReturn.return_no },
        ]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {purchaseReturn.return_no}
            <StatusBadge status={purchaseReturn.status} label={t(`return_statuses.${purchaseReturn.status}`)} />
          </span>
        }
        description={purchaseReturn.vendor?.name ?? ""}
        actions={
          <Link href="/procurement/returns" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeft /> {tc("back")}
          </Link>
        }
      />

      <PurchaseReturnDetail
        purchaseReturn={purchaseReturn}
        actions={{
          submit: submitPurchaseReturn.bind(null, returnId),
          dispatch: dispatchPurchaseReturn.bind(null, returnId),
          cancel: cancelPurchaseReturn.bind(null, returnId),
        }}
      />
    </>
  );
}
