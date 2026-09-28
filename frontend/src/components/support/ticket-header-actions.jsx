"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { Pencil, Trash2 } from "lucide-react";
import { buttonVariants, Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";

/** Only rendered at all when `ticket.can_edit` is true — see `SupportTicketApiController::summary()` for exactly what that requires. */
function TicketHeaderActions({ ticketId, deleteTicket }) {
  const t = useT("support");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [confirming, setConfirming] = useState(false);
  const [pending, startTransition] = useTransition();

  function runDelete() {
    startTransition(async () => {
      const result = await deleteTicket(ticketId);
      if (result?.status === "success") {
        toastManager.add({ title: t("ticket_deleted_toast"), type: "success" });
        router.push("/support/tickets");
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
        setConfirming(false);
      }
    });
  }

  return (
    <div className="flex items-center gap-2">
      <Link href={`/support/tickets/${ticketId}/edit`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
        <Pencil /> {tc("edit")}
      </Link>
      <Button variant="outline" size="sm" onClick={() => setConfirming(true)}>
        <Trash2 className="text-danger" /> {tc("delete")}
      </Button>

      <ConfirmDialog
        open={confirming}
        onOpenChange={setConfirming}
        title={t("delete_ticket_title")}
        description={t("delete_ticket_description")}
        confirmLabel={tc("delete")}
        loading={pending}
        onConfirm={runDelete}
      />
    </div>
  );
}

export { TicketHeaderActions };
