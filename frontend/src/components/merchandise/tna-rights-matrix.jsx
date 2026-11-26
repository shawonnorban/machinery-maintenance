"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { CheckCheck, KeyRound, RotateCcw, Save } from "lucide-react";
import { Card, CardBody, CardHeader, CardTitle } from "@/components/ui/card";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const ACTIONS = ["can_create", "can_update", "can_delete"];

/**
 * TNA Task Rights → T&A Task Permission (AnnotechERP 06/01): pick a user, and
 * every T&A task loads with what they hold. Seeing a task (R) is the floor —
 * ticking C, U or D ticks R too, and clearing R clears the rest.
 *
 * @param {{ users: {value: string, label: string}[], load: Function, save: Function, canManage: boolean }} props
 */
function TnaRightsMatrix({ users, load, save, canManage }) {
  const t = useT("merchandise");
  const router = useRouter();
  const toastManager = useToastManager();
  const [user, setUser] = useState("");
  const [rows, setRows] = useState([]);
  const [pending, startTransition] = useTransition();

  function fetchRows(id) {
    return load(id).then((result) => {
      if (result?.status === "success") setRows(result.rows);
    });
  }

  useEffect(() => {
    if (user) fetchRows(user);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user]);

  function set(id, field, on) {
    setRows((current) =>
      current.map((r) => {
        if (r.tna_task_id !== id) return r;
        if (field === "granted") return on ? { ...r, granted: true } : { ...r, granted: false, can_create: false, can_update: false, can_delete: false };
        return { ...r, [field]: on, granted: on ? true : r.granted };
      }),
    );
  }

  function submit() {
    startTransition(async () => {
      const rights = rows.filter((r) => r.granted).map((r) => ({ tna_task_id: r.tna_task_id, can_create: r.can_create, can_update: r.can_update, can_delete: r.can_delete }));
      const result = await save(user, rights);
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
          <KeyRound className="size-4" /> {t("master.tna.rights_title")}
        </CardTitle>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{t("master.tna.rights_hint")}</p>
      </CardHeader>
      <CardBody className="flex flex-col gap-3">
        <label className="flex w-full max-w-md flex-col gap-1.5">
          <span className="text-[13px] font-semibold text-foreground">
            {t("master.f.user")}
            <span className="ml-0.5 text-danger">*</span>
          </span>
          <Select value={user} onValueChange={(v) => v && setUser(v)} options={users} placeholder={t("master.pick.user_buyers.placeholder")} />
        </label>
        {!user ? (
          <p className="py-10 text-center text-sm text-foreground-muted">{t("master.tna.rights_choose")}</p>
        ) : (
          <>
            <div className="overflow-x-auto rounded-lg border border-border">
              <table className="w-full min-w-max text-sm">
                <thead>
                  <tr className="bg-primary text-left text-primary-foreground">
                    <th className="px-3 py-2 font-semibold">{t("master.f.task_group")}</th>
                    <th className="px-3 py-2 font-semibold">{t("master.f.task_name")}</th>
                    {["C", "R", "U", "D"].map((h) => (
                      <th key={h} className="w-14 px-3 py-2 text-center font-semibold">
                        {h}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {rows.map((r) => (
                    <tr key={r.tna_task_id} className="border-b border-border last:border-0">
                      <td className="px-3 py-1.5 text-foreground-muted">{r.group ?? "—"}</td>
                      <td className="px-3 py-1.5">{r.name}</td>
                      {["can_create", "granted", "can_update", "can_delete"].map((field) => (
                        <td key={field} className="px-3 py-1.5 text-center">
                          <Checkbox checked={r[field]} onCheckedChange={(c) => set(r.tna_task_id, field, c === true)} disabled={!canManage} aria-label={`${r.name} ${field}`} />
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="text-[13px] text-foreground-muted">{t("master.tna.rights_count", { granted: rows.filter((r) => r.granted).length, count: rows.length })}</p>
            {canManage ? (
              <div className="flex gap-2">
                <Button variant="outline" onClick={() => setRows((current) => current.map((r) => ({ ...r, granted: true, ...Object.fromEntries(ACTIONS.map((a) => [a, true])) })))}>
                  <CheckCheck /> {t("master.pick.all")}
                </Button>
                <Button variant="outline" onClick={() => fetchRows(user)} disabled={pending}>
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

export { TnaRightsMatrix };
