"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import {
  ArrowDown,
  ArrowUp,
  Download,
  ListChecks,
  Plus,
  RotateCcw,
  Save,
  Trash2,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Modal } from "@/components/ui/modal";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/*
 * Fabric Requirement → Operations (AnnotechERP 13/02): the editors Brickland
 * opens beside a chosen group — a constraint family's areas and score bands,
 * a section's closed days, and a scheduling board's batches. Rows change in
 * place; Revert goes back to what was saved, Save keeps them all at once.
 */

const card = "rounded-lg border border-border bg-surface";
const firstError = (res) =>
  Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message;
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
let seq = 0;
const key = () => `n${++seq}`;
const keyed = (rows) => rows.map((x) => ({ ...x, key: x.id }));

function useOpsT() {
  const t = useT("fabricplanning");
  return (k, x) => t(`ops.${k}`, x);
}

/**
 * A grid's rows against what was saved, its Save and its Revert. Saved rows
 * come back through `prepare` (their keys); the groups beside refresh.
 */
function useRows(initial, save, savedToast, prepare = (rows) => rows) {
  const toastManager = useToastManager();
  const router = useRouter();
  const [base, setBase] = useState(initial);
  const [rows, setRows] = useState(initial);
  const [pending, startTransition] = useTransition();
  const dirty = !same(base, rows);
  const change = (i, patch) =>
    setRows((rs) => rs.map((r, j) => (j === i ? { ...r, ...patch } : r)));
  const submit = () =>
    startTransition(async () => {
      const res = await save(rows);
      if (res?.status !== "success")
        return toastManager.add({ title: firstError(res), type: "danger" });
      const saved = prepare(res.data.rows);
      setBase(saved);
      setRows(saved);
      toastManager.add({ title: savedToast(res.data), type: "success" });
      router.refresh();
    });
  return {
    rows,
    setRows,
    change,
    dirty,
    pending,
    submit,
    revert: () => setRows(base),
  };
}

/** Brickland's editor card: title and hint, its actions, the grid, the foot note, Revert and Save. */
function EditorCard({
  title,
  hint,
  actions,
  foot,
  dirty,
  pending,
  onRevert,
  onSave,
  canManage,
  children,
}) {
  const o = useOpsT();
  return (
    <section className={cn(card, "min-w-0 overflow-hidden")}>
      <div className="flex flex-wrap items-start justify-between gap-3 px-5 pt-5 pb-4">
        <div className="flex gap-3">
          <ListChecks
            className="mt-0.5 size-4 text-foreground-muted"
            aria-hidden
          />
          <div>
            <h2 className="text-base font-semibold text-foreground">{title}</h2>
            <p className="text-[13px] text-foreground-muted">{hint}</p>
          </div>
        </div>
        <div className="flex flex-wrap gap-2">{actions}</div>
      </div>
      <div className="overflow-x-auto">{children}</div>
      <div className="border-t border-border px-5 py-4">
        <p className="text-[13px] text-foreground-muted">{foot}</p>
        {canManage ? (
          <div className="mt-3 flex items-center gap-2">
            <Button
              variant="outline"
              disabled={!dirty || pending}
              onClick={onRevert}
            >
              <RotateCcw className="size-4" aria-hidden /> {o("revert")}
            </Button>
            <Button disabled={!dirty} loading={pending} onClick={onSave}>
              <Save className="size-4" aria-hidden /> {o("save")}
            </Button>
            {dirty ? (
              <span className="text-xs text-foreground-muted">
                {o("unsaved")}
              </span>
            ) : null}
          </div>
        ) : null}
      </div>
    </section>
  );
}

function Head({ cols }) {
  return (
    <thead className="bg-brand text-brand-foreground">
      <tr>
        {cols.map(([label, right], i) => (
          <th
            key={i}
            className={cn(
              "px-3 py-2.5 text-left text-xs font-semibold whitespace-nowrap",
              right && "text-right",
            )}
          >
            {label}
          </th>
        ))}
      </tr>
    </thead>
  );
}

