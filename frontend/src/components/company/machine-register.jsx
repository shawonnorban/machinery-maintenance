"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ArrowLeftRight, ExternalLink, Plus, RotateCcw, Save } from "lucide-react";
import { Card, CardActions, CardBody, CardDescription, CardFooter, CardHeader, CardTitle, CardTitleGroup } from "@/components/ui/card";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { StatusBadge } from "@/components/ui/status-badge";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";
import { cn } from "@/lib/utils";
import { refOf } from "@/lib/ref-path";

/** Brickland's five states → the asset status machinery maintenance records. */
const TO_ASSET_STATUS = {
  ACTIVE: "RUNNING",
  IDLE: "IDLE",
  UNDER_MAINTENANCE: "UNDER_MAINTENANCE",
  DAMAGED: "BREAKDOWN",
  RETIRED: "RETIRED",
};

function Th({ children, className }) {
  return <th className={`px-3 py-2.5 font-semibold whitespace-nowrap ${className ?? ""}`}>{children}</th>;
}

/**
 * Machine Master (AnnotechERP 01/04): machinery maintenance's machine register
 * in Brickland's columns. Capacity and status are edited here and saved
 * together; everything else — and adding a machine — opens machinery
 * maintenance's own form, so there is only ever one machine record.
 */
function MachineMaster({ machines, saveMachines }) {
  const t = useT("company");
  const router = useRouter();
  const toastManager = useToastManager();
  const [drafts, setDrafts] = useState({});
  const [pending, startTransition] = useTransition();
  const statusOptions = Object.keys(TO_ASSET_STATUS).map((value) => ({ value, label: t(`machine_status.${value}`) }));
  const dirty = Object.keys(drafts);

  function edit(machine, field, value) {
    setDrafts((current) => {
      const next = { ...(current[machine.id] ?? {}), [field]: value };
      if (String(machine[field] ?? "") === String(value ?? "")) delete next[field];
      const all = { ...current, [machine.id]: next };
      if (Object.keys(next).length === 0) delete all[machine.id];
      return all;
    });
  }

  function save() {
    startTransition(async () => {
      const changes = dirty.map((id) => {
        const machine = machines.find((m) => m.id === id);
        const draft = drafts[id];
        return {
          id,
          version: machine.version,
          ...("capacity" in draft ? { capacity: draft.capacity } : {}),
          ...("status" in draft ? { assetStatus: TO_ASSET_STATUS[draft.status] } : {}),
        };
      });
      const result = await saveMachines(changes);
      const failed = result.results.filter((entry) => entry.status === "error");
      setDrafts((current) => Object.fromEntries(failed.map((entry) => [entry.id, current[entry.id]])));
      toastManager.add(
        failed.length > 0
          ? { title: t("save_failed", { count: failed.length, message: failed[0].message }), type: "danger" }
          : { title: t("saved"), type: "success" },
      );
      router.refresh();
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitleGroup>
          <CardTitle>{t("tabs.machine-master")}</CardTitle>
          <CardDescription>{t("descriptions.machine-master")}</CardDescription>
        </CardTitleGroup>
        <CardActions>
          <Link href="/assets/create" className={cn(buttonVariants({ size: "sm" }))}>
            <Plus /> {t("add", { name: t("singular.machine") })}
          </Link>
        </CardActions>
      </CardHeader>
      <CardBody className="overflow-x-auto p-0">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-primary text-left text-primary-foreground">
              <Th>{t("sl")}</Th>
              <Th>{t("machine.code")}</Th>
              <Th>{t("machine.name")}</Th>
              <Th>{t("machine.type")}</Th>
              <Th>{t("machine.brand")}</Th>
              <Th>{t("machine.model")}</Th>
              <Th>{t("machine.serial_number")}</Th>
              <Th>{t("machine.purchase_date")}</Th>
              <Th>{t("machine.installation_date")}</Th>
              <Th>{t("machine.capacity")}</Th>
              <Th>{t("machine.status")}</Th>
              <Th className="text-right">{t("action")}</Th>
            </tr>
          </thead>
          <tbody>
            {machines.map((machine, index) => {
              const draft = drafts[machine.id] ?? {};
              const knownStatus = machine.status in TO_ASSET_STATUS;
              return (
                <tr key={machine.id} className={drafts[machine.id] ? "bg-warning-subtle/40" : "border-b border-border last:border-0"}>
                  <td className="px-3 py-2 text-foreground-muted">{index + 1}</td>
                  <td className="px-3 py-2 font-mono">{machine.code}</td>
                  <td className="px-3 py-2">{machine.name}</td>
                  <td className="px-3 py-2">{machine.type ?? "—"}</td>
                  <td className="px-3 py-2">{machine.brand ?? "—"}</td>
                  <td className="px-3 py-2">{machine.model ?? "—"}</td>
                  <td className="px-3 py-2">{machine.serial_number ?? "—"}</td>
                  <td className="px-3 py-2 tabular-nums">{machine.purchase_date ?? "—"}</td>
                  <td className="px-3 py-2 tabular-nums">{machine.installation_date ?? "—"}</td>
                  <td className="px-3 py-2">
                    <Input
                      type="number"
                      className="w-28"
                      value={draft.capacity ?? machine.capacity ?? ""}
                      onChange={(event) => edit(machine, "capacity", event.target.value)}
                      aria-label={t("machine.capacity")}
                    />
                  </td>
                  <td className="px-3 py-2">
                    {knownStatus ? (
                      <Select
                        value={draft.status ?? machine.status}
                        onValueChange={(value) => edit(machine, "status", value)}
                        options={statusOptions}
                        className="min-w-44"
                        aria-label={t("machine.status")}
                      />
                    ) : (
                      // A lifecycle state Brickland has no name for (DRAFT, COMMISSIONED …):
                      // shown, and changed only from machinery maintenance.
                      <StatusBadge status={machine.asset_status} />
                    )}
                  </td>
                  <td className="px-3 py-2 text-right">
                    <Link href={`/assets/${refOf(machine, "asset_code")}`} className={cn(buttonVariants({ variant: "ghost", size: "sm" }))} aria-label={t("open_in_machinery")}>
                      <ExternalLink />
                    </Link>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </CardBody>
      <CardFooter className="flex flex-wrap items-center justify-between gap-3">
        <p className="max-w-3xl text-sm text-foreground-muted">
          {dirty.length > 0 ? <strong className="mr-2 text-foreground">{t("unsaved", { count: dirty.length })}</strong> : null}
          {t("rules.machine-master")}
        </p>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" onClick={() => setDrafts({})} disabled={dirty.length === 0 || pending}>
            <RotateCcw /> {t("revert")}
          </Button>
          <Button size="sm" onClick={save} loading={pending} disabled={dirty.length === 0}>
            <Save /> {t("save")}
          </Button>
        </div>
      </CardFooter>
    </Card>
  );
}

/**
 * Machine Allocation (AnnotechERP 01/04): where every machine stands. Moving
 * one goes through machinery maintenance's transfer, which keeps the history.
 */
function MachineAllocation({ machines }) {
  const t = useT("company");

  return (
    <Card>
      <CardHeader>
        <CardTitleGroup>
          <CardTitle>{t("tabs.machine-allocation")}</CardTitle>
          <CardDescription>{t("descriptions.machine-allocation")}</CardDescription>
        </CardTitleGroup>
        <CardActions>
          <Link href="/assets/transfers" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            <ArrowLeftRight /> {t("transfers")}
          </Link>
        </CardActions>
      </CardHeader>
      <CardBody className="overflow-x-auto p-0">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-primary text-left text-primary-foreground">
              <Th>{t("sl")}</Th>
              <Th>{t("machine.code")}</Th>
              <Th>{t("machine.name")}</Th>
              <Th>{t("tabs.building")}</Th>
              <Th>{t("tabs.floor")}</Th>
              <Th>{t("tabs.department")}</Th>
              <Th>{t("tabs.section")}</Th>
              <Th>{t("tabs.line")}</Th>
              <Th>{t("tabs.workstation")}</Th>
              <Th>{t("machine.status")}</Th>
              <Th className="text-right">{t("action")}</Th>
            </tr>
          </thead>
          <tbody>
            {machines.map((machine, index) => (
              <tr key={machine.id} className="border-b border-border last:border-0">
                <td className="px-3 py-2 text-foreground-muted">{index + 1}</td>
                <td className="px-3 py-2 font-mono">{machine.code}</td>
                <td className="px-3 py-2">{machine.name}</td>
                <td className="px-3 py-2">{machine.building ?? "—"}</td>
                <td className="px-3 py-2">{machine.floor ?? "—"}</td>
                <td className="px-3 py-2">{machine.department ?? "—"}</td>
                <td className="px-3 py-2">{machine.section ?? "—"}</td>
                <td className="px-3 py-2">{machine.line ?? "—"}</td>
                <td className="px-3 py-2">{machine.workstation ?? "—"}</td>
                <td className="px-3 py-2">
                  <StatusBadge status={machine.asset_status} />
                </td>
                <td className="px-3 py-2 text-right">
                  <Link href={`/assets/${refOf(machine, "asset_code")}`} className={cn(buttonVariants({ variant: "ghost", size: "sm" }))} aria-label={t("move_machine")}>
                    <ArrowLeftRight />
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </CardBody>
      <CardFooter>
        <p className="text-sm text-foreground-muted">{t("rules.machine-allocation")}</p>
      </CardFooter>
    </Card>
  );
}

export { MachineMaster, MachineAllocation };
