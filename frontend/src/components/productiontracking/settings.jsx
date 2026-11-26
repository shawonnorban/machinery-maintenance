"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { ShieldCheck } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { useToastManager } from "@/components/ui/toast";
import { usePt, Panel, stamp } from "./common";
import { Field } from "./plans";

/*
 * Production Tracking → Settings (AnnotechERP 30/06): live refresh and the
 * stale-data warning, the plan approval workflow and the SMV method, the
 * achievement thresholds; what is configured; who may do what.
 */

function PtSettings({ settings, canManage, act }) {
  const p = usePt();
  const router = useRouter();
  const toast = useToastManager();
  const [v, setV] = useState(() => ({ refresh_seconds: settings.refresh_seconds, stale_seconds: settings.stale_seconds, on_track_pct: settings.on_track_pct, below_pct: settings.below_pct, approval_workflow: settings.approval_workflow, smv_method: settings.smv_method }));
  const [errors, setErrors] = useState({});
  const [pending, start] = useTransition();
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));
  const err = (k) => errors[k]?.[0];
  const save = () =>
    start(async () => {
      setErrors({});
      const res = await act("saveSettings", { ...v, refresh_seconds: Number(v.refresh_seconds), stale_seconds: Number(v.stale_seconds), on_track_pct: Number(v.on_track_pct), below_pct: Number(v.below_pct) });
      if (res?.status === "success") {
        toast.add({ title: p("toast.settings_saved"), type: "success" });
        router.refresh();
      } else {
        setErrors(res?.errors ?? {});
        toast.add({ title: res?.message ?? p("error"), type: "danger" });
      }
    });
  const unit = (u) => <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-foreground-muted">{u}</span>;
  const c = settings.configured;

  return (
    <div className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_24rem]">
      <div className="space-y-4">
        <Panel title={p("settings.live_title")} subtitle={p("settings.live_hint")}>
          <div className="grid grid-cols-1 gap-3 px-5 py-4 sm:grid-cols-2">
            <Field label={p("f.refresh_interval")} required error={err("refresh_seconds")} hint={p("settings.refresh_hint")}>
              <span className="relative block"><Input type="number" disabled={!canManage} value={v.refresh_seconds} onChange={(e) => set("refresh_seconds")(e.target.value)} />{unit(p("sec"))}</span>
            </Field>
            <Field label={p("f.stale_after")} required error={err("stale_seconds")} hint={p("settings.stale_hint")}>
              <span className="relative block"><Input type="number" disabled={!canManage} value={v.stale_seconds} onChange={(e) => set("stale_seconds")(e.target.value)} />{unit(p("sec"))}</span>
            </Field>
          </div>
        </Panel>
        <Panel title={p("settings.plan_title")} subtitle={p("settings.plan_hint")}>
          <div className="space-y-4 px-5 py-4">
            {[
              ["approval_workflow", "settings.approval"],
              ["smv_method", "settings.smv"],
            ].map(([k, key]) => (
              <label key={k} className="flex items-start gap-3">
                <Switch checked={Boolean(v[k])} disabled={!canManage} onCheckedChange={(on) => set(k)(on)} />
                <span>
                  <span className="font-semibold text-foreground">{p(`${key}_label`)}: </span>
                  <span className={cn("font-semibold", v[k] ? "text-success" : "text-foreground-muted")}>{p(v[k] ? "on" : "off")}</span>
                  <span className="block text-[13px] text-foreground-muted">{p(`${key}_hint`)}</span>
                </span>
              </label>
            ))}
          </div>
        </Panel>
        <Panel title={p("settings.thresholds_title")} subtitle={p("settings.thresholds_hint")}>
          <div className="grid grid-cols-1 gap-3 px-5 py-4 sm:grid-cols-2">
            <Field label={p("f.on_track")} required error={err("on_track_pct")}>
              <span className="relative block"><Input type="number" disabled={!canManage} value={v.on_track_pct} onChange={(e) => set("on_track_pct")(e.target.value)} />{unit("%")}</span>
            </Field>
            <Field label={p("f.below_target")} required error={err("below_pct")}>
              <span className="relative block"><Input type="number" disabled={!canManage} value={v.below_pct} onChange={(e) => set("below_pct")(e.target.value)} />{unit("%")}</span>
            </Field>
            <div className="flex flex-wrap gap-2 sm:col-span-2">
              {[
                ["ON_TRACK", "bg-success", `≥ ${v.on_track_pct}%`],
                ["ATTENTION", "bg-warning", `${v.below_pct}% – ${Number(v.on_track_pct) - 1}%`],
                ["BELOW", "bg-danger", `< ${v.below_pct}%`],
              ].map(([b, dot, range]) => (
                <span key={b} className="inline-flex flex-1 items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm">
                  <span className="inline-flex items-center gap-2 font-semibold"><span className={cn("size-2.5 rounded-full", dot)} /> {p(`band.${b}`)}</span>
                  <span className="text-foreground-muted">{range}</span>
                </span>
              ))}
            </div>
            <p className="text-[13px] text-foreground-muted sm:col-span-2">{p("settings.no_target_grey")}</p>
          </div>
        </Panel>
        {canManage ? (
          <div className="flex items-center gap-3">
            <Button loading={pending} onClick={save}>{p("btn.save_settings")}</Button>
            {settings.updated_at ? <span className="text-[13px] text-foreground-muted">{p("settings.last_changed", { at: stamp(settings.updated_at), by: settings.updated_by ?? "—" })}</span> : null}
          </div>
        ) : null}
      </div>
      <div className="space-y-4">
        <Panel title={p("settings.configured_title")} subtitle={p("settings.configured_hint")}>
          <div className="grid grid-cols-2 gap-2 px-5 py-4">
            {[
              ["sewing_lines", c.sewing_lines],
              ["finishing_lines", c.finishing_lines],
              ["capacities", c.capacities],
              ["plans_today", c.plans_today],
              ["tv_screens", c.tv_screens],
            ].map(([k, n]) => (
              <div key={k} className="rounded-md border border-border bg-surface-muted px-3 py-2">
                <p className="text-[13px] text-foreground-muted">{p(`settings.configured.${k}`)}</p>
                <p className="text-xl font-bold text-foreground">{n}</p>
              </div>
            ))}
          </div>
        </Panel>
        <Panel title={p("settings.users_title")} subtitle={p("settings.users_hint")} icon={ShieldCheck} action={<Link href="/access-control/role-menu" className={buttonVariants({ variant: "outline", size: "sm" })}>{p("btn.open_security")}</Link>}>
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-brand text-left text-brand-foreground">
                <th className="px-4 py-2 font-semibold">{p("settings.may")}</th>
                <th className="px-4 py-2 font-semibold">{p("settings.roles")}</th>
              </tr>
            </thead>
            <tbody>
              {["view", "prepare", "approve", "capacity", "tv", "entry", "settings"].map((k) => (
                <tr key={k} className="border-b border-border last:border-0">
                  <td className="px-4 py-2 font-semibold">{p(`settings.who.${k}`)}</td>
                  <td className="px-4 py-2 text-foreground-muted">{p(`settings.who.${k}_roles`)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Panel>
      </div>
    </div>
  );
}

export { PtSettings };
