"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

function UserStatusToggle({ userId, status, activate, deactivate }) {
  const t = useT("user");
  const [open, setOpen] = useState(false);
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();
  const isActive = status === "ACTIVE";

  function confirm() {
    startTransition(async () => {
      const result = await (isActive ? deactivate : activate)(userId);
      if (result?.status === "success") {
        toastManager.add({ title: isActive ? t("user_suspended_toast") : t("user_activated_toast"), type: "success" });
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
        {isActive ? t("suspend") : t("activate")}
      </Button>
      <ConfirmDialog
        open={open}
        onOpenChange={setOpen}
        title={isActive ? t("suspend_user_title") : t("activate_user_title")}
        description={isActive ? t("suspend_user_description") : undefined}
        confirmLabel={isActive ? t("suspend") : t("activate")}
        destructive={isActive}
        loading={pending}
        onConfirm={confirm}
      />
    </>
  );
}

export { UserStatusToggle };
