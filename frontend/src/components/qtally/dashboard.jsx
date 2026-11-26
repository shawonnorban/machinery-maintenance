"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { BarChart3, Clock, LayoutDashboard, ShieldAlert } from "lucide-react";
import { Input } from "@/components/ui/input";
import { useLocale } from "next-intl";
import { SECTIONS, useQt, useFilters, num, pct, day, dhuTone, achievementTone, Panel, Table, Field, Select, Kpi, SectionPill, FilterCard } from "./common";
import { hour12 } from "@/lib/clock";

/*
 * Quality Tally → Dashboard (AnnotechERP 36): one day on the floor — every
 * section's checked, pass, target and DHU; every line and job; the day's top
 * defects and each hour. Today's board re-reads itself every minute.
 */

function QtDashboard({ data, lookups, query, fixed }) {
  const p = useQt();
  const router = useRouter();
  const bn = useLocale() === "bn";
  const { apply, pending } = useFilters(query);
  const [date, setDate] = useState(query.date ?? data.date);
  const [section, setSection] = useState(query.section ?? "");
  const live = data.date === lookups.today;
  const t = data.totals;

  useEffect(() => {
    if (!live) return undefined;
    const timer = setInterval(() => router.refresh(), 60_000);
    return () => clearInterval(timer);
  }, [live, router]);

  const sheetHref = (r) => `/quality-tally/${r.section.toLowerCase()}-sheet?${new URLSearchParams({ date: data.date, line_id: r.line_id, job_id: r.job_id })}`;
  const cols = [
    ...(fixed ? [] : [{ key: "section", label: p("col.section"), render: (r) => <SectionPill section={r.section} /> }]),
    { key: "line", label: p("col.line"), render: (r) => <Link href={sheetHref(r)} className="font-semibold text-brand hover:underline">{r.line}</Link> },
    { key: "job", label: p("col.job"), render: (r) => (<><b>{r.job_no ?? "—"}</b><div className="text-xs text-foreground-muted">{[r.buyer_name, r.style, r.color].filter(Boolean).join(" · ")}</div></>) },
    { key: "target", label: p("col.target"), align: "right", render: (r) => num(r.target) },
    { key: "checked", label: p("col.checked"), align: "right", render: (r) => num(r.checked) },
    { key: "rft", label: p("action.RFT"), align: "right", render: (r) => num(r.rft) },
    { key: "defect", label: p("action.DEFECT"), align: "right", render: (r) => num(r.defect) },
    { key: "rectify", label: p("action.RECTIFY"), align: "right", render: (r) => num(r.rectify) },
    { key: "reject", label: p("action.REJECT"), align: "right", render: (r) => num(r.reject) },
    { key: "pass", label: p("col.pass"), align: "right", render: (r) => <b>{num(r.pass)}</b> },
    { key: "achievement", label: p("col.achievement"), align: "right", render: (r) => <span className={achievementTone(r.achievement)}>{pct(r.achievement)}</span> },
    { key: "dhu", label: p("col.dhu"), align: "right", render: (r) => <span className={dhuTone(r.dhu)}>{pct(r.dhu)}</span> },
    { key: "rft_pct", label: p("col.rft_pct"), align: "right", render: (r) => pct(r.rft_pct) },
    { key: "efficiency", label: p("col.efficiency"), align: "right", render: (r) => pct(r.efficiency) },
  ];

  const hours = [...new Set(data.hourly.map((h) => h.hour))].sort((a, b) => a - b);
  const sections = Object.keys(data.sections);

  return (
    <>
      <FilterCard
        onApply={() => apply(fixed ? { date } : { date, section })}
        pending={pending}
        applyLabel={p("apply")}
        extra={<span className="ml-auto text-xs text-foreground-muted">{live ? p("dash.live") : p("dash.past", { date: day(data.date) })}</span>}
      >
        <Field label={p("f.date")}>
          <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="w-44" />
        </Field>
        {fixed ? null : (
          <Field label={p("f.section")}>
            <Select value={section} onChange={setSection} options={SECTIONS.map((s) => ({ value: s, label: p(`section.${s}`) }))} placeholder={p("all_sections")} />
          </Field>
        )}
      </FilterCard>

      <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
        <Kpi label={p("kpi.target")} value={num(t.target)} tone="muted" sub={t.mixed ? p("dash.pick_section") : null} />
        <Kpi label={p("kpi.checked")} value={num(t.checked)} tone="info" />
        <Kpi label={p("kpi.pass")} value={num(t.pass)} tone="success" sub={t.mixed ? p("dash.pick_section") : null} />
        <Kpi label={p("kpi.achievement")} value={pct(t.achievement)} tone="brand" valueClass={achievementTone(t.achievement)} sub={t.mixed ? p("dash.pick_section") : null} />
        <Kpi label={p("kpi.dhu")} value={pct(t.dhu)} tone="warning" valueClass={dhuTone(t.dhu)} sub={p("kpi.tags", { n: num(t.defect_tags) })} />
        <Kpi label={p("kpi.rft_pct")} value={pct(t.rft_pct)} tone="success" />
        <Kpi label={p("kpi.rectify")} value={num(t.rectify)} tone="info" />
        <Kpi label={p("kpi.reject")} value={num(t.reject)} tone="danger" sub={pct(t.reject_pct)} />
      </div>

      <div className={fixed && !(fixed === "CUTTING" && data.panels?.reports) ? "hidden" : "mb-4 grid grid-cols-1 gap-3 md:grid-cols-3"}>
        {sections.map((s) => {
          const x = data.sections[s];
          if (s === "CUTTING" && data.panels && data.panels.reports > 0) {
            const c = data.panels;
            return (
              <div key={s} className="rounded-lg border border-border bg-surface p-4">
                <div className="flex items-center justify-between">
                  <SectionPill section={s} />
                  <Link href="/quality-tally/cut-panel-inspection" className="text-xs font-semibold text-brand hover:underline">{p("dash.panel_reports", { n: c.reports })}</Link>
                </div>
                <div className="mt-3 grid grid-cols-3 gap-2 text-center">
                  <Mini label={p("cpi.checked_panels")} value={num(c.checked)} />
                  <Mini label={p("cpi.reject_panels")} value={num(c.reject)} cls="text-danger" />
                  <Mini label={p("cpi.reject_pct")} value={pct(c.reject_pct)} cls={dhuTone(c.reject_pct)} />
                </div>
              </div>
            );
          }
          return (
            <div key={s} className="rounded-lg border border-border bg-surface p-4">
              <div className="flex items-center justify-between">
                <SectionPill section={s} />
                <span className="text-xs text-foreground-muted">{p("dash.lines", { n: x.lines })}</span>
              </div>
              <div className="mt-3 grid grid-cols-3 gap-2 text-center">
                <Mini label={p("kpi.pass")} value={num(x.pass)} sub={x.target ? `/ ${num(x.target)}` : null} />
                <Mini label={p("kpi.dhu")} value={pct(x.dhu)} cls={dhuTone(x.dhu)} />
                <Mini label={p("kpi.rft_pct")} value={pct(x.rft_pct)} />
              </div>
            </div>
          );
        })}
      </div>

      <Panel title={p("dash.lines_title")} subtitle={p("dash.lines_hint")} icon={LayoutDashboard} className="mb-4">
        <Table
          columns={cols}
          rows={data.rows}
          rowKey={(r) => r.key}
          empty={p("empty.dashboard")}
          foot={{ [fixed ? "line" : "section"]: p("total"), target: num(t.target), checked: num(t.checked), rft: num(t.rft), defect: num(t.defect), rectify: num(t.rectify), reject: num(t.reject), pass: num(t.pass), achievement: pct(t.achievement), dhu: pct(t.dhu), rft_pct: pct(t.rft_pct) }}
        />
      </Panel>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <Panel title={p("dash.top_defects")} icon={ShieldAlert}>
          {data.top_defects.length === 0 ? (
            <p className="px-5 py-8 text-center text-sm text-foreground-muted">{p("empty.no_defects")}</p>
          ) : (
            <ul className="divide-y divide-border">
              {data.top_defects.map((d, i) => {
                const max = data.top_defects[0].count || 1;
                return (
                  <li key={`${d.section}${d.code}`} className="flex items-center gap-3 px-5 py-2.5">
                    <span className="w-5 text-right text-xs font-bold text-foreground-muted">{i + 1}</span>
                    {fixed ? null : <SectionPill section={d.section} />}
                    <span className="min-w-0 flex-1 truncate text-sm">
                      <b>{d.code}</b> — {bn && d.name_bn ? d.name_bn : d.name}
                    </span>
                    <span className="hidden h-2 w-32 overflow-hidden rounded-full bg-surface-muted sm:block">
                      <span className="block h-full rounded-full bg-warning" style={{ width: `${(d.count / max) * 100}%` }} />
                    </span>
                    <span className="w-12 text-right font-semibold tabular-nums">{num(d.count)}</span>
                  </li>
                );
              })}
            </ul>
          )}
        </Panel>
        <Panel title={p("dash.by_hour")} icon={Clock}>
          {hours.length === 0 ? (
            <p className="px-5 py-8 text-center text-sm text-foreground-muted">{p("empty.no_taps")}</p>
          ) : (
            <Table
              dense
              columns={[
                { key: "hour", label: p("col.hour"), render: (r) => hour12(r.hour) },
                ...sections.flatMap((s) => [
                  { key: `${s}_pass`, label: `${p(`section_short.${s}`)} ${p("col.pass")}`, align: "right", render: (r) => num(r[s]?.pass) },
                  { key: `${s}_dhu`, label: `${p(`section_short.${s}`)} ${p("col.dhu")}`, align: "right", render: (r) => <span className={dhuTone(r[s]?.dhu)}>{pct(r[s]?.dhu)}</span> },
                ]),
              ]}
              rows={hours.map((h) => ({ key: h, hour: h, ...Object.fromEntries(data.hourly.filter((x) => x.hour === h).map((x) => [x.section, x])) }))}
              empty=""
            />
          )}
        </Panel>
      </div>
      <p className="mt-3 flex items-center gap-1.5 text-xs text-foreground-muted">
        <BarChart3 className="size-3.5" aria-hidden /> {p("dash.formula")}
      </p>
    </>
  );
}

function Mini({ label, value, sub, cls }) {
  return (
    <div>
      <p className="text-[11px] font-semibold text-foreground-muted uppercase">{label}</p>
      <p className={`text-lg font-bold tabular-nums ${cls ?? "text-foreground"}`}>{value}</p>
      {sub ? <p className="text-xs text-foreground-muted">{sub}</p> : null}
    </div>
  );
}

export { QtDashboard };
