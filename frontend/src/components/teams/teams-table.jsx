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

function TeamsTable({ teams, factories, actions }) {
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
        toastManager.add({ title: team.status === "ACTIVE" ? "Team deactivated" : "Team activated", type: "success" });
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
        toastManager.add({ title: "Team deleted", type: "success" });
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
          <Plus /> New team
        </Button>
      </div>

      <DataTable
        columns={[
          {
            key: "name",
            header: "Team",
            render: (t) => (
              <div>
                <button type="button" onClick={() => setEditing(t)} className="font-medium text-brand hover:underline">
                  {t.name}
                </button>
                <div className="text-xs text-foreground-muted">{t.code}</div>
              </div>
            ),
          },
          { key: "factory", header: "Factory", render: (t) => t.factory?.name ?? "—" },
          { key: "specialization", header: "Specialization", render: (t) => t.specialization ?? "—" },
          { key: "status", header: "Status", render: (t) => <StatusBadge status={t.status} /> },
        ]}
        rows={teams}
        rowKey={(t) => t.id}
        emptyTitle="No teams yet."
        rowActions={(t) => [
          { label: "Edit", onSelect: () => setEditing(t) },
          { label: t.status === "ACTIVE" ? "Deactivate" : "Activate", onSelect: () => runToggle(t) },
          { label: "Delete", destructive: true, onSelect: () => setDeleting(t) },
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
        title={`Delete ${deleting?.name}?`}
        description="Only possible while nothing points at this team."
        confirmLabel="Delete"
        loading={pending}
        onConfirm={runDelete}
      />
    </div>
  );
}

/** One form for both create and edit, the same shape `TeamApiController::validated()` accepts either way — `key={team?.id ?? "create"}` at the call site remounts this fresh per target. */
function TeamFormModal({ open, onOpenChange, team, factories, action }) {
  const isEdit = team != null;
  const [state, formAction] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: isEdit ? "Team saved" : "Team created", type: "success" });
      queueMicrotask(() => onOpenChange(false));
      router.refresh();
    }
    // toastManager/router/onOpenChange/isEdit are deliberately excluded —
    // see every other form in this app for why including them re-fires
    // this effect every render once state first becomes "success".
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={isEdit ? `Edit ${team.name}` : "New team"}>
      <form action={formAction} className="flex flex-col gap-4">
        <FormField label="Name" required error={state?.errors?.name?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="name" defaultValue={team?.name} maxLength={255} required />}
        </FormField>
        <FormField label="Code" required helperText="Letters, numbers, dots, hyphens, underscores." error={state?.errors?.code?.[0]}>
          {(fieldProps) => <Input {...fieldProps} name="code" defaultValue={team?.code} maxLength={32} required />}
        </FormField>
        <FormField label="Factory" required error={state?.errors?.factory_id?.[0]}>
          {(fieldProps) => (
            <Select
              {...fieldProps}
              name="factory_id"
              defaultValue={team?.factory?.id ?? ""}
              placeholder="Select a factory"
              options={factories.map((f) => ({ value: f.id, label: f.name }))}
            />
          )}
        </FormField>
        <FormField label="Specialization">
          {(fieldProps) => <Input {...fieldProps} name="specialization" defaultValue={team?.specialization} maxLength={255} />}
        </FormField>

        {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit">{isEdit ? "Save changes" : "Create team"}</Button>
        </div>
      </form>
    </Modal>
  );
}

export { TeamsTable };
