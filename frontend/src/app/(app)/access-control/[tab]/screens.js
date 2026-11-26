import { apiFetch, apiFetchAll } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { RoleMenuMatrix } from "@/components/access/role-menu-matrix";
import { MenuDefinition } from "@/components/access/menu-definition";
import { LookupTables } from "@/components/access/lookup-tables";
import { SystemParameters } from "@/components/access/system-parameters";
import { CategoryPermissionMatrix } from "@/components/access/category-permission-matrix";
import { RoleAssignments } from "@/components/users/role-assignments";
import { UserPicker } from "@/components/access/user-picker";
import * as actions from "./actions";
import * as forms from "./form-actions";
import { MAPPING_SCREENS } from "./mapping-screens";
import { CONFIG_SCREENS } from "./config-screens";
import { tnaTaskStatus } from "./tna-screen";

/*
 * Access Control's nine Brickland screens (AnnotechERP UI parity): for each,
 * its tabs ("Records N", its own tabs, "+ New X") and the body of the chosen
 * tab, built from the shared screen kit. A screen answers
 * `{ tabs, body, count }`; the page draws the header and the tab strip.
 */

const FLAGS = ["V", "C", "E", "D", "A", "P", "X"];
const opt = (value, label) => ({ value: String(value), label });

function field(name, label, type = "text", extra = {}) {
  return { name, label, type, ...extra };
}

async function moduleRegistration({ t, te }) {
  const modules = await apiFetch("/access/modules");
  const fields = [
    field("code", t("module_fields.code"), "text", { readOnly: true }),
    field("name", t("module_fields.name"), "text", { required: true }),
    field("name_bn", t("module_fields.name_bn")),
    field("icon", t("module_fields.icon"), "text", {
      placeholder: "ShieldCheck",
    }),
    field("sort_order", t("module_fields.sort_order"), "number"),
    field("description", t("module_fields.description"), "textarea"),
    field("active", t("module_fields.active"), "checkbox"),
  ];
  return {
    count: modules.length,
    tabs: [
      {
        key: "records",
        label: te("records"),
        icon: "Table2",
        count: modules.length,
      },
    ],
    body: () => (
      <RecordsGrid
        exportName="modules"
        rows={modules}
        columns={[
          { key: "code", label: t("module_fields.code"), type: "mono" },
          { key: "name", label: t("module_fields.name") },
          { key: "slug", label: t("module_fields.slug") },
          { key: "icon", label: t("module_fields.icon") },
          {
            key: "menus_count",
            label: t("module_fields.menus_count"),
            type: "number",
          },
          {
            key: "sort_order",
            label: t("module_fields.sort_order"),
            type: "number",
          },
          { key: "active", label: t("ui.status"), type: "status" },
        ]}
        edit={{
          fields,
          action: forms.updateModule,
          title: t("ui.edit_module"),
          submitLabel: t("ui.save_module"),
        }}
      />
    ),
  };
}

async function menuDefinition({ t, te, tab }) {
  const modules = await apiFetch("/access/menus");
  const names = Object.fromEntries(
    modules.flatMap((m) => m.menus.map((menu) => [menu.id, menu.name])),
  );
  const rows = modules.flatMap((m) =>
    m.menus.map((menu) => ({
      ...menu,
      module: m.name,
      parent: menu.parent_id ? names[menu.parent_id] : null,
      level: menu.parent_id ? 2 : 1,
    })),
  );
  const levels = Object.fromEntries(
    ["PUBLIC", "INTERNAL", "RESTRICTED", "CONFIDENTIAL"].map((l) => [
      l,
      t(`ui.security_levels.${l}`),
    ]),
  );
  const fields = [
    field("code", t("ui.code"), "text", { readOnly: true }),
    field("name", t("menu_fields.name"), "text", { required: true }),
    field("name_bn", t("menu_fields.name_bn")),
    field("security_level", t("ui.security_level"), "select", {
      options: Object.entries(levels).map(([v, l]) => opt(v, l)),
    }),
    field("icon", t("menu_fields.icon"), "text", { placeholder: "UserCog" }),
    field("sort_order", t("menu_fields.sort_order"), "number"),
    field("description", t("ui.description"), "textarea"),
    field("open_new_window", t("ui.open_new_window"), "checkbox"),
    field("active", t("menu_fields.active"), "checkbox"),
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
      { key: "tree", label: t("ui.menu_tree"), icon: "ListTree" },
    ],
    body: () =>
      tab === "tree" ? (
        <MenuDefinition modules={modules} saveMenus={actions.saveMenus} />
      ) : (
        <RecordsGrid
          exportName="menus"
          rows={rows}
          columns={[
            { key: "code", label: t("ui.code"), type: "mono" },
            { key: "name", label: t("menu_fields.name") },
            { key: "module", label: t("ui.module") },
            { key: "parent", label: t("ui.parent") },
            { key: "url", label: t("menu_fields.url"), type: "mono" },
            {
              key: "security_level",
              label: t("ui.security_level"),
              type: "badge",
              labels: levels,
            },
            { key: "level", label: t("ui.level"), type: "number" },
            {
              key: "sort_order",
              label: t("menu_fields.sort_order"),
              type: "number",
            },
            { key: "active", label: t("ui.status"), type: "status" },
          ]}
          edit={{
            fields,
            action: forms.updateMenu,
            title: t("ui.edit_menu"),
            submitLabel: t("ui.save_menu"),
          }}
        />
      ),
  };
}

