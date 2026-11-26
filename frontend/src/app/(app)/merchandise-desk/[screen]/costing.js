import { apiFetch } from "@/lib/api-server";
import { PickPanel } from "@/components/erp/pick-panel";
import { PendingFiberTemplates } from "@/components/merchandise/pending-fiber-templates";
import { PendingEmbellishmentRates } from "@/components/merchandise/pending-embellishment-rates";
import { CadSizes } from "@/components/merchandise/cad-sizes";
import { BudgetSheet } from "@/components/merchandise/budget-sheet";
import { BudgetActuals } from "@/components/merchandise/budget-actuals";
import { BudgetWorkflow } from "@/components/merchandise/budget-workflow";
import { RecordsGrid } from "@/components/erp/records-grid";
import { findByRef, refsFor, slug } from "@/lib/row-ref";
import { masterScreen } from "./screens";
import * as actions from "./actions";

/*
 * Merchandise Desk → Costing & Budget (AnnotechERP 06/09): Fabric Process
 * Rate — the knitting, dyeing and finishing rate chart and the rates still
 * waiting for their fibre combination template.
 */

const CATEGORIES = ["KNITTING", "YARN_DYEING", "AOP", "DYEING", "DYEING_FINISHING"];
const TEMPLATES = ["COTTON_100", "LYCRA_BLEND", "CVC_BLEND"];

const labelled = (m, group, values) => values.map((v) => ({ value: v, label: m(`${group}.${v}`) }));
const pickOne = (m, list) => [{ value: "", label: m("select") }, ...list];
const distinct = (rows, key) => [...new Set(rows.map((r) => r[key]).filter(Boolean))].sort().map((v) => ({ value: v, label: v }));

