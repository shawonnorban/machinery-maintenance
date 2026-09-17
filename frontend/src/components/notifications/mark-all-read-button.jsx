"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

function MarkAllReadButton({ action }) {
  const t = useT("notification");
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function run() {
    startTransition(async () => {
      const result = await action();
      if (result?.status === "success") {
        toastManager.add({ title: t("all_marked_read_toast"), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Button size="sm" loading={pending} onClick={run}>
      {t("mark_all_read")}
    </Button>
  );
}

export { MarkAllReadButton };
