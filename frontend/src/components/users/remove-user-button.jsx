"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";

/**
 * Ends this company's membership (`ManageCompanyUser::remove` — the
 * keyholder guard against removing the last administrator lives there, so
 * the API refuses that case with its own message rather than this
 * component special-casing it). The account and everything it ever signed
 * off stay; only the row disappears from this company's own list.
 */
function RemoveUserButton({ userId, userName, action }) {
  const [open, setOpen] = useState(false);
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  function confirm() {
    startTransition(async () => {
      const result = await action(userId);
      if (result?.status === "success") {
        toastManager.add({ title: "User removed", type: "success" });
        router.push("/settings/users");
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
        setOpen(false);
      }
    });
  }

  return (
    <>
      <Button size="sm" variant="danger" onClick={() => setOpen(true)}>
        <Trash2 /> Remove
      </Button>
      <ConfirmDialog
        open={open}
        onOpenChange={setOpen}
        title={`Remove ${userName}?`}
        description="Ends their membership of this company. Their account and everything they've signed off stay exactly as they are — they simply lose access here."
        confirmLabel="Remove"
        loading={pending}
        onConfirm={confirm}
      />
    </>
  );
}

export { RemoveUserButton };
