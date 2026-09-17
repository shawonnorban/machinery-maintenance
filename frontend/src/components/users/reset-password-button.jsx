"use client";

import { useState, useTransition } from "react";
import { KeyRound } from "lucide-react";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Mirrors `user::users.index.blade.php`'s own password-reissue flow: a
 * fresh password shown exactly once, in a modal the person reading it has
 * to explicitly close — never a toast, which can be missed or dismissed by
 * a stray click before it's been copied down.
 */
function ResetPasswordButton({ userId, action }) {
  const t = useT("user");
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
        <KeyRound /> {t("reset_password")}
      </Button>

      <ConfirmDialog
        open={confirming}
        onOpenChange={setConfirming}
        title={t("reset_password_title")}
        description={t("reset_password_description")}
        confirmLabel={t("reset_password")}
        loading={pending}
        onConfirm={confirm}
      />

      <Modal
        open={password !== null}
        onOpenChange={(open) => !open && setPassword(null)}
        title={t("password_reset_title")}
        description={t("password_created_hint")}
      >
        <div className="rounded-sm border border-border bg-surface-muted px-4 py-3 font-mono text-sm">{password}</div>
      </Modal>
    </>
  );
}

export { ResetPasswordButton };
