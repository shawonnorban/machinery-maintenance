"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { CircleCheck, Download, FileSpreadsheet, FileUp, Upload } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import { FileInput } from "@/components/ui/file-input";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const FORMATS = [
  { key: "csv", accept: ".csv" },
  { key: "xlsx", accept: ".xlsx,.xls" },
  { key: "json", accept: ".json" },
];

/** The header row each file carries, as Brickland's Template writes it. */
const HEADERS = { allowances: ["Emp Code", "Amount"], deductions: ["Emp Code", "Ded Type Id", "Pay Period Id", "Amount"] };

/**
 * Allowance → Excel Upload and Deduction → Upload (AnnotechERP 24/01): pick
 * the file type, the company and pay period (and, for an allowance, the pay
 * element), attach the file and Show File Data — every line is checked
 * against the employee, head and period registers before anything is paid.
 * Upload applies the clean lines; nothing is written before it. A deduction
 * file names its head (Ded Type Id) and may name its period on every line;
 * the chosen period stands in for a blank one.
 *
 * @param {{ kind: "allowances"|"deductions", company: {value: string, label: string}, periods: object[], elements: object[], save: Function, title: string }} props
 */
function EntryUpload({ kind, company, periods, elements, save, title }) {
  const t = useT("hrpayroll");
  const router = useRouter();
  const toastManager = useToastManager();
  const allowance = kind === "allowances";
  const [format, setFormat] = useState("csv");
  const [element, setElement] = useState("");
  const [companyId, setCompanyId] = useState("");
  const [period, setPeriod] = useState("");
  const [file, setFile] = useState(null);
  // Bumped to clear the file box after an upload; choosing a file must not remount it.
  const [inputKey, setInputKey] = useState(0);
  const [rows, setRows] = useState(null);
  const [reading, setReading] = useState(false);
  const [pending, startTransition] = useTransition();
  const clean = (rows ?? []).filter((r) => !r.error);
  const m = (k, p) => t(`master.${k}`, p);
  const reset = () => setRows(null);

  function template() {
    const head = HEADERS[kind];
    const sample = allowance ? ["50004061", "1500"] : ["50004061", "MED", "", "500"];
    const body =
      format === "json"
        ? JSON.stringify([Object.fromEntries(head.map((h, i) => [h.toLowerCase().replaceAll(" ", "_"), h === "Amount" ? Number(sample[i]) : sample[i]]))], null, 2)
        : `${head.join(",")}\n${sample.join(",")}\n`;
    const a = document.createElement("a");
    a.href = URL.createObjectURL(new Blob([body], { type: format === "json" ? "application/json" : "text/csv" }));
    a.download = `${kind}-template.${format === "json" ? "json" : "csv"}`;
    a.click();
    URL.revokeObjectURL(a.href);
  }

  function missing() {
    if (allowance && !element) return m("pay_element_required");
    if (!companyId) return m("company_required");
    if (allowance && !period) return m("pay_period_required");
    return null;
  }

  async function show() {
    const problem = missing() ?? (file ? null : m("file_required"));
    if (problem) return toastManager.add({ title: problem, type: "danger" });
    setReading(true);
    try {
      const body = new FormData();
      body.set("kind", kind);
      body.set("format", format);
      body.set("file", file);
      if (period) body.set("pay_period_id", period);
      if (allowance) body.set("pay_element_id", element);
      const response = await fetch("/api/hr-payroll/uploads", { method: "POST", body });
      const json = await response.json();
      if (!response.ok) {
        setRows(null);
        toastManager.add({ title: json?.errors ? Object.values(json.errors).flat()[0] : json?.message, type: "danger" });
      } else setRows(json.data);
    } finally {
      setReading(false);
    }
  }

  function upload() {
    const problem = missing();
    if (problem) return toastManager.add({ title: problem, type: "danger" });
    startTransition(async () => {
      const result = await save(kind, {
        pay_period_id: period || null,
        pay_element_id: allowance ? element : null,
        rows: clean.map((r) => ({ employee_code: r.employee_code, pay_element: allowance ? null : r.pay_element, pay_period: allowance ? null : r.pay_period, amount: r.amount })),
      });
      if (result?.status === "success") {
        toastManager.add({ title: m("up.uploaded", { count: clean.length }), type: "success" });
        setRows(null);
        setFile(null);
        setInputKey((k) => k + 1);
        router.refresh();
      } else {
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat()[0] : result?.message, type: "danger" });
      }
    });
  }

  const select = (label, value, onChange, opts, placeholder, help) => (
    <label className="flex flex-col gap-1.5">
      <span className="text-[13px] font-semibold">{label}</span>
      <Select value={value} onValueChange={(v) => (onChange(v ?? ""), reset())} options={opts} placeholder={placeholder} />
      {help ? <span className="text-xs text-foreground-muted">{help}</span> : null}
    </label>
  );

  return (
    <section className="rounded-lg border border-border bg-surface">
      <header className="flex items-start gap-3 px-5 pt-4 pb-3">
        <FileSpreadsheet className="mt-0.5 size-5 shrink-0 text-foreground-muted" />
        <div>
          <h2 className="text-base font-semibold">{title}</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{m(allowance ? "up.hint_allowance" : "up.hint_deduction")}</p>
        </div>
      </header>
      <div className="flex flex-col gap-4 px-5 pb-5">
        <div className="flex flex-col gap-1.5">
          <span className="text-[13px] font-semibold">{m("up.file_type")}</span>
          <div className="flex flex-wrap gap-5">
            {FORMATS.map((f) => (
              <label key={f.key} className="flex cursor-pointer items-center gap-2 text-sm">
                <input type="radio" name={`${kind}-format`} value={f.key} checked={format === f.key} onChange={() => (setFormat(f.key), setFile(null), reset())} className="size-4 accent-brand" />
                {m(`up.format.${f.key}`)}
              </label>
            ))}
          </div>
        </div>
        <div className={cn("grid gap-4 sm:grid-cols-2", allowance && "lg:grid-cols-3")}>
          {allowance ? select(m("f.pay_element"), element, setElement, elements, m("select_element"), m("up.element_hint")) : null}
          {select(m("f.company"), companyId, setCompanyId, [company], m(allowance ? "select_company" : "select_company_short"), allowance ? null : m("up.company_hint"))}
          {select(
            m("f.pay_period"),
            period,
            setPeriod,
            periods.map((p, i) => ({ value: p.value, label: allowance ? p.label : `${i + 1} — ${p.no} (${company.label.replace(/ \(.*\)$/, "")})` })),
            m(allowance ? "select_period_allowance" : "select_period"),
            allowance ? null : m("up.period_hint"),
          )}
        </div>
        <div className="flex flex-col gap-1.5">
          <span className="text-[13px] font-semibold">{m("up.attach")}</span>
          <div className="flex flex-wrap items-center gap-2">
            <div className="min-w-64 flex-1">
              <FileInput key={`${format}-${inputKey}`} accept={FORMATS.find((f) => f.key === format).accept} onChange={(e) => (setFile(e.target.files?.[0] ?? null), reset())} />
            </div>
            <Button variant="outline" onClick={show} loading={reading} disabled={!file}>
              <CircleCheck /> {m("up.show")}
            </Button>
            <Button variant="outline" onClick={template}>
              <Download /> {m("up.template")}
            </Button>
            <Button onClick={upload} loading={pending} disabled={!clean.length}>
              <Upload /> {m("up.upload")}
            </Button>
          </div>
        </div>
      </div>

      {rows ? (
        <div className="border-t border-border">
          <div className="flex flex-wrap items-center gap-3 px-5 py-3">
            <h3 className="text-[15px] font-semibold">{m("up.file_data")}</h3>
            <span className="text-[13px] text-foreground-muted">{m("up.summary", { count: rows.length, clean: clean.length, bad: rows.length - clean.length })}</span>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-left text-[13px] text-white">
                <tr>
                  <th className="px-3 py-2">{m("up.row")}</th>
                  <th className="px-3 py-2">{m("up.emp_code")}</th>
                  <th className="px-3 py-2">{m("f.employee")}</th>
                  {allowance ? null : <th className="px-3 py-2">{m("up.ded_type")}</th>}
                  {allowance ? null : <th className="px-3 py-2">{m("f.pay_period")}</th>}
                  <th className="px-3 py-2 text-right">{m("f.amount")}</th>
                  <th className="px-3 py-2">{m("up.check")}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.row} className={cn("border-t border-border", r.error && "bg-danger-subtle/40")}>
                    <td className="px-3 py-2 tabular-nums">{r.row}</td>
                    <td className="px-3 py-2 font-mono text-[13px]">{r.employee_code || "—"}</td>
                    <td className="px-3 py-2">{r.employee ?? "—"}</td>
                    {allowance ? null : <td className="px-3 py-2">{r.pay_element_name ? `${r.pay_element_name} (${r.pay_element})` : r.pay_element || "—"}</td>}
                    {allowance ? null : <td className="px-3 py-2 font-mono text-[13px]">{r.pay_period || "—"}</td>}
                    <td className="px-3 py-2 text-right tabular-nums">{r.amount ?? "—"}</td>
                    <td className={cn("px-3 py-2 text-[13px]", r.error ? "text-danger" : "text-success")}>{r.error ?? m("up.ok")}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : (
        <div className="flex flex-col items-center gap-2 border-t border-border px-5 py-12 text-center">
          <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
            <FileUp className="size-5" />
          </span>
          <p className="text-sm font-semibold">{m("up.no_rows")}</p>
          <p className="max-w-md text-[13px] text-foreground-muted">{m("up.carries", { headers: HEADERS[kind].join(", ") })}</p>
        </div>
      )}
      <p className="border-t border-border px-5 py-3 text-[13px] text-foreground-muted">{m(allowance ? "up.rule_allowance" : "up.rule_deduction")}</p>
    </section>
  );
}

export { EntryUpload };
