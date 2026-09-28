import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { cn } from "@/lib/utils";
import { StockCountDetail } from "@/components/item-master/stock-count-detail";
import { getT } from "@/lib/i18n-server";
import {
  recordCounts,
  recordFound,
  flagForRecount,
  submitStockCount,
  postStockCount,
  cancelStockCount,
} from "./actions";

/** Mirrors `StockCountApiController::show` (Inventory spec §12). */
export default async function StockCountPage({ params }) {
  const { countId } = await params;

  const [count, t, tc] = await Promise.all([
    apiFetch(`/stock-counts/${countId}`),
    getT("itemmaster"),
    getT("common"),
  ]);

  // Only needed for the "found on the shelf" picker, so only fetched
  // while the sheet is still open.
  const items = count.can_record
    ? (await apiFetch("/items?per_page=200", { includeMeta: true })).data
    : [];

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("item_master") },
          { label: t("stock_counts"), href: "/item-master/stock-counts" },
          { label: count.count_no },
        ]}
        title={
          <span className="flex flex-wrap items-center gap-2">
            {count.count_no}
            <StatusBadge status={count.status} label={t(`count_statuses.${count.status}`)} />
          </span>
        }
        description={`${count.store?.name ?? ""} · ${t(`count_types.${count.count_type}`)}`}
        actions={
          <Link href="/item-master/stock-counts" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeft /> {tc("back")}
          </Link>
        }
      />

      <StockCountDetail
        count={count}
        items={items}
        actions={{
          record: recordCounts.bind(null, countId),
          recordFound: recordFound.bind(null, countId),
          recount: flagForRecount.bind(null, countId),
          submit: submitStockCount.bind(null, countId),
          post: postStockCount.bind(null, countId),
          cancel: cancelStockCount.bind(null, countId),
        }}
      />
    </>
  );
}
