import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { TransferRequestForm } from "@/components/inventory/transfer-request-form";
import { getT } from "@/lib/i18n-server";
import { requestTransfer } from "./actions";

export default async function CreateTransferPage() {
  const [formOptions, spareParts, t, tc] = await Promise.all([
    apiFetch("/inventory-transfers/form-options"),
    apiFetch("/spare-parts?per_page=100"),
    getT("inventory"),
    getT("common"),
  ]);

  return (
    <>
      <ScreenHeader
        parentUrl="/inventory/transfers"
        title={t("request_a_transfer")}
      />

      <TransferRequestForm factories={formOptions.factories} bins={formOptions.bins} spareParts={spareParts} action={requestTransfer} />
    </>
  );
}
