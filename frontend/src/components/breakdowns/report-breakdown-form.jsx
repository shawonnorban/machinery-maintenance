"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { X } from "lucide-react";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { Input } from "@/components/ui/input";
import { FileInput } from "@/components/ui/file-input";
import { DateTimeField } from "@/components/ui/date-time-field";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { saveDraft, flush } from "@/lib/offline/queue";
import { resizeImage } from "@/lib/offline/resize-image";

const PRIORITY_OPTIONS = [
  { value: "", label: "— (uses the machine's own criticality)" },
  { value: "CRITICAL", label: "Critical" },
  { value: "HIGH", label: "High" },
  { value: "MEDIUM", label: "Medium" },
  { value: "LOW", label: "Low" },
];
const SEVERITY_OPTIONS = [
  { value: "CATASTROPHIC", label: "Catastrophic" },
  { value: "MAJOR", label: "Major" },
  { value: "MINOR", label: "Minor" },
  { value: "NEGLIGIBLE", label: "Negligible" },
];

/**
 * The one form in the product built to work with no signal (docs/12-
 * Stack-Migration-Implementation-Plan.md Phase E; mirrors the Blade
 * version's own framing exactly: "somebody is standing at a stopped
 * machine; demanding a diagnosis here gets either a delayed report or a
 * guessed code, and both are worse than an incomplete one" — asset and
 * problem description are the only two actually required). Everything
 * below that mirrors `breakdowns/create.blade.php`'s own "All" card
 * field-for-field — all optional at report time, all correctable later.
 *
 * Deliberately NOT a Server Action: `saveDraft()` writes to IndexedDB
 * synchronously and needs no network at all, which is the entire point.
 * A Server Action's very first hop is a round trip to the Next.js
 * server — no different from reaching Laravel directly as far as "is
 * there a signal right now" is concerned, so it can't be the boundary
 * that makes this form offline-capable. Submission returns as soon as
 * the draft is saved locally; sending it is `flush()`'s job, running in
 * the background and visible in `AppShell`'s sync indicator, not
 * something this form waits on.
 *
 * `initialAssetId` preselects the machine when this form is reached from
 * a QR scan (`ScanApiController`'s `report_breakdown` action carries the
 * scanned asset's id as `?asset_id=`) — the technician already scanned
 * the machine standing in front of them, so asking them to find it again
 * in a dropdown would be the one extra step the scan flow exists to
 * remove. Validated against `options.assets` rather than trusted blindly:
 * a stale link or a tampered query string just falls back to an empty,
 * ordinary pick.
 */