function Empty({ cols, text }) {
  return (
    <tr>
      <td
        colSpan={cols}
        className="px-3 py-10 text-center text-[13px] text-foreground-muted"
      >
        {text}
      </td>
    </tr>
  );
}

const cell = "h-8 px-2 text-xs";

/** A CSV of the rows as shown, Brickland's Export. */
function exportCsv(name, head, rows) {
  const esc = (v) => `"${String(v ?? "").replaceAll('"', '""')}"`;
  const csv = [head, ...rows].map((r) => r.map(esc).join(",")).join("\n");
  const link = document.createElement("a");
  link.href = URL.createObjectURL(
    new Blob([csv], { type: "text/csv;charset=utf-8" }),
  );
  link.download = `${name}.csv`;
  link.click();
}

/* ---------------- Planning Constraint Ruleset → areas ---------------- */

/** @param {{ group: object, rows: object[], save: Function, canManage: boolean }} props */
function StatusAreasEditor({ group, rows: initial, save, canManage }) {
  const o = useOpsT();
  const te = useT("erp");
  const r = useRows(
    keyed(initial),
    (rows) =>
      save(
        rows.map(({ id, sl, code, name, wt_factor, active }) => ({
          id,
          sl,
          code,
          name,
          wt_factor,
          active,
        })),
      ),
    () => o("area.saved", { group: group.label }),
    keyed,
  );
  const cols = [
    [o("f.sl"), true],
    [o("f.code")],
    [o("f.parent")],
    [o("f.area")],
    [o("f.wt"), true],
    [o("f.active")],
    [o("f.status")],
    [o("f.action")],
  ];
  const add = () =>
    r.setRows((rs) => [
      ...rs,
      {
        key: key(),
        id: null,
        sl: rs.length + 1,
        code: "",
        parent: null,
        name: "",
        wt_factor: 1,
        active: true,
      },
    ]);

  return (
    <EditorCard
      title={o("area.title_of", { group: group.label })}
      hint={o("area.hint")}
      foot={o("area.foot", { n: r.rows.length })}
      canManage={canManage}
      {...{ dirty: r.dirty, pending: r.pending }}
      onRevert={r.revert}
      onSave={r.submit}
      actions={
        <>
          <Button
            size="sm"
            variant="outline"
            onClick={() =>
              exportCsv(
                `status-areas-${group.key}`,
                cols.slice(0, 5).map((c) => c[0]),
                r.rows.map((x) => [
                  x.sl,
                  x.code,
                  x.parent ?? "",
                  x.name,
                  x.wt_factor,
                ]),
              )
            }
          >
            <Download className="size-4" aria-hidden /> {o("export")}
          </Button>
          {canManage ? (
            <Button size="sm" variant="outline" onClick={add}>
              <Plus className="size-4" aria-hidden /> {o("area.add")}
            </Button>
          ) : null}
        </>
      }
    >
      <table className="w-full min-w-[820px] text-sm">
        <Head cols={cols} />
        <tbody>
          {r.rows.length === 0 ? (
            <Empty
              cols={cols.length}
              text={o("area.empty", { group: group.label })}
            />
          ) : null}
          {r.rows.map((x, i) => (
            <tr key={x.key} className="border-t border-border">
              <td className="px-3 py-2 text-right">
                <Input
                  type="number"
                  min="0"
                  value={x.sl}
                  disabled={!canManage}
                  onChange={(ev) => r.change(i, { sl: ev.target.value })}
                  aria-label={o("f.sl")}
                  className={cn(cell, "w-16 text-right")}
                />
              </td>
              <td className="px-3 py-2 font-mono text-xs">
                {x.id ? (
                  x.code
                ) : (
                  <Input
                    value={x.code}
                    onChange={(ev) =>
                      r.change(i, { code: ev.target.value.toUpperCase() })
                    }
                    aria-label={o("f.code")}
                    className={cn(cell, "w-28 font-mono")}
                  />
                )}
              </td>
              <td className="px-3 py-2 text-xs text-foreground-muted">
                {x.parent ?? "—"}
              </td>
              <td className="px-3 py-2">
                <Input
                  value={x.name}
                  disabled={!canManage}
                  onChange={(ev) => r.change(i, { name: ev.target.value })}
                  aria-label={o("f.area")}
                  className={cn(cell, "min-w-48")}
                />
              </td>
              <td className="px-3 py-2 text-right">
                <Input
                  type="number"
                  min="0"
                  step="0.0001"
                  value={x.wt_factor}
                  disabled={!canManage}
                  onChange={(ev) => r.change(i, { wt_factor: ev.target.value })}
                  aria-label={o("f.wt")}
                  className={cn(cell, "w-24 text-right")}
                />
              </td>
              <td className="px-3 py-2">
                <input
                  type="checkbox"
                  checked={Boolean(x.active)}
                  disabled={!canManage}
                  onChange={(ev) => r.change(i, { active: ev.target.checked })}
                  aria-label={o("f.active")}
                  className="size-4 accent-[var(--brand)]"
                />
              </td>
              <td className="px-3 py-2">
                <span
                  className={cn(
                    "rounded-full px-2 py-0.5 text-xs font-medium",
                    x.active
                      ? "bg-success-subtle text-success"
                      : "bg-surface-muted text-foreground-muted",
                  )}
                >
                  {te(x.active ? "active" : "inactive")}
                </span>
                {x.id ? null : (
                  <span className="ml-1 rounded-full bg-info-subtle px-2 py-0.5 text-xs text-info">
                    {o("area.new_tag")}
                  </span>
                )}
              </td>
              <td className="px-3 py-2">
                {canManage ? (
                  <Button
                    size="sm"
                    variant="ghost"
                    className="text-danger"
                    onClick={() =>
                      r.setRows((rs) => rs.filter((_, j) => j !== i))
                    }
                  >
                    {o("area.remove")}
                  </Button>
                ) : null}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </EditorCard>
  );
}

/* ---------------- Planning Constraint Ruleset → rules ---------------- */

const STANDING_TONE = {
  POOR: "bg-danger-subtle text-danger",
  WATCH: "bg-warning-subtle text-warning",
  GOOD: "bg-success-subtle text-success",
};
const standing = (rank) =>
  Number(rank) < 0.5 ? "POOR" : Number(rank) < 0.75 ? "WATCH" : "GOOD";

/** @param {{ group: object, rows: object[], save: Function, canManage: boolean }} props */
function StatusRulesEditor({ group, rows: initial, save, canManage }) {
  const o = useOpsT();
  const r = useRows(
    keyed(initial),
    (rows) =>
      save(
        rows.map(
          ({
            id,
            score_from,
            score_to,
            status_code,
            status_desc,
            color_code,
            green_rank,
          }) => ({
            id,
            score_from,
            score_to,
            status_code,
            status_desc,
            color_code,
            green_rank,
          }),
        ),
      ),
    () => o("rule.saved", { group: group.label }),
    keyed,
  );
  const cols = [
    [o("f.score_from"), true],
    [o("f.score_to"), true],
    [o("f.status_code")],
    [o("f.status_desc")],
    [o("f.color")],
    [o("f.rank"), true],
    [o("f.status")],
    [o("f.action")],
  ];
  const add = () =>
    r.setRows((rs) => [
      ...rs,
      {
        key: key(),
        id: null,
        score_from: "",
        score_to: "",
        status_code: "",
        status_desc: "",
        color_code: "#16A34A",
        green_rank: 0.5,
      },
    ]);
  const num = (i, k, x, extra = {}) => (
    <Input
      type="number"
      min="0"
      step="0.0001"
      value={x[k]}
      disabled={!canManage}
      onChange={(ev) => r.change(i, { [k]: ev.target.value })}
      aria-label={o(`f.${extra.label ?? k}`)}
      className={cn(cell, "w-24 text-right")}
      {...(extra.max ? { max: extra.max } : {})}
    />
  );

  return (
    <EditorCard
      title={o("rule.title_of", { group: group.label })}
      hint={o("rule.hint")}
      foot={o("rule.foot", { n: r.rows.length })}
      canManage={canManage}
      {...{ dirty: r.dirty, pending: r.pending }}
      onRevert={r.revert}
      onSave={r.submit}
      actions={
        <>
          <Button
            size="sm"
            variant="outline"
            onClick={() =>
              exportCsv(
                `status-rules-${group.key}`,
                cols.slice(0, 6).map((c) => c[0]),
                r.rows.map((x) => [
                  x.score_from,
                  x.score_to,
                  x.status_code,
                  x.status_desc,
                  x.color_code,
                  x.green_rank,
                ]),
              )
            }
          >
            <Download className="size-4" aria-hidden /> {o("export")}
          </Button>
          {canManage ? (
            <Button size="sm" variant="outline" onClick={add}>
              <Plus className="size-4" aria-hidden /> {o("rule.add")}
            </Button>
          ) : null}
        </>
      }
    >
      <table className="w-full min-w-[1000px] text-sm">
        <Head cols={cols} />
        <tbody>
          {r.rows.length === 0 ? (
            <Empty
              cols={cols.length}
              text={o("rule.empty", { group: group.label })}
            />
          ) : null}
          {r.rows.map((x, i) => (
            <tr key={x.key} className="border-t border-border">
              <td className="px-3 py-2 text-right">
                {num(i, "score_from", x)}
              </td>
              <td className="px-3 py-2 text-right">{num(i, "score_to", x)}</td>
              <td className="px-3 py-2 font-mono text-xs">
                {x.id ? (
                  x.status_code
                ) : (
                  <Input
                    value={x.status_code}
                    onChange={(ev) =>
                      r.change(i, {
                        status_code: ev.target.value.toUpperCase(),
                      })
                    }
                    aria-label={o("f.status_code")}
                    className={cn(cell, "w-28 font-mono")}
                  />
                )}
              </td>
              <td className="px-3 py-2">
                <Input
                  value={x.status_desc}
                  disabled={!canManage}
                  onChange={(ev) =>
                    r.change(i, { status_desc: ev.target.value })
                  }
                  aria-label={o("f.status_desc")}
                  className={cn(cell, "min-w-48")}
                />
              </td>
              <td className="px-3 py-2">
                <label className="flex h-8 min-w-32 items-center gap-2 rounded-lg border border-border-strong bg-surface px-2 text-xs">
                  <input
                    type="color"
                    value={x.color_code}
                    disabled={!canManage}
                    onChange={(ev) =>
                      r.change(i, {
                        color_code: ev.target.value.toUpperCase(),
                      })
                    }
                    aria-label={o("f.color")}
                    className="size-5 cursor-pointer rounded border-0 bg-transparent p-0"
                  />
                  <span className="font-mono uppercase">{x.color_code}</span>
                </label>
              </td>
              <td className="px-3 py-2 text-right">
                {num(i, "green_rank", x, { label: "rank", max: 1 })}
              </td>
              <td className="px-3 py-2">
                <span
                  className={cn(
                    "rounded-full px-2 py-0.5 text-xs font-medium",
                    STANDING_TONE[standing(x.green_rank)],
                  )}
                >
                  {o(`opt.standing.${standing(x.green_rank)}`)}
                </span>
              </td>
              <td className="px-3 py-2">
                {canManage ? (
                  <Button
                    size="sm"
                    variant="ghost"
                    className="text-danger"
                    onClick={() =>
                      r.setRows((rs) => rs.filter((_, j) => j !== i))
                    }
                  >
                    {o("rule.remove")}
                  </Button>
                ) : null}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </EditorCard>
  );
}

/* ---------------- Factory Calendar & Holidays → a section's days ---------------- */

const DAY_TYPES = ["WEEKLY_OFF", "GOVT_HOLIDAY", "COMPANY_HOLIDAY"];

/** @param {{ section: object, rows: object[], options: object, save: Function, canManage: boolean }} props */
function SectionDaysEditor({
  section,
  rows: initial,
  options,
  save,
  canManage,
}) {
  const o = useOpsT();
  const r = useRows(
    keyed(initial),
    (rows) =>
      save(
        rows.map(({ id, factory_id, day, day_type, name }) => ({
          id,
          factory_id,
          day,
          day_type,
          name: name || null,
        })),
      ),
    () => o("hol.saved", { section: section.label }),
    keyed,
  );
  const blank = {
    company: options.company.value,
    factory_id: options.factories[0]?.value ?? "",
    day: "",
    day_type: "WEEKLY_OFF",
    name: "",
  };
  const [draft, setDraft] = useState(null);
  const cols = [
    [o("f.office")],
    [o("f.date")],
    [o("f.day_type")],
    [o("f.name")],
    [o("f.action")],
  ];
  const sel =
    "h-8 rounded-lg border border-border-strong bg-surface px-2 text-xs disabled:opacity-60";
  const field = (label, required, control) => (
    <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
      <span>
        {label}
        {required ? <span className="text-danger"> *</span> : null}
      </span>
      {control}
    </label>
  );

  return (
    <>
      <EditorCard
        title={o("hol.title_of", { section: section.label })}
        hint={o("hol.hint")}
        foot={o("hol.foot", { n: r.rows.length })}
        canManage={canManage}
        {...{ dirty: r.dirty, pending: r.pending }}
        onRevert={r.revert}
        onSave={r.submit}
        actions={
          canManage ? (
            <Button size="sm" variant="outline" onClick={() => setDraft(blank)}>
              <Plus className="size-4" aria-hidden /> {o("hol.close")}
            </Button>
          ) : null
        }
      >
        <table className="w-full min-w-[760px] text-sm">
          <Head cols={cols} />
          <tbody>
            {r.rows.length === 0 ? (
              <Empty
                cols={cols.length}
                text={o("hol.empty", { section: section.label })}
              />
            ) : null}
            {r.rows.map((x, i) => (
              <tr key={x.key} className="border-t border-border">
                <td className="px-3 py-2">
                  <select
                    value={x.factory_id}
                    disabled={!canManage}
                    onChange={(ev) =>
                      r.change(i, { factory_id: ev.target.value })
                    }
                    aria-label={o("f.office")}
                    className={cn(sel, "w-56")}
                  >
                    {options.factories.map((f) => (
                      <option key={f.value} value={f.value}>
                        {f.label}
                      </option>
                    ))}
                  </select>
                </td>
                <td className="px-3 py-2">
                  <Input
                    type="date"
                    value={x.day}
                    disabled={!canManage}
                    onChange={(ev) => r.change(i, { day: ev.target.value })}
                    aria-label={o("f.date")}
                    className={cn(cell, "w-40")}
                  />
                </td>
                <td className="px-3 py-2">
                  <select
                    value={x.day_type}
                    disabled={!canManage}
                    onChange={(ev) =>
                      r.change(i, { day_type: ev.target.value })
                    }
                    aria-label={o("f.day_type")}
                    className={cn(sel, "w-40")}
                  >
                    {DAY_TYPES.map((t) => (
                      <option key={t} value={t}>
                        {o(`opt.day_type.${t}`)}
                      </option>
                    ))}
                  </select>
                </td>
                <td className="px-3 py-2">
                  <Input
                    value={x.name ?? ""}
                    disabled={!canManage}
                    onChange={(ev) => r.change(i, { name: ev.target.value })}
                    aria-label={o("f.name")}
                    className={cn(cell, "min-w-48")}
                  />
                </td>
                <td className="px-3 py-2">
                  {canManage ? (
                    <Button
                      size="sm"
                      variant="ghost"
                      className="text-danger"
                      onClick={() =>
                        r.setRows((rs) => rs.filter((_, j) => j !== i))
                      }
                    >
                      <Trash2 className="size-4" aria-hidden />{" "}
                      {o("hol.remove")}
                    </Button>
                  ) : null}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </EditorCard>
      {draft ? (
        <Modal
          open
          onOpenChange={(v) => !v && setDraft(null)}
          title={o("hol.close_title", { section: section.label })}
          className="max-w-2xl"
          footer={
            <>
              <Button variant="outline" onClick={() => setDraft(blank)}>
                <RotateCcw className="size-4" aria-hidden /> {o("revert")}
              </Button>
              <Button
                disabled={
                  !draft.company ||
                  !draft.factory_id ||
                  !draft.day ||
                  !draft.day_type
                }
                onClick={() => {
                  r.setRows((rs) => [
                    ...rs,
                    {
                      key: key(),
                      id: null,
                      factory_id: draft.factory_id,
                      day: draft.day,
                      day_type: draft.day_type,
                      name: draft.name,
                    },
                  ]);
                  setDraft(null);
                }}
              >
                <Save className="size-4" aria-hidden /> {o("hol.close")}
              </Button>
            </>
          }
        >
          <p className="text-sm font-semibold text-foreground">
            {o("hol.new_day")}
          </p>
          <p className="mb-3 text-[13px] text-foreground-muted">
            {o("hol.new_day_hint")}
          </p>
          <div className="grid gap-3 sm:grid-cols-3">
            {field(
              o("f.company"),
              true,
              <select
                value={draft.company}
                onChange={(ev) =>
                  setDraft({ ...draft, company: ev.target.value })
                }
                aria-label={o("f.company")}
                className="h-10 rounded-lg border border-border-strong bg-surface px-3 text-sm"
              >
                <option value="">—</option>
                <option value={options.company.value}>
                  {options.company.label}
                </option>
              </select>,
            )}
            {field(
              o("f.office"),
              true,
              <select
                value={draft.factory_id}
                onChange={(ev) =>
                  setDraft({ ...draft, factory_id: ev.target.value })
                }
                aria-label={o("f.office")}
                className="h-10 rounded-lg border border-border-strong bg-surface px-3 text-sm"
              >
                <option value="">—</option>
                {options.factories.map((f) => (
                  <option key={f.value} value={f.value}>
                    {f.label}
                  </option>
                ))}
              </select>,
            )}
            {field(
              o("f.date"),
              true,
              <Input
                type="date"
                value={draft.day}
                onChange={(ev) => setDraft({ ...draft, day: ev.target.value })}
                aria-label={o("f.date")}
              />,
            )}
            {field(
              o("f.holyday_type"),
              true,
              <select
                value={draft.day_type}
                onChange={(ev) =>
                  setDraft({ ...draft, day_type: ev.target.value })
                }
                aria-label={o("f.holyday_type")}
                className="h-10 rounded-lg border border-border-strong bg-surface px-3 text-sm"
              >
                {DAY_TYPES.map((t) => (
                  <option key={t} value={t}>
                    {o(`opt.day_type.${t}`)}
                  </option>
                ))}
              </select>,
            )}
            <div className="sm:col-span-3">
              {field(
                o("f.name"),
                false,
                <Input
                  value={draft.name}
                  onChange={(ev) =>
                    setDraft({ ...draft, name: ev.target.value })
                  }
                  aria-label={o("f.name")}
                />,
              )}
            </div>
          </div>
        </Modal>
      ) : null}
    </>
  );
}

/* ---------------- Batch Schedule Board → a board's batches ---------------- */

const qty = (n) =>
  n === null || n === undefined
    ? "—"
    : Number(n).toLocaleString("en-US", { maximumFractionDigits: 2 });

/** @param {{ board: object, data: object, options: object, save: Function, canManage: boolean }} props */
function ScheduleBoardEditor({ board, data, options, save, canManage }) {
  const o = useOpsT();
  const toastManager = useToastManager();
  const plans = Object.fromEntries(data.batch_plans.map((p) => [p.bp_no, p]));
  const asRows = (rows) =>
    rows.map(({ bp_no, dye_machine_id, planned_start, planned_end }) => ({
      bp_no,
      dye_machine_id: dye_machine_id ?? "",
      planned_start: planned_start ?? "",
      planned_end: planned_end ?? "",
    }));
  const r = useRows(
    asRows(data.rows),
    async (rows) => {
      const res = await save(
        rows.map((x) => ({
          bp_no: x.bp_no,
          dye_machine_id: x.dye_machine_id || null,
          planned_start: x.planned_start || null,
          planned_end: x.planned_end || null,
        })),
      );
      return res?.status === "success"
        ? { ...res, data: { ...res.data, rows: asRows(res.data.rows) } }
        : res;
    },
    (d) => o("sb.saved", { plan: board.label, n: d.rows.length }),
  );
  const blank = {
    bp_no: "",
    dye_machine_id: "",
    planned_start: "",
    planned_end: "",
  };
  const [draft, setDraft] = useState(null);
  const machines = Object.fromEntries(
    options.machines.map((m) => [m.value, m.label]),
  );
  const move = (i, by) =>
    r.setRows((rs) => {
      const next = [...rs];
      const [x] = next.splice(i, 1);
      next.splice(i + by, 0, x);
      return next;
    });
  const place = () => {
    const at = r.rows.findIndex((x) => x.bp_no === draft.bp_no);
    const row = {
      ...draft,
      dye_machine_id:
        draft.dye_machine_id || plans[draft.bp_no]?.dye_machine_id || "",
    };
    if (at >= 0) {
      // Placing a batch again moves it rather than duplicating it.
      r.change(at, row);
      toastManager.add({
        title: o("sb.moved", { no: draft.bp_no }),
        type: "info",
      });
    } else r.setRows((rs) => [...rs, row]);
    setDraft(null);
  };
  const cols = [
    [o("f.seq"), true],
    [o("f.bp_no")],
    [o("f.buyer")],
    [o("f.week")],
    [o("f.colour_group")],
    [o("f.qty"), true],
    [o("f.machine")],
    [o("f.start")],
    [o("f.end")],
    [o("f.action")],
  ];
  const sel =
    "h-8 rounded-lg border border-border-strong bg-surface px-2 text-xs disabled:opacity-60";

  return (
    <>
      <EditorCard
        title={o("sb.title_of", { plan: board.label })}
        hint={o("sb.hint")}
        foot={o("sb.foot", { n: r.rows.length })}
        canManage={canManage}
        {...{ dirty: r.dirty, pending: r.pending }}
        onRevert={r.revert}
        onSave={r.submit}
        actions={
          canManage ? (
            <Button size="sm" variant="outline" onClick={() => setDraft(blank)}>
              <Plus className="size-4" aria-hidden /> {o("sb.place")}
            </Button>
          ) : null
        }
      >
        <table className="w-full min-w-[1100px] text-sm">
          <Head cols={cols} />
          <tbody>
            {r.rows.length === 0 ? (
              <Empty
                cols={cols.length}
                text={o("sb.empty", { plan: board.label })}
              />
            ) : null}
            {r.rows.map((x, i) => {
              const p = plans[x.bp_no];
              return (
                <tr key={x.bp_no} className="border-t border-border">
                  <td className="px-3 py-2 text-right tabular-nums">{i + 1}</td>
                  <td className="px-3 py-2 font-semibold whitespace-nowrap">
                    {x.bp_no}
                  </td>
                  <td className="px-3 py-2">
                    {[p?.buyer, p?.style_job].filter(Boolean).join(" · ") ||
                      "—"}
                  </td>
                  <td className="px-3 py-2 whitespace-nowrap text-xs">
                    {p?.week ?? "—"}
                  </td>
                  <td className="px-3 py-2">{p?.colour_group ?? "—"}</td>
                  <td className="px-3 py-2 text-right tabular-nums">
                    {qty(p?.bp_qty)}
                  </td>
                  <td className="px-3 py-2">
                    <select
                      value={x.dye_machine_id}
                      disabled={!canManage}
                      onChange={(ev) =>
                        r.change(i, { dye_machine_id: ev.target.value })
                      }
                      aria-label={`${x.bp_no} ${o("f.machine")}`}
                      className={cn(sel, "w-40")}
                    >
                      <option value="">—</option>
                      {options.machines.map((m) => (
                        <option key={m.value} value={m.value}>
                          {m.label}
                        </option>
                      ))}
                    </select>
                  </td>
                  {["planned_start", "planned_end"].map((k) => (
                    <td key={k} className="px-3 py-2">
                      <Input
                        type="date"
                        value={x[k]}
                        disabled={!canManage}
                        onChange={(ev) => r.change(i, { [k]: ev.target.value })}
                        aria-label={`${x.bp_no} ${o(k === "planned_start" ? "f.start" : "f.end")}`}
                        className={cn(cell, "w-36")}
                      />
                    </td>
                  ))}
                  <td className="px-3 py-2">
                    {canManage ? (
                      <div className="flex gap-1">
                        <Button
                          size="icon"
                          variant="ghost"
                          className="size-8"
                          disabled={i === 0}
                          onClick={() => move(i, -1)}
                          aria-label={o("sb.up", { no: x.bp_no })}
                        >
                          <ArrowUp className="size-4" aria-hidden />
                        </Button>
                        <Button
                          size="icon"
                          variant="ghost"
                          className="size-8"
                          disabled={i === r.rows.length - 1}
                          onClick={() => move(i, 1)}
                          aria-label={o("sb.down", { no: x.bp_no })}
                        >
                          <ArrowDown className="size-4" aria-hidden />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          className="text-danger"
                          onClick={() =>
                            r.setRows((rs) => rs.filter((_, j) => j !== i))
                          }
                        >
                          {o("sb.remove")}
                        </Button>
                      </div>
                    ) : null}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </EditorCard>
      {draft ? (
        <Modal
          open
          onOpenChange={(v) => !v && setDraft(null)}
          title={o("sb.place_title", { plan: board.label })}
          className="max-w-2xl"
          footer={
            <>
              <Button variant="outline" onClick={() => setDraft(blank)}>
                <RotateCcw className="size-4" aria-hidden /> {o("revert")}
              </Button>
              <Button disabled={!draft.bp_no} onClick={place}>
                <Save className="size-4" aria-hidden /> {o("sb.place")}
              </Button>
            </>
          }
        >
          <p className="text-sm font-semibold text-foreground">
            {o("sb.new_batch")}
          </p>
          <p className="mb-3 text-[13px] text-foreground-muted">
            {o("sb.once")}
          </p>
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
              <span>
                {o("f.batch_plan")}
                <span className="text-danger"> *</span>
              </span>
              <select
                value={draft.bp_no}
                onChange={(ev) =>
                  setDraft({ ...draft, bp_no: ev.target.value })
                }
                aria-label={o("f.batch_plan")}
                className="h-10 rounded-lg border border-border-strong bg-surface px-3 text-sm"
              >
                <option value="">—</option>
                {data.batch_plans.map((p) => (
                  <option key={p.bp_no} value={p.bp_no}>
                    {[p.bp_no, p.buyer, p.style_job, `${qty(p.bp_qty)} kg`]
                      .filter(Boolean)
                      .join(" · ")}
                  </option>
                ))}
              </select>
              {data.batch_plans.length === 0 ? (
                <span className="text-xs font-normal text-foreground-muted">
                  {o("bp.none_yet")}
                </span>
              ) : null}
            </label>
            <label className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground">
              {o("f.machine")}
              <select
                value={draft.dye_machine_id}
                onChange={(ev) =>
                  setDraft({ ...draft, dye_machine_id: ev.target.value })
                }
                aria-label={o("f.machine")}
                className="h-10 rounded-lg border border-border-strong bg-surface px-3 text-sm"
              >
                <option value="">
                  {draft.bp_no && plans[draft.bp_no]?.dye_machine_id
                    ? machines[plans[draft.bp_no].dye_machine_id]
                    : "—"}
                </option>
                {options.machines.map((m) => (
                  <option key={m.value} value={m.value}>
                    {m.label}
                  </option>
                ))}
              </select>
            </label>
            {["planned_start", "planned_end"].map((k) => (
              <label
                key={k}
                className="flex flex-col gap-1.5 text-[13px] font-semibold text-foreground"
              >
                {o(k === "planned_start" ? "f.start" : "f.end")}
                <Input
                  type="date"
                  value={draft[k]}
                  onChange={(ev) =>
                    setDraft({ ...draft, [k]: ev.target.value })
                  }
                  aria-label={o(k === "planned_start" ? "f.start" : "f.end")}
                />
              </label>
            ))}
          </div>
        </Modal>
      ) : null}
    </>
  );
}

export {
  StatusAreasEditor,
  StatusRulesEditor,
  SectionDaysEditor,
  ScheduleBoardEditor,
};
