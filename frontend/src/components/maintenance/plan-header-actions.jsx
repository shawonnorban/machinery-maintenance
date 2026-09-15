"use client";

import Link from "next/link";
import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button, buttonVariants } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { cn } from "@/lib/utils";

/** Mirrors `plans/show.blade.php`'s own header actions. */
function PlanHeaderActions({ plan, actions }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [deleting, setDeleting] = useState(false);
  const toastManager = useToastManager();

  function runToggle() {
    startTransition(async () => {
      const result = plan.active ? await actions.deactivatePlan(plan.id) : await actions.activatePlan(plan.id);
      if (result?.status === "success") router.refresh();
      else if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  function runDelete() {
    startTransition(async () => {
      const result = await actions.deletePlan(plan.id);
      if (result?.status === "success") {
        toastManager.add({ title: "Plan deleted", type: "success" });
        router.push("/maintenance/plans");
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
        setDeleting(false);
      }
    });
  }

  return (
    <div className="flex gap-2">
      <Link href={`/maintenance/plans/${plan.id}/edit`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
        Edit
      </Link>
      <Button size="sm" variant={plan.active ? "outline" : "primary"} loading={pending} onClick={runToggle}>
        {plan.active ? "Deactivate" : "Activate"}
      </Button>
      <Button size="sm" variant="danger" onClick={() => setDeleting(true)}>
        Delete
      </Button>

      <ConfirmDialog
        open={deleting}
        onOpenChange={setDeleting}
        title={`Delete ${plan.name}?`}
        description="Only possible while the plan has never generated an occurrence."
        confirmLabel="Delete"
        loading={pending}
        onConfirm={runDelete}
      />
    </div>
  );
}

export { PlanHeaderActions };
