"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Button } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/status-badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { CustomFieldDefinitionModal } from "@/components/settings/custom-field-definition-modal";
import { useT } from "@/lib/i18n";

/** One table per `fieldable_type` — forked from `MasterDataTable` since a custom field's columns are fixed, not schema-driven like a master-data row's. */
function CustomFieldDefinitionsTable({ fieldableType, rows, actions }) {
  const t = useT("custom_field");
  const tc = useT("common");
  const [createOpen, setCreateOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  function runToggle(row) {
    startTransition(async () => {
      const result = await actions.setActive(row.id, !row.active);
      if (result?.status === "success") {
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  function runDelete() {
    startTransition(async () => {
      const result = await actions.deleteDefinition(deleting.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("field_deleted_toast"), type: "success" });
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
          <Plus /> {t("new_field")}
        </Button>
      </div>

      <DataTable
        columns={[
          { key: "label", header: t("label"), render: (row) => row.label },
          { key: "code", header: t("code"), render: (row) => <code className="text-xs">{row.code}</code> },
          { key: "field_type", header: t("field_type"), render: (row) => t(`field_type_${row.field_type.toLowerCase()}`) },
          { key: "required", header: t("required"), render: (row) => (row.required ? tc("yes") : tc("no")) },
          {
            key: "active",
            header: t("status"),
            render: (row) => <StatusBadge status={row.active ? "ACTIVE" : "INACTIVE"} label={row.active ? t("active") : t("inactive")} />,
          },
        ]}
        rows={rows}
        rowKey={(row) => row.id}
        emptyTitle={t("no_fields")}
        emptyDescription={t("no_fields_hint")}
        rowActions={(row) => [
          { label: tc("edit"), onSelect: () => setEditing(row) },
          { label: row.active ? t("deactivate") : t("activate"), onSelect: () => runToggle(row) },
          { label: tc("delete"), destructive: true, onSelect: () => setDeleting(row) },
        ]}
      />

      <CustomFieldDefinitionModal
        key="create"
        open={createOpen}
        onOpenChange={() => setCreateOpen(false)}
        title={t("new_field")}
        definition={null}
        action={actions.createDefinition.bind(null, fieldableType)}
      />

      {editing ? (
        <CustomFieldDefinitionModal
          key={editing.id}
          open
          onOpenChange={() => setEditing(null)}
          title={t("edit_field")}
          definition={editing}
          action={actions.updateDefinition.bind(null, editing.id)}
        />
      ) : null}

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={t("delete_confirm_title")}
        description={t("delete_confirm_body")}
        confirmLabel={tc("delete")}
        loading={pending}
        onConfirm={runDelete}
      />
    </div>
  );
}

export { CustomFieldDefinitionsTable };
