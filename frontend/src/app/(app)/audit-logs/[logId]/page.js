import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { getT } from "@/lib/i18n-server";

function formatValue(value) {
  if (value === null || value === undefined) return "—";
  return typeof value === "object" ? JSON.stringify(value) : String(value);
}

/** Mirrors `AuditLogController::show` (SRS 34, ADR-061: one request id resolves the whole causal chain). */
export default async function AuditLogDetailPage({ params }) {
  const { logId } = await params;
  const [log, t] = await Promise.all([
    apiFetch(`/audit-logs/${logId}`),
    getT("audit"),
  ]);

  const changedEntries = Object.entries(log.changed_fields ?? {});
  const newValueEntries = Object.entries(log.new_values ?? {});

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: t("audit_log"), href: "/audit-logs" }, { label: t(`actions.${log.action}`) }]}
        title={t(`actions.${log.action}`)}
        description={log.entity_label}
      />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
        <div className="lg:col-span-5">
          <Card>
            <CardBody>
              <dl className="grid grid-cols-2 gap-y-3 text-sm">
                <dt className="text-foreground-muted">{t("when")}</dt>
                <dd className="text-foreground"><FormattedDateTime value={log.created_at} /></dd>

                <dt className="text-foreground-muted">{t("who")}</dt>
                <dd className="text-foreground">{log.user?.name ?? t("system")}</dd>

                {log.impersonated_by ? (
                  <>
                    <dt className="text-danger">{t("impersonated_by")}</dt>
                    <dd className="text-danger">{log.impersonated_by.name}</dd>
                  </>
                ) : null}

                <dt className="text-foreground-muted">{t("entity_type")}</dt>
                <dd className="text-foreground">{log.entity_type ?? "—"}</dd>

                <dt className="text-foreground-muted">{t("entity_id")}</dt>
                <dd className="font-mono text-xs text-foreground">{log.entity_id ?? "—"}</dd>

                <dt className="text-foreground-muted">{t("context")}</dt>
                <dd className="text-foreground">{t(`contexts.${log.context}`)}</dd>

                <dt className="text-foreground-muted">{t("ip_address")}</dt>
                <dd className="text-foreground">{log.ip_address ?? "—"}</dd>

                <dt className="text-foreground-muted">{t("request_id")}</dt>
                <dd className="font-mono text-xs text-foreground">{log.request_id ?? "—"}</dd>

                <dt className="text-foreground-muted">{t("user_agent")}</dt>
                <dd className="text-xs text-foreground-muted">{log.user_agent ?? "—"}</dd>
              </dl>
            </CardBody>
          </Card>
        </div>

        <div className="flex flex-col gap-4 lg:col-span-7">
          <Card>
            <CardHeader>
              <CardTitle>{t("changes")}</CardTitle>
            </CardHeader>
            <CardBody>
              {changedEntries.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="text-left text-xs text-foreground-muted">
                        <th className="pb-2">{t("field")}</th>
                        <th className="pb-2">{t("before")}</th>
                        <th className="pb-2">{t("after")}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {changedEntries.map(([field, [before, after]]) => (
                        <tr key={field} className="border-t border-border">
                          <td className="py-1.5 font-mono text-xs">{field}</td>
                          <td className="py-1.5 text-foreground-muted">{formatValue(before)}</td>
                          <td className="py-1.5 text-foreground">{formatValue(after)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : newValueEntries.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <tbody>
                      {newValueEntries.map(([field, value]) => (
                        <tr key={field} className="border-t border-border first:border-t-0">
                          <td className="py-1.5 pr-4 font-mono text-xs">{field}</td>
                          <td className="py-1.5 text-foreground">{formatValue(value)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <p className="text-sm text-foreground-muted">{t("no_changes")}</p>
              )}
            </CardBody>
          </Card>

          {log.related?.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle>{t("related")}</CardTitle>
              </CardHeader>
              <CardBody className="flex flex-col gap-2">
                {log.related.map((sibling) => (
                  <a key={sibling.id} href={`/audit-logs/${sibling.id}`} className="flex items-center gap-2 text-sm hover:underline">
                    <Badge variant={ACTION_TONE[sibling.action] ?? "neutral"}>{t(`actions.${sibling.action}`)}</Badge>
                    <span className="text-foreground">{sibling.entity_label ?? sibling.entity_type}</span>
                    <span className="ml-auto text-xs text-foreground-muted">
                      <FormattedDateTime value={sibling.created_at} />
                    </span>
                  </a>
                ))}
                <p className="mt-1 text-xs text-foreground-subtle">{t("related_hint")}</p>
              </CardBody>
            </Card>
          ) : null}
        </div>
      </div>
    </>
  );
}

const ACTION_TONE = {
  DELETED: "danger",
  LOGIN_FAILED: "danger",
  SECURITY_EVENT: "danger",
  CREATED: "success",
  COST_CHANGED: "warning",
  PERMISSION_CHANGED: "warning",
};
