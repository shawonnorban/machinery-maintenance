"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Select } from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/** Mirrors `reports::run.blade.php`'s own export form — same query the preview is showing, handed to `POST /report-jobs`. */
function ExportButton({ reportKey, query, formats, action }) {
  const t = useT("report");
  const [format, setFormat] = useState(formats[0] ?? "CSV");
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  function run() {
    startTransition(async () => {
      const result = await action(reportKey, query, format);
      if (result?.status === "success") {
        toastManager.add({
          title: result.job.status === "QUEUED" ? t("export_queued_toast") : t("export_ready_toast"),
          type: "success",
        });
        router.push("/reports/jobs");
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <div className="flex items-end gap-2">
      <div className="flex flex-col gap-1.5">
        <label className="text-sm font-medium text-foreground">{t("format")}</label>
        <div className="w-32">
          <Select value={format} onValueChange={setFormat} options={formats.map((f) => ({ value: f, label: f }))} />
        </div>
      </div>
      <Button variant="outline" loading={pending} onClick={run}>
        {t("export")}
      </Button>
    </div>
  );
}

export { ExportButton };
