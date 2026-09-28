"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const DAY_SHORT_KEY = { 1: "monday_short", 2: "tuesday_short", 3: "wednesday_short", 4: "thursday_short", 5: "friday_short", 6: "saturday_short", 7: "sunday_short" };

/**
 * Column `render` functions can't cross the Server→Client boundary, so this
 * client component owns both the column definitions and the "end shift"
 * mutation — the server page just hands over plain `shifts` data.
 */
function ShiftsList({ shifts, endShift }) {
  const t = useT("calendar");
  const router = useRouter();
  const [ending, setEnding] = useState(null);
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function runEnd() {
    startTransition(async () => {
      const result = await endShift(ending.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("shift_ended"), type: "success" });
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
            header: t("shift"),
            render: (shift) => (
              <div>
                {shift.name}
                <div className="text-xs text-foreground-muted">{shift.code}</div>
              </div>
            ),
          },
          {
            key: "hours",
            header: t("hours"),
            render: (shift) => (
              <>
                {shift.start_time.slice(0, 5)}–{shift.end_time.slice(0, 5)}
                {shift.crosses_midnight ? <Badge className="ml-1">{t("overnight")}</Badge> : null}
                {shift.is_overtime ? <Badge className="ml-1">{t("overtime")}</Badge> : null}
              </>
            ),
          },
          {
            key: "days",
            header: t("days"),
            render: (shift) => (shift.days_of_week ?? []).map((d) => t(DAY_SHORT_KEY[d])).join(" "),
          },
          {
            key: "actions",
            header: "",
            align: "right",
            render: (shift) =>
              shift.status === "ACTIVE" ? (
                <Button variant="outline" size="sm" onClick={() => setEnding(shift)}>
                  {t("end_shift")}
                </Button>
              ) : (
                <span className="text-xs text-foreground-muted">{t("ended_on", { date: shift.effective_to })}</span>
              ),
          },
        ]}
        rows={shifts}
        rowKey={(shift) => shift.id}
        emptyTitle={t("no_shifts_title")}
        emptyDescription={t("no_shifts_hint")}
      />

      <ConfirmDialog
        open={Boolean(ending)}
        onOpenChange={() => setEnding(null)}
        title={ending ? t("end_shift_title", { name: ending.name }) : ""}
        description={t("end_shift_description")}
        confirmLabel={t("end_shift")}
        loading={pending}
        onConfirm={runEnd}
      />
    </>
  );
}

export { ShiftsList };
