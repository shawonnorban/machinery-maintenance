"use client";

import Link from "next/link";
import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button, buttonVariants } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { cn } from "@/lib/utils";

/** Mirrors `WebhookController::show`'s own header actions. */
function WebhookHeaderActions({ endpoint, actions }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [newSecret, setNewSecret] = useState(null);
  const toastManager = useToastManager();

  function runToggle() {
    startTransition(async () => {
      const result = endpoint.status === "ACTIVE" ? await actions.pauseEndpoint(endpoint.id) : await actions.enableEndpoint(endpoint.id);
      if (result?.status === "success") router.refresh();
      else if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  function runRotate() {
    startTransition(async () => {
      const result = await actions.rotateSecret(endpoint.id);
      if (result?.status === "success") setNewSecret(result.secret);
      else if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  return (
    <div className="flex gap-2">
      <Link href={`/settings/webhooks/${endpoint.id}/edit`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
        Edit
      </Link>
      <Button size="sm" variant="outline" loading={pending} onClick={runRotate}>
        Rotate secret
      </Button>
      <Button size="sm" variant={endpoint.status === "ACTIVE" ? "outline" : "primary"} loading={pending} onClick={runToggle}>
        {endpoint.status === "ACTIVE" ? "Pause" : "Enable"}
      </Button>

      <Modal open={Boolean(newSecret)} onOpenChange={() => setNewSecret(null)} title="New signing secret">
        <p className="mb-3 text-sm text-foreground-muted">Copy this now — it will not be shown again.</p>
        <div className="rounded-sm border border-border bg-surface-muted px-4 py-3 font-mono text-sm break-all">{newSecret}</div>
      </Modal>
    </div>
  );
}

export { WebhookHeaderActions };
