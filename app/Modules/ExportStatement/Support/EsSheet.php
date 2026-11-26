<?php

declare(strict_types=1);

namespace App\Modules\ExportStatement\Support;

use App\Modules\Identity\Models\LookupTable;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\ExportStatement\Models\EsChange;
use App\Modules\ExportStatement\Models\EsColumn;
use App\Modules\ExportStatement\Models\EsGroup;
use App\Modules\ExportStatement\Models\ExportStatement;
use App\Shared\Formula\Formula;
use App\Shared\Formula\FormulaSet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * One company's Export Statement sheet (AnnotechERP 35, 2026-11-21) as it
 * stands: groups and columns (EsCatalog to start, Column Setup after), their
 * formulas worked out together, what each source offers (the companies are
 * the factories, the buyers Merchandise Desk's, the banks Trade & Export's)
 * and what this user may see and edit. Every read and write of a statement's
 * cells goes through here — Order Summary's sheet rules on export invoices.
 */
final class EsSheet
{
    public const TIMEZONE = 'Asia/Dhaka';

    /** A statement's status as the Status column shows it. */
    public const STATUS_LABELS = ['CREATED' => 'Created', 'PENDING_APPROVAL' => 'Pending Approval', 'APPROVED' => 'Approved'];

    /** @var Collection<int, EsColumn> */
    public Collection $columns;

    /** @var Collection<int, EsGroup> */
    public Collection $groups;

    public FormulaSet $formulas;

    /** @var array<int|string, true>|null */
    private ?array $roles = null;

    /** @var array<string, list<array{value: string, label: string}>> */
    private array $options = [];

    public function __construct(public readonly string $companyId, public readonly ?User $user = null)
    {
        EsCatalog::ensure($companyId);
        $this->groups = EsGroup::query()->where('company_id', $companyId)->orderBy('sort_order')->get();
        $order = $this->groups->pluck('sort_order', 'id');
        $this->columns = EsColumn::query()->where('company_id', $companyId)->get()
            ->sortBy(fn (EsColumn $c) => [$order[$c->group_id] ?? 0, $c->sort_order])->values();
        $this->formulas = new FormulaSet($this->columns->mapWithKeys(fn (EsColumn $c) => [$c->key => ['formula' => $c->formula, 'type' => $c->type, 'decimals' => $c->decimals, 'reduce' => $c->pair_reduce]])->all());
        if ($user) {
            $this->roles = array_fill_keys(UserRole::query()->where('company_id', $companyId)->where('user_id', $user->id)->pluck('role_id')->map(fn ($id) => (string) $id)->all(), true);
        }
    }

    public static function today(): string
    {
        return now(self::TIMEZONE)->toDateString();
    }

    public function column(string $key): ?EsColumn
    {
        return $this->columns->firstWhere('key', $key);
    }

    /** none, view or edit — as Order Summary: role grants in Column Setup, formula and system columns read-only. */
    public function access(EsColumn $c): string
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

