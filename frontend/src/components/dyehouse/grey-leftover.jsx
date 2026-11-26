"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ArchiveRestore, PackageOpen, RotateCcw } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const today = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" });
const kg = (v) => Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: 3 });

/**
 * Dye House → Batch (Bulk) → Grey to Leftover Transfer (AnnotechERP 17/03):
 * grey still on a batch in the batch store, line by line. Tick what is going
 * to left-over, set the weight — what the line has left is offered, and a
 * weighed roll goes whole — pick the left-over store, and Make Transfer
 * writes it as Knit Production's Batch to Leftover movements. Revert undoes
 * the ticks.
 *
 * @param {{ lots: object[], stores: object[], canManage: boolean, make: Function }} props
 */
function GreyLeftover({ lots, stores, canManage, make }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const toastManager = useToastManager();
  const [picked, setPicked] = useState({});
  const [store, setStore] = useState("");
  const [date, setDate] = useState(today());
  const [pending, startTransition] = useTransition();
  const g = (k, p) => t(`batch.g2l.${k}`, p);
  const f = (k) => t(`batch.f.${k}`);
  const warn = (title) => toastManager.add({ title, type: "danger" });
  const set = (lot, patch) => setPicked((p) => ({ ...p, [lot.lot_id]: { on: false, qty: String(lot.balance), ...p[lot.lot_id], ...patch } }));

  function submit() {
    const lines = Object.entries(picked)
      .filter(([, v]) => v.on)
      .map(([lot_id, v]) => ({ lot_id, qty: Number(v.qty) }));
    if (!lines.length) return warn(g("pick"));
    startTransition(async () => {
      const r = await make({ store_id: store || null, transfer_date: date, lines });
      if (r?.status === "success") {
        setPicked({});
        toastManager.add({ title: g("made", { nos: r.data.transfers.join(", ") }), type: "success" });
        router.refresh();
      } else warn(r?.errors ? Object.values(r.errors).flat()[0] : r?.message);
    });
  }

  const rolls = lots.reduce((n, l) => n + (l.rolls || (l.roll_id ? 1 : 0)), 0);

  return (
    <div className="flex flex-col gap-4">
      <div className="rounded-lg border border-border bg-surface p-5">
        <h2 className="flex items-center gap-2 text-base font-semibold">
          <ArchiveRestore className="size-4" /> {g("title")}
        </h2>
        <p className="mt-0.5 text-[13px] text-foreground-muted">{g("hint")}</p>
        {lots.length === 0 ? (
          <div className="flex flex-col items-center gap-2 px-4 py-10 text-center">
            <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
              <PackageOpen className="size-5" />
            </span>
            <p className="text-sm font-semibold text-foreground">{g("empty")}</p>
            <p className="max-w-sm text-[13px] text-foreground-muted">{g("empty_hint")}</p>
          </div>
        ) : (
          <div className="mt-4 overflow-x-auto rounded-md border border-border">
            <table className="w-full min-w-max text-sm">
              <thead>
                <tr className="bg-brand text-left text-brand-foreground">
                  <th className="w-10 px-3 py-2.5" />
                  <th className="px-3 py-2.5 font-semibold">{f("batch_no")}</th>
                  <th className="px-3 py-2.5 font-semibold">{f("store")}</th>
                  <th className="px-3 py-2.5 font-semibold">{f("buyer")}</th>
                  <th className="px-3 py-2.5 font-semibold">{f("order_no")}</th>
                  <th className="px-3 py-2.5 font-semibold">{f("fabric")}</th>
                  <th className="px-3 py-2.5 font-semibold">{f("color")}</th>
                  <th className="px-3 py-2.5 font-semibold">{f("roll")}</th>
                  <th className="px-3 py-2.5 text-right font-semibold">{f("balance")}</th>
                  <th className="px-3 py-2.5 text-right font-semibold">{f("transfer_kg")}</th>
                </tr>
              </thead>
              <tbody>
                {lots.map((l) => (
                  <tr key={l.lot_id} className="border-b border-border last:border-0">
                    <td className="px-3 py-1.5">
                      <Checkbox checked={Boolean(picked[l.lot_id]?.on)} onCheckedChange={(v) => set(l, { on: v === true })} disabled={!canManage} aria-label={l.label} />
                    </td>
                    <td className="px-3 py-1.5 font-mono">
                      {l.batch_no}
                      {l.batch_status ? <span className="ml-1.5 text-xs text-foreground-muted">({t(`batch.opt.batch_status.${l.batch_status}`)})</span> : null}
                    </td>
                    <td className="px-3 py-1.5">{l.store}</td>
                    <td className="px-3 py-1.5">{l.buyer ?? "—"}</td>
                    <td className="px-3 py-1.5">{l.order_no ?? "—"}</td>
                    <td className="px-3 py-1.5">{l.fabric}</td>
                    <td className="px-3 py-1.5">{l.colour ?? "—"}</td>
                    <td className="px-3 py-1.5 font-mono">{l.roll_no ?? "—"}</td>
                    <td className="px-3 py-1.5 text-right tabular-nums">{kg(l.balance)}</td>
                    <td className="px-3 py-1.5">
                      <Input type="number" min="0" step="0.001" value={picked[l.lot_id]?.qty ?? String(l.balance)} onChange={(e) => set(l, { on: true, qty: e.target.value })} disabled={!canManage || Boolean(l.roll_id)} className="ml-auto w-28 text-right" aria-label={f("transfer_kg")} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <p className="mt-3 text-[13px] text-foreground-muted">{g("footer", { n: rolls })}</p>
      </div>
      {canManage ? (
        <div className="flex flex-wrap items-end justify-end gap-3 rounded-lg border border-border bg-surface p-5">
          <label className="flex min-w-56 flex-col gap-1.5">
            <span className="text-[13px] font-semibold">{f("leftover_store")}</span>
            <Select value={store} onValueChange={(v) => setStore(v ?? "")} options={stores} placeholder={g("store_ph")} />
          </label>
          <label className="flex flex-col gap-1.5">
            <span className="text-[13px] font-semibold">{f("transfer_date")}</span>
            <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="w-44" />
          </label>
          <Button variant="outline" onClick={() => setPicked({})} disabled={pending}>
            <RotateCcw /> {g("revert")}
          </Button>
          <Button onClick={submit} loading={pending} disabled={!lots.length}>
            <ArchiveRestore /> {g("make")}
          </Button>
        </div>
      ) : null}
    </div>
  );
}

export { GreyLeftover };
