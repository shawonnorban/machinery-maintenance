"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { StatusBadge } from "@/components/ui/status-badge";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import { useToastManager } from "@/components/ui/toast";
import { InspectionPhotos } from "@/components/item-master/inspection-photos";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/i18n";

/**
 * Inspecting one roll (Inventory spec §4.2–§4.3). Grade is a single field
 * with four values (A/B/C/Reject); choosing Reject additionally asks for a
 * disposition, which becomes the roll's status — no separate accept/reject
 * screens. The defect checklist is filtered to the roll's own family
 * (Knit or Woven), never showing the other list's entries.
 */
function InspectRollForm({ roll, defects, action, canUploadPhoto, uploadPhoto }) {
  const t = useT("inspection");
  const tc = useT("common");
  const router = useRouter();
  const toastManager = useToastManager();
  const [pending, startTransition] = useTransition();

  const [actualWidth, setActualWidth] = useState(roll.actual_width ?? "");
  const [shadeMatch, setShadeMatch] = useState(true);
  const [grade, setGrade] = useState("");
  const [disposition, setDisposition] = useState("");
  const [defectIds, setDefectIds] = useState([]);
  const [notes, setNotes] = useState("");
  const [errors, setErrors] = useState(null);

  const familyDefects = defects[roll.defect_family] ?? [];

  function toggleDefect(id) {
    setDefectIds((prev) => (prev.includes(id) ? prev.filter((d) => d !== id) : [...prev, id]));
  }

  function submit(event) {
    event.preventDefault();
    startTransition(async () => {
      const result = await action(roll.id, {
        actual_width: actualWidth || undefined,
        shade_match: shadeMatch,
        grade,
        disposition: grade === "REJECT" ? disposition : undefined,
        defect_ids: defectIds,
        notes: notes || undefined,
      });

      if (result?.status === "success") {
        toastManager.add({ title: t("roll_inspected", { roll_no: roll.roll_no }), type: "success" });
        router.push("/item-master/inspection");
      } else if (result?.status === "error") {
        setErrors(result.errors ?? null);
        toastManager.add({ title: result.message, type: "danger" });
      }
    });
  }

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
      <div className="flex flex-col gap-4 lg:col-span-6">
        <Card>
          <CardHeader>
            <CardTitle>{roll.roll_no}</CardTitle>
          </CardHeader>
          <CardBody className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <Field label={t("item")}>{roll.item ? `${roll.item.name} (${roll.item.item_code})` : "—"}</Field>
            <Field label={t("status")}><StatusBadge status={roll.status} label={t(`statuses.${roll.status}`)} /></Field>
            <Field label={t("shade")}>{roll.shade ?? "—"}</Field>
            <Field label={t("shade_band")}>{roll.shade_band ?? "—"}</Field>
            <Field label={t("lot_no")}>{roll.lot_no ?? "—"}</Field>
            <Field label={t("nominal_width")}>{roll.nominal_width ? formatNumber(roll.nominal_width, { maximumFractionDigits: 2 }) : "—"}</Field>
            <Field label={t("gross_length")}>{roll.gross_length ? formatNumber(roll.gross_length, { maximumFractionDigits: 2 }) : "—"}</Field>
            <Field label={t("net_length")}>{roll.net_length ? formatNumber(roll.net_length, { maximumFractionDigits: 2 }) : "—"}</Field>
            <Field label={t("gross_weight")}>{roll.gross_weight ? formatNumber(roll.gross_weight, { maximumFractionDigits: 2 }) : "—"}</Field>
            <Field label={t("net_weight")}>{roll.net_weight ? formatNumber(roll.net_weight, { maximumFractionDigits: 2 }) : "—"}</Field>
            <Field label={t("unit_cost")}>{formatNumber(roll.unit_cost, { maximumFractionDigits: 2 })} {roll.currency}</Field>
          </CardBody>
        </Card>

        {/* Lives beside the roll's own facts, not inside the grading form:
            a shade dispute is often settled by a picture taken days after
            the grade was recorded, so this stays reachable either way. */}
        <Card>
          <CardHeader>
            <CardTitle>{t("photos")}</CardTitle>
          </CardHeader>
          <CardBody>
            <InspectionPhotos photos={roll.photos ?? []} canUpload={canUploadPhoto} action={uploadPhoto} />
          </CardBody>
        </Card>
      </div>

      <div className="flex flex-col gap-4 lg:col-span-6">
        {roll.can_inspect ? (
          <form onSubmit={submit}>
            <Card>
              <CardHeader>
                <CardTitle>{t("inspection_data")}</CardTitle>
              </CardHeader>
              <CardBody className="flex flex-col gap-4">
                <div className="grid grid-cols-2 gap-4">
                  <FormField label={t("actual_width")} error={errors?.actual_width?.[0]}>
                    {(fp) => <Input {...fp} type="number" step="0.01" min="0" value={actualWidth} onChange={(e) => setActualWidth(e.target.value)} />}
                  </FormField>
                  <FormField label={t("grade")} required error={errors?.grade?.[0]}>
                    {(fp) => (
                      <Select {...fp} value={grade} onValueChange={setGrade} options={["A", "B", "C", "REJECT"].map((g) => ({ value: g, label: t(`grades.${g}`) }))} placeholder={t("grade")} />
                    )}
                  </FormField>
                </div>

                <label className="flex items-center gap-2 text-sm text-foreground">
                  <Checkbox checked={shadeMatch} onCheckedChange={setShadeMatch} />
                  {t("shade_match")}
                </label>

                {grade === "REJECT" ? (
                  <FormField label={t("disposition")} required error={errors?.disposition?.[0]}>
                    {(fp) => (
                      <Select {...fp} value={disposition} onValueChange={setDisposition} options={["DAMAGED", "RETURNED"].map((d) => ({ value: d, label: t(`dispositions.${d}`) }))} placeholder={t("disposition")} />
                    )}
                  </FormField>
                ) : null}

                <div className="flex flex-col gap-2">
                  <span className="text-sm font-medium text-foreground">{t("defects")} — {t(`defect_family_${roll.defect_family?.toLowerCase()}`)}</span>
                  {errors?.defect_ids?.[0] ? <p className="text-xs text-danger">{errors.defect_ids[0]}</p> : null}
                  <div className="flex flex-wrap gap-x-4 gap-y-2">
                    {familyDefects.map((defect) => (
                      <label key={defect.id} className="flex items-center gap-2 text-sm text-foreground">
                        <Checkbox checked={defectIds.includes(defect.id)} onCheckedChange={() => toggleDefect(defect.id)} />
                        {defect.name}
                      </label>
                    ))}
                  </div>
                </div>

                <FormField label={t("inspection_notes")}>
                  {(fp) => <Textarea {...fp} value={notes} onChange={(e) => setNotes(e.target.value)} rows={3} maxLength={2000} />}
                </FormField>

                <div className="flex justify-end gap-2">
                  <Button type="button" variant="outline" onClick={() => router.back()}>
                    {tc("cancel")}
                  </Button>
                  <Button type="submit" loading={pending} disabled={!grade}>
                    {t("submit_inspection")}
                  </Button>
                </div>
              </CardBody>
            </Card>
          </form>
        ) : (
          <Alert variant="info">
            {t("roll_not_pending", { status: t(`statuses.${roll.status}`) })}
          </Alert>
        )}
      </div>
    </div>
  );
}

function Field({ label, children }) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs text-foreground-muted">{label}</span>
      <span>{children}</span>
    </div>
  );
}

export { InspectRollForm };
