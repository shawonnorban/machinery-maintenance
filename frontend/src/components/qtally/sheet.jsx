"use client";

import { useState } from "react";
import { useLocale } from "next-intl";
import { Printer, Sheet as SheetIcon, ShieldAlert, Users } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { printDocument } from "@/lib/print-document";
import { useQt, useFilters, num, pct, day, dhuTone, achievementTone, Panel, Table, Field, Select, Kpi, Notice, FilterCard } from "./common";

/*
 * Quality Tally → Reports → Tally Sheet (AnnotechERP 36): one line on one day,
 * hour by hour — RFT, defect, rectify, reject, checked, pass against the
 * hourly target, the running pass — and which defect was found in which
 * hour: the paper tally sheet a QC used to keep. Printed on A4 landscape.
 */

function TallySheet({ sheet, lookups, query, section }) {
  const p = useQt();
  const bn = useLocale() === "bn";
  const { apply, pending } = useFilters(query);
  const [v, setV] = useState({ date: query.date ?? lookups.today, line_id: query.line_id ?? "", job_id: query.job_id ?? "" });
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));
  const lines = lookups.lines[section] ?? [];

  const hourCols = [
    { key: "label", label: p("col.hour"), render: (r) => <b>{r.label}</b> },
    { key: "hourly_target", label: p("col.hourly_target"), align: "right", render: (r) => num(r.target) },
    { key: "rft", label: p("action.RFT"), align: "right", render: (r) => num(r.rft) },
    { key: "defect", label: p("action.DEFECT"), align: "right", render: (r) => num(r.defect) },
    { key: "rectify", label: p("action.RECTIFY"), align: "right", render: (r) => num(r.rectify) },
    { key: "reject", label: p("action.REJECT"), align: "right", render: (r) => num(r.reject) },
    { key: "checked", label: p("col.checked"), align: "right", render: (r) => num(r.checked) },
    { key: "pass", label: p("col.pass"), align: "right", render: (r) => <b>{num(r.pass)}</b> },
    { key: "variance", label: p("col.variance"), align: "right", render: (r) => (r.target ? <span className={r.pass >= r.target ? "text-success" : "text-danger"}>{num(r.pass - r.target)}</span> : "—") },
    { key: "cumulative_pass", label: p("col.cumulative"), align: "right", render: (r) => num(r.cumulative_pass) },
    { key: "dhu", label: p("col.dhu"), align: "right", render: (r) => <span className={dhuTone(r.dhu)}>{pct(r.dhu)}</span> },
  ];

  const t = sheet?.totals;
  const hours = sheet?.hours ?? [];
  const defectName = (d) => `${d.code} — ${bn && d.name_bn ? d.name_bn : d.name}`;
  const job = sheet?.jobs?.length === 1 ? sheet.jobs[0] : null;

  const print = () =>
    printDocument({
      title: p("sheet.print_title"),
      docNo: `${sheet.line ?? ""} · ${day(sheet.date)}`,
      orientation: "landscape",
      meta: [
        [p("f.date"), day(sheet.date)],
        [p("f.section"), p(`section.${sheet.section}`)],
        [p("f.line"), sheet.line],
        [p("f.job"), sheet.jobs.map((j) => `${j.job_no} (${j.style}${j.color ? ` · ${j.color}` : ""})`).join(", ") || "—"],
        [p("kpi.target"), num(sheet.target)],
        [p("kpi.hourly_target"), num(sheet.hourly_target)],
        [p("col.manpower"), num(sheet.manpower)],
        [p("kpi.efficiency"), pct(sheet.efficiency)],
      ],
      metaColumns: 4,
      sections: [
        {
          title: p("sheet.hourly"),
          columns: [
            { label: p("col.hour") }, { label: p("col.hourly_target"), align: "right" }, { label: p("action.RFT"), align: "right" }, { label: p("action.DEFECT"), align: "right" },
            { label: p("action.RECTIFY"), align: "right" }, { label: p("action.REJECT"), align: "right" }, { label: p("col.checked"), align: "right" }, { label: p("col.pass"), align: "right" },
            { label: p("col.cumulative"), align: "right" }, { label: p("col.dhu"), align: "right" },
          ],
          rows: hours.map((h) => [h.label, h.target ?? "", h.rft, h.defect, h.rectify, h.reject, h.checked, { v: h.pass, bold: true }, h.cumulative_pass, h.dhu === null ? "" : `${h.dhu}%`]),
          foot: [[p("total"), sheet.target ?? "", t.rft, t.defect, t.rectify, t.reject, t.checked, t.pass, t.pass, t.dhu === null ? "" : `${t.dhu}%`]],
        },
        {
          title: p("sheet.defects"),
          columns: [{ label: p("col.defect") }, ...hours.map((h) => ({ label: String(h.hour).padStart(2, "0"), align: "center" })), { label: p("total"), align: "right" }],
          rows: sheet.defects.map((d) => [defectName(d), ...hours.map((h) => d.by_hour[h.hour] ?? ""), { v: d.total, bold: true }]),
          empty: p("empty.no_defects"),
        },
      ],
      notes: [p("dash.formula"), sheet.inspectors.length ? `${p("sheet.inspectors")}: ${sheet.inspectors.map((i) => `${i.name} (${i.taps})`).join(", ")}` : ""].filter(Boolean),
      signatures: [p("sheet.sign_qc"), p("sheet.sign_qc_incharge"), p("sheet.sign_manager")],
    });

  return (
    <>
      <FilterCard
        onApply={() => apply(v)}
        pending={pending}
        applyLabel={p("btn.show")}
        extra={
          sheet ? (
            <Button variant="outline" className="ml-auto" onClick={print}>
              <Printer /> {p("btn.print")}
            </Button>
          ) : null
        }
      >
        <Field label={p("f.date")}>
          <Input type="date" value={v.date} onChange={(e) => set("date")(e.target.value)} className="w-44" />
        </Field>
        <Field label={p("f.line")}>
          <Select value={v.line_id} onChange={set("line_id")} options={lines} placeholder={p("f.select_line")} />
        </Field>
        <Field label={p("f.job")} className="min-w-64">
          <Select value={v.job_id} onChange={set("job_id")} options={lookups.all_jobs} placeholder={p("f.all_jobs")} />
        </Field>
      </FilterCard>

      {!sheet ? (
        <Notice tone="info">{p("sheet.pick")}</Notice>
      ) : (
        <>
          <div className="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
            <Kpi label={p("kpi.target")} value={num(sheet.target)} tone="muted" sub={sheet.working_hours ? p("sheet.hours", { n: sheet.working_hours }) : null} />
            <Kpi label={p("kpi.hourly_target")} value={num(sheet.hourly_target)} tone="muted" />
            <Kpi label={p("kpi.checked")} value={num(t.checked)} tone="info" />
            <Kpi label={p("kpi.pass")} value={num(t.pass)} tone="success" />
            <Kpi label={p("kpi.achievement")} value={pct(t.achievement)} valueClass={achievementTone(t.achievement)} />
            <Kpi label={p("kpi.dhu")} value={pct(t.dhu)} tone="warning" valueClass={dhuTone(t.dhu)} sub={p("kpi.tags", { n: num(t.defect_tags) })} />
            <Kpi label={p("kpi.rft_pct")} value={pct(t.rft_pct)} tone="success" />
            <Kpi label={p("kpi.efficiency")} value={pct(sheet.efficiency)} tone="info" sub={sheet.efficiency === null ? p("sheet.eff_missing") : job ? p("sheet.eff_smv", { smv: job.smv }) : null} />
          </div>

          <Panel
            title={p("sheet.title", { line: sheet.line ?? "—", date: day(sheet.date) })}
            subtitle={sheet.jobs.map((j) => `${j.job_no} · ${j.buyer_name} · ${j.style}${j.color ? ` · ${j.color}` : ""}`).join("  |  ") || p("sheet.no_job")}
            icon={SheetIcon}
            className="mb-4"
          >
            <Table
              columns={hourCols}
              rows={hours.map((h) => ({ ...h, key: h.hour }))}
              rowKey={(r) => r.key}
              empty={p("empty.no_taps")}
              foot={{ label: p("total"), hourly_target: num(sheet.target), rft: num(t.rft), defect: num(t.defect), rectify: num(t.rectify), reject: num(t.reject), checked: num(t.checked), pass: num(t.pass), cumulative_pass: num(t.pass), dhu: pct(t.dhu) }}
            />
          </Panel>

          <div className="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <Panel title={p("sheet.defects")} subtitle={p("sheet.defects_hint")} icon={ShieldAlert} className="xl:col-span-2">
              <Table
                dense
                columns={[
                  { key: "name", label: p("col.defect"), render: (d) => <span className="whitespace-nowrap">{defectName(d)}</span> },
                  ...hours.map((h) => ({ key: `h${h.hour}`, label: String(h.hour).padStart(2, "0"), align: "center", render: (d) => d.by_hour[h.hour] ?? "" })),
                  { key: "total", label: p("total"), align: "right", render: (d) => <b>{num(d.total)}</b> },
                ]}
                rows={sheet.defects}
                empty={p("empty.no_defects")}
              />
            </Panel>
            <Panel title={p("sheet.inspectors")} icon={Users}>
              {sheet.inspectors.length === 0 ? (
                <p className="px-5 py-8 text-center text-sm text-foreground-muted">{p("empty.no_taps")}</p>
              ) : (
                <ul className="divide-y divide-border">
                  {sheet.inspectors.map((i) => (
                    <li key={i.name} className="flex justify-between px-5 py-2.5 text-sm">
                      <span>{i.name}</span>
                      <b className="tabular-nums">{num(i.taps)}</b>
                    </li>
                  ))}
                </ul>
              )}
            </Panel>
          </div>
        </>
      )}
    </>
  );
}

export { TallySheet };
