"use client";

import { useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { FileText, Trash2, Upload } from "lucide-react";
import { Button } from "@/components/ui/button";
import { FileInput } from "@/components/ui/file-input";
import { EmptyState } from "@/components/ui/empty-state";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";

/**
 * Mirrors `asset::assets.show.blade.php`'s documents card (SRS 8) — the
 * manual, the wiring diagram, the calibration certificate. Upload and
 * delete both need `asset.document.manage`; the API enforces that on
 * submit (a 403 there is what actually gates it, same as the QR
 * regenerate button), so both actions are always shown.
 */
function DocumentsTab({ documents, uploadAction, deleteAction, canManage }) {
  const router = useRouter();
  const formRef = useRef(null);
  const [uploading, setUploading] = useState(false);
  const [pendingDelete, setPendingDelete] = useState(null);
  const [deleting, setDeleting] = useState(false);
  const toastManager = useToastManager();

  async function handleUpload(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    setUploading(true);
    const result = await uploadAction(formData);
    setUploading(false);
    if (result?.status === "success") {
      toastManager.add({ title: "Document uploaded", type: "success" });
      formRef.current?.reset();
      router.refresh();
    } else if (result?.status === "error") {
      toastManager.add({ title: result.message, type: "danger" });
    }
  }

  async function handleDelete() {
    setDeleting(true);
    const result = await deleteAction(pendingDelete.id);
    setDeleting(false);
    if (result?.status === "success") {
      toastManager.add({ title: "Document removed", type: "success" });
      setPendingDelete(null);
      router.refresh();
    } else if (result?.status === "error") {
      toastManager.add({ title: result.message, type: "danger" });
    }
  }

  return (
    <div className="flex flex-col gap-4">
      {documents.length === 0 ? (
        <EmptyState
          icon={<FileText />}
          title="No documents yet."
          description="The manual, the wiring diagram, the calibration certificate — anything a technician standing at this machine would need."
        />
      ) : (
        <div className="divide-y divide-border rounded-sm border border-border">
          {documents.map((file) => (
            <div key={file.id} className="flex items-center justify-between gap-3 p-3 text-sm hover:bg-surface-muted">
              <a
                href={`/api/files/${file.id}/download`}
                target="_blank"
                rel="noopener noreferrer"
                className="flex-1 text-foreground hover:underline"
              >
                {file.original_name}
              </a>
              <span className="shrink-0 text-xs text-foreground-muted">
                {file.human_size} · <FormattedDateTime value={file.created_at} mode="date" fallback="" />
              </span>
              {canManage ? (
                <button
                  type="button"
                  onClick={() => setPendingDelete(file)}
                  className="shrink-0 text-foreground-muted hover:text-danger"
                  aria-label={`Remove ${file.original_name}`}
                >
                  <Trash2 className="size-4" />
                </button>
              ) : null}
            </div>
          ))}
        </div>
      )}

      {canManage ? (
        <form
          ref={formRef}
          onSubmit={handleUpload}
          className="flex flex-col gap-3 rounded-sm border border-border bg-surface-muted/50 p-4 sm:flex-row sm:items-center"
        >
          <div className="flex-1">
            <FileInput name="file" required />
          </div>
          <Button type="submit" loading={uploading}>
            <Upload /> Upload
          </Button>
        </form>
      ) : null}

      <ConfirmDialog
        open={pendingDelete !== null}
        onOpenChange={(open) => !open && setPendingDelete(null)}
        title="Remove this document?"
        description={pendingDelete ? `"${pendingDelete.original_name}" will be deleted permanently.` : ""}
        confirmLabel="Remove"
        destructive
        loading={deleting}
        onConfirm={handleDelete}
      />
    </div>
  );
}

export { DocumentsTab };