function lookupFields(t, editing) {
  return [
    field("code", t("lookup_fields.code"), "text", {
      required: !editing,
      readOnly: editing,
      help: t("ui.lookup_code_help"),
      placeholder: "SHIPMENT_MODE",
    }),
    field("name", t("lookup_fields.name"), "text", {
      required: true,
      placeholder: "Shipment Mode",
    }),
    field("description", t("lookup_fields.description"), "textarea"),
    field("active", t("lookup_fields.active"), "checkbox", { default: true }),
  ];
}

async function lookupTable({
  t,
  te,
  tab,
  searchParams,
  valueSchema,
  tableSchema,
}) {
  const tables = await apiFetch("/access/lookups");
  // `?table=` holds the table's code (readable); its id is looked up here.
  const chosen = tab === "tables" && searchParams.table ? tables.find((x) => x.code === searchParams.table || x.id === searchParams.table) : null;
  const selected = chosen ? await apiFetch(`/access/lookups/${chosen.id}/values`) : null;
  return {
    count: tables.length,
    tabs: [
      {
        key: "records",
        label: te("records"),
        icon: "Table2",
        count: tables.length,
      },
      { key: "tables", label: t("ui.lookup_tables"), icon: "ListChecks" },
      { key: "new", label: t("ui.new_lookup_table"), icon: "Plus" },
    ],
    body: () => {
      if (tab === "tables") {
        return (
          <LookupTables
            tables={tables}
            selected={selected?.table ?? null}
            values={selected?.values ?? []}
            tableSchema={tableSchema}
            valueSchema={valueSchema}
            actions={{
              createRow: actions.createLookupRow,
              saveRows: actions.saveLookupRows,
              deleteRow: actions.deleteLookupRow,
            }}
          />
        );
      }
      if (tab === "new") {
        return (
          <EntryForm
            fields={lookupFields(t, false)}
            action={forms.createLookupTable}
            title={t("ui.new_lookup_table")}
            description={t("ui.new_lookup_hint")}
            submitLabel={t("ui.save_lookup_table")}
            successHref="/access-control/lookup-table?tab=records"
          />
        );
      }
      return (
        <RecordsGrid
          exportName="lookup-tables"
          rows={tables}
          lockedField="is_system"
          columns={[
            { key: "code", label: t("lookup_fields.code"), type: "mono" },
            { key: "name", label: t("lookup_fields.name") },
            {
              key: "values_count",
              label: t("lookup_fields.values_count"),
              type: "number",
            },
            { key: "is_system", label: t("ui.system"), type: "yesno" },
            { key: "description", label: t("lookup_fields.description") },
            { key: "active", label: t("ui.status"), type: "status" },
          ]}
          edit={{
            fields: lookupFields(t, true),
            action: forms.updateLookupTable,
            title: t("ui.edit_lookup_table"),
            submitLabel: t("ui.save_lookup_table"),
          }}
          remove={forms.deleteLookupTable}
        />
      );
    },
  };
}

