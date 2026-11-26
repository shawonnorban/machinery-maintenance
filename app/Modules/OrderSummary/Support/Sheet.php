<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Support;

use App\Modules\Identity\Models\LookupTable;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\OrderSummary\Models\OsChange;
use App\Modules\OrderSummary\Models\OsColumn;
use App\Modules\OrderSummary\Models\OsGroup;
use App\Modules\OrderSummary\Models\OsOrder;
use App\Modules\PreBudget\Support\MerchLists;
use App\Shared\Formula\Formula;
use App\Shared\Formula\FormulaSet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * One company's Order Summary sheet (AnnotechERP 32) as it stands: its
 * groups and columns (ColumnCatalog to start, Column Setup after), their
 * formulas worked out together, what each source offers, and what this user
 * may see and edit. Everything that reads or writes an order row goes
 * through here, so a value is checked, a formula worked out and a change
 * logged the same way from the sheet, Bulk Update, paste and Copy Row.
 */
final class Sheet
{
    public const TIMEZONE = 'Asia/Dhaka';

    /** @var Collection<int, OsColumn> */
    public Collection $columns;

    /** @var Collection<int, OsGroup> */
    public Collection $groups;

    public FormulaSet $formulas;

    /** @var array<int|string, true>|null role ids of the user (null = no user: every column) */
    private ?array $roles = null;

    /** @var array<string, list<array{value: string, label: string}>> */
    private array $options = [];

    public function __construct(public readonly string $companyId, public readonly ?User $user = null)
    {
        ColumnCatalog::ensure($companyId);
        $this->groups = OsGroup::query()->where('company_id', $companyId)->orderBy('sort_order')->get();
        $order = $this->groups->pluck('sort_order', 'id');
        $this->columns = OsColumn::query()->where('company_id', $companyId)->get()
            ->sortBy(fn (OsColumn $c) => [$order[$c->group_id] ?? 0, $c->sort_order])->values();
        $this->formulas = new FormulaSet($this->columns->mapWithKeys(fn (OsColumn $c) => [$c->key => ['formula' => $c->formula, 'type' => $c->type, 'decimals' => $c->decimals, 'reduce' => $c->pair_reduce]])->all());
        if ($user) {
            $this->roles = array_fill_keys(UserRole::query()->where('company_id', $companyId)->where('user_id', $user->id)->pluck('role_id')->map(fn ($id) => (string) $id)->all(), true);
        }
    }

    public static function today(): string
    {
        return now(self::TIMEZONE)->toDateString();
    }

    public function column(string $key): ?OsColumn
    {
        return $this->columns->firstWhere('key', $key);
    }

    /**
     * What this user may do with a column: none, view or edit. A formula or
     * system column is never typed into. Column Setup's role grants decide
     * the rest — with several roles the widest grant wins; a column with no
     * grants is open to everyone.
     */
    public function access(OsColumn $c): string
    {
        $grant = 'edit';
        if ($this->roles !== null && ! empty($c->role_access)) {
            $grant = 'none';
            foreach ($c->role_access as $role => $g) {
                if (isset($this->roles[(string) $role])) {
                    $grant = self::wider($grant, (string) $g);
                }
            }
            // A role the column does not name keeps the default (edit).
            if (array_diff_key($this->roles, $c->role_access) !== []) {
                $grant = 'edit';
            }
        }
        if ($grant === 'edit' && ($this->formulas->isFormula($c->key) || $c->type === 'system')) {
            return 'view';
        }

        return $grant;
    }

    private static function wider(string $a, string $b): string
    {
        $rank = ['none' => 0, 'view' => 1, 'edit' => 2];

        return ($rank[$b] ?? 0) > ($rank[$a] ?? 0) ? $b : $a;
    }

