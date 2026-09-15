"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";

function CloseGrantButton({ grantId, action }) {
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  function close() {
    startTransition(async () => {
      const result = await action(grantId);
      if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      } else {
        router.refresh();
      }
    });
  }

  return (
    <Button size="sm" variant="outline" loading={pending} onClick={close}>
      Close now
    </Button>
  );
}

export { CloseGrantButton };