function parameterField(definition, t) {
  const base = {
    name: "value",
    label: t("param_fields.value"),
    required: true,
    help: definition.description,
  };
  if (definition.value_type === "BOOL")
    return {
      ...base,
      type: "select",
      options: [opt("true", t("on")), opt("false", t("off"))],
    };
  if (definition.value_type === "ENUM")
    return {
      ...base,
      type: "select",
      options: (definition.allowed_values ?? []).map((v) => opt(v, v)),
    };
  if (definition.value_type === "INT") return { ...base, type: "number" };
  if (definition.value_type === "DECIMAL")
    return { ...base, type: "number", step: "0.01" };
  return { ...base, type: "text" };
}

async function systemParameter({ t, te, tab, searchParams, groupLabel }) {
  // A factory's own answers (`?factory=<code>`): the keys that allow a
  // factory level are edited there, the rest show what the factory inherits.
  const factories = await apiFetch("/factories?per_page=100").catch(() => []);
  const factory = factories.find((f) => f.code === searchParams.factory) ?? null;
  const [definitions, settings] = await Promise.all([
    apiFetch("/settings/definitions"),
    apiFetch(factory ? `/settings?factory_id=${factory.id}` : "/settings"),
  ]);
  const byKey = new Map(settings.map((row) => [row.key, row]));
  const types = Object.fromEntries(
    ["BOOL", "INT", "DECIMAL", "STRING", "ENUM", "LIST"].map((v) => [
      v,
      t(`param_types.${v}`),
    ]),
  );
  const sources = Object.fromEntries(
    ["COMPANY", "FACTORY", "PLATFORM"].map((v) => [v, t(`param_sources.${v}`)]),
  );
  const parameters = definitions.map((definition) => {
    const row = byKey.get(definition.key);
    const value = row?.value ?? definition.default_value;
    const group = definition.key.split(".")[0];
    return {
      ...definition,
      id: definition.key,
      group,
      group_label: groupLabel(group),
      value,
      value_text:
        definition.value_type === "BOOL"
          ? value
            ? t("on")
            : t("off")
          : Array.isArray(value)
            ? value.join(",")
            : String(value ?? ""),
      level: row?.level ?? "PLATFORM",
      editable: row?.editable_here ?? false,
      locked: !(row?.editable_here ?? false),
      updated_at: row?.updated_at ?? null,
      updated_by: row?.updated_by ?? null,
      // The Edit dialog's own field, typed to the parameter.
      edit_fields: [
        { name: "key", label: t("param_fields.key"), readOnly: true },
        parameterField(definition, t),
      ],
      key_text: definition.key,
    };
  });
  // The dialog starts from the current value as text the field understands.
  for (const p of parameters)
    p.value =
      p.value_type === "BOOL"
        ? String(Boolean(p.value))
        : p.value_type === "LIST"
          ? p.value_text
          : p.value;

  const group = searchParams.group ?? null;
  const factoryId = factory?.id ?? null;
  const levelHref = (code, g = group) => {
    const query = new URLSearchParams({ tab: "settings" });
    if (code) query.set("factory", code);
    if (g) query.set("group", g);
    return `/access-control/system-parameter?${query}`;
  };
  const levels = [
    { code: null, name: t("ui.company_wide") },
    ...factories.map((f) => ({ code: f.code, name: f.name })),
  ];
  return {
    count: parameters.length,
    tabs: [
      {
        key: "records",
        label: te("records"),
        icon: "Table2",
        count: parameters.length,
      },
      {
        key: "settings",
        label: t("ui.system_setting"),
        icon: "SlidersHorizontal",
      },
    ],
    body: () => {
      if (tab === "settings") {
        const groups = [...new Set(parameters.map((p) => p.group))];
        const shown = group
          ? parameters.filter((p) => p.group === group)
          : parameters;
        return (
          <div className="grid grid-cols-1 items-start gap-4 xl:grid-cols-[18rem_1fr]">
            <div className="flex flex-col gap-4">
            <div className="rounded-lg border border-border bg-surface">
              <p className="border-b border-border px-4 py-3 text-sm font-semibold text-foreground">
                {t("ui.parameter_level")}
              </p>
              <ul className="p-2">
                {levels.map((level) => (
                  <li key={level.code ?? "company"}>
                    <a
                      href={levelHref(level.code)}
                      className={`flex items-center rounded-md px-3 py-2 text-sm ${level.code === (factory?.code ?? null) ? "bg-brand-subtle font-semibold text-brand" : "text-foreground hover:bg-surface-muted"}`}
                    >
                      {level.name}
                    </a>
                  </li>
                ))}
              </ul>
              <p className="border-t border-border px-4 py-3 text-xs text-foreground-muted">
                {t("ui.factory_level_hint")}
              </p>
            </div>
            <div className="rounded-lg border border-border bg-surface">
              <p className="border-b border-border px-4 py-3 text-sm font-semibold text-foreground">
                {t("ui.groups")}
              </p>
              <ul className="p-2">
                {groups.map((g) => (
                  <li key={g}>
                    <a
                      href={levelHref(factory?.code ?? null, g)}
                      className={`flex items-center justify-between rounded-md px-3 py-2 text-sm ${g === group ? "bg-brand-subtle font-semibold text-brand" : "text-foreground hover:bg-surface-muted"}`}
                    >
                      {groupLabel(g)}
                      <span className="text-xs text-foreground-muted tabular-nums">
                        {parameters.filter((p) => p.group === g).length}
                      </span>
                    </a>
                  </li>
                ))}
              </ul>
            </div>
            </div>
            <div className="min-w-0">
              <SystemParameters
                parameters={shown}
                factoryId={factoryId}
                updateParameter={actions.updateParameter}
                resetParameter={actions.resetParameter}
              />
            </div>
          </div>
        );
      }
      return (
        <RecordsGrid
          exportName="system-parameters"
          rows={parameters}
          lockedField="locked"
          columns={[
            { key: "group_label", label: t("param_fields.group") },
            { key: "key_text", label: t("param_fields.key"), type: "mono" },
            { key: "name", label: t("param_fields.name") },
            { key: "value_text", label: t("param_fields.value") },
            {
              key: "value_type",
              label: t("param_fields.type"),
              type: "badge",
              labels: types,
            },
            {
              key: "level",
              label: t("param_fields.source"),
              type: "badge",
              labels: sources,
            },
            {
              key: "updated_at",
              label: t("param_fields.updated"),
              type: "datetime",
            },
          ]}
          edit={{
            rowFields: "edit_fields",
            action: factoryId
              ? forms.updateFactoryParameterValue.bind(null, factoryId)
              : forms.updateParameterValue,
            title: t("ui.edit_parameter"),
            submitLabel: t("ui.save_parameter"),
          }}
        />
      );
    },
  };
}

