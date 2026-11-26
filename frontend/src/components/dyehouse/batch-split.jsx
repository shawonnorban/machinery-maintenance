"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ChevronLeft, ChevronRight, FilePlus2, Layers, Plus, RotateCcw, Save, Search, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

const PAGE = 15;
// Each section's kinds and what moves of them, as Brickland's three cards ask it.
const SECTIONS = [
  { key: "grey", kinds: ["GREY", "FINISHED"], hint: "split.grey_hint", fields: [["qty_kg", "new_batch_kg"], ["issue_kg", "new_issue_kg"], ["rolls", "new_rolls"], ["issue_rolls", "new_issue_rolls"]] },
  { key: "cc", kinds: ["COLLAR_CUFF"], hint: "split.cc_hint", fields: [["pcs", "new_pcs"], ["issue_pcs", "new_issue_pcs"], ["issue_rolls", "new_issue_roll"]] },
  { key: "trims", kinds: ["TRIMS"], hint: "split.trims_hint", fields: [["yds", "new_yds"], ["qty_kg", "new_kg"], ["issue_pcs", "new_issue_pcs_t"], ["issue_kg", "new_issue_kg_t"]] },
];
const blankHead = { plan_qty: "", card_qty: "", lot_no: "", machine_id: "" };
const num = (v) => Number(v || 0).toLocaleString("en-US", { maximumFractionDigits: 3 });

/**
 * Dye House → Batch (Bulk) → Batch Card Splitting (AnnotechERP 17/03), laid
 * out as Brickland lays it out: the open cards on the left, searched, fifteen
 * to a page; the picked card's present weights, lot and machine on the right
 * with the new card's; then a card each for grey fabric, collar & cuff and
 * trims, where a line per item says how much moves — never more than the
 * original carries. Save split writes the new card and reduces the original
 * in one transaction.
 *
 * @param {{ batches: object[], machines: object[], canManage: boolean, load: Function, split: Function }} props
 */
