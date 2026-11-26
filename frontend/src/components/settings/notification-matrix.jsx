"use client";

import { useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Lock, RotateCcw, Save, Search } from "lucide-react";
import { Card, CardBody, CardFooter } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const CHANNELS = ["email", "sms", "whatsapp"];

function ticksOf(rows) {
  return Object.fromEntries(rows.map((r) => [r.event_type, { email: r.email, sms: r.sms, whatsapp: r.whatsapp, locked: r.locked }]));
}

/** An event's name in the reader's language; the code, made readable, when none is written. */
function useEventLabel() {
  const tn = useT("notification");
  return (event) => {
    const key = `event_${event.toLowerCase()}`;
    const label = tn(key);
    return label === key ? event.charAt(0) + event.slice(1).toLowerCase().replaceAll("_", " ") : label;
  };
}

/**
 * Settings → Notification Preferences (AnnotechERP 03/04): the company's
 * default channels per event. In-app is always on; Locked makes the company's
 * choice apply to everybody, over their own preference. Saved as a whole.
 */
function NotificationMatrix({ rows, save }) {
  const t = useT("settings");
  const router = useRouter();
  const toastManager = useToastManager();
  const eventLabel = useEventLabel();
  const [ticks, setTicks] = useState(() => ticksOf(rows));
  const [query, setQuery] = useState("");
  const [pending, startTransition] = useTransition();

  const visible = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return needle ? rows.filter((r) => `${r.event_type} ${eventLabel(r.event_type)}`.toLowerCase().includes(needle)) : rows;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rows, query]);

  const toggle = (event, field, checked) => setTicks((current) => ({ ...current, [event]: { ...current[event], [field]: checked } }));

  function submit() {
    startTransition(async () => {
      const result = await save(ticks);
      if (result?.status === "success") {
        toastManager.add({ title: t("hub.saved"), type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardBody className="flex flex-col gap-3">
        <Alert variant="info">{t("system.notify.hint")}</Alert>
        <div className="relative max-w-sm">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t("system.notify.search")} className="pl-9" />
        </div>
        <div className="overflow-x-auto rounded-md border border-border">
          <table className="w-full min-w-max text-sm">
            <thead>
              <tr className="bg-primary text-left text-primary-foreground">
                <th className="px-4 py-2.5 font-semibold">{t("system.notify.event")}</th>
                <th className="px-4 py-2.5 text-center font-semibold">{t("system.notify.in_app")}</th>
                {CHANNELS.map((channel) => (
                  <th key={channel} className="px-4 py-2.5 text-center font-semibold">
                    {t(`system.notify.${channel}`)}
                  </th>
                ))}
                <th className="px-4 py-2.5 text-center font-semibold">{t("system.notify.locked")}</th>
              </tr>
            </thead>
            <tbody>
              {visible.map((row) => {
                const tick = ticks[row.event_type];
                return (
                  <tr key={row.event_type} className="border-b border-border last:border-0 hover:bg-surface-muted/50">
                    <td className="px-4 py-2">
                      <div className="font-medium text-foreground">{eventLabel(row.event_type)}</div>
                      <div className="font-mono text-xs text-foreground-muted">{row.event_type}</div>
                    </td>
                    <td className="px-4 py-2 text-center">
                      <Checkbox checked disabled aria-label={t("system.notify.in_app")} />
                    </td>
                    {CHANNELS.map((channel) => (
                      <td key={channel} className="px-4 py-2 text-center">
                        <Checkbox
                          checked={Boolean(tick[channel])}
                          onCheckedChange={(c) => toggle(row.event_type, channel, c === true)}
                          aria-label={t(`system.notify.${channel}`)}
                        />
                      </td>
                    ))}
                    <td className="px-4 py-2 text-center">
                      <label className="inline-flex items-center gap-1.5">
                        <Checkbox checked={Boolean(tick.locked)} onCheckedChange={(c) => toggle(row.event_type, "locked", c === true)} aria-label={t("system.notify.locked")} />
                        {tick.locked ? <Lock className="size-3.5 text-warning" /> : null}
                      </label>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </CardBody>
      <CardFooter className="flex items-center justify-between gap-3">
        <p className="text-sm text-foreground-muted">{t("system.notify.footer")}</p>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => setTicks(ticksOf(rows))} disabled={pending}>
            <RotateCcw /> {t("system.reset")}
          </Button>
          <Button onClick={submit} disabled={pending}>
            <Save /> {t("system.notify.save")}
          </Button>
        </div>
      </CardFooter>
    </Card>
  );
}

export { NotificationMatrix, useEventLabel };
