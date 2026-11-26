"use client";

import { useState, useTransition } from "react";
import { Pencil, Save, Table2, X } from "lucide-react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * Sales & Shipment → Operation → Sales & Shipment Settings (AnnotechERP
 * 21/01), as Brickland lays it out: two tabs that switch in place — Current
 * Settings (the one row under its RULES / ALERT DAYS / PREFIXES headings,
 * the sequence width, who changed it last and Edit) and Edit settings (the
 * two rules with what each means, the alert windows in days, the nine
 * prefixes, the sequence width with the number it prints, Cancel and Update
 * settings).
 */

const PREFIXES = ["prefix_amendment", "prefix_challan_return", "prefix_sc_return", "prefix_sc_reconciliation", "prefix_shipment", "prefix_booking", "prefix_invoice", "prefix_packing_list", "prefix_handover"];
const firstError = (res) => (res?.errors ? Object.values(res.errors).flat()[0] : res?.message);
const when = (iso) => (iso ? new Date(iso).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", hourCycle: "h12" }).replace(/\b[ap]m\b/, (x) => x.toUpperCase()) : "—");
const toForm = (d) => ({ ...d, due_alert_days: String(d.due_alert_days), cert_alert_days: String(d.cert_alert_days), seq_width: String(d.seq_width) });

function Field({ label, hint, error, children }) {
  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-[13px] font-semibold">
        {label} <span className="text-danger">*</span>
      </span>
      {children}
      {error ? <span className="text-xs text-danger">{error}</span> : <span className="text-xs text-foreground-muted">{hint}</span>}
    </label>
  );
}

function Suffixed({ value, onChange, suffix, disabled, ...rest }) {
  return (
    <div className="relative">
      <Input value={value} onChange={onChange} disabled={disabled} className="pr-14" {...rest} />
      <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-foreground-muted">{suffix}</span>
    </div>
  );
}

