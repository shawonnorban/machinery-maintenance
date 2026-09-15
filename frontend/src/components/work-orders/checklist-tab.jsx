"use client";

import { useActionState, useEffect, useRef } from "react";
import { useFormStatus } from "react-dom";
import Link from "next/link";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { FileInput } from "@/components/ui/file-input";
import { Select } from "@/components/ui/select";
import { useToastManager } from "@/components/ui/toast";

const RESULT_TONE = { PASS: "success", FAIL: "danger", NA: "neutral" };

/**
 * Mirrors `work_order::work-orders._checklist.blade.php` exactly, including
 * its own scope: only NUMERIC/CHOICE/TEXT get a dedicated input, the rest
 * (PASS_FAIL, PHOTO, SIGNATURE) get the bare result selector — the web
 * itself has no special handling for those either. Answers post one item
 * at a time rather than as one big form: a connection dropping mid-
 * checklist should not cost fourteen already-recorded answers because the
 * fifteenth failed.
 */
function ChecklistTab({ progress, items, canExecute, action }) {
  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-2 text-sm">
        <span className="text-foreground-muted">
          {progress.answered} of {progress.total} answered
        </span>
        {progress.required_remaining > 0 ? (
          <Badge variant="warning">{progress.required_remaining} required remaining</Badge>
        ) : null}
        {progress.failed > 0 ? <Badge variant="danger">{progress.failed} failed</Badge> : null}
      </div>

      <div className="overflow-x-auto rounded-sm border border-border">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border text-left text-xs text-foreground-muted">
              <th className="px-4 py-3">#</th>
              <th className="px-4 py-3">Item</th>
              <th className="px-4 py-3" style={{ minWidth: "18rem" }}>
                Answer
              </th>
              <th className="px-4 py-3">Status</th>
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <ChecklistRow key={item.id} item={item} canExecute={canExecute} action={action} />
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function SubmitButton() {
  const { pending } = useFormStatus();
  return (
    <button
      type="submit"
      disabled={pending}
      className="inline-flex h-8 items-center rounded-sm bg-brand px-3 text-xs font-medium text-brand-foreground hover:bg-brand-hover disabled:opacity-50"
    >
      {pending ? "…" : "Record"}
    </button>
  );
}

function ChecklistRow({ item, canExecute, action }) {
  const result = item.result;
  const [state, dispatch] = useActionState(action, null);
  const formRef = useRef(null);
  const toastManager = useToastManager();

  useEffect(() => {
    if (state?.status === "success") {
      toastManager.add({ title: `${item.label} recorded`, type: "success" });
    } else if (state?.status === "error") {
      toastManager.add({ title: state.message, type: "danger" });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  return (
    <tr className={`border-t border-border ${result?.result === "FAIL" ? "bg-danger-subtle" : ""}`}>
      <td className="px-4 py-3 align-top">{item.sequence}</td>

      <td className="px-4 py-3 align-top">
        <div className="font-medium text-foreground">
          {item.label}
          {item.required ? <span className="ml-0.5 text-danger">*</span> : null}
        </div>
        {item.help_text ? <div className="text-xs text-foreground-muted">{item.help_text}</div> : null}
        {item.is_safety_item ? (
          <Badge variant="danger" className="mt-1">
            Safety
          </Badge>
        ) : null}
        {item.tolerance_min !== undefined && (item.tolerance_min !== null || item.tolerance_max !== null) ? (
          <div className="mt-1 text-xs text-foreground-muted">
            Tolerance: {item.tolerance_min ?? "−∞"} to {item.tolerance_max ?? "∞"} {item.unit}
          </div>
        ) : null}
      </td>

      <td className="px-4 py-3 align-top">
        {canExecute ? (
          <form ref={formRef} action={dispatch} className="flex flex-col gap-1.5">
            <input type="hidden" name="checklist_item_id" value={item.id} />

            <div className="flex flex-wrap items-center gap-1.5">
              {item.input_type === "NUMERIC" ? (
                <div className="flex items-center gap-1">
                  <Input name="numeric_value" type="number" step="0.0001" defaultValue={result?.numeric_value ?? ""} className="w-28" />
                  {item.unit ? <span className="text-xs text-foreground-muted">{item.unit}</span> : null}
                </div>
              ) : item.input_type === "CHOICE" ? (
                <select
                  name="text_value"
                  defaultValue={result?.text_value ?? ""}
                  className="h-9 w-40 rounded-sm border border-border-strong bg-surface px-2 text-sm"
                >
                  <option value="">—</option>
                  {(item.options ?? []).map((option) => (
                    <option key={option} value={option}>
                      {option}
                    </option>
                  ))}
                </select>
              ) : item.input_type === "TEXT" ? (
                <Input name="text_value" type="text" defaultValue={result?.text_value ?? ""} maxLength={2000} className="w-48" />
              ) : null}

              <select
                name="result"
                defaultValue={result?.result ?? ""}
                required
                className="h-9 w-28 rounded-sm border border-border-strong bg-surface px-2 text-sm"
              >
                <option value="" disabled>
                  Result
                </option>
                <option value="PASS">Pass</option>
                <option value="FAIL">Fail</option>
                {!item.required ? <option value="NA">N/A</option> : null}
              </select>

              <SubmitButton />
            </div>

            {item.requires_note_on_fail || item.requires_attachment_on_fail ? (
              <div className="flex flex-wrap gap-1.5">
                {item.requires_note_on_fail ? (
                  <Input
                    name="observation"
                    type="text"
                    defaultValue={result?.observation ?? ""}
                    maxLength={2000}
                    placeholder="Observation (if fail)"
                    className="w-56"
                  />
                ) : null}
                {item.requires_attachment_on_fail ? (
                  <FileInput name="photo" accept="image/*,application/pdf" className="w-56 text-xs" />
                ) : null}
              </div>
            ) : null}
          </form>
        ) : result === null || result === undefined ? (
          <span className="text-foreground-muted">—</span>
        ) : (
          <div>
            {result.numeric_value !== null ? (
              <span className="font-medium text-foreground">
                {result.numeric_value} {item.unit}
              </span>
            ) : result.text_value ? (
              result.text_value
            ) : null}
            {result.observation ? <div className="text-xs text-foreground-muted">{result.observation}</div> : null}
          </div>
        )}
      </td>

      <td className="px-4 py-3 align-top">
        {result ? (
          <div className="flex flex-col gap-1">
            <Badge variant={RESULT_TONE[result.result] ?? "neutral"}>{result.result}</Badge>
            {result.is_within_tolerance === false ? <span className="text-xs text-danger">Out of tolerance</span> : null}
            {result.followup_work_order_id ? (
              <Link href={`/work-orders/${result.followup_work_order_id}`} className="text-xs text-brand hover:underline">
                Follow-up raised
              </Link>
            ) : null}
          </div>
        ) : (
          <span className="text-foreground-muted">—</span>
        )}
      </td>
    </tr>
  );
}

export { ChecklistTab };
