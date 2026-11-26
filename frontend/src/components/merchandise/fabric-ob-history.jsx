"use client";

import { useState, useTransition } from "react";
import { Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Alert } from "@/components/ui/alert";
import { useT } from "@/lib/i18n";
import { clockFormat } from "@/lib/clock";

const stamp = clockFormat({ day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Dhaka" });

/**
 * Purchased Fabric Opening Balance → Approval History (AnnotechERP 06/06),
 * as Brickland has it: the opening balances on the left, searchable; picking
 * one loads beside it every workflow step it has taken — who, from what to
 * what, when, with any remarks. Nothing is read until one is picked.
 *
 * @param {{ balances: {ob_no: string, status: string, ob_date: string}[], load: Function }} props
 */
function FabricObHistory({ balances, load }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.fob.${k}`, p);
  const [query, setQuery] = useState("");
  const [picked, setPicked] = useState(null);
  const [steps, setSteps] = useState([]);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const shown = balances.filter((b) => b.ob_no.toLowerCase().includes(query.trim().toLowerCase()));

  function choose(b) {
    setPicked(b);
    startTransition(async () => {
      const result = await load(b.ob_no);
      if (result?.status !== "success") return setError(result?.message ?? "");
      setError(null);
      setSteps(result.data.steps);
    });
  }

  const card = "rounded-xl border border-border bg-surface";
  return (
    <div className="grid gap-4 lg:grid-cols-[20rem_1fr]">
      <section className={cn(card, "self-start p-4")}>
        <h2 className="text-base font-semibold text-foreground">{m("balances")}</h2>
        <div className="relative mt-3">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-foreground-muted" />
          <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={m("search_obs")} className="pl-8" aria-label={m("search_obs")} />
        </div>
        <ul className="mt-3 flex flex-col gap-1">
          {shown.map((b) => (
            <li key={b.ob_no}>
              <button type="button" onClick={() => choose(b)} className={cn("flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-muted", picked?.ob_no === b.ob_no && "bg-brand-subtle font-semibold text-brand")}>
                <span className="font-mono text-xs">{b.ob_no}</span>
                <span className="rounded-full bg-surface-muted px-2 text-xs text-foreground-muted">{m(`status.${b.status}`)}</span>
              </button>
            </li>
          ))}
          {shown.length === 0 ? <li className="px-3 py-6 text-center text-[13px] text-foreground-muted">{m("no_ob")}</li> : null}
        </ul>
      </section>

      <section className={cn(card, "overflow-hidden")}>
        <div className="px-5 py-4">
          <h2 className="text-base font-semibold text-foreground">
            {m("steps")}
            {picked ? ` — ${picked.ob_no}` : ""}
          </h2>
        </div>
        {error ? <Alert variant="danger">{error}</Alert> : null}
        {!picked ? (
          <div className="px-5 pb-10 text-center">
            <p className="text-sm font-semibold text-foreground">{m("select_ob")}</p>
            <p className="mt-1 text-xs text-foreground-muted">{m("pick_ob")}</p>
            <p className="mt-1 text-xs text-foreground-muted">{m("pick_ob_hint")}</p>
          </div>
        ) : (
          <div className={cn("overflow-x-auto", pending && "opacity-60")}>
            <table className="w-full text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {["step", "from", "to", "by", "at", "remarks"].map((k) => (
                    <th key={k} className="px-4 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap">
                      {m(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {steps.map((s, i) => (
                  <tr key={`${s.at}-${i}`} className="border-t border-border">
                    <td className="px-4 py-2.5 font-medium">{m(`action.${s.action}`)}</td>
                    <td className="px-4 py-2.5">{s.from ? m(`status.${s.from}`) : "—"}</td>
                    <td className="px-4 py-2.5">{m(`status.${s.to}`)}</td>
                    <td className="px-4 py-2.5">{s.by ?? "—"}</td>
                    <td className="px-4 py-2.5 whitespace-nowrap">{stamp.format(new Date(s.at))}</td>
                    <td className="px-4 py-2.5">{s.remarks ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
}

export { FabricObHistory };
