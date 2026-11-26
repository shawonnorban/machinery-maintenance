import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import { Tiles } from "@/components/buyergateway/tiles";
import * as actions from "./actions";

/*
 * AI File & PO Import (AnnotechERP 26), as Brickland lays each screen out:
 * four tiles, then one card — the title, the New button, a search and a
 * filter, the sortable list with its switch column, Edit and Delete on every
 * row, and the pager. The lists are the screen kit's ERP resources; the
 * buyers come from the company's buyer master.
 */

const FAMILIES = ["PURCHASE_ORDER", "TECH_PACK", "INQUIRY", "BOM", "COST_SHEET", "COMMERCIAL", "ARTWORK", "PRODUCTION_REPORT"];
const DATA_FAMILIES = ["PURCHASE_ORDER", "TECH_PACK", "INQUIRY", "BOM", "COST_SHEET", "COMMERCIAL"];
const INTAKE_FAMILIES = [...FAMILIES.slice(0, 7), "UNRECOGNISED"];
const SCOPES = [...DATA_FAMILIES, "EVERY"];
const ARRIVALS = ["EMAIL", "PORTAL", "WATCHED_FOLDER", "SFTP", "MANUAL"];
const STAGES = ["QUEUED", "RECOGNISING", "EXTRACTING", "VALIDATING", "AWAITING_APPROVAL", "POSTED", "FAILED"];
const MARKERS = ["HEADER_TEXT", "LOGO_REGION", "SHEET_NAME", "COLUMN_RUN", "FILE_NAMING", "SENDER_ADDRESS"];
const FOUND_BY = ["LABELLED", "TABLE_COLUMN", "FIXED_REGION", "REGEX", "MODEL"];
const DATA_TYPES = ["TEXT", "NUMBER", "DATE", "CURRENCY", "CODE"];
const KINDS = ["MASTER_LOOKUP", "RANGE", "FORMAT", "CROSS_FIELD", "DUPLICATE"];
const ON_FAILURE = ["BLOCK", "WARN", "REVIEW"];
const APPROVERS = ["MERCHANDISER", "PLANNING", "COMMERCIAL", "COSTING", "QUALITY", "DIVISION_HEAD"];
const STAGE_TONES = { QUEUED: "muted", RECOGNISING: "info", EXTRACTING: "info", VALIDATING: "info", AWAITING_APPROVAL: "warning", POSTED: "success", FAILED: "danger" };

const choices = (m, group, values) => values.map((v) => ({ value: v, label: m(`${group}.${v}`) }));
const labels = (m, group, values) => Object.fromEntries(values.map((v) => [v, m(`${group}.${v}`)]));
/** An average of a percentage column, one place: 81.2%. */
const average = (rows, key) => `${(rows.length ? rows.reduce((s, r) => s + Number(r[key] ?? 0), 0) / rows.length : 0).toFixed(1)}%`;
const families = (rows, key) => new Set(rows.map((r) => r[key]).filter(Boolean)).size;

function importScreen(config) {
  return async function render({ m, permissions }) {
    const canManage = permissions.includes(`aiimport.${config.area ?? "intake"}.manage`);
    const [rows, opts] = await Promise.all([apiFetch(`/erp/${config.resource}`), apiFetch("/ai-import/options")]);
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
          rowAction={canManage && config.rowAction ? config.rowAction(ctx) : undefined}
          remove={canManage ? actions.deleteResource.bind(null, config.resource) : undefined}
          deleteLabels={{ title: m(`${k}.delete_title`), confirm: m("delete"), aria: m(`${k}.delete_aria`), nameKey: config.nameKey, text: m(`${k}.delete_text`) }}
        />
      </div>
    );
  };
}

/* ── Intake (26/01) ───────────────────────────────────────────────── */

