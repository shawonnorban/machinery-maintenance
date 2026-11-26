import { demoScreens, choices, labels, average, total, distinct } from "@/lib/demo-screen";
import { apiFetch } from "@/lib/api-server";
import { getLocale } from "next-intl/server";
import { getNavigation, screensOf } from "@/lib/navigation";
import { CommandDeck } from "@/components/commandcenter/command-deck";
import * as actions from "./actions";

/*
 * Command Center (AnnotechERP 27), Brickland's "Demo data" registers on the
 * shared demo-screen layout. The staff and departments come from the
 * company's own masters.
 */

const screen = demoScreens({ actions, prefix: "commandcenter", optionsPath: "/command-center/options" });

const REFRESH = ["LIVE", "HOURLY", "DAILY", "WEEKLY"];
const AUDIENCES = ["BOARD", "DEPT_HEADS", "ALL_MANAGERS", "EVERYONE"];
const DOMAINS = ["COMMERCIAL", "PRODUCTION", "QUALITY", "PEOPLE", "FINANCE"];
const LEVEL_1 = ["DIVISION", "BUYER", "SEASON", "DEPARTMENT", "COST_CENTRE"];
const LEVEL_2 = ["STYLE", "LINE", "ORDER", "MACHINE", "ACCOUNT"];
const LEVEL_3 = ["SHIFT", "OPERATOR", "COLOUR", "SIZE", "DAY"];
const SOURCES = ["MERCHANDISING", "KNIT", "ASSEMBLY", "CUTTING", "ACCOUNTING", "HR"];
const GRAINS = ["HOURLY", "DAILY", "WEEKLY", "MONTHLY"];
const LAYOUTS = ["TWO", "THREE", "SINGLE", "GRID", "FULL"];
const SHARED = ["BOARD", "DIVISION_HEADS", "ALL_MANAGERS", "EVERYONE"];
const COMPOSITION_STATES = ["DRAFT", "RELEASED", "ARCHIVED"];
const COMPOSITION_TONES = { DRAFT: "warning", RELEASED: "success", ARCHIVED: "muted" };
/** A board's data health, coloured as Brickland does: 90 and over good, 75 and over fair, below that poor. */
const healthBand = (v) => (Number(v) >= 90 ? "success" : Number(v) >= 75 ? "warning" : "danger");

/* ── Command Center (27/01) ───────────────────────────────────────── */

const boards = screen({
  resource: "cc-boards",
  area: "command",
  key: "b",
  icon: "board",
  booleans: ["published"],
  nameKey: "name",
  check: "published",
  // Brickland's row button: Publish a draft, Unpublish a published board.
  rowAction: ({ m }) => ({ label: m("b.publish"), labels: { true: m("b.unpublish"), false: m("b.publish") }, labelField: "published", action: actions.togglePublish }),
  decorate: (r, { m }) => ({
    ...r,
    sub: [r.department, r.owner].filter(Boolean).join(" · ") || null,
    refresh_label: m(`refresh.${r.refresh}`),
    audience_label: m(`audience.${r.audience}`),
    health_tone: healthBand(r.data_health),
    health_label: `${Math.round(Number(r.data_health))}%`,
    state: r.published ? "PUBLISHED" : "DRAFT",
  }),
  fields: ({ m, opts }) => [
    { name: "name", label: m("b.f.name"), required: true, placeholder: "Finishing Floor Live", span: 2 },
    { name: "department_id", label: m("b.f.department"), type: "select", options: opts.departments },
    { name: "owner_id", label: m("b.f.owner"), type: "select", options: opts.staff },
    { name: "tiles", label: m("b.f.tiles"), type: "number", default: 0 },
    { name: "refresh", label: m("b.f.refresh"), type: "select", options: choices(m, "refresh", REFRESH), default: "DAILY" },
    { name: "audience", label: m("b.f.audience"), type: "select", options: choices(m, "audience", AUDIENCES), default: "DEPT_HEADS" },
    { name: "data_health", label: m("b.f.health"), type: "number", step: "0.1", suffix: "%", default: 100 },
    { name: "published", label: m("b.f.published"), type: "checkbox", help: m("b.f.published_hint"), default: false },
  ],
  columns: ({ m }) => [
    { key: "name", label: m("b.c.board"), sub: "sub" },
    { key: "tiles", label: m("b.c.tiles"), type: "number" },
    { key: "refresh_label", label: m("b.c.refresh") },
    { key: "audience_label", label: m("b.c.audience") },
    { key: "health_label", label: m("b.c.health"), type: "badge", toneKey: "health_tone", tones: { success: "success", warning: "warning", danger: "danger" } },
    { key: "state", label: m("b.c.state"), type: "badge", labels: { PUBLISHED: m("b.published"), DRAFT: m("b.draft") }, tones: { PUBLISHED: "info", DRAFT: "muted" } },
  ],
  tiles: (rows, { m }) => [
    { label: m("b.t.boards"), value: rows.length },
    { label: m("b.t.published"), value: rows.filter((r) => r.published).length },
    { label: m("b.t.tiles"), value: total(rows, "tiles") },
    { label: m("b.t.health"), value: average(rows, "data_health") },
  ],
});

