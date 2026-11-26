"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Download, Paperclip, Upload } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { FileInput } from "@/components/ui/file-input";
import { useToastManager } from "@/components/ui/toast";
import { CompanyGrid } from "@/components/company/company-grid";
import { useT } from "@/lib/i18n";

/** The scanned copies of one document: list, download, upload. */
function DocumentFiles({ document, actions }) {
  const t = useT("company");
  const router = useRouter();
  const toastManager = useToastManager();
  const [open, setOpen] = useState(false);
  const [files, setFiles] = useState(null);
  const [pending, startTransition] = useTransition();

  function load() {
    startTransition(async () => {
      const result = await actions.listDocumentFiles(document.id);
      setFiles(result.status === "success" ? result.files : []);
    });
  }

  function upload(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    startTransition(async () => {
      const result = await actions.uploadDocumentFile(document.id, formData);
      if (result.status === "success") {
        toastManager.add({ title: t("uploaded"), type: "success" });
        load();
        router.refresh();
      } else {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <>
      <Button
        variant="ghost"
        size="sm"
        onClick={() => {
          setOpen(true);
          load();
        }}
        aria-label={t("files")}
      >
        <Paperclip /> {document.files_count ?? 0}
      </Button>
      <Modal open={open} onOpenChange={setOpen} title={`${t("files")} — ${document.title}`}>
        <div className="flex flex-col gap-4">
          {files === null ? null : files.length === 0 ? (
            <p className="text-sm text-foreground-muted">{t("no_files")}</p>
          ) : (
            <ul className="flex flex-col gap-2">
              {files.map((file) => (
                <li key={file.id} className="flex items-center justify-between gap-3 rounded-sm border border-border px-3 py-2 text-sm">
                  <span className="truncate">{file.original_name}</span>
                  <a href={`/api/files/${file.id}/download`} className="flex shrink-0 items-center gap-1 text-primary hover:underline">
                    <Download className="size-4" /> {file.human_size}
                  </a>
                </li>
              ))}
            </ul>
          )}
          <form onSubmit={upload} className="flex items-center gap-2">
            <FileInput name="file" required />
            <Button type="submit" size="sm" loading={pending}>
              <Upload /> {t("upload")}
            </Button>
          </form>
        </div>
      </Modal>
    </>
  );
}

/**
 * Company Documents (AnnotechERP 01/01): the shared grid over the document
 * API, with each row's scanned copies one click away.
 */
function CompanyDocuments({ rows, schema, actions }) {
  return (
    <CompanyGrid
      tabKey="company-documents"
      typeKey="company-documents"
      rows={rows}
      schema={schema}
      referenceOptions={{}}
      actions={{ createRow: actions.createDocument, saveRows: actions.saveDocuments, deleteRow: actions.deleteDocument }}
      rowActions={(row) => <DocumentFiles document={row} actions={actions} />}
    />
  );
}

export { CompanyDocuments };
