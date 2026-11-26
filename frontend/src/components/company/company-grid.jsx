"use client";

import { useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Download, ListChecks, Plus, RotateCcw, Save, Trash2 } from "lucide-react";
import { Card, CardActions, CardBody, CardDescription, CardFooter, CardHeader, CardTitle, CardTitleGroup } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { RowFormModal } from "@/components/settings/row-form-modal";
import { useT } from "@/lib/i18n";

/** Columns the grid edits in place, in schema order; the code is fixed once issued. */
function editableFields(schema) {
  return schema.fields.filter((field) => field.in_list);
}

function optionsFor(field, referenceOptions) {
  if (field.type === "ENUM") {
    return field.options.map((option) => ({
      value: option,
      label: field.option_labels?.[option] ?? option,
    }));
  }
  return (referenceOptions[field.name] ?? []).map((option) => ({
    value: option.id,
    label: option.label,
  }));
}

function readable(field, value, referenceOptions) {
  if (value === null || value === undefined || value === "") return "";
  if (field.type === "ENUM") return field.option_labels?.[value] ?? value;
  if (field.type === "REFERENCE" || field.type === "BELONGS_TO") {
    return (referenceOptions[field.name] ?? []).find((option) => option.id === value)?.label ?? value;
  }
  if (field.type === "BOOLEAN") return value ? "✓" : "";
  return String(value);
}

function Cell({ field, value, onChange, referenceOptions }) {
  // A code is fixed once issued — except where the tab says it may change (`editable`: the factory's).
  if (field.type === "CODE" && field.editable) {
    return <Input value={value ?? ""} onChange={(event) => onChange(event.target.value.toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, field.max ?? 32))} className="w-24 font-mono" aria-label={field.label} />;
  }
  if (field.type === "CODE") {
    return <span className="font-mono text-sm text-foreground">{value}</span>;
  }
  if (field.type === "BOOLEAN") {
    return <Checkbox checked={Boolean(value)} onCheckedChange={onChange} />;
  }
  if (field.type === "ENUM" || field.type === "REFERENCE" || field.type === "BELONGS_TO") {
    return <Select value={value ?? ""} onValueChange={onChange} options={optionsFor(field, referenceOptions)} className="min-w-36" aria-label={field.label} />;
  }
  return (
    <Input
      value={value ?? ""}
      onChange={(event) => onChange(event.target.value)}
      type={field.type === "NUMBER" ? "number" : field.type === "DATE" ? "date" : "text"}
      className={field.type === "NUMBER" ? "w-28" : "min-w-40"}
      aria-label={field.label}
    />
  );
}

/**
 * One Company Management tab: Brickland's card with Export and "Add X", an
 * inline-editable grid whose edits are kept as drafts until Save, and the rule
 * line with Revert/Save underneath. Driven by the master-data schema, so every
 * tab is this one component.
 */
