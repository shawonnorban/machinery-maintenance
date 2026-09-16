import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { MyWorkTable } from "@/components/work-orders/my-work-table";
import { getT } from "@/lib/i18n-server";

/** Mirrors `MyWorkController::index` — rendered on the mobile layout there; here it's the same query against `WorkOrderApiController`'s `assigned_to_me` filter. */
export default async function MyWorkPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);

  const [workOrders, t] = await Promise.all([
    apiFetch(`/work-orders?assigned_to_me=true&page=${page}`, { includeMeta: true }),
    getT("work_order"),
  ]);

  return (
    <>
      <PageHeader breadcrumb={[{ label: t("my_work") }]} title={t("my_work")} description={t("my_work_page_hint")} />

      <MyWorkTable workOrders={workOrders.data} meta={workOrders.meta} />
    </>
  );
}
