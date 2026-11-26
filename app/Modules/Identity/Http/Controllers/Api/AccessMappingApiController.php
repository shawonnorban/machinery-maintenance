<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Modules\Identity\Models\AccessMapping;
use App\Modules\Identity\Support\MappingRegistry;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Access Control → Mappings (AnnotechERP 02/02), one API for every kind in
 * `MappingRegistry`: the Records grid, the per-subject matrix (Brickland's
 * "User & X Mapping" tab), a single new row, its edit and its removal — the
 * user mappings and the buyer mappings alike.
 */
class AccessMappingApiController extends ApiController
{
    public function __construct(private readonly MappingRegistry $registry) {}

    /** Records: every row of the kind, with subject and target by name. */
    public function index(string $kind): JsonResponse
    {
        $definition = $this->definition($kind);
        $subjects = $this->registry->subjects($kind);
        $targets = $this->registry->targets($kind);

        $rows = AccessMapping::query()->where('kind', $kind)->get()
            ->map(fn (AccessMapping $row): array => $this->row($row, $definition, $subjects, $targets))
            ->sortBy([['subject', 'asc'], ['sort_order', 'asc'], ['target', 'asc']])
            ->values();

        return ApiResponse::ok($rows->all());
    }

    /** The choices a form offers: subjects, targets and the kind's shape. */
    public function options(string $kind): JsonResponse
    {
        $definition = $this->definition($kind);

        $keys = $this->registry->subjectKeys($kind);

        return ApiResponse::ok([
            'flags' => array_keys($definition['flags']),
            'flag_defaults' => $definition['flags'],
            'extras' => $definition['extras'],
            'required' => $definition['required'] ?? [],
            'amount_range' => $definition['amount_range'] ?? null,
            // A buyer or a store is addressed in the URL by its code, never its key.
            'subjects' => collect($this->pairs($this->registry->subjects($kind)))->map(fn (array $p): array => $p + (isset($keys[$p['value']]) ? ['key' => $keys[$p['value']]] : []))->all(),
            'targets' => $this->pairs($this->registry->targets($kind)),
        ]);
    }

    /** One subject's matrix: every target, with what the subject holds on it today. */
    public function matrix(string $kind, string $subject): JsonResponse
    {
        $definition = $this->definition($kind);
        $this->assertSubject($kind, $subject);

        $held = AccessMapping::query()->where('kind', $kind)->where('subject_id', $subject)->get()->keyBy('target_id');

        return ApiResponse::ok(collect($this->registry->targets($kind))->map(function (string $label, string $target) use ($held, $definition): array {
            $row = $held->get($target);

            return [
                'target_id' => (string) $target,
                'target' => $label,
                'selected' => $row !== null,
                'flags' => collect($definition['flags'])->mapWithKeys(fn (bool $default, string $flag): array => [$flag => (bool) ($row?->flags[$flag] ?? false)])->all(),
                'amount' => $row?->amount,
                'text' => $row?->text,
                'date_from' => $row?->date_from?->toDateString(),
                'date_to' => $row?->date_to?->toDateString(),
                'sort_order' => $row?->sort_order ?? 0,
                'is_default' => (bool) $row?->is_default,
            ];
        })->values()->all());
    }

