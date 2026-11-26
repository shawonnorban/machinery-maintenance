import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { WorkOrderForm } from "@/components/work-orders/work-order-form";
import { getT } from "@/lib/i18n-server";
import { createWorkOrder } from "./actions";

export default async function CreateWorkOrderPage() {
  const [options, t, tc] = await Promise.all([
    apiFetch("/work-orders/form-options"),
    getT("work_order"),
    getT("common"),
  ]);

  return (
    <>
      <ScreenHeader
        parentUrl="/work-orders"
        title={t("new_work_order")}
      />

      <WorkOrderForm options={options} action={createWorkOrder} />
    </>
  );
}
