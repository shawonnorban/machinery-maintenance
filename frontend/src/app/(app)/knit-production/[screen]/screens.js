import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { FilterBar } from "@/components/erp/filter-bar";
import { SideGrid } from "@/components/workstudy/side-grid";
import { ScQuotationEditor } from "@/components/dyehouse/sc-quotation-editor";
import * as actions from "./actions";

/*
 * Knit Production → Master Data (AnnotechERP 15/01): User Company & Office
 * Mapping, Machine Capacity Hour Plan, Knitting Machine Profile, Machine
 * Operator Assignment, Yarn Count Master, Label Printer Configuration and
 * Sub-Contract Party Rate — each with Brickland's tabs and filters, opening
 * on the tab Brickland opens on.
 */

const lookups = () => apiFetch("/knitting/lookups");
const all = (m) => ({ value: "", label: m("all") });
const num = (v) =>
  v === null || v === undefined || v === "" ? null : String(Number(v));

/**
 * Brickland's filter row above a Records grid. The choice lives in the url;
 * rows are narrowed here. `match`: eq (default), contains, from / to (dates).
 */
function filtered(rows, query, specs) {
  return rows.filter((r) =>
    specs.every(({ name, key = name, match = "eq" }) => {
      const want = query[name];
      if (!want) return true;
      const have = r[key];
      if (match === "contains")
        return String(have ?? "")
          .toLowerCase()
          .includes(want.toLowerCase());
      if (match === "from") return !have || String(have).slice(0, 10) >= want;
      if (match === "to") return !have || String(have).slice(0, 10) <= want;
      return String(have ?? "") === want;
    }),
  );
}

function filterBar(m, te, screen, query, fields) {
  return (
    <FilterBar
      basePath={`/knit-production/${screen}`}
      keep={{ tab: "records" }}
      values={query}
      fields={fields}
      labels={{ show: m("filter.show"), reset: m("filter.clear") }}
      instant
    />
  );
}

async function userOffices({
  m,
  te,
  tab,
  canManage,
  searchParams: query = {},
}) {
  const [mappings, users] = await Promise.all([
    apiFetch("/knitting/user-offices"),
    apiFetch("/knitting/office-users"),
  ]);
  const uniq = (key) =>
    [...new Set(mappings.map((r) => r[key]).filter(Boolean))].map((v) => ({
      value: v,
      label: v,
    }));
  return {
    tabs: [
      {
        key: "records",
        label: m("offices.records"),
        icon: "Table2",
        count: mappings.length,
      },
      { key: "mapping", label: m("offices.tab"), icon: "ListChecks" },
    ],
    body: () =>
      tab === "mapping" ? (
        <SideGrid
          ns="knitting"
          picker={{
            title: m("offices.search_by"),
            label: m("offices.user"),
            placeholder: m("offices.select_user"),
            load: m("offices.load"),
          }}
          listTitle={m("offices.user_list")}
          items={users}
          title={m("offices.office_list")}
          hint={m("offices.hint")}
          emptyTitle={m("offices.empty_title")}
          empty={m("offices.empty")}
          gridEmpty={{
            title: m("offices.no_offices_title"),
            text: m("offices.no_offices"),
          }}
          footerKey="master.offices.footer"
          selectedKey="selected"
          columns={[
            { key: "selected", label: m("f.select"), type: "check" },
            { key: "company", label: m("f.company"), type: "text" },
            { key: "office", label: m("f.office"), type: "text" },
            { key: "address", label: m("f.address"), type: "text" },
            { key: "is_primary", label: m("f.is_primary"), type: "check" },
            { key: "can_write", label: m("f.can_write"), type: "check" },
          ]}
          load={actions.loadOffices}
          save={actions.saveOffices}
          canManage={canManage}
          exportName="user-offices"
        />
      ) : (
        <div className="flex flex-col gap-3">
          {filterBar(m, te, "user-company-office-mapping", query, [
            {
              name: "user",
              label: m("f.user"),
              type: "select",
              options: [all(m), ...uniq("user")],
            },
            {
              name: "company",
              label: m("f.company"),
              type: "select",
              options: [all(m), ...uniq("company")],
            },
            {
              name: "office",
              label: m("f.office"),
              type: "select",
              options: [all(m), ...uniq("office")],
            },
          ])}
          <RecordsGrid
            exportName="user-company-office-mapping"
            rows={filtered(mappings, query, [
              { name: "user" },
              { name: "company" },
              { name: "office" },
            ])}
            columns={[
              { key: "user", label: m("f.user") },
              { key: "company", label: m("f.company_en") },
              { key: "office", label: m("f.office") },
              { key: "address", label: m("f.address") },
              { key: "is_primary", label: m("f.is_primary"), type: "yesno" },
              { key: "can_write", label: m("f.can_write"), type: "yesno" },
            ]}
          />
        </div>
      ),
  };
}

