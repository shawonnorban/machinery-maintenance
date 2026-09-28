import Link from "next/link";
import { ArrowLeft, Pencil } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { ReceiptDetail } from "@/components/procurement/receipt-detail";
import { getT } from "@/lib/i18n-server";
import { completeReceipt, cancelReceipt } from "./actions";

/** Mirrors `GoodsReceiptApiController::show` (Procurement spec §9). */
export default async function GoodsReceiptPage({ params }) {
  const { receiptId } = await params;

  const [receipt, t, tc] = await Promise.all([apiFetch(`/goods-receipts/${receiptId}`), getT("procurement"), getT("common")]);

  // Bins of the receiving store, for lines still without one. Only fetched
  // while completing is possible: a completed receipt's bins are fixed.
  const bins = receipt.can_complete
    ? (await apiFetch("/goods-receipts/form-options")).bins.filter((b) => b.store_id === receipt.store?.id)
    : [];

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("goods_receipts"), href: "/procurement/receipts" }, { label: receipt.grn_no }]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {receipt.grn_no}
            <StatusBadge status={receipt.status} label={t(`statuses.${receipt.status}`)} />
          </span>
        }
        description={`${receipt.store?.name ?? ""}${receipt.vendor ? ` · ${receipt.vendor.name}` : ""}`}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {receipt.can_edit ? (
              <Link href={`/procurement/receipts/${receiptId}/edit`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
                <Pencil /> {tc("edit")}
              </Link>
            ) : null}
            <Link href="/procurement/receipts" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <ArrowLeft /> {tc("back")}
            </Link>
          </div>
        }
      />

      <ReceiptDetail
        receipt={receipt}
        bins={bins}
        actions={{
          complete: completeReceipt.bind(null, receiptId),
          cancel: cancelReceipt.bind(null, receiptId),
        }}
      />
    </>
  );
}
