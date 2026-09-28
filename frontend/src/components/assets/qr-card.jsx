"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { RefreshCw } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Button, buttonVariants } from "@/components/ui/button";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { cn } from "@/lib/utils";
import { useT } from "@/lib/i18n";

/**
 * Mirrors `asset::assets.show.blade.php`'s "QR token" card — the scannable
 * SVG (trusted, server-rendered by `QrCodeRenderer`, not user input), the
 * raw code, a link to the label sheet, and a regenerate action gated the
 * same way the reset-meter form is (docs/12-Stack-Migration-
 * Implementation-Plan.md Phase C §5): the button is always shown, and a
 * 403 on submit is what actually enforces `asset.qr.regenerate`, not a
 * client-side guess.
 */
function QrCard({ assetId, qr, regenerateAction }) {
  const t = useT("asset");
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [pending, startTransition] = useTransition();
  const router = useRouter();
  const toastManager = useToastManager();

  function regenerate() {
    startTransition(async () => {
      const result = await regenerateAction();
      if (result?.status === "success") {
        toastManager.add({ title: t("qr_regenerated_toast"), description: t("qr_regenerated_desc"), type: "success" });
        setConfirmOpen(false);
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("qr_token")}</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col items-center gap-3 text-center">
        <div className="size-36 shrink-0 [&_svg]:size-full" dangerouslySetInnerHTML={{ __html: qr.svg }} />
        <code className="text-xs text-foreground-muted">{qr.qr_code}</code>

        <div className="mt-2 flex w-full flex-col gap-2">
          <Link
            href={`/assets/labels?ids[]=${assetId}`}
            className={cn(buttonVariants({ variant: "outline", size: "sm" }))}
          >
            {t("print_label")}
          </Link>
          <Button variant="outline" size="sm" onClick={() => setConfirmOpen(true)}>
            <RefreshCw /> {t("regenerate_qr")}
          </Button>
        </div>
      </CardBody>

      <ConfirmDialog
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        title={t("regenerate_qr_confirm_title")}
        description={t("regenerate_qr_confirm_desc")}
        confirmLabel={t("regenerate")}
        destructive
        loading={pending}
        onConfirm={regenerate}
      />
    </Card>
  );
}

export { QrCard };
