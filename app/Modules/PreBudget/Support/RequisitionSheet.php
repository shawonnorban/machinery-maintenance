<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Support;

use App\Modules\Identity\Models\LookupTable;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\PreBudget\Models\PbChange;
use App\Modules\PreBudget\Models\PbColumn;
use App\Modules\PreBudget\Models\PbGroup;
use App\Modules\PreBudget\Models\PbRequisition;
use App\Shared\Formula\Formula;
use App\Shared\Formula\FormulaSet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * One company's requisition sheet (Pre-Budget, AnnotechERP 33/34) as it
 * stands: groups and columns (RequisitionCatalog to start, Column Setup
 * after), their formulas worked out together, what each source offers, which
 * columns a Knit or a Woven row has, and what this user may see and edit.
 * Every read and write of a requisition goes through here — the same way as
 * Order Summary's sheet, whose rules it follows.
 */
final class RequisitionSheet
{
    public const TIMEZONE = 'Asia/Dhaka';

    /** @var Collection<int, PbColumn> */
    public Collection $columns;

    /** @var Collection<int, PbGroup> */
    public Collection $groups;

    public FormulaSet $formulas;

    /** @var array<int|string, true>|null */
    private ?array $roles = null;

    /** @var array<string, list<array{value: string, label: string}>> */
    private array $options = [];

    public function __construct(public readonly string $companyId, public readonly ?User $user = null)
    {
        RequisitionCatalog::ensure($companyId);
        $this->groups = PbGroup::query()->where('company_id', $companyId)->where('sheet', 'REQ')->orderBy('sort_order')->get();
        $order = $this->groups->pluck('sort_order', 'id');
        $this->columns = PbColumn::query()->where('company_id', $companyId)->where('sheet', 'REQ')->get()
            ->sortBy(fn (PbColumn $c) => [$order[$c->group_id] ?? 0, $c->sort_order])->values();
        $this->formulas = new FormulaSet($this->columns->mapWithKeys(fn (PbColumn $c) => [$c->key => ['formula' => $c->formula, 'type' => $c->type, 'decimals' => $c->decimals]])->all());
        if ($user) {
            $this->roles = array_fill_keys(UserRole::query()->where('company_id', $companyId)->where('user_id', $user->id)->pluck('role_id')->map(fn ($id) => (string) $id)->all(), true);
        }
    }

    public static function today(): string
    {
        return now(self::TIMEZONE)->toDateString();
    }

    public function column(string $key): ?PbColumn
    {
        return $this->columns->firstWhere('key', $key);
    }

    /** Whether a column belongs to a Knit / Woven row (a column for both, or with no type yet, always does). */
    public static function applies(PbColumn $c, ?string $entryType): bool
    {
        return $c->applies_to === null || $entryType === null || $c->applies_to === $entryType;
    }

    /** The stored Entry Type (KNIT / WOVEN) a row's values name, or null. */
    public static function entryType(array $values): ?string
    {
        $v = $values['entry_type'] ?? null;

        return is_string($v) ? (RequisitionCatalog::ENTRY_TYPES[$v] ?? null) : null;
    }

