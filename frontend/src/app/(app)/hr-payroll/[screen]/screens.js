import { apiFetch } from "@/lib/api-server";
import { findByRef } from "@/lib/row-ref";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { SideGrid } from "@/components/workstudy/side-grid";
import { EntryUpload } from "@/components/hrpayroll/entry-upload";
import { GazetteForm } from "@/components/hrpayroll/gazette-form";
import { PayrollSettings } from "@/components/hrpayroll/payroll-settings";
import * as actions from "./actions";

/*
 * HR & Payroll → Master Data (AnnotechERP 24/01): Employment Type, Employee
 * Category, Pay Grade, Pay Element, Allowance, Deduction, Shift, Leave Type,
 * OT Rule, Bonus Rule, Wage/Gazette Rule, Gazette Grade Wage, Festival
 * Calendar and Payroll Settings — each with Brickland's tabs. Edit opens
 * Brickland's "Edit X" tab in place of "New X" (?tab=edit&ref=…), the form
 * filled, Cancel and "Update X".
 */

const MANPOWER = ["OPERATOR", "QUALITY", "HELPER", "SUPERVISOR", "STAFF"];
const LEAVE_GROUPS = ["LEAVE", "PRESENT", "ABSENT"];
const UNITS = ["DAY", "MONTH", "YEAR"];
const GENDERS = ["MALE", "FEMALE", "COMMON"];
const BASES = ["BASIC", "GROSS"];
const BONUS_TYPES = ["ATTENDANCE", "EID_ADHA", "EID_FITR", "PRODUCTION"];
const HOLIDAY_TYPES = ["COMPANY", "GOVT", "WEEKLY_OFF"];
const PERIOD_TYPES = ["MONTHLY", "DAYS"];
const STATUSES = ["INITIATED", "SUBMITTED", "ACTIVATED", "CLOSED"];
const MOVES = ["SUBMIT", "ACTIVATE", "SEND_BACK", "CLOSE", "REOPEN"];
const DEPT_TYPES = ["PRODUCTION", "NON_PRODUCTION"];
const MONTHS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

