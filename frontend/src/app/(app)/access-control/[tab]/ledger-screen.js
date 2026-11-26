import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { EntryForm } from "@/components/erp/entry-form";
import { findByRef, refsFor } from "@/lib/row-ref";
import * as forms from "./form-actions";

/*
 * Access Control → Ledger Integration Setup (AnnotechERP 02/03, Brickland's
 * GL Configuration): the GL sources (Records), one source's COA Detail Data
 * (GL Configuration Entry — the Chart of Account ledgers it debits and
 * credits), and New GL source. Machinery's costs post through their sources
 * as draft vouchers once a debit and a credit ledger are set (the user's
 * decision of 2026-11-15).
 */

const BASE = "/access-control/ledger-integration";
const SOURCES = "acc-gl-sources";
const LINES = "acc-gl-source-lines";
const SOURCE_BOOLEANS = ["final_bill_collection", "editable_from_gl", "active"];
const opt = (value, label) => ({ value: String(value), label });

function sourceFields(c, editing) {
  return [
    { name: "name", label: c("gl_source_name"), required: true, placeholder: "Bank Bill Collection" },
    { name: "name_bn", label: c("gl_source_name_bn") },
    {
      name: "mapping_type",
      label: c("gl_mapping_type"),
      type: "select",
      required: true,
      default: "GENERAL",
      options: ["GENERAL", "BANK_BILL_COLLECTION"].map((v) => opt(v, c(`gl_mapping_types.${v}`))),
    },
    { name: "link_ref", label: c("gl_link_ref"), help: c("gl_link_ref_hint"), readOnly: editing === "system" },
    { name: "final_bill_collection", label: c("gl_final_bill"), type: "checkbox", help: c("gl_final_bill_hint") },
    { name: "editable_from_gl", label: c("gl_editable"), type: "checkbox" },
    { name: "active", label: c("active"), type: "checkbox", default: true },
  ];
}

