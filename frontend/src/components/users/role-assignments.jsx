"use client";

import { useActionState, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { X, Plus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { useToastManager } from "@/components/ui/toast";

/** Mirrors the web's granular per-assignment add/remove (not the bulk `roles` array `UserApiController::update` also accepts) — one role, one factory scope, one action at a time. */
function RoleAssignments({ userId, assignments, roles, factories, assign, remove }) {
  const [open, setOpen] = useState(false);
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  function runRemove(assignmentId) {
    startTransition(async () => {
      const result = await remove(userId, assignmentId);
      if (result?.status === "success") {
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <div className="flex flex-col gap-3">
      {assignments.length > 0 ? (
        <ul className="flex flex-col gap-2">
          {assignments.map((a) => (
            <li key={a.id} className="flex items-center justify-between gap-2 rounded-sm border border-border px-3 py-2">
              <div className="flex items-center gap-2">
                <Badge variant="brand">{a.role}</Badge>
                <span className="text-xs text-foreground-muted">{a.factory ?? "Company-wide"}</span>
              </div>
              <button type="button" onClick={() => runRemove(a.id)} disabled={pending} className="text-foreground-muted hover:text-danger" aria-label="Remove role">
                <X className="size-4" />
              </button>
            </li>
          ))}
        </ul>
      ) : (
        <p className="text-sm text-foreground-muted">No roles assigned.</p>
      )}

      <div>
        <Button size="sm" variant="outline" onClick={() => setOpen(true)}>
          <Plus className="size-4" /> Add role
        </Button>
      </div>

      <AddRoleModal open={open} onOpenChange={setOpen} roles={roles} factories={factories} action={assign.bind(null, userId)} />
    </div>
  );
}

function AddRoleModal({ open, onOpenChange, roles, factories, action }) {
  const [state, formAction] = useActionState(action, null);
  const router = useRouter();
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: "Role assigned", type: "success" });
      queueMicrotask(() => onOpenChange(false));
      router.refresh();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <Modal open={open} onOpenChange={onOpenChange} title="Add role">
      <form action={formAction} className="flex flex-col gap-4">
        <FormField label="Role" required error={state?.errors?.role_id?.[0]}>
          {(fieldProps) => <Select {...fieldProps} name="role_id" placeholder="Select a role" options={roles.map((r) => ({ value: String(r.id), label: r.name }))} />}
        </FormField>

        <FormField label="Factory" helperText="Leave blank for a company-wide role." error={state?.errors?.factory_id?.[0]}>
          {(fieldProps) => <Select {...fieldProps} name="factory_id" placeholder="Company-wide" options={factories.map((f) => ({ value: f.id, label: f.name }))} />}
        </FormField>

        {state?.status === "error" && !state.errors ? <p className="text-sm text-danger">{state.message}</p> : null}

        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit">Add role</Button>
        </div>
      </form>
    </Modal>
  );
}

export { RoleAssignments };
