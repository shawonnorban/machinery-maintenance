import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { TransferRequestForm } from "@/components/inventory/transfer-request-form";
import { requestTransfer } from "./actions";

export default async function CreateTransferPage() {
  const [formOptions, spareParts] = await Promise.all([
    apiFetch("/inventory-transfers/form-options"),
    apiFetch("/spare-parts?per_page=100"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Transfers", href: "/inventory/transfers" }, { label: "New" }]}
        title="Request a transfer"
      />

      <TransferRequestForm factories={formOptions.factories} bins={formOptions.bins} spareParts={spareParts} action={requestTransfer} />
    </>
  );
}