const fabProcessRate = masterScreen({
  resource: "merch-fab-process-rates",
  screen: "fab-process-rate",
  noun: "process_rate",
  nameKey: "description",
  refKey: "rate_no",
  newLabel: "fpr.new",
  description: "fpr.new_hint",
  formColumns: 3,
  booleans: ["is_slub", "active"],
  load: async () => ({ look: await apiFetch("/merchandise/lookups") }),
  fields: ({ m, look }) => [
    { name: "category", label: m("fpr.f.category"), type: "select", required: true, options: pickOne(m, labelled(m, "fpr.cat", CATEGORIES)) },
    { name: "fiber_template", label: m("fpr.f.template"), type: "select", options: pickOne(m, labelled(m, "opt.template", TEMPLATES)) },
    { name: "count_range", label: m("fpr.f.count_range"), placeholder: m("fpr.ph.count") },
    { name: "fabric_type_id", label: m("fpr.f.fabric_type"), type: "select", options: pickOne(m, look.fabric_types) },
    { name: "colour_group_id", label: m("fpr.f.colour_type"), type: "select", options: pickOne(m, look.colour_groups) },
    { name: "is_slub", label: m("fpr.f.slub"), type: "checkbox" },
    { name: "description", label: m("fpr.f.description"), type: "textarea", required: true, placeholder: m("fpr.ph.description"), span: 3 },
    { name: "rate", label: m("fpr.f.rate"), type: "number", required: true, step: "0.0001" },
    { name: "currency", label: m("fpr.f.currency"), default: "BDT", placeholder: "BDT" },
    { name: "per", label: m("fpr.f.per"), default: "Kg", placeholder: "Kg" },
    { name: "prod_rate", label: m("fpr.f.prod_rate"), type: "number", required: true, step: "0.0001" },
    { name: "process_loss", label: m("fpr.f.loss"), type: "number", required: true, step: "0.01", suffix: "%" },
    { name: "active", label: m("fpr.f.active"), type: "checkbox", default: true },
  ],
  decorate: (row, { m }) => ({
    ...row,
    rate: String(row.rate),
    prod_rate: String(row.prod_rate),
    process_loss: String(row.process_loss),
    fiber_template: row.fiber_template ?? "",
    fabric_type_id: row.fabric_type_id ?? "",
    colour_group_id: row.colour_group_id ?? "",
    category_label: m(`fpr.cat.${row.category}`),
    template_label: row.fiber_template ? m(`opt.template.${row.fiber_template}`) : null,
    rate_label: m("fpr.per_unit", { rate: row.rate, ccy: row.currency, per: row.per ?? "" }),
    prod_label: m("fpr.per_unit", { rate: row.prod_rate, ccy: row.currency, per: row.per ?? "" }),
    loss_label: `${row.process_loss} %`,
    slub_label: row.is_slub ? "Y" : "N",
    active_label: row.active ? "Y" : "N",
  }),
  columns: ({ m }) => [
    { key: "rate_no", label: m("fpr.f.id"), type: "number" },
    { key: "category_label", label: m("fpr.col.category") },
    { key: "fabric_type", label: m("fpr.col.fab_type") },
    { key: "count_range", label: m("fpr.col.cnt") },
    { key: "template_label", label: m("fpr.col.template") },
    { key: "description", label: m("fpr.col.desc") },
    { key: "rate_label", label: m("fpr.col.rate"), align: "right" },
    { key: "prod_label", label: m("fpr.col.prod_rate"), align: "right" },
    { key: "loss_label", label: m("fpr.col.apo"), align: "right" },
    { key: "slub_label", label: m("fpr.col.s") },
    { key: "active_label", label: m("fpr.col.a") },
    { key: "active", label: m("fpr.f.status"), type: "status" },
  ],
  filters: ({ m, rows }) => [
    { name: "category", field: "category", label: m("fpr.f.category"), options: labelled(m, "fpr.cat", CATEGORIES) },
    { name: "fab_type", field: "fabric_type", label: m("fpr.col.fab_type"), options: distinct(rows, "fabric_type") },
    { name: "count", field: "count_range", label: m("fpr.f.count_range"), options: distinct(rows, "count_range") },
    { name: "template", field: "fiber_template", label: m("fpr.col.template"), options: labelled(m, "opt.template", TEMPLATES) },
    { name: "active", field: "active", label: m("fpr.f.active"), options: [{ value: "true", label: m("fpr.yn.Y") }, { value: "false", label: m("fpr.yn.N") }] },
  ],
  extraTabs: ({ m, rows, canManage, searchParams }) => [
    {
      key: "pending",
      label: m("fpr.tab_pending"),
      icon: "ListChecks",
      render: async () => {
        // A category is named in the url by its code; its rates are those still without a template.
        const active = CATEGORIES.includes(searchParams.category) ? searchParams.category : null;
        const waiting = (c) => rows.filter((r) => r.category === c && !r.fiber_template);
        return (
          <PickPanel
            title={m("fpr.categories")}
            items={CATEGORIES.map((c) => ({ key: c, label: m(`fpr.cat.${c}`), count: waiting(c).length }))}
            active={active}
            hrefBase="/merchandise-desk/fab-process-rate?tab=pending&category="
            searchPlaceholder={m("fpr.search")}
            noMatch={m("fpr.no_match")}
            panelTitle={active ? `${m("fpr.pending_rates")} — ${m(`fpr.cat.${active}`)}` : m("fpr.pending_rates")}
            panelHint={m("fpr.pending_hint")}
            emptyTitle={m("fpr.pick")}
            emptyText={m("fpr.pick_hint")}
          >
            {active ? <PendingFiberTemplates key={active} rows={waiting(active)} templates={labelled(m, "opt.template", TEMPLATES)} assign={actions.assignFiberTemplate} canManage={canManage} /> : null}
          </PickPanel>
        );
      },
    },
  ],
});

const EMB_TYPES = ["PRINT", "EMBROIDERY"];

