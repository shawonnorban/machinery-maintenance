import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { SubjectMatrix } from "@/components/access/subject-matrix";
import { GreyTransferRules } from "@/components/access/grey-transfer-rules";
import * as forms from "./form-actions";

/*
 * Access Control → Mappings (AnnotechERP 02/02), Brickland's layout for each:
 * Records | "User & X Mapping" (choose a subject, tick the whole list) |
 * "New X mapping". The user and buyer kinds share one engine
 * (`/access/mappings/{kind}`); User Company Mapping works on memberships
 * instead. Buyer-Wise Fabric Transaction Rights' middle tab is Brickland's
 * grey fabric transfer rules per buyer. A buyer or a store is addressed in
 * the URL by its code.
 */

const KINDS = {
  "user-rule-mapping": {
    kind: "user-rule",
    subject: "user",
    target: "business_rule",
    flags: ["override"],
    help: { override: "override" },
    extras: [],
  },
  "user-requisition-mapping": {
    kind: "user-requisition",
    subject: "user",
    target: "requisition_type",
    flags: ["raise", "approve"],
    extras: ["amount"],
  },
  "user-store-mapping": {
    kind: "user-store",
    subject: "user",
    target: "store",
    flags: ["issue", "receive", "adjust"],
    help: { adjust: "adjust" },
    extras: [],
  },
  "user-production-category-mapping": {
    kind: "user-production-category",
    subject: "user",
    target: "production_category",
    flags: ["plan", "report"],
    extras: [],
  },
  "menu-report-mapping": {
    kind: "menu-report",
    subject: "menu",
    target: "report",
    flags: [],
    extras: ["sort_order", "is_default"],
    defaultHelp: "default_report",
  },
  "buyer-fabric-rights": {
    kind: "user-buyer-fabric",
    subject: "user",
    target: "buyer",
    flags: ["issue", "receive", "transfer", "return"],
    extras: ["date_from", "date_to"],
    grey: "buyer-grey-transfer",
  },
  "fabric-defect-mapping": {
    kind: "buyer-defect",
    subject: "buyer",
    target: "fabric_defect",
    flags: ["critical", "active"],
    help: { critical: "critical" },
    extras: ["amount", "sort_order"],
    select: true,
    amountLabel: "penalty_points",
    amountHelp: "penalty_points_help",
    orderHelp: "defect_order_help",
    order: ["amount", "flag_critical", "sort_order", "flag_active"],
  },
  "buyer-store-mapping": {
    kind: "store-buyer",
    subject: "store",
    target: "buyer",
    flags: [],
    extras: ["is_default"],
    defaultHelp: "default_store",
  },
  "buyer-item-category-mapping": {
    kind: "buyer-item-category",
    subject: "buyer",
    target: "item_category",
    flags: ["sub_style", "active"],
    help: { sub_style: "sub_style" },
    extras: ["text"],
    select: true,
    order: ["text", "flag_sub_style", "flag_active"],
  },
};

const CHOOSE = {
  user: "choose_user",
  menu: "choose_menu",
  buyer: "choose_buyer",
  store: "choose_store",
};

