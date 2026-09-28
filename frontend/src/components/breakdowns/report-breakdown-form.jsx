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
import { useT } from "@/lib/i18n";

// Neither failure codes nor reason codes are exhaustive — a machine breaks
// in a way the catalog hasn't seen yet often enough that forcing the
// closest wrong match was worse than an honest free-text note. Not a real
// record's id, so it can never collide with one.
const OTHER = "OTHER";

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
  const t = useT("breakdown");
  const tc = useT("common");
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
  const [failureCodeOther, setFailureCodeOther] = useState("");
  const [downtimeReasonCodeId, setDowntimeReasonCodeId] = useState("");
  const [downtimeReasonOther, setDowntimeReasonOther] = useState("");
  const [photo, setPhoto] = useState(null); // { dataUrl, filename } | null
  const [photoProcessing, setPhotoProcessing] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  const PRIORITY_OPTIONS = [
    { value: "", label: t("priority_default_hint") },
    { value: "CRITICAL", label: t("priority_critical") },
    { value: "HIGH", label: t("priority_high") },
    { value: "MEDIUM", label: t("priority_medium") },
    { value: "LOW", label: t("priority_low") },
  ];
  const SEVERITY_OPTIONS = [
    { value: "CATASTROPHIC", label: t("severity_catastrophic") },
    { value: "MAJOR", label: t("severity_major") },
    { value: "MINOR", label: t("severity_minor") },
    { value: "NEGLIGIBLE", label: t("severity_negligible") },
  ];

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
      setError(t("photo_error"));
    } finally {
      setPhotoProcessing(false);
    }
  }

  async function handleSubmit(event) {
    event.preventDefault();

    if (!assetId || !problemDescription.trim()) {
      setError(t("pick_machine_and_describe"));
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
          // "Other" is never a real catalog id — it stands in for the
          // free-text field below, so the id is left null either way.
          failure_code_id: failureCodeId && failureCodeId !== OTHER ? failureCodeId : null,
          failure_code_other: failureCodeId === OTHER ? failureCodeOther.trim() || null : null,
          downtime_reason_code_id: downtimeReasonCodeId && downtimeReasonCodeId !== OTHER ? downtimeReasonCodeId : null,
          downtime_reason_other: downtimeReasonCodeId === OTHER ? downtimeReasonOther.trim() || null : null,
          photo_base64: photo?.dataUrl ?? null,
          photo_filename: photo?.filename ?? null,
        },
        label: `Breakdown report — ${options.assets.find((a) => a.id === assetId)?.asset_code ?? assetId}`,
      });

      flush();

      toastManager.add({
        title: t("reported"),
        description: t("saved_and_sending"),
        type: "success",
      });
      router.push("/breakdowns");
    } catch {
      // saveDraft() itself failing means IndexedDB is unavailable
      // (private browsing with storage blocked, most likely) — the one
      // case this form genuinely can't route around.
      setError(t("could_not_save_offline"));
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      {error ? <Alert variant="danger">{error}</Alert> : null}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className="flex flex-col gap-5">
          <FormField label={t("asset")} required>
            {(fieldProps) => (
              <Select
                {...fieldProps}
                value={assetId}
                onValueChange={setAssetId}
                placeholder={t("select_machine")}
                options={options.assets.map((asset) => ({ value: asset.id, label: `${asset.asset_code} — ${asset.name}` }))}
              />
            )}
          </FormField>

          <FormField label={t("problem_description")} required helperText={t("problem_description_hint")}>
            {(fieldProps) => (
              <Textarea {...fieldProps} value={problemDescription} onChange={(e) => setProblemDescription(e.target.value)} rows={4} maxLength={5000} required />
            )}
          </FormField>

          <FormField
            label={t("photo")}
            helperText={photoProcessing ? t("photo_processing") : t("photo_hint")}
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
                      aria-label={t("remove_photo")}
                    >
                      <X /> {t("remove_photo")}
                    </Button>
                  </div>
                ) : null}
              </div>
            )}
          </FormField>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label={t("failure_at")} helperText={t("failure_at_hint")}>
              {(fieldProps) => <DateTimeField {...fieldProps} value={failureAt} onChange={setFailureAt} />}
            </FormField>
            <FormField label={t("reported_at")}>
              {(fieldProps) => <DateTimeField {...fieldProps} value={reportedAt} onChange={setReportedAt} />}
            </FormField>
          </div>
        </div>

        <div className="flex flex-col gap-5">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label={t("priority")}>
              {(fieldProps) => <Select {...fieldProps} value={priority} onValueChange={setPriority} options={PRIORITY_OPTIONS} />}
            </FormField>
            <FormField label={t("severity")}>
              {(fieldProps) => <Select {...fieldProps} value={severity} onValueChange={setSeverity} options={SEVERITY_OPTIONS} />}
            </FormField>
          </div>

          <FormField label={t("production_line")}>
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

          <FormField label={t("production_order_reference")}>
            {(fieldProps) => (
              <Input {...fieldProps} value={productionOrderReference} onChange={(e) => setProductionOrderReference(e.target.value)} maxLength={255} />
            )}
          </FormField>

          <FormField label={t("failure_code")} helperText={t("failure_code_report_hint")}>
            {(fieldProps) => (
              <div className="flex flex-col gap-2">
                <Select
                  {...fieldProps}
                  value={failureCodeId}
                  onValueChange={setFailureCodeId}
                  placeholder="—"
                  options={[
                    ...options.failure_codes.map((code) => ({ value: code.id, label: code.name })),
                    { value: OTHER, label: t("other_option") },
                  ]}
                />
                {failureCodeId === OTHER ? (
                  <Input
                    value={failureCodeOther}
                    onChange={(e) => setFailureCodeOther(e.target.value)}
                    placeholder={t("describe_failure_placeholder")}
                    maxLength={255}
                  />
                ) : null}
              </div>
            )}
          </FormField>

          <FormField label={t("reason_code")}>
            {(fieldProps) => (
              <div className="flex flex-col gap-2">
                <Select
                  {...fieldProps}
                  value={downtimeReasonCodeId}
                  onValueChange={setDowntimeReasonCodeId}
                  placeholder="—"
                  options={[
                    ...options.reason_codes.map((reason) => ({ value: reason.id, label: reason.name })),
                    { value: OTHER, label: t("other_option") },
                  ]}
                />
                {downtimeReasonCodeId === OTHER ? (
                  <Input
                    value={downtimeReasonOther}
                    onChange={(e) => setDowntimeReasonOther(e.target.value)}
                    placeholder={t("describe_reason_placeholder")}
                    maxLength={255}
                  />
                ) : null}
              </div>
            )}
          </FormField>
        </div>
      </div>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          {tc("cancel")}
        </Button>
        <Button type="submit" variant="danger" loading={submitting} disabled={photoProcessing}>
          {t("report_breakdown")}
        </Button>
      </div>
    </form>
  );
}

export { ReportBreakdownForm };