async function capacityPlans({
  m,
  te,
  tab,
  canManage,
  searchParams: query = {},
}) {
  const [rows, look] = await Promise.all([
    apiFetch("/erp/knit-capacity-plans"),
    lookups(),
  ]);
  const fields = [
    {
      name: "machine_id",
      label: m("f.machine_no"),
      type: "select",
      required: true,
      options: look.machines,
    },
    {
      name: "floor_display",
      label: m("f.floor"),
      type: "derived",
      from: "machine_id",
      values: Object.fromEntries(
        look.machines.map((x) => [x.value, x.floor ?? ""]),
      ),
    },
    {
      name: "shift_id",
      label: m("f.schedule"),
      type: "select",
      options: [{ value: "", label: m("select_dash") }, ...look.shifts],
      placeholder: m("select_dash"),
      help: m("capacity.schedule_hint"),
    },
    {
      name: "effective_from",
      label: m("f.effective_from"),
      type: "date",
      required: true,
    },
    {
      name: "effective_to",
      label: m("f.effective_to"),
      type: "date",
      help: m("capacity.to_hint"),
    },
    {
      name: "plan_hours",
      label: m("f.plan_hours"),
      type: "number",
      required: true,
      suffix: m("unit.hrs"),
      placeholder: "22",
      step: "0.01",
    },
    {
      name: "running_days",
      label: m("f.running_days"),
      type: "number",
      required: true,
      suffix: m("unit.days"),
      placeholder: "26",
    },
    {
      name: "efficiency",
      label: m("f.efficiency"),
      type: "number",
      suffix: "%",
      placeholder: "85",
      step: "0.01",
    },
    {
      name: "capacity_per_hour",
      label: m("f.capacity_per_hour"),
      type: "number",
      required: true,
      suffix: m("unit.kg"),
      placeholder: "8.500",
      step: "0.001",
      help: m("capacity.capacity_hint"),
    },
    { name: "remarks", label: m("f.remarks"), type: "textarea", span: 3 },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ];
  return {
    tabs: [
      {
        key: "records",
        label: m("capacity.records"),
        icon: "Table2",
        count: rows.length,
      },
      ...(canManage
        ? [{ key: "new", label: m("capacity.new"), icon: "Plus" }]
        : []),
    ],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm
          fields={fields}
          description={m("capacity.hint")}
          action={actions.createResource.bind(null, "knit-capacity-plans", [
            "active",
          ])}
          title={m("capacity.new")}
          submitLabel={m("capacity.save")}
          columns={3}
          successHref="/knit-production/machine-capacity-hour-plan?tab=records"
        />
      ) : (
        <div className="flex flex-col gap-3">
          {filterBar(m, te, "machine-capacity-hour-plan", query, [
            {
              name: "machine",
              label: m("f.machine_no"),
              type: "text",
              placeholder: "01",
            },
            {
              name: "floor",
              label: m("f.floor"),
              type: "select",
              options: [
                all(m),
                ...look.floors.map((f) => ({ value: f.label, label: f.label })),
              ],
            },
            {
              name: "shift",
              label: m("f.schedule"),
              type: "select",
              options: [
                all(m),
                ...look.shifts.map((s) => ({ value: s.label, label: s.label })),
              ],
            },
          ])}
          <RecordsGrid
            exportName="machine-capacity-hour-plan"
            rows={filtered(rows, query, [
              { name: "machine", key: "machine_no", match: "contains" },
              { name: "floor" },
              { name: "shift" },
            ]).map((r) => ({
              ...r,
              shift_id: r.shift_id ?? "",
              effective_to: r.effective_to ?? "",
              plan_hours: num(r.plan_hours),
              running_days: String(r.running_days),
              efficiency: num(r.efficiency),
              capacity_per_hour: num(r.capacity_per_hour),
              plan_qty_month: num(r.plan_qty_month),
            }))}
            columns={[
              { key: "machine_no", label: m("f.machine_no"), type: "mono" },
              { key: "floor", label: m("f.floor") },
              { key: "shift", label: m("f.schedule") },
              { key: "effective_from", label: m("f.from"), type: "date" },
              { key: "effective_to", label: m("f.to"), type: "date" },
              {
                key: "plan_hours",
                label: m("f.plan_hours"),
                type: "number",
                align: "right",
              },
              {
                key: "running_days",
                label: m("f.days_month"),
                type: "number",
                align: "right",
              },
              {
                key: "efficiency",
                label: m("f.efficiency"),
                type: "number",
                align: "right",
              },
              {
                key: "capacity_per_hour",
                label: m("f.capacity_per_hour"),
                type: "number",
                align: "right",
              },
              {
                key: "plan_qty_month",
                label: m("f.plan_qty_month"),
                type: "number",
                align: "right",
              },
            ]}
            edit={
              canManage
                ? {
                    fields,
                    action: actions.updateResource.bind(
                      null,
                      "knit-capacity-plans",
                      ["active"],
                    ),
                    title: m("capacity.edit"),
                    submitLabel: m("save"),
                    columns: 3,
                  }
                : undefined
            }
          />
        </div>
      ),
  };
}

