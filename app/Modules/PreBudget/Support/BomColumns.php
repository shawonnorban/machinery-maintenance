<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Support;

use App\Modules\Identity\Models\LookupTable;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Inventory\Support\UomCodes;
use App\Modules\PreBudget\Models\PbAccessoryItem;
use App\Modules\PreBudget\Models\PbBomLine;
use App\Modules\PreBudget\Models\PbColumn;
use App\Modules\PreBudget\Models\PbGroup;
use App\Modules\PreBudget\Models\PbUnitRule;
use App\Shared\Formula\Formula;
use App\Shared\Formula\FormulaSet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One company's accessories BOM worksheet as it stands (Pre-Budget → Acc
 * Budget Column Setup): its groups and columns (BomCatalog to start), their
 * formulas, its Unit Cost Rules, what each source offers and what this user
 * may edit. A line is worked out here: the style's cells and the line's typed
 * values, Unit Rule Cost from the line's unit's rule, then every formula.
 */
final class BomColumns
{
    /** @var Collection<int, PbColumn> */
    public Collection $columns;

    /** @var Collection<int, PbGroup> */
    public Collection $groups;

    public FormulaSet $formulas;

    /** @var array<string, array{formula: string, tree: array<int, mixed>, note: ?string}> unit => rule */
    public array $rules = [];

    /** @var array<int|string, true>|null */
    private ?array $roles = null;

    /** @var array<string, list<array{value: string, label: string}>> */
    private array $options = [];

    public function __construct(public readonly string $companyId, public readonly ?User $user = null)
    {
        BomCatalog::ensure($companyId);
        $this->groups = PbGroup::query()->where('company_id', $companyId)->where('sheet', BomCatalog::SHEET)->orderBy('sort_order')->get();
        $order = $this->groups->pluck('sort_order', 'id');
        $this->columns = PbColumn::query()->where('company_id', $companyId)->where('sheet', BomCatalog::SHEET)->get()
            ->sortBy(fn (PbColumn $c) => [$order[$c->group_id] ?? 0, $c->sort_order])->values();
        $this->formulas = new FormulaSet($this->columns->mapWithKeys(fn (PbColumn $c) => [$c->key => ['formula' => $c->formula, 'type' => $c->type, 'decimals' => $c->decimals]])->all());
        foreach (PbUnitRule::query()->where('company_id', $companyId)->orderBy('uom')->get() as $r) {
            try {
                $this->rules[$r->uom] = ['formula' => $r->formula, 'tree' => Formula::parse($r->formula, fn () => null), 'note' => $r->note];
            } catch (\Throwable) {
                // A rule naming a column deleted since: it works out blank until fixed in Column Setup.
            }
        }
        if ($user) {
            $this->roles = array_fill_keys(UserRole::query()->where('company_id', $companyId)->where('user_id', $user->id)->pluck('role_id')->map(fn ($id) => (string) $id)->all(), true);
        }
    }

    public static function today(): string
    {
        return RequisitionSheet::today();
    }

    public function column(string $key): ?PbColumn
    {
        return $this->columns->firstWhere('key', $key);
    }

    public function styleGroupId(): ?string
    {
        return $this->groups->firstWhere('code', BomCatalog::STYLE_GROUP)?->id;
    }

    /** Whether a column is one of the style's cells (filled from the budget). */
    public function isStyle(PbColumn $c): bool
    {
        return $c->group_id === $this->styleGroupId();
    }

    /** A column a line's user types in: not the style's, not filled by the app, not a formula. */
    public function isInput(PbColumn $c): bool
    {
        return ! $this->isStyle($c) && $c->type !== 'system' && ! $this->formulas->isFormula($c->key);
    }

    /** Nothing is stored worked out: the BOM is worked out when it is read. */
    public function recalculateAll(?string $by = null): int
    {
        return 0;
    }

    /** none, view or edit — role grants in Column Setup; formula, system and style columns read-only. */
    public function access(PbColumn $c): string
    {
        $grant = 'edit';
        if ($this->roles !== null && ! empty($c->role_access)) {
            $grant = 'none';
            $rank = ['none' => 0, 'view' => 1, 'edit' => 2];
            foreach ($c->role_access as $role => $g) {
                if (isset($this->roles[(string) $role]) && ($rank[(string) $g] ?? 0) > $rank[$grant]) {
                    $grant = (string) $g;
                }
            }
            if (array_diff_key($this->roles, $c->role_access) !== []) {
                $grant = 'edit';
            }
        }

        return $grant === 'edit' && ! $this->isInput($c) ? 'view' : $grant;
    }

    /* ── Sources ────────────────────────────────────────────────────── */

    /** @return list<array{value: string, label: string}> */
    public function options(string $source): array
    {
        if (isset($this->options[$source])) {
            return $this->options[$source];
        }
        $c = $this->companyId;
        $list = match (true) {
            $source === 'accessory_items' => PbAccessoryItem::query()->where('company_id', $c)->where('active', true)->orderBy('name')->pluck('name'),
            $source === 'suppliers' => DB::table('mp_suppliers')->where('company_id', $c)->where('active', true)->orderBy('name')->pluck('name'),
            $source === 'units' => collect(UomCodes::codes($c)),
            $source === 'nominated' => collect(BomCatalog::NOMINATED),
            str_starts_with($source, 'pb:') => DB::table('pb_list_values')->where('company_id', $c)->where('list', substr($source, 3))->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            str_starts_with($source, 'lookup:') => ($t = LookupTable::query()->visible()->where('code', substr($source, 7))->orderByRaw('company_id is null')->first())
                ? $t->values()->visible()->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name') : collect(),
            default => collect(),
        };

        return $this->options[$source] = collect($list)->map(fn ($v) => trim((string) $v))->filter()->unique()->values()
            ->map(fn (string $v) => ['value' => $v, 'label' => $v])->all();
    }