/** The intake form; an edit also shows what the AI service read (read-only). */
const intakeFields = ({ m, opts }, edit) => [
    { name: "file", label: m("i.f.file"), type: "filepath", required: true, upload: "/api/ai-file-import/intake-file", accept: ".pdf,.xlsx,.xls,.csv,.txt,.docx,.eml,.msg,image/jpeg,image/png,image/webp,image/gif", attachLabel: m("i.f.attach"), placeholder: "primark-po-88412.pdf", help: opts.reader?.auto ? m("i.f.file_hint_auto") : m("i.f.file_hint"), span: 2 },
    { name: "arrived_by", label: m("i.f.arrived"), type: "select", options: choices(m, "arrive", ARRIVALS), default: "MANUAL" },
    { name: "family", label: m("i.f.family"), type: "select", options: choices(m, "family", INTAKE_FAMILIES) },
    { name: "buyer_id", label: m("i.f.buyer"), type: "select", options: [{ value: "", label: m("i.not_identified") }, ...opts.buyers] },
    { name: "received_on", label: m("i.f.received"), type: "date" },
    { name: "size_mb", label: m("i.f.size"), type: "number", step: "0.1", suffix: "MB", help: m("i.f.size_hint") },
    { name: "confidence", label: m("i.f.confidence"), type: "number", step: "0.1", suffix: "%", default: 0 },
    { name: "stage", label: m("i.f.stage"), type: "select", options: choices(m, "stage", STAGES), default: "QUEUED" },
    ...(edit
      ? [
          { name: "read_by", label: m("i.f.read_by"), readOnly: true },
          { name: "read_note", label: m("i.f.read_note"), type: "textarea", readOnly: true, rows: 2 },
          { name: "summary", label: m("i.f.summary"), type: "textarea", readOnly: true, rows: 3 },
          { name: "values_text", label: m("i.f.values"), type: "textarea", readOnly: true, rows: 6 },
        ]
      : []),
  ];

const intake = importScreen({
  resource: "ai-intake-files",
  key: "i",
  icon: "intake",
  nameKey: "file_name",
  // Sends the file to the AI service in Settings → AI Services (again).
  rowAction: ({ m }) => ({ label: m("i.read"), action: actions.readIntake }),
  editFields: (ctx) => intakeFields(ctx, true),
  pick: ({ m }) => ({ key: "stage", options: choices(m, "stage", STAGES) }),
  // Under the file: why the AI stopped where it did, else how the file arrived.
  decorate: (r, { m }) => ({ ...r, arrived_label: m(`arrive.${r.arrived_by}`), file_sub: r.read_note ?? m(`arrive.${r.arrived_by}`), family_label: r.family ? m(`family.${r.family}`) : null, buyer_label: r.buyer ?? m("i.not_identified") }),
  fields: (ctx) => intakeFields(ctx, false),
  columns: ({ m }) => [
    { key: "file_name", label: m("i.c.file"), sub: "file_sub" },
    { key: "family_label", label: m("i.c.family") },
    { key: "buyer_label", label: m("i.c.buyer") },
    { key: "size_mb", label: m("i.c.size"), type: "number", decimals: 1, suffix: " MB" },
    { key: "confidence", label: m("i.c.confidence"), type: "progress" },
    { key: "stage", label: m("i.c.stage"), type: "badge", labels: labels(m, "stage", STAGES), tones: STAGE_TONES },
  ],
  tiles: (rows, { m }) => [
    { label: m("i.t.files"), value: rows.length },
    { label: m("i.t.posted"), value: rows.filter((r) => r.stage === "POSTED").length },
    { label: m("i.t.failed"), value: rows.filter((r) => r.stage === "FAILED").length },
    { label: m("i.t.confidence"), value: average(rows, "confidence") },
  ],
});

