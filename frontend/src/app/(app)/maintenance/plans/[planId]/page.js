import Link from "next/link";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { StatusBadge, formatStatus } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { PlanHeaderActions } from "@/components/maintenance/plan-header-actions";
import { activatePlan, deactivatePlan, deletePlan } from "../actions";

/** Mirrors `plans/show.blade.php`. */
export default async function MaintenancePlanDetailPage({ params }) {
  const { planId } = await params;

  const [plan, schedules] = await Promise.all([
    apiFetch(`/maintenance-plans/${planId}`),
    apiFetch(`/maintenance-schedules?maintenance_plan_id=${planId}&per_page=50`, { includeMeta: true }),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: "Maintenance" }, { label: "Plans", href: "/maintenance/plans" }, { label: plan.name }]}
        title={plan.name}
        description={plan.asset?.asset_code ?? plan.asset_type ?? undefined}
        actions={<PlanHeaderActions plan={plan} actions={{ activatePlan, deactivatePlan, deletePlan }} />}
      />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardBody>
            <div className="mb-4 flex items-center justify-between">
              <h2 className="text-sm font-medium text-foreground">Upcoming occurrences</h2>
              <StatusBadge status={plan.active ? "ACTIVE" : "INACTIVE"} />
            </div>

            {schedules.data.length === 0 ? (
              <p className="text-sm text-foreground-muted">Nothing scheduled yet.</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="text-xs font-medium text-foreground-muted">
                    <tr>
                      <th className="px-2 py-2 text-left">Due</th>
                      <th className="px-2 py-2 text-left">Asset</th>
                      <th className="px-2 py-2 text-right">Due meter</th>
                      <th className="px-2 py-2 text-left">Triggered by</th>
                      <th className="px-2 py-2 text-left">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {schedules.data.map((s) => (
                      <tr key={s.id}>
                        <td className="px-2 py-2">
                          <FormattedDateTime value={s.due_at} mode="date" />
                        </td>
                        <td className="px-2 py-2">
                          <Link href={`/assets/${s.asset?.id}`} className="text-brand hover:underline">
                            {s.asset?.asset_code}
                          </Link>
                        </td>
                        <td className="px-2 py-2 text-right tabular text-foreground-muted">{s.due_meter ?? "—"}</td>
                        <td className="px-2 py-2 text-foreground-muted">{s.triggered_by ?? "—"}</td>
                        <td className="px-2 py-2">
                          <StatusBadge status={s.status} />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <dl className="flex flex-col gap-3 text-sm">
              <div className="flex justify-between gap-2">
                <dt className="text-foreground-muted">Trigger</dt>
                <dd className="font-medium text-foreground">{formatStatus(plan.trigger_type)}</dd>
              </div>
              {plan.trigger_type === "COMBINED" ? (
                <div className="flex justify-between gap-2">
                  <dt className="text-foreground-muted">Rule logic</dt>
                  <dd className="font-medium text-foreground">{plan.rule_logic === "AND" ? "Both required" : "Whichever comes first"}</dd>
                </div>
              ) : null}
              <div className="flex justify-between gap-2">
                <dt className="text-foreground-muted">Schedule mode</dt>
                <dd className="font-medium text-foreground">{formatStatus(plan.schedule_mode)}</dd>
              </div>
              {plan.rules?.map((rule) => (
                <div key={rule.id} className="flex justify-between gap-2">
                  <dt className="text-foreground-muted">{rule.rule_type === "TIME" ? "Interval" : "Meter threshold"}</dt>
                  <dd className="font-medium text-foreground">
                    Every {Math.trunc(Number(rule.value))} {rule.unit}
                  </dd>
                </div>
              ))}
              <div className="flex justify-between gap-2">
                <dt className="text-foreground-muted">Non-working day</dt>
                <dd className="font-medium text-foreground">{formatStatus(plan.non_working_day_policy)}</dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-foreground-muted">Grace</dt>
                <dd className="font-medium text-foreground">{plan.grace_period_minutes} min</dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-foreground-muted">Lead time</dt>
                <dd className="font-medium text-foreground">{plan.lead_time_days} days</dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-foreground-muted">Next due</dt>
                <dd className="font-medium text-foreground">
                  <FormattedDateTime value={plan.next_due_at} mode="date" fallback="—" />
                </dd>
              </div>
              {plan.template_version_number ? (
                <div className="flex justify-between gap-2">
                  <dt className="text-foreground-muted">Template</dt>
                  <dd className="font-medium text-foreground">v{plan.template_version_number}</dd>
                </div>
              ) : null}
            </dl>
          </CardBody>
        </Card>
      </div>
    </>
  );
}
