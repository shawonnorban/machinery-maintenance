"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { CheckCheck, ListChecks, RotateCcw, Save } from "lucide-react";
import { Card, CardBody, CardHeader, CardTitle } from "@/components/ui/card";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Fabric Type Master → GARM Part List (AnnotechERP 06/01): pick a fabric type,
 * every garment part loads with the ones it may be cut for ticked.
 */
function GarmentPartPicker({ fabricTypes, load, save, canManage }) {
  const t = useT("merchandise");
  const router = useRouter();
  const toastManager = useToastManager();
  const [typeId, setTypeId] = useState("");
  const [parts, setParts] = useState([]);
  const [ticked, setTicked] = useState(new Set());
  const [pending, startTransition] = useTransition();

  useEffect(() => {
    if (!typeId) return;
    let live = true;
    load(typeId).then((result) => {
      if (!live || result?.status !== "success") return;
      setParts(result.parts);
      setTicked(new Set(result.parts.filter((p) => p.assigned).map((p) => p.id)));
    });
    return () => {
      live = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [typeId]);

  function toggle(id, on) {
    setTicked((current) => {
      const next = new Set(current);
      if (on) next.add(id);
      else next.delete(id);
      return next;
    });
  }

  function submit() {
    startTransition(async () => {
      const result = await save(typeId, [...ticked]);
      if (result?.status === "success") {
        setParts(result.parts);
        toastManager.add({ title: t("master.saved"), type: "success" });
        router.refresh();
      } else {
        toastManager.add({ title: result?.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <ListChecks className="size-4" /> {t("master.garm.title")}
        </CardTitle>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{t("master.garm.hint")}</p>
      </CardHeader>
      <CardBody className="flex flex-col gap-3">
        <label className="flex max-w-md flex-col gap-1.5">
          <span className="text-[13px] font-semibold text-foreground">
            {t("master.garm.select")}
            <span className="ml-0.5 text-danger">*</span>
          </span>
          <Select
            value={typeId}
            onValueChange={setTypeId}
            options={fabricTypes.map((f) => ({ value: f.id, label: `${f.name} (${f.code})` }))}
            placeholder={t("master.garm.placeholder")}
          />
        </label>
        {!typeId ? (
          <p className="py-10 text-center text-sm text-foreground-muted">{t("master.garm.choose")}</p>
        ) : parts.length === 0 ? (
          <p className="py-6 text-center text-sm text-foreground-muted">{t("master.garm.no_parts")}</p>
        ) : (
          <>
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
              {parts.map((p) => (
                <label key={p.id} className="flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm">
                  <Checkbox checked={ticked.has(p.id)} onCheckedChange={(c) => toggle(p.id, c === true)} disabled={!canManage} />
                  <span className="flex-1 truncate">{p.name}</span>
                  <span className="font-mono text-xs text-foreground-muted">{p.code}</span>
                </label>
              ))}
            </div>
            <p className="text-[13px] text-foreground-muted">{t("master.garm.selected", { selected: ticked.size, count: parts.length })}</p>
            {canManage ? (
              <div className="flex gap-2">
                <Button variant="outline" onClick={() => setTicked(new Set(parts.map((p) => p.id)))}>
                  <CheckCheck /> {t("master.garm.all")}
                </Button>
                <Button variant="outline" onClick={() => setTicked(new Set(parts.filter((p) => p.assigned).map((p) => p.id)))} disabled={pending}>
                  <RotateCcw /> {t("master.reset")}
                </Button>
                <Button onClick={submit} loading={pending}>
                  <Save /> {t("master.save")}
                </Button>
              </div>
            ) : null}
          </>
        )}
      </CardBody>
    </Card>
  );
}

export { GarmentPartPicker };
