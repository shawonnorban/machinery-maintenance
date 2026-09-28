import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { OrderForm } from "@/components/production/order-form";
import { getT } from "@/lib/i18n-server";
import { createProductionOrder } from "./actions";

/** Mirrors `ProductionOrderApiController::store` (Master Data spec §6). */
export default async function CreateProductionOrderPage() {
  // The floor requisition form already exposes the factories this user can
  // reach; reused rather than adding a form-options endpoint for one list.
  const [options, t] = await Promise.all([
    apiFetch("/floor-requisitions/form-options"),
    getT("production"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("production") }, { label: t("orders"), href: "/production/orders" }, { label: t("new_order") }]}
        title={t("new_order")}
      />

      <OrderForm factories={options.factories} action={createProductionOrder} />
    </>
  );
}