function roleFields(t, editing) {
  return [
    field("code", t("fields.code"), "text", {
      required: !editing,
      readOnly: editing,
      placeholder: "MERCH",
    }),
    field("name", t("fields.name"), "text", {
      required: true,
      placeholder: "Merchandiser",
    }),
    field("name_bn", t("fields.name_bn"), "text", {
      placeholder: "মার্চেন্ডাইজার",
    }),
    field("short_name", t("fields.short_name"), "text", {
      required: true,
      placeholder: "Merch",
    }),
    field("rank", t("fields.rank"), "number", { required: true }),
    field("is_admin", t("fields.is_admin"), "checkbox", {
      help: t("ui.role_admin_help"),
    }),
    field("active", t("fields.active"), "checkbox", { default: true }),
  ];
}

async function roleDefinition({ t, te, tab }) {
  const [roles, grants, users] = await Promise.all([
    apiFetch("/roles?per_page=100"),
    apiFetch("/access/menu-grants"),
    apiFetchAll("/users"),
  ]);
  const menus = grants.reduce(
    (acc, row) => ({ ...acc, [row.role_id]: (acc[row.role_id] ?? 0) + 1 }),
    {},
  );
  const holders = users.reduce((acc, user) => {
    for (const id of user.role_ids ?? []) acc[id] = (acc[id] ?? 0) + 1;
    return acc;
  }, {});
  const rows = roles.map((role) => ({
    ...role,
    menus_count: menus[role.id] ?? 0,
    users_count: holders[role.id] ?? 0,
  }));
  return {
    count: rows.length,
    tabs: [
      {
        key: "records",
        label: te("records"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "new", label: t("ui.new_role"), icon: "Plus" },
    ],
    body: () =>
      tab === "new" ? (
        <EntryForm
          fields={roleFields(t, false)}
          action={forms.createRoleEntry}
          title={t("ui.new_role")}
          description={t("descriptions.role-definition")}
          submitLabel={t("ui.save_role")}
          successHref="/access-control/role-definition?tab=records"
        />
      ) : (
        <RecordsGrid
          exportName="roles"
          rows={rows}
          lockedField="is_system"
          columns={[
            { key: "code", label: t("fields.code"), type: "mono" },
            { key: "name", label: t("fields.name") },
            { key: "name_bn", label: t("fields.name_bn") },
            { key: "short_name", label: t("fields.short_name") },
            { key: "is_admin", label: t("fields.is_admin"), type: "yesno" },
            { key: "rank", label: t("fields.rank"), type: "number" },
            { key: "menus_count", label: t("ui.menus"), type: "number" },
            { key: "users_count", label: t("ui.users"), type: "number" },
            { key: "active", label: t("ui.status"), type: "status" },
          ]}
          edit={{
            fields: roleFields(t, true),
            action: forms.updateRoleEntry,
            title: t("ui.edit_role"),
            submitLabel: t("ui.save_role"),
          }}
          remove={forms.deleteRoleEntry}
          deleteDescription={t("delete_description")}
        />
      ),
  };
}

function flagFields(t) {
  return FLAGS.map((flag) =>
    field(`flag_${flag}`, t(`flags.${flag}`), "checkbox"),
  );
}

async function roleMenu({ t, te, tab, searchParams, locale }) {
  const [rows, roles] = await Promise.all([
    apiFetch("/access/menu-grants"),
    apiFetch("/roles?per_page=100"),
  ]);
  const records = rows.map((row) => ({
    ...row,
    ...Object.fromEntries(
      FLAGS.map((flag) => [`flag_${flag}`, row.flags.includes(flag)]),
    ),
  }));
  const editable = roles.filter((role) => !role.is_system && !role.is_admin);
  return {
    count: records.length,
    tabs: [
      {
        key: "records",
        label: te("records"),
        icon: "Table2",
        count: records.length,
      },
      { key: "assign", label: t("ui.menu_assign"), icon: "KeyRound" },
      { key: "new", label: t("ui.new_menu_grant"), icon: "Plus" },
    ],
    body: async () => {
      if (tab === "assign") {
        const [modules, grants] = await Promise.all([
          apiFetch("/access/menus"),
          searchParams.role
            ? apiFetch(`/access/roles/${searchParams.role}/menu-grants`)
            : Promise.resolve(null),
        ]);
        return (
          <RoleMenuMatrix
            key={searchParams.role ?? "none"}
            roles={roles}
            grants={grants}
            modules={modules}
            saveGrants={actions.saveGrants}
            locale={locale}
          />
        );
      }
      if (tab === "new") {
        const modules = await apiFetch("/access/menus");
        const menuOptions = modules.flatMap((m) =>
          m.menus
            .filter((menu) => menu.is_leaf && menu.active)
            .map((menu) => opt(menu.id, `${m.name} › ${menu.name}`)),
        );
        return (
          <EntryForm
            fields={[
              field("role_id", t("ui.role"), "select", {
                required: true,
                options: editable.map((role) =>
                  opt(role.id, `${role.name} (${role.code})`),
                ),
              }),
              field("menu_id", t("menu"), "select", {
                required: true,
                options: menuOptions,
              }),
              ...flagFields(t),
            ]}
            action={forms.createMenuGrant}
            title={t("ui.new_menu_grant")}
            description={t("ui.menu_grant_hint")}
            submitLabel={t("ui.save_menu_grant")}
            successHref="/access-control/role-menu?tab=records"
          />
        );
      }
      return (
        <RecordsGrid
          exportName="menu-grants"
          rows={records}
          lockedField="is_system"
          columns={[
            { key: "role", label: t("ui.role") },
            { key: "module", label: t("module_column") },
            { key: "menu", label: t("menu") },
            {
              key: "flags",
              label: t("ui.rights"),
              type: "flags",
              sortable: false,
            },
            { key: "updated_at", label: t("ui.updated"), type: "datetime" },
          ]}
          edit={{
            fields: flagFields(t),
            action: forms.saveMenuGrantRow,
            title: t("ui.edit_menu_grant"),
            submitLabel: t("ui.save_menu_grant"),
            columns: 3,
          }}
        />
      );
    },
  };
}

async function userAccount({ t, tu, te, tab, searchParams }) {
  const [users, roles, factories, departments, lines] = await Promise.all([
    apiFetchAll("/users?sort=name"),
    apiFetch("/roles?per_page=100"),
    apiFetch("/factories?per_page=100"),
    apiFetch("/master-data/departments?per_page=100").catch(() => []),
    apiFetch("/master-data/production-lines?per_page=200").catch(() => []),
  ]);
  const roleName = Object.fromEntries(
    roles.map((role) => [role.id, role.name]),
  );
  const typeLabels = Object.fromEntries(
    ["INTERNAL", "EXTERNAL", "GUEST"].map((v) => [v, tu(`user_types.${v}`)]),
  );
  const statusLabels = Object.fromEntries(
    [
      "ACTIVE",
      "READ_ONLY",
      "PASSWORD_EXPIRING",
      "DORMANT",
      "LOCKED",
      "SEPARATED",
    ].map((v) => [v, tu(`account_statuses.${v}`)]),
  );
  const tfaLabels = Object.fromEntries(
    ["DISABLED", "AUTHENTICATOR", "SMS_OTP", "EMAIL_OTP"].map((v) => [
      v,
      tu(`two_factor_methods.${v}`),
    ]),
  );
  const rows = users.map((user) => ({
    ...user,
    primary_role: user.role_ids?.[0] ?? null,
    primary_role_name: user.role_ids?.[0] ? roleName[user.role_ids[0]] : null,
    roles_count: user.role_ids?.length ?? 0,
  }));
  const options = (labels) => Object.entries(labels).map(([v, l]) => opt(v, l));
  const roleOptions = roles.map((role) =>
    opt(
      role.id,
      `${role.name}${role.scope === "FACTORY" ? ` · ${tu("factory_scope")}` : ""}`,
    ),
  );
  const common = (editing) => [
    field("user_type", tu("user_type"), "select", {
      required: true,
      options: options(typeLabels),
      default: "INTERNAL",
    }),
    field("username", tu("username"), "text", {
      required: true,
      placeholder: "r.islam",
      help: tu("username_hint"),
    }),
    ...(editing
      ? []
      : [
          field("password", tu("password"), "password", {
            required: true,
            help: t("password_policy_hint"),
          }),
          field(
            "password_confirmation",
            tu("password_confirmation"),
            "password",
            { required: true },
          ),
        ]),
    field("memorable_word", tu("memorable_word"), "password", {
      help: editing
        ? tu("memorable_word_keep_hint")
        : tu("memorable_word_hint"),
    }),
    field(
      "memorable_word_confirmation",
      tu("memorable_word_confirmation"),
      "password",
    ),
    field("name", tu("name"), "text", {
      required: true,
      placeholder: "Rashedul Islam",
    }),
    field("email", tu("email"), "email", {
      required: !editing,
      readOnly: editing,
      placeholder: "name@company.com",
    }),
    field("employee_code", tu("employee_code"), "text", {
      placeholder: "EMP-01042",
    }),
    field("department_id", tu("department"), "select", {
      options: departments.map((d) => opt(d.id, d.name)),
    }),
    // A Line Chief sees only their own line's breakdowns (Machinery).
    field("production_line_id", tu("production_line"), "select", {
      options: lines.map((l) => opt(l.id, l.code ? `${l.code} — ${l.name}` : l.name)),
      help: tu("production_line_hint"),
    }),
    field("phone", tu("phone"), "text", { placeholder: "01XXXXXXXXX" }),
    field("primary_role", t("ui.primary_role"), "select", {
      required: true,
      options: roleOptions,
    }),
    field("factory_id", tu("factory"), "select", {
      options: factories.map((f) => opt(f.id, f.name)),
      help: tu("factory_hint"),
    }),
    field("account_status", tu("account_status"), "select", {
      options: options(statusLabels),
      default: "ACTIVE",
    }),
    field("two_factor_method", tu("two_factor_method"), "select", {
      options: options(tfaLabels),
      default: "DISABLED",
    }),
    field("account_expires_at", tu("account_expires_at"), "date", {
      help: tu("account_expires_hint"),
    }),
    field("must_change_password", tu("must_change_password"), "checkbox"),
    field("cannot_change_password", tu("cannot_change_password"), "checkbox"),
    field("password_never_expires", tu("password_never_expires"), "checkbox"),
    field("justification", tu("justification"), "textarea", { rows: 3 }),
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
      { key: "roles", label: t("ui.user_roles"), icon: "ListChecks" },
      { key: "new", label: t("new_user_account"), icon: "Plus" },
    ],
    body: async () => {
      if (tab === "roles") {
        const user = searchParams.user
          ? await apiFetch(`/users/${searchParams.user}`).catch(() => null)
          : null;
        return (
          <div className="flex flex-col gap-4">
            <div className="rounded-lg border border-border bg-surface p-5">
              <h2 className="mb-3 text-base font-semibold text-foreground">
                {t("ui.user_roles")}
              </h2>
              <UserPicker
                users={users.map((u) => ({
                  id: u.id,
                  label: `${u.name} (${u.username ?? u.email})`,
                }))}
                value={user?.id ?? ""}
                placeholder={t("ui.choose_user")}
              />
            </div>
            {user ? (
              <div className="rounded-lg border border-border bg-surface p-5">
                <RoleAssignments
                  userId={user.id}
                  assignments={user.role_assignments}
                  roles={roles}
                  factories={factories}
                  assign={forms.assignRole}
                  remove={forms.removeRoleAssignment}
                />
              </div>
            ) : null}
          </div>
        );
      }
      if (tab === "new") {
        return (
          <EntryForm
            fields={common(false)}
            action={forms.createUserAccount}
            title={t("new_user_account")}
            description={t("ui.new_user_hint")}
            submitLabel={t("ui.save_user_account")}
            columns={3}
            successHref="/access-control/user-account?tab=records"
          />
        );
      }
      return (
        <RecordsGrid
          exportName="user-accounts"
          remove={forms.removeUserAccount}
          deleteDescription={tu("remove_user_description")}
          rows={rows}
          columns={[
            {
              key: "user_type",
              label: tu("user_type"),
              type: "badge",
              labels: typeLabels,
            },
            { key: "username", label: tu("username"), type: "mono" },
            { key: "name", label: tu("name") },
            { key: "email", label: tu("email") },
            { key: "employee_code", label: tu("employee_code") },
            { key: "primary_role_name", label: t("ui.role") },
            { key: "roles_count", label: t("ui.roles_count"), type: "number" },
            {
              key: "two_factor_method",
              label: t("two_factor_short"),
              type: "badge",
              labels: tfaLabels,
            },
            {
              key: "last_login_at",
              label: t("last_sign_in"),
              type: "datetime",
            },
            {
              key: "account_status",
              label: t("ui.status"),
              type: "status",
              labels: statusLabels,
            },
          ]}
          edit={{
            fields: common(true),
            action: forms.updateUserAccount,
            title: t("ui.edit_user_account"),
            submitLabel: t("ui.save_user_account"),
            columns: 3,
          }}
        />
      );
    },
  };
}

