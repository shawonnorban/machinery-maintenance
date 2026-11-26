import { apiFetch, apiFetchAll } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { GroupedRecords } from "@/components/erp/grouped-records";
import * as forms from "./form-actions";
import { approvalWorkflow } from "./designer-screen";
import { ledgerIntegration } from "./ledger-screen";

/*
 * Access Control → Configuration and Operations (AnnotechERP 02/03, 02/04),
 * on the screen kit's resources (`/erp/{resource}`): Report Template Setup,
 * Mail Service Configuration, Rule Violation Log, Global Notification
 * Broadcast and Document Deletion Console — each Brickland's Records, its own
 * grouped tab where it has one, and its New form.
 */

const opt = (value, label) => ({ value: String(value), label });
const choices = (labels) =>
  Object.entries(labels).map(([value, label]) => opt(value, label));

/** A Records + (grouped tab) + New screen over one resource. */
function resourceScreen(config) {
  return async function render({ t, te, tab, searchParams }) {
    const c = (key) => t(`cfg.${key}`);
    const extra = config.load ? await config.load({ c, t }) : {};
    const rows = (await apiFetch(`/erp/${config.resource}`)).map((row) =>
      config.decorate ? config.decorate(row, { c, ...extra }) : row,
    );
    const fields = config.fields({ c, t, ...extra });
    const basePath = `/access-control/${config.screen}`;
    const action = (verb) =>
      forms[verb].bind(null, config.resource, config.booleans ?? []);

    const grid = (list) => (
      <RecordsGrid
        exportName={config.screen}
        rows={list}
        columns={config.columns({ c, t, ...extra })}
        edit={{
          fields: config.editFields
            ? config.editFields({ c, t, ...extra })
            : fields,
          action: action("updateResource"),
          title: c(config.labels.edit),
          submitLabel: c(config.labels.save),
          columns: config.formColumns ?? 2,
        }}
        remove={forms.deleteResource.bind(null, config.resource)}
      />
    );

    return {
      count: rows.length,
      tabs: [
        {
          key: "records",
          label: te("records"),
          icon: "Table2",
          count: rows.length,
        },
        ...(config.group
          ? [{ key: "groups", label: c(config.group.tab), icon: "ListChecks" }]
          : []),
        { key: "new", label: c(config.labels.new), icon: "Plus" },
      ],
      body: () => {
        if (tab === "new") {
          return (
            <EntryForm
              fields={fields}
              action={action("createResource")}
              title={c(config.labels.new)}
              description={c(config.labels.hint)}
              submitLabel={c(config.labels.save)}
              columns={config.formColumns ?? 2}
              successHref={`${basePath}?tab=records`}
            />
          );
        }
        if (tab === "groups" && config.group) {
          const groups = config.group.groups({ c, rows, ...extra });
          const active = searchParams.group ?? null;
          return (
            <GroupedRecords
              groups={groups}
              active={active}
              hrefFor={(key) =>
                `${basePath}?tab=groups&group=${encodeURIComponent(key)}`
              }
              groupsTitle={c(config.group.groupsTitle)}
              recordsTitle={groups.find((g) => g.key === active)?.label ?? ""}
              emptyText={c(config.group.empty)}
            >
              {active
                ? grid(
                    rows.filter(
                      (row) => String(row[config.group.column]) === active,
                    ),
                  )
                : null}
            </GroupedRecords>
          );
        }
        return grid(rows);
      },
    };
  };
}

const reportTemplate = resourceScreen({
  screen: "report-template",
  resource: "report-templates",
  booleans: ["active"],
  labels: {
    new: "new_report_template",
    save: "save_report_template",
    edit: "edit_report_template",
    hint: "report_template_hint",
  },
  fields: ({ c }) => [
    {
      name: "code",
      label: c("template_code"),
      required: true,
      placeholder: "RPT-PO",
    },
    {
      name: "name",
      label: c("template_name"),
      required: true,
      placeholder: "Purchase Order Print",
    },
    {
      name: "report_path",
      label: c("report_path"),
      placeholder: "rptBundleCard",
    },
    {
      name: "output_format",
      label: c("output_format"),
      type: "select",
      required: true,
      options: choices(t2(c, "formats")),
    },
    {
      name: "orientation",
      label: c("orientation"),
      type: "select",
      options: choices(t2(c, "orientations")),
    },
    {
      name: "paper_size",
      label: c("paper_size"),
      type: "select",
      options: choices(t2(c, "papers")),
    },
    { name: "description", label: c("description"), type: "textarea" },
    { name: "active", label: c("active"), type: "checkbox", default: true },
  ],
  columns: ({ c }) => [
    { key: "code", label: c("template_code"), type: "mono" },
    { key: "name", label: c("template_name") },
    { key: "report_path", label: c("report_path"), type: "mono" },
    {
      key: "output_format",
      label: c("output_format"),
      type: "badge",
      labels: t2(c, "formats"),
    },
    {
      key: "paper_size",
      label: c("paper_size"),
      type: "badge",
      labels: t2(c, "papers"),
    },
    {
      key: "orientation",
      label: c("orientation"),
      type: "badge",
      labels: t2(c, "orientations"),
    },
    { key: "updated_at", label: c("updated"), type: "datetime" },
    { key: "active", label: c("status"), type: "status" },
  ],
});

