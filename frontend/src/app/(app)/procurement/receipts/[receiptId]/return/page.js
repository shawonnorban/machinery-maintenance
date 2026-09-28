import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { PurchaseReturnForm } from "@/components/procurement/purchase-return-form";
import { getT } from "@/lib/i18n-server";
import { createPurchaseReturn } from "./actions";

/** Raising a return against the receipt the goods came in on (spec §12). */
export default async function CreatePurchaseReturnPage({ params }) {
  const { receiptId } = await params;

  const [receipt, t] = await Promise.all([apiFetch(`/goods-receipts/${receiptId}`), getT("procurement")]);

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("receipts"), href: "/procurement/receipts" },
          { label: receipt.grn_no, href: `/procurement/receipts/${receiptId}` },
          { label: t("new_return") },
        ]}
        title={t("new_return")}
        description={`${receipt.grn_no} · ${receipt.vendor?.name ?? ""}`}
      />

      <PurchaseReturnForm receipt={receipt} onSubmit={createPurchaseReturn.bind(null, receiptId)} />
    </>
  );
}
