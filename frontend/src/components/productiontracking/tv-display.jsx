"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { cn } from "@/lib/utils";
import { usePt, num, pct, clock, LineCard, Legend } from "./common";

const COLS = { 1: "grid-cols-1", 2: "grid-cols-2", 3: "grid-cols-3", 4: "grid-cols-4", 6: "grid-cols-6" };

/**
 * The TV's own page: today's line cards of its saved lines, big, re-read on its
 * interval. `embedded`: the same screen previewed inside the ERP (no refresh loop).
 */
function TvDisplay({ data, embedded = false }) {
  const p = usePt();
  const router = useRouter();
  useEffect(() => {
    if (embedded) return undefined;
    const id = setInterval(() => router.refresh(), Math.max(15, data.refresh_seconds) * 1000);
    return () => clearInterval(id);
  }, [router, data.refresh_seconds, embedded]);
  const n = data.lines.length;
  const auto = n <= 1 ? 1 : n <= 4 ? 2 : n <= 9 ? 3 : n <= 16 ? 4 : 6;
  const cols = COLS[data.layout === "AUTO" ? auto : Number(data.layout)] ?? "grid-cols-3";
  const totalPct = data.totals.target ? (data.totals.actual / data.totals.target) * 100 : null;

  return (
    <main className={cn("bg-background", embedded ? "rounded-lg p-4" : "min-h-dvh p-6")}>
      <header className="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="text-sm font-semibold text-foreground-muted">{data.company}</p>
          <h1 className="text-3xl font-bold text-foreground">{data.name}</h1>
          <p className="text-foreground-muted">
            {p(`section.${data.section}`)} · {data.date} · {p("live.last")} {clock(data.refreshed_at)}
          </p>
        </div>
        <div className="text-right">
          <p className="text-sm font-semibold text-foreground-muted uppercase">{p("card.actual")} / {p("card.target")}</p>
          <p className="text-4xl font-bold text-foreground tabular-nums">
            {num(data.totals.actual)} / {num(data.totals.target)} <span className="text-2xl text-foreground-muted">({pct(totalPct)})</span>
          </p>
          <Legend meta={data} />
        </div>
      </header>
      <div className={cn("grid gap-4", cols)}>
        {data.lines.map((line) => (
          <LineCard key={line.id} line={line} showDefects={data.show_defects} big />
        ))}
      </div>
    </main>
  );
}

export { TvDisplay };