const lookups = () => apiFetch("/hr-payroll/lookups");
const options = (m, group, values) => values.map((v) => ({ value: String(v), label: m(`opt.${group}.${v}`) }));
const pick = (m, list) => [{ value: "", label: m("select") }, ...list];
const recordsTab = (m, count, label = "records") => ({ key: "records", label: m(label), icon: "Table2", count });
const newTab = (m, key) => ({ key: "new", label: m(key), icon: "Plus" });
const editTab = (m, key) => ({ key: "edit", label: m(key), icon: "Pencil" });
const statusCol = (m, key = "active") => ({ key, label: m("f.status"), type: "status" });
const money = (v) => (v === null || v === undefined || v === "" ? "" : Number(v).toLocaleString("en-US", { maximumFractionDigits: 2 }));
const num = (v) => (v === null || v === undefined || v === "" ? "" : String(Number(v)));
const day = (d) => (d ? new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric", timeZone: "UTC" }).format(new Date(`${d}T00:00:00Z`)) : "");
const dmy = (d) => (d ? d.split("-").reverse().join("-") : "");

/**
 * The row an "Edit X" tab rewrites, or null when the url names none (or the
 * user may not change it). The url carries the row's readable reference
 * (`?tab=edit&ref=…`, the grid's first column or `refKey`), resolved against
 * the same rows the grid shows.
 */
async function editing(resource, tab, query, canManage, rows, refKey) {
  if (tab !== "edit" || !query.ref || !canManage) return null;
  const hit = findByRef(rows, refKey, query.ref);
  return hit ? apiFetch(`/erp/${resource}/${hit.id}`).catch(() => null) : null;
}

/** Brickland's "Edit X" tab: the filled form, "Rewriting …", Cancel and "Update X". */
function editForm({ m, resource, screen, booleans, fields, row, prefix, nameOf, columns = 3 }) {
  return (
    <EntryForm
      key={row.id}
      fields={fields}
      initial={row}
      title={m(`${prefix}.edit`)}
      description={m("rewriting", { name: nameOf(row) })}
      action={actions.updateResource.bind(null, resource, booleans, row.id)}
      submitLabel={m(`${prefix}.update`)}
      cancelHref={`/hr-payroll/${screen}?tab=records`}
      cancelLabel={m("cancel")}
      successHref={`/hr-payroll/${screen}?tab=records`}
      columns={columns}
    />
  );
}

/** The usual Records + New screen over one resource, with its Edit tab. */
function simpleScreen({ resource, screen, booleans, fields, columns, decorate = (r) => r, prefix, newKey, saveKey, description, deletable, needsLookups = true, nameOf = (r) => r.name }) {
  return async function render({ m, tab, canManage, searchParams: query = {} }) {
    const [look, list] = await Promise.all([needsLookups ? lookups() : null, apiFetch(`/erp/${resource}`)]);
    const rows = list.map((r) => decorate(r, m, look));
    const one = await editing(resource, tab, query, canManage, rows, columns(m)[0].key);
    const row = one ? decorate(one, m, look) : null;
    const f = fields(m, look);
    return {
      tabs: [recordsTab(m, rows.length), ...(canManage ? [row ? editTab(m, `${prefix}.edit`) : newTab(m, newKey)] : [])],
      body: () =>
        row ? (
          editForm({ m, resource, screen, booleans, fields: f, row, prefix, nameOf })
        ) : tab === "new" && canManage ? (
          <EntryForm fields={f} title={m(newKey)} description={description ? m(description) : undefined} action={actions.createResource.bind(null, resource, booleans)} submitLabel={m(saveKey)} columns={3} successHref={`/hr-payroll/${screen}?tab=records`} />
        ) : (
          <RecordsGrid
            exportName={screen}
            rows={rows}
            columns={columns(m)}
            edit={canManage ? { href: `/hr-payroll/${screen}?tab=edit&ref=` } : undefined}
            remove={canManage && deletable ? actions.deleteResource.bind(null, resource) : undefined}
          />
        ),
    };
  };
}

const treeFields = (m, parents, levelHint, { order } = {}) => [
  { name: "parent_id", label: m(order ? "f.parent_group" : "f.parent_type"), type: "select", options: pick(m, parents), placeholder: m("select"), help: m(levelHint) },
  { name: "level", label: m(order ? "f.group_level" : "f.type_level"), readOnly: true, placeholder: m("derived_on_save") },
];

async function employmentTypes({ m, tab, canManage, searchParams: query = {} }) {
  const [rows, tree, look] = await Promise.all([apiFetch("/erp/hr-employment-types"), apiFetch("/hr-payroll/type-tree"), lookups()]);
  const one = await editing("hr-employment-types", tab, query, canManage, rows, "name");
  const fields = [
    ...treeFields(m, look.types, "et.level_hint"),
    { name: "name", label: m("f.type_name_en"), required: true },
    { name: "name_bn", label: m("f.type_name_bn"), required: true },
    { name: "short_name", label: m("f.short_name"), required: true },
    { name: "is_leaf", label: m("f.leaf"), type: "checkbox", default: true },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ];
  const booleans = ["is_leaf", "active"];
  const shape = (r) => ({ ...r, parent_id: r.parent_id ?? "", level: String(r.level) });
  return {
    tabs: [recordsTab(m, rows.length), { key: "tree", label: m("et.tree"), icon: "ListTree" }, ...(canManage ? [one ? editTab(m, "et.edit") : newTab(m, "et.new")] : [])],
    body: () => {
      if (one) return editForm({ m, resource: "hr-employment-types", screen: "employee-type", booleans, fields, row: shape(one), prefix: "et", nameOf: (r) => r.name });
      if (tab === "new" && canManage) return <EntryForm fields={fields} title={m("et.new")} description={m("et.hint")} action={actions.createResource.bind(null, "hr-employment-types", booleans)} submitLabel={m("et.save")} columns={3} successHref="/hr-payroll/employee-type?tab=records" />;
      if (tab === "tree") {
        return (
          <SideGrid
            ns="hrpayroll"
            listTitle={m("et.select_node")}
            items={tree}
            title={m("et.node_title")}
            hint={m("et.tree_hint")}
            emptyTitle={m("et.select_node")}
            empty={m("et.select_node_hint")}
            gridEmpty={{ title: m("et.no_children_title"), text: m("et.no_children") }}
            footerKey="master.et.footer"
            columns={[
              { key: "name", label: m("f.type_name_en"), type: "input" },
              { key: "name_bn", label: m("f.type_name_bn"), type: "input" },
              { key: "short_name", label: m("f.short_name"), type: "input", width: "w-28" },
              { key: "level", label: m("f.type_level"), type: "text" },
              { key: "leaf", label: m("f.leaf"), type: "text" },
              { key: "active", label: m("f.active"), type: "check" },
            ]}
            newForm={{
              buttonLabel: m("et.tree_new"),
              title: m("et.tree_new"),
              formTitle: m("et.tree_form"),
              description: m("et.tree_form_hint"),
              parentField: "parent_id",
              submitLabel: m("et.tree_new"),
              action: actions.createResource.bind(null, "hr-employment-types", ["active"]),
              fields: [
                { name: "name", label: m("f.type_name_en"), required: true },
                { name: "name_bn", label: m("f.type_name_bn"), required: true, help: m("et.bn_hint") },
                { name: "short_name", label: m("f.short_name"), required: true },
                { name: "active", label: m("f.active"), type: "checkbox", default: true },
              ],
            }}
            load={actions.loadTypeChildren}
            save={actions.saveTypeChildren}
            canManage={canManage}
            exportName="employment-types"
          />
        );
      }
      return (
        <RecordsGrid
          exportName="employment-types"
          rows={rows.map((r) => ({ ...shape(r), parent_label: r.parent ?? m("et.root") }))}
          columns={[
            { key: "name", label: m("f.type_name_en") },
            { key: "name_bn", label: m("f.type_name_bn") },
            { key: "short_name", label: m("f.short_name"), type: "mono" },
            { key: "parent_label", label: m("f.parent_type") },
            { key: "level", label: m("f.type_level"), type: "number", align: "right" },
            { key: "is_leaf", label: m("f.leaf"), type: "yesno" },
            statusCol(m),
          ]}
          edit={canManage ? { href: "/hr-payroll/employee-type?tab=edit&ref=", refKey: "name" } : undefined}
        />
      );
    },
  };
}

const designationGroups = simpleScreen({
  resource: "hr-designation-groups",
  screen: "designation-group",
  booleans: ["is_leaf", "active"],
  prefix: "dg",
  newKey: "dg.new",
  saveKey: "dg.save",
  description: "dg.hint",
  fields: (m, look) => [
    ...treeFields(m, look.groups, "dg.level_hint", { order: true }),
    { name: "name", label: m("f.group_name_en"), required: true },
    { name: "name_bn", label: m("f.group_name_bn"), required: true },
    { name: "short_name", label: m("f.short_name"), required: true },
    { name: "sort_order", label: m("f.group_order"), type: "number", required: true },
    { name: "manpower_category", label: m("f.manpower"), type: "select", options: pick(m, options(m, "manpower", MANPOWER)), placeholder: m("select"), help: m("dg.manpower_hint") },
    { name: "is_leaf", label: m("f.leaf"), type: "checkbox", default: true },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (r, m) => ({ ...r, parent_id: r.parent_id ?? "", manpower_category: r.manpower_category ?? "", parent_label: r.parent ?? m("dg.root"), manpower_label: r.manpower_category ? m(`opt.manpower.${r.manpower_category}`) : m("dg.by_title"), level: String(r.level), sort_order: String(r.sort_order) }),
  columns: (m) => [
    { key: "name", label: m("f.group_name_en") },
    { key: "short_name", label: m("f.short_name") },
    { key: "manpower_label", label: m("f.manpower") },
    { key: "parent_label", label: m("f.parent_group") },
    { key: "level", label: m("f.group_level"), type: "number", align: "right" },
    { key: "sort_order", label: m("f.group_order"), type: "number", align: "right" },
    { key: "is_leaf", label: m("f.leaf"), type: "yesno" },
    statusCol(m),
  ],
});

const payGrades = simpleScreen({
  resource: "hr-grades",
  screen: "grade",
  booleans: ["active"],
  prefix: "gr",
  newKey: "gr.new",
  saveKey: "gr.save",
  description: "gr.hint",
  needsLookups: false,
  nameOf: (r) => r.code,
  fields: (m) => [
    { name: "code", label: m("f.grade_en"), required: true, placeholder: "1" },
    { name: "code_bn", label: m("f.grade_bn"), required: true, placeholder: "গ্রেড-১" },
    { name: "name", label: m("f.grade_name"), placeholder: "Grade-01" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (r) => ({ ...r, designations: String(r.designations) }),
  columns: (m) => [
    { key: "code", label: m("f.grade_no_en"), type: "mono" },
    { key: "name", label: m("f.grade_name") },
    { key: "code_bn", label: m("f.grade_no_bn") },
    { key: "designations", label: m("f.designations"), type: "number", align: "right" },
    statusCol(m),
  ],
});

const payElements = simpleScreen({
  resource: "hr-pay-elements",
  screen: "pay-element",
  booleans: ["active"],
  prefix: "pe",
  newKey: "pe.new",
  saveKey: "pe.save",
  description: "pe.hint",
  needsLookups: false,
  nameOf: (r) => r.code,
  fields: (m) => [
    { name: "code", label: m("f.element_code"), required: true, placeholder: "BASIC" },
    { name: "name", label: m("f.element_name"), required: true, placeholder: "Basic Pay" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (r) => ({ ...r, in_structures: String(r.in_structures), gl_mapped: String(r.gl_mapped) }),
  columns: (m) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.pay_element") },
    { key: "in_structures", label: m("f.in_structures"), type: "number", align: "right" },
    { key: "gl_mapped", label: m("f.gl_mapped"), type: "number", align: "right" },
    statusCol(m),
  ],
});

/** Allowance and Deduction: the entries, a file upload, and one typed in. */
function entryScreen({ kind, resource, screen, keys }) {
  return async function render({ m, tab, canManage }) {
    const [rows, look] = await Promise.all([apiFetch(`/erp/${resource}`), lookups()]);
    const allowance = kind === "allowances";
    const fields = allowance
      ? [
          { name: "pay_element_id", label: m("f.pay_element"), type: "select", required: true, options: look.elements, placeholder: m("select_element") },
          { name: "company", label: m("f.company"), type: "select", required: true, options: [look.company], placeholder: m("select_company") },
          { name: "pay_period_id", label: m("f.pay_period"), type: "select", required: true, options: look.periods.map((p) => ({ value: p.value, label: p.no })), placeholder: m("select_period_allowance") },
          { name: "input_mode", label: m("f.input_mode"), type: "radio", required: true, default: "MANUAL", options: [{ value: "EXCEL", label: m("al.upload") }, { value: "MANUAL", label: m("al.manual") }], helpWhen: { EXCEL: m("al.excel_hint") } },
          { name: "user_id", label: m("f.employee"), type: "select", required: true, options: look.employees, placeholder: m("select_employee"), showWhen: { field: "input_mode", value: "MANUAL" } },
          { name: "amount", label: m("f.amount"), type: "number", required: true, step: "0.01", showWhen: { field: "input_mode", value: "MANUAL" } },
        ]
      : [
          { name: "pay_period_id", label: m("f.salary_pay_period"), type: "select", required: true, options: look.periods, placeholder: m("select_period") },
          { name: "company", label: m("f.select_company"), readOnly: true, default: look.company.label, help: m("de.company_hint") },
          { name: "pay_element_id", label: m("f.salary_head"), type: "select", required: true, options: look.elements, placeholder: m("select_deduction") },
          { name: "user_id", label: m("f.employee_code"), type: "select", required: true, options: look.employees, placeholder: m("select_employee") },
          { name: "amount", label: m("f.amount"), type: "number", required: true, step: "0.01" },
        ];
    const decorated = rows.map((r) => ({ ...r, amount_label: money(r.amount), amount: num(r.amount) }));
    return {
      tabs: [recordsTab(m, rows.length, keys.records), ...(canManage ? [{ key: "upload", label: m(keys.upload), icon: "FileUp" }, { key: "new", label: m(keys.new), icon: "Plus" }] : [])],
      body: () => {
        if (tab === "upload" && canManage) return <EntryUpload kind={kind} company={look.company} periods={look.periods} elements={look.elements} save={actions.saveEntries} title={m(keys.upload)} />;
        if (tab === "new" && canManage) {
          return (
            <EntryForm
              fields={fields}
              title={m(keys.form)}
              description={m(keys.hint)}
              action={actions.saveEntry.bind(null, kind)}
              submitLabel={m(keys.save)}
              columns={3}
              successHref={`/hr-payroll/${screen}?tab=records`}
              cancelHref={allowance ? undefined : `/hr-payroll/${screen}?tab=records`}
              cancelLabel={m("cancel")}
            />
          );
        }
        return (
          <RecordsGrid
            exportName={screen}
            rows={decorated}
            columns={[
              { key: "pay_period", label: m("f.pay_period"), type: "mono" },
              { key: "pay_element", label: m(allowance ? "f.pay_element" : "f.salary_head") },
              { key: "employee_code", label: m("f.employee_code"), type: "mono" },
              { key: "employee", label: m("f.employee") },
              { key: "amount_label", label: m("f.amount"), type: "number", align: "right" },
            ]}
            edit={canManage ? { fields: [{ name: "amount", label: m("f.amount"), type: "number", required: true, step: "0.01" }], action: actions.updateResource.bind(null, resource, []), title: m(keys.edit), submitLabel: m("save"), columns: 1 } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, resource) : undefined}
          />
        );
      },
    };
  };
}

const allowances = entryScreen({ kind: "allowances", resource: "hr-allowances", screen: "allowance-entry", keys: { records: "records", upload: "al.upload", new: "al.manual", form: "al.new", hint: "al.hint", save: "al.save", edit: "al.edit" } });
const deductions = entryScreen({ kind: "deductions", resource: "hr-deductions", screen: "head-wise-deduction", keys: { records: "de.entry", upload: "de.upload", new: "de.tab_new", form: "de.new", hint: "de.hint", save: "save", edit: "de.edit" } });

const shifts = simpleScreen({
  resource: "hr-shifts",
  screen: "shift-master",
  booleans: ["cross_day", "ot_allowed", "ot_approval", "active"],
  prefix: "sh",
  newKey: "sh.new",
  saveKey: "sh.save",
  description: "sh.hint",
  needsLookups: false,
  nameOf: (r) => r.code,
  fields: (m) => [
    { name: "code", label: m("f.shift_code"), required: true, placeholder: "DAY" },
    { name: "name", label: m("f.shift_name"), required: true, placeholder: "Day Shift" },
    { name: "start_time", label: m("f.start_time"), type: "time", required: true, help: m("sh.start_hint") },
    { name: "end_time", label: m("f.end_time"), type: "time", required: true },
    { name: "break_start", label: m("f.break_start"), type: "time", help: m("sh.break_hint") },
    { name: "break_min", label: m("f.break_time"), type: "number", suffix: m("min") },
    { name: "working_hours", label: m("f.working_hours"), type: "number", step: "0.25", help: m("sh.hours_hint") },
    { name: "grace_min", label: m("f.grace_time"), type: "number", suffix: m("min"), help: m("sh.grace_hint") },
    { name: "cross_day", label: m("f.cross_day"), type: "checkbox", help: m("sh.cross_hint") },
    { name: "ot_allowed", label: m("f.ot_allowed"), type: "checkbox" },
    { name: "ot_approval", label: m("f.ot_approval"), type: "checkbox" },
    { name: "effective_date", label: m("f.effective_date"), type: "date" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ],
  decorate: (r) => ({ ...r, break_min: num(r.break_min), working_hours: num(r.working_hours), grace_min: num(r.grace_min), effective_label: day(r.effective_date) }),
  columns: (m) => [
    { key: "code", label: m("f.shift_code"), type: "mono" },
    { key: "name", label: m("f.shift_name") },
    { key: "start_time", label: m("f.start_time"), type: "mono" },
    { key: "end_time", label: m("f.end_time_col"), type: "mono" },
    { key: "break_start", label: m("f.break_start"), type: "mono" },
    { key: "break_min", label: m("f.break_min"), type: "number", align: "right" },
    { key: "working_hours", label: m("f.working_hours"), type: "number", align: "right" },
    { key: "grace_min", label: m("f.grace_min"), type: "number", align: "right" },
    { key: "cross_day", label: m("f.cross_day_col"), type: "yesno" },
    { key: "ot_allowed", label: m("f.ot_allowed"), type: "yesno" },
    { key: "ot_approval", label: m("f.ot_approval"), type: "yesno" },
    { key: "effective_label", label: m("f.effective_date") },
    statusCol(m),
  ],
});

const leaveTypes = simpleScreen({
  resource: "hr-leave-types",
  screen: "leave-type",
  booleans: ["paid", "day_forward", "accrual", "accrual_auto", "offday_included", "holiday_included", "balance_check", "encashable", "active"],
  prefix: "lt",
  newKey: "lt.new",
  saveKey: "lt.save",
  description: "lt.hint",
  nameOf: (r) => r.code,
  fields: (m, look) => [
    { name: "code", label: m("f.leave_code"), required: true, placeholder: "CL" },
    { name: "color", label: m("f.color"), placeholder: "#3987e5" },
    { name: "name", label: m("f.leave_name_en"), required: true },
    { name: "name_bn", label: m("f.leave_name_bn") },
    { name: "paid", label: m("f.paid_leave"), type: "checkbox", default: true },
    { name: "pay_element_id", label: m("f.pay_element"), type: "select", options: pick(m, look.elements), placeholder: m("select") },
    { name: "day_forward", label: m("f.day_forward"), type: "checkbox" },
    { name: "forward_days", label: m("f.forward_days"), type: "number" },
    { name: "accrual", label: m("f.accrual"), type: "checkbox" },
    { name: "accrual_auto", label: m("f.accrual_auto"), type: "checkbox" },
    { name: "offday_included", label: m("f.offday_included"), type: "checkbox" },
    { name: "holiday_included", label: m("f.holiday_included"), type: "checkbox" },
    { name: "balance_check", label: m("f.balance_check"), type: "checkbox" },
    { name: "leave_group", label: m("f.leave_group"), type: "select", required: true, options: options(m, "leave_group", LEAVE_GROUPS), placeholder: m("select") },
    { name: "min_employment", label: m("f.min_employment"), type: "number" },
    { name: "min_unit", label: m("f.min_unit"), type: "select", options: pick(m, options(m, "unit", UNITS)), placeholder: m("select") },
    { name: "notify_days", label: m("f.notify_days"), type: "number" },
    { name: "max_days", label: m("f.max_days"), type: "number" },
    { name: "min_work_days", label: m("f.min_work_days"), type: "number" },
    { name: "applicable_for", label: m("f.applicable_for"), type: "select", required: true, options: options(m, "gender", GENDERS), placeholder: m("select") },
    { name: "encashable", label: m("f.encashable"), type: "checkbox" },
    { name: "active", label: m("f.active_q"), type: "checkbox", default: true },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
  ],
  decorate: (r) => ({ ...r, pay_element_id: r.pay_element_id ?? "", min_unit: r.min_unit ?? "", ...Object.fromEntries(["forward_days", "min_employment", "notify_days", "max_days", "min_work_days"].map((k) => [k, num(r[k])])) }),
  columns: (m) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.leave_name") },
    { key: "paid", label: m("f.paid"), type: "yesno" },
    { key: "leave_group", label: m("f.group") },
    { key: "max_days", label: m("f.max_days_col"), type: "number", align: "right" },
    { key: "applicable_for", label: m("f.applicable_for") },
    { key: "encashable", label: m("f.encashable"), type: "yesno" },
    statusCol(m),
  ],
});

const otRules = simpleScreen({
  resource: "hr-ot-rules",
  screen: "ot-rule",
  booleans: ["active"],
  prefix: "ot",
  newKey: "ot.new",
  saveKey: "ot.save",
  description: "ot.hint",
  nameOf: (r) => r.code,
  fields: (m, look) => [
    { name: "code", label: m("f.rule_code"), required: true, placeholder: "OT-STD" },
    { name: "name", label: m("f.rule_name"), required: true, placeholder: "Standard OT" },
    { name: "wage_basis", label: m("f.ot_basis"), type: "select", required: true, options: options(m, "basis", BASES), placeholder: m("select") },
    { name: "divisor", label: m("f.divisor"), type: "number", required: true, help: m("ot.divisor_hint") },
    { name: "multiplier", label: m("f.multiplier"), type: "number", required: true, step: "0.01", help: m("ot.multiplier_hint") },
    { name: "effective_from", label: m("f.effective_from"), type: "date", required: true },
    { name: "effective_to", label: m("f.effective_to"), type: "date", help: m("ot.to_hint") },
    { name: "employment_type_id", label: m("f.applicable_category"), type: "select", options: pick(m, look.types), placeholder: m("select"), help: m("ot.all_hint") },
    { name: "grade_id", label: m("f.applicable_grade"), type: "select", options: [{ value: "", label: m("select_dash") }, ...look.grades], placeholder: m("select_dash"), help: m("ot.all_hint") },
    { name: "legal_ref", label: m("f.legal_ref"), span: 2 },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (r, m) => ({ ...r, employment_type_id: r.employment_type_id ?? "", grade_id: r.grade_id ?? "", divisor: num(r.divisor), multiplier: num(r.multiplier), category_label: r.category ?? m("all"), grade_label: r.grade ?? m("all"), effective: `${day(r.effective_from)} → ${r.effective_to ? day(r.effective_to) : m("open")}` }),
  columns: (m) => [
    { key: "code", label: m("f.code"), type: "mono" },
    { key: "name", label: m("f.name") },
    { key: "formula", label: m("f.formula") },
    { key: "category_label", label: m("f.category") },
    { key: "grade_label", label: m("f.grade") },
    { key: "effective", label: m("f.effective") },
    { key: "legal_ref", label: m("f.legal_ref") },
    statusCol(m),
  ],
});

const bonusRules = simpleScreen({
  resource: "hr-bonus-rules",
  screen: "bonus-rules-setup",
  booleans: ["active"],
  prefix: "bo",
  newKey: "bo.new",
  saveKey: "bo.save",
  description: "bo.hint",
  nameOf: (r) => r.rule_set,
  fields: (m, look) => [
    { name: "rule_set_id", label: m("f.rule_set"), type: "select", required: true, options: look.rule_sets, placeholder: m("select"), help: m("bo.set_hint") },
    { name: "service_low", label: m("f.service_low"), type: "number", required: true, help: m("bo.months_hint") },
    { name: "service_high", label: m("f.service_high"), type: "number", required: true, help: m("bo.no_limit") },
    { name: "salary_low", label: m("f.salary_low"), type: "number", required: true },
    { name: "salary_high", label: m("f.salary_high"), type: "number", required: true, help: m("bo.no_limit") },
    { name: "basis", label: m("f.bonus_basis"), type: "select", required: true, options: options(m, "basis", BASES), placeholder: m("select") },
    { name: "pct", label: m("f.bonus_pct"), type: "number", step: "0.01", help: m("bo.amount_hint") },
    { name: "fixed", label: m("f.bonus_fixed"), type: "number", step: "0.01" },
    { name: "employment_type_id", label: m("f.employee_type"), type: "select", required: true, options: look.types, placeholder: m("select") },
    { name: "department_id", label: m("f.department"), type: "select", options: [{ value: "", label: m("select_dash") }, ...look.departments], placeholder: m("select_dash") },
    { name: "designation_group_id", label: m("f.designation_group"), type: "select", options: [{ value: "", label: m("select_dash") }, ...look.groups], placeholder: m("select_dash") },
    { name: "shift_team_id", label: m("f.shift_team"), type: "select", options: pick(m, look.shift_teams), placeholder: m("select") },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (r, m) => ({
    ...r,
    department_id: r.department_id ?? "",
    designation_group_id: r.designation_group_id ?? "",
    shift_team_id: r.shift_team_id ?? "",
    service_low: String(r.service_low),
    service_high: String(r.service_high),
    salary_low: num(r.salary_low),
    salary_high: num(r.salary_high),
    pct: num(r.pct),
    fixed: num(r.fixed),
    service: `${r.service_low} – ${r.service_high}`,
    band: `${money(r.salary_low)} – ${money(r.salary_high)}`,
    basis_label: m(`opt.basis.${r.basis}`),
    scope_label: r.scope || m("all"),
  }),
  columns: (m) => [
    { key: "rule_set", label: m("f.rule_set_col") },
    { key: "service", label: m("f.service") },
    { key: "band", label: m("f.salary_band") },
    { key: "basis_label", label: m("f.basis") },
    { key: "pct", label: "%", type: "number", align: "right" },
    { key: "fixed", label: m("f.fixed"), type: "number", align: "right" },
    { key: "employment_type", label: m("f.employee_type") },
    { key: "scope_label", label: m("f.scope") },
    statusCol(m),
  ],
});

async function wageGazette({ m, tab, canManage, searchParams: query = {} }) {
  const [rows, look] = await Promise.all([apiFetch("/erp/hr-gazette-lines"), lookups()]);
  const one = await editing("hr-gazette-lines", tab, query, canManage, rows, "gazette");
  const fields = [
    { name: "gazette_id", label: m("f.gazette"), type: "select", required: true, options: look.gazettes, placeholder: m("select") },
    { name: "employment_type_id", label: m("f.employee_category"), type: "select", required: true, options: look.types, placeholder: m("select") },
    { name: "pay_element_id", label: m("f.pay_element"), type: "select", required: true, options: look.elements, placeholder: m("select") },
    { name: "amount", label: m("f.amount"), type: "number", required: true, step: "0.01", help: m("wg.amount_hint") },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ];
  const shape = (r) => ({ ...r, amount_label: money(r.amount), amount: num(r.amount), effective_label: day(r.effective_date) });
  return {
    tabs: [recordsTab(m, rows.length), { key: "gazette", label: m("wg.title"), icon: "Layers" }, ...(canManage ? [one ? editTab(m, "wg.edit") : newTab(m, "wg.new_line")] : [])],
    body: () => {
      if (one) return editForm({ m, resource: "hr-gazette-lines", screen: "salary-policy-and-rules", booleans: ["active"], fields, row: shape(one), prefix: "wg", nameOf: (r) => r.gazette });
      if (tab === "gazette") return <GazetteForm gazettes={look.gazettes} load={actions.loadGazette} save={actions.saveGazette} remove={actions.deleteGazette} canManage={canManage} />;
      if (tab === "new" && canManage) return <EntryForm fields={fields} title={m("wg.new_line")} description={m("wg.line_hint")} action={actions.createResource.bind(null, "hr-gazette-lines", ["active"])} submitLabel={m("wg.save_line")} columns={3} successHref="/hr-payroll/salary-policy-and-rules?tab=records" />;
      return (
        <RecordsGrid
          exportName="wage-gazette"
          rows={rows.map(shape)}
          columns={[
            { key: "gazette", label: m("f.gazette") },
            { key: "number_year", label: m("f.number_year") },
            { key: "category", label: m("f.category") },
            { key: "pay_element", label: m("f.pay_element") },
            { key: "amount_label", label: m("f.amount"), type: "number", align: "right" },
            { key: "means", label: m("f.means") },
            { key: "effective_label", label: m("f.effective_date") },
            statusCol(m),
          ]}
          edit={canManage ? { href: "/hr-payroll/salary-policy-and-rules?tab=edit&ref=" } : undefined}
          remove={canManage ? actions.deleteResource.bind(null, "hr-gazette-lines") : undefined}
        />
      );
    },
  };
}

const gradeWages = simpleScreen({
  resource: "hr-gazette-grade-wages",
  screen: "gazette-grade-wage",
  booleans: ["active"],
  prefix: "gg",
  newKey: "gg.new",
  saveKey: "gg.save",
  description: "gg.hint",
  nameOf: (r) => r.gazette,
  fields: (m, look) => [
    { name: "gazette_id", label: m("f.gazette"), type: "select", required: true, options: look.gazettes, placeholder: m("select") },
    { name: "employment_type_id", label: m("f.employee_category"), type: "select", required: true, options: look.types, placeholder: m("select") },
    { name: "grade_id", label: m("f.grade"), type: "select", required: true, options: look.grades, placeholder: m("select") },
    { name: "basic", label: m("f.basic_salary"), type: "number", required: true },
    { name: "house_rent_pct", label: m("f.house_rent_pct"), type: "number", required: true, suffix: "%" },
    { name: "medical", label: m("f.medical"), type: "number", required: true },
    { name: "conveyance", label: m("f.conveyance"), type: "number", required: true },
    { name: "food", label: m("f.food"), type: "number", required: true },
    { name: "other", label: m("f.other_allowance"), type: "number" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ],
  decorate: (r) => ({
    ...r,
    ...Object.fromEntries(["basic", "house_rent_pct", "medical", "conveyance", "food", "other"].map((k) => [k, num(r[k])])),
    year: r.year ? String(r.year) : "",
    basic_label: money(r.basic),
    house_rent_label: `${money(r.house_rent)} (${num(r.house_rent_pct)}%)`,
    medical_label: money(r.medical),
    conveyance_label: money(r.conveyance),
    food_label: money(r.food),
    gross_label: money(r.gross_a),
  }),
  columns: (m) => [
    { key: "gazette", label: m("f.gazette") },
    { key: "year", label: m("f.year") },
    { key: "category", label: m("f.category") },
    { key: "grade", label: m("f.grade") },
    { key: "basic_label", label: m("f.basic"), type: "number", align: "right" },
    { key: "house_rent_label", label: m("f.house_rent"), align: "right" },
    { key: "medical_label", label: m("f.medical_col"), type: "number", align: "right" },
    { key: "conveyance_label", label: m("f.conveyance_col"), type: "number", align: "right" },
    { key: "food_label", label: m("f.food_col"), type: "number", align: "right" },
    { key: "gross_label", label: m("f.gross_a"), type: "number", align: "right" },
    statusCol(m),
  ],
});

async function festivalCalendar({ m, tab, canManage, locale }) {
  const [festivals, holidays, look] = await Promise.all([apiFetch("/erp/hr-festivals"), apiFetch("/erp/hr-holidays"), lookups()]);
  const festivalFields = [
    { name: "name", label: m("f.festival_name"), required: true, placeholder: "Eid-ul-Fitr" },
    { name: "festival_date", label: m("f.festival_date"), type: "date", required: true },
    { name: "year", label: m("f.year"), type: "number", required: true, default: String(new Date().getFullYear()) },
    { name: "payment_month", label: m("f.payment_month"), type: "select", required: true, monthsFrom: "festival_date", locale: locale === "bn" ? "bn-BD" : "en-GB", placeholder: m("fe.pick_date"), help: m("fe.month_hint") },
    { name: "bonus_rule_set_id", label: m("f.bonus_rule"), type: "select", options: pick(m, look.rule_sets), placeholder: m("select"), help: m("fe.rule_hint") },
    { name: "bonus_type", label: m("f.bonus_type"), type: "select", options: pick(m, options(m, "bonus", BONUS_TYPES)), placeholder: m("select") },
    { name: "min_service", label: m("f.min_service"), type: "number", default: "0", suffix: m("days"), help: m("fe.service_hint") },
    { name: "active", label: m("f.status"), type: "select", options: [{ value: "1", label: m("active_label") }, { value: "0", label: m("inactive_label") }], default: "1", placeholder: m("select") },
    { name: "eligibility", label: m("f.eligibility"), type: "textarea", rows: 2, span: 2, placeholder: m("fe.eligibility_placeholder") },
    { name: "reference", label: m("f.reference"), type: "textarea", rows: 2, span: 2, placeholder: m("fe.reference_placeholder") },
    { name: "add_holiday", label: m("f.add_holiday"), type: "checkbox", span: 3, help: m("fe.holiday_hint") },
  ];
  const holidayFields = [
    { name: "fiscal_year_id", label: m("f.fiscal_year"), type: "select", required: true, options: look.fiscal_years, placeholder: m("select") },
    { name: "code", label: m("f.holiday_code"), required: true, placeholder: "HOL-VICTORY" },
    { name: "name", label: m("f.holiday_name"), required: true, placeholder: "Victory Day" },
    { name: "type", label: m("f.holiday_type"), type: "select", required: true, options: options(m, "holiday", HOLIDAY_TYPES), placeholder: m("select") },
    { name: "start_date", label: m("f.start_date"), type: "date", help: m("fe.inside_year") },
    { name: "days", label: m("f.no_of_days"), type: "number", required: true, default: "1" },
    { name: "recurring", label: m("f.recurring_every_year"), type: "checkbox", help: m("fe.recurring_hint") },
    { name: "remarks", label: m("f.remarks"), span: 3 },
  ];
  const festivalRows = festivals.map((r) => ({ ...r, min_service: num(r.min_service), year: String(r.year), date_label: day(r.festival_date), month_label: r.payment_month ? new Intl.DateTimeFormat("en-GB", { month: "long", year: "numeric", timeZone: "UTC" }).format(new Date(`${r.payment_month}-01T00:00:00Z`)) : "", bonus_type_label: r.bonus_type ? m(`opt.bonus.${r.bonus_type}`) : "" }));
  const holidayRows = holidays.map((r) => ({ ...r, days: String(r.days), calendar_days: String(r.calendar_days), type_label: m(`opt.holiday.${r.type}`), from: dmy(r.start_date), to: dmy(r.end_date) }));
  return {
    tabs: [
      { key: "festivals", label: m("fe.festivals"), icon: "CalendarHeart", count: festivals.length },
      { key: "holidays", label: m("fe.holidays"), icon: "CalendarOff", count: holidays.length },
    ],
    body: () =>
      tab === "holidays" ? (
        <div className="flex flex-col gap-4">
          {canManage ? <EntryForm fields={holidayFields} title={m("fe.add_holiday")} description={m("fe.holiday_form_hint")} action={actions.createResource.bind(null, "hr-holidays", ["recurring"])} submitLabel={m("fe.add_holiday")} columns={4} inline successHref="/hr-payroll/festival-calendar?tab=holidays" /> : null}
          <div>
            <h2 className="text-[15px] font-semibold">{m("fe.register")}</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("fe.delete_holiday")}</p>
          </div>
          <RecordsGrid
            exportName="holiday-register"
            rows={holidayRows}
            columns={[
              { key: "code", label: m("f.code"), type: "mono" },
              { key: "name", label: m("f.holiday") },
              { key: "type_label", label: m("f.type") },
              { key: "fiscal_year", label: m("f.fiscal_year") },
              { key: "from", label: m("f.from") },
              { key: "to", label: m("f.to") },
              { key: "days", label: m("f.days"), type: "number", align: "right" },
              { key: "recurring", label: m("f.recurring"), type: "yesno" },
              { key: "calendar_days", label: m("f.on_calendar"), type: "number", align: "right" },
              { key: "active", label: m("f.status"), type: "toggle", action: canManage ? actions.setActive.bind(null, "hr-holidays") : undefined },
            ]}
            rowAction={canManage ? { label: m("fe.to_calendar"), action: actions.holidayToCalendar } : undefined}
            remove={canManage ? actions.deleteResource.bind(null, "hr-holidays") : undefined}
            deleteDescription={m("fe.delete_holiday")}
          />
        </div>
      ) : (
        <div className="flex flex-col gap-4">
          {canManage ? <EntryForm fields={festivalFields} title={m("fe.new")} description={m("fe.hint")} action={actions.createResource.bind(null, "hr-festivals", ["add_holiday"])} submitLabel={m("fe.add")} columns={4} inline successHref="/hr-payroll/festival-calendar?tab=festivals" /> : null}
          <div>
            <h2 className="text-[15px] font-semibold">{m("fe.festivals")}</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("fe.footer", { count: festivals.length })}</p>
          </div>
          <RecordsGrid
            exportName="festivals"
            rows={festivalRows}
            columns={[
              { key: "name", label: m("f.festival") },
              { key: "year", label: m("f.year") },
              { key: "date_label", label: m("f.festival_date") },
              { key: "month_label", label: m("f.payment_month") },
              { key: "bonus_rule_set", label: m("f.bonus_rule") },
              { key: "bonus_type_label", label: m("f.bonus_type") },
              { key: "min_service", label: m("f.min_service_col"), type: "number", align: "right" },
              { key: "eligibility", label: m("f.eligibility_col") },
              { key: "active", label: m("f.status"), type: "toggle", action: canManage ? actions.setActive.bind(null, "hr-festivals") : undefined },
            ]}
            remove={canManage ? actions.deleteResource.bind(null, "hr-festivals") : undefined}
            deleteDescription={m("fe.delete_festival")}
          />
        </div>
      ),
  };
}

async function payrollSettings({ m, tab, canManage, searchParams: q = {}, locale }) {
  const [rows, look] = await Promise.all([apiFetch("/erp/hr-pay-periods"), lookups()]);
  // Pay periods are named by their number; every row shares the company, the grid's first column.
  const one = await editing("hr-pay-periods", tab, q, canManage, rows, "period_no");
  const fields = [
    { name: "period_no", label: m("f.period_no"), readOnly: true, placeholder: m("pp.no_hint") },
    { name: "company", label: m("f.company"), type: "select", required: true, options: [look.company], default: look.company.value, placeholder: m("select") },
    { name: "period_type", label: m("f.period_type"), type: "select", required: true, options: options(m, "period_type", PERIOD_TYPES), placeholder: m("select") },
    { name: "fiscal_year_id", label: m("f.fiscal_year"), type: "select", required: true, options: look.fiscal_years, placeholder: m("select") },
    { name: "month", label: m("f.month"), type: "select", required: true, options: options(m, "month", MONTHS), placeholder: m("select") },
    { name: "start_date", label: m("f.start_date"), type: "date", required: true },
    { name: "end_date", label: m("f.end_date"), type: "date", required: true },
    { name: "pay_date", label: m("f.pay_date"), type: "date", required: true },
    { name: "status", label: m("f.action_status"), type: "select", required: true, options: options(m, "status", STATUSES), default: "INITIATED", placeholder: m("select") },
    { name: "pay_element_id", label: m("f.pay_element"), type: "select", options: pick(m, look.elements), placeholder: m("select") },
    { name: "dept_type", label: m("f.dept_type"), type: "select", options: pick(m, options(m, "dept", DEPT_TYPES)), placeholder: m("select") },
    { name: "remarks", label: m("f.remarks"), type: "textarea" },
    { name: "show_for_report", label: m("f.show_for_report"), type: "checkbox", default: true },
    { name: "closed", label: m("f.is_close"), type: "checkbox" },
  ];
  const booleans = ["show_for_report", "closed"];
  const yn = (v) => (v ? "Y" : "N");
  const shape = (r) => ({ ...r, company: look.company.value, company_label: r.company, month: String(r.month), month_label: m(`opt.month.${r.month}`), pay_element_id: r.pay_element_id ?? "", dept_type: r.dept_type ?? "", pay_date_label: day(r.pay_date), report_yn: yn(r.show_for_report), closed_yn: yn(r.closed), total_pays: String(r.total_pays), total_payable: money(r.total_payable) || "0", status_label: m(`opt.status.${r.status}`) });
  const decorated = rows.map(shape);
  const shown = decorated.filter(
    (r) =>
      (!q.fiscal_year_id || r.fiscal_year_id === q.fiscal_year_id) &&
      (!q.month || r.month === q.month) &&
      (!q.pay_element_id || r.pay_element_id === q.pay_element_id) &&
      (!q.period_no || r.period_no.toLowerCase().includes(q.period_no.toLowerCase())) &&
      (!q.dept_type || r.dept_type === q.dept_type) &&
      (!q.closed || String(r.closed) === q.closed) &&
      (!q.from || r.start_date >= q.from) &&
      (!q.to || r.end_date <= q.to),
  );
  const [companies, registers, settings] = tab === "actions" ? [await apiFetch("/hr-payroll/period-companies"), [], []] : tab === "fy" ? [[], await apiFetch("/hr-payroll/fiscal-registers"), []] : tab === "settings" ? [[], [], await apiFetch("/hr-payroll/settings")] : [[], [], []];
  return {
    tabs: [recordsTab(m, rows.length), { key: "settings", label: m("ps.settings_title"), icon: "Layers" }, { key: "actions", label: m("ps.actions"), icon: "ListChecks" }, { key: "fy", label: m("ps.new_fy"), icon: "ListChecks" }, ...(canManage ? [one ? editTab(m, "pp.edit") : newTab(m, "pp.new")] : [])],
    body: () => {
      if (one) return editForm({ m, resource: "hr-pay-periods", screen: "company-pay-period", booleans, fields, row: shape(one), prefix: "pp", nameOf: (r) => r.company_label });
      if (tab === "settings") return <PayrollSettings rows={settings} save={actions.saveSettings} canManage={canManage} locale={locale} />;
      if (tab === "actions") {
        return (
          <SideGrid
            ns="hrpayroll"
            listTitle={m("f.company")}
            items={companies.map((c) => ({ ...c, hint: m("ps.periods_count", { count: c.count }) }))}
            title={m("ps.periods")}
            hint={m("ps.actions_hint")}
            emptyTitle={m("ps.select_company")}
            empty={m("ps.select_company_hint")}
            gridEmpty={{ title: m("ps.no_periods_title"), text: m("ps.no_periods") }}
            footerKey="master.ps.actions_footer"
            columns={[
              { key: "period_no", label: m("f.pay_period"), type: "text", mono: true },
              { key: "fiscal_year", label: m("f.fiscal"), type: "text" },
              { key: "month", label: m("f.month"), type: "lookup", from: "month", options: options(m, "month", MONTHS) },
              { key: "period_type", label: m("f.type"), type: "text" },
              { key: "pay_element", label: m("f.pay_element"), type: "text" },
              { key: "pay_date", label: m("f.pay_date"), type: "text" },
              { key: "show_for_report", label: m("f.show_for_report"), type: "check" },
              { key: "closed_label", label: m("f.close_col"), type: "text" },
              { key: "total_pays", label: m("f.total_no"), type: "text" },
              { key: "total_payable", label: m("f.total_payable"), type: "text" },
              { key: "remarks", label: m("f.remarks"), type: "input", width: "w-56" },
              { key: "status_label", label: m("f.status"), type: "text" },
              { key: "action", label: m("f.action"), type: "actions", buttons: options(m, "move", MOVES).map((o) => ({ value: o.value, label: o.label })) },
            ]}
            load={actions.loadPeriods}
            save={actions.savePeriods}
            canManage={canManage}
            exportName="pay-periods"
          />
        );
      }
      if (tab === "fy") {
        return (
          <SideGrid
            ns="hrpayroll"
            listTitle={m("ps.fiscal_years")}
            items={registers.map((r) => ({ ...r, hint: m("ps.years_count", { count: r.count }) }))}
            title={m("ps.fy_register")}
            hint={m("ps.fy_hint")}
            emptyTitle={m("ps.select_register")}
            empty={m("ps.select_register_hint")}
            gridEmpty={{ title: m("ps.no_years_title"), text: m("ps.no_years") }}
            footerKey="master.ps.fy_footer"
            columns={[
              { key: "name", label: m("f.fy_name"), type: "input", width: "w-32", lockedBy: "id" },
              { key: "fy_start", label: m("f.fy_start"), type: "date" },
              { key: "fy_end", label: m("f.fy_end"), type: "date" },
              { key: "cal_start", label: m("f.cal_start"), type: "date" },
              { key: "cal_end", label: m("f.cal_end"), type: "date" },
              { key: "pay_periods", label: m("f.pay_periods"), type: "text" },
              { key: "active", label: m("f.active"), type: "check" },
              { key: "_status", from: "active", label: m("f.status"), type: "status", labels: { on: m("active_label"), off: m("inactive_label") } },
              { key: "_remove", label: m("f.action"), type: "action", blockedBy: "pay_periods" },
            ]}
            blank={{ name: "", fy_start: "", fy_end: "", cal_start: "", cal_end: "", pay_periods: 0, active: true }}
            addLabel={m("ps.add_year")}
            load={actions.loadFiscalYears}
            save={actions.saveFiscalYears}
            canManage={canManage}
            exportName="fiscal-years"
          />
        );
      }
      if (tab === "new" && canManage) return <EntryForm fields={fields} title={m("pp.new")} description={m("pp.hint")} action={actions.createResource.bind(null, "hr-pay-periods", booleans)} submitLabel={m("pp.save")} columns={3} successHref="/hr-payroll/company-pay-period?tab=records" />;
      return (
        <div className="flex flex-col gap-3">
          <FilterBar
            basePath="/hr-payroll/company-pay-period"
            keep={{ tab: "records" }}
            values={q}
            title={m("pp.searching")}
            hint={m("pp.search_hint")}
            fields={[
              { name: "company", label: m("f.company"), type: "select", options: [{ value: "", label: m("select_dash") }, look.company] },
              { name: "fiscal_year_id", label: m("f.fiscal_year"), type: "select", options: [{ value: "", label: m("select_dash") }, ...look.fiscal_years] },
              { name: "month", label: m("f.month"), type: "select", options: [{ value: "", label: m("select_dash") }, ...options(m, "month", MONTHS)] },
              { name: "pay_element_id", label: m("f.pay_element"), type: "select", options: [{ value: "", label: m("select_dash") }, ...look.elements] },
              { name: "period_no", label: m("f.pay_period_number"), type: "text", placeholder: "APP-C-001202606" },
              { name: "dept_type", label: m("f.dept_type"), type: "select", options: [{ value: "", label: m("select_dash") }, ...options(m, "dept", DEPT_TYPES)] },
              { name: "closed", label: m("f.closed_q"), type: "select", options: [{ value: "", label: m("select_closed") }, { value: "true", label: m("yes") }, { value: "false", label: m("no") }] },
              { name: "from", label: m("f.pay_from"), type: "date" },
              { name: "to", label: m("f.pay_to"), type: "date" },
            ]}
            labels={{ show: m("search"), reset: m("reset") }}
          />
          <RecordsGrid
            exportName="pay-periods"
            rows={shown}
            columns={[
              { key: "company_label", label: m("f.company") },
              { key: "fiscal_year", label: m("f.fiscal_year") },
              { key: "month_label", label: m("f.month") },
              { key: "period_type", label: m("f.type") },
              { key: "period_no", label: m("f.pay_period_no_col"), type: "mono" },
              { key: "pay_element", label: m("f.pay_element") },
              { key: "pay_date_label", label: m("f.pay_date") },
              { key: "report_yn", label: m("f.show_for_report_col") },
              { key: "closed_yn", label: m("f.close_col") },
              { key: "total_pays", label: m("f.total_pays"), type: "number", align: "right" },
              { key: "total_payable", label: m("f.total_payable"), type: "number", align: "right" },
              { key: "remarks", label: m("f.remarks") },
              { key: "status_label", label: m("f.status") },
            ]}
            edit={canManage ? { href: "/hr-payroll/company-pay-period?tab=edit&ref=", refKey: "period_no" } : undefined}
          />
        </div>
      );
    },
  };
}

const DEFAULT_TABS = { "festival-calendar": "festivals" };

const SCREENS = {
  "employee-type": employmentTypes,
  "designation-group": designationGroups,
  grade: payGrades,
  "pay-element": payElements,
  "allowance-entry": allowances,
  "head-wise-deduction": deductions,
  "shift-master": shifts,
  "leave-type": leaveTypes,
  "ot-rule": otRules,
  "bonus-rules-setup": bonusRules,
  "salary-policy-and-rules": wageGazette,
  "gazette-grade-wage": gradeWages,
  "festival-calendar": festivalCalendar,
  "company-pay-period": payrollSettings,
};

export { DEFAULT_TABS, SCREENS };
