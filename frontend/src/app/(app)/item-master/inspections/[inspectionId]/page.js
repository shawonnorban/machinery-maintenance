import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { InspectionSheet } from "@/components/item-master/inspection-sheet";
import { getT } from "@/lib/i18n-server";
import {
  recordInspectionRoll,
  submitInspection,
  approveInspection,
  rejectInspection,
  escalateInspection,
  cancelInspection,
  uploadInspectionPhoto,
} from "../actions";

/** Mirrors `FabricInspectionApiController::show` (Inventory spec §4.3). */
export default async function InspectionPage({ params }) {
  const { inspectionId } = await params;

  const [inspection, t, tc] = await Promise.all([
    apiFetch(`/fabric-inspections/${inspectionId}`),
    getT("inspection"),
    getT("common"),
  ]);

  // The defect list is only needed while rolls can still be tallied, and
  // the endpoint is gated on the inspect permission anyway.
  const options = inspection.can_record
    ? await apiFetch("/fabric-inspections/form-options")
    : { defects: [] };

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("fabric_inspection") },
          { label: t("inspections"), href: "/item-master/inspections" },
          { label: inspection.inspection_no },
        ]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {inspection.inspection_no}
            <StatusBadge
              status={inspection.status}
              label={t(`inspection_statuses.${inspection.status}`)}
            />
          </span>
        }
        description={[inspection.item?.name, inspection.style_reference, inspection.colour]
          .filter(Boolean)
          .join(" · ")}
        actions={
          <Link href="/item-master/inspections" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeft /> {tc("back")}
          </Link>
        }
      />

      <InspectionSheet
        inspection={inspection}
        options={options}
        actions={{
          recordRoll: recordInspectionRoll.bind(null, inspectionId),
          submit: submitInspection.bind(null, inspectionId),
          approve: approveInspection.bind(null, inspectionId),
          reject: rejectInspection.bind(null, inspectionId),
          escalate: escalateInspection.bind(null, inspectionId),
          cancel: cancelInspection.bind(null, inspectionId),
          uploadPhoto: uploadInspectionPhoto.bind(null, inspectionId),
        }}
      />
    </>
  );
}