async function machineProfile({
  m,
  te,
  tab,
  canManage,
  searchParams: query = {},
}) {
  const [rows, lists, look] = await Promise.all([
    apiFetch("/erp/knit-machines"),
    apiFetch("/knitting/ref-lists"),
    lookups(),
  ]);
  const ref = (list) => look.refs[list] ?? [];
  const fields = [
    {
      name: "machine_no",
      label: m("f.machine_no"),
      required: true,
      placeholder: "01",
      help: m("machine.no_hint"),
    },
    {
      name: "machine_type_id",
      label: m("f.machine_type"),
      type: "select",
      required: true,
      options: ref("MC_TYPE"),
      placeholder: m("select_dash"),
    },
    {
      name: "brand_id",
      label: m("f.brand"),
      type: "select",
      required: true,
      options: ref("BRAND"),
      placeholder: m("select_dash"),
    },
    { name: "model", label: m("f.model"), placeholder: "MV4-3.2 II" },
    {
      name: "origin_country_id",
      label: m("f.country"),
      type: "select",
      required: true,
      options: look.countries,
      placeholder: m("select_dash"),
      help: m("machine.country_hint"),
    },
    { name: "serial_no", label: m("f.serial_no"), placeholder: "01" },
    {
      name: "dia_id",
      label: m("f.dia"),
      type: "select",
      options: [{ value: "", label: m("machine.select_dia") }, ...ref("DIA")],
      placeholder: m("machine.select_dia"),
    },
    {
      name: "gauge_id",
      label: m("f.gauge"),
      type: "select",
      options: [{ value: "", label: m("machine.select_gg") }, ...ref("GG")],
      placeholder: m("machine.select_gg"),
      help: m("machine.gauge_hint"),
    },
    {
      name: "supported_gauge_ids",
      label: m("f.supported"),
      type: "multiselect",
      options: ref("GG"),
      help: m("machine.supported_hint"),
    },
    {
      name: "capacity_kg",
      label: m("f.capacity_kg"),
      type: "number",
      placeholder: "180",
      step: "0.01",
    },
    {
      name: "feeders",
      label: m("f.feeders"),
      type: "number",
      placeholder: "96",
    },
    { name: "rpm", label: m("f.rpm"), type: "number", placeholder: "24" },
    {
      name: "efficiency",
      label: m("f.efficiency_m"),
      type: "number",
      placeholder: "85",
      step: "0.01",
    },
    {
      name: "floor_id",
      label: m("f.floor"),
      type: "select",
      required: true,
      options: look.floors,
      placeholder: m("select_dash"),
    },
    {
      name: "mfg_year",
      label: m("f.mfg_year"),
      type: "number",
      placeholder: "YYYY",
    },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ];
  const opts = (list) => [
    all(m),
    ...ref(list).map((o) => ({ value: o.value, label: o.label })),
  ];
  return {
    tabs: [
      {
        key: "records",
        label: m("machine.records"),
        icon: "Table2",
        count: rows.length,
      },
      { key: "lists", label: m("machine.lists"), icon: "ListChecks" },
      ...(canManage
        ? [{ key: "new", label: m("machine.new_tab"), icon: "Plus" }]
        : []),
    ],
    body: () => {
      if (tab === "new" && canManage) {
        return (
          <EntryForm
            fields={fields}
            title={m("machine.new")}
            description={m("machine.hint")}
            action={actions.createResource.bind(null, "knit-machines", [
              "active",
            ])}
            submitLabel={m("machine.save")}
            columns={3}
            successHref="/knit-production/machine-profile?tab=records"
          />
        );
      }
      if (tab === "lists") {
        return (
          <SideGrid
            ns="knitting"
            listTitle={m("lists.list")}
            items={lists.map((l) => ({
              key: l.key,
              label: m(`lists.name.${l.key}`),
              hint: m(`lists.hint.${l.key}`),
              count: l.count,
            }))}
            title={m("lists.entries")}
            hint={m("lists.intro")}
            emptyTitle={m("lists.empty_title")}
            empty={m("lists.empty")}
            gridEmpty={{
              title: m("lists.no_entries_title"),
              text: m("lists.no_entries"),
            }}
            footerKey="master.lists.footer"
            addLabel={m("lists.add")}
            columns={[
              {
                key: "code",
                label: m("f.code"),
                type: "input",
                lockedBy: "id",
                width: "w-28",
              },
              { key: "name", label: m("f.name"), type: "input", width: "w-52" },
              { key: "sort_order", label: m("f.order"), type: "number" },
              { key: "machines", label: m("f.machines"), type: "text" },
              { key: "active", label: m("f.active"), type: "check" },
              {
                key: "_status",
                from: "active",
                label: m("f.status"),
                type: "status",
                labels: { on: m("active_label"), off: m("inactive_label") },
              },
              {
                key: "_remove",
                label: m("f.action"),
                type: "action",
                blockedBy: "machines",
              },
            ]}
            blank={{
              code: "",
              name: "",
              sort_order: "",
              machines: 0,
              active: true,
            }}
            load={actions.loadRefEntries}
            save={actions.saveRefEntries}
            canManage={canManage}
            exportName="machine-reference-list"
          />
        );
      }
      return (
        <div className="flex flex-col gap-3">
          {filterBar(m, te, "machine-profile", query, [
            {
              name: "machine",
              label: m("f.machine_no"),
              type: "text",
              placeholder: "01",
            },
            {
              name: "type",
              label: m("f.machine_type"),
              type: "select",
              options: opts("MC_TYPE"),
            },
            {
              name: "brand",
              label: m("f.brand"),
              type: "select",
              options: opts("BRAND"),
            },
            {
              name: "floor",
              label: m("f.floor"),
              type: "select",
              options: [all(m), ...look.floors],
            },
            {
              name: "gg",
              label: m("f.gg"),
              type: "select",
              options: opts("GG"),
            },
            {
              name: "dia",
              label: m("f.dia_short"),
              type: "select",
              options: opts("DIA"),
            },
            {
              name: "active",
              label: m("f.active"),
              type: "select",
              options: [
                all(m),
                { value: "true", label: m("active_label") },
                { value: "false", label: m("withdrawn") },
              ],
            },
          ])}
          <RecordsGrid
            exportName="knitting-machines"
            rows={filtered(rows, query, [
              { name: "machine", key: "machine_no", match: "contains" },
              { name: "type", key: "machine_type_id" },
              { name: "brand", key: "brand_id" },
              { name: "floor", key: "floor_id" },
              { name: "gg", key: "gauge_id" },
              { name: "dia", key: "dia_id" },
              { name: "active" },
            ]).map((r) => ({
              ...r,
              dia_id: r.dia_id ?? "",
              gauge_id: r.gauge_id ?? "",
              capacity_kg: num(r.capacity_kg),
              efficiency: num(r.efficiency),
              feeders: r.feeders === null ? null : String(r.feeders),
              rpm: r.rpm === null ? null : String(r.rpm),
              mfg_year: r.mfg_year === null ? null : String(r.mfg_year),
              dia_gg: [r.dia, r.gauge].filter(Boolean).join(" · "),
            }))}
            columns={[
              { key: "machine_no", label: m("f.machine_no"), type: "mono" },
              { key: "machine_type", label: m("f.machine_type") },
              { key: "brand", label: m("f.brand") },
              { key: "model", label: m("f.model") },
              { key: "dia_gg", label: m("f.dia_gg") },
              { key: "supported", label: m("f.supported_short") },
              {
                key: "capacity_kg",
                label: m("f.capacity_kg_col"),
                type: "number",
                align: "right",
              },
              {
                key: "feeders",
                label: m("f.feeders"),
                type: "number",
                align: "right",
              },
              { key: "rpm", label: m("f.rpm"), type: "number", align: "right" },
              {
                key: "efficiency",
                label: m("f.efficiency_m"),
                type: "number",
                align: "right",
              },
              { key: "floor", label: m("f.floor") },
              { key: "country", label: m("f.country") },
              { key: "serial_no", label: m("f.serial_no") },
              { key: "mfg_year", label: m("f.mfg_year") },
              {
                key: "active",
                label: m("f.status"),
                type: "status",
                labels: { true: m("active_label"), false: m("withdrawn") },
              },
            ]}
            edit={
              canManage
                ? {
                    fields,
                    action: actions.updateResource.bind(null, "knit-machines", [
                      "active",
                    ]),
                    title: m("machine.edit"),
                    submitLabel: m("save"),
                    columns: 3,
                  }
                : undefined
            }
          />
        </div>
      );
    },
  };
}