const embellishmentRate = masterScreen({
  resource: "merch-embellishment-rates",
  screen: "print-embr-rate-operation",
  noun: "embellishment_rate",
  nameKey: "type_label",
  refKey: "description",
  newLabel: "emb.new",
  formColumns: 3,
  booleans: ["confirmed"],
  load: async () => {
    const [orderLook, look, orders] = await Promise.all([apiFetch("/merchandise/orders/lookups"), apiFetch("/merchandise/lookups"), apiFetch("/erp/merch-orders")]);
    return { orderLook, look, orders };
  },
  fields: ({ m, orderLook, look, orders }) => [
    { name: "type", label: m("emb.f.type"), type: "select", required: true, options: pickOne(m, labelled(m, "emb.type", EMB_TYPES)) },
    { name: "style_id", label: m("emb.f.style"), type: "select", options: pickOne(m, orderLook.styles.map(({ value, label }) => ({ value, label }))) },
    { name: "order_id", label: m("emb.f.order"), type: "select", options: pickOne(m, orders.map((o) => ({ value: o.id, label: `${o.order_no} · ${o.style_no} (${o.work_style_no || o.style_no})` }))) },
    { name: "buyer_id", label: m("emb.f.buyer"), type: "select", options: pickOne(m, orderLook.buyers) },
    { name: "supplier_id", label: m("emb.f.supplier"), type: "select", options: pickOne(m, look.suppliers) },
    { name: "description", label: m("emb.f.description"), type: "textarea", required: true, span: 3 },
    { name: "rate", label: m("emb.f.rate"), type: "number", required: true, step: "0.0001" },
    { name: "currency", label: m("emb.f.currency"), default: "BDT", placeholder: "BDT" },
    { name: "per", label: m("emb.f.per"), default: "Pcs", placeholder: "Pcs" },
    { name: "confirmed", label: m("emb.f.confirmed"), type: "checkbox", help: m("emb.confirmed_help") },
  ],
  decorate: (row, { m }) => ({
    ...row,
    rate: String(row.rate),
    style_id: row.style_id ?? "",
    order_id: row.order_id ?? "",
    buyer_id: row.buyer_id ?? "",
    supplier_id: row.supplier_id ?? "",
    type_label: m(`emb.type.${row.type}`),
    rate_label: m("emb.per_unit", { rate: row.rate, ccy: row.currency, per: row.per ?? "" }),
    confirmed_label: row.confirmed ? m("emb.yes") : m("emb.no"),
    state: row.confirmed ? "CONFIRMED" : "PENDING",
  }),
  columns: ({ m }) => [
    { key: "type_label", label: m("emb.f.type") },
    { key: "buyer", label: m("emb.f.buyer") },
    { key: "order_style", label: m("emb.f.order_style") },
    { key: "description", label: m("emb.f.description") },
    { key: "rate_label", label: m("emb.f.rate"), align: "right" },
    { key: "supplier", label: m("emb.f.supplier") },
    { key: "confirmed_label", label: m("emb.f.confirmed") },
    { key: "state", label: m("emb.f.status"), type: "badge", labels: { PENDING: m("emb.pending"), CONFIRMED: m("emb.confirmed") } },
  ],
  filters: ({ m, rows }) => [
    { name: "type", field: "type", label: m("emb.f.type"), options: labelled(m, "emb.type", EMB_TYPES) },
    { name: "buyer", field: "buyer", label: m("emb.f.buyer"), options: distinct(rows, "buyer") },
    { name: "supplier", field: "supplier", label: m("emb.f.supplier"), options: distinct(rows, "supplier") },
    { name: "state", field: "state", label: m("emb.f.state"), options: [{ value: "CONFIRMED", label: m("emb.confirmed") }, { value: "PENDING", label: m("emb.pending") }] },
  ],
  extraTabs: ({ m, rows, canManage, searchParams }) => [
    {
      key: "pendings",
      label: m("emb.tab_pendings"),
      icon: "ListChecks",
      render: async () => {
        // A buyer is named in the url by its short name; only rates with a buyer are listed by buyer.
        const waiting = rows.filter((r) => !r.confirmed && r.buyer);
        const buyers = [...new Set(waiting.map((r) => r.buyer))].sort();
        const active = buyers.includes(searchParams.buyer) ? searchParams.buyer : null;
        return (
          <PickPanel
            title={m("emb.buyers")}
            items={buyers.map((b) => ({ key: b, label: b, sub: b, count: waiting.filter((r) => r.buyer === b).length }))}
            active={active}
            hrefBase="/merchandise-desk/print-embr-rate-operation?tab=pendings&buyer="
            searchPlaceholder={m("emb.search")}
            noMatch={m("emb.no_buyer")}
            panelTitle={active ? m("emb.unconfirmed_of", { buyer: active }) : m("emb.unconfirmed")}
            panelHint={m("emb.pending_hint")}
            emptyTitle={m("emb.pick")}
            emptyText={m("emb.pick_hint")}
          >
            {active ? <PendingEmbellishmentRates key={active} buyer={active} rows={waiting.filter((r) => r.buyer === active)} confirm={actions.confirmEmbellishmentRate} drop={actions.dropEmbellishmentRate} canManage={canManage} /> : null}
          </PickPanel>
        );
      },
    },
  ],
});

