"use client";

import { useMemo, useState, useTransition } from "react";
import { ArrowRight, Plus, RotateCcw, Save, Trash2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Select } from "@/components/ui/select";
import { useToastManager } from "@/components/ui/toast";

/**
 * Buyer-Wise Fabric Transaction Rights → "Permission for Grey Fabric
 * Transfer" (AnnotechERP 02/02), as Brickland lays it out: the buyer accounts
 * with how many rules each holds; pick one and its transfer rules — from one
 * fabric booking category to another, active or not — load beside it. Add
 * rule, Revert, Save. The absence of a rule is a refusal. Nothing reloads.
 *
 * @param {{ buyers: {value: string, label: string, key?: string}[], rules: object[], pairs: {value: string, label: string}[],
 *           save: Function, labels: object }} props
 */
function GreyTransferRules({ buyers, rules, pairs, save, labels: l }) {
  const toastManager = useToastManager();
  const [all, setAll] = useState(rules);
  const [buyer, setBuyer] = useState(null);
  const [list, setList] = useState([]);
  const [form, setForm] = useState(null);
  const [pending, startTransition] = useTransition();

  // The booking categories, read off the pairs: "FP01>FP02" → "Sample Booking (FP01) → …".
  const categories = useMemo(() => {
    const out = new Map();
    for (const p of pairs) {
      const [from] = p.value.split(">");
      if (!out.has(from)) out.set(from, p.label.split(" → ")[0]);
    }
    return [...out.entries()].map(([value, label]) => ({ value, label }));
  }, [pairs]);
  const nameOf = (code) =>
    categories.find((c) => c.value === code)?.label ?? code;
  const counts = all.reduce(
    (acc, r) => ({ ...acc, [r.subject_id]: (acc[r.subject_id] ?? 0) + 1 }),
    {},
  );
  const of = (id) =>
    all
      .filter((r) => r.subject_id === id)
      .map((r) => ({ target_id: r.target_id, active: Boolean(r.flag_active) }));

  const pick = (id) => {
    setBuyer(id);
    setList(of(id));
    setForm(null);
  };
  const add = () => {
    if (!form.from || !form.to) return;
    if (form.from === form.to)
      return toastManager.add({ title: l.same, type: "danger" });
    const target = `${form.from}>${form.to}`;
    if (list.some((r) => r.target_id === target))
      return toastManager.add({ title: l.exists, type: "danger" });
    setList((cur) => [...cur, { target_id: target, active: form.active }]);
    setForm(null);
  };
  const submit = () =>
    startTransition(async () => {
      const result = await save(
        buyer,
        list.map((r) => ({
          target_id: r.target_id,
          selected: true,
          flags: { active: r.active },
        })),
      );
      if (result?.status !== "success") {
        toastManager.add({
          title: Object.values(result?.errors ?? {})[0]?.[0] ?? result?.message,
          type: "danger",
        });
        return;
      }
      setAll((cur) => [
        ...cur.filter((r) => r.subject_id !== buyer),
        ...list.map((r) => ({
          subject_id: buyer,
          target_id: r.target_id,
          flag_active: r.active,
        })),
      ]);
      toastManager.add({ title: l.saved, type: "success" });
    });
  const buyerName = buyers.find((b) => b.value === buyer)?.label.split(" (")[0];

  return (
    <div className="grid gap-4 lg:grid-cols-[18rem_1fr]">
      <section className="rounded-lg border border-border bg-surface">
        <h2 className="flex items-center justify-between border-b border-border px-4 py-3 text-sm font-semibold text-foreground">
          {l.accounts}
          <span className="rounded-full bg-surface-muted px-2 py-0.5 text-xs text-foreground-muted">
            {buyers.length}
          </span>
        </h2>
        <ul className="max-h-[32rem] overflow-y-auto p-2">
          {buyers.map((b) => {
            const [name, code] = [
              b.label.replace(/ \([^)]*\)$/, ""),
              b.key ?? "",
            ];
            return (
              <li key={b.value}>
                <button
                  type="button"
                  onClick={() => pick(b.value)}
                  className={cn(
                    "flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm hover:bg-surface-muted",
                    buyer === b.value && "bg-brand-subtle text-brand",
                  )}
                >
                  <span>
                    <span className="block font-semibold">{name}</span>
                    <span className="block text-xs text-foreground-muted">
                      {code}
                    </span>
                  </span>
                  <span className="text-xs tabular-nums text-foreground-muted">
                    {counts[b.value] ?? 0}
                  </span>
                </button>
              </li>
            );
          })}
        </ul>
      </section>

      <section className="rounded-lg border border-border bg-surface">
        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4">
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {buyer ? l.rules_for.replace(":buyer", buyerName) : l.rules}
            </h2>
            <p className="text-[13px] text-foreground-muted">{l.hint}</p>
          </div>
          {buyer ? (
            <Button
              size="sm"
              onClick={() => setForm({ from: "", to: "", active: true })}
            >
              <Plus className="size-4" aria-hidden /> {l.add}
            </Button>
          ) : null}
        </div>

        {!buyer ? (
          <div className="px-5 py-14 text-center">
            <p className="text-sm font-semibold text-foreground">{l.pick}</p>
            <p className="text-[13px] text-foreground-muted">{l.pick_hint}</p>
          </div>
        ) : (
          <div className="flex flex-col gap-4 p-5">
            {form ? (
              <div className="rounded-lg border border-brand/30 bg-brand-subtle/40 p-4">
                <p className="text-sm font-semibold text-foreground">
                  {l.add_for.replace(":buyer", buyerName)}
                </p>
                <p className="mb-3 text-[13px] text-foreground-muted">
                  {l.refusal}
                </p>
                <div className="grid items-end gap-3 sm:grid-cols-[1fr_1fr_auto_auto]">
                  {["from", "to"].map((k) => (
                    <label key={k} className="flex flex-col gap-1.5">
                      <span className="text-[13px] font-semibold text-foreground">
                        {l[k]}
                        <span className="text-danger"> *</span>
                      </span>
                      <Select
                        value={form[k]}
                        onValueChange={(x) =>
                          setForm((f) => ({ ...f, [k]: x ?? "" }))
                        }
                        options={categories}
                        aria-label={l[k]}
                      />
                    </label>
                  ))}
                  <label className="flex h-10 items-center gap-2 text-sm text-foreground">
                    <Checkbox
                      checked={form.active}
                      onCheckedChange={(c) =>
                        setForm((f) => ({ ...f, active: c === true }))
                      }
                      aria-label={l.active}
                    />
                    {l.active}
                  </label>
                  <Button onClick={add} disabled={!form.from || !form.to}>
                    <Plus className="size-4" aria-hidden /> {l.add}
                  </Button>
                </div>
              </div>
            ) : null}

            {list.length === 0 ? (
              <div className="py-8 text-center">
                <p className="text-sm font-semibold text-foreground">
                  {l.none.replace(":buyer", buyerName)}
                </p>
                <p className="text-[13px] text-foreground-muted">
                  {l.none_hint}
                </p>
              </div>
            ) : (
              <table className="w-full text-sm">
                <thead className="bg-brand text-left text-brand-foreground">
                  <tr>
                    <th className="px-3 py-2.5 text-xs font-semibold uppercase">
                      {l.from}
                    </th>
                    <th className="w-8" aria-hidden />
                    <th className="px-3 py-2.5 text-xs font-semibold uppercase">
                      {l.to}
                    </th>
                    <th className="px-3 py-2.5 text-center text-xs font-semibold uppercase">
                      {l.active}
                    </th>
                    <th className="w-12" aria-hidden />
                  </tr>
                </thead>
                <tbody>
                  {list.map((r, i) => {
                    const [from, to] = r.target_id.split(">");
                    return (
                      <tr key={r.target_id} className="border-t border-border">
                        <td className="px-3 py-2.5">{nameOf(from)}</td>
                        <td className="text-foreground-muted">
                          <ArrowRight className="size-4" aria-hidden />
                        </td>
                        <td className="px-3 py-2.5">{nameOf(to)}</td>
                        <td className="px-3 py-2.5 text-center">
                          <Checkbox
                            checked={r.active}
                            onCheckedChange={(c) =>
                              setList((cur) =>
                                cur.map((x, j) =>
                                  j === i ? { ...x, active: c === true } : x,
                                ),
                              )
                            }
                            aria-label={`${nameOf(from)} → ${nameOf(to)} — ${l.active}`}
                          />
                        </td>
                        <td className="px-2 text-right">
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                              setList((cur) => cur.filter((_, j) => j !== i))
                            }
                            aria-label={l.remove}
                          >
                            <Trash2
                              className="size-4 text-danger"
                              aria-hidden
                            />
                          </Button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            )}
            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3.5">
              <p className="max-w-2xl text-[13px] text-foreground-muted">
                {l.count.replace(":count", list.length)} {l.refusal}
              </p>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  onClick={() => setList(of(buyer))}
                  disabled={pending}
                >
                  <RotateCcw className="size-4" aria-hidden /> {l.revert}
                </Button>
                <Button onClick={submit} loading={pending}>
                  <Save className="size-4" aria-hidden /> {l.save}
                </Button>
              </div>
            </div>
          </div>
        )}
      </section>
    </div>
  );
}

export { GreyTransferRules };