function mappingScreen(screenKey) {
  const config = KINDS[screenKey];

  return async function render({ t, te, tab, searchParams }) {
    const m = (key) => t(`mapping.${key}`);
    const tabs = (key) => t(`mapping.tabs.${screenKey}.${key}`);
    const [rows, options] = await Promise.all([
      apiFetch(`/access/mappings/${config.kind}`),
      apiFetch(`/access/mappings/${config.kind}/options`),
    ]);
    const flagFields = config.flags.map((flag) => ({
      name: `flag_${flag}`,
      label: m(`flags.${flag}`),
      type: "checkbox",
      default: options.flag_defaults?.[flag] ?? false,
      help: config.help?.[flag]
        ? m(`flag_help.${config.help[flag]}`)
        : undefined,
    }));
    const amountLabel = m(config.amountLabel ?? "approval_limit");
    const extraFields = [
      ...(config.extras.includes("amount")
        ? [
            {
              name: "amount",
              label: amountLabel,
              type: "number",
              required: options.required?.includes("amount"),
              help: m(config.amountHelp ?? "approval_limit_help"),
            },
          ]
        : []),
      ...(config.extras.includes("text")
        ? [
            {
              name: "text",
              label: m("nomination_code"),
              placeholder: "HM-TRIM-2026",
              help: m("nomination_code_help"),
            },
          ]
        : []),
      ...(config.extras.includes("date_from")
        ? [
            {
              name: "date_from",
              label: m("effective_from"),
              type: "date",
              required: true,
            },
          ]
        : []),
      ...(config.extras.includes("date_to")
        ? [
            {
              name: "date_to",
              label: m("effective_to"),
              type: "date",
              help: m("effective_to_help"),
            },
          ]
        : []),
      ...(config.extras.includes("sort_order")
        ? [
            {
              name: "sort_order",
              label: m("display_order"),
              type: "number",
              placeholder: "10",
              help: config.orderHelp ? m(config.orderHelp) : undefined,
            },
          ]
        : []),
      ...(config.extras.includes("is_default")
        ? [
            {
              name: "is_default",
              label: m("default"),
              type: "checkbox",
              help: m(`flag_help.${config.defaultHelp}`),
            },
          ]
        : []),
    ];
    // Brickland's field order where it mixes rights and extras (Penalty Points, Critical, Display Order, Active).
    const rowFields = config.order
      ? config.order
          .map((name) =>
            [...flagFields, ...extraFields].find((f) => f.name === name),
          )
          .filter(Boolean)
      : [...flagFields, ...extraFields];
    const subjectLabel = m(config.subject);
    const targetLabel = m(config.target);
    const basePath = `/access-control/${screenKey}`;
    // ?subject= is a buyer's or a store's code, or (for users and menus) its id.
    const subjectId =
      options.subjects.find(
        (s) =>
          s.key === searchParams.subject || s.value === searchParams.subject,
      )?.value ?? null;
    const extraColumns = [
      ...(config.extras.includes("amount")
        ? [{ key: "amount", label: amountLabel, type: "number" }]
        : []),
      ...(config.extras.includes("text")
        ? [{ key: "text", label: m("nomination_code") }]
        : []),
      ...(config.extras.includes("date_from")
        ? [{ key: "date_from", label: m("effective_from"), type: "date" }]
        : []),
      ...(config.extras.includes("date_to")
        ? [{ key: "date_to", label: m("effective_to"), type: "date" }]
        : []),
      ...(config.extras.includes("sort_order")
        ? [{ key: "sort_order", label: m("display_order"), type: "number" }]
        : []),
      ...(config.extras.includes("is_default")
        ? [{ key: "is_default", label: m("default"), type: "yesno" }]
        : []),
    ];

    return {
      count: rows.length,
      tabs: [
        {
          key: "records",
          label: te("records"),
          icon: "Table2",
          count: rows.length,
        },
        { key: "matrix", label: tabs("matrix"), icon: "ListChecks" },
        { key: "new", label: tabs("new"), icon: "Plus" },
      ],
      body: async () => {
        if (tab === "matrix" && config.grey) {
          const [greyRules, greyOptions] = await Promise.all([
            apiFetch(`/access/mappings/${config.grey}`),
            apiFetch(`/access/mappings/${config.grey}/options`),
          ]);
          const g = (key) => t(`mapping.grey.${key}`);
          return (
            <GreyTransferRules
              buyers={greyOptions.subjects}
              rules={greyRules}
              pairs={greyOptions.targets}
              save={forms.saveMappingMatrix.bind(null, config.grey)}
              labels={Object.fromEntries(
                [
                  "accounts",
                  "rules",
                  "rules_for",
                  "hint",
                  "pick",
                  "pick_hint",
                  "add",
                  "add_for",
                  "new",
                  "none",
                  "none_hint",
                  "count",
                  "refusal",
                  "from",
                  "to",
                  "active",
                  "same",
                  "exists",
                  "revert",
                  "save",
                  "remove",
                  "saved",
                ].map((k) => [k, g(k)]),
              )}
            />
          );
        }
        if (tab === "matrix") {
          const matrix = subjectId
            ? await apiFetch(
                `/access/mappings/${config.kind}/subjects/${subjectId}`,
              ).catch(() => null)
            : null;
          const hintKey = `mapping.matrix_hints.${screenKey}`;
          return (
            <SubjectMatrix
              key={subjectId ?? "none"}
              basePath={basePath}
              subjects={options.subjects}
              subjectId={subjectId}
              rows={matrix}
              flags={config.flags.map((flag) => ({
                key: flag,
                label: m(`flags.${flag}`),
              }))}
              extras={config.extras}
              select={Boolean(config.select)}
              flagDefaults={options.flag_defaults ?? {}}
              amountRange={options.amount_range}
              save={forms.saveMappingMatrix.bind(null, config.kind)}
              labels={{
                title: tabs("matrix"),
                hint:
                  t(hintKey) === hintKey
                    ? t(`descriptions.${screenKey}`)
                    : t(hintKey),
                subject: subjectLabel,
                choose: m(CHOOSE[config.subject]),
                target: targetLabel,
                selected: m("selected"),
                amount: amountLabel,
                order: m("display_order"),
                text: m("nomination_code"),
                from: m("effective_from"),
                to: m("effective_to"),
                defaultLabel: m("default"),
                empty: m("choose_to_begin"),
                emptyHint: m("choose_to_begin_hint"),
                saved: m("saved"),
              }}
            />
          );
        }
        if (tab === "new") {
          return (
            <EntryForm
              fields={[
                {
                  name: "subject_id",
                  label: subjectLabel,
                  type: "select",
                  required: true,
                  options: options.subjects,
                },
                {
                  name: "target_id",
                  label: targetLabel,
                  type: "select",
                  required: true,
                  options: options.targets,
                },
                ...rowFields,
              ]}
              action={forms.createMapping.bind(null, config.kind, config.flags)}
              title={tabs("new")}
              description={m(`hints.${screenKey}`)}
              submitLabel={tabs("save")}
              columns={2}
              successHref={`${basePath}?tab=records`}
            />
          );
        }
        return (
          <RecordsGrid
            exportName={screenKey}
            rows={rows}
            columns={[
              { key: "subject", label: subjectLabel },
              { key: "target", label: targetLabel },
              ...config.flags.map((flag) => ({
                key: `flag_${flag}`,
                label: m(`flags.${flag}`),
                type: "yesno",
              })),
              ...extraColumns,
              { key: "updated_at", label: t("ui.updated"), type: "datetime" },
            ]}
            edit={{
              fields: [
                { name: "subject", label: subjectLabel, readOnly: true },
                { name: "target", label: targetLabel, readOnly: true },
                ...rowFields,
              ],
              action: forms.updateMapping.bind(null, config.kind, config.flags),
              title: tabs("edit"),
              submitLabel: tabs("save"),
            }}
            remove={forms.deleteMapping.bind(null, config.kind)}
          />
        );
      },
    };
  };
}