const CAD_CATEGORIES = ["BULK", "SAMPLE", "DEVELOPMENT"];
const CAD_STATUSES = ["DRAFT", "SUBMITTED", "IN_PROGRESS", "SENT", "APPROVED", "REJECTED", "CANCELLED"];

const cadConsumption = masterScreen({
  resource: "merch-cad-records",
  screen: "cad-consumption-list",
  noun: "cad_record",
  nameKey: "buyer_ac",
  refKey: "job_no",
  newLabel: "cad.new",
  formColumns: 3,
  booleans: [],
  load: async () => {
    const [orderLook, look, orders] = await Promise.all([apiFetch("/merchandise/orders/lookups"), apiFetch("/merchandise/lookups"), apiFetch("/erp/merch-orders")]);
    return { orderLook, look, orders };
  },
  fields: ({ m, orderLook, look, orders }) => [
    { name: "buyer_office_id", label: m("cad.f.buyer_ac"), type: "select", options: pickOne(m, look.offices) },
    { name: "style_id", label: m("cad.f.style"), type: "select", options: pickOne(m, orderLook.styles.map(({ value, label }) => ({ value, label }))) },
    { name: "order_id", label: m("cad.f.order"), type: "select", options: pickOne(m, orders.map((o) => ({ value: o.id, label: `${o.order_no} · ${o.style_no} (${o.work_style_no || o.style_no})` }))) },
    { name: "category", label: m("cad.f.category"), type: "select", options: pickOne(m, labelled(m, "cad.category", CAD_CATEGORIES)) },
    { name: "job_no", label: m("cad.f.job"), required: true },
    { name: "record_date", label: m("cad.f.date"), type: "date", required: true },
    { name: "status", label: m("cad.f.status"), type: "select", default: "DRAFT", options: pickOne(m, labelled(m, "cad.status", CAD_STATUSES)) },
  ],
  decorate: (row, { m }) => ({
    ...row,
    buyer_office_id: row.buyer_office_id ?? "",
    style_id: row.style_id ?? "",
    order_id: row.order_id ?? "",
    category: row.category ?? "",
    category_label: row.category ? m(`cad.category.${row.category}`) : null,
    avg_label: row.avg_consumption === null ? null : Number(row.avg_consumption).toFixed(4),
  }),
  columns: ({ m }) => [
    { key: "buyer_ac", label: m("cad.f.buyer_ac") },
    { key: "category_label", label: m("cad.f.category") },
    { key: "job_no", label: m("cad.f.job") },
    { key: "record_date", label: m("cad.f.date"), type: "date" },
    { key: "sizes", label: m("cad.f.sizes"), type: "number" },
    { key: "avg_label", label: m("cad.f.avg"), align: "right" },
    { key: "status", label: m("cad.f.status"), type: "badge", labels: Object.fromEntries(CAD_STATUSES.map((s) => [s, m(`cad.status.${s}`)])) },
  ],
  filters: ({ m, rows }) => [
    { name: "buyer_ac", field: "buyer_ac", label: m("cad.f.buyer_ac"), options: distinct(rows, "buyer_ac") },
    { name: "category", field: "category", label: m("cad.f.category"), options: labelled(m, "cad.category", CAD_CATEGORIES) },
    { name: "from", label: m("cad.f.date_from"), type: "date", match: (r, v) => r.record_date >= v },
    { name: "to", label: m("cad.f.date_to"), type: "date", match: (r, v) => r.record_date <= v },
    { name: "status", field: "status", label: m("cad.f.status"), options: labelled(m, "cad.status", CAD_STATUSES) },
  ],
  extraTabs: ({ m, rows, canManage, searchParams }) => [
    {
      key: "consumption",
      label: m("cad.tab_consumption"),
      icon: "ListChecks",
      render: async () => {
        // A record is named in the url by its job / work style.
        const refs = refsFor(rows, "job_no");
        const picked = findByRef(rows, "job_no", searchParams.record);
        const data = picked ? await apiFetch(`/merchandise/cad-records/${picked.id}/sizes`) : null;
        return (
          <PickPanel
            title={m("cad.records")}
            items={rows.map((r) => ({ key: refs.get(r.id), label: r.job_no, sub: r.category_label ?? "", count: r.sizes }))}
            active={picked ? refs.get(picked.id) : null}
            hrefBase="/merchandise-desk/cad-consumption-list?tab=consumption&record="
            searchPlaceholder={m("cad.search")}
            noMatch={m("cad.no_match")}
            panelTitle={picked ? m("cad.consumption_of", { job: picked.job_no }) : m("cad.consumption")}
            panelHint={m("cad.hint")}
            emptyTitle={m("cad.pick")}
            emptyText={m("cad.pick_hint")}
          >
            {data ? <CadSizes key={picked.id} recordId={picked.id} data={data} save={actions.saveCadSizes} canManage={canManage} /> : null}
          </PickPanel>
        );
      },
    },
  ],
});

