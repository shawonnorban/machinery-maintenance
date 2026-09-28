import Link from "next/link";
import { ArrowLeft, Pencil } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { RequisitionDetail } from "@/components/procurement/requisition-detail";
import { getT } from "@/lib/i18n-server";
import { submitRequisition, cancelRequisition, approveRequisition, rejectRequisition } from "./actions";

/** Mirrors `PurchaseRequisitionApiController::show` (Procurement spec §5). */
export default async function PurchaseRequisitionPage({ params }) {
  const { requisitionId } = await params;

  const [requisition, t, tc] = await Promise.all([
    apiFetch(`/purchase-requisitions/${requisitionId}`),
    getT("procurement"),
    getT("common"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("purchase_requisitions"), href: "/procurement/requisitions" }, { label: requisition.pr_no }]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {requisition.pr_no}
            <StatusBadge status={requisition.status} label={t(`statuses.${requisition.status}`)} />
          </span>
        }
        description={`${requisition.factory?.name ?? ""} · ${t(`priorities.${requisition.priority}`)}`}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {requisition.can_edit ? (
              <Link href={`/procurement/requisitions/${requisitionId}/edit`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
                <Pencil /> {tc("edit")}
              </Link>
            ) : null}
            <Link href="/procurement/requisitions" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <ArrowLeft /> {tc("back")}
            </Link>
          </div>
        }
      />

      <RequisitionDetail
        requisition={requisition}
        actions={{
          submit: submitRequisition.bind(null, requisitionId),
          cancel: cancelRequisition.bind(null, requisitionId),
          approve: approveRequisition.bind(null, requisitionId),
          reject: rejectRequisition.bind(null, requisitionId),
        }}
      />
    </>
  );
}