async function ledgerIntegration({ t, te, tab, searchParams }) {
  const c = (key) => t(`cfg.${key}`);
  const [sources, lines] = await Promise.all([apiFetch(`/erp/${SOURCES}`), apiFetch(`/erp/${LINES}`)]);
  const types = Object.fromEntries(["GENERAL", "BANK_BILL_COLLECTION"].map((v) => [v, c(`gl_mapping_types.${v}`)]));
  const sides = Object.fromEntries(["DEBIT", "CREDIT"].map((v) => [v, c(`gl_sides.${v}`)]));
  // A source is named by its name in the URL, never by its id.
  const chosen = findByRef(sources, "name", searchParams.source);
  const refs = refsFor(sources, "name");

  const tabs = [
    { key: "records", label: te("records"), icon: "Table2", count: sources.length },
    { key: "entry", label: c("gl_entry"), icon: "ListTree" },
    { key: "new", label: c("gl_new_source"), icon: "Plus" },
  ];

  return {
    count: sources.length,
    tabs,
    body: async () => {
      if (tab === "new") {
        return (
          <EntryForm
            fields={sourceFields(c, false)}
            action={forms.createResource.bind(null, SOURCES, SOURCE_BOOLEANS)}
            title={c("gl_new_source")}
            description={c("gl_hint")}
            submitLabel={c("gl_save_source")}
            columns={3}
            successHref={`${BASE}?tab=records`}
          />
        );
      }

      if (tab === "entry") {
        const options = await apiFetch("/accounting/vouchers/options").catch(() => ({ accounts: [], cost_centers: [] }));
        const picker = (
          <div className="rounded-lg border border-border bg-surface p-4">
            <p className="mb-2 text-sm font-semibold text-foreground">{c("gl_existing_source")}</p>
            <div className="flex flex-wrap gap-2">
              {sources.map((s) => {
                const ref = refs.get(s.id);
                const on = chosen?.id === s.id;
                return (
                  <a
                    key={s.id}
                    href={`${BASE}?tab=entry&source=${encodeURIComponent(ref)}`}
                    className={`rounded-md border px-3 py-1.5 text-sm ${on ? "border-brand bg-brand-subtle font-semibold text-brand" : "border-border text-foreground hover:bg-surface-muted"}`}
                  >
                    {s.name}
                    {s.ready ? null : <span className="ml-1.5 text-xs text-warning">· {c("gl_not_ready")}</span>}
                  </a>
                );
              })}
            </div>
          </div>
        );
        if (!chosen) {
          return (
            <div className="flex flex-col gap-4">
              {picker}
              <p className="text-sm text-foreground-muted">{c("gl_pick_source")}</p>
            </div>
          );
        }
        const rows = lines.filter((l) => l.gl_source_id === chosen.id);
        const lineFields = [
          { name: "gl_source_id", type: "hidden", default: chosen.id },
          { name: "side", label: c("gl_side"), type: "select", required: true, default: "DEBIT", options: Object.entries(sides).map(([v, l]) => opt(v, l)) },
          { name: "account_id", label: c("gl_account"), type: "select", required: true, options: options.accounts ?? [], span: 2 },
          { name: "cost_center_id", label: c("gl_cost_center"), type: "select", options: [opt("", "—"), ...(options.cost_centers ?? [])] },
          { name: "note", label: c("gl_note"), span: 2 },
        ];
        return (
          <div className="flex flex-col gap-4">
            {picker}
            <div className="rounded-lg border border-border bg-surface p-4 text-sm">
              <p className="font-semibold text-foreground">{chosen.name}</p>
              <p className="text-foreground-muted">
                {types[chosen.mapping_type] ?? chosen.mapping_type}
                {chosen.link_ref ? ` · ${c("gl_link_ref")}: ${chosen.link_ref}` : ""}
              </p>
              <p className="mt-1 text-xs text-foreground-muted">{c("gl_lines_hint")}</p>
            </div>
            <RecordsGrid
              exportName="gl-coa-lines"
              rows={rows.map((r) => ({ ...r, side_label: sides[r.side] ?? r.side }))}
              columns={[
                { key: "side_label", label: c("gl_side") },
                { key: "account", label: c("gl_account") },
                { key: "cost_center", label: c("gl_cost_center") },
                { key: "note", label: c("gl_note") },
              ]}
              edit={{
                fields: lineFields,
                action: forms.updateResource.bind(null, LINES, []),
                title: c("gl_edit_line"),
                submitLabel: c("gl_save_line"),
                columns: 3,
              }}
              remove={forms.deleteResource.bind(null, LINES)}
              emptyText={c("gl_no_lines")}
            />
            <EntryForm
              fields={lineFields}
              action={forms.createResource.bind(null, LINES, [])}
              title={c("gl_add_line")}
              submitLabel={c("gl_save_line")}
              columns={3}
              successHref={`${BASE}?tab=entry&source=${encodeURIComponent(refs.get(chosen.id))}`}
            />
          </div>
        );
      }

      return (
        <RecordsGrid
          exportName="gl-sources"
          rows={sources.map((s) => ({ ...s, type_label: types[s.mapping_type] ?? s.mapping_type }))}
          columns={[
            { key: "name", label: c("gl_source_name") },
            { key: "name_bn", label: c("gl_source_name_bn") },
            { key: "type_label", label: c("gl_mapping_type") },
            { key: "link_ref", label: c("gl_link_ref"), type: "mono" },
            { key: "lines", label: c("gl_lines"), type: "number" },
            { key: "editable_from_gl", label: c("gl_editable"), type: "yesno" },
            { key: "updated_at", label: c("updated"), type: "datetime" },
            { key: "active", label: c("status"), type: "status" },
          ]}
          rowLink={{ label: c("gl_entry"), href: `${BASE}?tab=entry&source=`, refKey: "name" }}
          edit={{
            fields: sourceFields(c, true),
            action: forms.updateResource.bind(null, SOURCES, SOURCE_BOOLEANS),
            title: c("gl_edit_source"),
            submitLabel: c("gl_save_source"),
            columns: 3,
          }}
          remove={forms.deleteResource.bind(null, SOURCES)}
        />
      );
    },
  };
}

export { ledgerIntegration };
