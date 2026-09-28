import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { LandedCostDetail } from "@/components/procurement/landed-cost-detail";
import { getT } from "@/lib/i18n-server";
import { previewLandedCost, postLandedCost, cancelLandedCost } from "./actions";

/** Mirrors `LandedCostApiController::show` (Procurement spec §11). */
export default async function LandedCostPage({ params }) {
  const { voucherId } = await params;

  const [voucher, t, tc] = await Promise.all([
    apiFetch(`/landed-costs/${voucherId}`),
    getT("procurement"),
    getT("common"),
  ]);

  // A draft has nothing stored to show, so the distribution is worked out
  // on the way in rather than leaving the page empty until a button.
  const preview = voucher.status === "DRAFT" ? await apiFetch(`/landed-costs/${voucherId}/preview`) : null;

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("landed_costs"), href: "/procurement/landed-costs" },
          { label: voucher.voucher_no },
        ]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {voucher.voucher_no}
            <StatusBadge status={voucher.status} label={t(`landed_cost_statuses.${voucher.status}`)} />
          </span>
        }
        description={voucher.factory?.name ?? ""}
        actions={
          <Link href="/procurement/landed-costs" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeft /> {tc("back")}
          </Link>
        }
      />

      <LandedCostDetail
        voucher={voucher}
        preview={preview}
        actions={{
          preview: previewLandedCost.bind(null, voucherId),
          post: postLandedCost.bind(null, voucherId),
          cancel: cancelLandedCost.bind(null, voucherId),
        }}
      />
    </>
  );
}