function CompanyGrid({ tabKey, typeKey, rows, schema, referenceOptions, actions, rowActions, canCreate = true, canDelete = true, readOnlyField, ns = "company" }) {
  // A server page cannot hand a function across, so read-only rows are named
  // by a field (e.g. "is_system") rather than a predicate.
  const rowEditable = readOnlyField ? (row) => !row[readOnlyField] : null;
  const t = useT(ns);
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [drafts, setDrafts] = useState({});
  const [adding, setAdding] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [pending, startTransition] = useTransition();

  const fields = useMemo(() => editableFields(schema), [schema]);
  const computed = schema.computed ?? [];
  const dirtyIds = Object.keys(drafts);
  const singular = t(`singular.${tabKey}`);

  function valueOf(row, name) {
    return drafts[row.id] && name in drafts[row.id] ? drafts[row.id][name] : row[name];
  }

  function edit(row, name, value) {
    setDrafts((current) => {
      const next = { ...(current[row.id] ?? {}), [name]: value };
      // Back to what was saved: no longer a change.
      if (String(row[name] ?? "") === String(value ?? "")) delete next[name];
      const all = { ...current, [row.id]: next };
      if (Object.keys(next).length === 0) delete all[row.id];
      return all;
    });
  }

  function save() {
    if (dirtyIds.length === 0) {
      toastManager.add({ title: t("nothing_to_save"), type: "info" });
      return;
    }
    startTransition(async () => {
      const changes = dirtyIds.map((id) => {
        const row = rows.find((candidate) => candidate.id === id);
        const payload = {};
        for (const field of schema.fields) payload[field.name] = valueOf(row, field.name);
        return { id, payload };
      });
      const result = await actions.saveRows(typeKey, changes);
      const failed = result.results.filter((entry) => entry.status === "error");
      setDrafts((current) => {
        const kept = {};
        for (const entry of failed) kept[entry.id] = current[entry.id];
        return kept;
      });
      if (failed.length > 0) {
        toastManager.add({
          title: t("save_failed", {
            count: failed.length,
            message: failed[0].message,
          }),
          type: "danger",
        });
      } else {
        toastManager.add({ title: t("saved"), type: "success" });
      }
      router.refresh();
    });
  }

  function exportCsv() {
    const columns = [
      ...fields.map((field) => ({
        key: field.name,
        label: field.label,
        field,
      })),
      ...computed.map((c) => ({ key: c.name, label: c.label })),
    ];
    const escape = (text) => `"${String(text ?? "").replaceAll('"', '""')}"`;
    const lines = [columns.map((column) => escape(column.label)).join(",")];
    for (const row of rows) {
      lines.push(columns.map((column) => escape(column.field ? readable(column.field, row[column.key], referenceOptions) : row[column.key])).join(","));
    }
    const blob = new Blob([`﻿${lines.join("\n")}`], {
      type: "text/csv;charset=utf-8",
    });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `${tabKey}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  function runDelete() {
    startTransition(async () => {
      const result = await actions.deleteRow(typeKey, deleting.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("deleted"), type: "success" });
        setDeleting(null);
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardHeader className="pb-4">
        <div className="flex items-start gap-3">
          <ListChecks className="mt-0.5 size-5 shrink-0 text-foreground-muted" aria-hidden />
          <CardTitleGroup>
            <CardTitle>{t(`tabs.${tabKey}`)}</CardTitle>
            <CardDescription>{t(`descriptions.${tabKey}`)}</CardDescription>
          </CardTitleGroup>
        </div>
        <CardActions>
          <Button variant="outline" size="sm" onClick={exportCsv} disabled={rows.length === 0}>
            <Download /> {t("export")}
          </Button>
          {canCreate ? (
            <Button size="sm" variant="outline" onClick={() => setAdding(true)}>
              <Plus /> {t("add", { name: singular })}
            </Button>
          ) : null}
        </CardActions>
      </CardHeader>

      <CardBody className="overflow-x-auto p-0">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-primary text-left text-primary-foreground">
              <th className="px-3 py-2.5 font-semibold">{t("sl")}</th>
              {fields.map((field) => (
                <th key={field.name} className="px-3 py-2.5 font-semibold whitespace-nowrap">
                  {field.label}
                  {field.required ? <span className="ml-0.5 text-primary-foreground/80">*</span> : null}
                </th>
              ))}
              {computed.map((column) => (
                <th key={column.name} className="px-3 py-2.5 text-right font-semibold whitespace-nowrap">
                  {column.label}
                </th>
              ))}
              <th className="px-3 py-2.5 text-right font-semibold">{t("action")}</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={fields.length + computed.length + 2} className="px-3 py-10 text-center text-foreground-muted">
                  {t("empty")}
                </td>
              </tr>
            ) : (
              rows.map((row, index) => (
                <tr key={row.id} className={drafts[row.id] ? "bg-warning-subtle/40" : "border-b border-border last:border-0"}>
                  <td className="px-3 py-2 text-foreground-muted">{index + 1}</td>
                  {fields.map((field) => (
                    <td key={field.name} className="px-3 py-2">
                      {rowEditable && !rowEditable(row) ? (
                        <span className="text-sm text-foreground">{readable(field, row[field.name], referenceOptions) || "—"}</span>
                      ) : (
                        <Cell field={field} value={valueOf(row, field.name)} onChange={(value) => edit(row, field.name, value)} referenceOptions={referenceOptions} />
                      )}
                    </td>
                  ))}
                  {computed.map((column) => (
                    <td key={column.name} className="px-3 py-2 text-right tabular-nums">
                      {row[column.name] ?? 0}
                    </td>
                  ))}
                  <td className="px-3 py-2 text-right whitespace-nowrap">
                    {rowActions ? rowActions(row) : null}
                    {canDelete && (!rowEditable || rowEditable(row)) ? (
                      <Button variant="ghost" size="sm" onClick={() => setDeleting(row)} aria-label={tc("delete")}>
                        <Trash2 />
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </CardBody>

      <CardFooter className="flex flex-wrap items-center justify-between gap-3">
        <p className="max-w-3xl text-sm text-foreground-muted">
          {dirtyIds.length > 0 ? <strong className="mr-2 text-foreground">{t("unsaved", { count: dirtyIds.length })}</strong> : null}
          {t(`rules.${tabKey}`)}
        </p>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={() => setDrafts({})} disabled={dirtyIds.length === 0 || pending}>
            <RotateCcw /> {t("revert")}
          </Button>
          <Button size="sm" onClick={save} loading={pending} disabled={dirtyIds.length === 0}>
            <Save /> {t("save")}
          </Button>
        </div>
      </CardFooter>

      {canCreate ? (
        <RowFormModal
          key={adding ? "adding" : "idle"}
          open={adding}
          onOpenChange={() => setAdding(false)}
          title={t("add", { name: singular })}
          schema={schema}
          referenceOptions={referenceOptions}
          row={null}
          action={actions.createRow.bind(null, typeKey)}
          autoCodeHint={t("auto_code")}
          submitLabel={t("add", { name: singular })}
        />
      ) : null}

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={t("delete_title", { name: singular.toLowerCase() })}
        description={t("delete_description")}
        confirmLabel={tc("delete")}
        loading={pending}
        onConfirm={runDelete}
      />
    </Card>
  );
}

export { CompanyGrid };
