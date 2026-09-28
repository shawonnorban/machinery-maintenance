import Link from "next/link";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { PlanHeaderActions } from "@/components/maintenance/plan-header-actions";
import { getT } from "@/lib/i18n-server";
import { activatePlan, deactivatePlan, deletePlan } from "../actions";

/** Mirrors `plans/show.blade.php`. */
export default async function MaintenancePlanDetailPage({ params }) {
  const { planId } = await params;

  const [plan, schedules, t] = await Promise.all([
    apiFetch(`/maintenance-plans/${planId}`),
    apiFetch(`/maintenance-schedules?maintenance_plan_id=${planId}&per_page=50`, { includeMeta: true }),
    getT("maintenance"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("maintenance") }, { label: t("plans"), href: "/maintenance/plans" }, { label: plan.name }]}
        title={plan.name}
        description={plan.asset?.asset_code ?? plan.asset_type ?? undefined}
        actions={<PlanHeaderActions plan={plan} actions={{ activatePlan, deactivatePlan, deletePlan }} />}
      />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardBody>
            <div className="mb-4 flex items-center justify-between">
              <h2 className="text-sm font-medium text-foreground">{t("upcoming_occurrences")}</h2>
              <StatusBadge status={plan.active ? "ACTIVE" : "INACTIVE"} label={plan.active ? t("active") : t("inactive")} />
            </div>

            {schedules.data.length === 0 ? (
              <p className="text-sm text-foreground-muted">{t("no_schedules_hint")}</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="text-xs font-medium text-foreground-muted">
                    <tr>
                      <th className="px-2 py-2 text-left">{t("due_at")}</th>
                      <th className="px-2 py-2 text-left">{t("asset")}</th>
                      <th className="px-2 py-2 text-right">{t("due_meter")}</th>
                      <th className="px-2 py-2 text-left">{t("triggered_by")}</th>
                      <th className="px-2 py-2 text-left">{t("status")}</th>
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
                          <StatusBadge status={s.status} label={t(`status_${s.status?.toLowerCase()}`)} />
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
                <dt className="text-foreground-muted">{t("trigger")}</dt>
                <dd className="font-medium text-foreground">{t(`trigger_${plan.trigger_type?.toLowerCase()}`)}</dd>
              </div>
              {plan.trigger_type === "COMBINED" ? (
                <div className="flex justify-between gap-2">
                  <dt className="text-foreground-muted">{t("rule_logic")}</dt>
                  <dd className="font-medium text-foreground">{plan.rule_logic === "AND" ? t("logic_and") : t("logic_or")}</dd>
                </div>
              ) : null}
              <div className="flex justify-between gap-2">
                <dt className="text-foreground-muted">{t("schedule_mode")}</dt>
                <dd className="font-medium text-foreground">{plan.schedule_mode === "ROLLING" ? t("mode_rolling") : t("mode_fixed")}</dd>
              </div>
              {plan.rules?.map((rule) => (
                <div key={rule.id} className="flex justify-between gap-2">
                  <dt className="text-foreground-muted">{rule.rule_type === "TIME" ? t("interval") : t("meter_threshold")}</dt>
                  <dd className="font-medium text-foreground">{t("every_interval", { count: Math.trunc(Number(rule.value)), unit: rule.unit })}</dd>
                </div>
              ))}
              <div className="flex justify-between gap-2">
                <dt className="text-foreground-muted">{t("non_working_day_short")}</dt>
                <dd className="font-medium text-foreground">
                  {plan.non_working_day_policy === "NEXT_WORKING_DAY"
                    ? t("policy_next")
                    : plan.non_working_day_policy === "PREVIOUS_WORKING_DAY"
                      ? t("policy_previous")
                      : t("policy_none")}
                </dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-foreground-muted">{t("grace_minutes")}</dt>
                <dd className="font-medium text-foreground">{t("minutes", { count: plan.grace_period_minutes })}</dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-foreground-muted">{t("lead_time_days_short")}</dt>
                <dd className="font-medium text-foreground">{t("days_count", { count: plan.lead_time_days })}</dd>
              </div>
              <div className="flex justify-between gap-2">
                <dt className="text-foreground-muted">{t("next_due")}</dt>
                <dd className="font-medium text-foreground">
                  <FormattedDateTime value={plan.next_due_at} mode="date" fallback="—" />
                </dd>
              </div>
              {plan.template_version_number ? (
                <div className="flex justify-between gap-2">
                  <dt className="text-foreground-muted">{t("template")}</dt>
                  <dd className="font-medium text-foreground">{t("version_number", { number: plan.template_version_number })}</dd>
                </div>
              ) : null}
            </dl>
          </CardBody>
        </Card>
      </div>
    </>
  );
}
