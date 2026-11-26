"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { Download, FileBarChart } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useToastManager } from "@/components/ui/toast";
import { SECTIONS, useQt, Panel, Select } from "./common";

/*
 * Quality Tally → Reports → Reports (AnnotechERP 36, phase B): the module's
 * reports on the Reporting runner — a period, a section and a format (Excel,
 * PDF, CSV). A small one is ready at once; a big one is queued and lands in
 * My exports.
 */

const REPORTS = ["qt_daily_summary", "qt_hourly_tally", "qt_defect_analysis", "qt_inspector_performance"];
const FORMATS = [
  { value: "xlsx", label: "Excel" },
  { value: "pdf", label: "PDF" },
  { value: "csv", label: "CSV" },
];

function QtReports({ today, act }) {
  const p = useQt();
  return (
    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
      {REPORTS.map((key) => (
        <ReportCard key={key} report={key} today={today} act={act} p={p} />
      ))}
      <p className="text-xs text-foreground-muted xl:col-span-2">
        {p("rep.queued_hint")}{" "}
        <Link href="/reports/jobs" className="font-semibold text-brand hover:underline">
          {p("rep.my_exports")}
        </Link>
      </p>
    </div>
  );
}

function ReportCard({ report, today, act, p }) {
  const toast = useToastManager();
  const first = `${today.slice(0, 8)}01`;
  const [v, setV] = useState({ from: first, to: today, section: "", format: "xlsx" });
  const [job, setJob] = useState(null);
  const [pending, start] = useTransition();
  const set = (k) => (val) => setV((all) => ({ ...all, [k]: val }));

  const go = () =>
    start(async () => {
      setJob(null);
      const res = await act("runReport", { report, format: v.format, from: v.from, to: v.to, section: v.section || null });
      if (res?.status !== "success") {
        toast.add({ title: res?.message ?? p("error"), type: "danger" });
        return;
      }
      setJob(res.data);
      if (res.data.is_downloadable) window.location.href = `/api/report-jobs/${res.data.id}/download`;
      else toast.add({ title: p("rep.queued"), type: "info" });
    });

  return (
    <Panel title={p(`rep.${report}.title`)} subtitle={p(`rep.${report}.description`)} icon={FileBarChart}>
      <div className="flex flex-wrap items-end gap-3 p-5">
        <label className="text-[13px] font-semibold text-foreground">
          {p("f.from")}
          <Input type="date" value={v.from} onChange={(e) => set("from")(e.target.value)} className="mt-1.5 w-40" />
        </label>
        <label className="text-[13px] font-semibold text-foreground">
          {p("f.to")}
          <Input type="date" value={v.to} onChange={(e) => set("to")(e.target.value)} className="mt-1.5 w-40" />
        </label>
        <label className="text-[13px] font-semibold text-foreground">
          {p("f.section")}
          <Select value={v.section} onChange={set("section")} options={SECTIONS.map((s) => ({ value: s, label: p(`section.${s}`) }))} placeholder={p("all_sections")} className="mt-1.5 w-40" />
        </label>
        <label className="text-[13px] font-semibold text-foreground">
          {p("rep.format")}
          <Select value={v.format} onChange={set("format")} options={FORMATS} className="mt-1.5 w-28 min-w-0" />
        </label>
        <Button loading={pending} onClick={go}>
          <Download /> {p("rep.run")}
        </Button>
      </div>
      {job && !job.is_downloadable ? (
        <p className="border-t border-border px-5 py-2.5 text-xs text-foreground-muted">
          {p("rep.queued")}{" "}
          <Link href="/reports/jobs" className="font-semibold text-brand hover:underline">
            {p("rep.my_exports")}
          </Link>
        </p>
      ) : null}
    </Panel>
  );
}

export { QtReports };