async function operators({ m, te, tab, canManage, searchParams: query = {} }) {
  const [rows, machines, look] = await Promise.all([
    apiFetch("/erp/knit-operator-postings"),
    apiFetch("/knitting/posting-machines"),
    lookups(),
  ]);
  const live = rows.filter((r) => r.active);
  const fields = [
    {
      name: "machine_id",
      label: m("f.machine_no"),
      type: "select",
      required: true,
      options: look.machines,
    },
    {
      name: "floor_display",
      label: m("f.floor"),
      type: "derived",
      from: "machine_id",
      values: Object.fromEntries(
        look.machines.map((x) => [x.value, x.floor ?? ""]),
      ),
    },
    {
      name: "user_id",
      label: m("f.operator"),
      type: "select",
      required: true,
      options: look.users.map((u) => ({
        value: u.value,
        label: u.code ? `${u.label} (${u.code})` : u.label,
      })),
    },
    {
      name: "code_display",
      label: m("f.emp_code"),
      type: "derived",
      from: "user_id",
      values: Object.fromEntries(
        look.users.map((u) => [u.value, u.code ?? ""]),
      ),
    },
    {
      name: "name_display",
      label: m("f.name"),
      type: "derived",
      from: "user_id",
      values: Object.fromEntries(look.users.map((u) => [u.value, u.label])),
      span: 3,
    },
    {
      name: "shift_id",
      label: m("f.schedule"),
      type: "select",
      required: true,
      options: look.shifts,
      placeholder: m("select_dash"),
    },
    {
      name: "effective_at",
      label: m("f.effective"),
      type: "datetime",
      required: true,
      help: m("operator.effective_hint"),
    },
    {
      name: "until_at",
      label: m("f.until"),
      type: "datetime",
      help: m("operator.until_hint"),
    },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ];
  const fmt = (v) => (v ? v.replace("T", " ") : "");
  return {
    tabs: [
      {
        key: "records",
        label: m("operator.records"),
        icon: "Table2",
        count: live.length,
      },
      {
        key: "machines",
        label: m("operator.machine_wise"),
        icon: "ListChecks",
      },
      ...(canManage
        ? [{ key: "new", label: m("operator.new_tab"), icon: "Plus" }]
        : []),
    ],
    body: () => {
      if (tab === "new" && canManage) {
        return (
          <EntryForm
            fields={fields}
            title={m("operator.new")}
            description={m("operator.hint")}
            action={actions.createResource.bind(
              null,
              "knit-operator-postings",
              ["active"],
            )}
            submitLabel={m("operator.save")}
            columns={3}
            successHref="/knit-production/assign-machine-operator?tab=records"
          />
        );
      }
      if (tab === "machines") {
        return (
          <SideGrid
            ns="knitting"
            listTitle={m("operator.machine_list")}
            items={machines}
            title={m("operator.assigned")}
            hint={m("operator.machine_hint")}
            emptyTitle={m("operator.empty_title")}
            empty={m("operator.empty")}
            gridEmpty={{
              title: m("operator.no_postings_title"),
              text: m("operator.no_postings"),
            }}
            footerKey="master.operator.footer"
            columns={[
              {
                key: "emp_code",
                label: m("f.emp_code"),
                type: "text",
                mono: true,
              },
              { key: "name", label: m("f.name"), type: "text" },
              { key: "shift", label: m("f.schedule"), type: "text" },
              { key: "effective_label", label: m("f.effective"), type: "text" },
              {
                key: "until_at",
                label: m("f.until"),
                type: "datetime",
                width: "w-52",
              },
              { key: "_remove", label: m("operator.withdraw"), type: "action" },
            ]}
            load={actions.loadPostings}
            save={actions.savePostings}
            canManage={canManage}
            exportName="machine-postings"
          />
        );
      }
      return (
        <div className="flex flex-col gap-3">
          {filterBar(m, te, "assign-machine-operator", query, [
            {
              name: "machine",
              label: m("f.machine_no"),
              type: "text",
              placeholder: "01",
            },
            {
              name: "shift",
              label: m("f.schedule"),
              type: "select",
              options: [
                all(m),
                ...look.shifts.map((s) => ({ value: s.label, label: s.label })),
              ],
            },
            {
              name: "floor",
              label: m("f.floor"),
              type: "select",
              options: [
                all(m),
                ...look.floors.map((f) => ({ value: f.label, label: f.label })),
              ],
            },
            {
              name: "standing",
              label: m("f.standing"),
              type: "select",
              options: [
                all(m),
                { value: "true", label: m("operator.standing") },
                { value: "false", label: m("operator.ended") },
              ],
            },
          ])}
          <RecordsGrid
            exportName="machine-operator-postings"
            rows={filtered(live, query, [
              { name: "machine", key: "machine_no", match: "contains" },
              { name: "shift" },
              { name: "floor" },
              { name: "standing" },
            ]).map((r) => ({
              ...r,
              until_at: r.until_at ?? "",
              effective_text: fmt(r.effective_at),
              until_text: fmt(r.until_at),
            }))}
            columns={[
              { key: "machine_no", label: m("f.machine_no"), type: "mono" },
              { key: "emp_code", label: m("f.emp_code"), type: "mono" },
              { key: "name", label: m("f.name") },
              { key: "shift", label: m("f.schedule") },
              { key: "effective_text", label: m("f.effective") },
              { key: "until_text", label: m("f.until") },
              { key: "floor", label: m("f.floor") },
            ]}
            edit={
              canManage
                ? {
                    fields,
                    action: actions.updateResource.bind(
                      null,
                      "knit-operator-postings",
                      ["active"],
                    ),
                    title: m("operator.edit"),
                    submitLabel: m("save"),
                    columns: 3,
                  }
                : undefined
            }
          />
        </div>
      );
    },
  };
}

