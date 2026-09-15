"use client";

import Link from "next/link";
import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button, buttonVariants } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { cn } from "@/lib/utils";

/** Mirrors `templates/show.blade.php`'s own header actions — edit, start a revision, publish a draft. */
function TemplateHeaderActions({ template, actions }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const toastManager = useToastManager();

  const version = template.version;

  function runDraft() {
    startTransition(async () => {
      const result = await actions.startDraft(template.id);
      if (result?.status === "success") {
        toastManager.add({ title: "Revision started", type: "success" });
        router.push(`/maintenance/templates/${template.id}?version=${result.versionId}`);
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  function runPublish() {
    startTransition(async () => {
      const result = await actions.publishVersion(template.id, version.id);
      if (result?.status === "success") {
        toastManager.add({ title: "Version published", type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  if (!template.is_editable) {
    return null;
  }

  return (
    <div className="flex gap-2">
      <Link href={`/maintenance/templates/${template.id}/edit`} className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
        Edit
      </Link>
      {version?.status === "PUBLISHED" ? (
        <Button size="sm" variant="outline" loading={pending} onClick={runDraft}>
          Start revision
        </Button>
      ) : null}
      {version?.status === "DRAFT" ? (
        <Button size="sm" loading={pending} onClick={runPublish}>
          Publish
        </Button>
      ) : null}
    </div>
  );
}

export { TemplateHeaderActions };