/** A `cfg.*` label map (formats, severities …) for selects and badges. */
function t2(c, key) {
  const MAPS = {
    formats: ["PDF", "EXCEL", "CSV", "HTML"],
    orientations: ["PORTRAIT", "LANDSCAPE"],
    papers: ["A4", "A5", "LETTER", "LEGAL"],
    encryptions: ["NONE", "SSL", "TLS", "STARTTLS"],
    severities: ["LOW", "MEDIUM", "HIGH", "CRITICAL"],
    violation_statuses: ["OPEN", "ACKNOWLEDGED", "WAIVED", "RESOLVED"],
    types: ["INFORMATION", "WARNING", "ALERT", "MAINTENANCE"],
    audiences: ["ALL", "ROLE", "USER"],
    document_types: [
      "GENERAL_PR",
      "TRIMS_ACC_PR",
      "IMPORT_FABRIC_PR",
      "GENERAL_PO",
      "TRIMS_ACC_PO",
      "IMPORT_FABRIC_PO",
      "MRR",
      "GRN",
      "STORE_REQUISITION",
      "SALES_INVOICE",
      "DELIVERY_CHALLAN",
    ],
    deletion_statuses: [
      "DRAFT",
      "PENDING",
      "APPROVED",
      "REJECTED",
      "CANCELLED",
    ],
  };
  return Object.fromEntries(
    MAPS[key].map((value) => [value, c(`${key}.${value}`)]),
  );
}

const mailService = resourceScreen({
  screen: "mail-service",
  resource: "mail-services",
  booleans: ["is_default", "active"],
  formColumns: 3,
  labels: {
    new: "new_mail_service",
    save: "save_mail_service",
    edit: "edit_mail_service",
    hint: "mail_service_hint",
  },
  load: async () => ({
    modules: await apiFetch("/access/modules").catch(() => []),
  }),
  decorate: (row, { modules }) => ({
    ...row,
    relay: `${row.smtp_host}:${row.port}`,
    module_name: modules.find((m) => m.code === row.module_code)?.name ?? null,
  }),
  fields: ({ c, modules }) => [
    {
      name: "service_name",
      label: c("service_name"),
      required: true,
      placeholder: "AutoMailSend4OrderConfirmListService",
    },
    {
      name: "module_code",
      label: c("module_name"),
      type: "select",
      options: modules.map((m) => opt(m.code, m.name)),
    },
    {
      name: "title",
      label: c("title"),
      placeholder: "Order confirmation list",
    },
    {
      name: "to_mail",
      label: c("to_mail"),
      placeholder: "merchant@company.com, md@company.com",
    },
    { name: "cc_mail", label: c("cc_mail") },
    { name: "bcc_mail", label: c("bcc_mail") },
    {
      name: "smtp_host",
      label: c("smtp_host"),
      required: true,
      placeholder: "smtp.company.com",
    },
    {
      name: "port",
      label: c("port"),
      type: "number",
      required: true,
      placeholder: "587",
    },
    {
      name: "smtp_username",
      label: c("smtp_username"),
      required: true,
      placeholder: "erp-notifications",
    },
    {
      name: "password_env",
      label: c("password_env"),
      required: true,
      placeholder: "SMTP_PASSWORD",
      help: c("password_env_help"),
    },
    {
      name: "encryption",
      label: c("encryption"),
      type: "select",
      required: true,
      options: choices(t2(c, "encryptions")),
    },
    {
      name: "from_name",
      label: c("from_name"),
      required: true,
      placeholder: "Annotech ERP",
    },
    {
      name: "from_address",
      label: c("from_address"),
      type: "email",
      required: true,
      placeholder: "no-reply@company.com",
    },
    {
      name: "reply_to",
      label: c("reply_to"),
      type: "email",
      placeholder: "it-support@company.com",
    },
    { name: "remarks", label: c("remarks"), type: "textarea" },
    {
      name: "is_default",
      label: c("default_relay"),
      type: "checkbox",
      help: c("default_relay_help"),
    },
    { name: "active", label: c("active"), type: "checkbox", default: true },
  ],
  columns: ({ c }) => [
    { key: "service_name", label: c("service_name"), type: "mono" },
    { key: "module_name", label: c("module_name") },
    { key: "title", label: c("title") },
    { key: "to_mail", label: c("to_mail") },
    { key: "cc_mail", label: c("cc_mail") },
    { key: "bcc_mail", label: c("bcc_mail") },
    { key: "relay", label: c("relay"), type: "mono" },
    { key: "password_env", label: c("password_source"), type: "mono" },
    { key: "remarks", label: c("remarks") },
    { key: "created_at", label: c("created"), type: "datetime" },
    { key: "active", label: c("status"), type: "status" },
  ],
});

