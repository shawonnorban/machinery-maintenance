"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";

const DAY_SHORT = { 1: "Mon", 2: "Tue", 3: "Wed", 4: "Thu", 5: "Fri", 6: "Sat", 7: "Sun" };

/**
 * Column `render` functions can't cross the Server→Client boundary, so this
 * client component owns both the column definitions and the "end shift"
 * mutation — the server page just hands over plain `shifts` data.
 */
function ShiftsList({ shifts, endShift }) {
  const router = useRouter();
  const [ending, setEnding] = useState(null);
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function runEnd() {
    startTransition(async () => {
      const result = await endShift(ending.id);
      if (result?.status === "success") {
        toastManager.add({ title: "Shift ended", type: "success" });
        setEnding(null);
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
          {
            key: "name",
            header: "Shift",
            render: (shift) => (
              <div>
                {shift.name}
                <div className="text-xs text-foreground-muted">{shift.code}</div>
              </div>
            ),
          },
          {
            key: "hours",
            header: "Hours",
            render: (shift) => (
              <>
                {shift.start_time.slice(0, 5)}–{shift.end_time.slice(0, 5)}
                {shift.crosses_midnight ? <Badge className="ml-1">Overnight</Badge> : null}
                {shift.is_overtime ? <Badge className="ml-1">Overtime</Badge> : null}
              </>
            ),
          },
          {
            key: "days",
            header: "Days",
            render: (shift) => (shift.days_of_week ?? []).map((d) => DAY_SHORT[d]).join(" "),
          },
          {
            key: "actions",
            header: "",
            align: "right",
            render: (shift) =>
              shift.status === "ACTIVE" ? (
                <Button variant="outline" size="sm" onClick={() => setEnding(shift)}>
                  End
                </Button>
              ) : (
                <span className="text-xs text-foreground-muted">Ended {shift.effective_to}</span>
              ),
          },
        ]}
        rows={shifts}
        rowKey={(shift) => shift.id}
        emptyTitle="No shifts yet."
        emptyDescription="Without one, a scheduled operating time cannot be computed."
      />

      <ConfirmDialog
        open={Boolean(ending)}
        onOpenChange={() => setEnding(null)}
        title={`End ${ending?.name} from today?`}
        description="Past availability keeps reading against the hours it actually had."
        confirmLabel="End shift"
        loading={pending}
        onConfirm={runEnd}
      />
    </>
  );
}

export { ShiftsList };
