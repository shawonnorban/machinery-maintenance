"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * The action panel for a transfer's current step — approve/reject belong to
 * the sending side while it's REQUESTED, dispatch once APPROVED, receive
 * once IN_TRANSIT. Which of these render is decided by the `can_*` flags
 * the API computes (`InventoryTransferApiController::detail`), not by this
 * component guessing from status alone.
 */
function TransferActions({ transfer, destinationBins, actions }) {
  const t = useT("inventory");
  const tc = useT("common");
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [rejecting, setRejecting] = useState(false);
  const [reason, setReason] = useState("");
  const [receiveBins, setReceiveBins] = useState(() =>
    Object.fromEntries(transfer.items.map((item) => [item.id, item.to_bin_id ?? ""])),
  );
  const toastManager = useToastManager();

  function run(promise, successTitle) {
    startTransition(async () => {
      const result = await promise;
      if (result?.status === "success") {
        toastManager.add({ title: successTitle, type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  if (!transfer.can_approve && !transfer.can_dispatch && !transfer.can_receive) {
    return null;
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{tc("actions")}</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-4">
        {transfer.can_approve ? (
          <div className="flex flex-col gap-3">
            <div className="flex gap-2">
              <Button variant="success" loading={pending} onClick={() => run(actions.approve(), t("transfer_approved"))}>
                {t("approve")}
              </Button>
              <Button variant="outline" onClick={() => setRejecting(true)}>
                {t("reject")}
              </Button>
            </div>
            {rejecting ? (
              <div className="flex items-center gap-2">
                <Input value={reason} onChange={(e) => setReason(e.target.value)} placeholder={t("reason")} className="max-w-xs" />
                <Button
                  variant="danger"
                  size="sm"
                  loading={pending}
                  disabled={!reason.trim()}
                  onClick={() => run(actions.reject(reason), t("transfer_rejected"))}
                >
                  {t("confirm_reject")}
                </Button>
              </div>
            ) : null}
          </div>
        ) : null}

        {transfer.can_dispatch ? (
          <Button loading={pending} onClick={() => run(actions.dispatch(), t("transfer_dispatched"))}>
            {t("dispatch_full_quantities")}
          </Button>
        ) : null}

        {transfer.can_receive ? (
          <div className="flex flex-col gap-3">
            <p className="text-sm text-foreground-muted">{t("destination_bin_needed_hint")}</p>
            {transfer.items.map((item) => (
              <div key={item.id} className="flex items-center gap-2">
                <span className="w-40 shrink-0 truncate text-sm">{item.spare_part?.part_number}</span>
                <Select
                  value={receiveBins[item.id] ?? ""}
                  onValueChange={(value) => setReceiveBins((prev) => ({ ...prev, [item.id]: value }))}
                  options={destinationBins.map((b) => ({ value: b.id, label: b.full_path }))}
                  placeholder={t("destination_bin_placeholder")}
                />
              </div>
            ))}
            <div>
              <Button
                loading={pending}
                disabled={transfer.items.some((item) => !receiveBins[item.id])}
                onClick={() => run(actions.receive(receiveBins), t("transfer_received"))}
              >
                {t("receive")}
              </Button>
            </div>
          </div>
        ) : null}
      </CardBody>
    </Card>
  );
}

export { TransferActions };