const ruleViolation = resourceScreen({
  screen: "rule-violation",
  resource: "rule-violations",
  labels: {
    new: "record_violation",
    save: "save_violation",
    edit: "edit_violation",
    hint: "violation_hint",
  },
  load: async () => {
    const tables = await apiFetch("/access/lookups").catch(() => []);
    const table = tables.find((tb) => tb.code === "BUSINESS_RULE");
    const values = table
      ? (await apiFetch(`/access/lookups/${table.id}/values`)).values
      : [];
    return { rules: values.map((v) => ({ code: v.code, name: v.name })) };
  },
  decorate: (row, { rules }) => ({
    ...row,
    rule: `${row.rule_code} — ${rules.find((r) => r.code === row.rule_code)?.name ?? ""}`,
  }),
  fields: ({ c, rules }) => [
    {
      name: "rule_code",
      label: c("business_rule"),
      type: "select",
      required: true,
      options: rules.map((r) => opt(r.code, `${r.code} — ${r.name}`)),
      span: 2,
    },
    {
      name: "document_reference",
      label: c("document_reference"),
      required: true,
      placeholder: "PO-261024",
    },
    {
      name: "severity",
      label: c("severity"),
      type: "select",
      required: true,
      options: choices(t2(c, "severities")),
    },
    {
      name: "status",
      label: c("status"),
      type: "select",
      options: choices(t2(c, "violation_statuses")),
      default: "OPEN",
    },
    {
      name: "detail",
      label: c("detail"),
      type: "textarea",
      required: true,
      placeholder:
        "Purchase order raised above the approved requisition value…",
    },
  ],
  columns: ({ c }) => [
    { key: "rule", label: c("business_rule") },
    { key: "document_reference", label: c("document_reference"), type: "mono" },
    {
      key: "severity",
      label: c("severity"),
      type: "badge",
      labels: t2(c, "severities"),
    },
    {
      key: "status",
      label: c("status"),
      type: "badge",
      labels: t2(c, "violation_statuses"),
    },
    { key: "detail", label: c("detail") },
    { key: "raised_by_name", label: c("raised_by") },
    { key: "created_at", label: c("created"), type: "datetime" },
  ],
  group: {
    tab: "rules_violation",
    column: "rule_code",
    groupsTitle: "rule_scopes",
    empty: "choose_scope",
    groups: ({ rows, rules }) =>
      rules.map((r) => ({
        key: r.code,
        label: r.name,
        sub: r.code,
        count: rows.filter((row) => row.rule_code === r.code).length,
      })),
  },
});

