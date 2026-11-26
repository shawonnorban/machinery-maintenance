import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import { AccessGrid } from "@/components/workstudy/access-grid";
import { SideGrid } from "@/components/workstudy/side-grid";
import { CurveEditor } from "@/components/workstudy/curve-editor";
import * as actions from "./actions";

/*
 * Work Study → Master Data (AnnotechERP 14/01): Item & Process Grouping,
 * Floor-Wise Target Setup, Finishing Floor Access Setup, Learning Curve Model,
 * Responsible Owner Mapping and Resource Capability Profile — each with
 * Brickland's tabs, and opening on the tab Brickland opens on.
 */

const FABRIC_CLASSES = ["FLEECE", "PIQUE", "SINGLE_JERSEY", "TWILL"];
const PROD_TYPES = ["BASIC", "SEMI_CRITICAL", "CRITICAL", "HIGHLY_CRITICAL"];
const MACHINE_TYPES = [
  "SNLS",
  "OVERLOCK",
  "FLATLOCK",
  "BUTTON_ATTACH",
  "BUTTON_HOLE",
  "BARTACK",
];

const options = (m, group, values) =>
  values.map((v) => ({ value: v, label: m(`opt.${group}.${v}`) }));
const lookups = () => apiFetch("/workstudy/lookups");
const namesOf = (list, ids) =>
  (ids ?? [])
    .map((id) => list.find((x) => x.value === id)?.label ?? "?")
    .join(", ");
const recordsTab = (te, count) => ({
  key: "records",
  label: te("records"),
  icon: "Table2",
  count,
});

