"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ListChecks, RotateCcw, Save } from "lucide-react";
import { Select } from "@/components/ui/select";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { useToastManager } from "@/components/ui/toast";
import { useT } from "@/lib/i18n";

/**
 * Brickland's "User & X Mapping" tab (AnnotechERP 02/02): choose a user, a
 * menu, a buyer or a store, and every target appears with what they hold
 * today already ticked; change the ticks, limits, points, codes or order and
 * save the lot.
 *
 * A buyer or a store is addressed in the URL by its code (`subjects[].key`),
 * never its internal key. With `select`, a row is there because it is ticked
 * and its flags are attributes of it (a defect's Critical, a category's
 * Active), taking `flagDefaults` when first ticked.
 *
 * @param {{
 *   basePath: string, subjects: {value: string, label: string, key?: string}[], subjectId?: string, rows?: object[] | null,
 *   flags: {key: string, label: string}[], extras: string[], save: Function, select?: boolean, flagDefaults?: object,
 *   amountRange?: number[] | null,
 *   labels: { title: string, hint: string, subject: string, choose: string, target: string, selected: string,
 *             amount?: string, order?: string, defaultLabel?: string, text?: string, from?: string, to?: string,
 *             empty: string, emptyHint: string, saved: string },
 * }} props
 */
