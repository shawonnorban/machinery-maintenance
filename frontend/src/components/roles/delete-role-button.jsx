"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/** Mirrors `RoleApiController::destroy` — refused with a 409 while anyone still holds the role, surfaced here rather than guessed at client-side. */
function DeleteRoleButton({ roleName, action }) {
  const t = useT("user");
  const tc = useT("common");
  const [open, setOpen] = useState(false);
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  function confirm() {
    startTransition(async () => {
      const result = await action();
      if (result?.status === "success") {
        toastManager.add({ title: t("role_deleted_toast"), type: "success" });
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
        {tc("delete")}
      </Button>
      <ConfirmDialog
        open={open}
        onOpenChange={setOpen}
        title={t("delete_role_title", { name: roleName })}
        description={t("delete_role_description")}
        confirmLabel={tc("delete")}
        loading={pending}
        onConfirm={confirm}
      />
    </>
  );
}

export { DeleteRoleButton };
