"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { RowFormModal } from "@/components/settings/row-form-modal";
import { useT } from "@/lib/i18n";

/** Mirrors `MasterDataType::display()` — a reference/belongs-to value shows its parent's label, never a bare ulid. */
function displayValue(field, row, referenceOptions, t) {
  const value = row[field.name];

  if (value === null || value === undefined || value === "") {
    return "—";
  }

  if (field.type === "BOOLEAN") {
    return value ? t("yes") : t("no");
  }

  if (field.type === "REFERENCE" || field.type === "BELONGS_TO") {
    const match = (referenceOptions[field.name] ?? []).find((option) => option.id === value);
    return match?.label ?? value;
  }

  return String(value);
}

/**
 * One table for every master-data type, columns driven by `schema.fields`
 * (whichever ones are `in_list`) — mirrors `MasterDataType::columns()`/
 * `::display()` exactly, the same reason the backend is one controller
 * for all two dozen of these.
 */
function MasterDataTable({ typeKey, rows, schema, referenceOptions, actions }) {
  const t = useT("masterdata");
  const tc = useT("common");
  const [createOpen, setCreateOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  const listFields = schema.fields.filter((field) => field.in_list);

  function runToggle(row) {
    startTransition(async () => {
      const result = await actions.setActive(typeKey, row.id, !row.active);
      if (result?.status === "success") {
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
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
    <div className="flex flex-col gap-4">
      <div className="flex justify-end">
        <Button size="sm" onClick={() => setCreateOpen(true)}>
          <Plus /> {t("new_row")}
        </Button>
      </div>

      <DataTable
        columns={listFields.map((field) => ({
          key: field.name,
          header: field.label,
          render: (row) => displayValue(field, row, referenceOptions, t),
        }))}
        rows={rows}
        rowKey={(row) => row.id}
        emptyTitle={t("empty")}
        rowActions={(row) =>
          row.is_platform
            ? [{ label: t("use_as_starting_point"), onSelect: () => setCreateOpen(row) }]
            : [
                { label: tc("edit"), onSelect: () => setEditing(row) },
                ...(schema.supports_active
                  ? [{ label: row.active ? t("deactivate") : t("activate"), onSelect: () => runToggle(row) }]
                  : []),
                { label: tc("delete"), destructive: true, onSelect: () => setDeleting(row) },
              ]
        }
      />

      <RowFormModal
        key={createOpen && createOpen !== true ? `copy-${createOpen.id}` : "create"}
        open={Boolean(createOpen)}
        onOpenChange={() => setCreateOpen(false)}
        title={t("new_entry_named", { type: schema.title })}
        schema={schema}
        referenceOptions={referenceOptions}
        row={createOpen && createOpen !== true ? { ...createOpen, id: undefined } : null}
        action={actions.createRow.bind(null, typeKey)}
      />

      {editing ? (
        <RowFormModal
          key={editing.id}
          open
          onOpenChange={() => setEditing(null)}
          title={t("edit_entry_named", { type: schema.title })}
          schema={schema}
          referenceOptions={referenceOptions}
          row={editing}
          action={actions.updateRow.bind(null, typeKey, editing.id)}
        />
      ) : null}

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={t("delete_entry_title", { type: schema.title.toLowerCase() })}
        description={t("delete_entry_description")}
        confirmLabel={tc("delete")}
        loading={pending}
        onConfirm={runDelete}
      />
    </div>
  );
}

export { MasterDataTable };