    /** Saves one subject's matrix as a whole; a target with nothing on it is no row. */
    public function saveMatrix(Request $request, string $kind, string $subject): JsonResponse
    {
        $definition = $this->definition($kind);
        $this->assertSubject($kind, $subject);
        $targets = $this->registry->targets($kind);

        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.target_id' => ['required', 'string', Rule::in(array_map('strval', array_keys($targets)))],
            'rows.*.selected' => ['sometimes', 'boolean'],
            'rows.*.flags' => ['sometimes', 'array'],
            'rows.*.amount' => ['nullable', 'numeric', 'min:0'],
            'rows.*.text' => ['nullable', 'string', 'max:120'],
            'rows.*.date_from' => ['nullable', 'date'],
            'rows.*.date_to' => ['nullable', 'date'],
            'rows.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'rows.*.is_default' => ['sometimes', 'boolean'],
        ]);
        $this->checkRows($definition, $data['rows'], $targets);

        DB::transaction(function () use ($kind, $subject, $definition, $data): void {
            AccessMapping::query()->where('kind', $kind)->where('subject_id', $subject)->delete();
            $defaultTaken = false;
            foreach ($data['rows'] as $entry) {
                $values = $this->values($definition, $entry);
                if (! $this->carriesAnything($definition, $values, (bool) ($entry['selected'] ?? false))) {
                    continue;
                }
                // Only one default per subject (per target where the kind says so).
                if ($values['is_default'] && ($definition['default_scope'] ?? 'subject') === 'subject') {
                    $values['is_default'] = ! $defaultTaken;
                    $defaultTaken = true;
                }
                $mapping = AccessMapping::create(['kind' => $kind, 'subject_id' => $subject, 'target_id' => $entry['target_id']] + $values);
                if (($definition['default_scope'] ?? 'subject') === 'target') {
                    $this->clearOtherDefaults($mapping, $definition);
                }
            }
        });

        return $this->matrix($kind, $subject);
    }

    /** "New X mapping": one row; an existing subject × target is updated in place. */
    public function store(Request $request, string $kind): JsonResponse
    {
        $definition = $this->definition($kind);
        $data = $this->validatedRow($request, $kind, $definition, creating: true);

        $mapping = DB::transaction(function () use ($kind, $definition, $data): AccessMapping {
            $values = $this->values($definition, $data);
            $mapping = AccessMapping::query()->updateOrCreate(['kind' => $kind, 'subject_id' => $data['subject_id'], 'target_id' => $data['target_id']], $values);
            $this->clearOtherDefaults($mapping, $definition);

            return $mapping;
        });

        return ApiResponse::created($this->row($mapping, $definition, $this->registry->subjects($kind), $this->registry->targets($kind)));
    }

    public function update(Request $request, string $kind, AccessMapping $mapping): JsonResponse
    {
        $definition = $this->definition($kind);
        $this->assertKind($mapping, $kind);
        $data = $this->validatedRow($request, $kind, $definition, creating: false);

        DB::transaction(function () use ($mapping, $definition, $data): void {
            $mapping->update($this->values($definition, $data, $mapping));
            $this->clearOtherDefaults($mapping, $definition);
        });

        return ApiResponse::ok($this->row($mapping->fresh(), $definition, $this->registry->subjects($kind), $this->registry->targets($kind)));
    }

    public function destroy(string $kind, AccessMapping $mapping): JsonResponse
    {
        $this->definition($kind);
        $this->assertKind($mapping, $kind);

        $mapping->delete();

        return ApiResponse::noContent();
    }

    /**
     * @return array{subject: string, target: string, flags: array<string, bool>, extras: list<string>, permission: string}
     */
    private function definition(string $kind): array
    {
        try {
            $definition = $this->registry->kind($kind);
        } catch (InvalidArgumentException) {
            abort(404);
        }
        $this->allow($definition['permission']);

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function validatedRow(Request $request, string $kind, array $definition, bool $creating): array
    {
        $required = $definition['required'] ?? [];
        $range = $definition['amount_range'] ?? null;
        $rules = [
            'amount' => [in_array('amount', $required, true) && $creating ? 'required' : 'nullable', 'numeric', ...($range ? ["between:{$range[0]},{$range[1]}"] : ['min:0'])],
            'text' => ['nullable', 'string', 'max:120'],
            'date_from' => [in_array('date_from', $required, true) && $creating ? 'required' : 'nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
        ];
        foreach (array_keys($definition['flags']) as $flag) {
            $rules["flag_{$flag}"] = ['sometimes', 'boolean'];
        }
        if ($creating) {
            $rules['subject_id'] = ['required', 'string', Rule::in(array_map('strval', array_keys($this->registry->subjects($kind))))];
            $rules['target_id'] = ['required', 'string', Rule::in(array_map('strval', array_keys($this->registry->targets($kind))))];
        }

        $data = $request->validate($rules, [
            'subject_id.required' => __('access.mapping.subject_required.'.$definition['subject']),
            'target_id.required' => __('access.mapping.target_required'),
            'amount.required' => __('access.mapping.points_required'),
            'amount.between' => __('access.mapping.points_range'),
            'date_from.required' => __('access.mapping.from_required'),
            'date_to.after_or_equal' => __('access.mapping.to_after_from'),
        ]);

        // flag_issue … → flags.issue …, the shape `values()` reads.
        $data['flags'] = [];
        foreach (array_keys($definition['flags']) as $flag) {
            if (array_key_exists("flag_{$flag}", $data)) {
                $data['flags'][$flag] = (bool) $data["flag_{$flag}"];
            }
        }

        return $data;
    }

    /**
     * The stored values from a request row, keeping what an edit did not send.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function values(array $definition, array $entry, ?AccessMapping $current = null): array
    {
        $flags = [];
        foreach (array_keys($definition['flags']) as $flag) {
            $flags[$flag] = (bool) ($entry['flags'][$flag] ?? ($current?->flags[$flag] ?? false));
        }

        $extra = fn (string $key) => in_array($key, $definition['extras'], true)
            ? (array_key_exists($key, $entry) ? ($entry[$key] === '' ? null : $entry[$key]) : $current?->{$key})
            : null;

        return [
            'flags' => $flags === [] ? null : $flags,
            'amount' => in_array('amount', $definition['extras'], true) ? ($entry['amount'] ?? ($current ? $current->amount : null)) : null,
            'text' => ($t = $extra('text')) === null ? null : (trim((string) $t) ?: null),
            'date_from' => $extra('date_from'),
            'date_to' => $extra('date_to'),
            'sort_order' => (int) ($entry['sort_order'] ?? ($current?->sort_order ?? 0)),
            'is_default' => in_array('is_default', $definition['extras'], true) && (bool) ($entry['is_default'] ?? ($current?->is_default ?? false)),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $values
     */
    private function carriesAnything(array $definition, array $values, bool $selected): bool
    {
        if ($definition['flags'] === [] || ($definition['select'] ?? false)) {
            return $selected;
        }

        return in_array(true, $values['flags'] ?? [], true);
    }

    /**
     * "Setting this clears the flag on the subject's other rows" — or, where the
     * default is per target, on the target's other rows (a buyer's other stores).
     *
     * @param  array<string, mixed>  $definition
     */
    private function clearOtherDefaults(AccessMapping $mapping, array $definition): void
    {
        if ($mapping->is_default) {
            $column = ($definition['default_scope'] ?? 'subject') === 'target' ? 'target_id' : 'subject_id';
            AccessMapping::query()->where('kind', $mapping->kind)->where($column, $mapping->{$column})
                ->whereKeyNot($mapping->id)->update(['is_default' => false]);
        }
    }

    /**
     * A ticked matrix row must carry what the kind requires — points within the
     * range, a start date — row by row, as the matrix shows them.
     *
     * @param  array<string, mixed>  $definition
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $targets
     */
    private function checkRows(array $definition, array $rows, array $targets): void
    {
        $required = $definition['required'] ?? [];
        $range = $definition['amount_range'] ?? null;
        $errors = [];
        foreach (array_values($rows) as $i => $row) {
            $values = $this->values($definition, $row);
            if (! $this->carriesAnything($definition, $values, (bool) ($row['selected'] ?? false))) {
                continue;
            }
            $name = $targets[$row['target_id']] ?? $row['target_id'];
            $amount = $row['amount'] ?? null;
            if (in_array('amount', $required, true) && ($amount === null || $amount === '')) {
                $errors["rows.{$i}.amount"] = __('access.mapping.row_points_required', ['target' => $name]);
            } elseif ($range && $amount !== null && $amount !== '' && ((float) $amount < $range[0] || (float) $amount > $range[1])) {
                $errors["rows.{$i}.amount"] = __('access.mapping.row_points_range', ['target' => $name]);
            }
            if (in_array('date_from', $required, true) && empty($row['date_from'])) {
                $errors["rows.{$i}.date_from"] = __('access.mapping.row_from_required', ['target' => $name]);
            }
            if (! empty($row['date_from']) && ! empty($row['date_to']) && $row['date_to'] < $row['date_from']) {
                $errors["rows.{$i}.date_to"] = __('access.mapping.to_after_from');
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assertSubject(string $kind, string $subject): void
    {
        if (! array_key_exists($subject, $this->registry->subjects($kind))) {
            abort(404);
        }
    }

    private function assertKind(AccessMapping $mapping, string $kind): void
    {
        if ($mapping->kind !== $kind) {
            abort(404);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, string>  $subjects
     * @param  array<string, string>  $targets
     * @return array<string, mixed>
     */
    private function row(AccessMapping $row, array $definition, array $subjects, array $targets): array
    {
        $out = [
            'id' => $row->id,
            'subject_id' => $row->subject_id,
            'subject' => $subjects[$row->subject_id] ?? $row->subject_id,
            'target_id' => $row->target_id,
            'target' => $targets[$row->target_id] ?? $row->target_id,
            'amount' => $row->amount,
            'text' => $row->text,
            'date_from' => $row->date_from?->toDateString(),
            'date_to' => $row->date_to?->toDateString(),
            'sort_order' => $row->sort_order,
            'is_default' => $row->is_default,
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
        foreach (array_keys($definition['flags']) as $flag) {
            $out["flag_{$flag}"] = $row->holds($flag);
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $map
     * @return list<array{value: string, label: string}>
     */
    private function pairs(array $map): array
    {
        return collect($map)->map(fn (string $label, string $id): array => ['value' => (string) $id, 'label' => $label])->values()->all();
    }
}