async function yarnCounts({ m, te, tab, canManage, searchParams: query = {} }) {
  const rows = await apiFetch("/erp/knit-yarn-counts");
  const systems = ["Ne", "Nm", "D", "Tex", "Dtex"].map((s) => ({
    value: s,
    label: s,
  }));
  const fields = [
    {
      name: "count_no",
      label: m("f.count_no"),
      required: true,
      placeholder: "30/1",
      help: m("yarn.no_hint"),
    },
    {
      name: "count_system",
      label: m("f.count_system"),
      type: "select",
      required: true,
      options: systems,
    },
    {
      name: "description",
      label: m("f.count_desc"),
      required: true,
      placeholder: "30/1 Ne",
      help: m("yarn.desc_hint"),
    },
    {
      name: "count_value",
      label: m("f.count_value"),
      type: "number",
      placeholder: "30",
      step: "0.01",
      help: m("yarn.value_hint"),
    },
    {
      name: "is_single",
      label: m("f.is_single"),
      type: "checkbox",
      default: true,
      help: m("yarn.single_hint"),
    },
    { name: "display_order", label: m("f.display_order"), type: "number" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ];
  const yn = { true: "Y", false: "N" };
  return {
    tabs: [
      {
        key: "records",
        label: m("yarn.records"),
        icon: "Table2",
        count: rows.length,
      },
      ...(canManage
        ? [{ key: "new", label: m("yarn.new_tab"), icon: "Plus" }]
        : []),
    ],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm
          fields={fields}
          title={m("yarn.new")}
          description={m("yarn.hint")}
          action={actions.createResource.bind(null, "knit-yarn-counts", [
            "is_single",
            "active",
          ])}
          submitLabel={m("yarn.save")}
          columns={3}
          successHref="/knit-production/yarn-count-setup?tab=records"
        />
      ) : (
        <div className="flex flex-col gap-3">
          {filterBar(m, te, "yarn-count-setup", query, [
            {
              name: "system",
              label: m("f.count_system"),
              type: "select",
              options: [{ value: "", label: m("yarn.any_system") }, ...systems],
            },
            {
              name: "single",
              label: m("f.is_single"),
              type: "select",
              options: [
                all(m),
                { value: "true", label: m("yarn.single") },
                { value: "false", label: m("yarn.plied") },
              ],
            },
            {
              name: "active",
              label: m("f.active"),
              type: "select",
              options: [
                all(m),
                { value: "true", label: m("active_label") },
                { value: "false", label: m("withdrawn") },
              ],
            },
          ])}
          <RecordsGrid
            exportName="yarn-counts"
            rows={filtered(rows, query, [
              { name: "system", key: "count_system" },
              { name: "single", key: "is_single" },
              { name: "active" },
            ]).map((r) => ({
              ...r,
              count_value: num(r.count_value),
              display_order: String(r.display_order),
              single_yn: r.is_single,
              active_yn: r.active,
            }))}
            columns={[
              { key: "count_no", label: m("f.count_no"), type: "mono" },
              {
                key: "single_yn",
                label: m("f.is_single"),
                type: "badge",
                labels: yn,
              },
              {
                key: "active_yn",
                label: m("f.active"),
                type: "badge",
                labels: yn,
              },
              { key: "count_system", label: m("f.count_system") },
              { key: "description", label: m("f.count_desc") },
              {
                key: "count_value",
                label: m("f.count_value"),
                type: "number",
                align: "right",
              },
              {
                key: "active",
                label: m("f.status"),
                type: "status",
                labels: { true: m("active_label"), false: m("withdrawn") },
              },
            ]}
            rowAction={
              canManage
                ? {
                    label: m("withdraw"),
                    action: actions.withdrawResource.bind(
                      null,
                      "knit-yarn-counts",
                    ),
                  }
                : undefined
            }
            edit={
              canManage
                ? {
                    fields,
                    action: actions.updateResource.bind(
                      null,
                      "knit-yarn-counts",
                      ["is_single", "active"],
                    ),
                    title: m("yarn.edit"),
                    submitLabel: m("save"),
                    columns: 3,
                  }
                : undefined
            }
            remove={
              canManage
                ? actions.deleteResource.bind(null, "knit-yarn-counts")
                : undefined
            }
          />
        </div>
      ),
  };
}

