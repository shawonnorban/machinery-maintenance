"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { CheckCheck } from "lucide-react";
import { Button } from "@/components/ui/button";

function MarkAllReadButton({ action }) {
  const [pending, startTransition] = useTransition();
  const router = useRouter();

  return (
    <Button
      size="sm"
      variant="outline"
      loading={pending}
      onClick={() =>
        startTransition(async () => {
          await action();
          router.refresh();
        })
      }
    >
      <CheckCheck /> Mark all read
    </Button>
  );
}

export { MarkAllReadButton };
