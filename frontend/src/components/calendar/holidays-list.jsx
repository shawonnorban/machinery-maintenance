"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Trash2 } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";

/**
 * Column `render` functions can't cross the Server→Client boundary, so this
 * client component owns both the column definitions and the delete
 * mutation — the server page just hands over plain `holidays` data.
 */
function HolidaysList({ holidays, deleteHoliday }) {
  const router = useRouter();
  const [deleting, setDeleting] = useState(null);
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function runDelete() {
    startTransition(async () => {
      const result = await deleteHoliday(deleting.id);
      if (result?.status === "success") {
        toastManager.add({ title: "Removed from the calendar", type: "success" });
        setDeleting(null);
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <>
      <DataTable
        columns={[
          { key: "date", header: "Date" },
          {
            key: "name",
            header: "Occasion",
            render: (holiday) => (
              <>
                {holiday.name}
                {holiday.is_working_day ? <Badge variant="success" className="ml-1">Working</Badge> : null}
              </>
            ),
          },
          {
            key: "actions",
            header: "",
            align: "right",
            render: (holiday) => (
              <Button variant="ghost" size="icon" aria-label="Delete" onClick={() => setDeleting(holiday)}>
                <Trash2 className="text-danger" />
              </Button>
            ),
          },
        ]}
        rows={holidays}
        rowKey={(holiday) => holiday.id}
        emptyTitle="Nothing on the calendar."
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={`Remove ${deleting?.name}?`}
        confirmLabel="Remove"
        loading={pending}
        onConfirm={runDelete}
      />
    </>
  );
}

export { HolidaysList };
