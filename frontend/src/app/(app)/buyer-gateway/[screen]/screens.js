import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import { Tiles } from "@/components/buyergateway/tiles";
import * as actions from "./actions";

/*
 * Buyer Gateway → Access & Onboarding (AnnotechERP 25/01), as Brickland lays
 * each screen out: four tiles, then one card — the title, the New button, a
 * search and a filter, the sortable list with its switch column, Edit and
 * Delete on every row, and the pager. The lists are the screen kit's ERP
 * resources; the buyers, countries and staff come from the company's masters.
 */

const ROLES = ["MERCHANDISER", "QUALITY_MANAGER", "SOURCING_HEAD", "LOGISTICS", "FINANCE", "READ_ONLY"];
const USER_STATES = ["ACTIVE", "INVITED", "SUSPENDED", "LOCKED"];
const ACCESS = ["FULL", "READ_ONLY", "HIDDEN"];
const DOCUMENTS = ["DOWNLOAD", "VIEW_ONLY", "HIDDEN"];
const STEPS = ["CONTRACT_SIGNED", "ACCOUNT_CREATED", "USERS_INVITED", "PERMISSIONS_SET", "BRANDING_APPLIED", "VISIBILITY_AGREED", "TRAINING_DONE", "LIVE"];
const ONBOARDING_STATES = ["ON_TRACK", "AT_RISK", "BLOCKED", "COMPLETE"];
const EVENTS = ["SAMPLE_APPROVED", "SAMPLE_COMMENTED", "SHIPMENT_BOOKED", "SHIPMENT_DELIVERED", "DOCUMENT_SHARED", "INVOICE_PUBLISHED", "ANNOUNCEMENT_POSTED", "REQUEST_ANSWERED"];
const CHANNELS = ["EMAIL", "IN_PORTAL", "BOTH", "MUTED"];
const DIGESTS = ["IMMEDIATE", "DAILY", "WEEKLY"];
const RECIPIENTS = ["ALL", "MERCHANDISERS", "NAMED", "OWNER"];

const SCOPES = ["ALL", "CONFIRMED", "SEASON", "NAMED"];
const DEPTHS = ["STATUS", "MILESTONES", "QUANTITIES", "FULL"];
const DELAYS = ["ALWAYS", "REVIEWED", "NEVER"];
const SAMPLE_TYPES = ["PROTO", "FIT", "SIZE_SET", "PP", "SHIPMENT", "PHOTO"];
const SAMPLE_STAGES = ["IN_DEVELOPMENT", "SENT", "COMMENTED", "REVISED", "APPROVED", "REJECTED"];
const COURIERS = ["DHL", "FEDEX", "ARAMEX", "HAND", "NOT_SENT"];
const MODES = ["SEA", "AIR", "SEA_AIR", "ROAD"];
const SHIPMENT_STAGES = ["BOOKED", "GATE_OUT", "ON_THE_WATER", "ARRIVED", "DELIVERED"];
const DOCUMENT_KINDS = ["TECH_PACK", "TEST_REPORT", "INSPECTION", "PACKING_LIST", "INVOICE", "CERTIFICATE"];
const DOCUMENT_STATES = ["SHARED", "WITHDRAWN", "EXPIRED"];
const SAMPLE_TONES = { IN_DEVELOPMENT: "muted", SENT: "info", COMMENTED: "warning", REVISED: "muted", APPROVED: "success", REJECTED: "danger" };
const SHIPMENT_TONES = { BOOKED: "muted", GATE_OUT: "warning", ON_THE_WATER: "info", ARRIVED: "info", DELIVERED: "success" };
const DOCUMENT_TONES = { SHARED: "success", WITHDRAWN: "warning", EXPIRED: "muted" };
const RFQ_STATES = ["NEW", "UNDER_COSTING", "QUOTED", "DECLINED", "CONVERTED"];
const INCOTERMS = ["FOB", "CIF", "DDP", "EXW"];
const INVOICE_STATES = ["PUBLISHED", "PAID", "OVERDUE", "UNPUBLISHED"];
const PAYMENT_TERMS = ["LC_SIGHT", "LC_60", "TT_30", "TT_60", "OPEN_90"];
const AGEING = ["NOT_DUE", "D30", "D60", "D90", "OVER_90"];
const RFQ_TONES = { NEW: "warning", UNDER_COSTING: "info", QUOTED: "info", DECLINED: "muted", CONVERTED: "success" };
const INVOICE_TONES = { PUBLISHED: "info", PAID: "success", OVERDUE: "danger", UNPUBLISHED: "muted" };
const AGEING_TONES = { NOT_DUE: "success", D30: "info", D60: "warning", D90: "danger", OVER_90: "danger" };
const REQUEST_KINDS = ["ORDER_CHANGE", "SAMPLE", "DOCUMENT", "PRICE", "SHIPMENT", "OTHER"];
const DESKS = ["MERCHANDISING", "SAMPLE_ROOM", "COMMERCIAL", "PLANNING", "QUALITY", "UNROUTED"];
const PRIORITIES = ["URGENT", "NORMAL", "LOW"];
const REQUEST_STATES = ["NEW", "IN_PROGRESS", "ANSWERED", "CLOSED"];
const CAUSES = ["SHADE", "MEASUREMENT", "STITCHING", "PACKING", "SHORT_SHIPMENT", "LATE_DELIVERY", "NOT_ESTABLISHED"];
const COMPLAINT_STATES = ["OPEN", "INVESTIGATING", "OFFER_MADE", "SETTLED", "REJECTED"];
const AUDIENCES = ["ALL", "STRATEGIC", "ONE", "LOGISTICS"];
const NOTICE_KINDS = ["HOLIDAY", "CAPACITY", "POLICY", "SYSTEM", "GENERAL"];
const NOTICE_STATES = ["LIVE", "SCHEDULED", "EXPIRED", "DRAFT"];
const SURVEY_STATES = ["OPEN", "CLOSED", "DRAFT"];
const TICKET_PRIORITIES = ["URGENT", "HIGH", "NORMAL", "LOW"];
const TICKET_STATES = ["NEW", "IN_PROGRESS", "WAITING", "RESOLVED", "CLOSED"];
const PAGES = ["ORDERS", "SHIPMENTS", "SAMPLES", "DOCUMENTS", "INVOICES"];
const THEMES = ["LIGHT", "DARK", "SYSTEM", "HIGH_CONTRAST"];
const ACCENT_SLOTS = [1, 2, 3, 4, 5, 6, 7, 8];
const ACTIONS = ["SIGNED_IN", "DOWNLOADED", "APPROVED", "COMMENTED", "RAISED_REQUEST", "SIGNED_OUT"];
const DEVICES = ["DESKTOP", "MOBILE", "TABLET"];
const ticketOpen = (r) => !["RESOLVED", "CLOSED"].includes(r.state);
const PRIORITY_TONES = { URGENT: "danger", NORMAL: "info", LOW: "muted" };
const REQUEST_TONES = { NEW: "warning", IN_PROGRESS: "info", ANSWERED: "muted", CLOSED: "success" };
const COMPLAINT_TONES = { OPEN: "warning", INVESTIGATING: "info", OFFER_MADE: "muted", SETTLED: "success", REJECTED: "danger" };
const NOTICE_TONES = { LIVE: "success", SCHEDULED: "info", EXPIRED: "muted", DRAFT: "warning" };
const SURVEY_TONES = { OPEN: "info", CLOSED: "success", DRAFT: "warning" };
const TICKET_PRIORITY_TONES = { URGENT: "danger", HIGH: "danger", NORMAL: "info", LOW: "muted" };
const TICKET_TONES = { NEW: "danger", IN_PROGRESS: "info", WAITING: "warning", RESOLVED: "muted", CLOSED: "success" };
const USER_TONES = { ACTIVE: "success", INVITED: "info", SUSPENDED: "warning", LOCKED: "danger" };
const ONBOARDING_TONES = { ON_TRACK: "info", AT_RISK: "warning", BLOCKED: "danger", COMPLETE: "success" };

