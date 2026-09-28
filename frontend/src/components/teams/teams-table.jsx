"use client";

import { useActionState, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { StatusBadge } from "@/components/ui/status-badge";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { FormField } from "@/components/ui/form-field";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

function TeamsTable({ teams, factories, actions }) {
  const t = useT("team");
  const tc = useT("common");
  const [createOpen, setCreateOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  function runToggle(team) {
    startTransition(async () => {
      const result = await actions.toggleTeam(team);
      if (result?.status === "success") {
        toastManager.add({ title: team.status === "ACTIVE" ? t("team_deactivated_toast") : t("team_activated_toast"), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  function runDelete() {
    startTransition(async () => {
      const result = await actions.deleteTeam(deleting.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("team_deleted_toast"), type: "success" });
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
          <Plus /> {t("new_team_title")}
        </Button>
      </div>

      <DataTable
        columns={[
          {
            key: "name",
            header: t("team"),
            render: (row) => (
              <div>
                <button type="button" onClick={() => setEditing(row)} className="font-medium text-brand hover:underline">
                  {row.name}
                </button>
                <div className="text-xs text-foreground-muted">{row.code}</div>
              </div>
            ),
          },
          { key: "factory", header: t("factory"), render: (row) => row.factory?.name ?? "—" },
          { key: "specialization", header: t("specialization"), render: (row) => row.specialization ?? "—" },
          {
            key: "status",
            header: t("status"),
            render: (row) => <StatusBadge status={row.status} label={row.status === "ACTIVE" ? t("active") : t("inactive")} />,
          },
        ]}
        rows={teams}
        rowKey={(row) => row.id}
        emptyTitle={t("no_teams")}
        rowActions={(row) => [
          { label: tc("edit"), onSelect: () => setEditing(row) },
          { label: row.status === "ACTIVE" ? t("deactivate") : t("activate"), onSelect: () => runToggle(row) },
          { label: tc("delete"), destructive: true, onSelect: () => setDeleting(row) },
        ]}
      />

      <TeamFormModal open={createOpen} onOpenChange={setCreateOpen} team={null} factories={factories} action={actions.createTeam} />
      <TeamFormModal
        key={editing?.id ?? "edit"}
        open={Boolean(editing)}
        onOpenChange={(open) => !open && setEditing(null)}
        team={editing}
        factories={factories}
        action={editing ? actions.updateTeam.bind(null, editing.id) : actions.updateTeam}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={t("delete_confirm_title", { name: deleting?.name })}
        description={t("delete_confirm_hint")}
        confirmLabel={tc("delete")}
        loading={pending}
        onConfirm={runDelete}
      />
    </div>
  );
}

/** One form for both create and edit, the same shape `TeamApiController::validated()` accepts either way — `key={team?.id ?? "create"}` at the call site remounts this fresh per target. */
function TeamFormModal({ open, onOpenChange, team, factories, action }) {
  const t = useT("team");
  const tc = useT("common");
  const isEdit = team != null;
  const [state, formAction] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: isEdit ? t("team_saved_toast") : t("team_created_toast"), type: "success" });
      queueMicrotask(() => onOpenChange(false));
      router.refresh();
    }
    // toastManager/router/onOpenChange/isEdit are deliberately excluded —
    // see every other form in this app for why including them re-fires
    // this effect every render once state first becomes "success".
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={isEdit ? t("edit_team_title", { name: team.name }) : t("new_team_title")}>
      <form action={formAction} className="flex flex-col gap-4">
        <FormField label={t("name")} required error={state?.errors?.name?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="name" defaultValue={team?.name} maxLength={255} required />}
        </FormField>
        <FormField label={t("code")} required helperText={t("code_hint")} error={state?.errors?.code?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="code" defaultValue={team?.code} maxLength={32} required />}
        </FormField>
        <FormField label={t("factory")} required error={state?.errors?.factory_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="factory_id"
              defaultValue={team?.factory?.id ?? ""}
              placeholder={t("select_a_factory")}
              options={factories.map((f) => ({ value: f.id, label: f.name }))}
            />
          )}
        </FormField>
        <FormField label={t("specialization")}>
          {(fieldProps) => <Input {...fieldProps} name="specialization" defaultValue={team?.specialization} maxLength={255} />}
        </FormField>

        {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {tc("cancel")}
          </Button>
          <Button type="submit">{isEdit ? t("save_changes") : t("create_team")}</Button>
        </div>
      </form>
    </Modal>
  );
}

export { TeamsTable };
