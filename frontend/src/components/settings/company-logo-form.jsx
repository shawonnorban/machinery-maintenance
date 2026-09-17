"use client";

import { startTransition, useActionState, useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { FileInput } from "@/components/ui/file-input";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { Wrench } from "lucide-react";
import { useT } from "@/lib/i18n";

/**
 * The company's own branding mark, shown in the sidebar in place of the
 * default wrench icon once uploaded. A logo is neither business data nor a
 * work-order attachment, so it lives on `companies.logo_path` and its own
 * small upload endpoint rather than the generic settings resolver
 * (`SettingsApiController::updateLogo`'s own docblock) or the file-
 * attachment system.
 */
function CompanyLogoForm({ logoUrl, actions }) {
  const t = useT("settings");
  const [state, dispatch, pending] = useActionState(actions.upload, null);
  const [removing, startRemoving] = useTransition();
  const [preview, setPreview] = useState(null);
  const router = useRouter();
  const toastManager = useToastManager();

  // The chosen file previews immediately, before the upload round-trip —
  // picking the wrong image and only finding out after a save/reload is
  // the exact friction a preview exists to remove. Revoked on every change
  // and on unmount so a picked-then-abandoned file doesn't leak memory.
  useEffect(() => {
    if (!preview) return undefined;
    return () => URL.revokeObjectURL(preview);
  }, [preview]);

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: t("logo_updated"), type: "success" });
      // queueMicrotask rather than a bare setState call, same pattern
      // ThemeProvider/SidebarNav already use for a post-render state update
      // triggered by an effect — avoids the react-hooks/set-state-in-effect
      // lint rule without an eslint-disable comment.
      queueMicrotask(() => setPreview(null));
      // The sidebar's own logo mark is read from `/auth/me` in the layout
      // above this page — `revalidatePath` inside the action marks that
      // stale, but this client still needs to actually ask for the fresh
      // RSC payload, which is what shows the new logo without a manual
      // reload.
      router.refresh();
    } else if (state?.status === "error" && !state.errors) {
      toastManager.add({ title: state.message, type: "danger" });
    }
    // toastManager/router are deliberately excluded: neither is a stable
    // reference across renders here, so including them re-fires this
    // effect (and its own router.refresh()) every render once state first
    // becomes "success" — the same infinite-loop hazard documented on
    // every other form in this app that follows this pattern.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  function handleFileChange(event) {
    const file = event.target.files?.[0];
    setPreview(file ? URL.createObjectURL(file) : null);
  }

  function remove() {
    startRemoving(async () => {
      const result = await actions.remove();
      if (result?.status === "success") {
        toastManager.add({ title: t("logo_removed"), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    startTransition(() => dispatch(formData));
  }

  const shown = preview ?? logoUrl;

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("company_logo")}</CardTitle>
      </CardHeader>
      <CardBody>
        <form onSubmit={handleSubmit} className="flex flex-col gap-5 sm:flex-row sm:items-center">
          {/* `w-40`, not square: most real logos are landscape wordmarks, and
              `object-contain` never crops — a tall, narrow mark just leaves
              side padding instead of being force-squeezed. */}
          <div className="flex h-20 w-40 shrink-0 items-center justify-center overflow-hidden rounded-sm border border-dashed border-border-strong bg-surface-muted p-2">
            {shown ? (
              // eslint-disable-next-line @next/next/no-img-element -- a tenant-hosted, on-disk (or locally previewed) logo, not an optimizable remote asset worth Next's image pipeline
              <img src={shown} alt={t("company_logo")} className="max-h-full max-w-full object-contain" />
            ) : (
              <div className="flex flex-col items-center gap-1 text-foreground-muted">
                <Wrench className="size-6" />
                <span className="text-[11px]">{t("no_logo_yet")}</span>
              </div>
            )}
          </div>

          <div className="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-end">
            <div className="w-full sm:max-w-xs">
              <FormField label={t("replace_logo")} helperText={t("logo_hint")} error={state?.errors?.logo?.[0]}>
                {(fieldProps) => (
                  <FileInput {...fieldProps} name="logo" accept="image/png,image/jpeg,image/webp" onChange={handleFileChange} required />
                )}
              </FormField>
            </div>

            <div className="flex shrink-0 gap-2 sm:ml-auto">
              {logoUrl ? (
                <Button type="button" variant="outline" loading={removing} onClick={remove}>
                  {t("remove")}
                </Button>
              ) : null}
              <Button type="submit" loading={pending}>
                {t("upload")}
              </Button>
            </div>
          </div>
        </form>
      </CardBody>
    </Card>
  );
}

export { CompanyLogoForm };