async function passwordChange({ t, te, tab }) {
  const [changes, users] = await Promise.all([
    apiFetch("/access/password-changes"),
    apiFetchAll("/users?sort=name"),
  ]);
  const types = Object.fromEntries(
    ["SELF", "ADMIN_SET", "ADMIN_RESET", "CREATED"].map((v) => [
      v,
      t(`change_types.${v}`),
    ]),
  );
  return {
    count: changes.length,
    tabs: [
      {
        key: "records",
        label: te("records"),
        icon: "Table2",
        count: changes.length,
      },
      { key: "change", label: t("change_password"), icon: "Lock" },
    ],
    body: () =>
      tab === "change" ? (
        <EntryForm
          fields={[
            field("user_id", t("account"), "select", {
              required: true,
              options: users.map((u) =>
                opt(u.id, `${u.username ?? u.email} — ${u.name}`),
              ),
            }),
            field("password", t("new_password"), "password", {
              required: true,
            }),
            field(
              "password_confirmation",
              t("confirm_new_password"),
              "password",
              { required: true },
            ),
            field("force_change", t("force_change"), "checkbox", {
              default: true,
            }),
          ]}
          action={forms.changeAccountPassword}
          title={t("new_password_change")}
          description={t("ui.change_password_hint")}
          submitLabel={t("change_password")}
          successHref="/access-control/password-change?tab=records"
        />
      ) : (
        <RecordsGrid
          exportName="password-changes"
          rows={changes}
          columns={[
            { key: "account", label: t("account"), type: "mono" },
            { key: "name", label: t("ui.users") },
            {
              key: "change_type",
              label: t("change_type"),
              type: "badge",
              labels: types,
            },
            {
              key: "forced_change_next_login",
              label: t("forced"),
              type: "yesno",
            },
            { key: "changed_at", label: t("changed"), type: "datetime" },
            { key: "changed_by", label: t("changed_by") },
          ]}
        />
      ),
  };
}

