import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { CuttingProgramDetail } from "@/components/production/cutting-program-detail";
import { getT } from "@/lib/i18n-server";
import { issueMaterial, raiseRequirementRequisition } from "./actions";

/**
 * One cutting programme: `MaterialRequirementApiController::forProgram`
 * (Procurement spec §4) and `MaterialIssueApiController::index`
 * (Inventory spec §9) side by side.
 */
export default async function CuttingProgramPage({ params }) {
  const { programId } = await params;

  const [program, requirement, issued, permissions, t] = await Promise.all([
    apiFetch(`/cutting-programs/${programId}`),
    apiFetch(`/cutting-programs/${programId}/material-requirement`),
    apiFetch(`/cutting-programs/${programId}/material-issues`),
    apiFetch("/auth/permissions"),
    getT("production"),
  ]);

  const tc = await getT("common");
  const held = new Set(permissions.permissions);

  // Where each BOM item could be issued from. Read per item rather than
  // through one endpoint: a bill of materials is a handful of lines, and
  // the item's own stock block already answers the question.
  const sources = Object.fromEntries(
    await Promise.all(
      issued.variance.map(async (line) => [line.item_id, await sourcesFor(line.item_id)]),
    ),
  );

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("production") },
          { label: t("cutting_programs"), href: "/production/cutting-programs" },
          { label: program.program_no },
        ]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {program.program_no}
            <StatusBadge status={program.status} label={t(`statuses.${program.status}`)} />
          </span>
        }
        description={`${program.style?.style_no ?? ""} · ${program.style?.order?.buyer ?? ""}`}
        actions={
          <Link href="/production/cutting-programs" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeft /> {tc("back")}
          </Link>
        }
      />

      <CuttingProgramDetail
        program={program}
        requirement={requirement}
        issues={issued.issues}
        variance={issued.variance}
        canIssue={issued.can_issue}
        canRaisePr={requirement.can_raise_requisition && held.has("procurement.requisition.create")}
        sources={sources}
        actions={{
          issue: issueMaterial.bind(null, programId),
          raiseRequisition: raiseRequirementRequisition.bind(null, programId),
        }}
      />
    </>
  );
}

/**
 * Free stock per bin for a quantity item, or the available rolls for a
 * roll-tracked one. A ROLL item keeps no balance row — the roll is the
 * stock (Inventory spec §3).
 */
async function sourcesFor(itemId) {
  const item = await apiFetch(`/items/${itemId}`);

  if (item.tracking_mode === "ROLL") {
    const { data } = await apiFetch(`/fabric-rolls?item_id=${itemId}&status=AVAILABLE&per_page=50`, { includeMeta: true });

    return data.map((roll) => ({
      fabric_roll_id: roll.id,
      roll_no: roll.roll_no,
      bin_id: roll.bin?.id ?? null,
      bin: roll.bin ?? null,
      available: roll.net_length ?? roll.net_weight ?? "0",
    }));
  }

  return (item.stock?.by_bin ?? [])
    .map((row) => ({
      fabric_roll_id: null,
      roll_no: null,
      bin_id: row.bin?.id ?? null,
      bin: row.bin ?? null,
      available: String(Number(row.quantity_on_hand) - Number(row.quantity_reserved)),
    }))
    .filter((row) => row.bin_id && Number(row.available) > 0);
}