function SalesShipmentSettings({ initial, canManage, act }) {
  const t = useT("salesshipment");
  const s = (k, p) => t(`set.${k}`, p);
  const toastManager = useToastManager();
  const [tab, setTab] = useState("current");
  const [data, setData] = useState(initial);
  const [form, setForm] = useState(() => toForm(initial));
  const [errors, setErrors] = useState({});
  const [pending, startTransition] = useTransition();
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const yes = (v) => (v ? s("yes") : s("no"));
  const width = Math.min(8, Math.max(4, Number(form.seq_width) || 6));
  const example = `${form.prefix_shipment || "SHP"}-${new Date().getFullYear()}-${"1".padStart(width, "0")}`;

  const save = () =>
    startTransition(async () => {
      const payload = { ...form, due_alert_days: Number(form.due_alert_days), cert_alert_days: Number(form.cert_alert_days), seq_width: Number(form.seq_width) };
      const res = await act.save(payload);
      if (res?.status !== "success") {
        setErrors(Object.fromEntries(Object.entries(res?.errors ?? {}).map(([k, v]) => [k, v[0]])));
        return toastManager.add({ title: firstError(res), type: "danger" });
      }
      setErrors({});
      setData(res.data);
      setForm(toForm(res.data));
      toastManager.add({ title: s("saved"), type: "success" });
      setTab("current");
    });
  const cancel = () => {
    setForm(toForm(data));
    setErrors({});
    setTab("current");
  };

  const tabs = [["current", s("tab_current"), Table2, 1], ...(canManage ? [["edit", s("tab_edit"), Pencil, null]] : [])];
  const head = (k) => <th key={k} scope="col" className="border border-border px-3 py-2 text-left font-medium whitespace-nowrap">{s(k)}</th>;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex gap-1 border-b border-border" role="tablist">
        {tabs.map(([key, label, Icon, count]) => (
          <button
            key={key}
            type="button"
            role="tab"
            aria-selected={tab === key}
            onClick={() => setTab(key)}
            className={cn("-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-semibold", tab === key ? "border-brand text-brand" : "border-transparent text-foreground-muted hover:text-foreground")}
          >
            <Icon className="size-4" /> {label}
            {count !== null ? <span className="rounded-full bg-surface-muted px-1.5 text-xs text-foreground-muted">{count}</span> : null}
          </button>
        ))}
      </div>

      {tab === "current" ? (
        <section className="rounded-lg border border-border bg-surface p-5">
          {!data.saved ? <p className="mb-3 rounded-md bg-info-subtle px-3 py-2 text-sm text-info">{s("defaults_note")}</p> : null}
          {!canManage ? <p className="mb-3 rounded-md bg-warning-subtle px-3 py-2 text-sm text-warning">{s("read_only")}</p> : null}
          <div className="overflow-x-auto">
            <table className="w-full min-w-max border border-border text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  <th scope="colgroup" colSpan={2} className="border border-border px-3 py-2 text-center font-semibold">{s("group_rules")}</th>
                  <th scope="colgroup" colSpan={2} className="border border-border px-3 py-2 text-center font-semibold">{s("group_alert")}</th>
                  <th scope="colgroup" colSpan={PREFIXES.length} className="border border-border px-3 py-2 text-center font-semibold">{s("group_prefix")}</th>
                  <th scope="col" rowSpan={2} className="border border-border px-3 py-2 text-left font-semibold">{s("seq_width")}</th>
                  <th scope="col" rowSpan={2} className="border border-border px-3 py-2 text-left font-semibold">{s("updated_by")}</th>
                  <th scope="col" rowSpan={2} className="border border-border px-3 py-2 text-left font-semibold">{s("updated_at")}</th>
                  <th scope="col" rowSpan={2} className="border border-border px-3 py-2 text-left font-semibold">{s("action")}</th>
                </tr>
                <tr>{["ship_against_delivered", "require_export_docs", "due_alert_days", "cert_alert_days", ...PREFIXES].map(head)}</tr>
              </thead>
              <tbody>
                <tr className="hover:bg-surface-muted">
                  <td className="border border-border px-3 py-2">{yes(data.ship_against_delivered)}</td>
                  <td className="border border-border px-3 py-2">{yes(data.require_export_docs)}</td>
                  <td className="border border-border px-3 py-2 text-right tabular-nums">{data.due_alert_days}</td>
                  <td className="border border-border px-3 py-2 text-right tabular-nums">{data.cert_alert_days}</td>
                  {PREFIXES.map((k) => (
                    <td key={k} className="border border-border px-3 py-2 font-mono">
                      {data[k]}
                    </td>
                  ))}
                  <td className="border border-border px-3 py-2 text-right tabular-nums">{data.seq_width}</td>
                  <td className="border border-border px-3 py-2">{data.updated_by ?? "—"}</td>
                  <td className="border border-border px-3 py-2 whitespace-nowrap">{when(data.updated_at)}</td>
                  <td className="border border-border px-3 py-2">
                    {canManage ? (
                      <Button size="sm" variant="outline" onClick={() => setTab("edit")}>
                        <Pencil /> {s("edit")}
                      </Button>
                    ) : null}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      ) : (
        <>
          <section className="flex flex-col gap-5 rounded-lg border border-border bg-surface p-5">
            <div>
              <h2 className="flex items-center gap-2 text-base font-semibold">
                <Pencil className="size-4" /> {s("edit_title")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{s("edit_sub", { no: s("settings_no") })}</p>
            </div>
            {[
              ["ship_against_delivered", "ship_label", "ship_hint"],
              ["require_export_docs", "docs_label", "docs_hint"],
            ].map(([k, label, hint]) => (
              <label key={k} className="flex items-start gap-3">
                <Checkbox checked={Boolean(form[k])} onCheckedChange={(v) => set(k, v === true)} className="mt-0.5" aria-label={s(label)} />
                <span>
                  <span className="block text-sm font-semibold">{s(label)}</span>
                  <span className="block text-xs text-foreground-muted">{s(hint)}</span>
                </span>
              </label>
            ))}
            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              <Field label={s("due_label")} hint={s("due_hint")} error={errors.due_alert_days}>
                <Suffixed inputMode="numeric" value={form.due_alert_days} onChange={(e) => set("due_alert_days", e.target.value.replace(/\D/g, "").slice(0, 3))} suffix={s("days")} aria-label={s("due_label")} />
              </Field>
              <Field label={s("cert_label")} hint={s("cert_hint")} error={errors.cert_alert_days}>
                <Suffixed inputMode="numeric" value={form.cert_alert_days} onChange={(e) => set("cert_alert_days", e.target.value.replace(/\D/g, "").slice(0, 3))} suffix={s("days")} aria-label={s("cert_label")} />
              </Field>
              {PREFIXES.map((k) => (
                <Field key={k} label={`${s(k)} ${s("prefix_suffix")}`} hint={s("prefix_hint")} error={errors[k]}>
                  <Input value={form[k]} maxLength={6} onChange={(e) => set(k, e.target.value.toUpperCase().replace(/[^A-Z]/g, ""))} className="font-mono" aria-label={`${s(k)} ${s("prefix_suffix")}`} />
                </Field>
              ))}
              <Field label={s("width_label")} hint={s("width_hint", { n: width, example })} error={errors.seq_width}>
                <Suffixed inputMode="numeric" value={form.seq_width} onChange={(e) => set("seq_width", e.target.value.replace(/\D/g, "").slice(0, 1))} suffix={s("digits")} aria-label={s("width_label")} />
              </Field>
            </div>
          </section>
          <div className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-surface p-5">
            <p className="min-w-0 flex-1 text-[13px] text-foreground-muted">{s("footer")}</p>
            <Button variant="outline" onClick={cancel} disabled={pending}>
              <X /> {s("cancel")}
            </Button>
            <Button onClick={save} loading={pending}>
              <Save /> {s("save")}
            </Button>
          </div>
        </>
      )}
    </div>
  );
}

export { SalesShipmentSettings };