    /** Columns this user sees, in sheet order. @return Collection<int, OsColumn> */
    public function visible(): Collection
    {
        return $this->columns->filter(fn (OsColumn $c) => ! $c->hidden && $this->access($c) !== 'none')->values();
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
            $source === 'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->orderBy('name')->get(['short_name', 'name'])->map(fn ($b) => $b->short_name ?: $b->name),
            $source === 'factories' => DB::table('factories')->where('company_id', $c)->orderBy('code')->pluck('code'),
            $source === 'items' => DB::table('item_types')->where('company_id', $c)->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            $source === 'colours' => DB::table('colours')->where('company_id', $c)->where('active', true)->orderBy('name')->pluck('name'),
            $source === 'fabric_types' => DB::table('fabric_types')->where('company_id', $c)->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            $source === 'compositions' => DB::table('fiber_compositions')->where('company_id', $c)->where('active', true)->orderBy('description')->pluck('description'),
            $source === 'suppliers' => DB::table('mp_suppliers')->where('company_id', $c)->where('active', true)->orderBy('name')->pluck('name'),
            $source === 'merchandisers' => DB::table('users')->whereIn('id', DB::table('os_team_members')->where('company_id', $c)->select('user_id'))->orderBy('name')->pluck('name'),
            str_starts_with($source, 'lookup:') => $this->lookup(substr($source, 7)),
            // The requisition sheet's lists (Order Type, Item, Wash, Fabric…): one list for both sheets.
            str_starts_with($source, 'pb:') => MerchLists::names($c, substr($source, 3)),
            default => collect(),
        };

