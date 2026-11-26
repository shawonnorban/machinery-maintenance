"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ListChecks, RotateCcw, Save, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Card, CardBody, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Merchandise Desk → Buyer Sample Mapping (AnnotechERP 06/01): pick a buyer,
 * tick the sample types they require, and override the source, size or
 * quantity where the buyer differs from the default. Buyers already mapped
 * are listed on the left.
 */
function SampleMapping({ buyers, mappedBuyerIds, load, save, canManage }) {
  const t = useT("merchandise");
  const router = useRouter();
  const toastManager = useToastManager();
  const [buyer, setBuyer] = useState("");
  const [rows, setRows] = useState([]);
  const [query, setQuery] = useState("");
  const [pending, startTransition] = useTransition();
  const mapped = useMemo(() => buyers.filter((b) => mappedBuyerIds.includes(b.value)).filter((b) => b.label.toLowerCase().includes(query.trim().toLowerCase())), [buyers, mappedBuyerIds, query]);

  useEffect(() => {
    if (!buyer) return;
    let live = true;
    load(buyer).then((result) => {
      if (live && result?.status === "success") setRows(result.rows);
    });
    return () => {
      live = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [buyer]);

  const set = (id, field, value) => setRows((current) => current.map((r) => (r.sample_type_id === id ? { ...r, [field]: value } : r)));

  function submit() {
    startTransition(async () => {
      const samples = rows
        .filter((r) => r.assigned)
        .map((r) => ({ sample_type_id: r.sample_type_id, source: r.source || null, size: r.size || null, quantity: r.quantity === "" || r.quantity === null ? null : Number(r.quantity) }));
      const result = await save(buyer, samples);
      if (result?.status === "success") {
        setRows(result.rows);
        toastManager.add({ title: t("master.saved"), type: "success" });
        router.refresh();
      } else {
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat()[0] : result?.message, type: "danger" });
      }
    });
  }

  const sources = ["IN_HOUSE", "SUBCONTRACT", "BUYER"].map((s) => ({ value: s, label: t(`master.opt.source.${s}`) }));

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <ListChecks className="size-4" /> {t("master.sample.title")}
        </CardTitle>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{t("master.sample.hint")}</p>
      </CardHeader>
      <CardBody className="flex flex-col gap-4">
        <fieldset className="rounded-lg border border-border p-4">
          <legend className="px-1 text-[13px] font-semibold">{t("master.sample.search_by")}</legend>
          <label className="flex max-w-md flex-col gap-1.5">
            <span className="text-[13px] font-semibold text-foreground">{t("master.f.buyer")}</span>
            <Select value={buyer} onValueChange={(v) => v && setBuyer(v)} options={buyers} placeholder={t("master.sample.select_buyer")} />
          </label>
        </fieldset>
        <div className="grid gap-4 lg:grid-cols-[300px_1fr]">
          <div className="rounded-lg border border-border">
            <div className="border-b border-border px-4 py-2.5 text-[13px] font-semibold uppercase">{t("master.sample.mapped")}</div>
            <div className="relative p-3">
              <Search className="pointer-events-none absolute top-1/2 left-6 size-4 -translate-y-1/2 text-foreground-subtle" />
              <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t("master.sample.search_buyers", { count: mappedBuyerIds.length })} className="pl-9" />
            </div>
            <ul className="divide-y divide-border">
              {mapped.map((b) => (
                <li key={b.value}>
                  <button type="button" onClick={() => setBuyer(b.value)} className={cn("w-full px-4 py-2 text-left text-sm hover:bg-surface-muted", buyer === b.value && "bg-brand-subtle font-semibold text-brand")}>
                    {b.label}
                  </button>
                </li>
              ))}
              {mapped.length === 0 ? <li className="px-4 py-3 text-sm text-foreground-muted">{t("master.sample.none_mapped")}</li> : null}
            </ul>
          </div>
          <div className="min-w-0 rounded-lg border border-border">
            <div className="border-b border-border px-4 py-2.5 text-[13px] font-semibold uppercase">{t("master.sample.types")}</div>
            {!buyer ? (
              <p className="px-4 py-10 text-center text-sm text-foreground-muted">{t("master.sample.choose")}</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full min-w-max text-sm">
                  <thead>
                    <tr className="bg-primary text-left text-primary-foreground">
                      <th className="w-10 px-3 py-2" />
                      <th className="px-3 py-2 font-semibold">{t("master.f.sample_type")}</th>
                      <th className="px-3 py-2 font-semibold">{t("master.f.lead_days")}</th>
                      <th className="px-3 py-2 font-semibold">{t("master.f.source")}</th>
                      <th className="px-3 py-2 font-semibold">{t("master.f.size")}</th>
                      <th className="px-3 py-2 font-semibold">{t("master.f.quantity")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {rows.map((r) => (
                      <tr key={r.sample_type_id} className="border-b border-border last:border-0">
                        <td className="px-3 py-1.5">
                          <Checkbox checked={r.assigned} onCheckedChange={(c) => set(r.sample_type_id, "assigned", c === true)} disabled={!canManage} aria-label={r.name} />
                        </td>
                        <td className="px-3 py-1.5">
                          {r.name} <span className="font-mono text-xs text-foreground-muted">{r.code}</span>
                        </td>
                        <td className="px-3 py-1.5 tabular-nums">{r.lead_days}</td>
                        <td className="px-3 py-1.5">
                          <div className="w-40">
                            <Select value={r.source ?? ""} onValueChange={(v) => set(r.sample_type_id, "source", v || null)} options={sources} placeholder="—" disabled={!r.assigned || !canManage} />
                          </div>
                        </td>
                        <td className="px-3 py-1.5">
                          <Input value={r.size ?? ""} onChange={(e) => set(r.sample_type_id, "size", e.target.value)} disabled={!r.assigned || !canManage} className="w-28" />
                        </td>
                        <td className="px-3 py-1.5">
                          <Input type="number" min="1" value={r.quantity ?? ""} onChange={(e) => set(r.sample_type_id, "quantity", e.target.value)} disabled={!r.assigned || !canManage} className="w-24" />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
        {buyer && canManage ? (
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => load(buyer).then((r) => r?.status === "success" && setRows(r.rows))} disabled={pending}>
              <RotateCcw /> {t("master.reset")}
            </Button>
            <Button onClick={submit} loading={pending}>
              <Save /> {t("master.save")}
            </Button>
          </div>
        ) : null}
      </CardBody>
    </Card>
  );
}

export { SampleMapping };
