"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Pencil, Plus, Save, Trash2, UserCog, Users, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { MultiPick } from "@/components/erp/multi-pick";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * Order Summary → Merchandising Team (AnnotechERP 32): who heads each
 * department, and each team — its department, team head and merchandisers
 * with the buyers each works. A merchandiser sees and edits their buyers'
 * orders, a team head the team's, a department head the department's; the
 * same people get those orders' edit mail.
 */

const sel = "h-9 w-full rounded-md border border-border bg-surface px-2 text-[13px]";

function TeamSetup({ data, act }) {
  const t = useT("ordersummary");
  const router = useRouter();
  const toast = useToastManager();
  const [editing, setEditing] = useState(null);
  const [heads, setHeads] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [pending, start] = useTransition();
  const [pickDept, setPickDept] = useState("");
  // Only the departments that have heads or teams; another is added through the picker.
  const used = data.departments.filter((d) => (data.heads[d.value] ?? []).length || data.teams.some((tm) => tm.department_id === d.value));
  const name = (id) => data.users.find((u) => u.value === id)?.label?.replace(/ \(.*\)$/, "") ?? "—";
  const buyer = (id) => data.buyers.find((b) => b.value === id)?.label ?? id;
  const dept = (id) => data.departments.find((d) => d.value === id)?.label ?? "—";

  const run = (call, ok, after) =>
    start(async () => {
      const res = await call;
      if (res?.status === "success") {
        toast.add({ title: ok, type: "success" });
        after?.();
        router.refresh();
      } else {
        const first = res?.errors ? Object.values(res.errors)[0]?.[0] : null;
        toast.add({ title: first ?? res?.message ?? t("sheet.error"), type: "danger" });
      }
    });

  return (
    <div className="flex flex-col gap-4 text-[13px]">
      <section className="rounded-lg border border-border bg-surface p-4">
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <h3 className="flex items-center gap-2 font-semibold"><UserCog className="size-4" /> {t("team.department_heads")}</h3>
          {data.can_manage ? (
            <div className="ml-auto flex items-center gap-2">
              <select className="h-9 w-72 rounded-md border border-border bg-surface px-2" value={pickDept} onChange={(e) => setPickDept(e.target.value)} aria-label={t("team.department")}>
                <option value="">{t("team.pick_department")}</option>
                {data.departments.map((d) => <option key={d.value} value={d.value}>{d.label}</option>)}
              </select>
              <Button size="sm" disabled={!pickDept} onClick={() => setHeads({ department_id: pickDept, user_ids: data.heads[pickDept] ?? [] })}><Plus /> {t("team.set_heads")}</Button>
            </div>
          ) : null}
        </div>
        {used.length === 0 ? <p className="text-foreground-muted">{t("team.no_heads")}</p> : null}
        <table className={used.length ? "w-full" : "hidden"}>
          <thead><tr className="text-left text-foreground-muted"><th className="py-1">{t("team.department")}</th><th className="py-1">{t("team.heads")}</th><th /></tr></thead>
          <tbody>
            {used.map((d) => (
              <tr key={d.value} className="border-t border-border">
                <td className="py-1.5 font-medium">{d.label}</td>
                <td className="py-1.5">{(data.heads[d.value] ?? []).map(name).join(", ") || <span className="text-foreground-subtle">—</span>}</td>
                <td className="py-1.5 text-right">{data.can_manage ? <Button size="sm" variant="outline" onClick={() => setHeads({ department_id: d.value, user_ids: data.heads[d.value] ?? [] })}><Pencil /> {t("team.edit")}</Button> : null}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>

      <section className="rounded-lg border border-border bg-surface p-4">
        <div className="mb-3 flex items-center gap-2">
          <h3 className="flex items-center gap-2 font-semibold"><Users className="size-4" /> {t("team.teams")}</h3>
          {data.can_manage ? <Button size="sm" className="ml-auto" onClick={() => setEditing({ name: "", department_id: "", team_head_id: "", active: true, members: [] })}><Plus /> {t("team.add")}</Button> : null}
        </div>
        {data.teams.length === 0 ? <p className="text-foreground-muted">{t("team.empty")}</p> : null}
        <div className="grid gap-3 lg:grid-cols-2">
          {data.teams.map((tm) => (
            <article key={tm.id} className="rounded-md border border-border p-3">
              <div className="flex items-start gap-2">
                <div>
                  <div className="font-semibold">{tm.name} {tm.active ? null : <span className="text-[11px] text-foreground-muted">({t("team.inactive")})</span>}</div>
                  <div className="text-[12px] text-foreground-muted">{dept(tm.department_id)} · {t("team.team_head")}: {tm.team_head_id ? name(tm.team_head_id) : "—"}</div>
                </div>
                {data.can_manage ? (
                  <div className="ml-auto flex gap-1">
                    <Button size="sm" variant="outline" onClick={() => setEditing(tm)} aria-label={t("team.edit")}><Pencil /></Button>
                    <Button size="sm" variant="outline" onClick={() => setDeleting(tm)} aria-label={t("team.delete")}><Trash2 /></Button>
                  </div>
                ) : null}
              </div>
              <ul className="mt-2 divide-y divide-border text-[12px]">
                {tm.members.map((m) => (
                  <li key={m.user_id} className="py-1"><b>{name(m.user_id)}</b> — {m.buyer_ids.map(buyer).join(", ") || <span className="text-foreground-subtle">{t("team.no_buyers")}</span>}</li>
                ))}
              </ul>
            </article>
          ))}
        </div>
      </section>

      {editing ? <TeamEditor team={editing} data={data} t={t} pending={pending} onClose={() => setEditing(null)} onSave={(body) => run(act("saveTeam", body, editing.id), t("team.saved"), () => setEditing(null))} /> : null}
      {heads ? (
        <Modal open onOpenChange={(o) => !o && setHeads(null)} title={t("team.heads_of", { department: dept(heads.department_id) })}
          footer={<><Button variant="outline" onClick={() => setHeads(null)}>{t("sheet.cancel")}</Button><Button disabled={pending} onClick={() => run(act("saveHeads", heads), t("team.saved"), () => setHeads(null))}><Save /> {t("sheet.save")}</Button></>}>
          <MultiPick options={data.users} value={heads.user_ids} onChange={(v) => setHeads((h) => ({ ...h, user_ids: v }))} ariaLabel={t("team.heads")} />
        </Modal>
      ) : null}
      <ConfirmDialog open={Boolean(deleting)} onOpenChange={(o) => !o && setDeleting(null)} title={t("team.delete_title", { name: deleting?.name ?? "" })} description={t("team.delete_text")} confirmLabel={t("team.delete")} cancelLabel={t("sheet.cancel")} destructive loading={pending} onConfirm={() => run(act("deleteTeam", deleting.id), t("team.deleted"), () => setDeleting(null))} />
    </div>
  );
}

function TeamEditor({ team, data, t, pending, onClose, onSave }) {
  const [f, setF] = useState(() => ({ name: team.name, department_id: team.department_id ?? "", team_head_id: team.team_head_id ?? "", active: team.active ?? true, members: team.members.map((m) => ({ ...m })) }));
  const set = (k, v) => setF((x) => ({ ...x, [k]: v }));
  const used = new Set(f.members.map((m) => m.user_id));
  return (
    <Modal open onOpenChange={(o) => !o && onClose()} title={t(team.id ? "team.edit" : "team.add")} className="max-w-3xl"
      footer={<><Button variant="outline" onClick={onClose}>{t("sheet.cancel")}</Button><Button disabled={pending || !f.name.trim()} onClick={() => onSave({ ...f, department_id: f.department_id || null, team_head_id: f.team_head_id || null })}><Save /> {t("sheet.save")}</Button></>}>
      <div className="flex flex-col gap-3 text-[13px]">
        <div className="grid gap-3 sm:grid-cols-3">
          <label className="flex flex-col gap-1 font-semibold">{t("team.name")}<Input value={f.name} onChange={(e) => set("name", e.target.value)} maxLength={120} /></label>
          <label className="flex flex-col gap-1 font-semibold">{t("team.department")}
            <select className={sel} value={f.department_id} onChange={(e) => set("department_id", e.target.value)}><option value="">—</option>{data.departments.map((d) => <option key={d.value} value={d.value}>{d.label}</option>)}</select>
          </label>
          <label className="flex flex-col gap-1 font-semibold">{t("team.team_head")}
            <select className={sel} value={f.team_head_id} onChange={(e) => set("team_head_id", e.target.value)}><option value="">—</option>{data.users.map((u) => <option key={u.value} value={u.value}>{u.label}</option>)}</select>
          </label>
        </div>
        <label className="flex items-center gap-2"><input type="checkbox" checked={f.active} onChange={(e) => set("active", e.target.checked)} /> {t("team.active")}</label>
        <div className="font-semibold">{t("team.merchandisers")}</div>
        {f.members.map((m, i) => (
          <div key={i} className="grid items-center gap-2 sm:grid-cols-[16rem_1fr_auto]">
            <select className={sel} value={m.user_id} onChange={(e) => set("members", f.members.map((x, j) => (j === i ? { ...x, user_id: e.target.value } : x)))} aria-label={t("team.merchandiser")}>
              <option value="">—</option>
              {data.users.filter((u) => u.value === m.user_id || !used.has(u.value)).map((u) => <option key={u.value} value={u.value}>{u.label}</option>)}
            </select>
            <MultiPick options={data.buyers} value={m.buyer_ids} onChange={(v) => set("members", f.members.map((x, j) => (j === i ? { ...x, buyer_ids: v } : x)))} placeholder={t("team.buyers")} ariaLabel={t("team.buyers")} />
            <Button type="button" variant="ghost" size="sm" onClick={() => set("members", f.members.filter((_, j) => j !== i))} aria-label={t("team.remove")}><X /></Button>
          </div>
        ))}
        <div><Button type="button" size="sm" variant="outline" onClick={() => set("members", [...f.members, { user_id: "", buyer_ids: [] }])}><Plus /> {t("team.add_merchandiser")}</Button></div>
        <p className="text-[12px] text-foreground-muted">{t("team.buyers_hint")}</p>
      </div>
    </Modal>
  );
}

export { TeamSetup };
