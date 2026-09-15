"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { Paperclip, Upload } from "lucide-react";
import { Button } from "@/components/ui/button";
import { FileInput } from "@/components/ui/file-input";
import { EmptyState } from "@/components/ui/empty-state";
import { useToastManager } from "@/components/ui/toast";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";

/**
 * Read-only on the web (a list at the bottom of the show page, no upload
 * form at all). The API already fully supports uploading
 * (`WorkOrderAttachmentApiController::store`), so this adds a real upload
 * form — evidence of the finished job, a vendor's note — genuinely new
 * rather than ported, same reasoning as the Costing tab and the Inventory
 * Transfer detail page.
 */
function AttachmentsTab({ attachments, isTerminal, action }) {
  const router = useRouter();
  const formRef = useRef(null);
  const [pending, setPending] = useState(false);
  const toastManager = useToastManager();

  async function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    setPending(true);
    const result = await action(formData);
    setPending(false);
    if (result?.status === "success") {
      toastManager.add({ title: "Uploaded", type: "success" });
      formRef.current?.reset();
      router.refresh();
    } else if (result?.status === "error") {
      toastManager.add({ title: result.message, type: "danger" });
    }
  }

  return (
    <div className="flex flex-col gap-4">
      {attachments.length === 0 ? (
        <EmptyState icon={<Paperclip />} title="No attachments yet." description="Photos of the finished job, a vendor's note, anything worth keeping with this record." />
      ) : (
        <div className="divide-y divide-border rounded-sm border border-border">
          {attachments.map((file) => (
            <a
              key={file.id}
              href={`/api/files/${file.id}/download`}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center justify-between gap-3 p-3 text-sm hover:bg-surface-muted"
            >
              <span className="text-foreground">{file.original_name}</span>
              <span className="shrink-0 text-xs text-foreground-muted">
                {file.human_size} · <FormattedDateTime value={file.created_at} mode="date" fallback="" />
              </span>
            </a>
          ))}
        </div>
      )}

      {!isTerminal ? (
        <form
          ref={formRef}
          onSubmit={handleSubmit}
          className="flex flex-col gap-3 rounded-sm border border-border bg-surface-muted/50 p-4 sm:flex-row sm:items-center"
        >
          <div className="flex-1">
            <FileInput name="file" required />
          </div>
          <Button type="submit" loading={pending}>
            <Upload /> Upload
          </Button>
        </form>
      ) : null}
    </div>
  );
}

export { AttachmentsTab };