function SubjectMatrix({
  basePath,
  subjects,
  subjectId,
  rows,
  flags,
  extras,
  save,
  labels,
  select = false,
  flagDefaults = {},
  amountRange = null,
}) {
  const te = useT("erp");
  const router = useRouter();
  const toastManager = useToastManager();
  const [values, setValues] = useState(rows ?? []);
  const [pending, startTransition] = useTransition();
  const ticks = flags.length === 0 || select;
  const input =
    "h-9 rounded-md border border-border-strong bg-surface px-2 text-sm";

  function update(index, patch) {
    setValues((current) =>
      current.map((row, i) => (i === index ? { ...row, ...patch } : row)),
    );
  }

  function tick(index, checked) {
    setValues((current) =>
      current.map((row, i) => {
        if (i !== index) return row;
        if (!checked) return { ...row, selected: false, is_default: false };
        // A row ticked for the first time takes the kind's defaults (Active on).
        return {
          ...row,
          selected: true,
          flags: row.selected ? row.flags : { ...row.flags, ...flagDefaults },
        };
      }),
    );
  }

  function setFlag(index, flag, checked) {
    setValues((current) =>
      current.map((row, i) =>
        i === index
          ? { ...row, flags: { ...row.flags, [flag]: checked } }
          : row,
      ),
    );
  }

  function setDefault(index, checked) {
    setValues((current) =>
      current.map((row, i) => ({
        ...row,
        is_default: i === index ? checked : checked ? false : row.is_default,
        ...(i === index && checked ? { selected: true } : {}),
      })),
    );
  }

  function submit() {
    startTransition(async () => {
      const result = await save(subjectId, values);
      if (result?.status === "success") {
        toastManager.add({ title: labels.saved, type: "success" });
        router.refresh();
      } else if (result?.status === "error") {
        const first = Object.values(result.errors ?? {})[0]?.[0];
        toastManager.add({ title: first ?? result.message, type: "danger" });
      }
    });
  }

  const keyOf = (id) => subjects.find((s) => s.value === id)?.key ?? id;
  const selectedCount = values.filter((row) => row.selected).length;

  return (
    <div className="rounded-lg border border-border bg-surface">
      <div className="p-5">
        <div className="flex items-start gap-3">
          <ListChecks
            className="mt-0.5 size-5 text-foreground-muted"
            aria-hidden
          />
          <div>
            <h2 className="text-base font-semibold text-foreground">
              {labels.title}
            </h2>
            <p className="text-[13px] text-foreground-muted">{labels.hint}</p>
          </div>
        </div>
        <div className="mt-4 flex flex-col gap-1.5">
          <span className="text-[13px] font-semibold text-foreground">
            {labels.subject}
            <span className="ml-0.5 text-danger">*</span>
          </span>
          <div className="w-full max-w-md">
            <Select
              value={subjectId ?? ""}
              onValueChange={(id) =>
                router.push(
                  `${basePath}?tab=matrix&subject=${encodeURIComponent(keyOf(id))}`,
                )
              }
              placeholder={labels.choose}
              options={subjects}
              aria-label={labels.subject}
            />
          </div>
        </div>
      </div>

      {!subjectId || rows === null ? (
        <div className="flex flex-col items-center gap-2 border-t border-border px-5 py-14 text-center">
          <span className="flex size-12 items-center justify-center rounded-lg bg-surface-muted text-foreground-muted">
            <ListChecks className="size-5" />
          </span>
          <p className="text-sm font-semibold text-foreground">
            {labels.empty}
          </p>
          <p className="max-w-md text-[13px] text-foreground-muted">
            {labels.emptyHint}
          </p>
        </div>
      ) : (
        <>
          <div className="overflow-x-auto border-t border-border">
            <table className="w-full min-w-max text-sm">
              <thead>
                <tr className="bg-brand text-left text-brand-foreground">
                  {ticks ? (
                    <th className="w-24 px-4 py-3 text-center text-[13px] font-semibold">
                      {labels.selected}
                    </th>
                  ) : null}
                  <th className="px-4 py-3 text-[13px] font-semibold">
                    {labels.target}
                  </th>
                  {extras.includes("amount") ? (
                    <th className="px-4 py-3 text-[13px] font-semibold">
                      {labels.amount}
                    </th>
                  ) : null}
                  {flags.map((flag) => (
                    <th
                      key={flag.key}
                      className="px-4 py-3 text-center text-[13px] font-semibold"
                    >
                      {flag.label}
                    </th>
                  ))}
                  {extras.includes("text") ? (
                    <th className="px-4 py-3 text-[13px] font-semibold">
                      {labels.text}
                    </th>
                  ) : null}
                  {extras.includes("date_from") ? (
                    <th className="px-4 py-3 text-[13px] font-semibold">
                      {labels.from}
                    </th>
                  ) : null}
                  {extras.includes("date_to") ? (
                    <th className="px-4 py-3 text-[13px] font-semibold">
                      {labels.to}
                    </th>
                  ) : null}
                  {extras.includes("sort_order") ? (
                    <th className="px-4 py-3 text-[13px] font-semibold">
                      {labels.order}
                    </th>
                  ) : null}
                  {extras.includes("is_default") ? (
                    <th className="px-4 py-3 text-center text-[13px] font-semibold">
                      {labels.defaultLabel}
                    </th>
                  ) : null}
                </tr>
              </thead>
              <tbody>
                {values.length === 0 ? (
                  <tr>
                    <td
                      colSpan={12}
                      className="px-4 py-10 text-center text-foreground-muted"
                    >
                      {te("no_records")}
                    </td>
                  </tr>
                ) : null}
                {values.map((row, index) => {
                  // In a ticked list, a row's own fields wait for its tick.
                  const off = select && !row.selected;
                  return (
                    <tr
                      key={row.target_id}
                      className="border-b border-border last:border-0"
                    >
                      {ticks ? (
                        <td className="px-4 py-2.5 text-center">
                          <Checkbox
                            checked={Boolean(row.selected)}
                            onCheckedChange={(c) => tick(index, c === true)}
                            aria-label={`${row.target} — ${labels.selected}`}
                          />
                        </td>
                      ) : null}
                      <td className="px-4 py-2.5">{row.target}</td>
                      {extras.includes("amount") ? (
                        <td className="px-4 py-2">
                          <input
                            type="number"
                            min={amountRange?.[0] ?? 0}
                            max={amountRange?.[1]}
                            disabled={off}
                            value={row.amount ?? ""}
                            onChange={(e) =>
                              update(index, {
                                amount:
                                  e.target.value === "" ? null : e.target.value,
                              })
                            }
                            className={`${input} w-28 disabled:opacity-50`}
                            aria-label={`${row.target} — ${labels.amount}`}
                          />
                        </td>
                      ) : null}
                      {flags.map((flag) => (
                        <td key={flag.key} className="px-4 py-2.5 text-center">
                          <Checkbox
                            disabled={off}
                            checked={Boolean(row.flags?.[flag.key])}
                            onCheckedChange={(c) =>
                              setFlag(index, flag.key, c === true)
                            }
                            aria-label={`${row.target} — ${flag.label}`}
                          />
                        </td>
                      ))}
                      {extras.includes("text") ? (
                        <td className="px-4 py-2">
                          <input
                            disabled={off}
                            value={row.text ?? ""}
                            onChange={(e) =>
                              update(index, { text: e.target.value })
                            }
                            className={`${input} w-48 disabled:opacity-50`}
                            aria-label={`${row.target} — ${labels.text}`}
                          />
                        </td>
                      ) : null}
                      {["date_from", "date_to"].map((key) =>
                        extras.includes(key) ? (
                          <td key={key} className="px-4 py-2">
                            <input
                              type="date"
                              value={row[key] ?? ""}
                              onChange={(e) =>
                                update(index, { [key]: e.target.value || null })
                              }
                              className={`${input} w-40`}
                              aria-label={`${row.target} — ${key === "date_from" ? labels.from : labels.to}`}
                            />
                          </td>
                        ) : null,
                      )}
                      {extras.includes("sort_order") ? (
                        <td className="px-4 py-2">
                          <input
                            type="number"
                            min="0"
                            disabled={off}
                            value={row.sort_order ?? 0}
                            onChange={(e) =>
                              update(index, { sort_order: e.target.value })
                            }
                            className={`${input} w-24 disabled:opacity-50`}
                            aria-label={`${row.target} — ${labels.order}`}
                          />
                        </td>
                      ) : null}
                      {extras.includes("is_default") ? (
                        <td className="px-4 py-2.5 text-center">
                          <Checkbox
                            disabled={ticks && !row.selected}
                            checked={Boolean(row.is_default)}
                            onCheckedChange={(c) =>
                              setDefault(index, c === true)
                            }
                            aria-label={`${row.target} — ${labels.defaultLabel}`}
                          />
                        </td>
                      ) : null}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <div className="flex items-center justify-between gap-2 border-t border-border px-5 py-3.5">
            <span className="text-[13px] text-foreground-muted">
              {ticks ? `${selectedCount} / ${values.length}` : null}
            </span>
            <div className="flex gap-2">
              <Button
                variant="outline"
                onClick={() => setValues(rows)}
                disabled={pending}
              >
                <RotateCcw /> {te("reset")}
              </Button>
              <Button onClick={submit} loading={pending}>
                <Save /> {te("save")}
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}

export { SubjectMatrix };