/**
 * Order Budget Sheet: one sheet, no tabs. `?order=<order no>` opens that
 * order's budget; otherwise the style and order are picked on the sheet.
 */
async function budgetSheet({ canManage, searchParams = {} }) {
  const options = await apiFetch("/merchandise/budgets/options");
  // The order is named by its number, or by the readable reference a grid row link makes of it.
  const picked = searchParams.order ? options.orders.find((o) => o.order_no === searchParams.order || slug(o.order_no) === searchParams.order) : null;
  const initial = picked ? await apiFetch(`/merchandise/orders/${picked.value}/budget`).catch(() => null) : null;
  return {
    tabs: [],
    body: () => <BudgetSheet options={options} initial={initial} load={actions.loadBudget} loadSources={actions.loadBudgetSources} loadItem={actions.loadBudgetItem} save={actions.saveBudget} canManage={canManage} listHref="/merchandise-desk/budget-vs-post-prod-costing" />,
  };
}

const ACTUAL_GROUPS = ["yarn_trims", "fabric_process", "cmt", "export"];

/**
 * Budget vs Actual Costing: the budget sheets (Records, each opening its
 * sheet) and four expense tabs where what production actually spent is
 * recorded against a budget (the budget named in the url by its Ref#).
 */
async function budgetVsActual({ m, te, tab, canManage, searchParams = {} }) {
  const rows = await apiFetch("/merchandise/budgets");
  const group = ACTUAL_GROUPS.includes(tab) ? tab : null;
  return {
    tabs: [{ key: "records", label: te("records"), icon: "Table2", count: rows.length }, ...ACTUAL_GROUPS.map((g) => ({ key: g, label: m(`bva.tab.${g}`), icon: "ListChecks" }))],
    body: async () => {
      if (group) {
        const picked = rows.find((r) => r.budget_no === searchParams.budget) ?? null;
        const data = picked ? await apiFetch(`/merchandise/budgets/${picked.id}/actuals/${group}`) : null;
        return (
          <PickPanel
            title={m("bva.budgets")}
            items={rows.map((r) => ({ key: r.budget_no, label: r.budget_no, sub: `${r.order_no} · ${r.style_no}` }))}
            active={picked?.budget_no ?? null}
            hrefBase={`/merchandise-desk/budget-vs-post-prod-costing?tab=${group}&budget=`}
            searchPlaceholder={m("bva.search")}
            noMatch={m("bva.no_match")}
            panelTitle={picked ? `${m("bva.cost_heads")} — ${picked.budget_no}` : m("bva.cost_heads")}
            panelHint={m("bva.hint", { what: m(`bva.what.${group}`) })}
            emptyTitle={m("bva.pick")}
            emptyText={m("bva.pick_hint")}
          >
            {data ? <BudgetActuals key={`${picked.id}-${group}`} budgetId={picked.id} group={group} data={data} save={actions.saveBudgetActuals} canManage={canManage} /> : null}
          </PickPanel>
        );
      }
      return (
        <RecordsGrid
          exportName="budget-vs-actual"
          rows={rows}
          header={{ title: m("screens.budget-vs-post-prod-costing"), subtitle: m("bva.caption"), searchLabel: m("bva.search"), searchPlaceholder: m("bva.search"), exportLabel: m("sod.export") }}
          columns={[
            { key: "budget_no", label: m("bva.col.budget_no"), type: "mono" },
            { key: "buyer_ac", label: m("bva.col.buyer_ac") },
            { key: "style_no", label: m("bva.col.style") },
            { key: "order_no", label: m("bva.col.order") },
            { key: "order_qty", label: m("bva.col.qty"), type: "number" },
            { key: "unit_price", label: m("bva.col.price"), type: "number" },
            { key: "order_value", label: m("bva.col.value"), type: "number" },
          ]}
          rowLink={{ label: m("bva.open"), href: "/merchandise-desk/budget-sheet?order=", refKey: "order_no" }}
          emptyText={m("bva.empty")}
        />
      );
    },
  };
}

