"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ListChecks, Plus, RotateCcw, Save, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Card, CardBody, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const FLAGS = ["requires_signature", "can_repeat", "can_cancel", "can_resend", "active"];

function blankStep(sequence) {
  return {
    key: `new-${Date.now()}-${sequence}`,
    id: null,
    sequence,
    name: "",
    task_message: "",
    role_id: "",
    role_flag: "",
    next_action: "",
    level: 1,
    min_cost: "",
    max_cost: "",
    assigned: 0,
    requires_signature: false,
    can_repeat: false,
    can_cancel: false,
    can_resend: false,
    active: true,
  };
}

function editable(steps) {
  return steps.map((s) => ({
    ...s,
    key: s.id,
    role_id: s.role_id === null || s.role_id === undefined ? "" : String(s.role_id),
    task_message: s.task_message ?? "",
    role_flag: s.role_flag ?? "",
    next_action: s.next_action ?? "",
    min_cost: s.min_cost ?? "",
    max_cost: s.max_cost ?? "",
  }));
}

/**
 * Approval Workflow Designer → Approval Actions (AnnotechERP 02/03): the
 * action types on the left; on the right the chosen one's steps in order,
 * edited in place and saved as a whole. Level 0 is a working step (raise,
 * issue, receive) that asks nobody for a decision; 1 and up are approval
 * levels, each optionally limited to an amount band.
 */
