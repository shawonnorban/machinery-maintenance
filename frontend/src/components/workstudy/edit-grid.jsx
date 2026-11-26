"use client";

import { ListChecks, Trash2 } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { StatusPill } from "@/components/erp/status-pill";
import { MultiPick } from "@/components/erp/multi-pick";

let seq = 0;
/** Give each row a key of its own, so lines added and removed keep their inputs. */
const keyed = (rows) => rows.map((r) => ({ ...r, _k: ++seq }));
const unkeyed = (rows) => rows.map(({ _k, ...r }) => r);

/** A grid line as plain text, for the CSV export. */
function cellText(column, row, index) {
  const label = (v) => column.options?.find((o) => o.value === v)?.label ?? v ?? "";
  if (column.type === "sl") return String(index + 1);
  if (column.type === "multi") return (row[column.key] ?? []).map(label).join("; ");
  if (column.type === "check") return row[column.key] ? "Yes" : "No";
  if (column.type === "actions") return "";
  if (column.type === "lookup") return label(row[column.from]);
  return String(column.type === "select" ? label(row[column.key]) : (row[column.key] ?? ""));
}

function exportCsv(name, columns, rows) {
  const shown = columns.filter((c) => c.type !== "action" && c.type !== "actions");
  const lines = [shown.map((c) => c.label), ...rows.map((r, i) => shown.map((c) => cellText(c, r, i)))];
  const csv = lines.map((l) => l.map((v) => `"${String(v).replaceAll('"', '""')}"`).join(",")).join("\n");
  const a = document.createElement("a");
  a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
  a.download = `${name}.csv`;
  a.click();
}

/**
 * The editable grid Work Study's setup screens share (AnnotechERP 14/01): a
 * brand-coloured header and one line per row. Column types: `sl` (line no.),
 * `text` (read only), `lookup` (read only, the label of another column's
 * value), `select`, `multi` (tags), `check`, `input` / `number` / `datetime`
 * (typed in; `lockedBy` names a key that, once a row has it, shows the value
 * as text instead — a saved code), `status` (Active / Inactive from a
 * boolean) and `action` (remove the line; `blockedBy` names a count that
 * keeps a row in use from being removed).
 *
 * @param {{ columns: object[], rows: object[], set: (k: number, field: string, value: any) => void, remove: (k: number) => void, canManage: boolean, empty: {title: string, text: string}, removeLabel: string }} props
 */
function EditGridTable({ columns, rows, set, remove, canManage, empty, removeLabel }) {
  const shown = columns.filter((c) => c.type !== "action" || canManage);
  if (rows.length === 0) {
    return (
      <div className="flex flex-col items-center gap-2 border-t border-border px-5 py-14 text-center">
        <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
          <ListChecks className="size-5" />
        </span>
        <p className="text-sm font-semibold">{empty.title}</p>
        <p className="max-w-md text-[13px] text-foreground-muted">{empty.text}</p>
      </div>
    );
  }
  const center = (c) => c.type === "check" || c.type === "action";
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-max text-sm">
        <thead>
          <tr className="bg-brand text-left text-brand-foreground">
            {shown.map((c) => (
              <th key={c.key} className={center(c) ? "px-3 py-2.5 text-center font-semibold" : "px-3 py-2.5 font-semibold"}>
                {c.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={r._k} className="border-b border-border last:border-0">
              {shown.map((c) => (
                <td key={c.key} className={center(c) ? "px-3 py-1.5 text-center" : "px-3 py-1.5"}>
                  {c.type === "sl" ? (
                    <span className="tabular-nums">{i + 1}</span>
                  ) : c.type === "text" || c.type === "lookup" ? (
                    <span className={c.mono ? "font-mono text-[13px]" : undefined}>{cellText(c, r, i) || "—"}</span>
                  ) : c.type === "multi" ? (
                    <div className={c.width ?? "w-60"}>
                      <MultiPick options={c.options} value={r[c.key] ?? []} onChange={(v) => set(r._k, c.key, v)} placeholder={c.placeholder} disabled={!canManage || (c.disabledBy && r[c.disabledBy])} ariaLabel={c.label} />
                    </div>
                  ) : c.type === "check" ? (
                    <Checkbox checked={!!r[c.key]} onCheckedChange={(v) => set(r._k, c.key, v === true)} disabled={!canManage} aria-label={c.label} />
                  ) : c.type === "action" ? (
                    <Button variant="ghost" size="sm" onClick={() => remove(r._k)} aria-label={removeLabel} disabled={Boolean(c.blockedBy && Number(r[c.blockedBy]) > 0)}>
                      <Trash2 className="text-danger" />
                    </Button>
                  ) : c.type === "actions" ? (
                    // A row's possible moves (Pay Period Actions); one is staged until Save, a second click unstages it.
                    <span className="inline-flex flex-wrap gap-1">
                      {c.buttons.map((b) => (
                        <Button key={b.value} type="button" size="sm" variant={r[c.key] === b.value ? "primary" : "outline"} onClick={() => set(r._k, c.key, r[c.key] === b.value ? "" : b.value)} disabled={!canManage}>
                          {b.label}
                        </Button>
                      ))}
                    </span>
                  ) : c.type === "status" ? (
                    <StatusPill active={!!r[c.from ?? c.key]} label={r[c.from ?? c.key] ? c.labels?.on : c.labels?.off} />
                  ) : c.type === "input" || c.type === "number" || c.type === "datetime" || c.type === "date" ? (
                    c.lockedBy && r[c.lockedBy] ? (
                      <span className="font-mono text-[13px]">{r[c.key]}</span>
                    ) : (
                      <Input type={c.type === "datetime" ? "datetime-local" : c.type === "date" ? "date" : c.type === "number" ? "number" : "text"} value={r[c.key] ?? ""} onChange={(e) => set(r._k, c.key, e.target.value)} disabled={!canManage} className={c.width ?? (c.type === "number" ? "w-24 text-right" : c.type === "date" ? "w-40" : "w-48")} aria-label={c.label} />
                    )
                  ) : (
                    <div className={c.width ?? "w-48"}>
                      <Select value={r[c.key] ?? ""} onValueChange={(v) => set(r._k, c.key, v ?? "")} options={c.options} placeholder={c.placeholder ?? "—"} disabled={!canManage} aria-label={c.label} />
                    </div>
                  )}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export { EditGridTable, keyed, unkeyed, exportCsv };
