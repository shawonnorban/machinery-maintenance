import { CreditCard } from "lucide-react";
import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { StatCard } from "@/components/ui/stat-card";
import { Alert } from "@/components/ui/alert";
import { EmptyState } from "@/components/ui/empty-state";
import { InvoicesTable } from "@/components/billing/invoices-table";

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

  const [subscription, invoices] = await Promise.all([
    apiFetch("/subscription"),
    apiFetch(`/subscription/invoices?page=${page}`, { includeMeta: true }),
  ]);

  const contract = subscription.contract;

  return (
    <>
      <PageHeader breadcrumb={[{ label: "Billing" }]} title="Billing" description={contract?.contract_number} />

      {contract === null ? (
        <EmptyState
          icon={<CreditCard />}
          title="No subscription contract"
          description="This company isn't billed by anyone — onboarding, or a self-hosted deployment, is restricted by nothing."
        />
      ) : (
        <div className="flex flex-col gap-6">
          {contract.is_read_only ? (
            <Alert variant="danger" title="This subscription is read-only.">
              Nothing on the account can be written until it&apos;s settled.
            </Alert>
          ) : ["PAST_DUE", "GRACE"].includes(contract.status) ? (
            <Alert variant="warning" title="Payment is past due.">
              Settle the outstanding balance below before the account becomes read-only.
            </Alert>
          ) : null}

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card className="p-5">
              <span className="text-xs font-medium text-foreground-muted">Status</span>
              <div className="mt-2">
                <Badge variant={CONTRACT_TONE[contract.status] ?? "neutral"}>{contract.status}</Badge>
              </div>
            </Card>
            <StatCard
              label="Outstanding"
              value={`${Number(subscription.outstanding).toLocaleString(undefined, { minimumFractionDigits: 2 })} ${contract.currency}`}
              tone={Number(subscription.outstanding) > 0 ? "danger" : "success"}
            />
            <StatCard
              label="Amount"
              value={`${Number(contract.amount).toLocaleString(undefined, { minimumFractionDigits: 2 })} ${contract.currency}`}
              supportingText={contract.billing_cycle}
            />
            <StatCard label="Grace period" value={`${contract.grace_period_days ?? 0} days`} />
          </div>

          <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <div className="flex flex-col gap-6 lg:col-span-5">
              <Card>
                <CardHeader>
                  <CardTitle>Subscription</CardTitle>
                </CardHeader>
                <CardBody>
                  <dl className="flex flex-col gap-2 text-sm">
                    <Row label="Start date" value={contract.start_date} />
                    <Row label="End date" value={contract.end_date ?? "—"} />
                    <Row label="Trial end" value={contract.trial_end ?? "—"} />
                    <Row label="Auto-renew" value={contract.auto_renew ? "Yes" : "No"} />
                    <Row label="Overage policy" value={contract.overage_policy} />
                  </dl>
                </CardBody>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Usage</CardTitle>
                </CardHeader>
                <CardBody>
                  {subscription.usage.length === 0 ? (
                    <p className="text-sm text-foreground-muted">No usage recorded yet.</p>
                  ) : (
                    <div className="flex flex-col gap-2 text-sm">
                      {subscription.usage.map((row) => (
                        <div
                          key={row.metric}
                          className={`flex items-center justify-between rounded-sm px-2 py-1.5 ${row.exceeded ? "bg-warning-subtle" : ""}`}
                        >
                          <span className="text-foreground">{row.metric}</span>
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
                  <CardTitle>Invoices</CardTitle>
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
