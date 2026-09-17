import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { Alert } from "@/components/ui/alert";
import { DecisionForms } from "@/components/approvals/decision-forms";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { getT } from "@/lib/i18n-server";
import { approveRequest, rejectRequest } from "./actions";

/** Mirrors `ApprovalController::show` (SRS 14). */
export default async function ApprovalDetailPage({ params }) {
  const { approvalId } = await params;

  const [approval, me, t] = await Promise.all([
    apiFetch(`/approval-requests/${approvalId}`),
    apiFetch("/auth/me"),
    getT("approval"),
  ]);

  const isPending = approval.status === "PENDING";
  const isOwnRequest = approval.requested_by === me.user?.id;

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("approvals"), href: "/approvals" }, { label: approval.work_order?.work_order_number ?? t("approval_fallback") }]}
        title={approval.work_order?.work_order_number ?? t("approval_fallback")}
        description={approval.work_order?.title}
        actions={<StatusBadge status={approval.status} label={t(`status_${approval.status?.toLowerCase()}`)} />}
      />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <div className="flex flex-col gap-4 lg:col-span-7">
          <Card>
            <CardHeader>
              <CardTitle>{t("context")}</CardTitle>
            </CardHeader>
            <CardBody>
              <dl className="grid grid-cols-2 gap-y-3 text-sm">
                <dt className="text-foreground-muted">{t("cost")}</dt>
                <dd className="font-medium text-foreground">
                  {approval.context?.cost !== undefined
                    ? `${Number(approval.context.cost).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${approval.context.currency ?? ""}`
                    : "—"}
                </dd>

                <dt className="text-foreground-muted">{t("criticality")}</dt>
                <dd className="text-foreground">{approval.context?.criticality ?? "—"}</dd>

                <dt className="text-foreground-muted">{t("priority")}</dt>
                <dd className="text-foreground">{approval.context?.priority ?? "—"}</dd>

                <dt className="text-foreground-muted">{t("requested_at")}</dt>
                <dd className="text-foreground">
                  <FormattedDateTime value={approval.requested_at} />
                </dd>
              </dl>
              {/* Frozen when the request was raised — an estimate edited afterward would make "what did they actually agree to" unanswerable. */}
              <p className="mt-3 text-xs text-foreground-subtle">{t("frozen_figures_note")}</p>
            </CardBody>
          </Card>

          {approval.can_act ? (
            <Card>
              <CardBody>
                <DecisionForms
                  step={approval.current_step}
                  totalSteps={approval.total_steps}
                  approveAction={approveRequest.bind(null, approval.id)}
                  rejectAction={rejectRequest.bind(null, approval.id)}
                />
              </CardBody>
            </Card>
          ) : isPending ? (
            <Alert variant="info">
              {isOwnRequest ? t("cannot_approve_own_request") : t("not_your_step")}
            </Alert>
          ) : null}
        </div>

        <div className="flex flex-col gap-4 lg:col-span-5">
          <Card>
            <CardHeader>
              <CardTitle>{t("workflow")}</CardTitle>
            </CardHeader>
            <CardBody className="flex flex-col gap-2">
              {(approval.steps ?? []).map((step) => (
                <div key={step.step} className="flex items-center gap-2 text-sm">
                  <span className={step.step < approval.current_step ? "text-success" : "text-foreground"}>
                    {step.step}. {step.name ?? "—"}
                  </span>
                  {step.step < approval.current_step ? (
                    <span className="ml-auto text-xs text-success">{t("action_approved")}</span>
                  ) : step.step === approval.current_step && isPending ? (
                    <span className="ml-auto text-xs text-warning">{t("awaiting_signature")}</span>
                  ) : null}
                </div>
              ))}
            </CardBody>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>{t("history")}</CardTitle>
            </CardHeader>
            <CardBody className="flex flex-col gap-3">
              {(approval.actions ?? []).length === 0 ? (
                <p className="text-sm text-foreground-muted">{t("no_decisions_yet")}</p>
              ) : (
                approval.actions.map((action) => (
                  <div key={action.id} className="text-sm">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-medium text-foreground">{t(`action_${action.action?.toLowerCase()}`)}</span>
                      <span className="text-xs text-foreground-muted">{t("step")} {action.step}</span>
                      <span className="ml-auto text-xs text-foreground-muted">
                        <FormattedDateTime value={action.acted_at} />
                      </span>
                    </div>
                    {action.comment ? <p className="mt-1 text-xs text-foreground-muted">{action.comment}</p> : null}
                  </div>
                ))
              )}
            </CardBody>
          </Card>
        </div>
      </div>
    </>
  );
}
