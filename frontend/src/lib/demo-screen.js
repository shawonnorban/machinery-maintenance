import { apiFetch } from "@/lib/api-server";
import { RecordsGrid } from "@/components/erp/records-grid";
import { DialogFormButton } from "@/components/erp/dialog-form-button";
import { Tiles } from "@/components/buyergateway/tiles";

/*
 * Brickland's "Demo data" register screens (AnnotechERP 27 onward), as each is
 * laid out: four tiles, then one card — the title, the New button, a search
 * and a filter (a select `pick`, or a `check` tick), the sortable list with
 * its switch column and row button, Edit and Delete on every row, and the
 * pager. One module calls `demoScreens({ actions, prefix, optionsPath })` and
 * describes each screen with the returned function.
 *
 * A screen: resource, area, key (its lang prefix), icon, nameKey, booleans,
 * toggle, pick | check, rowAction, decorate, fields, editFields, columns, tiles.
 */
function demoScreens({ actions, prefix, optionsPath }) {
  return function screen(config) {
    return async function render({ m, permissions }) {
      const canManage = permissions.includes(`${prefix}.${config.area}.manage`);
      const [rows, opts] = await Promise.all([apiFetch(`/erp/${config.resource}`), apiFetch(optionsPath)]);
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
              pick: config.pick ? { ...config.pick(ctx), label: m(`${k}.pick_label`), all: m(`${k}.pick_all`) } : undefined,
              check: config.check ? { key: config.check, label: m(`${k}.check`) } : undefined,
              action: canManage ? (
                <DialogFormButton variant="primary" label={m(`${k}.new`)} title={m(`${k}.new_title`)} description={m(`${k}.dialog_sub`)} fields={fields} action={actions.createResource.bind(null, config.resource, config.booleans ?? [])} submitLabel={m(`${k}.create`)} />
              ) : null,
            }}
            emptyState={{ title: m(`${k}.empty_title`), text: m(`${k}.empty_text`) }}
            columns={[...config.columns(ctx), ...toggle]}
            rowAction={canManage && config.rowAction ? config.rowAction(ctx) : undefined}
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
  };
}

const choices = (m, group, values) => values.map((v) => ({ value: v, label: m(`${group}.${v}`) }));
const labels = (m, group, values) => Object.fromEntries(values.map((v) => [v, m(`${group}.${v}`)]));
/** An average of a percentage column, `places` decimals: 85% or 81.2%. */
const average = (rows, key, places = 0) => `${(rows.length ? rows.reduce((s, r) => s + Number(r[key] ?? 0), 0) / rows.length : 0).toFixed(places)}%`;
const total = (rows, key) => rows.reduce((s, r) => s + Number(r[key] ?? 0), 0);
const distinct = (rows, key) => new Set(rows.map((r) => r[key]).filter(Boolean)).size;

export { demoScreens, choices, labels, average, total, distinct };
