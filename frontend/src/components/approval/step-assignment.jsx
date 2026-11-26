"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { CheckCheck, ListChecks, RotateCcw, Save, Search } from "lucide-react";
import { Card, CardBody, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Approval Workflow Designer → Action Assignment (AnnotechERP 02/03): pick a
 * step, tick the people it lands on. A step with named people is theirs
 * alone; one with a role but nobody named still falls back to the role.
 */
function StepAssignment({ steps, loadUsers, saveUsers }) {
  const t = useT("approval");
  const toastManager = useToastManager();
  const [stepId, setStepId] = useState("");
  const [users, setUsers] = useState([]);
  const [ticked, setTicked] = useState(new Set());
  const [query, setQuery] = useState("");
  const [pending, startTransition] = useTransition();

  useEffect(() => {
    if (!stepId) return;
    let live = true;
    loadUsers(stepId).then((result) => {
      if (!live || result?.status !== "success") return;
      setUsers(result.users);
      setTicked(new Set(result.users.filter((u) => u.assigned).map((u) => u.id)));
    });
    return () => {
      live = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [stepId]);

  const visible = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return needle ? users.filter((u) => `${u.name} ${u.email} ${u.department ?? ""}`.toLowerCase().includes(needle)) : users;
  }, [users, query]);

  const toggle = (id, on) =>
    setTicked((current) => {
      const next = new Set(current);
      if (on) next.add(id);
      else next.delete(id);
      return next;
    });

  function save() {
    startTransition(async () => {
      const result = await saveUsers(stepId, [...ticked]);
      if (result?.status === "success") {
        setUsers(result.users);
        toastManager.add({ title: t("center.designer.assigned_saved"), type: "success" });
      } else {
        toastManager.add({ title: result?.message, type: "danger" });
      }
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <ListChecks className="size-4" /> {t("center.designer.assignment")}
        </CardTitle>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{t("center.designer.assignment_hint")}</p>
      </CardHeader>
      <CardBody className="flex flex-col gap-3">
        <div className="flex flex-wrap items-end gap-3">
          <label className="flex w-full max-w-md flex-col gap-1.5">
            <span className="text-[13px] font-semibold text-foreground">
              {t("center.designer.select_action")}
              <span className="ml-0.5 text-danger">*</span>
            </span>
            <Select value={stepId} onValueChange={setStepId} options={steps} placeholder={t("center.designer.select_action_placeholder")} />
          </label>
          {stepId ? (
            <>
              <div className="relative min-w-[220px] flex-1">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
                <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t("center.designer.search_users", { count: users.length })} className="pl-9" />
              </div>
              <Button variant="outline" onClick={() => setTicked(new Set(users.map((u) => u.id)))}>
                <CheckCheck /> {t("center.designer.select_all")}
              </Button>
            </>
          ) : null}
        </div>

        {!stepId ? (
          <p className="py-10 text-center text-sm text-foreground-muted">{t("center.designer.choose_action")}</p>
        ) : (
          <>
            <div className="overflow-x-auto rounded-md border border-border">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-primary text-left text-primary-foreground">
                    <th className="w-10 px-3 py-2" />
                    <th className="px-3 py-2 font-semibold">{t("center.designer.user_name")}</th>
                    <th className="px-3 py-2 font-semibold">{t("center.designer.user_id")}</th>
                    <th className="px-3 py-2 font-semibold">{t("center.designer.department")}</th>
                  </tr>
                </thead>
                <tbody>
                  {visible.map((u) => (
                    <tr key={u.id} className="border-b border-border last:border-0">
                      <td className="px-3 py-1.5">
                        <Checkbox checked={ticked.has(u.id)} onCheckedChange={(c) => toggle(u.id, c === true)} aria-label={u.name} />
                      </td>
                      <td className="px-3 py-1.5">{u.name}</td>
                      <td className="px-3 py-1.5 text-foreground-muted">{u.email}</td>
                      <td className="px-3 py-1.5 text-foreground-muted">{u.department ?? "—"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="text-[13px] text-foreground-muted">{t("center.designer.selected", { selected: ticked.size, count: users.length })}</p>
            <div className="flex gap-2">
              <Button variant="outline" onClick={() => setTicked(new Set(users.filter((u) => u.assigned).map((u) => u.id)))} disabled={pending}>
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
  );
}

export { StepAssignment };
