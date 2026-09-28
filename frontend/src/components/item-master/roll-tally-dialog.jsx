"use client";

import { useState } from "react";
import { Plus, Trash2 } from "lucide-react";
import { Modal } from "@/components/ui/modal";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { FormField } from "@/components/ui/form-field";
import { useT } from "@/lib/i18n";

const LIGHTS = ["D65", "TL83", "A"];
const ASPECTS = ["APPROVED", "END_TO_END", "SIDE_TO_CENTER"];

/**
 * Tallying one roll onto the sheet (Inventory spec §4.3).
 *
 * This is the screen that has to beat the paper, so it is laid out like
 * the paper: three widths, the ticket length against the measured one, the
 * shade grid, then the fault tally. The running score is shown as the
 * inspector types, because the one thing the form cannot do is tell them
 * where the roll stands before they walk away from it.
 */
function RollTallyDialog({ roll, options, pending, onClose, onSubmit }) {
  const t = useT("inspection");
  const tc = useT("common");

  const [widthStart, setWidthStart] = useState(roll.width_start ?? roll.nominal_width ?? "");
  const [widthMiddle, setWidthMiddle] = useState(roll.width_middle ?? "");
  const [widthEnd, setWidthEnd] = useState(roll.width_end ?? "");
  const [markedLength, setMarkedLength] = useState(roll.marked_length ?? roll.net_length ?? "");
  const [inspectedLength, setInspectedLength] = useState(roll.inspected_length ?? roll.net_length ?? "");
  const [shade, setShade] = useState(roll.shade_assessment ?? {});
  const [notes, setNotes] = useState("");
  const [lines, setLines] = useState(
    roll.defects.length > 0
      ? roll.defects.map((d) => ({ defect_id: d.defect_id, point_class: String(d.point_class), occurrences: String(d.occurrences) }))
      : [{ defect_id: "", point_class: "1", occurrences: "1" }],
  );

  const defects = options.defects ?? [];

  // The same arithmetic the API will do, so the inspector sees the roll's
  // standing before they commit it rather than after.
  const widths = [widthStart, widthMiddle, widthEnd].filter((w) => w !== "" && Number(w) > 0).map(Number);
  const meanWidth = widths.length > 0 ? widths.reduce((a, b) => a + b, 0) / widths.length : 0;
  const points = lines.reduce(
    (sum, line) => sum + (line.defect_id ? Number(line.point_class || 0) * Number(line.occurrences || 0) : 0),
    0,
  );
  const yards = Number(inspectedLength || 0);
  const percent = yards > 0 && meanWidth > 0 ? (points * 3600) / (yards * meanWidth) : null;

  const ready = yards > 0 && meanWidth > 0;

  function setLine(index, key, value) {
    setLines((current) => current.map((line, i) => (i === index ? { ...line, [key]: value } : line)));
  }

  function submit(event) {
    event.preventDefault();

    onSubmit({
      fabric_roll_id: roll.id,
      width_start: widthStart || undefined,
      width_middle: widthMiddle || undefined,
      width_end: widthEnd || undefined,
      marked_length: markedLength || undefined,
      inspected_length: inspectedLength,
      shade_assessment: Object.keys(shade).length > 0 ? shade : undefined,
      notes: notes || undefined,
      defects: lines
        .filter((line) => line.defect_id && Number(line.occurrences) > 0)
        .map((line) => ({
          defect_id: line.defect_id,
          point_class: Number(line.point_class),
          occurrences: Number(line.occurrences),
        })),
    });
  }

  return (
    <Modal
      open
      onOpenChange={(open) => (open ? null : onClose())}
      title={`${t("record_roll")} — ${roll.roll_no}`}
      description={t("four_point_hint")}
      footer={
        <>
          <Button type="button" variant="outline" onClick={onClose} disabled={pending}>
            {tc("cancel")}
          </Button>
          <Button type="submit" form="roll-tally" disabled={pending || !ready}>
            {tc("save")}
          </Button>
        </>
      }
    >
      <form id="roll-tally" onSubmit={submit} className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <FormField label={t("width_start")} required helperText={t("width_hint")} className="sm:col-span-3">
            {() => (
              <div className="grid grid-cols-3 gap-2">
                <Input type="number" step="any" min="0" value={widthStart} onChange={(e) => setWidthStart(e.target.value)} />
                <Input
                  type="number"
                  step="any"
                  min="0"
                  placeholder={t("width_middle")}
                  value={widthMiddle}
                  onChange={(e) => setWidthMiddle(e.target.value)}
                />
                <Input
                  type="number"
                  step="any"
                  min="0"
                  placeholder={t("width_end")}
                  value={widthEnd}
                  onChange={(e) => setWidthEnd(e.target.value)}
                />
              </div>
            )}
          </FormField>

          <FormField label={t("marked_length")}>
            {(props) => (
              <Input {...props} type="number" step="any" min="0" value={markedLength} onChange={(e) => setMarkedLength(e.target.value)} />
            )}
          </FormField>

          <FormField label={t("inspected_length")} required>
            {(props) => (
              <Input
                {...props}
                type="number"
                step="any"
                min="0"
                value={inspectedLength}
                onChange={(e) => setInspectedLength(e.target.value)}
              />
            )}
          </FormField>

          {/* What the roll currently stands at, updating as they type. */}
          <div className="flex flex-col justify-end gap-0.5">
            <span className="text-xs text-foreground-muted">{t("penalty_percent")}</span>
            <span className="text-xl font-semibold tabular-nums">
              {percent === null ? "—" : percent.toFixed(2)}
            </span>
            <span className="text-xs text-foreground-subtle">
              {t("total_points")}: {points}
            </span>
          </div>
        </div>

        <div className="flex flex-col gap-2">
          <span className="text-sm font-medium">{t("shade_assessment")}</span>
          <p className="text-xs text-foreground-muted">{t("shade_hint")}</p>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-foreground-muted">
                  <th className="py-1" />
                  {LIGHTS.map((light) => (
                    <th key={light} className="py-1 text-center">
                      {t(`light_sources.${light}`)}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {ASPECTS.map((aspect) => (
                  <tr key={aspect} className="border-t border-border">
                    <td className="py-1.5 pr-2">{t(`shade_aspects.${aspect}`)}</td>
                    {LIGHTS.map((light) => {
                      const key = `${light}.${aspect}`;
                      return (
                        <td key={key} className="py-1.5 text-center">
                          <Select
                            options={[
                              { value: "", label: "—" },
                              { value: "PASS", label: t("results.PASS") },
                              { value: "FAIL", label: t("results.FAIL") },
                            ]}
                            value={shade[key] ?? ""}
                            onValueChange={(value) =>
                              setShade((current) => {
                                const next = { ...current };
                                if (value === "") delete next[key];
                                else next[key] = value;
                                return next;
                              })
                            }
                          />
                        </td>
                      );
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        <div className="flex flex-col gap-2">
          <div className="flex items-center justify-between">
            <span className="text-sm font-medium">{t("defect_tally")}</span>
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() => setLines((c) => [...c, { defect_id: "", point_class: "1", occurrences: "1" }])}
            >
              <Plus /> {t("add_defect")}
            </Button>
          </div>
          <p className="text-xs text-foreground-muted">{t("defect_tally_hint")}</p>

          {lines.map((line, index) => (
            <div key={index} className="grid grid-cols-12 gap-2">
              <div className="col-span-5">
                <Select
                  options={[
                    { value: "", label: "—" },
                    ...defects.map((d) => ({ value: d.id, label: d.name })),
                  ]}
                  value={line.defect_id}
                  onValueChange={(value) => setLine(index, "defect_id", value)}
                />
              </div>
              <div className="col-span-4">
                <Select
                  options={[1, 2, 3, 4].map((value) => ({ value: String(value), label: t(`point_classes.${value}`) }))}
                  value={line.point_class}
                  onValueChange={(value) => setLine(index, "point_class", value)}
                />
              </div>
              <div className="col-span-2">
                <Input
                  type="number"
                  min="1"
                  value={line.occurrences}
                  onChange={(e) => setLine(index, "occurrences", e.target.value)}
                />
              </div>
              <div className="col-span-1 flex items-center">
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={lines.length === 1}
                  onClick={() => setLines((c) => c.filter((_, i) => i !== index))}
                >
                  <Trash2 />
                </Button>
              </div>
            </div>
          ))}
        </div>

        <FormField label={t("inspection_notes")}>
          {(props) => <Textarea {...props} rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} />}
        </FormField>
      </form>
    </Modal>
  );
}

export { RollTallyDialog };
