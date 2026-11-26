"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Eraser, Layers, Plus, Save, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const EMPTY = { name: "", number: "", year: "", industry: "", effective_date: "", active: true, reference: "" };
let seq = 0;

/**
 * Wage/Gazette Rule → Wage Gazette (AnnotechERP 24/01): declare a wage board
 * gazette — number, year, industry, effective date and reference — the
 * policies published under it, and read its salary structure per category.
 * Choosing an existing gazette loads it back into the form; blank creates.
 *
 * @param {{ gazettes: object[], load: Function, save: Function, remove: Function, canManage: boolean }} props
 */
function GazetteForm({ gazettes, load, save, remove, canManage }) {
  const t = useT("hrpayroll");
  const m = (k, p) => t(`master.${k}`, p);
  const router = useRouter();
  const toastManager = useToastManager();
  const [picked, setPicked] = useState("");
  const [form, setForm] = useState(EMPTY);
  const [policies, setPolicies] = useState([]);
  const [structure, setStructure] = useState([]);
  const [errors, setErrors] = useState({});
  const [pending, startTransition] = useTransition();
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  function fill(g) {
    setForm({ name: g.name ?? "", number: g.number ?? "", year: g.year ? String(g.year) : "", industry: g.industry ?? "", effective_date: g.effective_date ?? "", active: !!g.active, reference: g.reference ?? "" });
    setPolicies(g.policies.map((p) => ({ ...p, _k: ++seq })));
    setStructure(g.structure);
    setErrors({});
  }

  function clear() {
    setPicked("");
    setForm(EMPTY);
    setPolicies([]);
    setStructure([]);
    setErrors({});
  }

  function choose(id) {
    if (!id) return clear();
    setPicked(id);
    startTransition(async () => {
      const result = await load(id);
      if (result?.status === "success") fill(result.data);
      else toastManager.add({ title: result?.message, type: "danger" });
    });
  }

  function submit() {
    const body = { ...form, year: form.year === "" ? null : Number(form.year), policies: policies.map(({ _k, ...p }) => p) };
    startTransition(async () => {
      const result = await save(picked || null, body);
      if (result?.status === "success") {
        setPicked(result.data.id);
        fill(result.data);
        toastManager.add({ title: m("saved"), type: "success" });
        router.refresh();
      } else {
        setErrors(result?.errors ?? {});
        toastManager.add({ title: result?.errors ? Object.values(result.errors).flat()[0] : result?.message, type: "danger" });
      }
    });
  }

  function destroy() {
    if (!picked || !window.confirm(m("wg.delete_confirm"))) return;
    startTransition(async () => {
      const result = await remove(picked);
      if (result?.status === "success") {
        clear();
        router.refresh();
      } else toastManager.add({ title: result?.errors ? Object.values(result.errors).flat()[0] : result?.message, type: "danger" });
    });
  }

  const field = (key, label, { required, type = "text", placeholder, hint } = {}) => (
    <label className="flex flex-col gap-1.5">
      <span className="text-[13px] font-semibold">
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      <Input type={type} value={form[key]} onChange={(e) => set(key, e.target.value)} placeholder={placeholder} disabled={!canManage} aria-invalid={errors[key] ? true : undefined} />
      {errors[key] ? <span className="text-xs text-danger">{errors[key][0]}</span> : hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
    </label>
  );
  const head = "px-3 py-2 text-left text-[13px] font-semibold";

  return (
    <section className="rounded-lg border border-border bg-surface">
      <header className="flex items-start justify-between gap-3 px-5 pt-4 pb-3">
        <div className="flex gap-3">
          <Layers className="mt-0.5 size-5 shrink-0 text-foreground-muted" />
          <div>
            <h2 className="text-base font-semibold">{m("wg.title")}</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">{m("wg.hint")}</p>
          </div>
        </div>
        <span className="rounded-full bg-brand-subtle px-2.5 py-0.5 text-xs font-semibold text-brand">{picked ? m("wg.editing") : m("wg.new")}</span>
      </header>
      <div className="flex flex-col gap-5 px-5 pb-5">
        <label className="flex flex-col gap-1.5">
          <span className="text-[13px] font-semibold">{m("wg.existing")}</span>
          <Select
            value={picked}
            onValueChange={(v) => choose(v ?? "")}
            options={[{ value: "", label: m("wg.new_option") }, ...gazettes.map((g) => ({ value: g.value, label: m("wg.option", { name: g.label, date: g.effective, lines: g.lines, policies: g.policies }) }))]}
            placeholder={m("wg.new_option")}
          />
          <span className="text-xs text-foreground-muted">{m("wg.existing_hint")}</span>
        </label>

        <fieldset className="grid gap-4 rounded-lg border border-border p-4 sm:grid-cols-3">
          <legend className="px-1 text-[13px] font-semibold">{m("wg.gazette")}</legend>
          {field("name", m("f.gazette_name"), { required: true, placeholder: "RMG Minimum Wage 2023" })}
          {field("number", m("f.gazette_number"), { placeholder: "S.R.O. No. 389-Law/2023" })}
          {field("year", m("f.year"), { type: "number", placeholder: "2023" })}
          {field("industry", m("f.industry"), { placeholder: "Readymade Garments" })}
        </fieldset>

        <fieldset className="grid gap-4 rounded-lg border border-border p-4 sm:grid-cols-2">
          <legend className="px-1 text-[13px] font-semibold">{m("wg.validity")}</legend>
          {field("effective_date", m("f.effective_date"), { required: true, type: "date", hint: m("wg.effective_hint") })}
          <label className="flex items-start gap-2 pt-6">
            <Checkbox checked={form.active} onCheckedChange={(v) => set("active", !!v)} disabled={!canManage} />
            <span>
              <span className="block text-[13px] font-semibold">{m("f.active_q")}</span>
              <span className="block text-xs text-foreground-muted">{m("wg.active_hint")}</span>
            </span>
          </label>
          <div className="sm:col-span-2">{field("reference", m("f.reference_doc"), { placeholder: m("wg.reference_placeholder") })}</div>
        </fieldset>

        <div className="rounded-lg border border-border">
          <div className="flex items-start justify-between gap-3 px-4 pt-3 pb-2">
            <div>
              <h3 className="text-[15px] font-semibold">{m("wg.policies")}</h3>
              <p className="text-xs text-foreground-muted">{m("wg.policies_hint")}</p>
            </div>
            {canManage ? (
              <Button variant="outline" onClick={() => setPolicies((p) => [...p, { code: "", name: "", active: true, _k: ++seq }])}>
                <Plus /> {m("wg.add_policy")}
              </Button>
            ) : null}
          </div>
          <table className="w-full text-sm">
            <thead className="bg-brand text-white">
              <tr>
                <th className={head}>{m("f.policy_code")}</th>
                <th className={head}>{m("f.policy_name")}</th>
                <th className={`${head} text-center`}>{m("f.active_q")}</th>
                <th className={`${head} text-center`}>{m("f.remove")}</th>
              </tr>
            </thead>
            <tbody>
              {policies.length ? (
                policies.map((p) => (
                  <tr key={p._k} className="border-t border-border">
                    <td className="px-3 py-2">
                      <Input value={p.code} onChange={(e) => setPolicies((all) => all.map((x) => (x._k === p._k ? { ...x, code: e.target.value } : x)))} disabled={!canManage} className="w-40 font-mono text-sm" aria-label={m("f.policy_code")} />
                    </td>
                    <td className="px-3 py-2">
                      <Input value={p.name} onChange={(e) => setPolicies((all) => all.map((x) => (x._k === p._k ? { ...x, name: e.target.value } : x)))} disabled={!canManage} aria-label={m("f.policy_name")} />
                    </td>
                    <td className="px-3 py-2 text-center">
                      <Checkbox checked={!!p.active} onCheckedChange={(v) => setPolicies((all) => all.map((x) => (x._k === p._k ? { ...x, active: !!v } : x)))} disabled={!canManage} aria-label={m("f.active_q")} />
                    </td>
                    <td className="px-3 py-2 text-center">
                      <Button variant="ghost" size="sm" onClick={() => setPolicies((all) => all.filter((x) => x._k !== p._k))} disabled={!canManage} aria-label={m("f.remove")}>
                        <Trash2 className="text-danger" />
                      </Button>
                    </td>
                  </tr>
                ))
              ) : (
                <tr className="border-t border-border">
                  <td colSpan={4} className="px-3 py-6 text-center text-[13px] text-foreground-muted">
                    {m("wg.no_policies")}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div className="rounded-lg border border-border">
          <div className="px-4 pt-3 pb-2">
            <h3 className="text-[15px] font-semibold">{m("wg.structure")}</h3>
            <p className="text-xs text-foreground-muted">{m("wg.structure_hint")}</p>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-brand text-white">
                <tr>
                  {["category", "basic", "house_rent", "medical", "food", "transport", "other", "gross_min", "basic_at_min"].map((k) => (
                    <th key={k} className={head}>
                      {m(`wg.col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {structure.length ? (
                  structure.map((r) => (
                    <tr key={r.category} className="border-t border-border">
                      <td className="px-3 py-2 font-semibold">{r.category}</td>
                      <td className="px-3 py-2">{r.basic}</td>
                      <td className="px-3 py-2">{r.house_rent}</td>
                      <td className="px-3 py-2">{r.medical}</td>
                      <td className="px-3 py-2">{r.food}</td>
                      <td className="px-3 py-2">{r.transport}</td>
                      <td className="px-3 py-2">{r.other}</td>
                      <td className="px-3 py-2">{r.gross_min ?? m("wg.not_set")}</td>
                      <td className="px-3 py-2">{r.basic_at_min ?? "—"}</td>
                    </tr>
                  ))
                ) : (
                  <tr className="border-t border-border">
                    <td colSpan={9} className="px-3 py-6 text-center text-[13px] text-foreground-muted">
                      {m("wg.no_structure")}
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
          <p className="border-t border-border px-4 py-2 text-xs text-foreground-muted">{m("wg.versioning")}</p>
        </div>

        {canManage ? (
          <div className="flex flex-wrap justify-end gap-2">
            {picked ? (
              <Button variant="outline" onClick={destroy} disabled={pending} className="mr-auto text-danger">
                <Trash2 /> {m("delete")}
              </Button>
            ) : null}
            <Button variant="outline" onClick={clear} disabled={pending}>
              <Eraser /> {m("clear")}
            </Button>
            <Button onClick={submit} loading={pending}>
              <Save /> {picked ? m("wg.save_changes") : m("wg.save")}
            </Button>
          </div>
        ) : null}
      </div>
    </section>
  );
}

export { GazetteForm };