const choices = (m, group, values) => values.map((v) => ({ value: v, label: m(`${group}.${v}`) }));
const labels = (m, group, values) => Object.fromEntries(values.map((v) => [v, m(`${group}.${v}`)]));
/** A step's share of the eight, rounded half to even as Brickland shows it (12.5 → 12, 37.5 → 38). */
const progressOf = (step) => {
  const x = ((STEPS.indexOf(step) + 1) * 100) / STEPS.length;
  const f = Math.floor(x);
  return x - f === 0.5 ? (f % 2 === 0 ? f : f + 1) : Math.round(x);
};

function portalScreen(config) {
  return async function render({ m, permissions }) {
    // Each group of screens has its own manage right: access (25/01), visibility (25/02).
    const canManage = permissions.includes(`buyergateway.${config.area ?? "access"}.manage`);
    const [rows, opts] = await Promise.all([apiFetch(`/erp/${config.resource}`), apiFetch("/buyer-gateway/options")]);
    const ctx = { m, opts };
    const shown = rows.map((r) => (config.decorate ? config.decorate(r, ctx) : r));
    ctx.rows = shown;
    const fields = config.fields(ctx);
    const k = config.key;
    const toggle = config.toggle
      ? [{ key: config.toggle, label: m(`${k}.c.${config.toggle}`), type: "toggle", bare: true, sortable: false, action: canManage ? actions.setFlag.bind(null, config.resource, config.toggle) : undefined }]
      : [];

    return (
      <div className="flex flex-col gap-4">
        <Tiles icon={config.icon} items={config.tiles(shown, ctx)} />
        <RecordsGrid
          exportName={config.resource}
          rows={shown}
          header={{
            title: m(`${k}.title`),
            subtitle: m(`${k}.subtitle`),
            searchLeft: true,
            searchPlaceholder: m(`${k}.search`),
            pick: { ...config.pick(ctx), label: m(`${k}.pick_label`), all: m(`${k}.pick_all`) },
            action: canManage ? (
              <DialogFormButton variant="primary" label={m(`${k}.new`)} title={m(`${k}.new_title`)} description={m(`${k}.dialog_sub`)} fields={fields} action={actions.createResource.bind(null, config.resource, config.booleans ?? [])} submitLabel={m(`${k}.create`)} />
            ) : null,
          }}
          emptyState={{ title: m(`${k}.empty_title`), text: m(`${k}.empty_text`) }}
          columns={[...config.columns(ctx), ...toggle]}
          edit={
            canManage
              ? {
                  fields: config.editFields ? config.editFields(ctx) : fields,
                  action: actions.updateResource.bind(null, config.resource, config.booleans ?? []),
                  title: m(`${k}.edit_title`),
                  description: m(`${k}.dialog_sub`),
                  submitLabel: m("save_changes"),
                }
              : undefined
          }
          remove={canManage ? actions.deleteResource.bind(null, config.resource) : undefined}
          deleteLabels={{ title: m(`${k}.delete_title`), confirm: m("delete"), aria: m(`${k}.delete_aria`), nameKey: config.nameKey, text: m(`${k}.delete_text`) }}
        />
      </div>
    );
  };
}