async function printers({ m, te, tab, canManage, searchParams: query = {} }) {
  const [rows, look] = await Promise.all([
    apiFetch("/erp/knit-label-printers"),
    lookups(),
  ]);
  const fields = [
    {
      name: "name",
      label: m("f.printer_name"),
      required: true,
      placeholder: "MFL-SOUTH",
      help: m("printer.name_hint"),
    },
    {
      name: "address",
      label: m("f.printer_address"),
      required: true,
      placeholder: "Printer1",
      help: m("printer.address_hint"),
    },
    {
      name: "user_ids",
      label: m("f.user_list"),
      type: "multiselect",
      options: look.users.map((u) => ({
        value: u.value,
        label: `${u.label} (${u.login})`,
      })),
      help: m("printer.users_hint"),
    },
    {
      name: "auto_scale",
      label: m("f.auto_scale"),
      type: "checkbox",
      help: m("printer.auto_hint"),
    },
    { name: "display_order", label: m("f.display_order"), type: "number" },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ];
  return {
    tabs: [
      {
        key: "records",
        label: m("printer.records"),
        icon: "Table2",
        count: rows.length,
      },
      ...(canManage
        ? [{ key: "new", label: m("printer.new_tab"), icon: "Plus" }]
        : []),
    ],
    body: () =>
      tab === "new" && canManage ? (
        <EntryForm
          fields={fields}
          title={m("printer.new")}
          description={m("printer.hint")}
          action={actions.createResource.bind(null, "knit-label-printers", [
            "auto_scale",
            "active",
          ])}
          submitLabel={m("printer.save")}
          columns={2}
          successHref="/knit-production/label-printer-setup?tab=records"
        />
      ) : (
        <div className="flex flex-col gap-3">
          {filterBar(m, te, "label-printer-setup", query, [
            {
              name: "printer",
              label: m("f.printer_name"),
              type: "text",
              placeholder: "EMS",
            },
            {
              name: "auto",
              label: m("f.auto_scale_short"),
              type: "select",
              options: [
                all(m),
                { value: "true", label: m("printer.auto") },
                { value: "false", label: m("printer.fixed") },
              ],
            },
          ])}
          <RecordsGrid
            exportName="label-printers"
            rows={filtered(rows, query, [
              { name: "printer", key: "name", match: "contains" },
              { name: "auto", key: "auto_scale" },
            ]).map((r) => ({
              ...r,
              display_order: String(r.display_order),
              users: r.users ?? m("printer.everyone"),
            }))}
            columns={[
              { key: "name", label: m("f.printer_name") },
              { key: "address", label: m("f.printer_address"), type: "mono" },
              { key: "users", label: m("f.user_list") },
              {
                key: "auto_scale",
                label: m("f.auto_scale_short"),
                type: "yesno",
              },
              { key: "active", label: m("f.status"), type: "status" },
            ]}
            edit={
              canManage
                ? {
                    fields,
                    action: actions.updateResource.bind(
                      null,
                      "knit-label-printers",
                      ["auto_scale", "active"],
                    ),
                    title: m("printer.edit"),
                    submitLabel: m("save"),
                    columns: 2,
                  }
                : undefined
            }
            remove={
              canManage
                ? actions.deleteResource.bind(null, "knit-label-printers")
                : undefined
            }
          />
        </div>
      ),
  };
}

