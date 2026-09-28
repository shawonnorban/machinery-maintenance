import { redirect } from "next/navigation";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { ReceiptForm } from "@/components/procurement/receipt-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { updateReceipt } from "./actions";

export default async function EditGoodsReceiptPage({ params }) {
  const { receiptId } = await params;

  const [receipt, options, t] = await Promise.all([
    apiFetch(`/goods-receipts/${receiptId}`),
    apiFetch("/goods-receipts/form-options"),
    getT("procurement"),
  ]);

  if (!receipt.can_edit) {
    redirect(`/procurement/receipts/${receiptId}`);
  }

  return (
    <>
      <PageHeader
        breadcrumb={[
          { label: t("procurement") },
          { label: t("goods_receipts"), href: "/procurement/receipts" },
          { label: receipt.grn_no, href: `/procurement/receipts/${receiptId}` },
          { label: t("edit_receipt") },
        ]}
        title={`${t("edit_receipt")} — ${receipt.grn_no}`}
      />

      <ReceiptForm receipt={receipt} options={options} searchItems={searchItems} action={updateReceipt.bind(null, receiptId)} />
    </>
  );
}