const globalNotification = resourceScreen({
  screen: "global-notification",
  resource: "broadcasts",
  booleans: ["active"],
  labels: {
    new: "send_to_all",
    save: "send_notification",
    edit: "edit_notification",
    hint: "notification_hint",
  },
  load: async () => {
    const [roles, users] = await Promise.all([
      apiFetch("/roles?per_page=100").catch(() => []),
      apiFetchAll("/users?sort=name").catch(() => []),
    ]);
    return { roles, users };
  },
  decorate: (row, { roles, users }) => ({
    ...row,
    audience_label:
      row.audience === "ROLE"
        ? roles.find((r) => String(r.id) === String(row.audience_id))?.name
        : row.audience === "USER"
          ? users.find((u) => u.id === row.audience_id)?.name
          : null,
  }),
  fields: ({ c, roles, users }) => [
    {
      name: "title",
      label: c("notification_title"),
      required: true,
      placeholder: "Planned maintenance window",
    },
    {
      name: "notification_type",
      label: c("notification_type"),
      type: "select",
      required: true,
      options: choices(t2(c, "types")),
    },
    {
      name: "message",
      label: c("notification_message"),
      type: "textarea",
      required: true,
      placeholder: "The ERP will be unavailable on Friday from 22:00 to 02:00…",
    },
    {
      name: "audience",
      label: c("audience"),
      type: "select",
      required: true,
      options: choices(t2(c, "audiences")),
      default: "ALL",
    },
    {
      name: "audience_id",
      label: c("audience_target"),
      type: "select",
      help: c("audience_target_help"),
      options: [
        ...roles.map((r) => opt(r.id, `${c("audiences.ROLE")}: ${r.name}`)),
        ...users.map((u) => opt(u.id, `${c("audiences.USER")}: ${u.name}`)),
      ],
    },
    {
      name: "severity",
      label: c("severity"),
      type: "select",
      options: choices(t2(c, "severities")),
    },
    {
      name: "message_time",
      label: c("message_time"),
      type: "date",
      required: true,
      default: new Date().toISOString().slice(0, 10),
    },
    { name: "offline_time", label: c("offline_time"), type: "date" },
    { name: "active", label: c("active"), type: "checkbox", default: true },
  ],
  columns: ({ c }) => [
    { key: "title", label: c("notification_title") },
    {
      key: "notification_type",
      label: c("notification_type"),
      type: "badge",
      labels: t2(c, "types"),
    },
    {
      key: "audience",
      label: c("audience"),
      type: "badge",
      labels: t2(c, "audiences"),
    },
    { key: "audience_label", label: c("audience_target") },
    {
      key: "severity",
      label: c("severity"),
      type: "badge",
      labels: t2(c, "severities"),
    },
    { key: "message_time", label: c("message_time"), type: "date" },
    { key: "offline_time", label: c("offline_time"), type: "date" },
    { key: "recipients_count", label: c("recipients"), type: "number" },
    { key: "sent_by_name", label: c("sent_by") },
    { key: "active", label: c("status"), type: "status" },
  ],
});

const documentDeletion = resourceScreen({
  screen: "document-deletion",
  resource: "deletion-requests",
  labels: {
    new: "raise_deletion_request",
    save: "save_deletion_request",
    edit: "edit_deletion_request",
    hint: "deletion_hint",
  },
  fields: ({ c }) => [
    {
      name: "document_type",
      label: c("document_type"),
      type: "select",
      required: true,
      options: choices(t2(c, "document_types")),
    },
    {
      name: "document_no",
      label: c("document_no"),
      required: true,
      placeholder: "PO-261024",
    },
    {
      name: "reason",
      label: c("reason"),
      type: "textarea",
      required: true,
      placeholder:
        "Raised in error against the wrong buyer; replaced by PO-261031.",
    },
    {
      name: "status",
      label: c("status"),
      type: "select",
      options: choices(t2(c, "deletion_statuses")),
      default: "DRAFT",
    },
    {
      name: "approval_remarks",
      label: c("approval_remarks"),
      type: "textarea",
    },
  ],
  columns: ({ c }) => [
    {
      key: "document_type",
      label: c("document_type"),
      type: "badge",
      labels: t2(c, "document_types"),
    },
    { key: "document_no", label: c("document_no"), type: "mono" },
    { key: "reason", label: c("reason") },
    {
      key: "status",
      label: c("status"),
      type: "badge",
      labels: t2(c, "deletion_statuses"),
    },
    { key: "approval_remarks", label: c("approval_remarks") },
    { key: "requested_by_name", label: c("requested_by") },
    { key: "created_at", label: c("created"), type: "datetime" },
  ],
  group: {
    tab: "deletion_admin_panel",
    column: "document_type",
    groupsTitle: "deletion_types",
    empty: "choose_type",
    groups: ({ c, rows }) =>
      Object.entries(t2(c, "document_types")).map(([key, label]) => ({
        key,
        label,
        count: rows.filter((row) => row.document_type === key).length,
      })),
  },
});

const CONFIG_SCREENS = {
  "approval-workflow": approvalWorkflow,
  "report-template": reportTemplate,
  "mail-service": mailService,
  "ledger-integration": ledgerIntegration,
  "rule-violation": ruleViolation,
  "global-notification": globalNotification,
  "document-deletion": documentDeletion,
};

export { CONFIG_SCREENS };
