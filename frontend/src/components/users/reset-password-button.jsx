"use client";

import { useState, useTransition } from "react";
import { KeyRound } from "lucide-react";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";

/**
 * Mirrors `user::users.index.blade.php`'s own password-reissue flow: a
 * fresh password shown exactly once, in a modal the person reading it has
 * to explicitly close — never a toast, which can be missed or dismissed by
 * a stray click before it's been copied down.
 */
function ResetPasswordButton({ userId, action }) {
  const [confirming, setConfirming] = useState(false);
  const [password, setPassword] = useState(null);
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function confirm() {
    startTransition(async () => {
      const result = await action(userId);
      if (result?.status === "success") {
        setConfirming(false);
        setPassword(result.password);
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <>
      <Button size="sm" variant="outline" onClick={() => setConfirming(true)}>
        <KeyRound /> Reset password
      </Button>

      <ConfirmDialog
        open={confirming}
        onOpenChange={setConfirming}
        title="Reset this user's password?"
        description="A new password is generated immediately. Their current one stops working."
        confirmLabel="Reset password"
        loading={pending}
        onConfirm={confirm}
      />

      <Modal
        open={password !== null}
        onOpenChange={(open) => !open && setPassword(null)}
        title="Password reset"
        description="Share this with them — it will not be shown again."
      >
        <div className="rounded-sm border border-border bg-surface-muted px-4 py-3 font-mono text-sm">{password}</div>
      </Modal>
    </>
  );
}

export { ResetPasswordButton };
