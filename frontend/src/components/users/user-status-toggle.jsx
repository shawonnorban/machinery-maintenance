"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";

function UserStatusToggle({ userId, status, activate, deactivate }) {
  const [open, setOpen] = useState(false);
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();
  const isActive = status === "ACTIVE";

  function confirm() {
    startTransition(async () => {
      const result = await (isActive ? deactivate : activate)(userId);
      if (result?.status === "success") {
        toastManager.add({ title: isActive ? "User suspended" : "User activated", type: "success" });
        setOpen(false);
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <>
      <Button size="sm" variant={isActive ? "danger" : "outline"} onClick={() => setOpen(true)}>
        {isActive ? "Suspend" : "Activate"}
      </Button>
      <ConfirmDialog
        open={open}
        onOpenChange={setOpen}
        title={isActive ? "Suspend this user?" : "Activate this user?"}
        description={isActive ? "They will no longer be able to sign in." : undefined}
        confirmLabel={isActive ? "Suspend" : "Activate"}
        destructive={isActive}
        loading={pending}
        onConfirm={confirm}
      />
    </>
  );
}

export { UserStatusToggle };
