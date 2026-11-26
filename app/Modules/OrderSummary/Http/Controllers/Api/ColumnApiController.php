<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Http\Controllers\Api;

use App\Modules\Identity\Models\LookupTable;
use App\Modules\Identity\Models\Role;
use App\Modules\OrderSummary\Models\OsColumn;
use App\Modules\OrderSummary\Models\OsColumnEvent;
use App\Modules\OrderSummary\Models\OsGroup;
use App\Modules\OrderSummary\Models\OsOrder;
use App\Modules\OrderSummary\Support\ColumnCatalog;
use App\Modules\OrderSummary\Support\Sheet;
use App\Modules\PreBudget\Support\MerchLists;
use App\Shared\Formula\Formula;
use App\Shared\Formula\FormulaError;
use App\Shared\Formula\FormulaSet;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Order Summary → Column Setup (AnnotechERP 32): the sheet's groups and
 * columns, kept by the company, not the code. Add a column (its group, which
 * column it follows, its type and list, its formula), change one — label,
 * place, formula, colour rules, summary card, which roles see or edit it —,
 * hide it, or delete one the company added. A formula is checked as it is
 * typed (names, functions, loops, a sample row's result); saved, every order
 * row is worked out again and the change goes into the history.
 */
class ColumnApiController extends ApiController
{
    private const MANAGE = 'ordersummary.columns.manage';

    public function index(): JsonResponse
    {
        $this->allow('ordersummary.view');
        $sheet = new Sheet($this->caller()->companyId);
        $labels = $sheet->columns->pluck('label', 'key');
        $usedBy = [];
        foreach ($sheet->columns as $c) {
            if ($c->formula) {
                foreach (Formula::refs(Formula::parse($c->formula, fn () => null)) as $ref) {
                    $usedBy[$ref][] = $c->label;
                }
            }
        }

        return ApiResponse::ok([
            'groups' => $sheet->groups->map(fn (OsGroup $g) => ['id' => $g->id, 'code' => $g->code, 'name' => $g->name, 'tone' => $g->tone, 'active' => $g->active, 'is_system' => $g->is_system])->values()->all(),
            'columns' => $sheet->columns->map(fn (OsColumn $c) => OrderApiController::columnBody($c, $sheet) + [
                'formula_text' => $c->formula ? Formula::display($c->formula, fn ($k) => $labels[$k] ?? null) : null,
                'role_access' => (object) ($c->role_access ?? []), 'used_by' => $usedBy[$c->key] ?? [],
            ])->values()->all(),
            'types' => ColumnCatalog::TYPES, 'sources' => $this->sources(), 'tones' => ColumnCatalog::TONES, 'text_tones' => ColumnCatalog::TEXT_TONES, 'bold' => true, 'rule_ops' => ColumnCatalog::RULE_OPS,
            'functions' => array_keys(Formula::FUNCTIONS),
            'roles' => Role::query()->availableTo($this->caller()->companyId)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->description ?: $r->name])->values()->all(),
            'can_manage' => $this->caller()->can(self::MANAGE),
        ]);
    }

    /** What a formula would do, checked as it is typed: its stored form, what it reads, a sample row's result. */
    public function check(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);
        $data = $request->validate(['formula' => ['required', 'string', 'max:2000'], 'type' => ['required', Rule::in(ColumnCatalog::TYPES)], 'decimals' => ['nullable', 'integer', 'min:0', 'max:6'], 'key' => ['nullable', 'string', 'max:64']]);
        $sheet = new Sheet($this->caller()->companyId);
        try {
            $stored = $this->storedFormula($sheet, $data['formula'], $data['key'] ?? null, $data['type'], $data['decimals'] ?? null);
        } catch (FormulaError $e) {
            return ApiResponse::ok(['ok' => false, 'error' => $this->errorText($e), 'position' => $e->position]);
        }
        $key = $data['key'] ?? '__new__';
        $set = $this->setWith($sheet, $key, $stored, $data['type'], $data['decimals'] ?? null);
        $sample = OsOrder::query()->latest('updated_at')->first();
        $result = $sample ? $set->apply($sample->values ?? [], Sheet::today(), true)[$key] ?? null : null;

        return ApiResponse::ok([
            'ok' => true, 'stored' => $stored, 'volatile' => $set->isVolatile($key),
            'reads' => array_map(fn ($k) => $sheet->column($k)?->label ?? $k, Formula::refs(Formula::parse($stored, fn () => null))),
            'sample' => $sample ? ['serial_no' => $sample->serial_no, 'result' => $result] : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);
        $sheet = new Sheet($this->caller()->companyId);
        $data = $this->validated($request, $sheet, null);
        $key = $this->newKey($sheet, $data['label']);
        $stored = $data['formula'] ? $this->storedFormula($sheet, $data['formula'], $key, $data['type'], $data['decimals'] ?? null) : null;
        $recalc = DB::transaction(function () use ($sheet, $data, $key, $stored): int {
            $sort = $this->placeAfter($data['group_id'], $data['after'] ?? null);
            $c = OsColumn::query()->create($this->fields($data) + ['key' => $key, 'group_id' => $data['group_id'], 'sort_order' => $sort, 'formula' => $stored, 'is_system' => false, 'updated_by' => $this->caller()->auditUserId()]);
            $n = $stored ? (new Sheet($sheet->companyId))->recalculateAll($this->caller()->auditUserId()) : 0;
            $this->event($key, 'ADD', null, $this->snapshot($c), $n);

            return $n;
        });

        return ApiResponse::created(['key' => $key, 'recalculated' => $recalc]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $this->allow(self::MANAGE);
        $sheet = new Sheet($this->caller()->companyId);
        $c = $sheet->column($key) ?? abort(404);
        $data = $this->validated($request, $sheet, $c);
        $stored = $data['formula'] ? $this->storedFormula($sheet, $data['formula'], $key, $data['type'], $data['decimals'] ?? null) : null;
        $before = $this->snapshot($c);
        $recalc = DB::transaction(function () use ($sheet, $c, $data, $stored, $key, $before): int {
            $fields = $this->fields($data);
            if ($c->is_system) {
                // A starting column keeps its type and list: its rows already hold values of that kind.
                unset($fields['type'], $fields['source'], $fields['multiple']);
                $fields['pair_reduce'] = $c->type === 'pairs' ? ($data['pair_reduce'] ?? $c->pair_reduce ?? 'SUM') : null;
            }
            $moved = $data['group_id'] !== $c->group_id || array_key_exists('after', $data);
            $c->fill($fields + ['group_id' => $data['group_id'], 'formula' => $stored, 'updated_by' => $this->caller()->auditUserId()]);
            if ($moved && array_key_exists('after', $data)) {
                $c->sort_order = $this->placeAfter($data['group_id'], $data['after'], $c->id);
            }
            $c->save();
            $formulaChanged = ($before['formula'] ?? null) !== $stored || ($before['type'] ?? null) !== $c->type || ($before['decimals'] ?? null) !== $c->decimals;
            $n = $formulaChanged ? (new Sheet($sheet->companyId))->recalculateAll($this->caller()->auditUserId()) : 0;
            $this->event($key, $formulaChanged ? 'FORMULA' : 'EDIT', $before, $this->snapshot($c->fresh()), $formulaChanged ? $n : null);

            return $n;
        });

        return ApiResponse::ok(['key' => $key, 'recalculated' => $recalc]);
    }

    /** Freeze the sheet up to this column (null: nothing frozen) — Excel's Freeze Panes. */
    public function freeze(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);
        $sheet = new Sheet($this->caller()->companyId);
        $key = $request->validate(['key' => ['nullable', 'string', 'max:64']])['key'] ?? null;
        $c = $key === null ? null : ($sheet->column($key) ?? abort(404));
        $before = $sheet->columns->firstWhere('frozen', true)?->key;
        DB::transaction(function () use ($c): void {
            OsColumn::query()->where('company_id', $this->caller()->companyId)->where('frozen', true)->update(['frozen' => false]);
            $c?->forceFill(['frozen' => true, 'updated_by' => $this->caller()->auditUserId()])->save();
        });
        $this->event($key ?? (string) $before, 'FREEZE', null, null, null);

        return ApiResponse::ok(['key' => $key]);
    }

    /** Hide or show a column (a starting one too); hiding one a formula reads is refused. */
    public function visibility(Request $request, string $key): JsonResponse
    {
        $this->allow(self::MANAGE);
        $sheet = new Sheet($this->caller()->companyId);
        $c = $sheet->column($key) ?? abort(404);
        $hidden = $request->boolean('hidden');
        $c->forceFill(['hidden' => $hidden, 'updated_by' => $this->caller()->auditUserId()])->save();
        $this->event($key, $hidden ? 'HIDE' : 'SHOW', null, null, null);

        return ApiResponse::ok(['key' => $key, 'hidden' => $hidden]);
    }

    /** Delete a column the company added — not a starting one, nor one a formula reads. */
    public function destroy(string $key): JsonResponse
    {
        $this->allow(self::MANAGE);
        $sheet = new Sheet($this->caller()->companyId);
        $c = $sheet->column($key) ?? abort(404);
        if ($c->is_system) {
            throw ValidationException::withMessages(['key' => __('ordersummary.v.system_column')]);
        }
        $readers = $sheet->columns->filter(fn ($x) => $x->formula && in_array($key, Formula::refs(Formula::parse($x->formula, fn () => null)), true))->pluck('label');
        if ($readers->isNotEmpty()) {
            throw ValidationException::withMessages(['key' => __('ordersummary.v.column_in_use', ['columns' => $readers->implode(', ')])]);
        }
        $before = $this->snapshot($c);
        $c->delete();
        $this->event($key, 'DELETE', $before, null, null);

        return ApiResponse::ok(['deleted' => $key]);
    }

    /* ── Groups ─────────────────────────────────────────────────────── */

    public function storeGroup(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);
        ColumnCatalog::ensure($this->caller()->companyId);
        $data = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('os_groups', 'name')->where('company_id', $this->caller()->companyId)], 'tone' => ['nullable', Rule::in(ColumnCatalog::TONES)]], [], ['name' => __('ordersummary.cs.group_name')]);
        $max = (int) OsGroup::query()->max('sort_order');
        $code = 'G'.Str::upper(Str::random(6));
        $g = OsGroup::query()->create(['code' => $code, 'name' => $data['name'], 'tone' => $data['tone'] ?? null, 'sort_order' => $max + 10, 'is_system' => false, 'active' => true]);

        return ApiResponse::created(['id' => $g->id]);
    }

    public function updateGroup(Request $request, string $group): JsonResponse
    {
        $this->allow(self::MANAGE);
        $g = OsGroup::query()->findOrFail($group);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('os_groups', 'name')->where('company_id', $this->caller()->companyId)->ignore($g->id)],
            'tone' => ['nullable', Rule::in(ColumnCatalog::TONES)], 'active' => ['sometimes', 'boolean'], 'move' => ['nullable', Rule::in(['up', 'down'])],
        ], [], ['name' => __('ordersummary.cs.group_name')]);
        DB::transaction(function () use ($g, $data): void {
            $g->fill(['name' => $data['name'], 'tone' => $data['tone'] ?? null, 'active' => $data['active'] ?? $g->active])->save();
            if (! empty($data['move'])) {
                $all = OsGroup::query()->orderBy('sort_order')->get()->values();
                $i = $all->search(fn ($x) => $x->id === $g->id);
                $j = $data['move'] === 'up' ? $i - 1 : $i + 1;
                if ($j >= 0 && $j < $all->count()) {
                    [$a, $b] = [$all[$i]->sort_order, $all[$j]->sort_order];
                    $all[$i]->forceFill(['sort_order' => $b])->save();
                    $all[$j]->forceFill(['sort_order' => $a])->save();
                }
            }
        });

        return ApiResponse::ok(['id' => $g->id]);
    }

    public function history(): JsonResponse
    {
        $this->allow('ordersummary.view');
        $sheet = new Sheet($this->caller()->companyId);
        $labels = $sheet->columns->pluck('label', 'key');
        $users = DB::table('users')->pluck('name', 'id');

        return ApiResponse::ok(OsColumnEvent::query()->orderByDesc('at')->limit(300)->get()->map(fn (OsColumnEvent $e) => [
            'at' => $e->at?->toIso8601String(), 'column' => $labels[$e->column_key] ?? ($e->before['label'] ?? $e->column_key), 'action' => $e->action,
            'before' => $this->eventText($e->before, $labels, $e->after), 'after' => $this->eventText($e->after, $labels, $e->before), 'recalculated' => $e->recalculated, 'by' => $users[$e->by] ?? null,
        ])->all());
    }

    /* ── Helpers ────────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function validated(Request $request, Sheet $sheet, ?OsColumn $row): array
    {
        $company = $this->caller()->companyId;
        $sources = array_column($this->sources(), 'value');
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120', 'regex:/^[^\[\]{}]+$/u', Rule::unique('os_columns', 'label')->where('company_id', $company)->ignore($row?->id)],
            'group_id' => ['required', 'string', Rule::exists('os_groups', 'id')->where('company_id', $company)],
            'after' => ['nullable', 'string', 'max:64'],
            // "system" (Serial No, Last Update Date) cannot be chosen, but a system column keeps it when its other settings are saved.
            'type' => ['required', Rule::in($row?->type === 'system' ? ['system'] : array_diff(ColumnCatalog::TYPES, ['system']))],
            'source' => ['nullable', Rule::in($sources)],
            'multiple' => ['sometimes', 'boolean'],
            'prefix' => ['nullable', 'string', 'max:12'], 'suffix' => ['nullable', 'string', 'max:12'],
            'decimals' => ['nullable', 'integer', 'min:0', 'max:6'], 'width' => ['nullable', 'integer', 'min:30', 'max:600'],
            'vertical' => ['sometimes', 'boolean'], 'required' => ['sometimes', 'boolean'], 'hidden' => ['sometimes', 'boolean'],
            'formula' => ['nullable', 'string', 'max:2000'],
            'color_rules' => ['nullable', 'array', 'max:12'], 'color_rules.*.op' => ['required', Rule::in(ColumnCatalog::RULE_OPS)],
            'color_rules.*.value' => ['nullable', 'string', 'max:120'], 'color_rules.*.value2' => ['nullable', 'string', 'max:120'], 'color_rules.*.tone' => ['required', Rule::in(ColumnCatalog::TONES)],
            'text_tone' => ['nullable', Rule::in(ColumnCatalog::TEXT_TONES)], 'bold' => ['sometimes', 'boolean'],
            'kpi' => ['nullable', Rule::in(['SUM', 'AVG'])],
            'pair_reduce' => ['nullable', Rule::in(['SUM', 'FIRST'])],
            'role_access' => ['nullable', 'array'], 'role_access.*' => [Rule::in(['none', 'view', 'edit'])],
        ], ['label.regex' => __('ordersummary.v.label_brackets')], __('ordersummary.cs.attrs'));
        if ($row?->type === 'system') {
            $data['type'] = 'system';
        }
        if (in_array($data['type'], ['dropdown'], true) && empty($data['source'])) {
            throw ValidationException::withMessages(['source' => __('ordersummary.v.source_needed')]);
        }
        // "__start" puts the column first in its group.
        if (! empty($data['after']) && $data['after'] !== '__start' && ! $sheet->column($data['after'])) {
            throw ValidationException::withMessages(['after' => __('ordersummary.v.after_unknown')]);
        }
        if (! empty($data['kpi']) && ! in_array($data['type'], FormulaSet::NUMBER_TYPES, true)) {
            throw ValidationException::withMessages(['kpi' => __('ordersummary.v.kpi_number')]);
        }
        $data['formula'] = trim((string) ($data['formula'] ?? '')) ?: null;
        if ($data['formula'] && in_array($data['type'], ['image', 'multi', 'dropdown', 'pairs'], true)) {
            throw ValidationException::withMessages(['formula' => __('ordersummary.v.formula_type')]);
        }
        $data['role_access'] = array_filter($data['role_access'] ?? [], fn ($g) => $g !== 'edit') ?: null;

        return $data;
    }

    /** @return array<string, mixed> */
    private function fields(array $d): array
    {
        return [
            'label' => trim($d['label']), 'type' => $d['type'], 'source' => in_array($d['type'], ['dropdown', 'multi'], true) ? ($d['source'] ?? null) : null,
            'multiple' => $d['type'] === 'multi', 'prefix' => $d['prefix'] ?? null, 'suffix' => $d['suffix'] ?? null, 'decimals' => $d['decimals'] ?? null,
            'width' => $d['width'] ?? 130, 'vertical' => (bool) ($d['vertical'] ?? false), 'required' => (bool) ($d['required'] ?? false), 'hidden' => (bool) ($d['hidden'] ?? false),
            'color_rules' => array_values($d['color_rules'] ?? []) ?: null, 'text_tone' => $d['text_tone'] ?? null, 'kpi' => $d['kpi'] ?? null, 'role_access' => $d['role_access'],
            // A pairs column: which number a formula reads — its lines' total (SUM) or the first line (FIRST).
            'pair_reduce' => $d['type'] === 'pairs' ? ($d['pair_reduce'] ?? 'SUM') : null,
        ] + (array_key_exists('bold', $d) ? ['bold' => (bool) $d['bold']] : []); // Bold only when sent: an older caller leaves it as it is.
    }

    /**
     * The formula as stored ({key}), refused when a name is unknown or it
     * would make a loop with the other columns' formulas.
     */
    private function storedFormula(Sheet $sheet, string $text, ?string $key, string $type, ?int $decimals): string
    {
        $labels = $sheet->columns->pluck('key', 'label');
        $byLower = $sheet->columns->mapWithKeys(fn ($c) => [mb_strtolower($c->label) => $c->key]);
        $stored = Formula::stored($text, fn (string $name) => $labels[$name] ?? $byLower[mb_strtolower($name)] ?? null);
        if ($key !== null && in_array($key, Formula::refs(Formula::parse($stored, fn () => null)), true)) {
            throw new FormulaError('self', []);
        }
        $this->setWith($sheet, $key ?? '__new__', $stored, $type, $decimals);

        return $stored;
    }

    private function setWith(Sheet $sheet, string $key, string $stored, string $type, ?int $decimals): FormulaSet
    {
        $cols = $sheet->columns->mapWithKeys(fn ($c) => [$c->key => ['formula' => $c->formula, 'type' => $c->type, 'decimals' => $c->decimals, 'reduce' => $c->pair_reduce]])->all();
        $cols[$key] = ['formula' => $stored, 'type' => $type, 'decimals' => $decimals];

        return new FormulaSet($cols);
    }

    private function errorText(FormulaError $e): string
    {
        return __("ordersummary.f.{$e->kind}", array_map('strval', $e->params));
    }

    /** @return list<array{value: string, label: string}> */
    private function sources(): array
    {
        $out = array_map(fn ($s) => ['value' => $s, 'label' => __("ordersummary.cs.source.{$s}")], ColumnCatalog::SOURCES);
        // The lists Order Summary shares with the requisition sheet (user, 2026-11-23).
        foreach (array_unique(array_values(MerchLists::OS_COLUMNS)) as $list) {
            $out[] = ['value' => "pb:{$list}", 'label' => __('ordersummary.cs.source.pb', ['name' => Str::title(str_replace('_', ' ', strtolower($list)))])];
        }
        foreach (LookupTable::query()->visible()->where('active', true)->orderBy('name')->get(['code', 'name']) as $t) {
            $out[] = ['value' => "lookup:{$t->code}", 'label' => __('ordersummary.cs.source.lookup', ['name' => $t->name])];
        }

        return $out;
    }

    private function newKey(Sheet $sheet, string $label): string
    {
        $base = Str::limit(Str::slug($label, '_'), 48, '') ?: 'column';
        $key = $base;
        for ($i = 2; $sheet->column($key) || OsColumn::query()->where('key', $key)->exists(); $i++) {
            $key = "{$base}_{$i}";
        }

        return $key;
    }

    /** A sort order putting a column just after `$after` in the group (first when null), the group renumbered. */
    private function placeAfter(string $groupId, ?string $after, ?string $selfId = null): int
    {
        $cols = OsColumn::query()->where('group_id', $groupId)->when($selfId, fn ($q) => $q->whereKeyNot($selfId))->orderBy('sort_order')->get();
        $pos = 0;
        foreach ($cols as $i => $c) {
            if ($c->key === $after) {
                $pos = $i + 1;
            }
        }
        $sort = 0;
        $mine = 0;
        foreach ($cols as $i => $c) {
            if ($i === $pos) {
                $sort += 10;
                $mine = $sort;
            }
            $sort += 10;
            if ($c->sort_order !== $sort) {
                $c->forceFill(['sort_order' => $sort])->saveQuietly();
            }
        }

        return $mine ?: $sort + 10;
    }

    private function snapshot(OsColumn $c): array
    {
        return $c->only(['key', 'label', 'group_id', 'type', 'source', 'decimals', 'formula', 'color_rules', 'text_tone', 'bold', 'kpi', 'pair_reduce', 'role_access', 'hidden', 'vertical', 'width', 'prefix', 'suffix', 'required']);
    }

    private function event(string $key, string $action, ?array $before, ?array $after, ?int $recalculated): void
    {
        OsColumnEvent::query()->create(['column_key' => $key, 'action' => $action, 'before' => $before, 'after' => $after, 'recalculated' => $recalculated, 'by' => $this->caller()->auditUserId(), 'at' => now()]);
    }

    /** A column as History shows it: its name and formula, plus each setting that differs from the other side. */
    private function eventText(?array $snap, $labels, ?array $other = null): ?string
    {
        if (! $snap) {
            return null;
        }
        $formula = ! empty($snap['formula']) ? Formula::display($snap['formula'], fn ($k) => $labels[$k] ?? null) : null;
        $changed = [];
        foreach (['type', 'source', 'decimals', 'width', 'prefix', 'suffix', 'vertical', 'required', 'hidden', 'kpi', 'pair_reduce', 'color_rules', 'text_tone', 'bold', 'role_access'] as $f) {
            if ($other !== null && array_key_exists($f, $snap) && array_key_exists($f, $other) && $snap[$f] != $other[$f]) {
                $v = $snap[$f];
                $changed[] = $f.' '.(is_bool($v) ? ($v ? 'yes' : 'no') : (is_array($v) ? count($v) : ($v ?? '—')));
            }
        }

        return trim(($snap['label'] ?? '').($formula ? " = {$formula}" : '').($changed ? ' · '.implode(', ', $changed) : ''));
    }
}