    /** none, view or edit — as Order Summary: role grants in Column Setup, formula and system columns read-only. */
    public function access(PbColumn $c): string
    {
        $grant = 'edit';
        if ($this->roles !== null && ! empty($c->role_access)) {
            $grant = 'none';
            foreach ($c->role_access as $role => $g) {
                if (isset($this->roles[(string) $role])) {
                    $grant = self::wider($grant, (string) $g);
                }
            }
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

    /** @return Collection<int, PbColumn> */
    public function visible(): Collection
    {
        return $this->columns->filter(fn (PbColumn $c) => ! $c->hidden && $this->access($c) !== 'none')->values();
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
            $source === 'entry_types' => collect(array_keys(RequisitionCatalog::ENTRY_TYPES)),
            $source === 'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->orderBy('name')->get(['short_name', 'name'])->map(fn ($b) => $b->short_name ?: $b->name),
            $source === 'factories' => DB::table('factories')->where('company_id', $c)->orderBy('code')->pluck('code'),
            $source === 'items' => DB::table('item_types')->where('company_id', $c)->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            $source === 'fabric_types' => DB::table('fabric_types')->where('company_id', $c)->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            $source === 'compositions' => DB::table('fiber_compositions')->where('company_id', $c)->where('active', true)->orderBy('description')->pluck('description'),
            $source === 'suppliers' => DB::table('mp_suppliers')->where('company_id', $c)->where('active', true)->orderBy('name')->pluck('name'),
            $source === 'garment_parts' => DB::table('garment_parts')->where('company_id', $c)->where('active', true)->orderBy('name')->pluck('name'),
            $source === 'colours' => DB::table('colours')->where('company_id', $c)->where('active', true)->orderBy('name')->pluck('name'),
            // The Size master in its own order (S, M, L … / 28, 30 … / 2-3Y …): an Order Color line's sizes.
            $source === 'sizes' => self::sizeOrder(DB::table('sizes')->where('company_id', $c)->where('active', true)->get(['name', 'size_group', 'display_order'])),
            $source === 'yarn_counts' => DB::table('knit_yarn_counts')->where('company_id', $c)->where('active', true)->orderBy('display_order')->orderBy('count_no')->pluck('description'),
            str_starts_with($source, 'pb:') => DB::table('pb_list_values')->where('company_id', $c)->where('list', substr($source, 3))->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            str_starts_with($source, 'lookup:') => $this->lookup(substr($source, 7)),
            default => collect(),
        };

        return $this->options[$source] = collect($list)->map(fn ($v) => trim((string) $v))->filter()->unique()->values()
            ->map(fn (string $v) => ['value' => $v, 'label' => $v])->all();
    }

    /**
     * The Size master in garment order, group by group: letter sizes as they
     * are worn (XXS, XS, S, M, L, XL, XXL/2XL, 3XL …), numbers and ages
     * (28, 30 … / 2-3Y, 4-5Y …) by their number, any other by its display
     * order — so a range reads S – XL even when the master's own order is
     * not set.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, string>
     */
    public static function sizeOrder(Collection $rows): Collection
    {
        $letters = ['XXXS' => 1, '3XS' => 1, 'XXS' => 2, '2XS' => 2, 'XS' => 3, 'S' => 4, 'M' => 5, 'L' => 6, 'XL' => 7, 'XXL' => 8, '2XL' => 8, 'XXXL' => 9, '3XL' => 9, '4XL' => 10, '5XL' => 11, '6XL' => 12];
        $rank = function (object $s) use ($letters): array {
            $name = strtoupper(trim((string) $s->name));
            if (isset($letters[$name])) {
                return [0, $letters[$name]];
            }
            if (preg_match('/^(\d+(?:\.\d+)?)/', $name, $m)) {
                return [1, (float) $m[1]];
            }

            return [2, (int) $s->display_order];
        };

        // Letter sizes first, then numbers, then ages, then any other group.
        $group = fn (object $s) => ['LETTER' => '0', 'NUMERIC' => '1', 'AGE' => '2'][strtoupper((string) $s->size_group)] ?? '3'.$s->size_group;

        return $rows->sort(function ($a, $b) use ($rank, $group) {
            return [$group($a), ...$rank($a), (string) $a->name] <=> [$group($b), ...$rank($b), (string) $b->name];
        })->pluck('name')->values();
    }

    /** @return Collection<int, string> */
    private function lookup(string $code): Collection
    {
        $table = LookupTable::query()->visible()->where('code', $code)->orderByRaw('company_id is null')->first();

        return $table ? $table->values()->visible()->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name') : collect();
    }

    /* ── Values ─────────────────────────────────────────────────────── */

    public function normalize(PbColumn $c, mixed $raw, string $field): mixed
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
                $s = is_string($raw) ? $raw : '';
                if (Formula::day($s) === null) {
                    $fail('date');
                }

                return substr($s, 0, 10);
            case 'dropdown':
                return $this->pick($c, (string) $raw, $fail);
            case 'multi':
                $list = is_array($raw) ? $raw : preg_split('/\s*[,\n]\s*/', (string) $raw);
                $out = [];
                foreach ($list as $v) {
                    $v = trim((string) $v);
                    if ($v !== '') {
                        $out[] = $c->source ? $this->pick($c, $v, $fail) : mb_substr($v, 0, 120);
                    }
                }

                return array_values(array_unique($out)) ?: null;
            case 'colorsize':
                return $this->colorSizes($c, $raw, $fail, $field);
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

    /**
     * An Order Color cell (user, 2026-11-22): lines of a colour and its sizes —
     * [{color, sizes: []}] from the sheet's editor, or text as copied
     * ("Navy (S, M, L); White"). The colour is needed on every line (the
     * Colour master's spelling when it has it); the sizes are optional, each
     * one from the Size master, kept in its order.
     *
     * @return list<array{color: string, sizes: list<string>}>|null
     */
    private function colorSizes(PbColumn $c, mixed $raw, callable $fail, string $field): ?array
    {
        $lines = is_array($raw) ? $raw : self::parseColorSizes((string) $raw);
        $colours = [];
        foreach ($c->source ? $this->options($c->source) : [] as $o) {
            $colours[mb_strtolower($o['value'])] = $o['value'];
        }
        $order = array_flip(array_map(fn ($o) => mb_strtolower($o['value']), $this->options('sizes')));
        $sizes = array_column($this->options('sizes'), 'value', null);
        $out = [];
        foreach (array_slice($lines, 0, 40) as $line) {
            $color = trim((string) (is_array($line) ? ($line['color'] ?? '') : $line));
            $picked = is_array($line) ? (array) ($line['sizes'] ?? []) : [];
            $picked = array_values(array_filter(array_map(fn ($s) => trim((string) $s), $picked), fn ($s) => $s !== ''));
            if ($color === '' && $picked === []) {
                continue;
            }
            if ($color === '') {
                $fail('colour_needed');
            }
            $color = $colours[mb_strtolower($color)] ?? mb_substr($color, 0, 120);
            $kept = [];
            foreach ($picked as $s) {
                $at = $order[mb_strtolower($s)] ?? null;
                if ($at === null) {
                    throw ValidationException::withMessages([$field => __('prebudget.v.size_unknown', ['size' => $s])]);
                }
                $kept[$at] = $sizes[$at];
            }
            ksort($kept);
            $out[] = ['color' => $color, 'sizes' => array_values(array_unique($kept))];
        }

        return $out ?: null;
    }

    /** "Navy (S, M, L); White; Black (XL)" → lines of a colour and its sizes. @return list<array{color: string, sizes: list<string>}> */
    public static function parseColorSizes(string $text): array
    {
        $out = [];
        foreach (preg_split('/\s*(?:[;\n]|,(?![^(]*\)))\s*/u', trim($text)) as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(.*?)\s*\((.*)\)\s*$/u', $part, $m)) {
                $out[] = ['color' => trim($m[1]), 'sizes' => array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $m[2]))))];
            } else {
                $out[] = ['color' => trim($part), 'sizes' => []];
            }
        }

        return $out;
    }

    /**
     * The sizes of an Order Color cell as a range in the Size master's order:
     * "S – XL", one size alone, "—" with none.
     *
     * @param  mixed  $value
     */
    public function sizeRange(mixed $value): ?string
    {
        $order = array_flip(array_map(fn ($o) => $o['value'], $this->options('sizes')));
        $all = [];
        foreach (is_array($value) ? $value : [] as $line) {
            foreach ((array) ($line['sizes'] ?? []) as $s) {
                $all[$order[$s] ?? PHP_INT_MAX] = $s;
            }
        }
        if ($all === []) {
            return null;
        }
        ksort($all);
        $first = reset($all);
        $last = end($all);

        return $first === $last ? $first : "{$first} – {$last}";
    }

    /** An Order Color cell's colours, in order. @return list<string> */
    public static function colorsOf(mixed $value): array
    {
        if (! is_array($value)) {
            return Formula::blank($value) ? [] : [(string) $value];
        }

        return array_values(array_filter(array_map(fn ($l) => is_array($l) ? trim((string) ($l['color'] ?? '')) : trim((string) $l), $value)));
    }

    private function pick(PbColumn $c, string $v, callable $fail): string
    {
        if (! $c->source) {
            return mb_substr($v, 0, 255);
        }
        foreach ($this->options($c->source) as $o) {
            if (mb_strtolower($o['value']) === mb_strtolower($v)) {
                return $o['value'];
            }
        }

        $fail('option');
    }

    /**
     * Put typed values on a row: only columns this user may edit, each value
     * checked, then the formulas worked out. A column the row's Entry Type
     * does not have is emptied (a Woven row keeps no Yarn Count).
     *
     * @return array{0: array<string, mixed>, 1: array<string, array{0: mixed, 1: mixed}>}
     */
    public function put(array $values, array $input, string $fieldPrefix): array
    {
        $next = $values;
        foreach ($input as $key => $raw) {
            $c = $this->column((string) $key);
            if ($c === null || $c->hidden || $c->type === 'system' || $this->formulas->isFormula($c->key)) {
                continue;
            }
            if ($this->access($c) !== 'edit') {
                if (! $this->same($values[$key] ?? null, $raw)) {
                    throw ValidationException::withMessages(["{$fieldPrefix}.{$key}" => __('prebudget.v.read_only', ['column' => $c->label])]);
                }

                continue;
            }
            $next[$key] = $this->normalize($c, $raw, "{$fieldPrefix}.{$key}");
        }
        $type = self::entryType($next);
        foreach ($this->columns as $c) {
            if (! self::applies($c, $type)) {
                unset($next[$c->key]);
            }
        }
        $next = $this->formulas->apply($next, self::today());
        $next = array_filter($next, fn ($v) => ! Formula::blank($v));

        return [$next, $this->diff($values, $next)];
    }

    /**
     * The required columns a row still lacks for its Entry Type (checked on
     * Complete; a draft may be half done).
     *
     * @return list<string> column labels
     */
    public function missing(array $values): array
    {
        $type = self::entryType($values);
        $out = [];
        foreach ($this->columns as $c) {
            if ($c->required && ! $c->hidden && self::applies($c, $type) && Formula::blank($values[$c->key] ?? null)) {
                $out[] = $c->label;
            }
        }

        return $out;
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

    public function buyerId(array $values): ?string
    {
        $name = $values['buyer'] ?? null;
        if (Formula::blank($name)) {
            return null;
        }

        return Buyer::query()->where(fn ($q) => $q->where('short_name', $name)->orWhere('name', $name))->orderBy('created_at')->value('id');
    }

    /** One row as the sheet shows it: stored values plus the day-dependent ones, the serial and entry date. */
    public function read(PbRequisition $r): array
    {
        $values = $this->formulas->apply($r->values ?? [], self::today(), true);
        $values['serial_no'] = $r->serial_no;
        $values['entry_date'] = $r->created_at?->setTimezone(self::TIMEZONE)->toDateString();

        return array_filter($values, fn ($v) => ! Formula::blank($v));
    }

    /** @param  array<string, array{0: mixed, 1: mixed}>  $diff */
    public function log(PbRequisition $r, string $kind, array $diff, string $batch, ?string $by): void
    {
        $at = now();
        $base = ['company_id' => $this->companyId, 'subject' => 'REQUISITION', 'subject_id' => $r->id, 'buyer_id' => $r->buyer_id, 'kind' => $kind, 'batch' => $batch, 'changed_by' => $by, 'changed_at' => $at];
        $rows = [];
        foreach ($diff as $key => [$old, $new]) {
            $rows[] = ['id' => (string) Str::ulid(), ...$base, 'column_key' => $key,
                'old_value' => Formula::blank($old) ? null : Formula::text($old), 'new_value' => Formula::blank($new) ? null : Formula::text($new)];
        }
        if ($diff === []) {
            $rows[] = ['id' => (string) Str::ulid(), ...$base, 'column_key' => null, 'old_value' => null, 'new_value' => null];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            PbChange::query()->insert($chunk);
        }
        \App\Modules\Erp\Support\SheetNotices::note('pb', $batch); // told to the team once the save is done (user, 2026-11-21)
    }

    /** Work every requisition's formulas out again (after a formula changed); changed rows are saved and logged as RECALC. */
    public function recalculateAll(?string $by): int
    {
        $changed = 0;
        $batch = (string) Str::ulid();
        $today = self::today();
        PbRequisition::query()->where('company_id', $this->companyId)->orderBy('id')->chunkById(200, function ($rows) use (&$changed, $batch, $by, $today): void {
            foreach ($rows as $r) {
                $next = array_filter($this->formulas->apply($r->values ?? [], $today), fn ($v) => ! Formula::blank($v));
                $diff = $this->diff($r->values ?? [], $next);
                if ($diff !== []) {
                    $r->forceFill(['values' => $next])->saveQuietly();
                    $this->log($r, 'RECALC', $diff, $batch, $by);
                    $changed++;
                }
            }
        });

        return $changed;
    }
}
