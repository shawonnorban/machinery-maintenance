"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { KeyRound } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/** API Keys & Webhooks → one key: Rotate secret, the new secret shown once. */
function ApiKeyActions({ client, rotate }) {
  const t = useT("api");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  const [secret, setSecret] = useState(null);

  function runRotate() {
    startTransition(async () => {
      const result = await rotate(client.id);
      if (result?.status === "success") {
        setSecret(result.secret);
        router.refresh();
      } else if (result?.status === "error") toastManager.add({ title: result.message, type: "danger" });
    });
  }

  return (
    <>
      <Button size="sm" variant="outline" loading={pending} disabled={!client.is_usable} onClick={runRotate}>
        <KeyRound /> {t("rotate_secret")}
      </Button>
      <Modal open={Boolean(secret)} onOpenChange={() => setSecret(null)} title={t("new_secret")}>
        <p className="mb-3 text-sm text-foreground-muted">{t("secret_shown_once")}</p>
        <div className="rounded-sm border border-border bg-surface-muted px-4 py-3 font-mono text-sm break-all">{secret}</div>
      </Modal>
    </>
  );
}

export { ApiKeyActions };
