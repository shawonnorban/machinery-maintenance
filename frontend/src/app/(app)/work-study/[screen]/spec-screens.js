import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import { SideGrid } from "@/components/workstudy/side-grid";
import {
  SpecPlanner,
  SpecGrid,
  ItemList,
} from "@/components/workstudy/spec-entry";
import * as actions from "./actions";

/*
 * Work Study → Item Specification (AnnotechERP 14/04): Garment Spec Entry —
 * Records, the Garments Item Specification planner and the New form — and
 * Garment Item Specification — Records (the tech variations), the GMT Item
 * List, the tech variations of a category and the categories of a group (each
 * a list with its grid beside it), and the New tech variation form.
 */

const specOptions = () => apiFetch("/workstudy/spec-options");
const opt = (m, group, values) =>
  values.map((v) => ({ value: v, label: m(`opt.${group}.${v}`) }));
const n = (v) => (v === null || v === undefined ? null : String(Number(v)));

async function specEntry({ m, s, te, tab, canManage, searchParams = {} }) {
  const [opts, specs] = await Promise.all([
    specOptions(),
    apiFetch("/workstudy/specs"),
  ]);
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: specs.length,
    },
    { key: "planner", label: s("tabs.planner"), icon: "FileText" },
    ...(canManage
      ? [{ key: "new", label: s("tabs.new_spec"), icon: "Plus" }]
      : []),
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "planner") {
        const style =
          typeof searchParams.style === "string" ? searchParams.style : "";
        const v = typeof searchParams.v === "string" ? searchParams.v : "";
        const initial =
          style && v
            ? await apiFetch(
                `/workstudy/specs/one?${new URLSearchParams({ style, v })}`,
              ).catch(() => null)
            : null;
        return (
          <SpecPlanner
            key={`${style}${v}`}
            initial={initial}
            specs={specs}
            options={opts}
            act={actions.specAction}
            canManage={canManage}
          />
        );
      }
      if (tab === "new" && canManage) {
        const select = (name, label, choices, extra = {}) => ({
          name,
          label,
          type: "select",
          options: [{ value: "", label: s("f.select") }, ...choices],
          ...extra,
        });
        return (
          <EntryForm
            fields={[
              { name: "style_no", label: s("f.style"), required: true },
              {
                name: "variation_no",
                label: s("f.variation"),
                type: "number",
                required: true,
                default: "1",
              },
              {
                name: "description",
                label: s("f.description"),
                required: true,
              },
              select(
                "fabric_class",
                s("f.fabric"),
                opt(m, "fabric", opts.fabric_classes),
              ),
              select(
                "criticality",
                s("f.nature"),
                opt(m, "prod", opts.natures),
                { help: s("f.nature_help") },
              ),
              {
                name: "op_manpower",
                label: s("f.op"),
                type: "number",
                required: true,
                default: "0",
              },
              {
                name: "aso_manpower",
                label: s("f.aso"),
                type: "number",
                required: true,
                default: "0",
              },
              {
                name: "sewing_smv",
                label: s("f.sewing_smv"),
                type: "number",
                required: true,
                default: "0",
              },
              { name: "iron_smv", label: s("f.iron_smv_new"), type: "number" },
              { name: "poly_smv", label: s("f.poly_smv_new"), type: "number" },
              { name: "avg_efficiency", label: s("f.avg_eff"), type: "number" },
              {
                name: "pick_efficiency",
                label: s("f.pick_eff"),
                type: "number",
              },
              {
                name: "balancing_efficiency",
                label: s("f.bal_eff_new"),
                type: "number",
              },
              {
                name: "ob_file_path",
                label: s("f.ob_file"),
                type: "filepath",
                upload: "/api/work-study/ob-file",
                accept: ".pdf,.xlsx,.xls,image/*",
                placeholder: s("f.ob_placeholder"),
                help: s("f.ob_help"),
                span: 2,
              },
              {
                name: "is_smv_artwork",
                label: s("f.smv_artwork"),
                type: "checkbox",
              },
              { name: "is_fusing", label: s("f.fusing"), type: "checkbox" },
            ]}
            action={actions.createSpec}
            title={s("tabs.new_spec")}
            submitLabel={s("f.save_new")}
            columns={3}
            successHref="/work-study/gmt-item-specification-entry?tab=records"
          />
        );
      }
      return <SpecGrid rows={specs} />;
    },
  };
}

