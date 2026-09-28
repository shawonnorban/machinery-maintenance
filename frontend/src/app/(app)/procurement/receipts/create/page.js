import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { ReceiptForm } from "@/components/procurement/receipt-form";
import { searchItems } from "@/components/procurement/search-items-action";
import { getT } from "@/lib/i18n-server";
import { createReceipt } from "./actions";

/** A direct receipt — no order behind it. Receiving against an order starts from the order's own page. */
export default async function CreateGoodsReceiptPage() {
  const [options, t] = await Promise.all([apiFetch("/goods-receipts/form-options"), getT("procurement")]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("procurement") }, { label: t("goods_receipts"), href: "/procurement/receipts" }, { label: t("new_receipt") }]}
        title={t("new_receipt")}
        description={t("direct_receipt")}
      />

      <ReceiptForm options={options} searchItems={searchItems} action={createReceipt} />
    </>
  );
}
