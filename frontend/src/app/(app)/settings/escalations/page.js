import { apiFetch } from "@/lib/api-server";
import { PageHeader } from "@/components/layout/page-header";
import { Card, CardBody } from "@/components/ui/card";
import { EscalationRuleForm } from "@/components/notification/escalation-rule-form";
import { EscalationRulesList } from "@/components/notification/escalation-rules-list";
import { getT } from "@/lib/i18n-server";
import { createRule, toggleRule, deleteRule } from "./actions";

/**
 * Who gets told when nobody answers (SRS 28) — mirrors
 * `EscalationRuleController::index` (`EscalationRuleApiController`, built
 * this pass to back it: no API for this admin-config side existed before,
 * only `NotificationApiController` for the personal inbox).
 */
export default async function EscalationsPage() {
  const [rules, roles, factories, t, tn] = await Promise.all([
    apiFetch("/escalation-rules"),
    apiFetch("/roles?per_page=100"),
    apiFetch("/factories?per_page=100"),
    getT("notification"),
    getT("nav"),
  ]);

  return (
    <>
      <PageHeader
        breadcrumb={[{ label: tn("settings") }, { label: t("escalations") }]}
        title={t("escalations")}
        description={t("escalations_intro")}
      />

      <div className="flex flex-col gap-4">
        <Card>
          <CardBody>
            <EscalationRuleForm roles={roles} factories={factories} action={createRule} />
          </CardBody>
        </Card>

        <EscalationRulesList rules={rules} toggleAction={toggleRule} deleteAction={deleteRule} />
      </div>
    </>
  );
}