function BatchSplit({ batches, machines, canManage, load, split }) {
  const t = useT("dyehouse");
  const router = useRouter();
  const toastManager = useToastManager();
  const [batch, setBatch] = useState(null);
  const [head, setHead] = useState(blankHead);
  const [lines, setLines] = useState([]);
  const [entry, setEntry] = useState({});
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(0);
  const [pending, startTransition] = useTransition();
  const s = (k, p) => t(`batch.split.${k}`, p);
  const f = (k) => t(`batch.f.${k}`);
  const warn = (title) => toastManager.add({ title, type: "danger" });

  function open(no) {
    startTransition(async () => {
      const r = await load(no);
      if (r?.status === "success") {
        setBatch(r.batch);
        setHead(blankHead);
        setLines([]);
        setEntry({});
      } else warn(r?.message);
    });
  }

  const clear = () => {
    setBatch(null);
    setHead(blankHead);
    setLines([]);
    setEntry({});
  };

  /** Brickland's Add: a section's entry row becomes a line and the row clears. */
  function add(section) {
    const e = entry[section.key] ?? {};
    const item = batch?.items.find((i) => i.id === e.item_id);
    if (!item || !section.fields.some(([k]) => Number(e[k] || 0) > 0)) return warn(s("line_incomplete"));
    if (lines.some((l) => l.item_id === item.id)) return warn(s("item_taken"));
    setLines((ls) => [...ls, { section: section.key, item_id: item.id, description: item.description, ...Object.fromEntries(section.fields.map(([k]) => [k, e[k] ?? ""])) }]);
    setEntry((x) => ({ ...x, [section.key]: {} }));
  }

  function submit() {
    if (!batch) return warn(s("pick_first"));
    startTransition(async () => {
      const payload = {
        plan_qty: head.plan_qty === "" ? null : Number(head.plan_qty),
        card_qty: head.card_qty === "" ? null : Number(head.card_qty),
        lot_no: head.lot_no || null,
        machine_id: head.machine_id || null,
        lines: lines.map((l) => {
          const { section, description, ...rest } = l;
          return Object.fromEntries(Object.entries(rest).map(([k, v]) => [k, k === "item_id" ? v : v === "" ? 0 : Number(v)]));
        }),
      };
      const r = await split(batch.batch_no, payload);
      if (r?.status === "success") {
        toastManager.add({ title: s("saved", { new: r.data.new.batch_no, old: r.data.old.batch_no }), type: "success" });
        setBatch(r.data.old);
        setHead(blankHead);
        setLines([]);
        router.refresh();
      } else warn(r?.errors ? Object.values(r.errors).flat()[0] : r?.message);
    });
  }

  const needle = query.trim().toLowerCase();
  const shown = batches.filter((b) => !needle || b.batch_no.toLowerCase().includes(needle));
  const pages = Math.max(1, Math.ceil(shown.length / PAGE));
  const at = Math.min(page, pages - 1);
  const slice = shown.slice(at * PAGE, at * PAGE + PAGE);
  const machineOptions = machines.map((x) => ({ value: x.value, label: x.label }));

  const field = (label, content, { required = false, hint = null } = {}) => (
    <label className="flex flex-col gap-1.5">
      <span className="text-[13px] font-semibold">
        {label}
        {required ? <span className="ml-0.5 text-danger">*</span> : null}
      </span>
      {content}
      {hint ? <span className="text-xs text-foreground-muted">{hint}</span> : null}
    </label>
  );
  const shownValue = (v) => (v === null || v === undefined ? "" : String(v));

  return (
    <div className="grid items-start gap-4 xl:grid-cols-[22rem_1fr]">
      <div className="rounded-lg border border-border bg-surface p-4">
        <h2 className="mb-3 text-base font-semibold">{s("plan_list")}</h2>
        <div className="relative mb-3">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground-subtle" />
          <Input
            value={query}
            onChange={(e) => {
              setQuery(e.target.value);
              setPage(0);
            }}
            placeholder={s("search")}
            className="pl-9"
          />
        </div>
        <table className="w-full overflow-hidden rounded-md border border-border text-sm">
          <thead>
            <tr className="bg-brand text-left text-brand-foreground">
              <th className="px-3 py-2 font-semibold">{f("batch_no")}</th>
              <th className="px-3 py-2 text-right font-semibold">{f("qty_kg")}</th>
            </tr>
          </thead>
          <tbody>
            {slice.length === 0 ? (
              <tr>
                <td colSpan={2} className="px-3 py-6 text-center text-foreground-muted">
                  {s("none")}
                </td>
              </tr>
            ) : null}
            {slice.map((b) => (
              <tr key={b.batch_no} className={cn("border-t border-border", batch?.batch_no === b.batch_no ? "bg-brand-subtle" : "hover:bg-surface-muted")}>
                <td className="px-3 py-1.5">
                  <button type="button" onClick={() => open(b.batch_no)} disabled={pending} className="font-mono font-medium text-brand hover:underline">
                    {b.batch_no}
                  </button>
                </td>
                <td className="px-3 py-1.5 text-right tabular-nums">{num(b.qty)}</td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="mt-3 flex items-center justify-between gap-2 text-[13px] text-foreground-muted">
          <span>{s("showing", { from: shown.length ? at * PAGE + 1 : 0, to: at * PAGE + slice.length, total: shown.length })}</span>
          <span className="flex items-center gap-1">
            <Button variant="outline" size="sm" onClick={() => setPage(at - 1)} disabled={at === 0} aria-label="‹">
              <ChevronLeft />
            </Button>
            <span className="tabular-nums">
              {at + 1} / {pages}
            </span>
            <Button variant="outline" size="sm" onClick={() => setPage(at + 1)} disabled={at >= pages - 1} aria-label="›">
              <ChevronRight />
            </Button>
          </span>
        </div>
      </div>

      <div className="flex flex-col gap-4">
        <div className="rounded-lg border border-border bg-surface p-5">
          <div className="mb-4 flex items-start justify-between gap-3">
            <div>
              <h2 className="flex items-center gap-2 text-base font-semibold">
                <Layers className="size-4" /> {s("title")}
              </h2>
              <p className="mt-0.5 text-[13px] text-foreground-muted">{s("hint")}</p>
            </div>
            <span className="shrink-0 rounded-full bg-brand-subtle px-2.5 py-0.5 text-xs font-semibold text-brand">{s("new_badge")}</span>
          </div>
          <div className="grid gap-4 sm:grid-cols-3">
            {field(f("batch_no"), <Input value={batch?.batch_no ?? ""} placeholder={s("pick_left")} readOnly />)}
            {field(f("present_plan_qty"), <Input value={shownValue(batch?.plan_qty)} readOnly />)}
            {field(f("present_card_qty"), <Input value={shownValue(batch?.card_qty)} readOnly />)}
            {field(f("present_lot"), <Input value={batch?.lot_no ?? ""} readOnly />)}
            {field(f("old_machine"), <Input value={batch?.machine ?? ""} readOnly />)}
            {field(f("new_plan_qty"), <Input type="number" min="0" step="0.01" value={head.plan_qty} onChange={(e) => setHead((h) => ({ ...h, plan_qty: e.target.value }))} disabled={!canManage} />, { required: true, hint: s("plan_qty_hint") })}
            {field(f("new_card_qty"), <Input type="number" min="0" step="0.01" value={head.card_qty} onChange={(e) => setHead((h) => ({ ...h, card_qty: e.target.value }))} disabled={!canManage} />, { required: true })}
            {field(f("new_lot"), <Input value={head.lot_no} onChange={(e) => setHead((h) => ({ ...h, lot_no: e.target.value }))} maxLength={40} disabled={!canManage} />, { required: true })}
            {field(f("new_machine"), <Select value={head.machine_id} onValueChange={(v) => setHead((h) => ({ ...h, machine_id: v ?? "" }))} options={machineOptions} placeholder={s("select_mc")} disabled={!canManage} />, {
              hint: machineOptions.length ? null : s("no_choices"),
            })}
          </div>
        </div>

        {SECTIONS.map((section) => {
          const items = (batch?.items ?? []).filter((i) => section.kinds.includes(i.kind)).map((i) => ({ value: i.id, label: `${i.description} · ${s("on_card", { qty: num(i.qty_kg || i.pcs || i.yds) })}` }));
          const mine = lines.filter((l) => l.section === section.key);
          const e = entry[section.key] ?? {};
          const setE = (patch) => setEntry((x) => ({ ...x, [section.key]: { ...(x[section.key] ?? {}), ...patch } }));
          return (
            <div key={section.key} className="rounded-lg border border-border bg-surface p-5">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 max-w-2xl flex-1">
                  <h3 className="text-base font-semibold">{t(`batch.opt.kind.${section.kinds[0]}`)}</h3>
                  <p className="mt-0.5 text-[13px] text-foreground-muted">{s(section.hint.replace("split.", ""))}</p>
                </div>
                {canManage ? (
                  <div className="flex shrink-0 gap-2">
                    <Button variant="outline" size="sm" onClick={() => add(section)}>
                      <Plus /> {s("add")}
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => setEntry((x) => ({ ...x, [section.key]: {} }))}>
                      {s("cancel")}
                    </Button>
                  </div>
                ) : null}
              </div>
              {canManage ? (
                <div className="mt-3 grid gap-4 sm:grid-cols-3">
                  {field(f("item"), <Select value={e.item_id ?? ""} onValueChange={(v) => setE({ item_id: v ?? "" })} options={items} placeholder={s("select_item")} />, { hint: items.length ? null : s("no_choices") })}
                  {section.fields.map(([k, label]) => (
                    <div key={k}>{field(f(label), <Input type="number" min="0" step={k.includes("kg") || k === "yds" ? "0.001" : "1"} value={e[k] ?? ""} onChange={(ev) => setE({ [k]: ev.target.value })} />)}</div>
                  ))}
                </div>
              ) : null}
              {mine.length === 0 ? (
                <div className="flex flex-col items-center gap-2 px-4 py-8 text-center">
                  <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
                    <FilePlus2 className="size-5" />
                  </span>
                  <p className="text-sm font-semibold text-foreground">{s("no_lines")}</p>
                  <p className="max-w-sm text-[13px] text-foreground-muted">{s("no_lines_hint")}</p>
                </div>
              ) : (
                <div className="mt-4 overflow-x-auto rounded-md border border-border">
                  <table className="w-full min-w-max text-sm">
                    <thead>
                      <tr className="bg-brand text-left text-brand-foreground">
                        <th className="px-3 py-2.5 font-semibold">{f("item")}</th>
                        {section.fields.map(([k, label]) => (
                          <th key={k} className="px-3 py-2.5 text-right font-semibold">
                            {f(label)}
                          </th>
                        ))}
                        <th className="w-10 px-3 py-2.5" />
                      </tr>
                    </thead>
                    <tbody>
                      {mine.map((l) => (
                        <tr key={l.item_id} className="border-b border-border last:border-0">
                          <td className="px-3 py-1.5">{l.description}</td>
                          {section.fields.map(([k]) => (
                            <td key={k} className="px-3 py-1.5">
                              <Input type="number" min="0" value={l[k]} onChange={(ev) => setLines((ls) => ls.map((x) => (x.item_id === l.item_id ? { ...x, [k]: ev.target.value } : x)))} disabled={!canManage} className="ml-auto w-28 text-right" aria-label={f(section.fields.find(([x]) => x === k)[1])} />
                            </td>
                          ))}
                          <td className="px-3 py-1.5">
                            {canManage ? (
                              <Button variant="ghost" size="sm" onClick={() => setLines((ls) => ls.filter((x) => x.item_id !== l.item_id))} aria-label={`${s("remove")} ${l.description}`}>
                                <Trash2 />
                              </Button>
                            ) : null}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          );
        })}

        {canManage ? (
          <div className="rounded-lg border border-border bg-surface p-5">
            <p className="text-[13px] text-foreground-muted">{s("footer")}</p>
            <div className="mt-3 flex gap-2">
              <Button variant="outline" onClick={clear} disabled={pending}>
                <RotateCcw /> {s("clear")}
              </Button>
              <Button onClick={submit} loading={pending}>
                <Save /> {s("save")}
              </Button>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}

export { BatchSplit };
