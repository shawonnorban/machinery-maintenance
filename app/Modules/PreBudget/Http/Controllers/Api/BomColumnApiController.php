<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\PreBudget\Models\PbUnitRule;
use App\Modules\PreBudget\Support\BomCatalog;
use App\Modules\PreBudget\Support\BomColumns;
use App\Modules\PreBudget\Support\RequisitionSheet;
use App\Shared\Formula\Formula;
use App\Shared\Formula\FormulaError;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Pre-Budget → Acc Budget Column Setup (user, 2026-11-19): the accessories
 * BOM worksheet's groups, columns and formulas — the requisition Column
 * Setup on the BOM sheet, every column the company's to change — and its
 * Unit Cost Rules: for each unit the formula that turns a line into its
 * Unit Rule Cost (Cost/Pc reads it), e.g. DZN = [Rate] / 12 * [B.Cons].
 * A rule reads typed and style columns only (not a formula column).
 */
class BomColumnApiController extends ColumnApiController
{
    protected const SHEET = BomCatalog::SHEET;

    /** @return array<string, mixed> */
    protected function extra(RequisitionSheet|BomColumns $sheet): array
    {
        $labels = $sheet->columns->pluck('label', 'key');

        return [
            'unit_rules' => collect($sheet->rules)->map(fn ($r, $uom) => [
                'uom' => $uom, 'formula_text' => Formula::display($r['formula'], fn ($k) => $labels[$k] ?? null), 'note' => $r['note'], 'reads' => $sheet->ruleReads($uom),
            ])->values()->all(),
            'units' => UomCodes::codes($sheet->companyId),
        ];
    }

    /** Add or change a unit's rule. Body: formula (with column names), note. */
    public function saveRule(Request $request, string $uom): JsonResponse
    {
        $this->allow(self::MANAGE);
        $sheet = new BomColumns($this->caller()->companyId);
        $uom = UomCodes::normalize($uom) ?? '';
        if (! in_array($uom, UomCodes::codes($sheet->companyId), true)) {
            abort(404);
        }
        $data = $request->validate(['formula' => ['required', 'string', 'max:2000'], 'note' => ['nullable', 'string', 'max:255']]);
        $stored = $this->ruleFormula($sheet, $data['formula']);
        $before = PbUnitRule::query()->where('uom', $uom)->first();
        PbUnitRule::query()->updateOrCreate(['uom' => $uom], ['formula' => $stored, 'note' => trim((string) ($data['note'] ?? '')) ?: null, 'updated_by' => $this->caller()->auditUserId()]);
        $this->event(BomCatalog::UNIT_COST, 'RULE', $before ? ['label' => "{$uom} rule", 'formula' => $before->formula] : null, ['label' => "{$uom} rule", 'formula' => $stored], null);

        return ApiResponse::ok(['uom' => $uom]);
    }

    public function deleteRule(string $uom): JsonResponse
    {
        $this->allow(self::MANAGE);
        $rule = PbUnitRule::query()->where('uom', strtoupper($uom))->first() ?? abort(404);
        $this->event(BomCatalog::UNIT_COST, 'RULE', ['label' => "{$rule->uom} rule", 'formula' => $rule->formula], null, null);
        $rule->delete();

        return ApiResponse::ok(['deleted' => $rule->uom]);
    }

    /** A rule's formula as stored: column names known, typed or style columns only. */
    private function ruleFormula(BomColumns $sheet, string $text): string
    {
        $labels = $sheet->columns->pluck('key', 'label');
        $byLower = $sheet->columns->mapWithKeys(fn ($c) => [mb_strtolower($c->label) => $c->key]);
        try {
            $stored = Formula::stored($text, fn (string $name) => $labels[$name] ?? $byLower[mb_strtolower($name)] ?? null);
            $refs = Formula::refs(Formula::parse($stored, fn () => null));
        } catch (FormulaError $e) {
            throw ValidationException::withMessages(['formula' => $this->errorText($e)]);
        }
        $bad = array_filter($refs, fn ($k) => ($c = $sheet->column($k)) === null || $sheet->formulas->isFormula($k) || $k === BomCatalog::UNIT_COST);
        if ($bad !== []) {
            throw ValidationException::withMessages(['formula' => __('prebudget.bom.v.rule_reads', ['columns' => implode(', ', array_map(fn ($k) => $sheet->column($k)?->label ?? $k, $bad))])]);
        }

        return $stored;
    }
}