function ReportBreakdownForm({ options, initialAssetId = "" }) {
  const router = useRouter();
  const toastManager = useToastManager();
  const [assetId, setAssetId] = useState(
    () => (options.assets.some((asset) => asset.id === initialAssetId) ? initialAssetId : ""),
  );
  const [problemDescription, setProblemDescription] = useState("");
  const [failureAt, setFailureAt] = useState("");
  const [reportedAt, setReportedAt] = useState("");
  const [priority, setPriority] = useState("");
  const [severity, setSeverity] = useState("MAJOR");
  const [productionLineId, setProductionLineId] = useState("");
  const [productionOrderReference, setProductionOrderReference] = useState("");
  const [failureCodeId, setFailureCodeId] = useState("");
  const [downtimeReasonCodeId, setDowntimeReasonCodeId] = useState("");
  const [photo, setPhoto] = useState(null); // { dataUrl, filename } | null
  const [photoProcessing, setPhotoProcessing] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  /**
   * Resized (docs/12-Stack-Migration-Implementation-Plan.md Phase E —
   * factory wifi can't absorb raw camera output) and read as a base64 data
   * URL right away, before submit, not at send time — the same reasoning
   * as `saveDraft()` minting the idempotency key at save time: this form
   * has to finish its own work with no network at all, and `FileReader`
   * needs none. The offline queue only carries JSON, so the photo has to
   * already be a string by the time it goes into the draft.
   */
  async function handlePhotoChange(event) {
    const file = event.target.files?.[0];

    if (!file) {
      setPhoto(null);
      return;
    }

    setPhotoProcessing(true);
    try {
      const resized = await resizeImage(file);
      const dataUrl = await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(resized);
      });
      setPhoto({ dataUrl, filename: resized.name });
    } catch {
      setPhoto(null);
      setError("Could not read that photo — try a different one, or skip it.");
    } finally {
      setPhotoProcessing(false);
    }
  }

  async function handleSubmit(event) {
    event.preventDefault();

    if (!assetId || !problemDescription.trim()) {
      setError("Pick a machine and describe the problem before reporting.");
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      await saveDraft({
        endpoint: "/breakdowns",
        payload: {
          asset_id: assetId,
          problem_description: problemDescription.trim(),
          // Blank means now — a machine that stopped an hour before anyone
          // reported it is common, and that hour belongs to reporting, not
          // to maintenance response (mirrors the Blade form's own note).
          failure_at: failureAt ? new Date(failureAt).toISOString() : null,
          reported_at: reportedAt ? new Date(reportedAt).toISOString() : null,
          priority: priority || null,
          severity,
          production_line_id: productionLineId || null,
          production_order_reference: productionOrderReference || null,
          failure_code_id: failureCodeId || null,
          downtime_reason_code_id: downtimeReasonCodeId || null,
          photo_base64: photo?.dataUrl ?? null,
          photo_filename: photo?.filename ?? null,
        },
        label: `Breakdown report — ${options.assets.find((a) => a.id === assetId)?.asset_code ?? assetId}`,
      });

      flush();

      toastManager.add({
        title: "Breakdown reported",
        description: "Saved on this device and sending now — check the sync icon if you're offline.",
        type: "success",
      });
      router.push("/breakdowns");
    } catch {
      // saveDraft() itself failing means IndexedDB is unavailable
      // (private browsing with storage blocked, most likely) — the one
      // case this form genuinely can't route around.
      setError("Could not save this report on this device. Try again, or use a different browser mode.");
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {error ? <Alert variant="danger">{error}</Alert> : null}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className="flex flex-col gap-5">
          <FormField label="Machine" required>
            {(fieldProps) => (
              <Select
                {...fieldProps}
                value={assetId}
                onValueChange={setAssetId}
                placeholder="Select the machine"
                options={options.assets.map((asset) => ({ value: asset.id, label: `${asset.asset_code} — ${asset.name}` }))}
              />
            )}
          </FormField>

          <FormField label="What's wrong" required helperText="Describe what happened — a technician can fill in the diagnosis later.">
            {(fieldProps) => (
              <Textarea {...fieldProps} value={problemDescription} onChange={(e) => setProblemDescription(e.target.value)} rows={4} maxLength={5000} required />
            )}
          </FormField>

          <FormField
            label="Photo"
            helperText={photoProcessing ? "Processing photo…" : "Optional — a picture of the fault, if you have one. Saved with the report even offline."}
          >
            {(fieldProps) => (
              <div className="flex flex-col gap-2">
                <FileInput {...fieldProps} accept="image/*" capture="environment" onChange={handlePhotoChange} disabled={photoProcessing} />
                {photo ? (
                  <div className="flex items-center gap-2">
                    {/* eslint-disable-next-line @next/next/no-img-element -- a local data: URL preview, not a next/image-optimizable remote asset */}
                    <img src={photo.dataUrl} alt="" className="size-14 rounded-sm border border-border object-cover" />
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => setPhoto(null)}
                      aria-label="Remove photo"
                    >
                      <X /> Remove
                    </Button>
                  </div>
                ) : null}
              </div>
            )}
          </FormField>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label="Machine stopped" helperText="Blank means now.">
              {(fieldProps) => <DateTimeField {...fieldProps} value={failureAt} onChange={setFailureAt} />}
            </FormField>
            <FormField label="Reported">
              {(fieldProps) => <DateTimeField {...fieldProps} value={reportedAt} onChange={setReportedAt} />}
            </FormField>
          </div>
        </div>

        <div className="flex flex-col gap-5">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label="Priority">
              {(fieldProps) => <Select {...fieldProps} value={priority} onValueChange={setPriority} options={PRIORITY_OPTIONS} />}
            </FormField>
            <FormField label="Severity">
              {(fieldProps) => <Select {...fieldProps} value={severity} onValueChange={setSeverity} options={SEVERITY_OPTIONS} />}
            </FormField>
          </div>

          <FormField label="Production line">
            {(fieldProps) => (
              <Select
                {...fieldProps}
                value={productionLineId}
                onValueChange={setProductionLineId}
                placeholder="—"
                options={options.production_lines.map((line) => ({ value: line.id, label: line.name }))}
              />
            )}
          </FormField>

          <FormField label="Production order reference">
            {(fieldProps) => (
              <Input {...fieldProps} value={productionOrderReference} onChange={(e) => setProductionOrderReference(e.target.value)} maxLength={255} />
            )}
          </FormField>

          <FormField label="Failure code" helperText="Optional at report time — maintenance confirms or corrects it at closure.">
            {(fieldProps) => (
              <Select
                {...fieldProps}
                value={failureCodeId}
                onValueChange={setFailureCodeId}
                placeholder="—"
                options={options.failure_codes.map((code) => ({ value: code.id, label: code.name }))}
              />
            )}
          </FormField>

          <FormField label="Reason">
            {(fieldProps) => (
              <Select
                {...fieldProps}
                value={downtimeReasonCodeId}
                onValueChange={setDowntimeReasonCodeId}
                placeholder="—"
                options={options.reason_codes.map((reason) => ({ value: reason.id, label: reason.name }))}
              />
            )}
          </FormField>
        </div>
      </div>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          Cancel
        </Button>
        <Button type="submit" variant="danger" loading={submitting} disabled={photoProcessing}>
          Report breakdown
        </Button>
      </div>
    </form>
  );
}

export { ReportBreakdownForm };
