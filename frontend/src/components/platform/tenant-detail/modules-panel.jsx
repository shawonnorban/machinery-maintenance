"use client";

import { useState, useTransition } from "react";
import { Boxes, Lock } from "lucide-react";
import { PanelHead } from "@/components/platform/ui";
import { cn } from "@/lib/utils";
import { Card, CardBody } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { useToastManager } from "@/components/ui/toast";
import { clockFormat } from "@/lib/clock";

const STAMP = clockFormat({ day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka" });

const SECTION_LABEL = {
  OVERVIEW: "Overview", SYSTEM_ADMINISTRATION: "System Administration", HUMAN_RESOURCE: "Human Resource", SALES_MERCHANDISING: "Sales & Merchandising",
  PRE_BUDGET: "Pre-Budget", PLANNING: "Planning", PRODUCTION: "Production", INVENTORY_STORES: "Inventory & Stores", FINANCE_ACCOUNTING: "Finance & Accounting",
  COMMERCIAL_EXPORT: "Commercial & Export", INDUSTRIAL_ENGINEERING: "Industrial Engineering", LABORATORY: "Laboratory", MAINTENANCE: "Maintenance",
};

/**
 * Every module of the company app (LicensedModules on the backend), by
 * sidebar section, and whether this customer has been given each. Core
 * modules (admin, chat, HR) are always on. Off = the module leaves the
 * company's sidebar and its screens show "not enabled"; its data is kept.
 */
function ModulesPanel({ modules: initial = [], setAction }) {
  // Kept here and replaced by each switch's answer, so the rows change even when the page's refresh misses (tenant-tabs).
  const [modules, setModules] = useState(initial);
  const [pending, start] = useTransition();
  const [confirm, setConfirm] = useState(null); // { codes, label }
  const toast = useToastManager();
  const sections = [...new Set(modules.map((m) => m.section))];
  const sellable = modules.filter((m) => !m.core);
  const onCount = sellable.filter((m) => m.enabled).length;

  const set = (codes, enabled) =>
    start(async () => {
      const res = await setAction(codes, enabled);
      if (res?.status === "error") toast.add({ title: res.message, type: "danger" });
      else {
        if (Array.isArray(res?.modules)) setModules(res.modules);
        const what = codes.length === 1 ? modules.find((m) => m.code === codes[0])?.name : `${codes.length} modules`;
        toast.add({ title: `${what} ${enabled ? "switched on" : "switched off"}`, type: "success" });
      }
      setConfirm(null);
    });
  // Switching on is harmless; switching off hides screens people may be using — confirm that.
  const ask = (codes, enabled, label) => (enabled ? set(codes, true) : setConfirm({ codes, label }));

  return (
    <Card>
      <PanelHead
        title="Modules"
        subtitle={`${onCount} of ${sellable.length} modules on. A company sees a module only while it is on; switching it off hides its screens and keeps its data. Core modules are always on.`}
        icon={Boxes}
        action={
          <>
            <Button size="sm" variant="outline" loading={pending} disabled={onCount === sellable.length} onClick={() => ask(sellable.filter((m) => !m.enabled).map((m) => m.code), true)}>
              Switch all on
            </Button>
            <Button size="sm" variant="outline" loading={pending} disabled={onCount === 0} onClick={() => ask(sellable.filter((m) => m.enabled).map((m) => m.code), false, "every module except the core ones")}>
              Switch all off
            </Button>
          </>
        }
      />
      <CardBody className="flex flex-col gap-0 p-0">
        {sections.map((section) => (
          <section key={section} className="border-t border-border first:border-t-0">
            <h3 className="bg-surface-muted px-5 py-2 text-[11px] font-bold tracking-wide text-brand uppercase">{SECTION_LABEL[section] ?? section}</h3>
            <div className="divide-y divide-border">
              {modules
                .filter((m) => m.section === section)
                .map((m) => (
                  <ModuleRow key={m.code} module={m} pending={pending} onSwitch={(enabled) => ask([m.code], enabled, m.name)} />
                ))}
            </div>
          </section>
        ))}
      </CardBody>
      <ConfirmDialog
        open={confirm !== null}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={`Switch off ${confirm?.label ?? ""}?`}
        description="The company's users stop seeing it at once. Its records are kept and come back when it is switched on again."
        confirmLabel="Switch off"
        loading={pending}
        onConfirm={() => confirm && set(confirm.codes, false)}
      />
    </Card>
  );
}

function ModuleRow({ module: m, pending, onSwitch }) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
      <div className="min-w-0">
        <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-foreground">
          {m.name}
          {m.name_bn ? <span className="text-foreground-muted">· {m.name_bn}</span> : null}
          {m.core ? (
            <Badge variant="brand">
              <Lock className="mr-1 inline size-3" aria-hidden /> Core
            </Badge>
          ) : m.enabled ? (
            <Badge variant="success">On</Badge>
          ) : (
            <Badge variant="neutral">Off</Badge>
          )}
        </p>
        {m.description ? <p className="mt-0.5 max-w-2xl text-xs text-foreground-muted">{m.description}</p> : null}
        {!m.core && m.changed_at ? (
          <p className="mt-0.5 text-xs text-foreground-muted">
            {m.enabled ? "Switched on" : "Switched off"} {STAMP.format(new Date(m.changed_at))}
            {m.changed_by ? ` by ${m.changed_by}` : ""}
          </p>
        ) : null}
      </div>
      {m.core ? (
        <span className="text-xs text-foreground-muted">Always on</span>
      ) : (
        <button
          type="button"
          role="switch"
          aria-checked={m.enabled}
          aria-label={`${m.name}: ${m.enabled ? "on" : "off"}`}
          disabled={pending}
          onClick={() => onSwitch(!m.enabled)}
          className={cn("relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition disabled:opacity-60", m.enabled ? "bg-success" : "bg-border-strong")}
        >
          <span className={cn("inline-block size-5 rounded-full bg-white shadow transition", m.enabled ? "translate-x-5" : "translate-x-0.5")} />
        </button>
      )}
    </div>
  );
}

export { ModulesPanel };