async function userCompanyMapping({ t, te, tab, searchParams }) {
  const m = (key) => t(`mapping.${key}`);
  const tabs = (key) => t(`mapping.tabs.user-company-mapping.${key}`);
  const [rows, options, me] = await Promise.all([
    apiFetch("/access/user-companies"),
    apiFetch("/access/user-companies/options"),
    apiFetch("/auth/me"),
  ]);
  const basePath = "/access-control/user-company-mapping";
  const defaultField = {
    name: "is_default",
    label: m("default"),
    type: "checkbox",
    help: m("flag_help.default_company"),
  };

  return {
    count: rows.length,
    tabs: [
      {
        key: "records",
        label: te("records"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "matrix", label: tabs("matrix"), icon: "ListChecks" },
      { key: "new", label: tabs("new"), icon: "Plus" },
    ],
    body: async () => {
      if (tab === "matrix") {
        const userId = searchParams.subject ?? null;
        const mine = userId ? rows.filter((r) => r.user_id === userId) : [];
        const companies = new Map([
          ...options.companies.map((c) => [c.value, c.label]),
          ...mine.map((r) => [r.company_id, r.company]),
        ]);
        const matrix = userId
          ? [...companies.entries()].map(([companyId, name]) => {
              const membership = mine.find((r) => r.company_id === companyId);
              return {
                target_id: companyId,
                target: name,
                selected: Boolean(membership),
                is_default: Boolean(membership?.is_default),
                was_default: Boolean(membership?.is_default),
                membership_id: membership?.id ?? null,
                // Leaving this company is done from User Account Registration.
                current: companyId === me.company_id,
              };
            })
          : null;
        return (
          <SubjectMatrix
            key={userId ?? "none"}
            basePath={basePath}
            subjects={options.users}
            subjectId={userId}
            rows={matrix}
            flags={[]}
            extras={["is_default"]}
            save={forms.saveCompanyMatrix}
            labels={{
              title: tabs("matrix"),
              hint: t("descriptions.user-company-mapping"),
              subject: m("user"),
              choose: m("choose_user"),
              target: m("company"),
              selected: m("selected"),
              defaultLabel: m("default"),
              empty: m("choose_to_begin"),
              emptyHint: m("choose_to_begin_hint"),
              saved: m("saved"),
            }}
          />
        );
      }
      if (tab === "new") {
        return (
          <EntryForm
            fields={[
              {
                name: "user_id",
                label: m("user"),
                type: "select",
                required: true,
                options: options.users,
              },
              {
                name: "company_id",
                label: m("company"),
                type: "select",
                required: true,
                options: options.companies,
              },
              defaultField,
            ]}
            action={forms.createCompanyMapping}
            title={tabs("new")}
            description={m("hints.user-company-mapping")}
            submitLabel={tabs("save")}
            successHref={`${basePath}?tab=records`}
          />
        );
      }
      return (
        <RecordsGrid
          exportName="user-company-mapping"
          rows={rows}
          lockedField="locked"
          columns={[
            { key: "user", label: m("user") },
            { key: "company", label: m("company") },
            { key: "is_default", label: m("default"), type: "yesno" },
          ]}
          edit={{
            fields: [
              { name: "user", label: m("user"), readOnly: true },
              { name: "company", label: m("company"), readOnly: true },
              defaultField,
            ],
            action: forms.updateCompanyMapping,
            title: tabs("edit"),
            submitLabel: tabs("save"),
          }}
          remove={forms.deleteCompanyMapping}
        />
      );
    },
  };
}

const MAPPING_SCREENS = {
  ...Object.fromEntries(
    Object.keys(KINDS).map((key) => [key, mappingScreen(key)]),
  ),
  "user-company-mapping": userCompanyMapping,
};

export { MAPPING_SCREENS };
