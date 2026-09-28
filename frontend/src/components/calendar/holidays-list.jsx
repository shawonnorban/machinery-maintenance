"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Trash2 } from "lucide-react";
import { DataTable } from "@/components/ui/data-table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Column `render` functions can't cross the Server→Client boundary, so this
 * client component owns both the column definitions and the delete
 * mutation — the server page just hands over plain `holidays` data.
 */
function HolidaysList({ holidays, deleteHoliday }) {
  const t = useT("calendar");
  const tc = useT("common");
  const router = useRouter();
  const [deleting, setDeleting] = useState(null);
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  function runDelete() {
    startTransition(async () => {
      const result = await deleteHoliday(deleting.id);
      if (result?.status === "success") {
        toastManager.add({ title: t("holiday_removed"), type: "success" });
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
          { key: "date", header: t("date") },
          {
            key: "name",
            header: t("occasion"),
            render: (holiday) => (
              <>
                {holiday.name}
                {holiday.is_working_day ? <Badge variant="success" className="ml-1">{t("working_day")}</Badge> : null}
              </>
            ),
          },
          {
            key: "actions",
            header: "",
            align: "right",
            render: (holiday) => (
              <Button variant="ghost" size="icon" aria-label={tc("delete")} onClick={() => setDeleting(holiday)}>
                <Trash2 className="text-danger" />
              </Button>
            ),
          },
        ]}
        rows={holidays}
        rowKey={(holiday) => holiday.id}
        emptyTitle={t("no_holidays")}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={() => setDeleting(null)}
        title={deleting ? t("remove_holiday_title", { name: deleting.name }) : ""}
        confirmLabel={t("remove")}
        loading={pending}
        onConfirm={runDelete}
      />
    </>
  );
}

export { HolidaysList };
