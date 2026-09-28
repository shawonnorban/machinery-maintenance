import Link from "next/link";
import { ArrowLeft, Pencil } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { FloorRequisitionDetail } from "@/components/requisition/floor-requisition-detail";
import { getT } from "@/lib/i18n-server";
import {
  submitFloorRequisition,
  cancelFloorRequisition,
  reviewFloorRequisition,
  issueFloorRequisition,
  rejectFloorRequisition,
  closeFloorRequisition,
  returnFloorRequisitionStock,
} from "./actions";

/** Mirrors `FloorRequisitionApiController::show`. */
export default async function FloorRequisitionPage({ params }) {
  const { requisitionId } = await params;

  const [requisition, t, tc] = await Promise.all([apiFetch(`/floor-requisitions/${requisitionId}`), getT("requisition"), getT("common")]);

  // Only the store sees live stock, and only while there is still
  // something to issue — everyone else gets the document alone.
  const availability = requisition.can_issue
    ? (await apiFetch(`/floor-requisitions/${requisitionId}/availability`)).lines
    : null;

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("floor_requisitions"), href: "/requisitions" }, { label: requisition.fr_no }]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {requisition.fr_no}
            <StatusBadge status={requisition.status} label={t(`statuses.${requisition.status}`)} />
          </span>
        }
        description={`${requisition.target_item_department?.name ?? ""} · ${t(`priorities.${requisition.priority}`)}`}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {requisition.can_edit ? (
              <Link href={`/requisitions/${requisitionId}/edit`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
                <Pencil /> {tc("edit")}
              </Link>
            ) : null}
            <Link href="/requisitions" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <ArrowLeft /> {tc("back")}
            </Link>
          </div>
        }
      />

      <FloorRequisitionDetail
        requisition={requisition}
        availability={availability}
        actions={{
          submit: submitFloorRequisition.bind(null, requisitionId),
          cancel: cancelFloorRequisition.bind(null, requisitionId),
          review: reviewFloorRequisition.bind(null, requisitionId),
          issue: issueFloorRequisition.bind(null, requisitionId),
          reject: rejectFloorRequisition.bind(null, requisitionId),
          close: closeFloorRequisition.bind(null, requisitionId),
          returnStock: returnFloorRequisitionStock.bind(null, requisitionId),
        }}
      />
    </>
  );
}