        return $this->options[$source] = collect($list)->map(fn ($v) => trim((string) $v))->filter()->unique()->values()
            ->map(fn (string $v) => ['value' => $v, 'label' => $v])->all();
    }

    /** @return Collection<int, string> */
    private function lookup(string $code): Collection
    {
        $table = LookupTable::query()->visible()->where('code', $code)->orderByRaw('company_id is null')->first();

        return $table ? $table->values()->visible()->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name') : collect();
    }

    /* ── Values ─────────────────────────────────────────────────────── */

    /**
     * One typed value as the column keeps it; a ValidationException names the
     * field `values.<key>` (or `rows.<n>.<key>` through $field) when it is not.
     */
    public function normalize(OsColumn $c, mixed $raw, string $field): mixed
    {
        if (is_string($raw)) {
            $raw = trim($raw);
        }
        if (Formula::blank($raw)) {
            return null;
        }
        $fail = fn (string $msg) => throw ValidationException::withMessages([$field => __("ordersummary.v.{$msg}", ['column' => $c->label])]);
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
                $s = is_string($raw) ? $raw : '';
                if (Formula::day($s) === null) {
                    $fail('date');
                }

                return substr($s, 0, 10);
            case 'dropdown':
                return $this->pick($c, (string) $raw, $fail);
            case 'multi':
                // A list column set to one value (Style, JOB No — user, 2026-11-23) keeps its list form, one entry only.
                $list = is_array($raw) ? $raw : ($c->multiple ? preg_split('/\s*[,\n]\s*/', (string) $raw) : [(string) $raw]);
                if (! $c->multiple && count(array_filter($list, fn ($v) => trim((string) $v) !== '')) > 1) {
                    $fail('one_value');
                }
                $out = [];
                foreach ($list as $v) {
                    $v = trim((string) $v);
                    if ($v !== '') {
                        $out[] = $c->source ? $this->pick($c, $v, $fail) : mb_substr($v, 0, 120);
                    }
                }

                return array_values(array_unique($out)) ?: null;
            case 'pairs':
                return self::pairs($c, $raw, $fail);
            case 'image':
                return is_string($raw) && strlen($raw) === 26 ? $raw : $fail('image');
            case 'system':
                return null;
            case 'longtext':
                return mb_substr(is_array($raw) ? implode(', ', $raw) : (string) $raw, 0, 5000);
            default:
                return mb_substr(is_array($raw) ? implode(', ', $raw) : (string) $raw, 0, 500);
        }
    }

    /** A dropdown value as its source spells it (a value no longer offered must already be there). */
    /**
     * A pairs cell: lines of a name and a number — from the sheet's editor ([{label, value}])
     * or pasted text ("Navy: 60, White: 40" / "BIO $2.48"). Numbers rounded to the column's
     * places; the same name twice adds up on a total column (as Norban's colours did); an empty
     * line is dropped.
     *
     * @return list<array{label: string, value: float|int|null}>|null
     */
    public static function pairs(OsColumn $c, mixed $raw, callable $fail): ?array
    {
        if (! is_array($raw)) {
            $raw = array_map(function (string $part): array {
                // "Navy: 60", "Navy 60", "BIO $2.48", "$2.48"
                if (preg_match('/^(.*?)[\s:=–-]*\$?\s*(-?[\d,]*\.?\d+)\s*[A-Za-z]*$/u', $part, $m)) {
                    return ['label' => preg_replace('/[\s:=–-]+$/u', '', $m[1]), 'value' => str_replace(',', '', $m[2])];
                }

                return ['label' => $part, 'value' => null];
            }, array_filter(array_map('trim', preg_split('/[\n;]|,(?!\d{3}\b)/u', (string) $raw))));
        }
        $places = (int) ($c->decimals ?? 2);
        $out = [];
        foreach (array_slice($raw, 0, 60) as $line) {
            $label = mb_substr(trim((string) (is_array($line) ? ($line['label'] ?? '') : $line)), 0, 120);
            $value = is_array($line) ? ($line['value'] ?? null) : null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $n = null;
            if ($value !== null && $value !== '') {
                $n = Formula::num($value);
                if ($n === null || abs($n) > 1e12) {
                    $fail('number');
                }
                $n = Formula::round($n, $places);
            }
            if ($label === '' && $n === null) {
                continue;
            }
            $at = $c->pair_reduce === 'FIRST' || $label === '' ? null : array_search(mb_strtolower($label), array_map(fn ($o) => mb_strtolower($o['label']), $out), true);
            if ($at !== null && $at !== false) {
                $out[$at]['value'] = $out[$at]['value'] === null && $n === null ? null : Formula::round(($out[$at]['value'] ?? 0) + ($n ?? 0), $places);

                continue;
            }
            $out[] = ['label' => $label, 'value' => $n];
        }

        return $out ?: null;
    }

    private function pick(OsColumn $c, string $v, callable $fail): string
    {
        if (! $c->source) {
            return mb_substr($v, 0, 255);
        }
        foreach ($this->options($c->source) as $o) {
            if (mb_strtolower($o['value']) === mb_strtolower($v)) {
                return $o['value'];
            }
        }
        // A shared list also takes its value spelled with other spaces, or by an approved old spelling ("No Wash" → "No").
        if (str_starts_with($c->source, 'pb:')) {
            $hit = MerchLists::find(substr($c->source, 3), $v, array_column($this->options($c->source), 'value'));
            if ($hit !== null) {
                return $hit;
            }
        }

        $fail('option');
    }

    /**
     * Put typed values on a row: only columns this user may edit, each value
     * checked, then the formulas worked out. Returns the new values and the
     * cells that changed (column → [old, new]).
     *
     * @param  array<string, mixed>  $values  current values
     * @param  array<string, mixed>  $input   key → typed value
     * @return array{0: array<string, mixed>, 1: array<string, array{0: mixed, 1: mixed}>}
     */
    public function put(array $values, array $input, string $fieldPrefix): array
    {
        $next = $values;
        foreach ($input as $key => $raw) {
            $c = $this->column((string) $key);
            // A formula or system column is worked out, never typed: a value sent for it
            // (a pasted Excel row carries them) is dropped, not refused.
            if ($c === null || $c->hidden || $c->type === 'system' || $this->formulas->isFormula($c->key)) {
                continue;
            }
            if ($this->access($c) !== 'edit') {
                if (! $this->same($values[$key] ?? null, $raw)) {
                    throw ValidationException::withMessages(["{$fieldPrefix}.{$key}" => __('ordersummary.v.read_only', ['column' => $c->label])]);
                }

                continue;
            }
            $next[$key] = $this->normalize($c, $raw, "{$fieldPrefix}.{$key}");
        }
        $next = $this->formulas->apply($next, self::today());
        $next = array_filter($next, fn ($v) => ! Formula::blank($v));

        return [$next, $this->diff($values, $next)];
    }

    /** @return array<string, array{0: mixed, 1: mixed}> */
    public function diff(array $old, array $new): array
    {
        $out = [];
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            if (! $this->same($old[$key] ?? null, $new[$key] ?? null)) {
                $out[$key] = [$old[$key] ?? null, $new[$key] ?? null];
            }
        }

        return $out;
    }

    private function same(mixed $a, mixed $b): bool
    {
        if (Formula::blank($a) || Formula::blank($b)) {
            return Formula::blank($a) && Formula::blank($b);
        }
        $x = is_array($a) ? null : Formula::num($a);
        $y = is_array($b) ? null : Formula::num($b);
        if ($x !== null && $y !== null) {
            return abs($x - $y) < 1e-9;
        }

        return Formula::text($a) === Formula::text($b);
    }

    /** The buyer an order row belongs to, from its Buyer column. */
    public function buyerId(array $values): ?string
    {
        $name = $values['buyer'] ?? null;
        if (Formula::blank($name)) {
            return null;
        }

        return Buyer::query()->where(fn ($q) => $q->where('short_name', $name)->orWhere('name', $name))->orderBy('created_at')->value('id');
    }

    /** One row as the sheet shows it: stored values plus the day-dependent ones. */
    public function read(OsOrder $o): array
    {
        $values = $this->formulas->apply($o->values ?? [], self::today(), true);
        $values['serial_no'] = $o->serial_no;
        $values['last_update'] = $o->updated_at?->setTimezone(self::TIMEZONE)->toDateString();
        // Budget Status, when the query worked it out (the list, Excel); never stored on the row.
        if (array_key_exists(OrderStatus::KEY, $o->getAttributes())) {
            $values[OrderStatus::KEY] = $o->getAttribute(OrderStatus::KEY);
        }

        return array_filter($values, fn ($v) => ! Formula::blank($v));
    }

    /** Log a row's changed cells under one batch. @param  array<string, array{0: mixed, 1: mixed}>  $diff */
    public function log(OsOrder $o, string $kind, array $diff, string $batch, ?string $by): void
    {
        $at = now();
        $rows = [];
        foreach ($diff as $key => [$old, $new]) {
            $rows[] = [
                'id' => (string) Str::ulid(), 'company_id' => $this->companyId, 'order_id' => $o->id, 'buyer_id' => $o->buyer_id, 'kind' => $kind,
                'column_key' => $key, 'old_value' => Formula::blank($old) ? null : Formula::text($old), 'new_value' => Formula::blank($new) ? null : Formula::text($new),
                'batch' => $batch, 'changed_by' => $by, 'changed_at' => $at, 'mailed_at' => null,
            ];
        }
        if ($diff === []) {
            $rows[] = ['id' => (string) Str::ulid(), 'company_id' => $this->companyId, 'order_id' => $o->id, 'buyer_id' => $o->buyer_id, 'kind' => $kind,
                'column_key' => null, 'old_value' => null, 'new_value' => null, 'batch' => $batch, 'changed_by' => $by, 'changed_at' => $at, 'mailed_at' => null];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            OsChange::query()->insert($chunk);
        }
        \App\Modules\Erp\Support\SheetNotices::note('os', $batch); // told to the team once the save is done (user, 2026-11-21)
    }

    /**
     * Work every row's formulas out again (after a formula changed). Rows whose
     * stored values change are saved and the cells logged as RECALC.
     */
    public function recalculateAll(?string $by): int
    {
        $changed = 0;
        $batch = (string) Str::ulid();
        $today = self::today();
        OsOrder::query()->where('company_id', $this->companyId)->orderBy('id')->chunkById(200, function ($orders) use (&$changed, $batch, $by, $today): void {
            foreach ($orders as $o) {
                $next = array_filter($this->formulas->apply($o->values ?? [], $today), fn ($v) => ! Formula::blank($v));
                $diff = $this->diff($o->values ?? [], $next);
                if ($diff !== []) {
                    $o->forceFill(['values' => $next])->saveQuietly();
                    $this->log($o, 'RECALC', $diff, $batch, $by);
                    $changed++;
                }
            }
        });

        return $changed;
    }
}
