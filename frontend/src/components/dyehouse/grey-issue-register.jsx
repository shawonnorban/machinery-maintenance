"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { PackageCheck } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { RecordsGrid } from "@/components/erp/records-grid";
import { useT } from "@/lib/i18n";

const today = () => new Date().toLocaleDateString("en-CA", { timeZone: "Asia/Dhaka" });
const kg = (v) => Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: 3 });

/**
 * Dye House → Batch (Bulk) → Bulk Grey Fabric Issue Register (AnnotechERP
 * 17/03): every bulk requisition with what was asked and what the batch store
 * has issued; Issue opens the requisition's sheet — its lines, the batch-store
 * stock lines carrying the card's number, and what has gone already — and the
 * store ticks what it releases, at the weight it releases (a weighed roll
 * leaves whole), off the grey ledger.
 *
 * @param {{ rows: object[], canIssue: boolean, load: Function, issue: Function }} props
 */
function GreyIssueRegister({ rows, canIssue, load, issue }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const toastManager = useToastManager();
  const [sheet, setSheet] = useState(null);
  const [picked, setPicked] = useState({});
  const [date, setDate] = useState(today());
  const [pending, startTransition] = useTransition();
  const b = (k, p) => t(`batch.${k}`, p);
  const f = (k) => t(`batch.f.${k}`);
  const warn = (title) => toastManager.add({ title, type: "danger" });

  function open(row) {
    startTransition(async () => {
      const r = await load(row.req_no);
      if (r?.status === "success") {
        setSheet(r.sheet);
        setPicked({});
        setDate(today());
      } else warn(r?.message);
    });
  }

  function save() {
    const lines = Object.entries(picked)
      .filter(([, v]) => v.on)
      .map(([lot_id, v]) => ({ lot_id, qty: Number(v.qty) }));
    if (!lines.length) return warn(b("issue.pick"));
    startTransition(async () => {
      const r = await issue(sheet.req_no, { issue_date: date, lines });
      if (r?.status === "success") {
        setSheet(r.sheet);
        setPicked({});
        toastManager.add({ title: b("issue.saved", { no: sheet.req_no }), type: "success" });
        router.refresh();
      } else warn(r?.errors ? Object.values(r.errors).flat()[0] : r?.message);
    });
  }

  const tick = (lot, on) => setPicked((p) => ({ ...p, [lot.lot_id]: { on, qty: p[lot.lot_id]?.qty ?? String(lot.balance) } }));
  const left = sheet ? Math.max(0, sheet.req_kg - sheet.issue_kg) : 0;

  return (
    <>
      <RecordsGrid
        exportName="bulk-grey-issue-register"
        rows={rows}
        columns={[
          { key: "req_no", label: f("req_no"), type: "mono" },
          { key: "req_date", label: f("req_date"), type: "date" },
          { key: "req_kg", label: f("req_kg"), type: "number", align: "right" },
          { key: "issue_kg", label: f("issue_kg"), type: "number", align: "right" },
          { key: "batch_no", label: f("batch_no"), type: "mono" },
          { key: "items", label: f("items"), type: "number", align: "right" },
          { key: "type_label", label: f("req_type") },
          { key: "company", label: f("company") },
        ]}
        rowOpen={{ label: b("issue.action"), onOpen: open }}
      />
      <Modal open={sheet !== null} onOpenChange={(o) => !o && setSheet(null)} title={sheet ? b("issue.title", { no: sheet.req_no }) : ""} description={b("issue.hint")} className="max-h-[92vh] max-w-4xl overflow-y-auto">
        {sheet ? (
          <div className="flex flex-col gap-4 text-sm">
            <p className="text-foreground-muted">
              {sheet.batch_no} · {f("req_kg")} {kg(sheet.req_kg)} · {f("issue_kg")} {kg(sheet.issue_kg)} · {b("issue.left", { qty: kg(left) })}
            </p>
            <section>
              <h3 className="mb-2 font-semibold">{b("issue.lines")}</h3>
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-brand text-left text-brand-foreground">
                    <th className="px-3 py-2 font-semibold">{f("kind")}</th>
                    <th className="px-3 py-2 font-semibold">{f("description")}</th>
                    <th className="px-3 py-2 text-right font-semibold">{f("req_kg")}</th>
                  </tr>
                </thead>
                <tbody>
                  {sheet.lines.map((l, i) => (
                    <tr key={i} className="border-b border-border">
                      <td className="px-3 py-1.5">{b(`opt.kind.${l.kind}`)}</td>
                      <td className="px-3 py-1.5">{l.description}</td>
                      <td className="px-3 py-1.5 text-right tabular-nums">{kg(l.req_kg)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </section>
            <section>
              <h3 className="mb-2 font-semibold">{b("issue.lots", { no: sheet.batch_no })}</h3>
              {sheet.lots.length === 0 ? (
                <p className="text-foreground-muted">{b("issue.no_lots")}</p>
              ) : (
                <table className="w-full text-sm">
                  <thead>
                    <tr className="bg-brand text-left text-brand-foreground">
                      <th className="w-10 px-3 py-2" />
                      <th className="px-3 py-2 font-semibold">{f("store")}</th>
                      <th className="px-3 py-2 font-semibold">{f("roll")}</th>
                      <th className="px-3 py-2 font-semibold">{f("fabric")}</th>
                      <th className="px-3 py-2 font-semibold">{f("order")}</th>
                      <th className="px-3 py-2 text-right font-semibold">{f("balance")}</th>
                      <th className="px-3 py-2 text-right font-semibold">{f("issue_qty")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {sheet.lots.map((lot) => (
                      <tr key={lot.lot_id} className="border-b border-border">
                        <td className="px-3 py-1.5">
                          <Checkbox checked={Boolean(picked[lot.lot_id]?.on)} onCheckedChange={(v) => tick(lot, v === true)} disabled={!canIssue} aria-label={lot.label} />
                        </td>
                        <td className="px-3 py-1.5">{lot.store}</td>
                        <td className="px-3 py-1.5 font-mono">{lot.roll_no ?? "—"}</td>
                        <td className="px-3 py-1.5">{lot.fabric}</td>
                        <td className="px-3 py-1.5">{lot.order_no ?? "—"}</td>
                        <td className="px-3 py-1.5 text-right tabular-nums">{kg(lot.balance)}</td>
                        <td className="px-3 py-1.5">
                          <Input
                            type="number"
                            min="0"
                            step="0.001"
                            value={picked[lot.lot_id]?.qty ?? String(lot.balance)}
                            onChange={(e) => setPicked((p) => ({ ...p, [lot.lot_id]: { on: true, qty: e.target.value } }))}
                            disabled={!canIssue || Boolean(lot.roll_id)}
                            className="ml-auto w-28 text-right"
                            aria-label={f("issue_qty")}
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </section>
            <section>
              <h3 className="mb-2 font-semibold">{b("issue.issued")}</h3>
              {sheet.issues.length === 0 ? (
                <p className="text-foreground-muted">{b("issue.none_issued")}</p>
              ) : (
                <ul className="divide-y divide-border rounded-md border border-border">
                  {sheet.issues.map((x, i) => (
                    <li key={i} className="flex justify-between gap-3 px-3 py-1.5">
                      <span>
                        {x.issue_date} · {x.lot}
                      </span>
                      <span className="tabular-nums">{kg(x.qty)} kg</span>
                    </li>
                  ))}
                </ul>
              )}
            </section>
            {canIssue ? (
              <div className="flex flex-wrap items-end justify-end gap-3 border-t border-border pt-4">
                <label className="flex flex-col gap-1.5">
                  <span className="text-[13px] font-semibold">{f("issue_date")}</span>
                  <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="w-44" />
                </label>
                <Button variant="outline" onClick={() => setSheet(null)} disabled={pending}>
                  {b("issue.close")}
                </Button>
                <Button onClick={save} loading={pending} disabled={!sheet.lots.length}>
                  <PackageCheck /> {b("issue.save")}
                </Button>
              </div>
            ) : null}
          </div>
        ) : null}
      </Modal>
    </>
  );
}

export { GreyIssueRegister };