function categoryTicks(t) {
  return ["view", "create", "edit", "approve"].map((action) =>
    field(`can_${action}`, t(`category_flags.${action}`), "checkbox"),
  );
}

async function categoryPermission({ t, te, tab, searchParams, locale }) {
  const [rows, roles] = await Promise.all([
    apiFetch("/access/category-permissions"),
    apiFetch("/roles?per_page=100"),
  ]);
  const records = rows.map((row) => ({
    ...row,
    key: `${row.role_id}:${row.item_category_id}`,
  }));
  return {
    count: records.length,
    tabs: [
      {
        key: "records",
        label: te("records"),
        icon: "Table2",
        count: records.length,
      },
      {
        key: "matrix",
        label: t("ui.item_category_permission"),
        icon: "FolderLock",
      },
      { key: "new", label: t("ui.new_category_permission"), icon: "Plus" },
    ],
    body: async () => {
      if (tab === "matrix") {
        const data = searchParams.role
          ? await apiFetch(
              `/access/roles/${searchParams.role}/category-permissions`,
            )
          : null;
        return (
          <CategoryPermissionMatrix
            key={searchParams.role ?? "none"}
            roles={roles}
            data={data}
            savePermissions={actions.saveCategoryPermissions}
            locale={locale}
          />
        );
      }
      if (tab === "new") {
        // The whole category tree, in order (any role's view of it lists every category).
        const categories = roles.length
          ? ((
              await apiFetch(
                `/access/roles/${roles[0].id}/category-permissions`,
              )
            ).categories ?? [])
          : [];
        return (
          <EntryForm
            fields={[
              field("role_id", t("ui.role"), "select", {
                required: true,
                options: roles.map((role) =>
                  opt(role.id, `${role.name} (${role.code})`),
                ),
              }),
              field("item_category_id", t("category"), "select", {
                required: true,
                options: categories.map((c) =>
                  opt(
                    c.id,
                    `${"— ".repeat(c.depth - 1)}${c.name}${c.code ? ` (${c.code})` : ""}`,
                  ),
                ),
              }),
              ...categoryTicks(t),
            ]}
            action={forms.createCategoryPermission}
            title={t("ui.new_category_permission")}
            description={t("ui.category_hint")}
            submitLabel={t("ui.save_category_permission")}
            successHref="/access-control/category-permission?tab=records"
          />
        );
      }
      return (
        <RecordsGrid
          exportName="category-permissions"
          rowKey="key"
          rows={records}
          columns={[
            { key: "role", label: t("ui.role") },
            { key: "category", label: t("category") },
            { key: "category_code", label: t("ui.code"), type: "mono" },
            { key: "can_view", label: t("category_flags.view"), type: "yesno" },
            {
              key: "can_create",
              label: t("category_flags.create"),
              type: "yesno",
            },
            { key: "can_edit", label: t("category_flags.edit"), type: "yesno" },
            {
              key: "can_approve",
              label: t("category_flags.approve"),
              type: "yesno",
            },
          ]}
          edit={{
            fields: categoryTicks(t),
            action: forms.saveCategoryRow,
            title: t("ui.edit_category_permission"),
            submitLabel: t("ui.save_category_permission"),
          }}
          remove={forms.deleteCategoryRow}
        />
      );
    },
  };
}

const SCREENS = {
  "module-registration": moduleRegistration,
  "menu-definition": menuDefinition,
  "lookup-table": lookupTable,
  "system-parameter": systemParameter,
  "role-definition": roleDefinition,
  "role-menu": roleMenu,
  "user-account": userAccount,
  "password-change": passwordChange,
  "category-permission": categoryPermission,
  ...MAPPING_SCREENS,
  ...CONFIG_SCREENS,
  "tna-task-status": tnaTaskStatus,
};

export { SCREENS };
