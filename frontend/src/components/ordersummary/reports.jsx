"use client";

import { Fragment, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import { Printer, RotateCcw, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { printDocument } from "@/lib/print-document";
import { useT } from "@/lib/i18n";
import { shortDate } from "./common";

/*
 * Order Summary's reports (AnnotechERP 32), Norban's: LDD and RFI wise
 * Pending Summary (one report type at a time — fabric or trims booking, base
 * hanger, black seal, FFP/GSS — most urgent first), Buyer Month Wise (buyer
 * → department by month, LDD/ETD or RFI/SD) and the Audit Log.
 */

const n0 = (v) => Number(v ?? 0).toLocaleString("en-US", { maximumFractionDigits: 0 });
const usd = (v) => `$${Number(v ?? 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const URGENCY = { critical: "bg-danger text-white", high: "bg-[#fb923c] text-white", medium: "bg-[#fde047]", low: "bg-success-subtle text-success", none: "bg-surface-muted" };

function useGo() {
  const router = useRouter();
  const pathname = usePathname();
  return (values) => {
    const p = new URLSearchParams(Object.entries(values).filter(([, v]) => v !== "" && v !== null && v !== undefined));
    router.push(`${pathname}${p.size ? `?${p}` : ""}`, { scroll: false });
  };
}

function Filters({ fields, values, onApply, t, extra }) {
  const [draft, setDraft] = useState(values);
  return (
    <form className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-3 text-[12px]" onSubmit={(e) => { e.preventDefault(); onApply(draft); }}>
      {fields.map((f) => (
        <label key={f.name} className="flex flex-col gap-1 font-semibold">
          {f.label}
          {f.options ? (
            <select className="h-8 min-w-44 rounded border border-border bg-surface px-2" value={draft[f.name] ?? ""} onChange={(e) => setDraft((d) => ({ ...d, [f.name]: e.target.value }))}>
              {f.all === false ? null : <option value="">{t("sheet.all")}</option>}
              {f.options.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          ) : (
            <input type={f.type ?? "text"} className="h-8 rounded border border-border bg-surface px-2" value={draft[f.name] ?? ""} onChange={(e) => setDraft((d) => ({ ...d, [f.name]: e.target.value }))} />
          )}
        </label>
      ))}
      <Button type="submit" size="sm">
        <Search /> {t("sheet.filter")}
      </Button>
      <Button type="button" size="sm" variant="outline" onClick={() => onApply({})}>
        <RotateCcw /> {t("sheet.clear")}
      </Button>
      {extra}
    </form>
  );
}

function PendingReport({ basis, data, query }) {
  const t = useT("ordersummary");
  const go = useGo();
  const dateLabel = t(basis === "rfi" ? "report.rfi_date" : "report.ldd_date");
  const print = () =>
    printDocument({
      title: t(`screens.${basis}`),
      subtitle: t(`report.type.${data.type}`),
      orientation: "landscape",
      sections: [{
        columns: [t("report.urgency"), t("report.buyer"), t("report.item"), t("report.style"), t("report.job"), t("report.po"), { label: dateLabel }, { label: t("report.days"), align: "right" }, t("report.pending"), { label: t("report.qty"), align: "right" }, { label: t("report.value"), align: "right" }, t("report.status")].map((c) => (typeof c === "string" ? { label: c } : c)),
        rows: data.rows.map((r) => [t(`report.urgency_${r.urgency}`), r.buyer ?? "", r.item ?? "", r.style, r.job_no, r.po, shortDate(r.date), r.days ?? "", t(`report.pending_${r.pending}`), n0(r.qty), usd(r.value), r.status ?? ""]),
        empty: t("report.empty"),
      }],
    });
  return (
    <div className="flex flex-col gap-3 text-[12px]">
      <div className="grid grid-cols-2 gap-2 md:grid-cols-7">
        {["critical", "high", "medium", "low"].map((u) => (
          <div key={u} className={cn("rounded-md px-3 py-2", URGENCY[u])}>
            <div className="text-xl font-bold tabular-nums">{data.summary[u]}</div>
            <div className="text-[11px] font-semibold">{t(`report.urgency_${u}`)}</div>
          </div>
        ))}
        <div className="rounded-md border border-border bg-surface px-3 py-2"><div className="text-xl font-bold tabular-nums">{n0(data.summary.styles)}</div><div className="text-[11px] text-foreground-muted">{t("dash.styles")}</div></div>
        <div className="rounded-md border border-border bg-surface px-3 py-2"><div className="text-xl font-bold tabular-nums">{n0(data.summary.qty)}</div><div className="text-[11px] text-foreground-muted">{t("report.qty")}</div></div>
        <div className="rounded-md border border-border bg-surface px-3 py-2"><div className="text-xl font-bold tabular-nums">{usd(data.summary.value)}</div><div className="text-[11px] text-foreground-muted">{t("report.value")}</div></div>
      </div>
      <Filters
        t={t}
        values={{ type: data.type, buyer: query.buyer ?? "", from: query.from ?? "", to: query.to ?? "" }}
        onApply={go}
        fields={[
          { name: "type", label: t("report.report_type"), options: data.types.map((k) => ({ value: k, label: t(`report.type.${k}`) })), all: false },
          { name: "buyer", label: t("report.buyer"), options: data.buyers.map((b) => ({ value: b, label: b })) },
          { name: "from", label: t("report.from", { date: dateLabel }), type: "date" },
          { name: "to", label: t("report.to", { date: dateLabel }), type: "date" },
        ]}
        extra={<Button type="button" size="sm" variant="outline" className="ml-auto" onClick={print}><Printer /> {t("report.print")}</Button>}
      />
      <section className="overflow-x-auto rounded-lg border border-border bg-surface">
        <table className="w-full text-[12px]">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {[t("report.urgency"), t("report.buyer"), t("report.item"), t("report.style"), t("report.job"), t("report.po"), dateLabel, t("report.days"), t("report.pending"), t("report.qty"), t("report.value"), t("report.status")].map((h) => (
                <th key={h} className="px-2 py-2 text-left font-semibold whitespace-nowrap">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data.rows.length === 0 ? <tr><td colSpan={12} className="px-4 py-8 text-center text-foreground-muted">{t("report.empty")}</td></tr> : null}
            {data.rows.map((r, i) => (
              <tr key={i} className="border-t border-border">
                <td className="px-2 py-1.5"><span className={cn("rounded px-2 py-0.5 text-[11px] font-semibold", URGENCY[r.urgency])}>{t(`report.urgency_${r.urgency}`)}</span></td>
                <td className="px-2 py-1.5">{r.buyer}</td>
                <td className="px-2 py-1.5">{r.item}</td>
                <td className="px-2 py-1.5">{r.style}</td>
                <td className="px-2 py-1.5">{r.job_no}</td>
                <td className="px-2 py-1.5">{r.po}</td>
                <td className="px-2 py-1.5 whitespace-nowrap">{shortDate(r.date)}</td>
                <td className={cn("px-2 py-1.5 text-right tabular-nums", r.days !== null && r.days < 0 && "font-bold text-danger")}>{r.days ?? "—"}</td>
                <td className="px-2 py-1.5">{t(`report.pending_${r.pending}`)}</td>
                <td className="px-2 py-1.5 text-right tabular-nums">{n0(r.qty)}</td>
                <td className="px-2 py-1.5 text-right tabular-nums">{usd(r.value)}</td>
                <td className="px-2 py-1.5">{r.status ?? "—"}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>
    </div>
  );
}

const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

function BuyerMonthReport({ basis, data, query }) {
  const t = useT("ordersummary");
  const go = useGo();
  // "All" (user, 2026-11-23): Quantity, Styles and Value together, one line each in every cell.
  const [measure, setMeasure] = useState("all");
  const all = measure === "all";
  const one = (c, m) => (!c ? "" : m === "value" ? usd(c.value) : n0(c[m]));
  const fmt = (c) =>
    !all ? one(c, measure) : !c ? "" : (
      <>
        <div>{one(c, "qty")}</div>
        <div className="text-foreground-muted">{one(c, "styles")}</div>
        <div className="text-success">{one(c, "value")}</div>
      </>
    );
  const legend = (
    <td className="px-2 py-1.5 text-[11px] whitespace-nowrap text-foreground-muted">
      <div>{t("report.measure_qty")}</div>
      <div>{t("report.measure_styles")}</div>
      <div>{t("report.measure_value")}</div>
    </td>
  );
  const row = (label, cells, total, cls) => (
    <tr className={cn("border-t border-border", all && "align-top", cls)}>
      <td className="sticky left-0 bg-inherit px-2 py-1.5 whitespace-nowrap">{label}</td>
      {all ? legend : null}
      {MONTHS.map((_, i) => <td key={i} className="px-2 py-1.5 text-right whitespace-nowrap tabular-nums">{fmt(cells?.[i + 1])}</td>)}
      <td className="px-2 py-1.5 text-right font-bold whitespace-nowrap tabular-nums">{fmt(total)}</td>
    </tr>
  );
  return (
    <div className="flex flex-col gap-3 text-[12px]">
      <Filters
        t={t}
        values={{ basis, year: query.year ?? String(data.year), buyer: query.buyer ?? "" }}
        onApply={go}
        fields={[
          { name: "basis", label: t("report.basis"), options: [{ value: "ldd", label: t("report.ldd_date") }, { value: "rfi", label: t("report.rfi_date") }], all: false },
          { name: "year", label: t("report.year"), options: (data.years.length ? data.years : [String(data.year)]).map((y) => ({ value: y, label: y })), all: false },
          { name: "buyer", label: t("report.buyer"), options: data.buyer_list.map((b) => ({ value: b, label: b })) },
        ]}
        extra={
          <div className="ml-auto flex rounded-md border border-border p-0.5">
            {["all", "qty", "styles", "value"].map((m) => (
              <button key={m} type="button" onClick={() => setMeasure(m)} className={cn("rounded px-3 py-1 font-semibold", measure === m ? "bg-brand text-brand-foreground" : "text-foreground-muted")}>
                {t(`report.measure_${m}`)}
              </button>
            ))}
          </div>
        }
      />
      <section className="overflow-x-auto rounded-lg border border-border bg-surface">
        <table className="w-full text-[12px]">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              <th className="px-2 py-2 text-left">{t("report.buyer_department")}</th>
              {all ? <th className="px-2 py-2" /> : null}
              {MONTHS.map((m) => <th key={m} className="px-2 py-2 text-right">{m}</th>)}
              <th className="px-2 py-2 text-right">{t("report.total")}</th>
            </tr>
          </thead>
          <tbody>
            {data.buyers.length === 0 ? <tr><td colSpan={all ? 15 : 14} className="px-4 py-8 text-center text-foreground-muted">{t("report.empty")}</td></tr> : null}
            {data.buyers.map((b) => (
              <BuyerRows key={b.buyer || "-"} b={b} row={row} />
            ))}
            {row(t("report.grand_total"), data.months, data.total, "bg-brand-subtle font-bold")}
          </tbody>
        </table>
      </section>
    </div>
  );
}

function BuyerRows({ b, row }) {
  return (
    <>
      {row(<b>{b.buyer || "—"}</b>, b.months, b.total, "bg-surface-muted/60")}
      {b.departments.map((d) => (
        <Fragment key={d.department || "-"}>{row(<span className="pl-4">{d.department || "—"}</span>, d.months, d.total, "bg-surface")}</Fragment>
      ))}
    </>
  );
}

function AuditLog({ data, query }) {
  const t = useT("ordersummary");
  const go = useGo();
  return (
    <div className="flex flex-col gap-3 text-[12px]">
      <Filters
        t={t}
        values={{ from: query.from ?? "", to: query.to ?? "", kind: query.kind ?? "", user: query.user ?? "", q: query.q ?? "" }}
        onApply={go}
        fields={[
          { name: "from", label: t("audit.from"), type: "date" }, { name: "to", label: t("audit.to"), type: "date" },
          { name: "kind", label: t("audit.action"), options: ["CREATE", "EDIT", "DELETE", "RECALC"].map((k) => ({ value: k, label: t(`audit.kind.${k}`) })) },
          { name: "user", label: t("audit.user"), options: data.users },
          { name: "q", label: t("audit.search") },
        ]}
      />
      <section className="rounded-lg border border-border bg-surface">
        <ul className="divide-y divide-border">
          {data.entries.length === 0 ? <li className="px-4 py-8 text-center text-foreground-muted">{t("audit.empty")}</li> : null}
          {data.entries.map((e, i) => (
            <li key={i} className="px-4 py-3">
              <div className="flex flex-wrap items-center gap-2">
                <span className={cn("rounded px-2 py-0.5 text-[11px] font-semibold", e.kind === "DELETE" ? "bg-danger-subtle text-danger" : e.kind === "CREATE" ? "bg-success-subtle text-success" : e.kind === "RECALC" ? "bg-info-subtle text-info" : "bg-warning-subtle text-warning")}>{t(`audit.kind.${e.kind}`)}</span>
                <b>#{e.serial_no}</b>
                <span>{[e.buyer, e.order].filter(Boolean).join(" · ")}</span>
                <span className="ml-auto text-foreground-muted">{e.by ?? "—"} · {e.at ? new Date(e.at).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", dateStyle: "medium", timeStyle: "short", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : ""}</span>
              </div>
              {e.cells.length ? (
                <table className="mt-2 w-full max-w-4xl text-[11px]">
                  <tbody>
                    {e.cells.map((c, j) => (
                      <tr key={j} className="border-t border-border/60">
                        <td className="w-1/3 py-1 pr-2 font-medium">{c.column}</td>
                        <td className="w-1/3 py-1 pr-2 text-danger line-through decoration-danger/40">{c.old ?? "—"}</td>
                        <td className="w-1/3 py-1 text-success">{c.new ?? "—"}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : null}
            </li>
          ))}
        </ul>
        {data.page.last > 1 ? (
          <div className="flex justify-end gap-1 border-t border-border px-3 py-2">
            {Array.from({ length: data.page.last }, (_, i) => i + 1).filter((n) => n === 1 || n === data.page.last || Math.abs(n - data.page.current) <= 2).map((n) => (
              <Button key={n} size="sm" variant={n === data.page.current ? "primary" : "outline"} onClick={() => go({ ...query, page: String(n) })}>{n}</Button>
            ))}
          </div>
        ) : null}
      </section>
    </div>
  );
}

export { PendingReport, BuyerMonthReport, AuditLog };
