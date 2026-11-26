"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { FileCheck2, History, Upload } from "lucide-react";
import { Card, CardBody, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { Modal } from "@/components/ui/modal";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Settings → Backup & Restore → Restore (AnnotechERP 03/04): upload a backup,
 * see what it would do table by table, then apply it — typing the company
 * code first. Nothing touches the company's data until that last step, and
 * the server takes a safety backup before it does.
 *
 * The file goes up through /api/settings-restores, not a server action: a
 * backup runs to many megabytes and server actions stop at one.
 */
function RestorePanel({ apply, pending: waiting }) {
  const t = useT("settings");
  const te = useT("erp");
  const router = useRouter();
  const toastManager = useToastManager();
  const [checked, setChecked] = useState(null);
  const [checking, startCheck] = useTransition();
  const [file, setFile] = useState(null);
  const [asking, setAsking] = useState(false);
  const [code, setCode] = useState("");
  const [error, setError] = useState(null);
  const [applying, startApply] = useTransition();
  const restore = checked ?? waiting ?? null;

  function submit(event) {
    event.preventDefault();
    if (!file) return;
    const data = new FormData();
    data.set("file", file);
    startCheck(async () => {
      const response = await fetch("/api/settings-restores", { method: "POST", body: data });
      const body = await response.json().catch(() => null);
      if (response.ok && body?.data) {
        setChecked(body.data);
        router.refresh();
      } else {
        toastManager.add({ title: body?.errors?.file?.[0] ?? body?.message ?? t("system.backup.upload_failed"), type: "danger" });
      }
    });
  }

  function confirmApply() {
    startApply(async () => {
      const result = await apply(restore.id, code);
      if (result?.status === "success") {
        setAsking(false);
        toastManager.add({ title: t("system.backup.restore_queued"), type: "success" });
        router.refresh();
      } else {
        setError(result?.errors?.confirm_code?.[0] ?? result?.message ?? null);
      }
    });
  }

  return (
    <div className="mb-4 flex flex-col gap-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Upload className="size-4" /> {t("system.backup.upload_title")}
          </CardTitle>
        </CardHeader>
        <CardBody className="flex flex-col gap-3">
          <Alert variant="warning">{t("system.backup.restore_rules")}</Alert>
          <form onSubmit={submit} className="flex flex-wrap items-center gap-3">
            <input
              type="file"
              accept=".zip,application/zip"
              onChange={(e) => setFile(e.target.files?.[0] ?? null)}
              className="block max-w-md flex-1 text-sm text-foreground file:mr-3 file:rounded-md file:border file:border-border file:bg-surface-muted file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-foreground hover:file:bg-surface"
            />
            <Button type="submit" disabled={!file || checking} loading={checking}>
              <FileCheck2 /> {t("system.backup.check")}
            </Button>
          </form>
        </CardBody>
      </Card>

      {restore ? (
        <Card>
          <CardHeader className="flex flex-wrap items-center justify-between gap-2">
            <CardTitle>{restore.original_name}</CardTitle>
            <span className="text-sm text-foreground-muted">
              {t("system.backup.taken")}: {restore.backup_created_at ? <FormattedDateTime value={restore.backup_created_at} /> : "—"}
            </span>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            {restore.status === "INVALID" ? (
              <Alert variant="danger">{restore.error}</Alert>
            ) : (
              <>
                <p className="text-sm text-foreground">
                  {t("system.backup.preview_summary", { tables: restore.tables, rows: restore.rows, fresh: restore.new, existing: restore.existing })}
                </p>
                <div className="max-h-80 overflow-auto rounded-md border border-border">
                  <table className="w-full text-sm">
                    <thead className="sticky top-0">
                      <tr className="bg-primary text-left text-primary-foreground">
                        <th className="px-4 py-2 font-semibold">{t("system.backup.table")}</th>
                        <th className="px-4 py-2 text-right font-semibold">{t("system.backup.in_file")}</th>
                        <th className="px-4 py-2 text-right font-semibold">{t("system.backup.fresh")}</th>
                        <th className="px-4 py-2 text-right font-semibold">{t("system.backup.existing")}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {restore.preview
                        .filter((row) => row.in_file > 0)
                        .map((row) => (
                          <tr key={row.table} className="border-b border-border last:border-0">
                            <td className="px-4 py-1.5 font-mono text-[13px]">
                              {row.table}
                              {row.restored === false ? <span className="ml-2 rounded-md bg-surface-muted px-1.5 py-0.5 font-sans text-xs text-foreground-muted">{t("system.backup.not_restored")}</span> : null}
                            </td>
                            <td className="px-4 py-1.5 text-right tabular-nums">{row.in_file}</td>
                            <td className="px-4 py-1.5 text-right tabular-nums">{row.new}</td>
                            <td className="px-4 py-1.5 text-right tabular-nums">{row.existing}</td>
                          </tr>
                        ))}
                    </tbody>
                  </table>
                </div>
              </>
            )}
          </CardBody>
          {restore.status === "PREVIEWED" ? (
            <CardFooter className="flex items-center justify-between gap-3">
              <p className="text-sm text-foreground-muted">{t("system.backup.apply_hint")}</p>
              <Button
                variant="danger"
                onClick={() => {
                  setCode("");
                  setError(null);
                  setAsking(true);
                }}
              >
                <History /> {t("system.backup.apply")}
              </Button>
            </CardFooter>
          ) : null}
        </Card>
      ) : null}

      <Modal open={asking} onOpenChange={setAsking} title={t("system.backup.apply_title")}>
        <div className="flex flex-col gap-3">
          <p className="text-sm text-foreground-muted">{t("system.backup.apply_confirm", { code: restore?.company_code ?? "" })}</p>
          <Input value={code} onChange={(e) => setCode(e.target.value)} placeholder={restore?.company_code ?? ""} aria-invalid={Boolean(error)} autoFocus />
          {error ? <p className="text-xs text-danger">{error}</p> : null}
          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => setAsking(false)} disabled={applying}>
              {te("cancel")}
            </Button>
            <Button variant="danger" onClick={confirmApply} loading={applying} disabled={code.trim() === ""}>
              {t("system.backup.apply")}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

export { RestorePanel };