const signatures = importScreen({
  resource: "ai-signatures",
  key: "g",
  icon: "scan",
  booleans: ["enabled"],
  toggle: "enabled",
  nameKey: "name",
  pick: ({ m }) => ({ key: "family", options: choices(m, "family", FAMILIES) }),
  decorate: (r, { m }) => ({ ...r, family_label: r.family ? m(`family.${r.family}`) : null, marker_label: r.marker ? m(`marker.${r.marker}`) : null, buyer_label: r.buyer ?? m("g.any") }),
  fields: ({ m, opts }) => [
    { name: "name", label: m("g.f.name"), required: true, placeholder: "H&M PO header block" },
    { name: "family", label: m("g.f.family"), type: "select", options: choices(m, "family", FAMILIES) },
    { name: "buyer_id", label: m("g.f.buyer"), type: "select", options: [{ value: "", label: m("g.any") }, ...opts.buyers] },
    { name: "marker", label: m("g.f.marker"), type: "select", options: choices(m, "marker", MARKERS) },
    { name: "hit_rate", label: m("g.f.hit_rate"), type: "number", step: "0.1", suffix: "%", default: 0 },
    { name: "times_matched", label: m("g.f.matched"), type: "number", default: 0 },
    { name: "last_matched", label: m("g.f.last_matched"), type: "date" },
  ],
  columns: ({ m }) => [
    { key: "name", label: m("g.c.name"), sub: "marker_label" },
    { key: "family_label", label: m("g.c.family") },
    { key: "buyer_label", label: m("g.c.buyer") },
    { key: "hit_rate", label: m("g.c.hit_rate"), type: "progress" },
    { key: "times_matched", label: m("g.c.matched"), type: "number", grouping: true },
  ],
  tiles: (rows, { m }) => [
    { label: m("g.t.signatures"), value: rows.length },
    { label: m("g.t.enabled"), value: rows.filter((r) => r.enabled).length },
    { label: m("g.t.hit_rate"), value: average(rows, "hit_rate") },
    { label: m("g.t.families"), value: families(rows, "family") },
  ],
});

const extraction = importScreen({
  resource: "ai-extraction-rules",
  key: "x",
  icon: "table",
  booleans: ["mandatory"],
  toggle: "mandatory",
  nameKey: "field",
  pick: ({ m }) => ({ key: "family", options: choices(m, "family", DATA_FAMILIES) }),
  decorate: (r, { m }) => ({ ...r, family_label: r.family ? m(`family.${r.family}`) : null, found_label: r.found_by ? m(`found.${r.found_by}`) : null, type_label: m(`dtype.${r.data_type}`) }),
  fields: ({ m }) => [
    { name: "field", label: m("x.f.field"), required: true, placeholder: "Order quantity" },
    { name: "family", label: m("x.f.family"), type: "select", options: choices(m, "family", DATA_FAMILIES) },
    { name: "found_by", label: m("x.f.found_by"), type: "select", options: choices(m, "found", FOUND_BY) },
    { name: "erp_column", label: m("x.f.erp_column"), placeholder: "order_line.quantity", help: m("x.f.erp_column_hint") },
    { name: "data_type", label: m("x.f.type"), type: "select", options: choices(m, "dtype", DATA_TYPES), default: "TEXT" },
    { name: "accuracy", label: m("x.f.accuracy"), type: "number", step: "0.1", suffix: "%", default: 0 },
  ],
  columns: ({ m }) => [
    { key: "field", label: m("x.c.field"), sub: "erp_column" },
    { key: "family_label", label: m("x.c.family") },
    { key: "found_label", label: m("x.c.found_by") },
    { key: "type_label", label: m("x.c.type") },
    { key: "accuracy", label: m("x.c.accuracy"), type: "progress" },
  ],
  tiles: (rows, { m }) => [
    { label: m("x.t.rules"), value: rows.length },
    { label: m("x.t.mandatory"), value: rows.filter((r) => r.mandatory).length },
    { label: m("x.t.accuracy"), value: average(rows, "accuracy") },
    { label: m("x.t.families"), value: families(rows, "family") },
  ],
});

