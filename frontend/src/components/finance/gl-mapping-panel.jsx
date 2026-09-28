"use client";

import { useState, useTransition } from "react";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Which two accounts each business event moves (Shared Systems spec §2).
 *
 * This screen is the point of the whole design: the chart of accounts has
 * not been signed off, so every event ships pointed at a placeholder and
 * finance re-points it here. Nothing in the code writes an account code,
 * so a real chart arriving is a data change and not a deployment.
 *
 * Switching an event off is allowed, and says plainly what it costs: the
 * business action still works — a receipt still puts stock on the shelf —
 * and only the books stay silent about it.
 */
function GlMappingPanel({ mappings, accounts, canManage, onSave }) {
  const t = useT("finance");
  const tc = useT("common");
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();
  const [draft, setDraft] = useState(() => Object.fromEntries(mappings.map((row) => [row.event_type, row])));

  const options = [
    { value: "", label: "—" },
    ...accounts.map((account) => ({ value: account.id, label: `${account.code} · ${account.name}` })),
  ];

  function set(eventType, key, value) {
    setDraft((current) => ({ ...current, [eventType]: { ...current[eventType], [key]: value } }));
  }

  function save(eventType) {
    const row = draft[eventType];

    startTransition(async () => {
      const result = await onSave(eventType, {
        debit_account_id: row.debit_account?.id ?? row.debit_account_id ?? null,
        credit_account_id: row.credit_account?.id ?? row.credit_account_id ?? null,
        secondary_credit_account_id: row.secondary_credit_account?.id ?? row.secondary_credit_account_id ?? null,
        active: row.active,
      });

      if (result?.status === "error") {
        toastManager.add({ type: "danger", title: result.message ?? tc("something_went_wrong") });
        return;
      }

      toastManager.add({ type: "success", title: t("mapping_saved", { event: t(`events.${eventType}`) }) });
    });
  }

  /** The accounts are stored as objects but edited as ids. */
  function accountId(row, key) {
    return row[key]?.id ?? row[`${key}_id`] ?? "";
  }

  function setAccount(eventType, key, value) {
    setDraft((current) => ({
      ...current,
      [eventType]: { ...current[eventType], [key]: null, [`${key}_id`]: value },
    }));
  }

  return (
    <div className="flex flex-col gap-4">
      <Alert variant="info">{t("placeholder_notice")}</Alert>

      {mappings.map((mapping) => {
        const row = draft[mapping.event_type];

        return (
          <Card key={mapping.event_type}>
            <CardHeader>
              <CardTitle>{t(`events.${mapping.event_type}`)}</CardTitle>
              <div className="flex items-center gap-2">
                {/* How much this mapping has actually carried. */}
                <Badge variant="neutral">{t("entries_posted", { count: mapping.entries })}</Badge>
                {row.postable ? null : <Badge variant="warning">{t("not_postable")}</Badge>}
              </div>
            </CardHeader>
            <CardBody className="flex flex-col gap-3">
              <p className="text-xs text-foreground-muted">{t(`event_hints.${mapping.event_type}`)}</p>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <label className="flex flex-col gap-1.5 text-sm">
                  <span className="font-medium">{t("debit_account")}</span>
                  <Select
                    options={options}
                    value={accountId(row, "debit_account")}
                    onValueChange={(value) => setAccount(mapping.event_type, "debit_account", value)}
                    disabled={!canManage}
                  />
                </label>

                <label className="flex flex-col gap-1.5 text-sm">
                  <span className="font-medium">{t("credit_account")}</span>
                  <Select
                    options={options}
                    value={accountId(row, "credit_account")}
                    onValueChange={(value) => setAccount(mapping.event_type, "credit_account", value)}
                    disabled={!canManage}
                  />
                </label>

                {/* Settlement is the only event that credits two accounts. */}
                {mapping.event_type === "INVOICE_SETTLEMENT" ? (
                  <label className="flex flex-col gap-1.5 text-sm">
                    <span className="font-medium">{t("secondary_credit_account")}</span>
                    <Select
                      options={options}
                      value={accountId(row, "secondary_credit_account")}
                      onValueChange={(value) => setAccount(mapping.event_type, "secondary_credit_account", value)}
                      disabled={!canManage}
                    />
                  </label>
                ) : null}
              </div>

              {canManage ? (
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3">
                  <label className="flex items-center gap-2 text-sm">
                    <Switch
                      checked={Boolean(row.active)}
                      onCheckedChange={(value) => set(mapping.event_type, "active", value)}
                    />
                    <span>{t("post_this_event")}</span>
                  </label>
                  <Button size="sm" onClick={() => save(mapping.event_type)} disabled={pending}>
                    {tc("save")}
                  </Button>
                </div>
              ) : null}
            </CardBody>
          </Card>
        );
      })}
    </div>
  );
}

export { GlMappingPanel };