const BANDS = ["band1", "band2", "band3", "band4", "band5", "band6"];

/** Itm Group & Category: the groups listed with their categories beside them (14/04; Spec-Wise CM Rate shows it too). */
function itemGroupsGrid({ s, m, opts, canManage }) {
  return (
    <SideGrid
      listTitle={s("grp.groups")}
      items={opts.groups.map((g) => ({
        key: g.key,
        label: g.label,
        count: g.count,
      }))}
      listAction={
        canManage ? (
          <DialogFormButton
            label={s("grp.new_group")}
            title={s("grp.new_group")}
            fields={[
              { name: "name", label: s("grp.name"), required: true },
              {
                name: "sort_order",
                label: s("grp.sort"),
                type: "number",
                default: "0",
              },
            ]}
            action={actions.createResource.bind(null, "ws-item-groups", [])}
            submitLabel={s("grp.new_group")}
          />
        ) : null
      }
      title={s("grp.title")}
      hint={s("grp.hint")}
      emptyTitle={s("grp.empty_title")}
      empty={s("grp.empty")}
      gridEmpty={{
        title: s("grp.grid_empty_title"),
        text: m("grid.empty_text"),
      }}
      footerKey="spec.grp.footer"
      columns={[
        { key: "_sl", label: s("grp.sl"), type: "sl" },
        { key: "name", label: s("grp.category"), type: "input", width: "w-56" },
        { key: "code", label: s("grp.code"), type: "text" },
        { key: "active", label: s("grp.active"), type: "check" },
        { key: "_remove", label: m("grid.action"), type: "action" },
      ]}
      blank={{ name: "", code: "", active: true }}
      load={actions.loadCategories}
      save={actions.saveCategories}
      canManage={canManage}
      exportName="item-categories"
    />
  );
}

