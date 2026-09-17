import { CreditCard } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { StatCard } from "@/components/ui/stat-card";
import { Alert } from "@/components/ui/alert";
import { EmptyState } from "@/components/ui/empty-state";
import { InvoicesTable } from "@/components/billing/invoices-table";
import { getT } from "@/lib/i18n-server";

const CONTRACT_TONE = {
  ACTIVE: "success",
  TRIAL: "info",
  PAST_DUE: "warning",
  GRACE: "warning",
  READ_ONLY: "danger",
  ARCHIVED: "neutral",
  CANCELLED: "danger",
  DRAFT: "neutral",
};

/**
 * Mirrors `BillingController::index`/`billing/index.blade.php` — what the
 * company owes and is using, reachable even while the subscription is
 * read-only (SRS 40), since the page where somebody would settle the
 * account is the last one that should be locked.
 */
export default async function BillingPage({ searchParams }) {
  const params = await searchParams;
  const page = Number(params.page ?? 1);

  const [subscription, invoices, t, tc] = await Promise.all([
    apiFetch("/subscription"),
    apiFetch(`/subscription/invoices?page=${page}`, { includeMeta: true }),
    getT("billing"),
    getT("common"),
  ]);

  const contract = subscription.contract;

  return (
    <>
      <PageHeader breadcrumb={[{ label: t("billing") }]} title={t("billing")} description={contract?.contract_number} />

      {contract === null ? (
        <EmptyState icon={<CreditCard />} title={t("no_contract")} description={t("no_contract_page_hint")} />
      ) : (
        <div className="flex flex-col gap-6">
          {contract.is_read_only ? (
            <Alert variant="danger" title={t("read_only_title")}>
              {t("read_only_alert_body")}
            </Alert>
          ) : ["PAST_DUE", "GRACE"].includes(contract.status) ? (
            <Alert variant="warning" title={t("past_due_title")}>
              {t("past_due_alert_body")}
            </Alert>
          ) : null}

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card className="p-5">
              <span className="text-xs font-medium text-foreground-muted">{t("status")}</span>
              <div className="mt-2">
                <Badge variant={CONTRACT_TONE[contract.status] ?? "neutral"}>{t(`statuses.${contract.status}`)}</Badge>
              </div>
            </Card>
            <StatCard
              label={t("outstanding")}
              value={`${Number(subscription.outstanding).toLocaleString(undefined, { minimumFractionDigits: 2 })} ${contract.currency}`}
              tone={Number(subscription.outstanding) > 0 ? "danger" : "success"}
            />
            <StatCard
              label={t("amount")}
              value={`${Number(contract.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })} ${contract.currency}`}
              supportingText={t(`cycles.${contract.billing_cycle}`)}
            />
            <StatCard label={t("grace_period")} value={t("grace_days", { days: contract.grace_period_days ?? 0 })} />
          </div>

          <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <div className="flex flex-col gap-6 lg:col-span-5">
              <Card>
                <CardHeader>
                  <CardTitle>{t("subscription")}</CardTitle>
                </CardHeader>
                <CardBody>
                  <dl className="flex flex-col gap-2 text-sm">
                    <Row label={t("start_date")} value={contract.start_date} />
                    <Row label={t("end_date")} value={contract.end_date ?? "—"} />
                    <Row label={t("trial_end")} value={contract.trial_end ?? "—"} />
                    <Row label={t("auto_renew")} value={contract.auto_renew ? tc("yes") : tc("no")} />
                    <Row label={t("overage_policy")} value={t(`policies.${contract.overage_policy}`)} />
                  </dl>
                </CardBody>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>{t("usage")}</CardTitle>
                </CardHeader>
                <CardBody>
                  {subscription.usage.length === 0 ? (
                    <p className="text-sm text-foreground-muted">{t("no_usage_recorded")}</p>
                  ) : (
                    <div className="flex flex-col gap-2 text-sm">
                      {subscription.usage.map((row) => (
                        <div
                          key={row.metric}
                          className={`flex items-center justify-between rounded-sm px-2 py-1.5 ${row.exceeded ? "bg-warning-subtle" : ""}`}
                        >
                          <span className="text-foreground">{t(`metrics.${row.metric}`)}</span>
                          <span className="tabular text-xs text-foreground-muted">
                            {Number(row.value).toLocaleString()}
                            {row.limit === null ? "" : ` / ${Number(row.limit).toLocaleString()}`}
                          </span>
                        </div>
                      ))}
                    </div>
                  )}
                </CardBody>
              </Card>
            </div>

            <div className="lg:col-span-7">
              <Card>
                <CardHeader>
                  <CardTitle>{t("invoices")}</CardTitle>
                </CardHeader>
                <CardBody>
                  <InvoicesTable invoices={invoices.data} meta={invoices.meta} />
                </CardBody>
              </Card>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

function Row({ label, value }) {
  return (
    <div className="flex justify-between">
      <dt className="text-foreground-muted">{label}</dt>
      <dd className="text-foreground">{value}</dd>
    </div>
  );
}
