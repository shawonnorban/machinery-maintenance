import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { WorkOrderForm } from "@/components/work-orders/work-order-form";
import { createWorkOrder } from "./actions";

export default async function CreateWorkOrderPage() {
  const options = await apiFetch("/work-orders/form-options");

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Work Orders", href: "/work-orders" }, { label: "New" }]} title="New work order" />

      <Card>
        <CardBody>
          <WorkOrderForm options={options} action={createWorkOrder} />
        </CardBody>
      </Card>
    </>
  );
}
