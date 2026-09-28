"use client";

import { Trash2, Plus } from "lucide-react";
import { Card, CardHeader, CardTitle, CardBody } from "@/components/ui/card";
import { FormField } from "@/components/ui/form-field";
import { Select } from "@/components/ui/select";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { useT } from "@/lib/i18n";

let nextRowId = 1;

function newRoll() {
  return { rowId: nextRowId++, roll_no: "", lot_no: "", shade: "", shade_band: "", nominal_width: "", gross_length: "", net_length: "", gross_weight: "", net_weight: "", bin_id: "" };
}

/**
 * The "Roll Entry" step of completing a goods receipt for a ROLL-tracked
 * line (Inventory spec §3.1, §4.2) — one row per physical roll that came
 * off the truck. Replaces the plain per-line bin picker other lines use,
 * since each roll can land on a different shelf. Measurements here are
 * what is known at the gate; `actual_width` and the rest of §4.3's
 * inspection data are recorded later, on the inspection screen.
 */
function RollEntryPanel({ line, rolls, onChange, bins, errors = {} }) {
  const t = useT("procurement");
  const ti = useT("inspection");

  const binOptions = bins.map((b) => ({ value: b.id, label: b.name ? `${b.code} — ${b.name}` : b.code }));

  function update(rowId, patch) {
    onChange(rolls.map((roll) => (roll.rowId === rowId ? { ...roll, ...patch } : roll)));
  }

  function remove(rowId) {
    if (rolls.length > 1) onChange(rolls.filter((roll) => roll.rowId !== rowId));
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          {ti("rolls")} — {line.item_name} <span className="font-mono text-xs text-foreground-muted">{line.item_code}</span>
        </CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-3">
        <p className="text-xs text-foreground-muted">{ti("roll_entry_hint")}</p>

        {rolls.map((roll, index) => {
          const err = (key) => errors?.[`${index}.${key}`]?.[0];
          return (
            <div key={roll.rowId} className="grid grid-cols-2 gap-2 rounded-sm border border-border p-3 sm:grid-cols-6">
              <FormField label={ti("roll_no")} required error={err("roll_no")}>
                {(fp) => <Input {...fp} value={roll.roll_no} onChange={(e) => update(roll.rowId, { roll_no: e.target.value })} maxLength={64} />}
              </FormField>
              <FormField label={ti("bin")} required error={err("bin_id")}>
                {(fp) => <Select {...fp} value={roll.bin_id} onValueChange={(value) => update(roll.rowId, { bin_id: value })} options={binOptions} placeholder={ti("bin")} />}
              </FormField>
              <FormField label={ti("shade")}>
                {(fp) => <Input {...fp} value={roll.shade} onChange={(e) => update(roll.rowId, { shade: e.target.value })} maxLength={64} />}
              </FormField>
              <FormField label={ti("shade_band")}>
                {(fp) => <Input {...fp} value={roll.shade_band} onChange={(e) => update(roll.rowId, { shade_band: e.target.value })} maxLength={32} />}
              </FormField>
              <FormField label={ti("lot_no")}>
                {(fp) => <Input {...fp} value={roll.lot_no} onChange={(e) => update(roll.rowId, { lot_no: e.target.value })} maxLength={64} />}
              </FormField>
              <FormField label={ti("nominal_width")}>
                {(fp) => <Input {...fp} type="number" step="0.01" min="0" value={roll.nominal_width} onChange={(e) => update(roll.rowId, { nominal_width: e.target.value })} />}
              </FormField>
              <FormField label={ti("gross_length")}>
                {(fp) => <Input {...fp} type="number" step="0.01" min="0" value={roll.gross_length} onChange={(e) => update(roll.rowId, { gross_length: e.target.value })} />}
              </FormField>
              <FormField label={ti("net_length")}>
                {(fp) => <Input {...fp} type="number" step="0.01" min="0" value={roll.net_length} onChange={(e) => update(roll.rowId, { net_length: e.target.value })} />}
              </FormField>
              <FormField label={ti("gross_weight")}>
                {(fp) => <Input {...fp} type="number" step="0.01" min="0" value={roll.gross_weight} onChange={(e) => update(roll.rowId, { gross_weight: e.target.value })} />}
              </FormField>
              <FormField label={ti("net_weight")}>
                {(fp) => <Input {...fp} type="number" step="0.01" min="0" value={roll.net_weight} onChange={(e) => update(roll.rowId, { net_weight: e.target.value })} />}
              </FormField>
              <div className="flex items-end">
                <Button type="button" variant="ghost" size="icon" aria-label={ti("remove_roll")} onClick={() => remove(roll.rowId)} disabled={rolls.length === 1}>
                  <Trash2 className="text-danger" />
                </Button>
              </div>
            </div>
          );
        })}

        <div>
          <Button type="button" variant="outline" size="sm" onClick={() => onChange([...rolls, newRoll()])}>
            <Plus /> {ti("add_roll")}
          </Button>
        </div>
      </CardBody>
    </Card>
  );
}

/** The wire shape `rolls[lineId]` the complete endpoint validates, from a panel's rows. */
function serialiseRolls(rolls) {
  return rolls
    .filter((roll) => roll.roll_no.trim() !== "")
    .map(({ rowId: _rowId, ...roll }) => Object.fromEntries(Object.entries(roll).filter(([, value]) => value !== "")));
}

export { RollEntryPanel, newRoll, serialiseRolls };
