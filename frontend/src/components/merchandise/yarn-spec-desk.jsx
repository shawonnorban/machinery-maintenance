"use client";

import { useState, useTransition } from "react";
import { ListChecks, Plus, Save, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { refsFor } from "@/lib/row-ref";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { MultiPick } from "@/components/erp/multi-pick";
import { useT } from "@/lib/i18n";

// An error's field messages, or its message when it names no field (an empty `errors` is no message at all).
const messagesOf = (result) => {
  const fields = [...new Set(Object.values(result?.errors ?? {}).flat())];
  return fields.length ? fields : [result?.message ?? ""];
};

const blankForm = (categories) => ({ categories: [...categories], fabric_type_id: "", gmt_item: "", part_ids: [], all_colours: false, gmt_colour_ids: [], fab_colour_ids: [] });
// Brickland's "15 Oct 2026".
const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" });
const day = (value) => (value ? dateFmt.format(new Date(`${value}T00:00:00Z`)) : "—");

/**
 * Style-Wise Yarn Spec Confirmation (AnnotechERP 06/11), one page as Brickland
 * lays it out: Search Category and Search Job/W.Style, with the style's buyer
 * and description beside them; the style's Yarn / Fabric Mapping on the left
 * (each with Confirm, Release and remove) and the mapping form on the right,
 * whose Save writes a mapping for every production category ticked. Spec
 * Confirmation opens the list of styles, pending or confirmed, to open one.
 * Nothing reloads; the chosen style and category stay in the address.
 *
 * @param {{ options: object, initialStyleId: string, initialCategory: string, initialRows: object[], companyName: string, canManage: boolean, actions: object }} props
 */
function YarnSpecDesk({ options, initialStyleId, initialCategory, initialRows, companyName, canManage, actions }) {
  const t = useT("merchandise");
  const m = (k, p) => t(`master.ysc.${k}`, p);
  const toastManager = useToastManager();
  const [category, setCategory] = useState(initialCategory);
  const [styleId, setStyleId] = useState(initialStyleId);
  const [rows, setRows] = useState(initialRows);
  const [form, setForm] = useState(() => blankForm(options.categories));
  const [listOpen, setListOpen] = useState(false);
  const [pending, startTransition] = useTransition();
  const style = options.styles.find((s) => s.value === styleId);
  const refs = refsFor(options.styles, "label", "value");
  const categories = options.categories.map((v) => ({ value: v, label: m(`cat.${v}`) }));
  const set = (field) => (value) => setForm((f) => ({ ...f, [field]: value }));
  const say = (result, success) => {
    if (result?.status === "success") {
      if (success) toastManager.add({ title: success, type: "success" });
      return true;
    }
    const messages = messagesOf(result);
    // One message at a time, the first thing missing — as Brickland says it.
    toastManager.add({ title: messages[0], type: "warning" });
    return false;
  };

  function show(nextStyle, nextCategory) {
    setStyleId(nextStyle);
    setCategory(nextCategory);
    const query = new URLSearchParams({ category: nextCategory, ...(nextStyle ? { style: refs.get(nextStyle) } : {}) });
    window.history.replaceState(null, "", `?${query}`);
    if (!nextStyle) {
      setRows([]);
      return;
    }
    startTransition(async () => {
      const result = await actions.load(nextStyle, nextCategory);
      if (say(result)) setRows(result.data);
    });
  }

  function save() {
    startTransition(async () => {
      const result = await actions.save({ style_id: styleId || null, ...form, gmt_item: form.gmt_item.trim() });
      if (say(result, m("saved", { n: result?.data?.length ?? 0 }))) {
        setForm(blankForm(options.categories));
        const fresh = await actions.load(styleId, category);
        if (say(fresh)) setRows(fresh.data);
      }
    });
  }

  function act(row, kind) {
    startTransition(async () => {
      const result = await actions[kind](row.id);
      if (!say(result, m(kind === "remove" ? "removed" : kind === "confirm" ? "confirmed" : "released"))) return;
      setRows((all) => (kind === "remove" ? all.filter((r) => r.id !== row.id) : all.map((r) => (r.id === row.id ? result.data : r))));
    });
  }

  const card = "rounded-xl border border-border bg-surface";
  const lab = "text-[13px] font-semibold text-foreground sm:text-right";
  const row = "grid items-start gap-x-3 gap-y-1.5 sm:grid-cols-[8rem_1fr]";
  const star = <span className="text-danger">*</span>;
  const off = !canManage || !styleId;
  return (
    <div className="flex flex-col gap-4">
      <section className={cn(card, "flex flex-wrap items-end gap-4 p-4")}>
        <Button onClick={() => setListOpen(true)}>
          <ListChecks /> {m("spec_confirmation")}
        </Button>
        <label className="flex w-40 flex-col gap-1">
          <span className="text-[13px] font-semibold text-foreground">{m("search_category")}</span>
          <Select value={category} onValueChange={(v) => v && show("", v)} options={categories} aria-label={m("search_category")} />
        </label>
        <label className="flex w-64 flex-col gap-1">
          <span className="text-[13px] font-semibold text-foreground">{m("search_style")}</span>
          <Select value={styleId} onValueChange={(v) => show(v ?? "", category)} options={[{ value: "", label: m("select") }, ...options.styles.map(({ value, label }) => ({ value, label }))]} placeholder={m("select")} aria-label={m("search_style")} />
        </label>
        {[
          ["buyer", style?.buyer],
          ["style", style?.label],
          ["style_desc", style?.description],
        ].map(([k, v]) => (
          <div key={k} className="flex min-w-32 flex-col gap-1 pb-1.5">
            <span className="text-[13px] font-semibold text-foreground">{m(k)}</span>
            <span className="text-sm text-foreground">{v || "—"}</span>
          </div>
        ))}
      </section>

      <div className="grid gap-4 lg:grid-cols-2">
        <section className={cn(card, "overflow-hidden")}>
          <div className="flex items-center justify-between px-5 py-4">
            <h2 className="text-base font-semibold text-foreground">{m("mapping")}</h2>
            <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs font-semibold text-foreground">{rows.length}</span>
          </div>
          {rows.length ? (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-brand text-brand-foreground">
                  <tr>
                    {["cat", "fab_colour", "fabric", "item", "gmt_colour", "action"].map((k) => (
                      <th key={k} className={cn("px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap", k === "action" && "text-right")}>
                        {m(`col.${k}`)}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {rows.map((r) => (
                    <tr key={r.id} className="border-t border-border align-top">
                      <td className="px-3 py-2">
                        <span className="flex flex-wrap items-center gap-1.5">
                          {m(`cat.${r.prod_category}`)}
                          <span className={cn("rounded-md px-1.5 py-0.5 text-xs font-medium", r.status === "CONFIRMED" ? "bg-success-subtle text-success" : "bg-warning-subtle text-warning")}>{m(`status.${r.status}`)}</span>
                        </span>
                      </td>
                      <td className="px-3 py-2">{r.fab_colours.join(", ") || "—"}</td>
                      <td className="px-3 py-2">{r.fabric ?? "—"}</td>
                      <td className="px-3 py-2">
                        {r.gmt_item}
                        <span className="block text-xs text-foreground-muted">{r.parts.join(", ")}</span>
                      </td>
                      <td className="px-3 py-2">{r.all_colours ? m("all") : r.gmt_colours.join(", ") || "—"}</td>
                      <td className="px-3 py-2">
                        {canManage ? (
                          <span className="flex justify-end gap-1.5">
                            <Button variant="outline" size="sm" onClick={() => act(r, "confirm")} disabled={pending}>
                              {m("confirm")}
                            </Button>
                            <Button variant="outline" size="sm" onClick={() => act(r, "release")} disabled={pending}>
                              {m("release")}
                            </Button>
                            <Button variant="outline" size="icon" className="size-8 text-danger" aria-label={m("remove")} onClick={() => act(r, "remove")} disabled={pending}>
                              <Trash2 />
                            </Button>
                          </span>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="flex flex-col items-center gap-2 px-5 py-14 text-center">
              <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
                <ListChecks className="size-5" />
              </span>
              <p className="text-sm font-semibold text-foreground">{m("nothing")}</p>
              <p className="text-xs text-foreground-muted">{styleId ? m("fill_form") : m("pick_style")}</p>
            </div>
          )}
        </section>

        <section className={cn(card, "flex flex-col")}>
          <div className="flex items-center justify-between px-5 py-4">
            <h2 className="text-base font-semibold text-foreground">{m("form")}</h2>
            <Button variant="outline" size="sm" onClick={() => setForm(blankForm(options.categories))} disabled={off}>
              <Plus /> {m("new_entry")}
            </Button>
          </div>
          <div className="flex flex-col gap-3 px-5 pb-5">
            <div className={row}>
              <span className={lab}>
                {m("f.prod_category")} {star}
              </span>
              <MultiPick options={categories} value={form.categories} onChange={set("categories")} placeholder={m("search")} disabled={off} ariaLabel={m("f.prod_category")} />
            </div>
            <div className={row}>
              <span className={lab}>
                {m("f.fabric")} {star}
              </span>
              <Select value={form.fabric_type_id} onValueChange={(v) => set("fabric_type_id")(v ?? "")} options={[{ value: "", label: m("select_fabric") }, ...options.fabrics]} placeholder={m("select_fabric")} disabled={off} aria-label={m("f.fabric")} />
            </div>
            <div className={row}>
              <span className={lab}>
                {m("f.gmt_item")} {star}
              </span>
              <Input list="ysc-items" value={form.gmt_item} onChange={(e) => set("gmt_item")(e.target.value)} placeholder={m("select_item")} maxLength={96} disabled={off} aria-label={m("f.gmt_item")} />
              <datalist id="ysc-items">
                {(style?.items ?? []).map((i) => (
                  <option key={i} value={i} />
                ))}
              </datalist>
            </div>
            <div className={row}>
              <span className={lab}>
                {m("f.parts")} {star}
              </span>
              <MultiPick options={options.parts} value={form.part_ids} onChange={set("part_ids")} placeholder={m("search")} disabled={off} ariaLabel={m("f.parts")} />
            </div>
            <div className={row}>
              <span className={lab}>
                {m("f.gmt_colour")} {star}
              </span>
              <MultiPick options={options.colours} value={form.gmt_colour_ids} onChange={set("gmt_colour_ids")} placeholder={form.all_colours ? m("all") : m("search")} disabled={off || form.all_colours} ariaLabel={m("f.gmt_colour")} />
            </div>
            <div className={row}>
              <span className={lab}>{m("f.all_col")}</span>
              <Checkbox checked={form.all_colours} onCheckedChange={(c) => setForm((f) => ({ ...f, all_colours: c === true, ...(c === true ? { gmt_colour_ids: [] } : {}) }))} disabled={off} aria-label={m("f.all_col")} />
            </div>
            <div className={row}>
              <span className={lab}>
                {m("f.fab_colour")} {star}
              </span>
              <MultiPick options={options.colours} value={form.fab_colour_ids} onChange={set("fab_colour_ids")} placeholder={m("search")} disabled={off} ariaLabel={m("f.fab_colour")} />
            </div>
          </div>
          <div className="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3">
            <p className="max-w-md text-xs text-foreground-muted">{m("footer")}</p>
            {canManage ? (
              <Button onClick={save} loading={pending} disabled={!styleId}>
                <Save /> {m("save")}
              </Button>
            ) : null}
          </div>
        </section>
      </div>

      <ConfirmationList
        open={listOpen}
        onOpenChange={setListOpen}
        options={options}
        companyName={companyName}
        search={actions.search}
        onOpen={(id) => {
          setListOpen(false);
          show(id, category);
        }}
        m={m}
      />
    </div>
  );
}

/** Spec Confirmation → Yarn Spec Confirmation List: the styles, pending or confirmed, narrowed and searched. */
function ConfirmationList({ open, onOpenChange, options, companyName, search, onOpen, m }) {
  const [filters, setFilters] = useState({ from: "", to: "", state: "PENDING", buyer_id: "", style_id: "" });
  const [rows, setRows] = useState(null);
  const [error, setError] = useState(null);
  const [pending, startTransition] = useTransition();
  const set = (k) => (v) => setFilters((f) => ({ ...f, [k]: v ?? "" }));
  const field = "flex flex-col gap-1";
  const lab = "text-[13px] font-semibold text-foreground";

  function run() {
    startTransition(async () => {
      const result = await search(Object.fromEntries(Object.entries(filters).filter(([, v]) => v)));
      if (result?.status === "success") {
        setRows(result.data);
        setError(null);
      } else setError(result?.message ?? "");
    });
  }

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={m("list.title")} className="max-w-5xl">
      <div className="grid gap-3 sm:grid-cols-3">
        <label className={field}>
          <span className={lab}>{m("list.from")}</span>
          <Input type="date" value={filters.from} onChange={(e) => set("from")(e.target.value)} />
        </label>
        <label className={field}>
          <span className={lab}>{m("list.to")}</span>
          <Input type="date" value={filters.to} onChange={(e) => set("to")(e.target.value)} />
        </label>
        <label className={field}>
          <span className={lab}>{m("list.state")}</span>
          <Select value={filters.state} onValueChange={set("state")} options={[{ value: "", label: m("select") }, ...["PENDING", "CONFIRMED"].map((v) => ({ value: v, label: m(`list.status.${v}`) }))]} placeholder={m("select")} aria-label={m("list.state")} />
        </label>
        <label className={field}>
          <span className={lab}>{m("list.buyer")}</span>
          <Select value={filters.buyer_id} onValueChange={set("buyer_id")} options={[{ value: "", label: m("select") }, ...options.buyers]} placeholder={m("select")} aria-label={m("list.buyer")} />
        </label>
        <label className={field}>
          <span className={lab}>{m("list.style")}</span>
          <Select value={filters.style_id} onValueChange={set("style_id")} options={[{ value: "", label: m("select") }, ...options.styles.map(({ value, label }) => ({ value, label }))]} placeholder={m("select")} aria-label={m("list.style")} />
        </label>
        <label className={field}>
          <span className={lab}>{m("list.company")}</span>
          <Select value="current" options={[{ value: "current", label: companyName }]} aria-label={m("list.company")} />
        </label>
      </div>
      <div className="mt-3">
        <Button onClick={run} loading={pending}>
          <Search /> {m("list.search")}
        </Button>
      </div>
      {error ? <p className="mt-2 text-sm text-danger">{error}</p> : null}
      <div className="mt-4 overflow-x-auto rounded-lg border border-border">
        <table className="w-full text-sm">
          <thead className="bg-brand text-brand-foreground">
            <tr>
              {["status", "buyer", "style", "desc", "order", "qty", "confirm_date", "ship_date", "modified", "act"].map((k) => (
                <th key={k} className="px-3 py-2.5 text-left text-[13px] font-semibold whitespace-nowrap">
                  {m(`list.col.${k}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows === null || rows.length === 0 ? (
              <tr>
                <td colSpan={10} className="px-3 py-8 text-center text-foreground-muted">
                  {rows === null ? m("list.narrow") : m("list.none")}
                </td>
              </tr>
            ) : (
              rows.map((r) => (
                <tr key={r.style_id} className="border-t border-border">
                  <td className="px-3 py-2">
                    <span className={cn("rounded-md px-1.5 py-0.5 text-xs font-medium", r.status === "CONFIRMED" ? "bg-success-subtle text-success" : "bg-warning-subtle text-warning")}>{m(`list.status.${r.status}`)}</span>
                  </td>
                  <td className="px-3 py-2">{r.buyer ?? "—"}</td>
                  <td className="px-3 py-2 font-mono text-[13px]">{r.style}</td>
                  <td className="px-3 py-2">{r.description ?? "—"}</td>
                  <td className="px-3 py-2">{r.orders ?? "—"}</td>
                  <td className="px-3 py-2 tabular-nums">{r.order_qty ?? "—"}</td>
                  <td className="px-3 py-2">{day(r.confirm_date)}</td>
                  <td className="px-3 py-2">{day(r.ship_date)}</td>
                  <td className="px-3 py-2">{day(r.modified_on)}</td>
                  <td className="px-3 py-2">
                    <Button variant="outline" size="sm" onClick={() => onOpen(r.style_id)}>
                      {m("list.open")}
                    </Button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </Modal>
  );
}

export { YarnSpecDesk };
