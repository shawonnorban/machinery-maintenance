"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ListChecks, Plus, Save, Trash2 } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Dye House → Dye Chemical Audit Recipe (AnnotechERP 17/01): load a buyer to
 * see the chemical checks their audit puts a recipe through; type over Alter
 * Item Name, mark the checks being dropped, add or remove checks, and Update
 * writes them all at once.
 *
 * @param {{ buyers: {value: string, label: string, checks: number}[], load: Function, save: Function, canManage: boolean }} props
 */
function AuditChecks({ buyers, load, save, canManage }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const toastManager = useToastManager();
  const [buyer, setBuyer] = useState("");
  const [loaded, setLoaded] = useState(null);
  const [rows, setRows] = useState([]);
  const [pending, startTransition] = useTransition();
  const label = (k, p) => t(`master.audit.${k}`, p);

  function doLoad() {
    startTransition(async () => {
      const result = await load(buyer);
      if (result?.status === "success") {
        setLoaded(buyer);
        setRows(result.rows.map((r) => ({ ...r, alter_item_name: r.alter_item_name ?? "" })));
      }
    });
  }

  function submit() {
    startTransition(async () => {
      const checks = rows.filter((r) => r.item_name.trim()).map((r) => ({ item_name: r.item_name.trim(), alter_item_name: r.alter_item_name || null, dropped: r.dropped }));
      const result = await save(loaded, checks);
      if (result?.status === "success") {
        setRows(result.rows.map((r) => ({ ...r, alter_item_name: r.alter_item_name ?? "" })));
        toastManager.add({ title: t("master.saved"), type: "success" });
        router.refresh();
      } else {
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat()[0] : result?.message, type: "danger" });
      }
    });
  }

  const set = (i, field, value) => setRows((current) => current.map((r, j) => (j === i ? { ...r, [field]: value } : r)));

  return (
    <div className="flex flex-col gap-4">
      <div className="rounded-lg border border-border bg-surface px-5 py-4">
        <h2 className="text-base font-semibold">{label("title")}</h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{label("hint")}</p>
        <div className="mt-3 flex flex-wrap items-end gap-3">
          <label className="flex min-w-72 flex-1 flex-col gap-1.5">
            <span className="text-[13px] font-semibold">{t("master.f.buyer")}</span>
            <Select value={buyer} onValueChange={(v) => v && setBuyer(v)} options={buyers.map((b) => ({ value: b.value, label: label("buyer_option", { buyer: b.label, count: b.checks }) }))} placeholder={label("select_buyer")} />
          </label>
          <Button onClick={doLoad} disabled={!buyer} loading={pending && !loaded}>
            {label("load")}
          </Button>
        </div>
      </div>

      {!loaded ? (
        <div className="flex flex-col items-center gap-2 rounded-lg border border-border bg-surface px-5 py-14 text-center">
          <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
            <ListChecks className="size-5" />
          </span>
          <p className="text-sm font-semibold">{label("empty_title")}</p>
          <p className="max-w-md text-[13px] text-foreground-muted">{label("empty")}</p>
        </div>
      ) : (
        <div className="rounded-lg border border-border bg-surface">
          <div className="overflow-x-auto">
            <table className="w-full min-w-max text-sm">
              <thead>
                <tr className="bg-brand text-left text-brand-foreground">
                  <th className="px-3 py-2.5 font-semibold">{t("master.f.item_name")}</th>
                  <th className="px-3 py-2.5 font-semibold">{t("master.f.alter_item_name")}</th>
                  <th className="px-3 py-2.5 text-center font-semibold">{t("master.f.dropped")}</th>
                  <th className="w-10 px-3 py-2.5" />
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="px-4 py-8 text-center text-foreground-muted">
                      {label("no_checks")}
                    </td>
                  </tr>
                ) : (
                  rows.map((r, i) => (
                    <tr key={i} className="border-b border-border last:border-0">
                      <td className="px-3 py-1.5">
                        <Input value={r.item_name} onChange={(e) => set(i, "item_name", e.target.value)} disabled={!canManage} aria-label={t("master.f.item_name")} />
                      </td>
                      <td className="px-3 py-1.5">
                        <Input value={r.alter_item_name} onChange={(e) => set(i, "alter_item_name", e.target.value)} disabled={!canManage} aria-label={t("master.f.alter_item_name")} />
                      </td>
                      <td className="px-3 py-1.5 text-center">
                        <Checkbox checked={r.dropped} onCheckedChange={(c) => set(i, "dropped", c === true)} disabled={!canManage} aria-label={`${r.item_name} ${t("master.f.dropped")}`} />
                      </td>
                      <td className="px-3 py-1.5">
                        {canManage ? (
                          <Button variant="ghost" size="sm" onClick={() => setRows((current) => current.filter((_, j) => j !== i))} aria-label={t("master.remove")}>
                            <Trash2 />
                          </Button>
                        ) : null}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
          {canManage ? (
            <div className="flex gap-2 border-t border-border p-4">
              <Button variant="outline" onClick={() => setRows((current) => [...current, { item_name: "", alter_item_name: "", dropped: false }])}>
                <Plus /> {label("add_check")}
              </Button>
              <Button onClick={submit} loading={pending} className="ml-auto">
                <Save /> {label("update")}
              </Button>
            </div>
          ) : null}
        </div>
      )}
    </div>
  );
}

export { AuditChecks };
