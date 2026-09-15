import { apiFetch } from "@/lib/api-server";
import { ApiError } from "@/lib/api-error";
import { getT } from "@/lib/i18n-server";
import { PageHeader } from "@/components/layout/page-header";
import { EmptyState } from "@/components/ui/empty-state";
import { LayoutDashboard } from "lucide-react";
import { DaysPicker } from "@/components/dashboard/days-picker";
import { ManagementPanel } from "@/components/dashboard/management-panel";
import { MaintenancePanel } from "@/components/dashboard/maintenance-panel";
import { StorePanel } from "@/components/dashboard/store-panel";

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
 * The three dashboards (SRS 30) — mirrors `DashboardController`/`dashboard.
 * index.blade.php`. Which panels a person sees is decided by what they can
 * act on, not a setting: a storekeeper lands on stock, not a fleet
 * availability figure they can't influence.
 */
export default async function DashboardPage({ searchParams }) {
  const params = await searchParams;
  const days = PERIODS.includes(Number(params.days)) ? Number(params.days) : 30;

  const [management, maintenance, store, trend, t] = await Promise.all([
    fetchPanel(`/dashboard/management?days=${days}`),
    fetchPanel(`/dashboard/maintenance?days=${days}`),
    fetchPanel(`/dashboard/store?days=${days}`),
    // Shared by whichever panel wants it — fetched once here rather than
    // once per panel, since it's the same series regardless of audience.
    fetchPanel(`/dashboard/trend?days=${days}`),
    getT("dashboard"),
  ]);

  const nothingToShow = !management && !maintenance && !store;

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("dashboard") }]}
        title={t("dashboard")}
        description={t("period_note")}
        actions={<DaysPicker periods={PERIODS} value={days} />}
      />

      {nothingToShow ? (
        <EmptyState icon={<LayoutDashboard />} title={t("no_panels")} description={t("no_panels_hint")} />
      ) : (
        <div className="flex flex-col gap-8">
          {[
            management ? { key: "management", node: <ManagementPanel data={management} /> } : null,
            maintenance ? { key: "maintenance", node: <MaintenancePanel data={maintenance} trend={trend} /> } : null,
            store ? { key: "store", node: <StorePanel data={store} /> } : null,
          ]
            .filter(Boolean)
            .map((panel, index) => (
              <div key={panel.key} className={index > 0 ? "border-t border-border pt-8" : undefined}>
                {panel.node}
              </div>
            ))}
        </div>
      )}
    </>
  );
}
