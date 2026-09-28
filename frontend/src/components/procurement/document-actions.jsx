"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * The action panel of a procurement document's detail page.
 *
 * Which buttons render is decided by the `can_*` flags the API computes,
 * never by this component guessing from the status. Each button is a
 * config row: an action that needs a reason (reject, cancel, revise) opens
 * a small modal for it before running.
 *
 * @param {{ buttons: { key: string, label: string, variant?: string, run: (reason?: string) => Promise<any>, successTitle: string, reason?: { label: string, required?: boolean }, hint?: string }[] }} props
 */
function DocumentActions({ buttons, title }) {
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  const [asking, setAsking] = useState(null);
  const [reason, setReason] = useState("");

  const visible = buttons.filter(Boolean);

  if (visible.length === 0) return null;

  function run(button, withReason) {
    startTransition(async () => {
      const result = await button.run(withReason);
      if (result?.status === "success") {
        toastManager.add({ title: button.successTitle, type: "success" });
        setAsking(null);
        setReason("");
        if (result.redirect) {
          router.push(result.redirect);
        } else {
          router.refresh();
        }
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{title ?? tc("actions")}</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-2">
        {visible.map((button) => (
          <div key={button.key} className="flex flex-col gap-1">
            <Button
              variant={button.variant ?? "primary"}
              loading={pending && asking === null}
              onClick={() => (button.reason ? setAsking(button) : run(button))}
            >
              {button.label}
            </Button>
            {button.hint ? <p className="text-xs text-foreground-muted">{button.hint}</p> : null}
          </div>
        ))}
      </CardBody>

      <Modal
        open={asking !== null}
        onOpenChange={(open) => {
          if (!open) {
            setAsking(null);
            setReason("");
          }
        }}
        title={asking?.label ?? ""}
        footer={
          <>
            <Button variant="outline" onClick={() => setAsking(null)}>
              {tc("cancel")}
            </Button>
            <Button
              variant={asking?.variant === "danger" ? "danger" : "primary"}
              loading={pending}
              disabled={Boolean(asking?.reason?.required) && !reason.trim()}
              onClick={() => run(asking, reason)}
            >
              {asking?.label}
            </Button>
          </>
        }
      >
        {asking ? (
          <label className="flex flex-col gap-1 text-sm">
            <span className="text-foreground-muted">{asking.reason.label}</span>
            <Textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={3} maxLength={1000} />
          </label>
        ) : null}
      </Modal>
    </Card>
  );
}

export { DocumentActions };