async function scRates({ m, te, tab, canManage, searchParams: query = {} }) {
  const [quotations, look] = await Promise.all([
    apiFetch("/dyehouse/sc-quotations"),
    lookups(),
  ]);
  const groups = [
    "KNITTING",
    "YARN_DYEING",
    "DYEING",
    "DYEING_FINISHING",
    "AOP",
    "YARN_TWIST",
  ];
  return {
    tabs: [
      {
        key: "records",
        label: m("sc.records"),
        icon: "Table2",
        count: quotations.length,
      },
      { key: "entry", label: m("sc.tab"), icon: "ListChecks" },
    ],
    body: () =>
      tab === "entry" ? (
        <ScQuotationEditor
          quotations={quotations}
          parties={look.suppliers}
          currencies={look.currencies}
          loadHeads={actions.loadHeads}
          load={actions.loadQuotation}
          save={actions.saveQuotation}
          canManage={canManage}
          initialId={query.quotation ?? null}
        />
      ) : (
        <div className="flex flex-col gap-3">
          {filterBar(m, te, "sc-party-rate", query, [
            { name: "ref", label: m("f.ref_no"), type: "text" },
            {
              name: "group",
              label: m("f.rate_group"),
              type: "select",
              options: [
                all(m),
                ...groups.map((g) => ({ value: g, label: m(`sc.group.${g}`) })),
              ],
            },
            {
              name: "party",
              label: m("f.party"),
              type: "select",
              options: [
                all(m),
                ...[
                  ...new Set(quotations.map((q) => q.party).filter(Boolean)),
                ].map((p) => ({ value: p, label: p })),
              ],
            },
            {
              name: "status",
              label: m("f.status"),
              type: "select",
              options: [
                all(m),
                ...[
                  "DRAFT",
                  "SUBMITTED",
                  "APPROVED",
                  "REJECTED",
                  "CANCELLED",
                ].map((s) => ({ value: s, label: m(`sc.status.${s}`) })),
              ],
            },
          ])}
          <RecordsGrid
            exportName="sc-party-rates"
            rows={filtered(quotations, query, [
              { name: "ref", key: "quotation_no", match: "contains" },
              { name: "group", key: "rate_group" },
              { name: "party" },
              { name: "status" },
            ]).map((q) => ({
              ...q,
              group_label: m(`sc.group.${q.rate_group}`),
              open: `/knit-production/sc-party-rate?tab=entry&quotation=${encodeURIComponent(q.quotation_no)}`,
            }))}
            columns={[
              { key: "quotation_no", label: m("f.ref_no"), type: "mono" },
              { key: "quotation_date", label: m("f.date"), type: "date" },
              { key: "party", label: m("f.party") },
              { key: "group_label", label: m("f.rate_group") },
              { key: "currency", label: m("f.currency"), type: "mono" },
              {
                key: "lines",
                label: m("f.rates"),
                type: "number",
                align: "right",
              },
              {
                key: "docs",
                label: m("f.docs"),
                type: "number",
                align: "right",
              },
              { key: "open", label: "", type: "link", sortable: false },
            ]}
          />
        </div>
      ),
  };
}

const SCREENS = {
  "user-company-office-mapping": userOffices,
  "machine-capacity-hour-plan": capacityPlans,
  "machine-profile": machineProfile,
  "assign-machine-operator": operators,
  "yarn-count-setup": yarnCounts,
  "label-printer-setup": printers,
  "sc-party-rate": scRates,
};

/** The tab each screen opens on, as Brickland opens it. */
const DEFAULT_TABS = {
  "user-company-office-mapping": "mapping",
  "sc-party-rate": "entry",
  "receive-sc-program-from-party": "entry",
  "test-for-production": "entry",
  "approval-without-test": "entry",
  "knit-plan-for-sample-solid": "entry",
  "knit-plan-for-sample-yd": "entry",
  "direct-fabric-booking": "entry",
  "machine-loading-plan": "entry",
};

export { SCREENS, DEFAULT_TABS };
