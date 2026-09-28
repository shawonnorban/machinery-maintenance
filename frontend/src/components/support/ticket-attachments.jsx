"use client";

import { useState } from "react";
import { FileText, Download } from "lucide-react";
import { EmptyState } from "@/components/ui/empty-state";
import { Modal } from "@/components/ui/modal";
import { FormattedDateTime } from "@/components/ui/formatted-date-time";
import { useT } from "@/lib/i18n";

const THUMBNAIL_SIZE = 200;

/**
 * Read-only — uploading now happens from the reply form itself (see
 * `ReplyForm`), so this only ever displays what's already there. An image
 * gets an actual thumbnail (clicking it opens the full picture in a modal)
 * rather than the same bare filename row every other attachment gets — a
 * screenshot is the most common thing a customer attaches to a ticket, and
 * a filename alone tells nobody what's in it.
 */
function TicketAttachments({ attachments }) {
  const t = useT("support");
  const [preview, setPreview] = useState(null);

  return (
    <div className="flex flex-col gap-3">
      <h3 className="text-sm font-medium text-foreground">{t("attachments")}</h3>

      {attachments.length === 0 ? (
        <EmptyState icon={<FileText />} title={t("no_attachments")} />
      ) : (
        <div className="flex flex-wrap gap-3">
          {attachments.map((file) =>
            file.is_image ? (
              <button
                key={file.id}
                type="button"
                onClick={() => setPreview(file)}
                className="group overflow-hidden rounded-sm border border-border text-left transition-shadow duration-150 hover:shadow-sm"
                style={{ width: THUMBNAIL_SIZE }}
              >
                <span className="block overflow-hidden bg-surface-muted" style={{ width: THUMBNAIL_SIZE, height: THUMBNAIL_SIZE }}>
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

      <Modal open={preview !== null} onOpenChange={() => setPreview(null)} title={preview?.original_name ?? ""} className="max-w-3xl">
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

export { TicketAttachments };