    /** @return Collection<int, EsColumn> */
    public function visible(): Collection
    {
        return $this->columns->filter(fn (EsColumn $c) => ! $c->hidden && $this->access($c) !== 'none')->values();
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
            str_starts_with($source, 'es:') => collect(EsOptions::list(substr($source, 3))),
            $source === 'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->orderBy('name')->get(['short_name', 'name'])->map(fn ($b) => $b->short_name ?: $b->name),
            // Norban's Company Name: the factory, by its short code (NCL, HAL …).
            $source === 'factories' => DB::table('factories')->where('company_id', $c)->where('status', '!=', 'INACTIVE')->orderBy('code')->pluck('code'),
            $source === 'banks' => DB::table('tx_banks')->where('company_id', $c)->where('active', true)->orderBy('name')->pluck('name'),
            str_starts_with($source, 'lookup:') => $this->lookup(substr($source, 7)),
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

    public function normalize(EsColumn $c, mixed $raw, string $field): mixed
    {
        if (is_string($raw)) {
            $raw = trim($raw);
        }
        if (Formula::blank($raw)) {
            return null;
        }
        $fail = fn (string $msg) => throw ValidationException::withMessages([$field => __("exportstatement.v.{$msg}", ['column' => $c->label])]);
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
            case 'pairs':
                return $this->pairs($c, $raw, $fail);
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
     * A pairs cell: lines of a name and a number — from the sheet's editor ([{label, value}])
     * or pasted text ("Navy: 60, White: 40" / "BIO $2.48"). Numbers rounded to the column's
     * places; the same name twice adds up on a total column (as Norban's colours did); an empty
     * line is dropped.
     *
     * @return list<array{label: string, value: float|int|null}>|null
     */
    private function pairs(EsColumn $c, mixed $raw, callable $fail): ?array
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

    private function pick(EsColumn $c, string $v, callable $fail): string
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
     * checked, then the formulas worked out.
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
                    throw ValidationException::withMessages(["{$fieldPrefix}.{$key}" => __('exportstatement.v.read_only', ['column' => $c->label])]);
                }

                continue;
            }
            $next[$key] = $this->normalize($c, $raw, "{$fieldPrefix}.{$key}");
        }
        $next = $this->formulas->apply($next, self::today());
        $next = array_filter($next, fn ($v) => ! Formula::blank($v));

        return [$next, $this->diff($values, $next)];
    }

    /**
     * The required columns a statement still lacks (checked on every save).
     *
     * @return list<string> column labels
     */
    public function missing(array $values): array
    {
        $out = [];
        foreach ($this->columns as $c) {
            if ($c->required && ! $c->hidden && $c->type !== 'system' && Formula::blank($values[$c->key] ?? null)) {
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
        $name = $values['buyer_name'] ?? null;
        if (Formula::blank($name)) {
            return null;
        }

        return Buyer::query()->where(fn ($q) => $q->where('short_name', $name)->orWhere('name', $name))->orderBy('created_at')->value('id');
    }

    /** One statement as the sheet shows it: stored values plus the day-dependent ones, the serial and its status. */
    public function read(ExportStatement $r): array
    {
        $values = $this->formulas->apply($r->values ?? [], self::today(), true);
        $values['serial_no'] = $r->serial_no;
        $values['status'] = self::STATUS_LABELS[$r->status] ?? $r->status;

        return array_filter($values, fn ($v) => ! Formula::blank($v));
    }

    /** @param  array<string, array{0: mixed, 1: mixed}>  $diff */
    public function log(ExportStatement $r, string $kind, array $diff, string $batch, ?string $by, bool $tell = true): void
    {
        $at = now();
        $base = ['company_id' => $this->companyId, 'statement_id' => $r->id, 'buyer_id' => $r->buyer_id, 'kind' => $kind, 'batch' => $batch, 'changed_by' => $by, 'changed_at' => $at];
        $rows = [];
        foreach ($diff as $key => [$old, $new]) {
            $rows[] = ['id' => (string) Str::ulid(), ...$base, 'column_key' => $key,
                'old_value' => Formula::blank($old) ? null : Formula::text($old), 'new_value' => Formula::blank($new) ? null : Formula::text($new)];
        }
        if ($diff === []) {
            $rows[] = ['id' => (string) Str::ulid(), ...$base, 'column_key' => null, 'old_value' => null, 'new_value' => null];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            EsChange::query()->insert($chunk);
        }
        if ($tell) {
            \App\Modules\Erp\Support\SheetNotices::note('es', $batch); // told to the team once the save is done (user, 2026-11-22)
        }
    }

    /** Work every statement's formulas out again (after a formula changed); changed rows are saved and logged as RECALC. */
    public function recalculateAll(?string $by): int
    {
        $changed = 0;
        $batch = (string) Str::ulid();
        $today = self::today();
        ExportStatement::query()->where('company_id', $this->companyId)->orderBy('id')->chunkById(200, function ($rows) use (&$changed, $batch, $by, $today): void {
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
