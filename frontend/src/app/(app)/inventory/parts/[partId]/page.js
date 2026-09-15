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
import { receiveStock, adjustStock, reverseTransaction, addCompatibility, deleteCompatibility } from "./actions";
import { formatNumber, formatCurrency } from "@/lib/format";

/**
 * Mirrors `SparePartController::show`'s core (catalogue info, stock by
 * bin, transaction ledger, receive/adjust/reverse, compatibility
 * management). Create/edit live at `../create` and `./edit`.
 */
export default async function SparePartDetailPage({ params }) {
  const { partId } = await params;

  const [part, stock, transactionsPage, bins, compatibilityRows, formOptions, otherPartsPage] = await Promise.all([
    apiFetch(`/spare-parts/${partId}`),
    apiFetch(`/spare-parts/${partId}/stock`),
    apiFetch(`/spare-parts/${partId}/transactions?per_page=50`),
    apiFetch("/spare-parts/bins"),
    apiFetch(`/spare-parts/${partId}/compatibility`),
    apiFetch("/spare-parts/form-options"),
    apiFetch("/spare-parts?per_page=100"),
  ]);

  const otherParts = otherPartsPage.filter((p) => p.id !== partId);

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

      <SparePartDetailTabs
        part={part}
        stock={stock}
        transactions={transactionsPage}
        reverseAction={reverseTransaction.bind(null, partId)}
        compatibility={{
          rows: compatibilityRows,
          assetModels: formOptions.asset_models,
          otherParts,
          addAction: addCompatibility.bind(null, partId),
          deleteAction: deleteCompatibility.bind(null, partId),
        }}
      />
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
