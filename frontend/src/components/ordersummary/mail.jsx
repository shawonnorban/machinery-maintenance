"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Clock, Plus, Save, Send, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * Order Summary → Mail Schedule (AnnotechERP 32): the edit mail on or off,
 * the times of day it goes (the factory's clock, Asia/Dhaka), the mail
 * service it goes through, when it last went and how many edits wait; Send
 * now sends what is waiting at once. Below it, the instant mail (user,
 * 2026-11-22): JOB No / PO Number / Shipment ID added or changed is mailed
 * at once — on or off, and which columns.
 */

function MailSchedule({ data, act }) {
  const t = useT("ordersummary");
  const router = useRouter();
  const toast = useToastManager();
  const [enabled, setEnabled] = useState(data.enabled);
  const [times, setTimes] = useState(data.times.length ? data.times : ["10:00", "18:00"]);
  const [service, setService] = useState(data.mail_service_id ?? "");
  const [instant, setInstant] = useState(data.instant_enabled ?? true);
  const [instantCols, setInstantCols] = useState(data.instant_columns ?? []);
  const label = (key) => data.columns?.find((c) => c.value === key)?.label ?? key;
  const [pending, start] = useTransition();

  const call = (promise, ok) =>
    start(async () => {
      const res = await promise;
      if (res?.status === "success") {
        toast.add({ title: ok(res.data), type: "success" });
        router.refresh();
      } else {
        const first = res?.errors ? Object.values(res.errors)[0]?.[0] : null;
        toast.add({ title: first ?? res?.message ?? t("sheet.error"), type: "danger" });
      }
    });

  return (
    <div className="flex max-w-3xl flex-col gap-4 text-[13px]">
      <section className="rounded-lg border border-border bg-surface p-5">
        <label className="flex items-center gap-3 font-semibold">
          <Switch checked={enabled} onCheckedChange={setEnabled} aria-label={t("mailset.enabled")} />
          {t("mailset.enabled")}
        </label>
        <p className="mt-1 text-foreground-muted">{t("mailset.explain")}</p>

        <div className="mt-4 font-semibold">{t("mailset.times")}</div>
        <div className="mt-2 flex flex-wrap items-center gap-2">
          {times.map((tm, i) => (
            <span key={i} className="flex items-center gap-1 rounded-md border border-border px-2 py-1">
              <Clock className="size-3.5 text-foreground-muted" />
              <Input type="time" className="h-7 w-28 border-0 p-0" value={tm} onChange={(e) => setTimes((all) => all.map((x, j) => (j === i ? e.target.value : x)))} aria-label={t("mailset.time")} />
              <button type="button" onClick={() => setTimes((all) => all.filter((_, j) => j !== i))} aria-label={t("mailset.remove_time")} className="text-foreground-subtle hover:text-danger"><X className="size-3.5" /></button>
            </span>
          ))}
          <Button type="button" size="sm" variant="outline" onClick={() => setTimes((all) => [...all, "12:00"])}><Plus /> {t("mailset.add_time")}</Button>
        </div>

        <label className="mt-4 flex max-w-md flex-col gap-1 font-semibold">
          {t("mailset.service")}
          <select className="h-9 rounded-md border border-border bg-surface px-2" value={service} onChange={(e) => setService(e.target.value)}>
            <option value="">{t("mailset.default_service")}</option>
            {data.services.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
          </select>
          {data.services.length === 0 ? <span className="font-normal text-danger">{t("mailset.no_services")}</span> : null}
        </label>

        <div className="mt-5 flex gap-2">
          <Button disabled={pending} onClick={() => call(act("saveMail", { enabled, times: times.filter(Boolean), mail_service_id: service || null, instant_enabled: instant, instant_columns: instantCols }), () => t("mailset.saved"))}><Save /> {t("sheet.save")}</Button>
          <Button variant="outline" disabled={pending || !data.waiting} onClick={() => call(act("sendNow"), (d) => t("mailset.sent", { sent: d.sent, changes: d.changes }))}><Send /> {t("mailset.send_now")}</Button>
        </div>
      </section>

      <section className="rounded-lg border border-border bg-surface p-5">
        <label className="flex items-center gap-3 font-semibold">
          <Switch checked={instant} onCheckedChange={setInstant} aria-label={t("mailset.instant")} />
          {t("mailset.instant")}
        </label>
        <p className="mt-1 text-foreground-muted">{t("mailset.instant_explain")}</p>
        <div className="mt-4 font-semibold">{t("mailset.instant_columns")}</div>
        <div className="mt-2 flex flex-wrap items-center gap-2">
          {instantCols.map((k) => (
            <span key={k} className="flex items-center gap-1 rounded-md border border-border bg-brand-subtle px-2 py-1 font-medium">
              {label(k)}
              <button type="button" onClick={() => setInstantCols((all) => all.filter((x) => x !== k))} aria-label={t("mailset.remove_time")} className="text-foreground-subtle hover:text-danger"><X className="size-3.5" /></button>
            </span>
          ))}
          <select className="h-8 rounded-md border border-border bg-surface px-2" value="" onChange={(e) => e.target.value && setInstantCols((all) => [...new Set([...all, e.target.value])])} aria-label={t("mailset.instant_pick")}>
            <option value="">{t("mailset.instant_pick")}</option>
            {(data.columns ?? []).filter((c) => !instantCols.includes(c.value)).map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
          </select>
        </div>
        <div className="mt-5">
          <Button disabled={pending} onClick={() => call(act("saveMail", { enabled, times: times.filter(Boolean), mail_service_id: service || null, instant_enabled: instant, instant_columns: instantCols }), () => t("mailset.saved"))}><Save /> {t("sheet.save")}</Button>
        </div>
      </section>

      <section className="grid gap-3 sm:grid-cols-2">
        <div className="rounded-lg border border-border bg-surface p-4"><div className="text-2xl font-bold tabular-nums">{data.waiting}</div><div className="text-foreground-muted">{t("mailset.waiting")}</div></div>
        <div className="rounded-lg border border-border bg-surface p-4"><div className="text-[15px] font-bold">{data.last_sent_at ? new Date(data.last_sent_at).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", dateStyle: "medium", timeStyle: "short", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : "—"}</div><div className="text-foreground-muted">{t("mailset.last_sent")}</div></div>
      </section>
    </div>
  );
}

export { MailSchedule };