const validations = importScreen({
  resource: "ai-validations",
  key: "k",
  icon: "check",
  booleans: ["enabled"],
  toggle: "enabled",
  nameKey: "name",
  pick: ({ m }) => ({ key: "kind", options: choices(m, "kind", KINDS) }),
  decorate: (r, { m }) => ({ ...r, kind_label: r.kind ? m(`kind.${r.kind}`) : null, scope_label: m(`family.${r.applies_to}`), failure_label: m(`failure.${r.on_failure}`) }),
  fields: ({ m }) => [
    { name: "name", label: m("k.f.name"), required: true, placeholder: "Buyer must exist in the buyer master", span: 2 },
    { name: "kind", label: m("k.f.kind"), type: "select", options: choices(m, "kind", KINDS) },
    { name: "applies_to", label: m("k.f.applies_to"), type: "select", options: choices(m, "family", SCOPES), default: "EVERY" },
    { name: "on_failure", label: m("k.f.on_failure"), type: "select", options: choices(m, "failure", ON_FAILURE), default: "WARN" },
    { name: "triggered", label: m("k.f.triggered"), type: "number", default: 0 },
    { name: "pass_rate", label: m("k.f.pass_rate"), type: "number", step: "0.1", suffix: "%", default: 0 },
  ],
  columns: ({ m }) => [
    { key: "name", label: m("k.c.name"), sub: "scope_label" },
    { key: "kind_label", label: m("k.c.kind") },
    { key: "failure_label", label: m("k.c.on_failure") },
    { key: "triggered", label: m("k.c.triggered"), type: "number", grouping: true },
    { key: "pass_rate", label: m("k.c.pass_rate"), type: "progress" },
  ],
  tiles: (rows, { m }) => [
    { label: m("k.t.validations"), value: rows.length },
    { label: m("k.t.blocking"), value: rows.filter((r) => r.on_failure === "BLOCK").length },
    { label: m("k.t.enabled"), value: rows.filter((r) => r.enabled).length },
    { label: m("k.t.pass_rate"), value: average(rows, "pass_rate") },
  ],
});

const approvals = importScreen({
  resource: "ai-approval-steps",
  key: "w",
  icon: "approve",
  booleans: ["enabled"],
  toggle: "enabled",
  nameKey: "name",
  pick: ({ m }) => ({ key: "applies_to", options: choices(m, "family", SCOPES) }),
  decorate: (r, { m }) => ({ ...r, scope_label: m(`family.${r.applies_to}`), approver_label: r.approver ? m(`approver.${r.approver}`) : null, sla_label: r.sla_hours ? `${r.sla_hours} h` : null }),
  fields: ({ m }) => [
    { name: "name", label: m("w.f.name"), required: true, placeholder: "Merchandiser confirms quantities", span: 2 },
    { name: "applies_to", label: m("w.f.applies_to"), type: "select", options: choices(m, "family", SCOPES), default: "EVERY" },
    { name: "approver", label: m("w.f.approver"), type: "select", options: choices(m, "approver", APPROVERS) },
    { name: "sequence", label: m("w.f.sequence"), type: "number", help: m("w.f.sequence_hint") },
    { name: "auto_skip", label: m("w.f.auto_skip"), type: "number", step: "0.1", suffix: "%", help: m("w.f.auto_skip_hint") },
    { name: "sla_hours", label: m("w.f.sla"), type: "number", suffix: "h" },
    { name: "pending", label: m("w.f.pending"), type: "number", default: 0 },
  ],
  columns: ({ m }) => [
    { key: "sequence", label: m("w.c.sequence"), type: "number" },
    { key: "name", label: m("w.c.name"), sub: "scope_label" },
    { key: "approver_label", label: m("w.c.approver") },
    { key: "auto_skip", label: m("w.c.auto_skip"), type: "number", decimals: 1, suffix: "%" },
    { key: "sla_label", label: m("w.c.sla") },
    { key: "pending", label: m("w.c.pending"), type: "number" },
  ],
  tiles: (rows, { m }) => [
    { label: m("w.t.steps"), value: rows.length },
    { label: m("w.t.enabled"), value: rows.filter((r) => r.enabled).length },
    { label: m("w.t.waiting"), value: rows.reduce((s, r) => s + Number(r.pending ?? 0), 0) },
    { label: m("w.t.families"), value: families(rows, "applies_to") },
  ],
});

const SCREENS = {
  "omni-file-intelligence": intake,
  "recognition-engine": signatures,
  "field-extraction": extraction,
  "validation-engine": validations,
  "approval-workflow": approvals,
};

export { SCREENS };
