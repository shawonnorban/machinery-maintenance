import { apiFetch } from "@/lib/api-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { MyWorkTable } from "@/components/work-orders/my-work-table";
import { getT } from "@/lib/i18n-server";
import { perPageQuery } from "@/lib/per-page";

/** Mirrors `MyWorkController::index` — rendered on the mobile layout there; here it's the same query against `WorkOrderApiController`'s `assigned_to_me` filter. */
export default async function MyWorkPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);

  const [workOrders, t] = await Promise.all([
    apiFetch(`/work-orders?assigned_to_me=true&page=${page}${await perPageQuery("/work-orders/my-work")}`, { includeMeta: true }),
    getT("work_order"),
  ]);

  return (
    <>
      <ScreenHeader url="/work-orders/my-work" title={t("my_work")} description={t("my_work_page_hint")} />

      <MyWorkTable workOrders={workOrders.data} meta={workOrders.meta} />
    </>
  );
}
