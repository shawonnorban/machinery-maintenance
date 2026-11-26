"use client";

import { useRef, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import {
  FileText,
  Paperclip,
  Plus,
  Printer,
  RotateCcw,
  Save,
  Search,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useToastManager } from "@/components/ui/toast";
import { RecordsGrid } from "@/components/erp/records-grid";
import { useT } from "@/lib/i18n";
import { printDocument } from "@/lib/print-document";

/*
 * Work Study → Item Specification (AnnotechERP 14/04), as Brickland lays it
 * out: Garment Spec Entry's planner — an existing variation to load back or a
 * new one; Style Info and Core Information; the operation bulletin attached
 * or pointed at; the process groups it is specified against and the machines
 * it needs — and the GMT Item List, the order items with the specification
 * their style carries. The variation stays in the address by its style and
 * number; nothing reloads.
 */

const card = "rounded-lg border border-border bg-surface";
const firstError = (res) =>
  Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message;
const num = (v) =>
  v === null || v === undefined || v === ""
    ? "—"
    : Number(v).toLocaleString("en-US", { maximumFractionDigits: 2 });
const HEADER = {
  style_no: "",
  variation_no: "",
  description: "",
  fabric_class: "",
  criticality: "",
  op_manpower: "0",
  aso_manpower: "0",
  sewing_smv: "0",
  iron_smv: "0",
  poly_smv: "0",
  avg_efficiency: "0",
  pick_efficiency: "0",
  balancing_efficiency: "0",
  ob_file_path: "",
  is_smv_artwork: false,
  is_fusing: false,
  sample_type_id: "",
};
let seq = 0;
const line = (l = {}) => ({ key: `l${++seq}`, ...l });
const fromSpec = (s) => ({
  header: Object.fromEntries(
    Object.keys(HEADER).map((k) => [
      k,
      typeof HEADER[k] === "boolean"
        ? Boolean(s[k])
        : s[k] === null || s[k] === undefined
          ? ""
          : String(s[k]),
    ]),
  ),
  processes: s.processes.map((p) =>
    line({
      garment_part_id: p.garment_part_id,
      specification: p.specification ?? "",
    }),
  ),
  machines: s.machines_list.map((m) =>
    line({ machine_type: m.machine_type, requirement: String(m.requirement) }),
  ),
});
const blank = () => ({ header: HEADER, processes: [], machines: [] });

function useWs() {
  const t = useT("workstudy");
  return { s: (k, x) => t(`spec.${k}`, x), m: (k, x) => t(`master.${k}`, x) };
}

/** @param {{ initial: object|null, specs: object[], options: object, act: Function, canManage: boolean }} props */
function SpecPlanner({
  initial,
  specs: initialSpecs,
  options,
  act,
  canManage,
}) {
  const { s, m } = useWs();
  const toastManager = useToastManager();
  const fileRef = useRef(null);
  const [specs, setSpecs] = useState(initialSpecs);
  const [current, setCurrent] = useState(
    initial ? { style: initial.style_no, v: initial.variation_no } : null,
  );
  const [form, setForm] = useState(() =>
    initial ? fromSpec(initial) : blank(),
  );
  const [errors, setErrors] = useState({});
  const [uploading, setUploading] = useState(false);
  const [pending, startTransition] = useTransition();
  const h = form.header;
  const set = (patch) =>
    setForm((f) => ({ ...f, header: { ...f.header, ...patch } }));
  const setLine = (grid, i, patch) =>
    setForm((f) => ({
      ...f,
      [grid]: f[grid].map((l, j) => (j === i ? { ...l, ...patch } : l)),
    }));
  const drop = (grid, i) =>
    setForm((f) => ({ ...f, [grid]: f[grid].filter((_, j) => j !== i) }));
  const keep = (sp) =>
    window.history.replaceState(
      null,
      "",
      `${window.location.pathname}?${new URLSearchParams(sp ? { tab: "planner", style: sp.style, v: sp.v } : { tab: "planner" })}`,
    );
  const load = (value) =>
    startTransition(async () => {
      setErrors({});
      if (!value) {
        setCurrent(null);
        keep(null);
        return setForm(blank());
      }
      const [style, v] = value.split("|");
      const res = await act("spec", style, v);
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      setCurrent({ style, v: Number(v) });
      keep({ style, v });
      setForm(fromSpec(res.data));
    });
  const attach = async (file) => {
    if (!file) return;
    setUploading(true);
    const body = new FormData();
    body.append("file", file);
    const response = await fetch("/api/work-study/ob-file", {
      method: "POST",
      body,
    });
    const json = await response.json().catch(() => ({}));
    setUploading(false);
    if (response.ok) set({ ob_file_path: json.data?.path ?? "" });
    else
      toastManager.add({
        title: json.errors
          ? Object.values(json.errors).flat()[0]
          : json.message,
        type: "danger",
      });
  };
  const save = () =>
    startTransition(async () => {
      const payload = {
        ...Object.fromEntries(
          Object.entries(h).map(([k, v]) => [k, v === "" ? null : v]),
        ),
        processes: form.processes.map(({ garment_part_id, specification }) => ({
          garment_part_id: garment_part_id || null,
          specification: specification || null,
        })),
        machines: form.machines.map(({ machine_type, requirement }) => ({
          machine_type: machine_type || null,
          requirement: requirement === "" ? null : requirement,
        })),
      };
      const res = current
        ? await act("updateSpec", current.style, current.v, payload)
        : await act("storeSpec", payload);
      if (res?.status !== "success") {
        setErrors(res?.errors ?? {});
        return toastManager.add({ title: firstError(res), type: "danger" });
      }
      setErrors({});
      const next = { style: res.data.style_no, v: res.data.variation_no };
      setCurrent(next);
      keep(next);
      setForm(fromSpec(res.data));
      const list = await act("specs");
      if (list?.status === "success") setSpecs(list.data);
      toastManager.add({
        title: s("f.saved", {
          label: res.data.label,
          processes: res.data.processes.length,
          machines: res.data.machines_list.length,
        }),
        type: "success",
      });
    });

  const err = (k) => errors[k]?.[0];
  const sel =
    "h-10 w-full rounded-lg border border-border-strong bg-surface px-3 text-sm disabled:opacity-60";
  const field = (k, label, control, { required, help, span } = {}) => (
    <label
      className={cn(
        "flex flex-col gap-1.5 text-[13px] font-semibold text-foreground",
        span,
      )}
    >
      <span>
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {control}
      {err(k) ? (
        <span className="text-xs font-normal text-danger">{err(k)}</span>
      ) : help ? (
        <span className="text-xs font-normal text-foreground-muted">
          {help}
        </span>
      ) : null}
    </label>
  );
  const text = (k, label, type = "text", extra = {}) =>
    field(
      k,
      label,
      <Input
        type={type}
        value={h[k]}
        disabled={!canManage}
        onChange={(ev) => set({ [k]: ev.target.value })}
        aria-label={label}
        {...(type === "number" ? { min: "0", step: "any" } : {})}
      />,
      extra,
    );
  const select = (k, label, choices, extra = {}) =>
    field(
      k,
      label,
      <select
        value={h[k]}
        disabled={!canManage}
        onChange={(ev) => set({ [k]: ev.target.value })}
        aria-label={label}
        className={sel}
      >
        <option value="">{s("f.select")}</option>
        {choices.map((c) => (
          <option key={c.value} value={c.value}>
            {c.label}
          </option>
        ))}
      </select>,
      extra,
    );
  const check = (k, label) => (
    <label className="flex items-center gap-2 text-[13px] font-semibold text-foreground">
      <input
        type="checkbox"
        checked={Boolean(h[k])}
        disabled={!canManage}
        onChange={(ev) => set({ [k]: ev.target.checked })}
        className="size-4 accent-[var(--brand)]"
        aria-label={label}
      />
      {label}
    </label>
  );
  const opt = (group, values) =>
    values.map((v) => ({ value: v, label: m(`opt.${group}.${v}`) }));
  const machineTotal = form.machines.reduce(
    (t, l) => t + (Number(l.requirement) || 0),
    0,
  );
  const gridCard = (title, hint, addLabel, onAdd, children) => (
    <section className={cn(card, "min-w-0 overflow-hidden")}>
      <div className="flex flex-wrap items-start justify-between gap-3 p-5">
        <div>
          <h2 className="text-base font-semibold text-foreground">{title}</h2>
          <p className="text-[13px] text-foreground-muted">{hint}</p>
        </div>
        {canManage ? (
          <Button size="sm" variant="outline" onClick={onAdd}>
            <Plus className="size-4" aria-hidden /> {addLabel}
          </Button>
        ) : null}
      </div>
      {children}
    </section>
  );
  const emptyLines = (hint) => (
    <div className="border-t border-border px-5 py-10 text-center">
      <p className="text-sm font-semibold text-foreground">{s("f.no_lines")}</p>
      <p className="text-[13px] text-foreground-muted">{hint}</p>
    </div>
  );

  return (
    <div className={cn("flex flex-col gap-4", pending && "opacity-80")}>
      <section className={cn(card, "p-5")}>
        <div className="flex items-start justify-between gap-3">
          <div className="flex gap-3">
            <FileText
              className="mt-0.5 size-4 text-foreground-muted"
              aria-hidden
            />
            <div>
              <h2 className="text-base font-semibold text-foreground">
                {s("f.title")}
              </h2>
              <p className="text-[13px] text-foreground-muted">{s("f.hint")}</p>
            </div>
          </div>
          <span className="rounded-full border border-border px-2 py-0.5 text-xs font-semibold text-foreground">
            {current ? `${current.style}/${current.v}` : s("f.new_badge")}
          </span>
        </div>
        <div className="mt-4">
          {field(
            "existing",
            s("f.existing"),
            <select
              value={current ? `${current.style}|${current.v}` : ""}
              onChange={(ev) => load(ev.target.value)}
              disabled={specs.length === 0}
              aria-label={s("f.existing")}
              className={sel}
            >
              <option value="">{s("f.existing_ph")}</option>
              {specs.map((sp) => (
                <option key={sp.id} value={`${sp.style_no}|${sp.variation_no}`}>
                  {sp.label} · {sp.description}
                </option>
              ))}
            </select>,
            { help: s("f.existing_help") },
          )}
        </div>
        <fieldset className="mt-4 rounded-lg border border-border p-4">
          <legend className="px-1 text-[13px] font-semibold text-foreground">
            {s("f.style_info")}
          </legend>
          <div className="grid gap-4 md:grid-cols-3">
            {text("style_no", s("f.style"), "text", { required: true })}
            {text("variation_no", s("f.variation"), "number", {
              required: true,
              help: s("f.variation_help"),
            })}
            {text("description", s("f.description"), "text", {
              required: true,
            })}
          </div>
        </fieldset>
        <fieldset className="mt-4 rounded-lg border border-border p-4">
          <legend className="px-1 text-[13px] font-semibold text-foreground">
            {s("f.core")}
          </legend>
          <div className="grid gap-4 md:grid-cols-3">
            {select(
              "fabric_class",
              s("f.fabric"),
              opt("fabric", options.fabric_classes),
            )}
            {select(
              "criticality",
              s("f.nature"),
              opt("prod", options.natures),
              { help: s("f.nature_help") },
            )}
            {text("op_manpower", s("f.op"), "number", { required: true })}
            {text("aso_manpower", s("f.aso"), "number", { required: true })}
            {text("sewing_smv", s("f.sewing_smv"), "number", {
              required: true,
            })}
            {text("iron_smv", s("f.iron_smv"), "number")}
            {text("poly_smv", s("f.poly_smv"), "number")}
            {text("avg_efficiency", s("f.avg_eff"), "number")}
            {text("pick_efficiency", s("f.pick_eff"), "number")}
            {text("balancing_efficiency", s("f.bal_eff"), "number")}
            {field(
              "ob_file_path",
              s("f.ob_file"),
              <div className="flex gap-2">
                <Input
                  value={h.ob_file_path}
                  disabled={!canManage}
                  onChange={(ev) => set({ ob_file_path: ev.target.value })}
                  placeholder={s("f.ob_placeholder")}
                  aria-label={s("f.ob_file")}
                />
                {canManage ? (
                  <Button
                    type="button"
                    variant="outline"
                    loading={uploading}
                    onClick={() => fileRef.current?.click()}
                  >
                    <Paperclip className="size-4" aria-hidden /> {s("f.attach")}
                  </Button>
                ) : null}
                <input
                  ref={fileRef}
                  type="file"
                  accept=".pdf,.xlsx,.xls,image/*"
                  className="hidden"
                  onChange={(ev) => attach(ev.target.files?.[0])}
                  aria-label={s("f.attach")}
                />
              </div>,
              { help: s("f.ob_help") },
            )}
            <div className="flex flex-col justify-center gap-3">
              {check("is_smv_artwork", s("f.smv_artwork"))}
              {check("is_fusing", s("f.fusing"))}
            </div>
            {select("sample_type_id", s("f.sample_type"), options.sample_types)}
          </div>
        </fieldset>
      </section>

      {gridCard(
        s("f.tech_title"),
        s("f.tech_hint"),
        s("f.add_process"),
        () =>
          setForm((f) => ({
            ...f,
            processes: [
              ...f.processes,
              line({ garment_part_id: "", specification: "" }),
            ],
          })),
        form.processes.length === 0 ? (
          emptyLines(s("f.no_process"))
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                <th className="px-3 py-2.5 text-left text-xs font-semibold">
                  {s("f.process_group")}
                </th>
                <th className="px-3 py-2.5 text-left text-xs font-semibold">
                  {s("f.specification")}
                </th>
                <th className="w-12" aria-hidden />
              </tr>
            </thead>
            <tbody>
              {form.processes.map((l, i) => (
                <tr key={l.key} className="border-t border-border">
                  <td className="px-3 py-2">
                    <select
                      value={l.garment_part_id}
                      disabled={!canManage}
                      onChange={(ev) =>
                        setLine("processes", i, {
                          garment_part_id: ev.target.value,
                        })
                      }
                      aria-label={s("f.process_group")}
                      className={cn(
                        sel,
                        "h-8 text-xs",
                        err(`processes.${i}.garment_part_id`) &&
                          "border-danger",
                      )}
                    >
                      <option value="">—</option>
                      {options.process_groups.map((p) => (
                        <option key={p.value} value={p.value}>
                          {p.label}
                        </option>
                      ))}
                    </select>
                  </td>
                  <td className="px-3 py-2">
                    <Input
                      value={l.specification}
                      disabled={!canManage}
                      onChange={(ev) =>
                        setLine("processes", i, {
                          specification: ev.target.value,
                        })
                      }
                      aria-label={s("f.specification")}
                      className="h-8 text-xs"
                    />
                  </td>
                  <td className="px-2 py-2">
                    {canManage ? (
                      <Button
                        size="icon"
                        variant="ghost"
                        className="size-8"
                        onClick={() => drop("processes", i)}
                        aria-label={s("f.remove")}
                      >
                        <Trash2 className="size-4" aria-hidden />
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ),
      )}

      {gridCard(
        s("f.mc_title"),
        s("f.mc_hint"),
        s("f.add_machine"),
        () =>
          setForm((f) => ({
            ...f,
            machines: [
              ...f.machines,
              line({ machine_type: "", requirement: "" }),
            ],
          })),
        form.machines.length === 0 ? (
          emptyLines(s("f.no_machine"))
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-brand text-brand-foreground">
              <tr>
                <th className="px-3 py-2.5 text-left text-xs font-semibold">
                  {s("f.machine_type")}
                </th>
                <th className="px-3 py-2.5 text-right text-xs font-semibold">
                  {s("f.requirement")}
                </th>
                <th className="w-12" aria-hidden />
              </tr>
            </thead>
            <tbody>
              {form.machines.map((l, i) => (
                <tr key={l.key} className="border-t border-border">
                  <td className="px-3 py-2">
                    <select
                      value={l.machine_type}
                      disabled={!canManage}
                      onChange={(ev) =>
                        setLine("machines", i, {
                          machine_type: ev.target.value,
                        })
                      }
                      aria-label={s("f.machine_type")}
                      className={cn(
                        sel,
                        "h-8 text-xs",
                        err(`machines.${i}.machine_type`) && "border-danger",
                      )}
                    >
                      <option value="">—</option>
                      {options.machine_types.map((t) => (
                        <option key={t} value={t}>
                          {m(`opt.machine.${t}`)}
                        </option>
                      ))}
                    </select>
                  </td>
                  <td className="px-3 py-2 text-right">
                    <Input
                      type="number"
                      min="0"
                      step="1"
                      value={l.requirement}
                      disabled={!canManage}
                      onChange={(ev) =>
                        setLine("machines", i, { requirement: ev.target.value })
                      }
                      aria-label={s("f.requirement")}
                      className={cn(
                        "ml-auto h-8 w-24 text-right text-xs",
                        err(`machines.${i}.requirement`) && "border-danger",
                      )}
                    />
                  </td>
                  <td className="px-2 py-2">
                    {canManage ? (
                      <Button
                        size="icon"
                        variant="ghost"
                        className="size-8"
                        onClick={() => drop("machines", i)}
                        aria-label={s("f.remove")}
                      >
                        <Trash2 className="size-4" aria-hidden />
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))}
              <tr className="border-t border-border bg-surface-muted font-semibold">
                <td className="px-3 py-2.5">
                  {s("f.total", { n: form.machines.length })}
                </td>
                <td className="px-3 py-2.5 text-right tabular-nums">
                  {machineTotal}
                </td>
                <td />
              </tr>
            </tbody>
          </table>
        ),
      )}

      <section
        className={cn(
          card,
          "flex flex-wrap items-center justify-between gap-3 px-5 py-4",
        )}
      >
        <p className="max-w-3xl text-[13px] text-foreground-muted">
          {s("f.foot")}
        </p>
        {canManage ? (
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => load("")}>
              <RotateCcw className="size-4" aria-hidden /> {s("f.clear")}
            </Button>
            <Button loading={pending} onClick={save}>
              <Save className="size-4" aria-hidden /> {s("f.save")}
            </Button>
          </div>
        ) : null}
      </section>
    </div>
  );
}

/** Garment Spec Entry → Records: the engineered variations; Edit opens one in the planner by its style and number. */
function SpecGrid({ rows }) {
  const { s, m } = useWs();
  const router = useRouter();
  const te = useT("erp");
  return (
    <div className="flex flex-col gap-2">
      <p className="px-1 text-sm font-semibold text-foreground">
        {s("f.records_title")}
      </p>
      <RecordsGrid
        exportName="garment-specifications"
        rows={rows.map((r) => ({
          ...r,
          nature_label: r.criticality ? m(`opt.prod.${r.criticality}`) : null,
        }))}
        columns={[
          { key: "label", label: s("col.style"), type: "mono" },
          { key: "description", label: s("col.description") },
          { key: "nature_label", label: s("col.nature") },
          {
            key: "op_manpower",
            label: s("col.op"),
            type: "number",
            align: "right",
          },
          {
            key: "aso_manpower",
            label: s("col.aso"),
            type: "number",
            align: "right",
          },
          {
            key: "sewing_smv",
            label: s("col.sewing_smv"),
            type: "number",
            align: "right",
          },
          {
            key: "avg_efficiency",
            label: s("col.avg_eff"),
            type: "number",
            align: "right",
          },
          {
            key: "machine_types",
            label: s("col.mc_types"),
            type: "number",
            align: "right",
          },
        ]}
        rowOpen={{
          label: te("edit"),
          onOpen: (r) =>
            router.push(
              `/work-study/gmt-item-specification-entry?${new URLSearchParams({ tab: "planner", style: r.style_no, v: r.variation_no })}`,
            ),
        }}
      />
    </div>
  );
}

const NATURES = ["ALL", "N", "R"];

/** Garment Item Specification → GMT Item List: the order items, filtered on Search, with the specification their style carries. */
function ItemList({ initial, company, act }) {
  const { s } = useWs();
  const [data, setData] = useState(initial);
  const [f, setF] = useState({
    month: "",
    factory_id: "",
    item_group: "",
    item_type: "",
    gmt_type: "",
    nature: "ALL",
    q: "",
    my_buyer: false,
    not_in_house: false,
  });
  const [pending, startTransition] = useTransition();
  const run = (next = f) =>
    startTransition(async () => {
      setF(next);
      const res = await act("itemList", {
        ...next,
        my_buyer: next.my_buyer ? "1" : "",
        not_in_house: next.not_in_house ? "1" : "",
      });
      if (res?.status === "success") setData(res.data);
    });
  const L = (k, x) => s(`list.${k}`, x);
  const sel =
    "h-10 w-full rounded-lg border border-border-strong bg-surface px-3 text-sm";
  const pick = (k, label, all, choices) => (
    <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
      {label}
      <select
        value={f[k]}
        onChange={(ev) => setF({ ...f, [k]: ev.target.value })}
        aria-label={label}
        className={sel}
      >
        <option value="">{all}</option>
        {choices.map((c) => (
          <option key={c.value ?? c} value={c.value ?? c}>
            {c.label ?? c}
          </option>
        ))}
      </select>
    </label>
  );
  const cols = [
    "month",
    "order_no",
    "style",
    "buyer",
    "unit",
    "item",
    "item_type",
    "gmt_type",
    "nature",
    "qty",
    "variation",
    "smv",
    "op",
    "aso",
    "cm_dz",
    "bgt_exp",
    "status",
  ];
  const right = new Set(["qty", "smv", "op", "aso", "cm_dz", "bgt_exp"]);
  const labelOf = (list, v) =>
    list.find((x) => (x.value ?? x) === v)?.label ?? v;
  // The printed list: the filters it was read with, then the items — landscape, one line each.
  const print = () =>
    printDocument({
      title: L("title"),
      subtitle: L("print_hint"),
      orientation: "landscape",
      metaColumns: 4,
      meta: [
        [L("booking_month"), f.month || L("all")],
        [
          L("unit"),
          f.factory_id ? labelOf(data.filters.units, f.factory_id) : L("all"),
        ],
        [L("item_group"), f.item_group || L("all")],
        [L("item_category"), f.item_type || L("all")],
        [L("gmt_type"), f.gmt_type || L("all")],
        [L("nature"), L(f.nature.toLowerCase())],
        [L("search"), f.q || "—"],
        [L("items"), String(data.rows.length)],
      ],
      sections: [
        {
          columns: cols.map((k) => ({
            label: L(`col.${k}`),
            align: right.has(k) ? "right" : undefined,
            nowrap: ["month", "order_no", "style", "variation"].includes(k),
          })),
          rows: data.rows.map((r) =>
            cols.map((k) =>
              k === "status"
                ? {
                    v: L(`status.${r.status}`),
                    tone: r.status === "SPECIFIED" ? "ok" : "warn",
                  }
                : k === "style"
                  ? r.style_no
                  : k === "qty"
                    ? `${num(r.qty)} ${r.unit_name ?? ""}`
                    : right.has(k)
                      ? num(r[k])
                      : r[k],
            ),
          ),
          foot: data.rows.length
            ? [
                cols.map((k, i) =>
                  i === 0
                    ? { v: L("count", { n: data.rows.length }), bold: true }
                    : "",
                ),
              ]
            : [],
          empty: L("empty"),
        },
      ],
      notes: [L("foot")],
    });

  return (
    <div className={cn("flex flex-col gap-4", pending && "opacity-80")}>
      <section className={cn(card, "p-4 print:hidden")}>
        <div className="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
          {pick(
            "month",
            L("booking_month"),
            L("select_month"),
            data.filters.months,
          )}
          <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
            {L("company")}
            <select defaultValue="" aria-label={L("company")} className={sel}>
              <option value="">{L("all_company")}</option>
              <option value={company.value}>{company.label}</option>
            </select>
          </label>
          {pick("factory_id", L("unit"), L("all_unit"), data.filters.units)}
          {pick(
            "item_group",
            L("item_group"),
            L("all_group"),
            data.filters.item_groups,
          )}
          {pick(
            "item_type",
            L("item_category"),
            L("all_category"),
            data.filters.item_types,
          )}
          {pick(
            "gmt_type",
            L("gmt_type"),
            L("all_gmt"),
            data.filters.gmt_types,
          )}
        </div>
        <div className="mt-3 flex flex-wrap items-center gap-4">
          <div
            role="radiogroup"
            aria-label={L("nature")}
            className="flex items-center gap-3 text-sm"
          >
            {NATURES.map((n) => (
              <label key={n} className="flex items-center gap-1.5">
                <input
                  type="radio"
                  name="order-nature"
                  checked={f.nature === n}
                  onChange={() => setF({ ...f, nature: n })}
                  className="accent-[var(--brand)]"
                />
                {L(n.toLowerCase())}
              </label>
            ))}
          </div>
          <div className="ml-auto flex flex-wrap items-center gap-3">
            <Input
              value={f.q}
              onChange={(ev) => setF({ ...f, q: ev.target.value })}
              onKeyDown={(ev) => ev.key === "Enter" && run()}
              placeholder={L("search_ph")}
              aria-label={L("search_ph")}
              className="w-56"
            />
            <Button variant="outline" onClick={() => run()}>
              <Search className="size-4" aria-hidden /> {L("search")}
            </Button>
            {[
              ["my_buyer", L("my_buyer")],
              ["not_in_house", L("not_inh")],
            ].map(([k, label]) => (
              <label
                key={k}
                className="flex items-center gap-1.5 text-sm font-medium"
              >
                <input
                  type="checkbox"
                  checked={f[k]}
                  onChange={(ev) => setF({ ...f, [k]: ev.target.checked })}
                  className="size-4 accent-[var(--brand)]"
                />
                {label}
              </label>
            ))}
          </div>
        </div>
      </section>
      <section className={cn(card, "min-w-0 overflow-hidden")}>
        {data.rows.length === 0 ? (
          <div className="px-5 py-16 text-center">
            <p className="text-sm font-semibold text-foreground">
              {L("empty_title")}
            </p>
            <p className="mx-auto max-w-md text-[13px] text-foreground-muted">
              {L("empty")}
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1150px] text-sm">
              <thead className="bg-brand text-brand-foreground">
                <tr>
                  {cols.map((k) => (
                    <th
                      key={k}
                      className={cn(
                        "px-3 py-2.5 text-xs font-semibold whitespace-nowrap",
                        right.has(k) ? "text-right" : "text-left",
                      )}
                    >
                      {L(`col.${k}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {data.rows.map((r) => (
                  <tr key={r.order_no} className="border-t border-border">
                    {cols.map((k) => (
                      <td
                        key={k}
                        className={cn(
                          "px-3 py-2.5",
                          right.has(k) && "text-right tabular-nums",
                          ["order_no", "style", "variation"].includes(k) &&
                            "font-mono text-[13px] whitespace-nowrap",
                        )}
                      >
                        {k === "status" ? (
                          <span
                            className={cn(
                              "rounded-full px-2 py-0.5 text-xs font-medium",
                              r.status === "SPECIFIED"
                                ? "bg-success-subtle text-success"
                                : "bg-warning-subtle text-warning",
                            )}
                          >
                            {L(`status.${r.status}`)}
                          </span>
                        ) : k === "style" ? (
                          (r.style_no ?? "—")
                        ) : k === "qty" ? (
                          `${num(r.qty)} ${r.unit_name ?? ""}`
                        ) : right.has(k) ? (
                          num(r[k])
                        ) : (
                          (r[k] ?? "—")
                        )}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <div className="flex items-center justify-end gap-3 border-t border-border px-5 py-3 print:hidden">
          <span className="text-[13px] text-foreground-muted">
            {data.rows.length ? L("count", { n: data.rows.length }) : L("none")}
          </span>
          <Button variant="outline" size="sm" onClick={print}>
            <Printer className="size-4" aria-hidden /> {L("print")}
          </Button>
        </div>
      </section>
      <p className="text-[13px] text-foreground-muted">{L("foot")}</p>
    </div>
  );
}

export { SpecPlanner, SpecGrid, ItemList };