/**
 * Budget Sheet Workflow: one view, no tabs — every budget sheet on its
 * approval route, filtered in place; an approver approves (locks) or unlocks.
 */
async function budgetWorkflow({ can, locale }) {
  const rows = await apiFetch("/merchandise/budget-workflow");
  return {
    tabs: [],
    body: () => <BudgetWorkflow rows={rows} decide={actions.decideBudget} canApprove={can("merchandise.costing.approve")} locale={locale} />,
  };
}

const COSTING_SCREENS = {
  "fab-process-rate": fabProcessRate,
  "print-embr-rate-operation": embellishmentRate,
  "cad-consumption-list": cadConsumption,
  "budget-sheet": budgetSheet,
  "budget-vs-post-prod-costing": budgetVsActual,
  "budget-sheet-workflow": budgetWorkflow,
};

/** Each Costing & Budget screen is changed under the costing permission. */
const COSTING_MANAGE = {
  "fab-process-rate": "merchandise.costing.manage",
  "print-embr-rate-operation": "merchandise.costing.manage",
  "cad-consumption-list": "merchandise.costing.manage",
  "budget-sheet": "merchandise.costing.manage",
  "budget-vs-post-prod-costing": "merchandise.costing.manage",
  "budget-sheet-workflow": "merchandise.costing.approve",
};

export { COSTING_SCREENS, COSTING_MANAGE };