const drillPaths = screen({
  resource: "cc-drill-paths",
  area: "command",
  key: "d",
  icon: "check",
  nameKey: "metric",
  pick: ({ m }) => ({ key: "domain", options: choices(m, "domain", DOMAINS) }),
  decorate: (r, { m }) => ({
    ...r,
    domain_label: r.domain ? m(`domain.${r.domain}`) : null,
    l1: r.level_1 ? m(`lvl.${r.level_1}`) : null,
    l2: r.level_2 ? m(`lvl.${r.level_2}`) : null,
    l3: r.level_3 ? m(`lvl.${r.level_3}`) : null,
    source_label: r.source ? m(`source.${r.source}`) : null,
  }),
  fields: ({ m }) => [
    { name: "metric", label: m("d.f.metric"), required: true, placeholder: "Shipment value" },
    { name: "domain", label: m("d.f.domain"), type: "select", options: choices(m, "domain", DOMAINS) },
    { name: "level_1", label: m("d.f.level_1"), type: "select", options: choices(m, "lvl", LEVEL_1) },
    { name: "level_2", label: m("d.f.level_2"), type: "select", options: choices(m, "lvl", LEVEL_2) },
    { name: "level_3", label: m("d.f.level_3"), type: "select", options: choices(m, "lvl", LEVEL_3) },
    { name: "source", label: m("d.f.source"), type: "select", options: choices(m, "source", SOURCES) },
    { name: "grain", label: m("d.f.grain"), type: "select", options: choices(m, "grain", GRAINS) },
    { name: "rows_k", label: m("d.f.rows"), type: "number", suffix: "k", default: 0, help: m("d.f.rows_hint") },
  ],
  columns: ({ m }) => [
    { key: "metric", label: m("d.c.metric"), sub: "source_label" },
    { key: "domain_label", label: m("d.c.domain") },
    { key: "l1", label: m("d.c.level_1") },
    { key: "l2", label: m("d.c.level_2") },
    { key: "l3", label: m("d.c.level_3") },
    { key: "rows_k", label: m("d.c.rows"), type: "number", grouping: true, suffix: " k" },
  ],
  tiles: (rows, { m }) => [
    { label: m("d.t.paths"), value: rows.length },
    { label: m("d.t.domains"), value: distinct(rows, "domain") },
    { label: m("d.t.hourly"), value: rows.filter((r) => r.grain === "HOURLY").length },
    { label: m("d.t.rows"), value: total(rows, "rows_k").toLocaleString("en-US") },
  ],
});