    /** @return array<string, list<array{value: string, label: string}>> every source the columns use */
    public function allOptions(): array
    {
        $out = [];
        foreach ($this->columns as $c) {
            if ($c->source && ! isset($out[$c->source])) {
                $out[$c->source] = $this->options($c->source);
            }
        }

        return $out;
    }

    /* ── Values ─────────────────────────────────────────────────────── */

    /**
     * A line's typed values, checked: only input columns this user may edit
     * (one they may not keeps its old value), numbers as numbers, a list's
     * value from its list. Blank values are left out.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $old
     * @return array<string, mixed>
     */
    public function put(array $input, array $old, string $field): array
    {
        $out = [];
        foreach ($this->columns as $c) {
            if (! $this->isInput($c)) {
                continue;
            }
            $raw = $this->access($c) === 'edit' ? ($input[$c->key] ?? null) : ($old[$c->key] ?? null);
            $v = $this->normalize($c, $raw, "{$field}.{$c->key}");
            if (! Formula::blank($v)) {
                $out[$c->key] = $v;
            }
        }

        return $out;
    }

    private function normalize(PbColumn $c, mixed $raw, string $field): mixed
    {
        if (is_string($raw)) {
            $raw = trim($raw);
        }
        if (Formula::blank($raw)) {
            return null;
        }
        $fail = fn (string $msg) => throw ValidationException::withMessages([$field => __("prebudget.v.{$msg}", ['column' => $c->label])]);
        switch ($c->type) {
            case 'number':
            case 'money':
            case 'percent':
            case 'days':
                $n = Formula::num($raw);
                if ($n === null || abs($n) > 1e12) {
                    $fail('number');
                }

                return Formula::round($n, $c->type === 'days' ? 0 : (int) ($c->decimals ?? 2));
            case 'date':
                return Formula::day(is_string($raw) ? $raw : '') === null ? $fail('date') : substr((string) $raw, 0, 10);
            case 'dropdown':
                return $this->pick($c, (string) $raw, $fail);
            case 'multi':
                $out = [];
                foreach (is_array($raw) ? $raw : preg_split('/\s*[,\n]\s*/', (string) $raw) as $v) {
                    $v = trim((string) $v);
                    if ($v !== '') {
                        $out[] = $c->source ? $this->pick($c, $v, $fail) : mb_substr($v, 0, 120);
                    }
                }

                return array_values(array_unique($out)) ?: null;
            case 'longtext':
                return mb_substr(is_array($raw) ? implode(', ', $raw) : (string) $raw, 0, 5000);
            case 'image':
                return null;
            default:
                return mb_substr(is_array($raw) ? implode(', ', $raw) : (string) $raw, 0, 500);
        }
    }

    private function pick(PbColumn $c, string $v, callable $fail): string
    {
        if (! $c->source) {
            return mb_substr($v, 0, 255);
        }
        if ($c->source === 'units') {
            $v = UomCodes::normalize($v) ?? $v;
        }
        foreach ($this->options($c->source) as $o) {
            if (mb_strtolower($o['value']) === mb_strtolower($v)) {
                return $o['value'];
            }
        }

        $fail('option');
    }

    /**
     * A line worked out: the style's cells (`sl`, `brand`, `job_no`, `style`,
     * `artwork`, `order_qty`, `fob`, `style_value`), the typed values, Unit
     * Rule Cost from the rule of its unit, then every formula.
     *
     * @param  array<string, mixed>  $typed
     * @param  array<string, mixed>  $style  the style's cells
     * @return array<string, mixed>
     */
    public function work(array $typed, array $style): array
    {
        $values = $style + $typed;
        $unit = $this->unitOf($values);
        $values[BomCatalog::UNIT_COST] = null;
        if ($unit !== null && isset($this->rules[$unit])) {
            $v = Formula::num(Formula::evaluate($this->rules[$unit]['tree'], $values, self::today()));
            $values[BomCatalog::UNIT_COST] = $v === null || is_nan($v) || is_infinite($v) ? null : Formula::round($v, FormulaSet::PRECISION);
        }

        return $this->formulas->apply($values, self::today(), true);
    }

    /** The line's unit: the value of the column the unit rules go by (Unit, a units dropdown). */
    public function unitOf(array $values): ?string
    {
        $key = $this->unitKey();
        $u = $key ? trim((string) ($values[$key] ?? '')) : '';

        return $u === '' ? null : strtoupper($u);
    }

    public function unitKey(): ?string
    {
        return $this->column('unit') ? 'unit' : $this->columns->first(fn (PbColumn $c) => $c->source === 'units')?->key;
    }

    /** A recent line with its style cells, for Column Setup's formula check. @return array{label: string, values: array<string, mixed>}|null */
    public function sample(): ?array
    {
        $line = PbBomLine::query()->latest('updated_at')->first();

        return $line ? ['label' => (string) ($line->values['item'] ?? ''), 'values' => ['order_qty' => 1000, 'style_value' => 3500, 'fob' => 3.5] + ($line->values ?? [])] : null;
    }

    /** The column labels a unit rule reads. @return list<string> */
    public function ruleReads(string $unit): array
    {
        $rule = $this->rules[strtoupper($unit)] ?? null;

        return $rule ? array_values(array_filter(array_map(fn ($k) => $this->column($k)?->label, Formula::refs($rule['tree'])))) : [];
    }
}
