"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";

/** Mirrors `RoleApiController::destroy` — refused with a 409 while anyone still holds the role, surfaced here rather than guessed at client-side. */
function DeleteRoleButton({ roleName, action }) {
  const [open, setOpen] = useState(false);
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  function confirm() {
    startTransition(async () => {
      const result = await action();
      if (result?.status === "success") {
        toastManager.add({ title: "Role deleted", type: "success" });
        router.push("/settings/roles");
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
        setOpen(false);
      }
    });
  }

  return (
    <>
      <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
        Delete
      </Button>
      <ConfirmDialog
        open={open}
        onOpenChange={setOpen}
        title={`Delete ${roleName}?`}
        description="Only possible while nobody holds it — a role still assigned to somebody is refused."
        confirmLabel="Delete"
        loading={pending}
        onConfirm={confirm}
      />
    </>
  );
}

export { DeleteRoleButton };