async function itemSpec({ m, s, te, tab, canManage }) {
  const [opts, variations] = await Promise.all([
    specOptions(),
    apiFetch("/erp/ws-tech-variations"),
  ]);
  const tabs = [
    {
      key: "records",
      label: te("records"),
      icon: "Table2",
      count: variations.length,
    },
    { key: "list", label: s("tabs.list"), icon: "ListChecks" },
    { key: "variations", label: s("tabs.variations"), icon: "ListTree" },
    { key: "groups", label: s("tabs.groups"), icon: "ListTree" },
    ...(canManage
      ? [{ key: "new", label: s("tabs.new_variation"), icon: "Plus" }]
      : []),
  ];
  const fields = [
    {
      name: "item_group",
      label: s("var.group"),
      type: "select",
      required: true,
      options: [
        { value: "", label: s("f.select") },
        ...opts.groups.map((g) => ({ value: g.label, label: g.label })),
      ],
    },
    {
      name: "garment_item_id",
      label: s("var.category"),
      type: "select",
      required: true,
      options: [
        { value: "", label: s("f.select") },
        ...opts.categories
          .filter((c) => c.active)
          .map((c) => ({ value: c.value, label: c.label })),
      ],
    },
    { name: "tech_spec", label: s("var.tech_spec"), required: true, span: 3 },
    { name: "op", label: s("var.op"), type: "number" },
    { name: "aso", label: s("var.aso"), type: "number" },
    { name: "smv", label: s("var.smv"), type: "number" },
    ...BANDS.map((b) => ({ name: b, label: opts.bands[b], type: "number" })),
  ];
  return {
    tabs,
    body: async () => {
      if (tab === "new" && canManage)
        return (
          <EntryForm
            fields={fields}
            action={actions.createResource.bind(null, "ws-tech-variations", [])}
            title={s("tabs.new_variation")}
            submitLabel={s("var.save")}
            columns={3}
            successHref="/work-study/gmt-item-specification?tab=records"
          />
        );
      if (tab === "list") {
        const list = await apiFetch("/workstudy/item-list");
        return (
          <ItemList
            initial={list}
            company={opts.company}
            act={actions.specAction}
          />
        );
      }
      if (tab === "variations") {
        const counts = variations.reduce(
          (c, v) => ({
            ...c,
            [v.category_code]: (c[v.category_code] ?? 0) + 1,
          }),
          {},
        );
        return (
          <SideGrid
            listTitle={s("var.categories")}
            items={opts.categories.map((c) => ({
              key: c.code,
              label: c.label.split(" — ").slice(1).join(" — ") || c.label,
              count: counts[c.code] ?? 0,
            }))}
            title={s("var.title")}
            hint={s("var.hint")}
            emptyTitle={s("var.empty_title")}
            empty={s("var.empty")}
            gridEmpty={{
              title: s("var.grid_empty_title"),
              text: m("grid.empty_text"),
            }}
            footerKey="spec.var.footer"
            columns={[
              { key: "_sl", label: s("grp.sl"), type: "sl" },
              {
                key: "tech_spec",
                label: s("var.tech_spec"),
                type: "input",
                width: "w-56",
              },
              {
                key: "op",
                label: s("var.op"),
                type: "number",
                width: "w-20 text-right",
              },
              {
                key: "aso",
                label: s("var.aso"),
                type: "number",
                width: "w-20 text-right",
              },
              {
                key: "smv",
                label: s("var.smv"),
                type: "number",
                width: "w-20 text-right",
              },
              ...BANDS.map((b) => ({
                key: b,
                label: opts.bands[b],
                type: "number",
                width: "w-24 text-right",
              })),
              { key: "_remove", label: m("grid.action"), type: "action" },
            ]}
            blank={{
              tech_spec: "",
              op: "",
              aso: "",
              smv: "",
              band1: "",
              band2: "",
              band3: "",
              band4: "",
              band5: "",
              band6: "",
            }}
            load={actions.loadVariations}
            save={actions.saveVariations}
            canManage={canManage}
            exportName="tech-variations"
          />
        );
      }
      if (tab === "groups") return itemGroupsGrid({ s, m, opts, canManage });
      return (
        <div className="flex flex-col gap-2">
          <p className="px-1 text-sm font-semibold text-foreground">
            {s("var.records_title")}
          </p>
          <RecordsGrid
            exportName="tech-variations"
            rows={variations.map((v) => ({
              ...v,
              ...Object.fromEntries(
                ["op", "aso", "smv", ...BANDS].map((k) => [k, n(v[k])]),
              ),
            }))}
            columns={[
              { key: "item_group", label: s("var.group") },
              { key: "category", label: s("var.category") },
              { key: "tech_spec", label: s("var.tech_spec") },
              { key: "op", label: s("var.op"), type: "number", align: "right" },
              {
                key: "aso",
                label: s("var.aso"),
                type: "number",
                align: "right",
              },
              {
                key: "smv",
                label: s("var.smv"),
                type: "number",
                align: "right",
              },
              ...BANDS.map((b) => ({
                key: b,
                label: opts.bands[b],
                type: "number",
                align: "right",
              })),
            ]}
            edit={
              canManage
                ? {
                    fields,
                    action: actions.updateResource.bind(
                      null,
                      "ws-tech-variations",
                      [],
                    ),
                    title: te("edit"),
                    submitLabel: s("var.save"),
                    columns: 3,
                    refKey: "tech_spec",
                  }
                : undefined
            }
            remove={
              canManage
                ? actions.deleteResource.bind(null, "ws-tech-variations")
                : undefined
            }
          />
        </div>
      );
    },
  };
}

const SPEC_SCREENS = {
  "gmt-item-specification-entry": specEntry,
  "gmt-item-specification": itemSpec,
};
const SPEC_DEFAULT_TABS = { "gmt-item-specification": "list" };

export { SPEC_SCREENS, SPEC_DEFAULT_TABS, itemGroupsGrid };
