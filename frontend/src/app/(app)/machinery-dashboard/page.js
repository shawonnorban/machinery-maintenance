import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { getT } from "@/lib/i18n-server";
import { ScreenHeader } from "@/components/layout/screen-header";
import { EmptyState } from "@/components/ui/empty-state";
import { LayoutDashboard } from "lucide-react";
import { DaysPicker } from "@/components/dashboard/days-picker";
import {
  AttentionStrip,
  KpiRow,
  TrendCard,
  MachineStatusCard,
  OpenBreakdownsCard,
  PmCard,
  WorkloadCard,
  TopFailingCard,
  CostDowntimeCard,
  StockOverviewCard,
  ReorderCard,
} from "@/components/dashboard/cards";

const PERIODS = [7, 30, 90];

/** A panel this account can't see 403s — that means "not for this role," not a real error, so it's hidden rather than surfaced. */
async function fetchPanel(path) {
  try {
    return await apiFetch(path);
  } catch (error) {
    if (error instanceof ApiError && error.status === 403) return null;
    throw error;
  }
}

/**
 * One row of cards. Cards a role can't see are passed as null and dropped,
 * and the rest share the row by their `grow` weight, so a storekeeper's
 * dashboard closes up instead of leaving holes where the fleet cards were.
 */
function Row({ items }) {
  const visible = items.filter((item) => item && item.node);
  if (visible.length === 0) return null;

  return (
    <div className="flex flex-col gap-4 lg:flex-row lg:items-stretch">
      {visible.map((item) => (
        <div key={item.key} className="flex min-w-0 flex-col [&>*]:flex-1" style={{ flex: `${item.grow ?? 1} 1 0%` }}>
          {item.node}
        </div>
      ))}
    </div>
  );
}

/**
 * The Machinery dashboard (SRS 30).
 * Moved off "/" (user, 2026-11-28): the home page is the merchandising one. Which cards a person sees is decided by
 * which panel endpoints answer for them (management / maintenance / store,
 * each on its own permission), not a setting.
 *
 * Order, top to bottom: what needs someone now, the period's headline
 * figures, how the period moved, the live lists behind the counts, then
 * people, machines, money, and the store.
 */
export default async function DashboardPage({ searchParams }) {
  const params = await searchParams;
  const days = PERIODS.includes(Number(params.days)) ? Number(params.days) : 30;

  const [management, maintenance, store, trend, t] = await Promise.all([
    fetchPanel(`/dashboard/management?days=${days}`),
    fetchPanel(`/dashboard/maintenance?days=${days}`),
    fetchPanel(`/dashboard/store?days=${days}`),
    fetchPanel(`/dashboard/trend?days=${days}`),
    getT("dashboard"),
  ]);

  const nothingToShow = !management && !maintenance && !store;

  return (
    <>
      <ScreenHeader url="/machinery-dashboard" title={t("dashboard")} description={t("period_note")} actions={<DaysPicker periods={PERIODS} value={days} />} />

      {nothingToShow ? (
        <EmptyState icon={<LayoutDashboard />} title={t("no_panels")} description={t("no_panels_hint")} />
      ) : (
        <div className="flex flex-col gap-4">
          {maintenance || store ? <AttentionStrip maintenance={maintenance} store={store} /> : null}

          <KpiRow management={management} maintenance={maintenance} />

          <Row
            items={[
              trend && (maintenance || management) ? { key: "trend", grow: 2, node: <TrendCard trend={trend} /> } : null,
              management ? { key: "machines", grow: 1, node: <MachineStatusCard management={management} /> } : null,
            ]}
          />

          <Row
            items={[
              maintenance ? { key: "breakdowns", node: <OpenBreakdownsCard maintenance={maintenance} /> } : null,
              maintenance ? { key: "pm", node: <PmCard maintenance={maintenance} /> } : null,
            ]}
          />

          <Row
            items={[
              maintenance ? { key: "workload", node: <WorkloadCard maintenance={maintenance} /> } : null,
              management ? { key: "failing", node: <TopFailingCard management={management} /> } : null,
              management ? { key: "cost", node: <CostDowntimeCard management={management} /> } : null,
            ]}
          />

          <Row
            items={[
              store ? { key: "stock", grow: 1, node: <StockOverviewCard store={store} /> } : null,
              store ? { key: "reorder", grow: 2, node: <ReorderCard store={store} /> } : null,
            ]}
          />
        </div>
      )}
    </>
  );
}
