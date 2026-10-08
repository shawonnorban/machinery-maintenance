"use client";

import { useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { Download, FileText, Upload } from "lucide-react";
import { Button } from "@/components/ui/button";
import { FileInput } from "@/components/ui/file-input";
import { Modal } from "@/components/ui/modal";
import { EmptyState } from "@/components/ui/empty-state";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const THUMBNAIL_SIZE = 160;

/**
 * Evidence photos for a roll or a four-point sheet (Inventory spec §4.3).
 *
 * Mirrors `TicketAttachments` (gallery, image thumbnails, a modal preview)
 * combined with `AttachmentsTab`'s own upload form — a shade or defect
 * dispute is usually settled with a picture, not with the words
 * describing it, and until this the inspection screens had nowhere to
 * put one.
 */
function InspectionPhotos({ photos, canUpload, action }) {
  const t = useT("inspection");
  const router = useRouter();
  const toastManager = useToastManager();
  const formRef = useRef(null);
  const [pending, setPending] = useState(false);
  const [preview, setPreview] = useState(null);

  async function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    setPending(true);
    const result = await action(formData);
    setPending(false);

    if (result?.status === "success") {
      toastManager.add({ title: t("uploaded"), type: "success" });
      formRef.current?.reset();
      router.refresh();
    } else if (result?.status === "error") {
      toastManager.add({ title: result.message, type: "danger" });
    }
  }

  return (
    <div className="flex flex-col gap-3">
      {photos.length === 0 ? (
        <EmptyState icon={<FileText />} title={t("no_photos")} />
      ) : (
        <div className="flex flex-wrap gap-3">
          {photos.map((file) =>
            file.is_image ? (
              <button
                key={file.id}
                type="button"
                onClick={() => setPreview(file)}
                className="group overflow-hidden rounded-sm border border-border text-left transition-shadow duration-150 hover:shadow-sm"
                style={{ width: THUMBNAIL_SIZE }}
              >
                <span
                  className="block overflow-hidden bg-surface-muted"
                  style={{ width: THUMBNAIL_SIZE, height: THUMBNAIL_SIZE }}
                >
                  {/* eslint-disable-next-line @next/next/no-img-element -- a private, signed-URL-backed file, not an optimizable static asset */}
                  <img
                    src={`/api/files/${file.id}/download`}
                    alt={file.original_name}
                    className="size-full object-cover transition-transform duration-150 group-hover:scale-105"
                  />
                </span>
                <span className="block truncate px-2 py-1.5 text-xs text-foreground-muted">{file.original_name}</span>
              </button>
            ) : (
              <a
                key={file.id}
                href={`/api/files/${file.id}/download`}
                target="_blank"
                rel="noopener noreferrer"
                className="flex flex-col items-center justify-center gap-2 rounded-sm border border-border p-3 text-center hover:bg-surface-muted"
                style={{ width: THUMBNAIL_SIZE, height: THUMBNAIL_SIZE }}
              >
                <FileText className="size-8 text-foreground-muted" />
                <span className="line-clamp-2 w-full break-words text-xs text-foreground">{file.original_name}</span>
                <span className="text-xs text-foreground-muted">{file.human_size}</span>
              </a>
            ),
          )}
        </div>
      )}

      {canUpload ? (
        <form
          ref={formRef}
          onSubmit={handleSubmit}
          className="flex flex-col gap-3 rounded-sm border border-border bg-surface-muted/50 p-3 sm:flex-row sm:items-center"
        >
          <div className="flex-1">
            <FileInput name="file" accept="image/*,.pdf" required />
          </div>
          <Button type="submit" size="sm" loading={pending}>
            <Upload /> {t("add_photo")}
          </Button>
        </form>
      ) : null}

      <Modal
        open={preview !== null}
        onOpenChange={() => setPreview(null)}
        title={preview?.original_name ?? ""}
        className="max-w-3xl"
      >
        {preview ? (
          <div className="flex flex-col gap-3">
            {/* eslint-disable-next-line @next/next/no-img-element -- see note above */}
            <img
              src={`/api/files/${preview.id}/download`}
              alt={preview.original_name}
              className="max-h-[70vh] w-full rounded-sm object-contain"
            />
            <div className="flex items-center justify-between text-xs text-foreground-muted">
              <span>
                {preview.human_size} · <FormattedDateTime value={preview.created_at} mode="date" fallback="" />
              </span>
              <a
                href={`/api/files/${preview.id}/download`}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 font-medium text-brand hover:underline"
              >
                <Download className="size-4" /> {t("download")}
              </a>
            </div>
          </div>
        ) : null}
      </Modal>
    </div>
  );
}

export { InspectionPhotos };
