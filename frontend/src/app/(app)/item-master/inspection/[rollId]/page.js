import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { InspectRollForm } from "@/components/item-master/inspect-roll-form";
import { getT } from "@/lib/i18n-server";
import { inspectRoll, uploadRollPhoto } from "./actions";

/** Mirrors `FabricRollApiController::show` (Inventory spec §4). */
export default async function InspectFabricRollPage({ params }) {
  const { rollId } = await params;

  const [roll, formOptions, permissions, t] = await Promise.all([
    apiFetch(`/fabric-rolls/${rollId}`),
    apiFetch("/fabric-rolls/form-options"),
    apiFetch("/auth/permissions"),
    getT("inspection"),
  ]);

  const canUploadPhoto = new Set(permissions.permissions).has("itemmaster.roll.inspect");

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("fabric_inspection"), href: "/item-master/inspection" }, { label: roll.roll_no }]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {t("inspect_roll")} — {roll.roll_no}
            <StatusBadge status={roll.status} label={t(`statuses.${roll.status}`)} />
          </span>
        }
        actions={
          <Link href="/item-master/inspection" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeft /> {t("fabric_inspection")}
          </Link>
        }
      />

      <InspectRollForm
        roll={roll}
        defects={formOptions.defects}
        action={inspectRoll}
        canUploadPhoto={canUploadPhoto}
        uploadPhoto={uploadRollPhoto.bind(null, rollId)}
      />
    </>
  );
}