function WorkflowSteps({ workflows, roles, loadSteps, saveSteps }) {
  const t = useT("approval");
  const router = useRouter();
  const toastManager = useToastManager();
  const [query, setQuery] = useState("");
  const [chosen, setChosen] = useState(null);
  const [steps, setSteps] = useState([]);
  const [original, setOriginal] = useState([]);
  const [pendingCount, setPendingCount] = useState(0);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();

  const visible = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return needle ? workflows.filter((w) => `${w.type_code} ${w.name}`.toLowerCase().includes(needle)) : workflows;
  }, [workflows, query]);

  useEffect(() => {
    if (!chosen) return;
    let live = true;
    loadSteps(chosen.id).then((result) => {
      if (!live || result?.status !== "success") return;
      setSteps(editable(result.steps));
      setOriginal(editable(result.steps));
      setPendingCount(result.pending);
      setError(null);
    });
    return () => {
      live = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [chosen]);

  const set = (key, field, value) => setSteps((current) => current.map((s) => (s.key === key ? { ...s, [field]: value } : s)));

  function save() {
    startTransition(async () => {
      const payload = steps.map((s) => ({
        id: s.id,
        sequence: Number(s.sequence),
        name: s.name,
        task_message: s.task_message || null,
        role_id: s.role_id || null,
        role_flag: s.role_flag || null,
        next_action: s.next_action || null,
        level: Number(s.level),
        min_cost: s.min_cost === "" ? null : Number(s.min_cost),
        max_cost: s.max_cost === "" ? null : Number(s.max_cost),
        ...Object.fromEntries(FLAGS.map((f) => [f, Boolean(s[f])])),
      }));
      const result = await saveSteps(chosen.id, payload);
      if (result?.status === "success") {
        setSteps(editable(result.steps));
        setOriginal(editable(result.steps));
        setError(null);
        toastManager.add({ title: t("center.designer.saved"), type: "success" });
        router.refresh();
      } else {
        setError(result?.errors ? Object.values(result.errors)[0][0] : result?.message);
      }
    });
  }

  const cell = "px-2 py-1.5";
  const roleOptions = [{ value: "", label: "—" }, ...roles.map((r) => ({ value: String(r.id), label: r.name }))];

  return (
    <div className="grid gap-4 lg:grid-cols-[320px_1fr]">
      <Card>
        <CardHeader className="flex items-center justify-between">
          <CardTitle>{t("center.designer.master_list")}</CardTitle>
          <span className="rounded-full bg-surface-muted px-2 text-xs font-semibold">{workflows.length}</span>
        </CardHeader>
        <CardBody className="flex flex-col gap-2 p-0">
          <div className="relative px-4">
            <Search className="pointer-events-none absolute top-1/2 left-7 size-4 -translate-y-1/2 text-foreground-subtle" />
            <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t("center.designer.search")} className="pl-9" />
          </div>
          <ul className="divide-y divide-border">
            {visible.map((w) => (
              <li key={w.id}>
                <button
                  type="button"
                  onClick={() => setChosen(w)}
                  className={cn(
                    "flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left text-sm hover:bg-surface-muted",
                    chosen?.id === w.id && "bg-brand-subtle font-semibold text-brand",
                  )}
                >
                  <span className="truncate">
                    {w.type_code} — {w.name}
                  </span>
                  <span className="text-xs text-foreground-muted tabular-nums">{w.actions}</span>
                </button>
              </li>
            ))}
          </ul>
        </CardBody>
      </Card>

      <Card className="min-w-0">
        <CardHeader className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <CardTitle className="flex items-center gap-2">
              <ListChecks className="size-4" /> {chosen ? `${t("center.designer.actions")} — ${chosen.type_code} — ${chosen.name}` : t("center.designer.actions")}
            </CardTitle>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{t("center.designer.actions_hint")}</p>
          </div>
          {chosen ? (
            <Button variant="outline" onClick={() => setSteps((s) => [...s, blankStep((s.at(-1)?.sequence ?? 0) + 1)])}>
              <Plus /> {t("center.designer.add_action")}
            </Button>
          ) : null}
        </CardHeader>
        <CardBody className="flex flex-col gap-3">
          {!chosen ? (
            <p className="py-10 text-center text-sm text-foreground-muted">{t("center.designer.pick")}</p>
          ) : (
            <>
              {pendingCount > 0 ? <Alert variant="warning">{t("center.designer.in_use", { count: pendingCount })}</Alert> : null}
              {error ? <Alert variant="danger">{error}</Alert> : null}
              <div className="overflow-x-auto rounded-md border border-border">
                <table className="w-full min-w-max text-sm">
                  <thead>
                    <tr className="bg-primary text-left text-primary-foreground">
                      {["order", "name", "task", "role", "role_flag", "next", "assigned", "level", "min", "max", ...FLAGS, "delete"].map((h) => (
                        <th key={h} className="px-2 py-2 font-semibold whitespace-nowrap">
                          {t(`center.designer.cols.${h}`)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {steps.map((s) => (
                      <tr key={s.key} className="border-b border-border last:border-0">
                        <td className={cell}>
                          <Input type="number" min={1} value={s.sequence} onChange={(e) => set(s.key, "sequence", e.target.value)} className="w-16" />
                        </td>
                        <td className={cell}>
                          <Input value={s.name} onChange={(e) => set(s.key, "name", e.target.value)} className="w-44" />
                        </td>
                        <td className={cell}>
                          <Input value={s.task_message} onChange={(e) => set(s.key, "task_message", e.target.value)} className="w-52" />
                        </td>
                        <td className={cell}>
                          <div className="w-44">
                            <Select value={s.role_id} onValueChange={(v) => set(s.key, "role_id", v)} options={roleOptions} placeholder="—" />
                          </div>
                        </td>
                        <td className={cell}>
                          <Input value={s.role_flag} onChange={(e) => set(s.key, "role_flag", e.target.value)} className="w-40" />
                        </td>
                        <td className={cell}>
                          <Input value={s.next_action} onChange={(e) => set(s.key, "next_action", e.target.value)} className="w-36" />
                        </td>
                        <td className={cn(cell, "text-center tabular-nums", s.id && s.assigned === 0 && Number(s.level) > 0 && "font-semibold text-warning")}>{s.assigned}</td>
                        <td className={cell}>
                          <Input type="number" min={0} value={s.level} onChange={(e) => set(s.key, "level", e.target.value)} className="w-16" />
                        </td>
                        <td className={cell}>
                          <Input type="number" min={0} value={s.min_cost} onChange={(e) => set(s.key, "min_cost", e.target.value)} className="w-28" />
                        </td>
                        <td className={cell}>
                          <Input type="number" min={0} value={s.max_cost} onChange={(e) => set(s.key, "max_cost", e.target.value)} className="w-28" />
                        </td>
                        {FLAGS.map((f) => (
                          <td key={f} className={cn(cell, "text-center")}>
                            <Checkbox checked={Boolean(s[f])} onCheckedChange={(c) => set(s.key, f, c === true)} aria-label={t(`center.designer.cols.${f}`)} />
                          </td>
                        ))}
                        <td className={cell}>
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setSteps((current) => current.filter((x) => x.key !== s.key))}
                            aria-label={t("center.designer.cols.delete")}
                          >
                            <Trash2 />
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <p className="text-[13px] text-foreground-muted">{t("center.designer.footer", { count: steps.length })}</p>
              <div className="flex gap-2">
                <Button variant="outline" onClick={() => setSteps(original)} disabled={pending}>
                  <RotateCcw /> {t("center.designer.revert")}
                </Button>
                <Button onClick={save} loading={pending}>
                  <Save /> {t("center.designer.save")}
                </Button>
              </div>
            </>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export { WorkflowSteps };