const compositions = screen({
  resource: "cc-compositions",
  area: "command",
  key: "c",
  icon: "compose",
  nameKey: "title",
  pick: ({ m }) => ({ key: "layout", options: choices(m, "layout", LAYOUTS) }),
  decorate: (r, { m }) => ({ ...r, layout_label: r.layout ? m(`layout.${r.layout}`) : null, shared_label: m(`shared.${r.shared_with}`) }),
  fields: ({ m, opts }) => [
    { name: "title", label: m("c.f.title"), required: true, placeholder: "Winter 26 readiness", span: 2 },
    { name: "layout", label: m("c.f.layout"), type: "select", options: choices(m, "layout", LAYOUTS) },
    { name: "author_id", label: m("c.f.author"), type: "select", options: opts.staff },
    { name: "tiles", label: m("c.f.tiles"), type: "number", default: 0 },
    { name: "charts", label: m("c.f.charts"), type: "number", default: 0 },
    { name: "shared_with", label: m("c.f.shared"), type: "select", options: choices(m, "shared", SHARED), default: "BOARD" },
    { name: "state", label: m("c.f.state"), type: "select", options: choices(m, "cstate", COMPOSITION_STATES), default: "DRAFT" },
    { name: "notes", label: m("c.f.notes"), type: "textarea", placeholder: m("c.f.notes_ph") },
  ],
  columns: ({ m }) => [
    { key: "title", label: m("c.c.composition"), sub: "author" },
    { key: "layout_label", label: m("c.c.layout") },
    { key: "tiles", label: m("c.c.tiles"), type: "number" },
    { key: "charts", label: m("c.c.charts"), type: "number" },
    { key: "shared_label", label: m("c.c.shared") },
    { key: "state", label: m("c.c.state"), type: "badge", labels: labels(m, "cstate", COMPOSITION_STATES), tones: COMPOSITION_TONES },
  ],
  tiles: (rows, { m }) => [
    { label: m("c.t.compositions"), value: rows.length },
    { label: m("c.t.released"), value: rows.filter((r) => r.state === "RELEASED").length },
    { label: m("c.t.tiles"), value: total(rows, "tiles") },
    { label: m("c.t.authors"), value: distinct(rows, "author_id") },
  ],
});

/**
 * Chief Executive Command Deck: the factory-wide figures from the modules'
 * own records (`/command-center/deck`) and the modules the user may open,
 * each linking to its first screen.
 */
async function commandDeck({ m }) {
  const [deck, navigation, locale] = await Promise.all([apiFetch("/command-center/deck"), getNavigation(), getLocale()]);
  // The menu tree carries each module's name in both languages, as the sidebar shows it.
  const modules = navigation.map((mod) => ({ code: mod.code, label: (locale === "bn" && mod.name_bn) || mod.name, icon: mod.icon, url: screensOf(mod)[0]?.url })).filter((mod) => mod.url);
  const w = {
    no_data: m("deck.no_data"),
    empty_title: m("deck.empty_title"),
    empty_text: m("deck.empty_text"),
    show_table: m("deck.show_table"),
    show_chart: m("deck.show_chart"),
    other: m("deck.other"),
    modules: m("deck.modules"),
    unconverted: m("deck.unconverted"),
    ago_m: m("deck.ago_m"),
    ago_h: m("deck.ago_h"),
    ago_d: m("deck.ago_d"),
    kpi: Object.fromEntries(["revenue", "order_book", "efficiency", "on_time", "vs_last", "invoices", "orders", "target", "shipped"].map((x) => [x, m(`deck.kpi.${x}`)])),
    trend: Object.fromEntries(["title", "sub", "revenue", "cost", "shipment", "month"].map((x) => [x, m(`deck.trend.${x}`)])),
    buyers: Object.fromEntries(["title", "sub", "buyer", "value"].map((x) => [x, m(`deck.buyers.${x}`)])),
    output: Object.fromEntries(["title", "sub", "floor", "plan", "actual"].map((x) => [x, m(`deck.output.${x}`)])),
    lines: Object.fromEntries(["title", "sub", "line", "achieved", "target"].map((x) => [x, m(`deck.lines.${x}`)])),
    activity: Object.fromEntries(["title", "sub", "system"].map((x) => [x, m(`deck.activity.${x}`)])),
    actions: Object.fromEntries(["CREATED", "UPDATED", "DELETED", "STATUS_CHANGED", "COST_CHANGED"].map((x) => [x, m(`deck.actions.${x}`)])),
    alerts: { title: m("deck.alerts.title"), sub: m("deck.alerts.sub"), approval: m("deck.alerts.approval"), severity: Object.fromEntries(["HIGH", "MEDIUM", "LOW"].map((x) => [x, m(`deck.alerts.severity.${x}`)])) },
  };
  return <CommandDeck deck={deck} modules={modules} w={w} />;
}

const SCREENS = {
  "command-deck": commandDeck,
  "department-dashboards": boards,
  "drill-down-explorer": drillPaths,
  "dashboard-builder": compositions,
};

export { SCREENS };
