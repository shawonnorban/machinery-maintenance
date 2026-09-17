import Link from "next/link";
import { ArrowLeft, Pencil } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { SparePartDetailTabs } from "@/components/inventory/spare-part-detail-tabs";
import { ReceiveStockModal } from "@/components/inventory/receive-stock-modal";
import { AdjustStockModal } from "@/components/inventory/adjust-stock-modal";
import { receiveStock, adjustStock, reverseTransaction } from "./actions";
import { formatNumber, formatCurrency } from "@/lib/format";

/**
 * Mirrors `SparePartController::show`'s core (catalogue info, stock by
 * bin, transaction ledger, receive/adjust/reverse, compatibility
 * management). Create/edit live at `../create` and `./edit`.
 *
 * Only three calls up front — the part itself, its stock summary (shown
 * eagerly in the always-visible header card), and bins (needed by the
 * header's Receive/Adjust actions) — rather than the seven this page
 * used to fire in one `Promise.all`. Transactions and Compatibility are
 * fetched by their own tab, lazily, the first time each is actually
 * opened (`SparePartDetailTabs`'s own note has why): the same problem
 * already found and fixed on the asset/work-order/breakdown detail pages.
 */
export default async function SparePartDetailPage({ params }) {
  const { partId } = await params;

  const [part, stock, bins] = await Promise.all([
    apiFetch(`/spare-parts/${partId}`),
    apiFetch(`/spare-parts/${partId}/stock`),
    apiFetch("/spare-parts/bins"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Spare Parts", href: "/inventory/parts" }, { label: part.part_number }]}
        title={part.part_number}
        description={part.name}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <AdjustStockModal bins={bins} action={adjustStock.bind(null, partId)} />
            <ReceiveStockModal bins={bins} action={receiveStock.bind(null, partId)} />
            <Link href={`/inventory/parts/${partId}/edit`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <Pencil /> Edit
            </Link>
            <Link href="/inventory/parts" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
              <ArrowLeft /> Back
            </Link>
          </div>
        }
      />

      <Card className={cn("mb-6 flex flex-wrap items-center gap-x-8 gap-y-3 border-l-4 p-4", part.is_critical_spare ? "border-l-danger" : "border-l-border-strong")}>
        <SummaryItem label="Category">
          <span className="text-sm text-foreground">{part.category?.name ?? "—"}</span>
        </SummaryItem>
        <SummaryItem label="On hand">
          <span className="text-sm font-medium text-foreground">
            {formatNumber(stock.total_on_hand)} <span className="font-normal text-foreground-muted">{stock.unit}</span>
          </span>
        </SummaryItem>
        <SummaryItem label="Unit cost">
          <span className="text-sm text-foreground">{part.unit_cost ? formatCurrency(part.unit_cost, part.currency) : "—"}</span>
        </SummaryItem>
        <SummaryItem label="Flags">
          <div className="flex gap-1">
            {part.is_critical_spare ? <Badge variant="danger">Critical spare</Badge> : null}
            {part.hazardous ? <Badge variant="warning">Hazardous</Badge> : null}
            {!part.active ? <Badge variant="neutral">Inactive</Badge> : null}
            {!part.is_critical_spare && !part.hazardous && part.active ? <span className="text-sm text-foreground-muted">—</span> : null}
          </div>
        </SummaryItem>
      </Card>

      <SparePartDetailTabs part={part} partId={partId} stock={stock} reverseAction={reverseTransaction.bind(null, partId)} />
    </>
  );
}

function SummaryItem({ label, children }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs font-medium text-foreground-muted">{label}</span>
      {children}
    </div>
  );
}
