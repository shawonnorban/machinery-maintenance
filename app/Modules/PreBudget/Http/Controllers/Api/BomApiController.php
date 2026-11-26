<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Http\Controllers\Api;

use App\Modules\PreBudget\Http\Controllers\Api\Concerns\FindsBudgets;
use App\Modules\PreBudget\Models\PbAccessoryItem;
use App\Modules\PreBudget\Models\PbBom;
use App\Modules\PreBudget\Models\PbBomLine;
use App\Modules\PreBudget\Models\PbBomStyle;
use App\Modules\PreBudget\Models\PbBudget;
use App\Modules\PreBudget\Support\BomCatalog;
use App\Modules\PreBudget\Support\BomColumns;
use App\Modules\PreBudget\Support\BomSheet;
use App\Shared\Formula\Formula;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pre-Budget → Accessories Budget Pending and BOM List (AnnotechERP 33/34,
 * Norban's Accessories Pre-Budget): the budgets with where their accessories
 * stand (Pending / Partial / Created), a budget's BOMs and styles, a BOM's
 * worksheet (styles ticked from the budget, each with its own accessory
 * lines), saved as a draft or final, and the BOM List (one row per BOM
 * style). A BOM changes while its budget may (draft or sent back); only a
 * draft BOM is deleted. The worksheet's columns, formulas and unit rules are
 * the company's (Acc Budget Column Setup, BomColumns).
 */
class BomApiController extends ApiController
{
    use FindsBudgets;

    public const STATES = ['PENDING', 'PARTIAL', 'CREATED'];

    /** Accessories Budget Pending: the budgets with their accessories state, filtered by `state`. */
    public function pending(Request $request): JsonResponse
    {
        $this->allowEither('prebudget.view', 'prebudget.update');
        $state = $request->validate(['state' => ['nullable', Rule::in(self::STATES)]])['state'] ?? null;
        $sheet = new BomSheet;
        $budgets = $this->scoped()->whereNotIn('status', PbBudget::INACTIVE)->latest('updated_at')->limit(300)->get();
        $names = $this->userNames($budgets->pluck('created_by')->all());
        $rows = $budgets->map(function (PbBudget $b) use ($sheet, $names) {
            $s = $sheet->summary($b);

            return [
                'budget_no' => $b->label(), 'buyer' => $b->buyer_name, 'contract_no' => $b->contract_no, 'status' => $b->status,
                'styles' => $s['count'], 'budgeted' => $s['budgeted'], 'boms' => count($s['boms']), 'state' => $s['state'], 'total' => $s['total'],
                'created_by' => $names[$b->created_by] ?? null, 'updated_at' => $b->updated_at?->toIso8601String(),
            ];
        });
        $counts = ['ALL' => $rows->count()] + array_combine(self::STATES, array_map(fn ($st) => $rows->where('state', $st)->count(), self::STATES));

        return ApiResponse::ok(['rows' => $rows->when($state, fn ($c) => $c->where('state', $state))->values()->all(), 'counts' => $counts]);
    }

    /** BOM List: one row per BOM style, filtered by `status` (DRAFT / FINAL) and `q` (BOM / budget no, buyer, brand, job no, style). */
    public function index(Request $request): JsonResponse
    {
        $this->allowEither('prebudget.view', 'prebudget.update');
        $f = $request->validate(['status' => ['nullable', Rule::in([PbBom::DRAFT, PbBom::FINAL])], 'q' => ['nullable', 'string', 'max:120']]);
        $sheet = new BomSheet;
        $budgets = $this->scoped()->whereHas('boms')->with('boms')->latest('updated_at')->limit(300)->get();
        $names = $this->userNames($budgets->flatMap(fn ($b) => $b->boms->pluck('created_by'))->all());
        $rows = [];
        foreach ($budgets as $b) {
            $styles = BomSheet::styles($b->requisitions()->get());
            foreach ($b->boms as $bom) {
                if (($f['status'] ?? null) && $bom->status !== $f['status']) {
                    continue;
                }
                $view = $sheet->bom($bom, $styles, $b);
                foreach ($view['styles'] as $st) {
                    $rows[] = [
                        'bom_no' => $view['bom_no'], 'budget_no' => $b->label(), 'buyer' => $b->buyer_name, 'brand' => $st['brand'], 'job_no' => $st['job_no'], 'style' => $st['style'],
                        'artwork_url' => $st['artwork_url'], 'order_qty' => $st['order_qty'], 'value' => $st['value'], 'cost_pc' => $st['order_qty'] > 0 ? round($st['value'] / $st['order_qty'], 4) : 0.0,
                        'acc_pct' => $st['acc_pct'], 'items' => count($st['lines']), 'status' => $bom->status, 'editable' => $b->editable(),
                        'created_by' => $names[$bom->created_by] ?? null, 'created_at' => $bom->created_at?->toIso8601String(),
                    ];
                }
            }
        }
        $q = mb_strtolower(trim((string) ($f['q'] ?? '')));
        if ($q !== '') {
            $rows = array_values(array_filter($rows, fn ($r) => str_contains(mb_strtolower(implode(' ', [$r['bom_no'], $r['budget_no'], $r['buyer'], $r['brand'], $r['job_no'], $r['style']])), $q)));
        }

        return ApiResponse::ok($rows);
    }

    /** A budget's accessories: its styles (and the BOM each is in), its BOMs, and what a new BOM's worksheet needs. */
    public function budget(string $no): JsonResponse
    {
        return ApiResponse::ok($this->sheet($this->readable($no, ['prebudget.view', 'prebudget.update']), null));
    }

    /** A BOM's worksheet, by its number (PB-CNA-1126-0001-A1). */
    public function show(string $no): JsonResponse
    {
        [$b, $bom] = $this->findBom($no, true);

        return ApiResponse::ok($this->sheet($b, $bom));
    }

    /** A new BOM of the budget's ticked styles. Body: styles[] of {style, lines[]}, final. */
    public function store(Request $request, string $no): JsonResponse
    {
        $this->allow('prebudget.update');
        $b = $this->find($no);
        $this->editable($b);
        $data = $this->validated($request, $b, null);
        $by = $this->caller()->auditUserId();
        $bom = DB::transaction(function () use ($b, $data, $by): PbBom {
            PbBudget::query()->whereKey($b->id)->lockForUpdate()->value('id');
            $bom = PbBom::query()->create(['budget_id' => $b->id, 'seq' => (int) PbBom::query()->where('budget_id', $b->id)->max('seq') + 1, 'status' => PbBom::DRAFT, 'version' => 1, 'created_by' => $by, 'updated_by' => $by]);
            $this->write($b, $bom, $data, $by);
            $this->log($b, 'BOM_CREATE', ['bom' => [null, $bom->label($b).' · '.implode(', ', array_column($data['styles'], 'style'))]], $by);

            return $bom;
        });

        return ApiResponse::created(['bom_no' => $bom->label($b)]);
    }

    /** Save a BOM's worksheet: its styles (one left out goes back to the budget's free styles) and their lines. */
    public function update(Request $request, string $no): JsonResponse
    {
        $this->allow('prebudget.update');
        [$b, $bom] = $this->findBom($no);
        $this->editable($b);
        $data = $this->validated($request, $b, $bom);
        if ((int) $data['version'] !== $bom->version) {
            return ApiResponse::error(\App\Shared\Http\Api\ErrorCode::CONFLICT, __('prebudget.bom.v.conflict'), status: 409);
        }
        $by = $this->caller()->auditUserId();
        DB::transaction(function () use ($b, $bom, $data, $by): void {
            $this->write($b, $bom, $data, $by);
            $this->log($b, 'BOM_UPDATE', ['bom' => [null, $bom->label($b).' · '.($data['final'] ? 'FINAL' : 'DRAFT')]], $by);
        });

        return ApiResponse::ok($this->sheet($b->fresh(), $bom->fresh()));
    }

    /** Delete a draft BOM; its styles go back to the budget's free styles. */
    public function destroy(string $no): JsonResponse
    {
        $this->allowEither('prebudget.update', 'prebudget.delete');
        [$b, $bom] = $this->findBom($no);
        $this->editable($b);
        if ($bom->status !== PbBom::DRAFT) {
            throw ValidationException::withMessages(['bom' => __('prebudget.bom.v.final_delete')]);
        }
        DB::transaction(function () use ($b, $bom): void {
            $this->log($b, 'BOM_DELETE', ['bom' => [$bom->label($b), null]], $this->caller()->auditUserId());
            $bom->delete();
        });

        return ApiResponse::ok(['budget_no' => $b->label()]);
    }

    /** @return array{0: PbBudget, 1: PbBom} */
    private function findBom(string $no, bool $read = false): array
    {
        if (! preg_match('/^(.+)-A(\d+)$/', $no, $m)) {
            abort(404);
        }
        // Read (a BOM's worksheet): a signer of the budget's approval may open it too.
        $b = $read ? $this->readable($m[1], ['prebudget.view', 'prebudget.update']) : $this->find($m[1]);

        return [$b, PbBom::query()->where('budget_id', $b->id)->where('seq', (int) $m[2])->first() ?? abort(404)];
    }

    /** @return array<string, mixed> */
    private function sheet(PbBudget $b, ?PbBom $bom): array
    {
        $reqs = $b->requisitions()->get();
        $styles = BomSheet::styles($reqs);
        $cols = new BomColumns($b->company_id, $this->caller()->user);
        $sheet = new BomSheet($cols);

        return [
            'budget_no' => $b->label(), 'status' => $b->status, 'buyer' => $b->buyer_name, 'contract_no' => $b->contract_no,
            'editable' => $b->editable() && $this->caller()->can('prebudget.update'),
            'can_delete' => $this->caller()->can('prebudget.update') || $this->caller()->can('prebudget.delete'),
            'summary' => $sheet->summary($b, $reqs), 'budget_styles' => array_values($styles),
            'bom' => $bom ? $sheet->bom($bom, $styles, $b) : null,
            // The company's worksheet (Acc Budget Column Setup): its columns, lists and Unit Cost Rules, worked out alike in the browser.
            'groups' => $cols->groups->where('active', true)->map(fn ($g) => ['id' => $g->id, 'code' => $g->code, 'name' => $g->name, 'tone' => $g->tone])->values()->all(),
            // Every column (a formula may read one this user does not see): `access` none is not shown.
            'columns' => $cols->columns->map(fn ($c) => RequisitionApiController::columnBody($c, $cols) + [
                'access' => $cols->access($c), 'style' => $cols->isStyle($c),
            ])->values()->all(),
            'options' => $cols->allOptions(),
            'unit_rules' => array_map(fn ($r) => $r['formula'], $cols->rules),
            // The Unit Guide (user, 2026-11-24): each rule's unit name and note, to check a line's unit before budgeting.
            'unit_names' => array_column(\App\Modules\Inventory\Support\UomCodes::options($b->company_id), 'name', 'code'),
            'unit_notes' => array_filter(array_map(fn ($r) => $r['note'] ?: null, $cols->rules)),
            'unit_key' => $cols->unitKey(),
            'item_units' => PbAccessoryItem::query()->where('active', true)->whereNotNull('uom')->pluck('uom', 'name')->all(),
            'today' => BomColumns::today(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, PbBudget $b, ?PbBom $bom): array
    {
        $data = $request->validate([
            'version' => [$bom ? 'required' : 'nullable', 'integer'], 'final' => ['sometimes', 'boolean'],
            'styles' => ['required', 'array', 'min:1', 'max:200'], 'styles.*.style' => ['present', 'nullable', 'string', 'max:500'],
            'styles.*.lines' => ['present', 'array', 'max:300'], 'styles.*.lines.*' => ['array'],
        ]);
        $data['final'] = (bool) ($data['final'] ?? false);
        $cols = new BomColumns($b->company_id, $this->caller()->user);

        // The styles: the budget's, each once, in no other BOM.
        $budgetStyles = BomSheet::styles($b->requisitions()->get());
        $taken = PbBomStyle::query()->where('budget_id', $b->id)->when($bom, fn ($q) => $q->where('bom_id', '!=', $bom->id))->pluck('style')->all();
        // What a line had (by style and row), for the columns this user may not edit.
        $old = $bom ? $bom->styles()->with('lines')->get()->mapWithKeys(fn ($s) => [$s->style => $s->lines->pluck('values')->all()])->all() : [];
        $seen = [];
        $errors = [];
        foreach ($data['styles'] as $i => &$st) {
            $st['style'] = trim((string) ($st['style'] ?? ''));
            if (! isset($budgetStyles[$st['style']]) || in_array($st['style'], $seen, true)) {
                $errors["styles.{$i}.style"] = __('prebudget.bom.v.not_budget_style', ['style' => $st['style'] ?: '—']);
            } elseif (in_array($st['style'], $taken, true)) {
                $errors["styles.{$i}.style"] = __('prebudget.bom.v.style_taken', ['style' => $st['style'] ?: '—']);
            }
            $seen[] = $st['style'];
            $lines = [];
            foreach (array_values($st['lines'] ?? []) as $j => $line) {
                $values = $cols->put($line, $old[$st['style']][$j] ?? [], "styles.{$i}.lines.{$j}");
                // A line with nothing on it (its starting Nominated / Extra % aside) is left out.
                if (array_diff_key($values, array_flip(['nominated', 'extra_percent'])) !== []) {
                    $lines[] = $values;
                }
            }
            $st['lines'] = $lines;
            if ($data['final'] && isset($budgetStyles[$st['style']])) {
                $errors += $this->finalErrors($cols, $i, $st, BomSheet::cells($budgetStyles[$st['style']], $i + 1));
            }
        }
        unset($st);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /**
     * What a final BOM wants of a style: a line at least; on each line the
     * columns marked Required, a unit with a Unit Cost Rule, and a Total Value
     * that works out (the rule's columns filled).
     */
    private function finalErrors(BomColumns $cols, int $i, array $st, array $cells): array
    {
        $style = $st['style'] ?: '—';
        if ($st['lines'] === []) {
            return ["styles.{$i}.lines" => __('prebudget.bom.v.no_lines', ['style' => $style])];
        }
        $errors = [];
        foreach ($st['lines'] as $j => $typed) {
            $missing = $cols->columns->filter(fn ($c) => $c->required && ! $c->hidden && $cols->isInput($c) && Formula::blank($typed[$c->key] ?? null))->pluck('label')->all();
            if ($missing !== []) {
                $errors["styles.{$i}.lines.{$j}"] = __('prebudget.bom.v.line_missing', ['style' => $style, 'row' => $j + 1, 'fields' => implode(', ', $missing)]);

                continue;
            }
            $unit = $cols->unitOf($typed);
            if ($unit !== null && ! isset($cols->rules[$unit])) {
                $errors["styles.{$i}.lines.{$j}"] = __('prebudget.bom.v.no_rule', ['style' => $style, 'row' => $j + 1, 'unit' => $unit]);

                continue;
            }
            if (Formula::blank($cols->work($typed, $cells)[BomCatalog::VALUE] ?? null)) {
                $errors["styles.{$i}.lines.{$j}"] = __('prebudget.bom.v.no_value', ['style' => $style, 'row' => $j + 1, 'fields' => implode(', ', $unit ? $cols->ruleReads($unit) : [])]);
            }
        }

        return $errors;
    }

    /** The BOM's styles and lines as sent; draft or final. */
    private function write(PbBudget $b, PbBom $bom, array $data, ?string $by): void
    {
        $bom->styles()->delete();
        $now = now();
        foreach (array_values($data['styles']) as $i => $st) {
            $bs = PbBomStyle::query()->create(['bom_id' => $bom->id, 'budget_id' => $b->id, 'style' => $st['style'], 'sort_order' => $i]);
            foreach (array_values($st['lines']) as $j => $values) {
                PbBomLine::query()->create(['bom_style_id' => $bs->id, 'sort_order' => $j, 'values' => $values]);
            }
        }
        $bom->forceFill([
            'status' => $data['final'] ? PbBom::FINAL : PbBom::DRAFT, 'finalized_at' => $data['final'] ? $now : null, 'finalized_by' => $data['final'] ? $by : null,
            'version' => $bom->version + ($bom->wasRecentlyCreated ? 0 : 1), 'updated_by' => $by,
        ])->save();
        $b->forceFill(['updated_by' => $by])->touch();
    }

    /** @param  list<string|null>  $ids  @return array<string, string> */
    private function userNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids === [] ? [] : DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->all();
    }
}
