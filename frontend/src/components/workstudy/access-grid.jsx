"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Download, ListChecks, Plus, RotateCcw, Save } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { EditGridTable, exportCsv, keyed, unkeyed } from "./edit-grid";

/**
 * Work Study → Floor-Wise Target Setup and Finishing Floor Access Setup
 * (AnnotechERP 14/01): one editable grid of access lines — Add a line, tag
 * its floors, pick the user and rights — saved as a whole. Save and Revert
 * wake only once something has changed.
 *
 * @param {{ title: string, hint: string, footerKey: string, empty: {title: string, text: string}, columns: object[], blank: object, rows: object[], save: Function, canManage: boolean, exportName?: string }} props
 */
function AccessGrid({ title, hint, footerKey, empty, columns, blank, rows: initial, save, canManage, exportName }) {
  const t = useT("workstudy");
  const router = useRouter();
  const toastManager = useToastManager();
  const [rows, setRows] = useState(() => keyed(initial));
  const [dirty, setDirty] = useState(false);
  const [pending, startTransition] = useTransition();

  const change = (next) => {
    setRows(next);
    setDirty(true);
  };

  function submit() {
    startTransition(async () => {
      const result = await save(unkeyed(rows));
      if (result?.status === "success") {
        setRows(keyed(result.rows));
        setDirty(false);
        toastManager.add({ title: t("master.saved"), type: "success" });
        router.refresh();
      } else {
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat()[0] : result?.message, type: "danger" });
      }
    });
  }

  const addButton = (
    <Button variant="outline" onClick={() => change([...rows, ...keyed([blank])])}>
      <Plus /> {t("master.grid.add")}
    </Button>
  );

  return (
    <div className="rounded-lg border border-border bg-surface">
      <div className="flex items-start justify-between gap-3 px-5 pt-4 pb-3">
        <div className="flex min-w-0 gap-3">
          <ListChecks className="mt-0.5 size-5 shrink-0 text-foreground-muted" />
          <div className="min-w-0">
            <h2 className="text-base font-semibold">{title}</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{hint}</p>
          </div>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          {dirty ? <span className="rounded-sm bg-warning-subtle px-2 py-0.5 text-xs font-semibold text-warning">{t("master.grid.unsaved")}</span> : null}
          {exportName ? (
            <Button variant="outline" onClick={() => exportCsv(exportName, columns, rows)}>
              <Download /> {t("master.grid.export")}
            </Button>
          ) : null}
          {canManage ? addButton : null}
        </div>
      </div>
      <EditGridTable columns={columns} rows={rows} set={(k, f, v) => change(rows.map((r) => (r._k === k ? { ...r, [f]: v } : r)))} remove={(k) => change(rows.filter((r) => r._k !== k))} canManage={canManage} empty={empty} removeLabel={t("master.grid.remove")} />
      <div className="flex flex-wrap items-center gap-3 border-t border-border p-4">
        {canManage ? addButton : null}
        <p className="mr-auto text-[13px] text-foreground-muted">{t(footerKey, { count: rows.length })}</p>
        {canManage ? (
          <>
            <Button
              variant="outline"
              onClick={() => {
                setRows(keyed(initial));
                setDirty(false);
              }}
              disabled={pending || !dirty}
            >
              <RotateCcw /> {t("master.grid.revert")}
            </Button>
            <Button onClick={submit} loading={pending} disabled={!dirty}>
              <Save /> {t("master.save")}
            </Button>
          </>
        ) : null}
      </div>
    </div>
  );
}

export { AccessGrid };
