"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { CheckCheck, ListChecks, RotateCcw, Save, Search } from "lucide-react";
import { Card, CardBody, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Brickland's "pick one, tick the rest" panel (Merchandise Desk → Master Data,
 * AnnotechERP 06/01): choose an office, a buyer or a user, and every item loads
 * with the ones it holds today already ticked; saved as a whole. `action` is an
 * optional button beside the picker ("New Institute").
 *
 * @param {{ kind: string, subjects: {value: string, label: string}[], load: Function, save: Function, canManage: boolean,
 *   labels: { title: string, hint: string, select: string, placeholder: string, choose: string, empty: string }, action?: React.ReactNode }} props
 */
function PickTicker({ kind, subjects, load, save, canManage, labels, action = null }) {
  const t = useT("merchandise");
  const router = useRouter();
  const toastManager = useToastManager();
  const [subject, setSubject] = useState("");
  const [rows, setRows] = useState([]);
  const [ticked, setTicked] = useState(new Set());
  const [query, setQuery] = useState("");
  const [pending, startTransition] = useTransition();

  useEffect(() => {
    if (!subject) return;
    let live = true;
    load(kind, subject).then((result) => {
      if (!live || result?.status !== "success") return;
      setRows(result.rows);
      setTicked(new Set(result.rows.filter((r) => r.assigned).map((r) => r.id)));
    });
    return () => {
      live = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [subject, kind]);

  const visible = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return needle ? rows.filter((r) => r.label.toLowerCase().includes(needle)) : rows;
  }, [rows, query]);

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
      const result = await save(kind, subject, [...ticked]);
      if (result?.status === "success") {
        setRows(result.rows);
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
          <ListChecks className="size-4" /> {labels.title}
        </CardTitle>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{labels.hint}</p>
      </CardHeader>
      <CardBody className="flex flex-col gap-3">
        <div className="flex flex-wrap items-end gap-3">
          <label className="flex w-full max-w-md flex-col gap-1.5">
            <span className="text-[13px] font-semibold text-foreground">
              {labels.select}
              <span className="ml-0.5 text-danger">*</span>
            </span>
            <Select value={subject} onValueChange={(v) => v && setSubject(v)} options={subjects} placeholder={labels.placeholder} />
          </label>
          {subject ? (
            <div className="relative min-w-[220px] flex-1">
              <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
              <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t("master.pick.search", { count: rows.length })} className="pl-9" />
            </div>
          ) : null}
          {action}
        </div>
        {!subject ? (
          <p className="py-10 text-center text-sm text-foreground-muted">{labels.choose}</p>
        ) : rows.length === 0 ? (
          <p className="py-6 text-center text-sm text-foreground-muted">{labels.empty}</p>
        ) : (
          <>
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
              {visible.map((r) => (
                <label key={r.id} className="flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm">
                  <Checkbox checked={ticked.has(r.id)} onCheckedChange={(c) => toggle(r.id, c === true)} disabled={!canManage} />
                  <span className={r.active === false ? "flex-1 truncate text-foreground-muted line-through" : "flex-1 truncate"}>{r.label}</span>
                </label>
              ))}
            </div>
            <p className="text-[13px] text-foreground-muted">{t("master.pick.selected", { selected: ticked.size, count: rows.length })}</p>
            {canManage ? (
              <div className="flex gap-2">
                <Button variant="outline" onClick={() => setTicked(new Set(rows.map((r) => r.id)))}>
                  <CheckCheck /> {t("master.pick.all")}
                </Button>
                <Button variant="outline" onClick={() => setTicked(new Set(rows.filter((r) => r.assigned).map((r) => r.id)))} disabled={pending}>
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

export { PickTicker };