function userFields({ m, opts }, edit) {
  return [
    { name: "name", label: m("u.f.name"), required: true, placeholder: "Anna Lindqvist" },
    { name: "email", label: m("u.f.email"), type: "email", required: true, placeholder: "anna.l@buyer.com" },
    { name: "buyer_id", label: m("u.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "role", label: m("u.f.role"), type: "select", options: choices(m, "role", ROLES) },
    { name: "country_id", label: m("u.f.country"), type: "select", options: opts.countries },
    { name: "last_seen", label: m("u.f.last_seen"), type: "date" },
    { name: "state", label: m("u.f.state"), type: "select", options: choices(m, "state", USER_STATES), default: "INVITED" },
    ...(edit ? [{ name: "login_enabled", label: m("u.f.login"), type: "checkbox" }] : []),
  ];
}

const buyerPick = ({ opts }) => ({ key: "buyer_id", options: opts.buyers });

const portalUsers = portalScreen({
  resource: "bg-portal-users",
  key: "u",
  icon: "users",
  booleans: ["login_enabled"],
  toggle: "login_enabled",
  nameKey: "name",
  pick: buyerPick,
  decorate: (r, { m }) => ({ ...r, role_label: r.role ? m(`role.${r.role}`) : null }),
  fields: (ctx) => userFields(ctx, false),
  // Edit adds the Login switch.
  editFields: (ctx) => userFields(ctx, true),
  columns: ({ m }) => [
    { key: "name", label: m("u.c.user"), sub: "email" },
    { key: "buyer", label: m("u.c.buyer") },
    { key: "role_label", label: m("u.c.role") },
    { key: "last_seen", label: m("u.c.last_seen"), type: "date" },
    { key: "state", label: m("u.c.state"), type: "badge", labels: labels(m, "state", USER_STATES), tones: USER_TONES },
  ],
  tiles: (rows, { m }) => [
    { label: m("u.t.users"), value: rows.length },
    { label: m("u.t.active"), value: rows.filter((r) => r.state === "ACTIVE").length },
    { label: m("u.t.suspended"), value: rows.filter((r) => r.state === "SUSPENDED").length },
    { label: m("u.t.buyers"), value: new Set(rows.map((r) => r.buyer_id)).size },
  ],
});
const permissionSets = portalScreen({
  resource: "bg-permission-sets",
  key: "p",
  icon: "key",
  booleans: ["may_approve"],
  toggle: "may_approve",
  nameKey: "role_label",
  pick: buyerPick,
  decorate: (r, { m }) => ({
    ...r,
    role_label: m(`role.${r.role}`),
    orders_label: m(`access.${r.orders}`),
    samples_label: m(`access.${r.samples}`),
    documents_label: m(`doc.${r.documents}`),
    commercial_label: m(`access.${r.commercial}`),
  }),
  fields: ({ m, opts }) => [
    { name: "role", label: m("p.f.role"), type: "select", required: true, options: choices(m, "role", ROLES) },
    { name: "buyer_id", label: m("p.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "orders", label: m("p.f.orders"), type: "select", options: choices(m, "access", ACCESS), default: "READ_ONLY" },
    { name: "samples", label: m("p.f.samples"), type: "select", options: choices(m, "access", ACCESS), default: "READ_ONLY" },
    { name: "documents", label: m("p.f.documents"), type: "select", options: choices(m, "doc", DOCUMENTS), default: "VIEW_ONLY" },
    { name: "commercial", label: m("p.f.commercial"), type: "select", options: choices(m, "access", ACCESS), default: "HIDDEN" },
    { name: "set_on", label: m("p.f.set_on"), type: "date" },
  ],
  columns: ({ m }) => [
    { key: "role_label", label: m("p.c.role"), sub: "buyer" },
    { key: "orders_label", label: m("p.c.orders") },
    { key: "samples_label", label: m("p.c.samples") },
    { key: "documents_label", label: m("p.c.documents") },
    { key: "commercial_label", label: m("p.c.commercial") },
  ],
  tiles: (rows, { m }) => [
    { label: m("p.t.sets"), value: rows.length },
    { label: m("p.t.approve"), value: rows.filter((r) => r.may_approve).length },
    { label: m("p.t.buyers"), value: new Set(rows.map((r) => r.buyer_id)).size },
    { label: m("p.t.roles"), value: new Set(rows.map((r) => r.role)).size },
  ],
});

const onboarding = portalScreen({
  resource: "bg-onboardings",
  key: "o",
  icon: "onboard",
  nameKey: "buyer_name",
  pick: ({ m }) => ({ key: "step", options: choices(m, "step", STEPS) }),
  decorate: (r, { m }) => ({ ...r, step_label: m(`step.${r.step}`) }),
  fields: ({ m, opts }) => [
    { name: "buyer_name", label: m("o.f.buyer"), required: true, placeholder: "Next Retail" },
    { name: "step", label: m("o.f.step"), type: "select", options: choices(m, "step", STEPS), default: "CONTRACT_SIGNED" },
    { name: "owner_id", label: m("o.f.owner"), type: "select", options: opts.staff },
    { name: "started_on", label: m("o.f.started"), type: "date" },
    { name: "target_live", label: m("o.f.target_live"), type: "date" },
    { name: "progress", label: m("o.f.progress"), type: "derived", from: "step", values: Object.fromEntries(STEPS.map((s) => [s, `${progressOf(s)} %`])) },
    { name: "users_invited", label: m("o.f.users"), type: "number", default: 0 },
    { name: "state", label: m("o.f.state"), type: "select", options: choices(m, "ostate", ONBOARDING_STATES), default: "ON_TRACK" },
  ],
  columns: ({ m }) => [
    { key: "buyer_name", label: m("o.c.buyer"), sub: "owner" },
    { key: "step_label", label: m("o.c.step") },
    { key: "progress", label: m("o.c.progress"), type: "progress" },
    { key: "users_invited", label: m("o.c.users"), type: "number" },
    { key: "target_live", label: m("o.c.target_live"), type: "date" },
    { key: "state", label: m("o.c.state"), type: "badge", labels: labels(m, "ostate", ONBOARDING_STATES), tones: ONBOARDING_TONES },
  ],
  tiles: (rows, { m }) => [
    { label: m("o.t.onboardings"), value: rows.length },
    { label: m("o.t.on_track"), value: rows.filter((r) => r.state === "ON_TRACK").length },
    { label: m("o.t.blocked"), value: rows.filter((r) => r.state === "BLOCKED").length },
    { label: m("o.t.average"), value: `${rows.length ? Math.round(rows.reduce((s, r) => s + Number(r.progress ?? 0), 0) / rows.length) : 0}%` },
  ],
});

const notifications = portalScreen({
  resource: "bg-notification-prefs",
  key: "n",
  icon: "sliders",
  booleans: ["enabled"],
  toggle: "enabled",
  nameKey: "event_label",
  pick: ({ m }) => ({ key: "channel", options: choices(m, "channel", CHANNELS) }),
  decorate: (r, { m }) => ({ ...r, event_label: m(`event.${r.event}`), channel_label: m(`channel.${r.channel}`), digest_label: m(`digest.${r.digest}`), recipients_label: m(`recipients.${r.recipients}`) }),
  fields: ({ m, opts }) => [
    { name: "event", label: m("n.f.event"), type: "select", required: true, options: choices(m, "event", EVENTS) },
    { name: "buyer_id", label: m("n.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "channel", label: m("n.f.channel"), type: "select", options: choices(m, "channel", CHANNELS), default: "EMAIL" },
    { name: "digest", label: m("n.f.digest"), type: "select", options: choices(m, "digest", DIGESTS), default: "IMMEDIATE" },
    { name: "recipients", label: m("n.f.recipients"), type: "select", options: choices(m, "recipients", RECIPIENTS), default: "ALL" },
    { name: "set_on", label: m("n.f.set_on"), type: "date" },
  ],
  columns: ({ m }) => [
    { key: "event_label", label: m("n.c.event"), sub: "buyer" },
    { key: "channel_label", label: m("n.c.channel") },
    { key: "digest_label", label: m("n.c.digest") },
    { key: "recipients_label", label: m("n.c.recipients") },
  ],
  tiles: (rows, { m }) => [
    { label: m("n.t.preferences"), value: rows.length },
    { label: m("n.t.enabled"), value: rows.filter((r) => r.enabled).length },
    { label: m("n.t.muted"), value: rows.filter((r) => r.channel === "MUTED").length },
    { label: m("n.t.buyers"), value: new Set(rows.map((r) => r.buyer_id)).size },
  ],
});

/* ── Order & Shipment Visibility (25/02) ──────────────────────────── */

const visibilityRules = portalScreen({
  resource: "bg-visibility-rules",
  area: "visibility",
  key: "v",
  icon: "eye",
  booleans: ["active"],
  toggle: "active",
  nameKey: "buyer",
  pick: ({ m }) => ({ key: "depth", options: choices(m, "depth", DEPTHS) }),
  decorate: (r, { m }) => ({ ...r, scope_label: m(`scope.${r.scope}`), depth_label: m(`depth.${r.depth}`), delays_label: m(`delays.${r.delays}`) }),
  fields: ({ m, opts }) => [
    { name: "buyer_id", label: m("v.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "scope", label: m("v.f.scope"), type: "select", options: choices(m, "scope", SCOPES), default: "CONFIRMED" },
    { name: "depth", label: m("v.f.depth"), type: "select", options: choices(m, "depth", DEPTHS), default: "STATUS" },
    { name: "delays", label: m("v.f.delays"), type: "select", options: choices(m, "delays", DELAYS), default: "REVIEWED" },
    { name: "effective_from", label: m("v.f.effective_from"), type: "date" },
    { name: "set_by", label: m("v.f.set_by"), type: "select", options: opts.staff },
    { name: "note", label: m("v.f.note"), type: "textarea", placeholder: m("v.f.note_ph") },
  ],
  columns: ({ m }) => [
    { key: "buyer", label: m("v.c.buyer"), sub: "note" },
    { key: "scope_label", label: m("v.c.scope") },
    { key: "depth_label", label: m("v.c.depth") },
    { key: "delays_label", label: m("v.c.delays") },
  ],
  tiles: (rows, { m }) => [
    { label: m("v.t.rules"), value: rows.length },
    { label: m("v.t.active"), value: rows.filter((r) => r.active).length },
    { label: m("v.t.full"), value: rows.filter((r) => r.depth === "FULL").length },
    { label: m("v.t.delays"), value: rows.filter((r) => r.delays === "ALWAYS").length },
  ],
});

const sampleProgress = portalScreen({
  resource: "bg-sample-progress",
  area: "visibility",
  key: "s",
  icon: "sample",
  nameKey: "style",
  pick: ({ m }) => ({ key: "stage", options: choices(m, "sstage", SAMPLE_STAGES) }),
  decorate: (r, { m }) => ({ ...r, type_label: r.sample_type ? m(`stype.${r.sample_type}`) : null, courier_label: r.courier ? m(`courier.${r.courier}`) : null }),
  fields: ({ m, opts }) => [
    { name: "style", label: m("s.f.style"), required: true, placeholder: "ST-4471" },
    { name: "buyer_id", label: m("s.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "sample_type", label: m("s.f.type"), type: "select", options: choices(m, "stype", SAMPLE_TYPES) },
    { name: "stage", label: m("s.f.stage"), type: "select", options: choices(m, "sstage", SAMPLE_STAGES), default: "IN_DEVELOPMENT" },
    { name: "sent_on", label: m("s.f.sent_on"), type: "date" },
    { name: "decision_due", label: m("s.f.decision_due"), type: "date" },
    { name: "version", label: m("s.f.version"), type: "number", default: 1 },
    { name: "courier", label: m("s.f.courier"), type: "select", options: choices(m, "courier", COURIERS) },
  ],
  columns: ({ m }) => [
    { key: "style", label: m("s.c.style"), type: "mono" },
    { key: "buyer", label: m("s.c.buyer"), sub: "type_label" },
    { key: "version", label: m("s.c.version"), type: "number" },
    { key: "sent_on", label: m("s.c.sent"), type: "date" },
    { key: "decision_due", label: m("s.c.decision_due"), type: "date" },
    { key: "courier_label", label: m("s.c.courier") },
    { key: "stage", label: m("s.c.stage"), type: "badge", labels: labels(m, "sstage", SAMPLE_STAGES), tones: SAMPLE_TONES },
  ],
  tiles: (rows, { m }) => [
    { label: m("s.t.samples"), value: rows.length },
    { label: m("s.t.awaiting"), value: rows.filter((r) => r.stage === "SENT").length },
    { label: m("s.t.approved"), value: rows.filter((r) => r.stage === "APPROVED").length },
    { label: m("s.t.rejected"), value: rows.filter((r) => r.stage === "REJECTED").length },
  ],
});

const shipmentProgress = portalScreen({
  resource: "bg-shipment-progress",
  area: "visibility",
  key: "h",
  icon: "truck",
  nameKey: "order_no",
  pick: ({ m }) => ({ key: "stage", options: choices(m, "hstage", SHIPMENT_STAGES) }),
  decorate: (r, { m }) => ({ ...r, mode_label: r.mode ? m(`mode.${r.mode}`) : null }),
  fields: ({ m, opts }) => [
    { name: "order_no", label: m("h.f.order"), required: true, placeholder: "SO-4471" },
    { name: "buyer_id", label: m("h.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "mode", label: m("h.f.mode"), type: "select", options: choices(m, "mode", MODES) },
    { name: "stage", label: m("h.f.stage"), type: "select", options: choices(m, "hstage", SHIPMENT_STAGES), default: "BOOKED" },
    { name: "agreed_date", label: m("h.f.agreed"), type: "date" },
    { name: "actual_date", label: m("h.f.actual"), type: "date" },
    { name: "quantity", label: m("h.f.quantity"), type: "number", default: 0 },
    { name: "cartons", label: m("h.f.cartons"), type: "number", default: 0 },
  ],
  columns: ({ m }) => [
    { key: "order_no", label: m("h.c.order"), type: "mono" },
    { key: "buyer", label: m("h.c.buyer"), sub: "mode_label" },
    { key: "quantity", label: m("h.c.quantity"), type: "number", grouping: true },
    { key: "cartons", label: m("h.c.cartons"), type: "number" },
    { key: "agreed_date", label: m("h.c.agreed"), type: "date" },
    { key: "actual_date", label: m("h.c.actual"), type: "date" },
    { key: "stage", label: m("h.c.stage"), type: "badge", labels: labels(m, "hstage", SHIPMENT_STAGES), tones: SHIPMENT_TONES },
  ],
  tiles: (rows, { m }) => [
    { label: m("h.t.shipments"), value: rows.length },
    { label: m("h.t.delivered"), value: rows.filter((r) => r.stage === "DELIVERED").length },
    { label: m("h.t.transit"), value: rows.filter((r) => r.stage === "ON_THE_WATER").length },
    { label: m("h.t.pieces"), value: rows.reduce((sum, r) => sum + Number(r.quantity ?? 0), 0).toLocaleString("en-US") },
  ],
});

const sharedDocuments = portalScreen({
  resource: "bg-shared-documents",
  area: "visibility",
  key: "d",
  icon: "files",
  nameKey: "title",
  pick: ({ m }) => ({ key: "kind", options: choices(m, "kind", DOCUMENT_KINDS) }),
  decorate: (r, { m }) => ({ ...r, kind_label: r.kind ? m(`kind.${r.kind}`) : null }),
  fields: ({ m, opts }) => [
    { name: "title", label: m("d.f.document"), required: true, placeholder: "ST-4471 tech pack rev 3" },
    { name: "buyer_id", label: m("d.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "kind", label: m("d.f.kind"), type: "select", options: choices(m, "kind", DOCUMENT_KINDS) },
    { name: "shared_on", label: m("d.f.shared_on"), type: "date" },
    { name: "shared_by", label: m("d.f.shared_by"), type: "select", options: opts.staff },
    { name: "size_mb", label: m("d.f.size"), type: "number", help: m("d.f.size_hint") },
    { name: "downloads", label: m("d.f.downloads"), type: "number", default: 0 },
    { name: "state", label: m("d.f.state"), type: "select", options: choices(m, "dstate", DOCUMENT_STATES), default: "SHARED" },
    { name: "file_path", label: m("d.f.file"), type: "filepath", upload: "/api/buyer-gateway/document-file", accept: ".pdf,.xlsx,.xls,image/*", attachLabel: m("d.f.attach"), placeholder: m("d.f.file_ph"), span: 3 },
  ],
  columns: ({ m }) => [
    { key: "title", label: m("d.c.document"), sub: "buyer" },
    { key: "kind_label", label: m("d.c.kind") },
    { key: "shared_on", label: m("d.c.shared"), type: "date" },
    { key: "size_mb", label: m("d.c.size"), type: "number", suffix: " MB" },
    { key: "downloads", label: m("d.c.downloads"), type: "number" },
    { key: "state", label: m("d.c.state"), type: "badge", labels: labels(m, "dstate", DOCUMENT_STATES), tones: DOCUMENT_TONES },
    { key: "file_path", label: m("d.c.file"), type: "download", text: m("d.c.download"), sortable: false },
  ],
  tiles: (rows, { m }) => [
    { label: m("d.t.documents"), value: rows.length },
    { label: m("d.t.shared"), value: rows.filter((r) => r.state === "SHARED").length },
    { label: m("d.t.downloads"), value: rows.reduce((sum, r) => sum + Number(r.downloads ?? 0), 0) },
    { label: m("d.t.withdrawn"), value: rows.filter((r) => r.state === "WITHDRAWN").length },
  ],
});

/* ── Pricing & Billing (25/03) ────────────────────────────────────── */

const usd = (v) => new Intl.NumberFormat("en-US", { style: "currency", currency: "USD", notation: "compact", maximumFractionDigits: 2 }).format(Number(v ?? 0));
const plain = (v) => Number(v).toLocaleString("en-US", { maximumFractionDigits: 2 });

/** The year's next RFQ reference, offered in the dialog: RFQ-2026-007. */
function nextRfq(rows) {
  const year = new Date().getFullYear();
  const prefix = `RFQ-${year}-`;
  const last = rows.filter((r) => String(r.reference ?? "").startsWith(prefix)).reduce((max, r) => Math.max(max, Number(String(r.reference).slice(prefix.length)) || 0), 0);
  return `${prefix}${String(last + 1).padStart(3, "0")}`;
}

const rfqInbox = portalScreen({
  resource: "bg-rfqs",
  area: "billing",
  key: "q",
  icon: "inbox",
  nameKey: "reference",
  pick: ({ m }) => ({ key: "state", options: choices(m, "rstate", RFQ_STATES) }),
  fields: ({ m, opts, rows }) => [
    { name: "reference", label: m("q.f.reference"), required: true, default: nextRfq(rows ?? []), placeholder: "RFQ-2026-118" },
    { name: "buyer_id", label: m("q.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "product_id", label: m("q.f.product"), type: "select", options: opts.products },
    { name: "quantity", label: m("q.f.quantity"), type: "number", default: 0 },
    { name: "target_price", label: m("q.f.target"), type: "number" },
    { name: "received_on", label: m("q.f.received"), type: "date" },
    { name: "respond_by", label: m("q.f.respond_by"), type: "date" },
    { name: "state", label: m("q.f.state"), type: "select", options: choices(m, "rstate", RFQ_STATES), default: "NEW" },
  ],
  editFields: ({ m, opts }) => [
    { name: "reference", label: m("q.f.reference"), required: true },
    { name: "buyer_id", label: m("q.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "product_id", label: m("q.f.product"), type: "select", options: opts.products },
    { name: "quantity", label: m("q.f.quantity"), type: "number" },
    { name: "target_price", label: m("q.f.target"), type: "number" },
    { name: "received_on", label: m("q.f.received"), type: "date" },
    { name: "respond_by", label: m("q.f.respond_by"), type: "date" },
    { name: "state", label: m("q.f.state"), type: "select", options: choices(m, "rstate", RFQ_STATES) },
  ],
  columns: ({ m }) => [
    { key: "reference", label: m("q.c.reference"), type: "mono" },
    { key: "buyer", label: m("q.c.buyer"), sub: "product" },
    { key: "quantity", label: m("q.c.quantity"), type: "number", grouping: true },
    { key: "target_price", label: m("q.c.target"), type: "number" },
    { key: "respond_by", label: m("q.c.respond_by"), type: "date" },
    { key: "state", label: m("q.c.state"), type: "badge", labels: labels(m, "rstate", RFQ_STATES), tones: RFQ_TONES },
  ],
  tiles: (rows, { m }) => [
    { label: m("q.t.requests"), value: rows.length },
    { label: m("q.t.new"), value: rows.filter((r) => r.state === "NEW").length },
    { label: m("q.t.converted"), value: rows.filter((r) => r.state === "CONVERTED").length },
    { label: m("q.t.pieces"), value: rows.reduce((sum, r) => sum + Number(r.quantity ?? 0), 0).toLocaleString("en-US") },
  ],
});

const priceLists = portalScreen({
  resource: "bg-price-lines",
  area: "billing",
  key: "l",
  icon: "tags",
  nameKey: "product",
  pick: ({ opts }) => ({ key: "season", options: opts.seasons }),
  fields: ({ m, opts }) => [
    { name: "product", label: m("l.f.product"), required: true, placeholder: "Basic crew tee, 160 gsm" },
    { name: "buyer_id", label: m("l.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "season", label: m("l.f.season"), type: "select", options: opts.seasons },
    { name: "price", label: m("l.f.price"), type: "number", default: 0 },
    { name: "incoterm", label: m("l.f.incoterm"), type: "select", options: INCOTERMS.map((x) => ({ value: x, label: x })) },
    { name: "min_qty", label: m("l.f.min_qty"), type: "number", default: 0 },
    { name: "valid_from", label: m("l.f.valid_from"), type: "date" },
    { name: "valid_to", label: m("l.f.valid_to"), type: "date" },
  ],
  columns: ({ m }) => [
    { key: "product", label: m("l.c.product"), sub: "buyer" },
    { key: "season", label: m("l.c.season") },
    { key: "price", label: m("l.c.price"), type: "number" },
    { key: "incoterm", label: m("l.c.incoterm") },
    { key: "min_qty", label: m("l.c.min_qty"), type: "number", grouping: true },
    { key: "valid_to", label: m("l.c.valid_to"), type: "date" },
  ],
  tiles: (rows, { m }) => [
    { label: m("l.t.lines"), value: rows.length },
    { label: m("l.t.buyers"), value: new Set(rows.map((r) => r.buyer_id)).size },
    { label: m("l.t.seasons"), value: new Set(rows.map((r) => r.season).filter(Boolean)).size },
    { label: m("l.t.average"), value: rows.length ? plain(rows.reduce((sum, r) => sum + Number(r.price ?? 0), 0) / rows.length) : 0 },
  ],
});

const invoices = portalScreen({
  resource: "bg-invoices",
  area: "billing",
  key: "i",
  icon: "invoice",
  nameKey: "invoice_no",
  pick: ({ m }) => ({ key: "state", options: choices(m, "istate", INVOICE_STATES) }),
  fields: ({ m, opts }) => [
    { name: "invoice_no", label: m("i.f.number"), required: true, placeholder: "INV-20261184" },
    { name: "buyer_id", label: m("i.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "order_no", label: m("i.f.order"), placeholder: "SO-4402" },
    { name: "amount", label: m("i.f.amount"), type: "number", default: 0 },
    { name: "issued_on", label: m("i.f.issued"), type: "date" },
    { name: "due_on", label: m("i.f.due"), type: "date" },
    { name: "state", label: m("i.f.state"), type: "select", options: choices(m, "istate", INVOICE_STATES), default: "PUBLISHED" },
  ],
  columns: ({ m }) => [
    { key: "invoice_no", label: m("i.c.invoice"), type: "mono" },
    { key: "buyer", label: m("i.c.buyer"), sub: "order_no" },
    { key: "amount", label: m("i.c.amount"), type: "number", currency: "USD" },
    { key: "issued_on", label: m("i.c.issued"), type: "date" },
    { key: "due_on", label: m("i.c.due"), type: "date" },
    { key: "state", label: m("i.c.state"), type: "badge", labels: labels(m, "istate", INVOICE_STATES), tones: INVOICE_TONES },
  ],
  tiles: (rows, { m }) => [
    { label: m("i.t.invoices"), value: rows.length },
    { label: m("i.t.value"), value: usd(rows.filter((r) => r.state !== "UNPUBLISHED").reduce((sum, r) => sum + Number(r.amount ?? 0), 0)) },
    { label: m("i.t.overdue"), value: rows.filter((r) => r.state === "OVERDUE").length },
    { label: m("i.t.paid"), value: rows.filter((r) => r.state === "PAID").length },
  ],
});

const payments = portalScreen({
  resource: "bg-payment-lines",
  area: "billing",
  key: "y",
  icon: "wallet",
  nameKey: "buyer",
  pick: ({ m }) => ({ key: "ageing", options: choices(m, "ageing", AGEING) }),
  decorate: (r, { m }) => ({ ...r, terms_label: r.payment_terms ? m(`terms.${r.payment_terms}`) : null }),
  fields: ({ m, opts }) => [
    { name: "buyer_id", label: m("y.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "billed", label: m("y.f.billed"), type: "number", default: 0 },
    { name: "received", label: m("y.f.received"), type: "number", default: 0 },
    { name: "payment_terms", label: m("y.f.terms"), type: "select", options: choices(m, "terms", PAYMENT_TERMS) },
    { name: "next_due", label: m("y.f.next_due"), type: "date" },
  ],
  columns: ({ m }) => [
    { key: "buyer", label: m("y.c.buyer"), sub: "terms_label" },
    { key: "billed", label: m("y.c.billed"), type: "number", currency: "USD" },
    { key: "received", label: m("y.c.received"), type: "number", currency: "USD" },
    { key: "outstanding", label: m("y.c.outstanding"), type: "number", currency: "USD" },
    { key: "next_due", label: m("y.c.next_due"), type: "date" },
    { key: "ageing", label: m("y.c.ageing"), type: "badge", labels: labels(m, "ageing", AGEING), tones: AGEING_TONES },
  ],
  tiles: (rows, { m }) => [
    { label: m("y.t.billed"), value: usd(rows.reduce((sum, r) => sum + Number(r.billed ?? 0), 0)) },
    { label: m("y.t.received"), value: usd(rows.reduce((sum, r) => sum + Number(r.received ?? 0), 0)) },
    { label: m("y.t.outstanding"), value: usd(rows.reduce((sum, r) => sum + Number(r.outstanding ?? 0), 0)) },
    { label: m("y.t.over_90"), value: rows.filter((r) => r.ageing === "OVER_90").length },
  ],
});

/* ── Buyer Engagement (25/04) ─────────────────────────────────────── */

/** The year's next number of a reference column, offered in the dialog: CMP-2026-007. */
function nextRef(rows, code, key) {
  const prefix = `${code}-${new Date().getFullYear()}-`;
  const last = rows.filter((r) => String(r[key] ?? "").startsWith(prefix)).reduce((max, r) => Math.max(max, Number(String(r[key]).slice(prefix.length)) || 0), 0);
  return `${prefix}${String(last + 1).padStart(3, "0")}`;
}

const requestInbox = portalScreen({
  resource: "bg-requests",
  area: "engagement",
  key: "r",
  icon: "message",
  nameKey: "subject",
  pick: ({ m }) => ({ key: "kind", options: choices(m, "rkind", REQUEST_KINDS) }),
  decorate: (r, { m }) => ({ ...r, kind_label: r.kind ? m(`rkind.${r.kind}`) : null, desk_label: m(`desk.${r.routed_to}`) }),
  fields: ({ m, opts }) => [
    { name: "subject", label: m("r.f.subject"), required: true, placeholder: "Ship date change for SO-4471" },
    { name: "buyer_id", label: m("r.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "kind", label: m("r.f.kind"), type: "select", options: choices(m, "rkind", REQUEST_KINDS) },
    { name: "routed_to", label: m("r.f.routed_to"), type: "select", options: choices(m, "desk", DESKS), default: "UNROUTED" },
    { name: "raised_on", label: m("r.f.raised_on"), type: "date" },
    { name: "age", label: m("r.f.age"), type: "derived", from: "raised_on", values: {}, placeholder: m("r.f.age_hint") },
    { name: "priority", label: m("r.f.priority"), type: "select", options: choices(m, "prio", PRIORITIES), default: "NORMAL" },
    { name: "state", label: m("r.f.state"), type: "select", options: choices(m, "qstate", REQUEST_STATES), default: "NEW" },
  ],
  columns: ({ m }) => [
    { key: "subject", label: m("r.c.request"), sub: "buyer" },
    { key: "kind_label", label: m("r.c.kind") },
    { key: "desk_label", label: m("r.c.routed_to") },
    { key: "age", label: m("r.c.age"), type: "number", suffix: " d" },
    { key: "priority", label: m("r.c.priority"), type: "badge", labels: labels(m, "prio", PRIORITIES), tones: PRIORITY_TONES },
    { key: "state", label: m("r.c.state"), type: "badge", labels: labels(m, "qstate", REQUEST_STATES), tones: REQUEST_TONES },
  ],
  tiles: (rows, { m }) => [
    { label: m("r.t.requests"), value: rows.length },
    { label: m("r.t.new"), value: rows.filter((r) => r.state === "NEW").length },
    { label: m("r.t.unrouted"), value: rows.filter((r) => r.state !== "CLOSED" && r.routed_to === "UNROUTED").length },
    { label: m("r.t.urgent"), value: rows.filter((r) => r.state !== "CLOSED" && r.priority === "URGENT").length },
  ],
});

const complaints = portalScreen({
  resource: "bg-complaints",
  area: "engagement",
  key: "c",
  icon: "alert",
  nameKey: "reference",
  pick: ({ m }) => ({ key: "state", options: choices(m, "cstate", COMPLAINT_STATES) }),
  decorate: (r, { m }) => ({ ...r, cause_label: r.cause ? m(`cause.${r.cause}`) : null }),
  fields: ({ m, opts, rows }) => [
    { name: "reference", label: m("c.f.reference"), required: true, default: nextRef(rows ?? [], "CMP", "reference"), placeholder: "CMP-2026-041" },
    { name: "buyer_id", label: m("c.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "shipment", label: m("c.f.shipment"), placeholder: "SO-4402" },
    { name: "cause", label: m("c.f.cause"), type: "select", options: choices(m, "cause", CAUSES) },
    { name: "claim_value", label: m("c.f.claim"), type: "number" },
    { name: "raised_on", label: m("c.f.raised_on"), type: "date" },
    { name: "state", label: m("c.f.state"), type: "select", options: choices(m, "cstate", COMPLAINT_STATES), default: "OPEN" },
    { name: "resolution", label: m("c.f.resolution"), type: "textarea", placeholder: m("c.f.resolution_ph") },
  ],
  columns: ({ m }) => [
    { key: "reference", label: m("c.c.reference"), type: "mono" },
    { key: "buyer", label: m("c.c.buyer"), sub: "resolution" },
    { key: "shipment", label: m("c.c.shipment") },
    { key: "cause_label", label: m("c.c.cause") },
    { key: "claim_value", label: m("c.c.claim"), type: "number", currency: "USD" },
    { key: "state", label: m("c.c.state"), type: "badge", labels: labels(m, "cstate", COMPLAINT_STATES), tones: COMPLAINT_TONES },
  ],
  tiles: (rows, { m }) => [
    { label: m("c.t.complaints"), value: rows.length },
    { label: m("c.t.open"), value: rows.filter((r) => r.state === "OPEN").length },
    { label: m("c.t.claims"), value: usd(rows.reduce((sum, r) => sum + Number(r.claim_value ?? 0), 0)) },
    { label: m("c.t.settled"), value: rows.filter((r) => r.state === "SETTLED").length },
  ],
});

const announcements = portalScreen({
  resource: "bg-announcements",
  area: "engagement",
  key: "a",
  icon: "bell",
  nameKey: "title",
  pick: ({ m }) => ({ key: "audience", options: choices(m, "audience", AUDIENCES) }),
  decorate: (r, { m }) => ({ ...r, kind_label: r.kind ? m(`nkind.${r.kind}`) : null, audience_label: r.audience === "ONE" && r.buyer ? r.buyer : m(`audience.${r.audience}`) }),
  fields: ({ m, opts }) => [
    { name: "title", label: m("a.f.title"), required: true, placeholder: "Eid holiday — factory closed" },
    { name: "audience", label: m("a.f.audience"), type: "select", options: choices(m, "audience", AUDIENCES), default: "ALL" },
    { name: "buyer_id", label: m("a.f.buyer"), type: "select", options: opts.buyers, showWhen: { field: "audience", value: "ONE" } },
    { name: "kind", label: m("a.f.kind"), type: "select", options: choices(m, "nkind", NOTICE_KINDS) },
    { name: "published_on", label: m("a.f.published"), type: "date" },
    { name: "expires_on", label: m("a.f.expires"), type: "date" },
    { name: "reads", label: m("a.f.reads"), type: "number", default: 0 },
    { name: "state", label: m("a.f.state"), type: "select", options: choices(m, "nstate", NOTICE_STATES), default: "LIVE", help: m("a.f.state_hint") },
    { name: "body", label: m("a.f.body"), type: "textarea", placeholder: m("a.f.body_ph") },
  ],
  columns: ({ m }) => [
    { key: "title", label: m("a.c.announcement"), sub: "body" },
    { key: "kind_label", label: m("a.c.kind") },
    { key: "audience_label", label: m("a.c.audience") },
    { key: "expires_on", label: m("a.c.expires"), type: "date" },
    { key: "reads", label: m("a.c.reads"), type: "number" },
    { key: "state", label: m("a.c.state"), type: "badge", labels: labels(m, "nstate", NOTICE_STATES), tones: NOTICE_TONES },
  ],
  tiles: (rows, { m }) => [
    { label: m("a.t.announcements"), value: rows.length },
    { label: m("a.t.live"), value: rows.filter((r) => r.state === "LIVE").length },
    { label: m("a.t.scheduled"), value: rows.filter((r) => r.state === "SCHEDULED").length },
    { label: m("a.t.reads"), value: rows.reduce((sum, r) => sum + Number(r.reads ?? 0), 0) },
  ],
});

const surveys = portalScreen({
  resource: "bg-surveys",
  area: "engagement",
  key: "f",
  icon: "thumbs",
  nameKey: "title",
  pick: ({ m }) => ({ key: "state", options: choices(m, "fstate", SURVEY_STATES) }),
  fields: ({ m, opts }) => [
    { name: "title", label: m("f.f.survey"), required: true, placeholder: "Winter 26 delivery experience" },
    { name: "buyer_id", label: m("f.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "season", label: m("f.f.season"), type: "select", options: opts.seasons },
    { name: "sent_on", label: m("f.f.sent_on"), type: "date" },
    { name: "recipients", label: m("f.f.recipients"), type: "number", default: 0 },
    { name: "responses", label: m("f.f.responses"), type: "number", default: 0 },
    { name: "score", label: m("f.f.score"), type: "number", help: m("f.f.score_hint") },
    { name: "state", label: m("f.f.state"), type: "select", options: choices(m, "fstate", SURVEY_STATES), default: "DRAFT" },
  ],
  columns: ({ m }) => [
    { key: "title", label: m("f.c.survey"), sub: "buyer" },
    { key: "season", label: m("f.c.season") },
    { key: "recipients", label: m("f.c.sent"), type: "number" },
    { key: "responses", label: m("f.c.replies"), type: "number" },
    { key: "score", label: m("f.c.score"), type: "number" },
    { key: "sent_on", label: m("f.c.sent_on"), type: "date" },
    { key: "state", label: m("f.c.state"), type: "badge", labels: labels(m, "fstate", SURVEY_STATES), tones: SURVEY_TONES },
  ],
  tiles: (rows, { m }) => {
    const scored = rows.filter((r) => Number(r.responses) > 0 && r.score !== null && r.score !== undefined);
    return [
      { label: m("f.t.surveys"), value: rows.length },
      { label: m("f.t.responses"), value: rows.reduce((sum, r) => sum + Number(r.responses ?? 0), 0) },
      { label: m("f.t.average"), value: scored.length ? plain(scored.reduce((sum, r) => sum + Number(r.score), 0) / scored.length) : 0 },
      { label: m("f.t.open"), value: rows.filter((r) => r.state === "OPEN").length },
    ];
  },
});

const tickets = portalScreen({
  resource: "bg-tickets",
  area: "engagement",
  key: "t",
  icon: "ticket",
  nameKey: "reference",
  pick: ({ m }) => ({ key: "state", options: choices(m, "tstate", TICKET_STATES) }),
  decorate: (r, { m }) => ({ ...r, owner_label: r.owner ?? m("t.unassigned") }),
  fields: ({ m, opts, rows }) => [
    { name: "reference", label: m("t.f.reference"), required: true, default: nextRef(rows ?? [], "TKT", "reference"), placeholder: "TKT-2026-284" },
    { name: "subject", label: m("t.f.subject"), required: true, placeholder: "Cannot download the packing list" },
    { name: "buyer_id", label: m("t.f.buyer"), type: "select", options: opts.buyers },
    { name: "priority", label: m("t.f.priority"), type: "select", options: choices(m, "tprio", TICKET_PRIORITIES), default: "NORMAL" },
    { name: "owner_id", label: m("t.f.owner"), type: "select", options: [{ value: "", label: m("t.unassigned") }, ...opts.staff] },
    { name: "raised_on", label: m("t.f.raised_on"), type: "date" },
    { name: "state", label: m("t.f.state"), type: "select", options: choices(m, "tstate", TICKET_STATES), default: "NEW" },
  ],
  columns: ({ m }) => [
    { key: "reference", label: m("t.c.reference"), type: "mono" },
    { key: "subject", label: m("t.c.subject"), sub: "buyer" },
    { key: "owner_label", label: m("t.c.owner") },
    { key: "hours", label: m("t.c.hours"), type: "number" },
    { key: "priority", label: m("t.c.priority"), type: "badge", labels: labels(m, "tprio", TICKET_PRIORITIES), tones: TICKET_PRIORITY_TONES },
    { key: "state", label: m("t.c.state"), type: "badge", labels: labels(m, "tstate", TICKET_STATES), tones: TICKET_TONES },
  ],
  tiles: (rows, { m }) => [
    { label: m("t.t.tickets"), value: rows.length },
    { label: m("t.t.open"), value: rows.filter(ticketOpen).length },
    // Unassigned and urgent only matter while the ticket is still open.
    { label: m("t.t.unassigned"), value: rows.filter((r) => ticketOpen(r) && !r.owner_id).length },
    { label: m("t.t.urgent"), value: rows.filter((r) => ticketOpen(r) && r.priority === "URGENT").length },
  ],
});

/* ── Portal Administration (25/05) ────────────────────────────────── */

const usage = portalScreen({
  resource: "bg-usage-lines",
  area: "admin",
  key: "g",
  icon: "chart",
  nameKey: "buyer",
  pick: ({ opts }) => ({ key: "period", options: opts.periods }),
  decorate: (r, { m }) => ({ ...r, page_label: r.most_opened ? m(`page.${r.most_opened}`) : null }),
  fields: ({ m, opts }) => [
    { name: "buyer_id", label: m("g.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "period", label: m("g.f.period"), type: "select", required: true, options: opts.periods, default: opts.periods[0]?.value },
    { name: "logins", label: m("g.f.logins"), type: "number", default: 0 },
    { name: "active_users", label: m("g.f.users"), type: "number", default: 0 },
    { name: "most_opened", label: m("g.f.most_opened"), type: "select", options: choices(m, "page", PAGES) },
    { name: "downloads", label: m("g.f.downloads"), type: "number", default: 0 },
    { name: "avg_session", label: m("g.f.session"), type: "number", step: "0.1", suffix: "min", default: 0 },
    { name: "adoption", label: m("g.f.adoption"), type: "number", step: "0.1", suffix: "%", default: 0 },
  ],
  columns: ({ m }) => [
    { key: "buyer", label: m("g.c.buyer"), sub: "page_label" },
    { key: "period_label", label: m("g.c.period") },
    { key: "logins", label: m("g.c.logins"), type: "number", grouping: true },
    { key: "active_users", label: m("g.c.users"), type: "number", grouping: true },
    { key: "downloads", label: m("g.c.downloads"), type: "number", grouping: true },
    { key: "avg_session", label: m("g.c.session"), type: "number", suffix: " min" },
    { key: "adoption", label: m("g.c.adoption"), type: "progress" },
  ],
  tiles: (rows, { m }) => {
    const sum = (k) => rows.reduce((s, r) => s + Number(r[k] ?? 0), 0).toLocaleString("en-US");
    const adoption = rows.length ? rows.reduce((s, r) => s + Number(r.adoption ?? 0), 0) / rows.length : 0;
    return [
      { label: m("g.t.logins"), value: sum("logins") },
      { label: m("g.t.users"), value: sum("active_users") },
      { label: m("g.t.downloads"), value: sum("downloads") },
      { label: m("g.t.adoption"), value: `${adoption.toFixed(1)}%` },
    ];
  },
});

const branding = portalScreen({
  resource: "bg-branding-profiles",
  area: "admin",
  key: "b",
  icon: "palette",
  booleans: ["live"],
  toggle: "live",
  nameKey: "buyer",
  pick: ({ m }) => ({ key: "theme", options: choices(m, "theme", THEMES) }),
  decorate: (r, { m }) => ({ ...r, theme_label: m(`theme.${r.theme}`), accent_label: m("b.slot", { n: r.accent_slot }) }),
  fields: ({ m, opts }) => [
    { name: "buyer_id", label: m("b.f.buyer"), type: "select", required: true, options: opts.buyers },
    { name: "theme", label: m("b.f.theme"), type: "select", options: choices(m, "theme", THEMES), default: "LIGHT" },
    { name: "accent_slot", label: m("b.f.accent"), type: "select", options: ACCENT_SLOTS.map((n) => ({ value: String(n), label: m("b.slot", { n }) })), default: "1" },
    { name: "logo_file", label: m("b.f.logo"), type: "filepath", upload: "/api/buyer-gateway/logo-file", accept: "image/png,image/jpeg,image/webp", attachLabel: m("b.f.attach"), placeholder: "hm-portal-mark.png", help: m("b.f.logo_hint") },
    { name: "welcome_line", label: m("b.f.welcome"), placeholder: m("b.f.welcome_ph"), span: 2 },
    { name: "support_email", label: m("b.f.support"), type: "email", placeholder: "portal@yourcompany.com" },
    { name: "updated_on", label: m("b.f.updated"), type: "date", help: m("b.f.updated_hint") },
    { name: "live", label: m("b.f.live"), type: "checkbox", default: true },
  ],
  columns: ({ m }) => [
    { key: "buyer", label: m("b.c.buyer"), sub: "welcome_line" },
    { key: "theme_label", label: m("b.c.theme") },
    { key: "accent_label", label: m("b.c.accent") },
    { key: "logo_name", label: m("b.c.logo") },
    { key: "updated_on", label: m("b.c.updated"), type: "date" },
  ],
  tiles: (rows, { m }) => [
    { label: m("b.t.profiles"), value: rows.length },
    { label: m("b.t.live"), value: rows.filter((r) => r.live).length },
    // A profile switched off leaves its buyer on the group default.
    { label: m("b.t.default"), value: rows.filter((r) => !r.live).length },
    { label: m("b.t.themes"), value: new Set(rows.map((r) => r.theme)).size },
  ],
});

const activity = portalScreen({
  resource: "bg-activity-entries",
  area: "admin",
  key: "e",
  icon: "activity",
  nameKey: "actor",
  pick: ({ m }) => ({ key: "action", options: choices(m, "action", ACTIONS) }),
  decorate: (r, { m }) => ({ ...r, action_label: r.action ? m(`action.${r.action}`) : null, device_label: r.device ? m(`device.${r.device}`) : null }),
  fields: ({ m, opts }) => [
    { name: "actor", label: m("e.f.actor"), required: true, placeholder: "Anna Lindqvist" },
    { name: "buyer_id", label: m("e.f.buyer"), type: "select", options: opts.buyers },
    { name: "action", label: m("e.f.action"), type: "select", options: choices(m, "action", ACTIONS) },
    { name: "target", label: m("e.f.target"), placeholder: "ST-4471 tech pack rev 3", span: 2 },
    { name: "occurred_on", label: m("e.f.date"), type: "date", help: m("e.f.date_hint") },
    { name: "occurred_time", label: m("e.f.time"), placeholder: "14:22", help: m("e.f.time_hint") },
    { name: "device", label: m("e.f.device"), type: "select", options: choices(m, "device", DEVICES) },
  ],
  columns: ({ m }) => [
    { key: "occurred_on", label: m("e.c.date"), type: "date" },
    { key: "occurred_time", label: m("e.c.time"), type: "mono" },
    { key: "actor", label: m("e.c.actor"), sub: "target" },
    { key: "buyer", label: m("e.c.buyer") },
    { key: "action_label", label: m("e.c.action") },
    { key: "device_label", label: m("e.c.device") },
  ],
  tiles: (rows, { m }) => [
    { label: m("e.t.entries"), value: rows.length },
    { label: m("e.t.downloads"), value: rows.filter((r) => r.action === "DOWNLOADED").length },
    { label: m("e.t.approvals"), value: rows.filter((r) => r.action === "APPROVED").length },
    { label: m("e.t.actors"), value: new Set(rows.map((r) => String(r.actor ?? "").trim().toLowerCase())).size },
  ],
});

const SCREENS = {
  "portal-users": portalUsers,
  "access-permissions": permissionSets,
  "buyer-onboarding": onboarding,
  "notification-preferences": notifications,
  "order-visibility": visibilityRules,
  "sample-status": sampleProgress,
  "shipment-status": shipmentProgress,
  "document-sharing": sharedDocuments,
  "rfq-inbox": rfqInbox,
  "buyer-price-lists": priceLists,
  "invoice-view": invoices,
  "payment-status": payments,
  "buyer-requests": requestInbox,
  "complaint-register": complaints,
  "announcements": announcements,
  "feedback-survey": surveys,
  "support-tickets": tickets,
  "portal-analytics": usage,
  "portal-branding": branding,
  "portal-activity-log": activity,
};

export { SCREENS };
