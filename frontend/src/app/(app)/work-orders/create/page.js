import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
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
      <PageHeader
        breadcrumb={[{ label: t("work_orders"), href: "/work-orders" }, { label: tc("new") }]}
        title={t("new_work_order")}
      />

      <Card>
        <CardBody>
          <WorkOrderForm options={options} action={createWorkOrder} />
        </CardBody>
      </Card>
    </>
  );
}
