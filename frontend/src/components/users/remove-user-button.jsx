"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Ends this company's membership (`ManageCompanyUser::remove` — the
 * keyholder guard against removing the last administrator lives there, so
 * the API refuses that case with its own message rather than this
 * component special-casing it). The account and everything it ever signed
 * off stay; only the row disappears from this company's own list.
 */
function RemoveUserButton({ userId, userName, action }) {
  const t = useT("user");
  const [open, setOpen] = useState(false);
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  function confirm() {
    startTransition(async () => {
      const result = await action(userId);
      if (result?.status === "success") {
        toastManager.add({ title: t("user_removed_toast"), type: "success" });
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
        <Trash2 /> {t("remove_button")}
      </Button>
      <ConfirmDialog
        open={open}
        onOpenChange={setOpen}
        title={t("remove_user_title", { name: userName })}
        description={t("remove_user_description")}
        confirmLabel={t("remove_button")}
        loading={pending}
        onConfirm={confirm}
      />
    </>
  );
}

export { RemoveUserButton };