async function itemParts({ m, te, tab, canManage }) {
  const [rows, items, look] = await Promise.all([
    apiFetch("/erp/ws-item-parts"),
    apiFetch("/erp/ws-garment-items"),
    lookups(),
  ]);
  const fields = [
    {
      name: "garment_item_id",
      label: m("f.garment_item"),
      type: "select",
      required: true,
      options: look.items,
    },
    {
      name: "garment_part_id",
      label: m("f.part"),
      type: "select",
      required: true,
      options: look.parts,
    },
    {
      name: "component_sl",
      label: m("f.component_sl"),
      type: "number",
      required: true,
    },
    {
      name: "included",
      label: m("f.included"),
      type: "checkbox",
      default: true,
    },
  ];
  const itemFields = [
    { name: "item_group", label: m("f.item_group"), required: true },
    { name: "name", label: m("f.garment_item"), required: true },
    { name: "active", label: m("f.active"), type: "checkbox", default: true },
  ];
  return {
    tabs: [
      recordsTab(te, rows.length),
      ...(canManage
        ? [{ key: "new", label: m("parts.new"), icon: "Plus" }]
        : []),
      {
        key: "items",
        label: m("parts.items"),
        icon: "Shirt",
        count: items.length,
      },
    ],
    body: () => {
      if (tab === "new" && canManage) {
        return (
          <EntryForm
            fields={fields}
            description={m("parts.hint")}
            action={actions.createResource.bind(null, "ws-item-parts", [
              "included",
            ])}
            title={m("parts.new")}
            submitLabel={m("parts.save")}
            columns={2}
            successHref="/work-study/item-and-process-group?tab=records"
          />
        );
      }
      if (tab === "items") {
        return (
          <div className="flex flex-col gap-3">
            {canManage ? (
              <div className="flex justify-end">
                <DialogFormButton
                  variant="primary"
                  label={m("parts.new_item")}
                  title={m("parts.new_item")}
                  description={m("parts.item_hint")}
                  fields={itemFields}
                  action={actions.createResource.bind(
                    null,
                    "ws-garment-items",
                    ["active"],
                  )}
                  submitLabel={m("save")}
                />
              </div>
            ) : null}
            <RecordsGrid
              exportName="garment-items"
              rows={items}
              columns={[
                { key: "item_group", label: m("f.item_group") },
                { key: "name", label: m("f.garment_item") },
                { key: "active", label: m("f.status"), type: "status" },
              ]}
              edit={
                canManage
                  ? {
                      fields: itemFields,
                      action: actions.updateResource.bind(
                        null,
                        "ws-garment-items",
                        ["active"],
                      ),
                      title: m("parts.edit_item"),
                      submitLabel: m("save"),
                    }
                  : undefined
              }
              remove={
                canManage
                  ? actions.deleteResource.bind(null, "ws-garment-items")
                  : undefined
              }
            />
          </div>
        );
      }
      return (
        <RecordsGrid
          exportName="item-and-process-group"
          rows={rows.map((r) => ({
            ...r,
            component_sl: String(r.component_sl),
          }))}
          columns={[
            { key: "item_group", label: m("f.item_group") },
            { key: "item", label: m("f.garment_item") },
            { key: "part", label: m("f.part") },
            {
              key: "component_sl",
              label: m("f.component_sl"),
              type: "number",
              align: "right",
            },
            {
              key: "is_process_group",
              label: m("f.is_process_group"),
              type: "yesno",
            },
            { key: "is_body", label: m("f.is_body"), type: "yesno" },
            {
              key: "included",
              label: m("f.status"),
              type: "status",
              labels: { true: m("included"), false: m("excluded") },
            },
          ]}
          edit={
            canManage
              ? {
                  fields,
                  action: actions.updateResource.bind(null, "ws-item-parts", [
                    "included",
                  ]),
                  title: m("parts.edit"),
                  submitLabel: m("save"),
                  columns: 2,
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-item-parts")
              : undefined
          }
        />
      );
    },
  };
}

function accessScreen({ kind, key, screen }) {
  return async function render({ m, te, tab, canManage }) {
    const [rows, look] = await Promise.all([
      apiFetch(`/workstudy/${kind}`),
      lookups(),
    ]);
    const userName = (id) =>
      look.users.find((u) => u.value === id)?.label ?? "?";
    const rights = [
      { value: "W", label: m("opt.right.W") },
      { value: "R", label: m("opt.right.R") },
    ];
    const floors = {
      key: "floor_ids",
      label: m("f.floor_list"),
      type: "multi",
      options: look.floors,
      placeholder: m("grid.select_floor"),
      width: "w-56",
    };
    const user = {
      key: "user_id",
      label: m("f.user"),
      type: "select",
      options: look.users,
    };
    const remove = { key: "_remove", label: m("grid.action"), type: "action" };
    const columns =
      key === "target"
        ? [
            floors,
            user,
            {
              key: "target_right",
              label: m("f.target_right"),
              type: "select",
              options: rights,
              width: "w-40",
            },
            {
              key: "product_right",
              label: m("f.product_right"),
              type: "select",
              options: rights,
              width: "w-40",
            },
            { key: "is_admin", label: m("f.is_admin"), type: "check" },
            remove,
          ]
        : [
            user,
            {
              key: "node_type",
              label: m("f.node_type"),
              type: "select",
              options: options(m, "node", ["IRON", "POLY"]),
              width: "w-36",
            },
            floors,
            remove,
          ];
    const blank =
      key === "target"
        ? {
            floor_ids: [],
            user_id: "",
            target_right: "W",
            product_right: "W",
            is_admin: false,
          }
        : { user_id: "", node_type: "IRON", floor_ids: [] };
    return {
      tabs: [
        recordsTab(te, rows.length),
        { key: "options", label: m(`${key}.tab`), icon: "ListChecks" },
      ],
      body: () =>
        tab === "options" ? (
          <AccessGrid
            title={m(`${key}.tab`)}
            hint={m(`${key}.hint`)}
            footerKey={`master.${key}.footer`}
            empty={{
              title: m(`${key}.empty_title`),
              text: m("grid.empty_text"),
            }}
            columns={columns}
            blank={blank}
            rows={rows}
            save={actions.saveAccess.bind(null, kind)}
            canManage={canManage}
            exportName={key === "target" ? screen : undefined}
          />
        ) : (
          <RecordsGrid
            exportName={screen}
            rowKey="user_key"
            rows={rows.map((r) => ({
              ...r,
              user_key: `${r.user_id}|${r.node_type ?? ""}`,
              user: userName(r.user_id),
              floors: namesOf(look.floors, r.floor_ids),
              target_label: r.target_right && m(`opt.right.${r.target_right}`),
              product_label:
                r.product_right && m(`opt.right.${r.product_right}`),
              node_label: r.node_type && m(`opt.node.${r.node_type}`),
            }))}
            columns={
              key === "target"
                ? [
                    { key: "user", label: m("f.user") },
                    { key: "floors", label: m("f.floor_list") },
                    { key: "target_label", label: m("f.target_right") },
                    { key: "product_label", label: m("f.product_right") },
                    { key: "is_admin", label: m("f.is_admin"), type: "yesno" },
                  ]
                : [
                    { key: "user", label: m("f.user") },
                    { key: "node_label", label: m("f.node_type") },
                    { key: "floors", label: m("f.floor_list") },
                  ]
            }
          />
        ),
    };
  };
}

async function learningCurve({ m, te, tab, canManage }) {
  const rows = await apiFetch("/erp/ws-learning-points");
  const fields = [
    {
      name: "fabric_class",
      label: m("f.fabric_class"),
      type: "select",
      required: true,
      options: options(m, "fabric", FABRIC_CLASSES),
    },
    {
      name: "prod_type",
      label: m("f.prod_type"),
      type: "select",
      required: true,
      options: options(m, "prod", PROD_TYPES),
    },
    {
      name: "day",
      label: m("f.day"),
      type: "number",
      required: true,
      help: m("curve.day_hint"),
    },
    {
      name: "efficiency",
      label: m("f.efficiency"),
      type: "number",
      required: true,
      suffix: "%",
      step: "0.01",
    },
  ];
  return {
    tabs: [
      recordsTab(te, rows.length),
      { key: "curve", label: m("curve.tab"), icon: "ListChecks" },
      ...(canManage
        ? [{ key: "new", label: m("curve.new"), icon: "Plus" }]
        : []),
    ],
    body: () => {
      if (tab === "new" && canManage) {
        return (
          <EntryForm
            fields={fields}
            description={m("curve.new_hint")}
            action={actions.createResource.bind(null, "ws-learning-points", [])}
            title={m("curve.new")}
            submitLabel={m("curve.save")}
            columns={3}
            successHref="/work-study/learning-curve-model?tab=records"
          />
        );
      }
      if (tab === "curve") {
        return (
          <CurveEditor
            classes={options(m, "fabric", FABRIC_CLASSES).map((o) => ({
              key: o.value,
              label: o.label,
            }))}
            bands={options(m, "prod", PROD_TYPES).map((o) => ({
              key: o.value,
              label: o.label,
            }))}
            points={rows}
            save={actions.saveCurve}
            canManage={canManage}
          />
        );
      }
      return (
        <RecordsGrid
          exportName="learning-curve-model"
          rows={rows.map((r) => ({
            ...r,
            day: String(r.day),
            efficiency: String(Number(r.efficiency)),
            fabric_label: m(`opt.fabric.${r.fabric_class}`),
            prod_label: m(`opt.prod_short.${r.prod_type}`),
          }))}
          columns={[
            { key: "fabric_label", label: m("f.fabric_class") },
            { key: "prod_label", label: m("f.prod_type") },
            { key: "day", label: m("f.day"), type: "number", align: "right" },
            {
              key: "efficiency",
              label: m("f.efficiency"),
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
                    "ws-learning-points",
                    [],
                  ),
                  title: m("curve.edit"),
                  submitLabel: m("save"),
                  columns: 2,
                }
              : undefined
          }
          remove={
            canManage
              ? actions.deleteResource.bind(null, "ws-learning-points")
              : undefined
          }
        />
      );
    },
  };
}

async function responsibleOwners({ m, te, tab, canManage }) {
  const [depts, persons, look] = await Promise.all([
    apiFetch("/workstudy/departments"),
    apiFetch("/workstudy/persons"),
    lookups(),
  ]);
  return {
    tabs: [
      recordsTab(te, persons.length),
      { key: "mapping", label: m("owner.tab"), icon: "ListChecks" },
    ],
    body: () =>
      tab === "mapping" ? (
        <SideGrid
          listTitle={m("owner.list_title")}
          items={depts.map((d) => ({
            key: d.id,
            label: d.name,
            count: d.count,
          }))}
          listAction={
            <DialogFormButton
              label={m("owner.new_dept")}
              title={m("owner.new_dept")}
              fields={[
                { name: "name", label: m("f.department"), required: true },
                { name: "code", label: m("f.code") },
                { name: "sl", label: m("f.sl"), type: "number" },
              ]}
              action={actions.createDepartment}
              submitLabel={m("save")}
            />
          }
          title={m("owner.title")}
          hint={m("owner.hint")}
          emptyTitle={m("owner.empty_title")}
          empty={m("owner.empty")}
          gridEmpty={{
            title: m("owner.grid_empty_title"),
            text: m("grid.empty_text"),
          }}
          footerKey="master.owner.footer"
          columns={[
            { key: "_sl", label: m("f.sl"), type: "sl" },
            {
              key: "floor_ids",
              label: m("f.floors"),
              type: "multi",
              options: look.floors,
              placeholder: m("grid.select_floor"),
              disabledBy: "all_floors",
              width: "w-52",
            },
            { key: "all_floors", label: m("f.all_floors"), type: "check" },
            {
              key: "buyer_ids",
              label: m("f.buyers"),
              type: "multi",
              options: look.buyers,
              placeholder: m("grid.select_buyer"),
              disabledBy: "all_buyers",
              width: "w-52",
            },
            { key: "all_buyers", label: m("f.all_buyers"), type: "check" },
            {
              key: "_employee",
              label: m("f.selected_employee"),
              type: "lookup",
              from: "user_id",
              options: look.users,
            },
            {
              key: "user_id",
              label: m("f.search_employee"),
              type: "select",
              options: look.users,
              width: "w-52",
            },
            { key: "_remove", label: m("grid.action"), type: "action" },
          ]}
          blank={{
            floor_ids: [],
            all_floors: false,
            buyer_ids: [],
            all_buyers: false,
            user_id: "",
          }}
          load={actions.loadPersons}
          save={actions.savePersons}
          canManage={canManage}
          exportName="responsible-owner-mapping"
        />
      ) : (
        <RecordsGrid
          exportName="responsible-owner-mapping"
          rows={persons.map((p) => ({
            ...p,
            floors: p.all_floors
              ? m("all_floors")
              : namesOf(look.floors, p.floor_ids),
            buyers: p.all_buyers
              ? m("all_buyers")
              : namesOf(look.buyers, p.buyer_ids),
          }))}
          columns={[
            { key: "dept", label: m("f.responsible_dept") },
            { key: "user", label: m("f.responsible_person") },
            { key: "floors", label: m("f.floors") },
            { key: "buyers", label: m("f.buyers") },
            { key: "all_floors", label: m("f.all_floors"), type: "yesno" },
            { key: "all_buyers", label: m("f.all_buyers"), type: "yesno" },
          ]}
        />
      ),
  };
}

async function capabilityProfile({ m, te, tab, canManage }) {
  const [rows, bands, look] = await Promise.all([
    apiFetch("/erp/ws-line-profiles"),
    apiFetch("/workstudy/criticality"),
    lookups(),
  ]);
  const fields = [
    {
      name: "planning_line_id",
      label: m("f.line"),
      type: "select",
      required: true,
      options: look.lines,
    },
    {
      name: "machine_type",
      label: m("f.machine_type"),
      type: "select",
      options: [
        { value: "", label: "—" },
        ...options(m, "machine", MACHINE_TYPES),
      ],
    },
    {
      name: "operators",
      label: m("f.operators"),
      type: "number",
      required: true,
    },
    { name: "aso", label: m("f.aso"), type: "number", required: true },
    {
      name: "machines",
      label: m("f.machines"),
      type: "number",
      required: true,
    },
  ];
  return {
    tabs: [
      recordsTab(te, rows.length),
      {
        key: "criticality",
        label: m("profile.criticality_tab"),
        icon: "ListChecks",
      },
      ...(canManage
        ? [{ key: "new", label: m("profile.new"), icon: "Plus" }]
        : []),
    ],
    body: () => {
      if (tab === "new" && canManage) {
        return (
          <EntryForm
            fields={fields}
            description={m("profile.machine_hint")}
            action={actions.createResource.bind(null, "ws-line-profiles", [])}
            title={m("profile.new")}
            submitLabel={m("profile.save")}
            columns={3}
            successHref="/work-study/resource-capability-profile?tab=records"
          />
        );
      }
      if (tab === "criticality") {
        return (
          <SideGrid
            listTitle={m("profile.bands")}
            items={bands.map((b) => ({
              key: b.key,
              label: m(`opt.prod.${b.key}`),
              count: b.count,
            }))}
            title={m("profile.lines_title")}
            hint={m("profile.lines_hint")}
            emptyTitle={m("profile.empty_title")}
            empty={m("profile.empty")}
            gridEmpty={{
              title: m("profile.no_lines_title"),
              text: m("profile.no_lines"),
            }}
            footerKey="master.profile.footer"
            columns={[
              { key: "unit_floor", label: m("f.unit_floor"), type: "text" },
              { key: "line", label: m("f.line"), type: "text", mono: true },
              { key: "runs", label: m("f.runs_band"), type: "check" },
              {
                key: "preferred_group",
                label: m("f.preferred_group"),
                type: "select",
                options: [
                  { value: "", label: m("grid.select_category") },
                  ...look.categories.map((g) => ({ value: g, label: g })),
                ],
                placeholder: m("grid.select_category"),
                width: "w-64",
              },
            ]}
            load={actions.loadBand}
            save={actions.saveBand}
            canManage={canManage}
            exportName="line-list"
          />
        );
      }
      return (
        <RecordsGrid
          exportName="resource-capability-profile"
          rows={rows.map((r) => ({
            ...r,
            machine_type: r.machine_type ?? "",
            machine_label: r.machine_type
              ? m(`opt.machine.${r.machine_type}`)
              : m("profile.overall"),
            operators: String(r.operators),
            aso: String(r.aso),
            machines: String(r.machines),
          }))}
          columns={[
            { key: "line", label: m("f.line"), type: "mono" },
            { key: "machine_label", label: m("f.machine_type") },
            {
              key: "operators",
              label: m("f.operators"),
              type: "number",
              align: "right",
            },
            { key: "aso", label: m("f.aso"), type: "number", align: "right" },
            {
              key: "machines",
              label: m("f.machines"),
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
                    "ws-line-profiles",
                    [],
                  ),
                  title: m("profile.edit"),
                  submitLabel: m("save"),
                  columns: 3,
                }
              : undefined
          }
        />
      );
    },
  };
}

const SCREENS = {
  "item-and-process-group": itemParts,
  "floor-wise-target-setup": accessScreen({
    kind: "target-access",
    key: "target",
    screen: "floor-wise-target-setup",
  }),
  "finishing-floor-access-setup": accessScreen({
    kind: "finishing-access",
    key: "finishing",
    screen: "finishing-floor-access-setup",
  }),
  "learning-curve-model": learningCurve,
  "responsible-owner-mapping": responsibleOwners,
  "resource-capability-profile": capabilityProfile,
};

/** The tab each screen opens on, as Brickland opens it. */
const DEFAULT_TABS = {
  "floor-wise-target-setup": "options",
  "finishing-floor-access-setup": "options",
  "learning-curve-model": "curve",
  "responsible-owner-mapping": "mapping",
  "resource-capability-profile": "criticality",
};

export { SCREENS, DEFAULT_TABS };
