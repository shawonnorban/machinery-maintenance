<?php

declare(strict_types=1);

namespace App\Modules\Erp\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Seeds a resource-kit master screen the way the screen itself saves a row:
 * the definition's own rules, defaults and beforeSave/afterSave, inside the
 * tenant context the caller has set. A row already there (matched on its
 * natural key) is left alone, so a seeder can run again without duplicating
 * anything or touching what a company has since changed.
 */
final class ResourceSeeder
{
    /** @var array<string, int> resource key => rows created this run */
    private array $created = [];

    /** @var list<string> seed rows a business rule turned away, with the reason */
    private array $skipped = [];

    public function __construct(private readonly ResourceRegistry $registry) {}

    /**
     * @param  list<array<string, mixed>|Closure(self): array<string, mixed>>  $rows  a closure resolves references to rows seeded before it
     * @param  list<string>|null  $match  the natural key; default "code" when the screen has one, else "name"
     */
    public function seed(string $key, array $rows, ?array $match = null): void
    {
        $definition = $this->definition($key);
        $rules = $definition->rules(null);
        $match ??= array_key_exists('code', $rules) ? ['code'] : ['name'];

        foreach ($rows as $row) {
            try {
                $row = $row instanceof Closure ? $row($this) : $row;
            } catch (RuntimeException $e) {
                // A row pointing at something this company does not have (a seed row turned away, no such setup).
                $this->skipped[] = "{$key}: ".$e->getMessage();

                continue;
            }
            // A key field the row leaves out is matched as empty (a buyer-wide standard has no line).
            $where = array_combine($match, array_map(fn (string $field) => $row[$field] ?? null, $match));
            if (array_filter($where, fn ($v) => $v !== null) === []) {
                throw new RuntimeException("Seed row for {$key} lacks its key (".implode(', ', $match).').');
            }
            if ($this->matching($definition, $where)->exists()) {
                continue;
            }
            $this->create($definition, $key, $row);
        }
    }

    /** Opens a screen's list once, which creates the defaults a module ships with (fabric types, T&A tasks …). */
    public function ensure(string $key): void
    {
        $this->definition($key)->query()->exists();
    }

    /** The id of a row seeded before (or already there, defaults included), by the fields given. */
    public function id(string $key, array $where): string
    {
        $definition = $this->definition($key);
        $model = $definition->model();
        $id = $this->matching($definition, $where)->value((new $model)->qualifyColumn('id'));
        if ($id === null) {
            $why = array_values(array_filter($this->skipped, fn (string $line) => str_starts_with($line, "{$key}: ")));
            throw new RuntimeException("Seed reference not found: {$key} ".json_encode($where).($why ? ' — turned away: '.implode(' | ', $why) : ''));
        }

        return $id;
    }

    /** @return array<string, int> */
    public function created(): array
    {
        return $this->created;
    }

    /** @return list<string> */
    public function skipped(): array
    {
        return $this->skipped;
    }

    private function create(ResourceDefinition $definition, string $key, array $row): void
    {
        // Some definitions read the request in their rules or beforeSave, as the screen would send it.
        $request = Request::create('/', 'POST', $row);
        app()->instance('request', $request);

        try {
            $data = Validator::make($row, $definition->rules(null), $definition->messages())->validate();
        } catch (ValidationException $e) {
            $this->skipped[] = "{$key}: invalid ".json_encode($e->errors(), JSON_UNESCAPED_UNICODE).' '.json_encode(array_filter($row, 'is_scalar'), JSON_UNESCAPED_UNICODE);

            return;
        }

        try {
            DB::transaction(function () use ($definition, $data): void {
                $model = $definition->model();
                /** @var Model $record */
                $record = new $model;
                $record->fill($definition->beforeSave($data + $definition->defaults(), null));
                $record->save();
                $definition->afterSave($record, true);
            });
        } catch (ValidationException $e) {
            // A business rule against what the company already keeps (an overlapping band, a second default):
            // its own row wins; the seed row is reported and left out.
            $this->skipped[] = "{$key}: ".collect($e->errors())->flatten()->implode(' ').' '.json_encode(array_filter($row, 'is_scalar'), JSON_UNESCAPED_UNICODE);

            return;
        }
        $this->created[$key] = ($this->created[$key] ?? 0) + 1;
    }

    /** The screen's own list narrowed to the natural key — text compared without case, since many masters store codes and names upper-cased. */
    private function matching(ResourceDefinition $definition, array $where): Builder
    {
        $model = $definition->model();
        $query = $definition->query()->reorder();
        foreach ($where as $column => $value) {
            $qualified = (new $model)->qualifyColumn($column);
            is_string($value) ? $query->whereRaw("UPPER(CAST({$qualified} AS TEXT)) = UPPER(?)", [$value]) : $query->where($qualified, $value);
        }

        return $query;
    }

    private function definition(string $key): ResourceDefinition
    {
        return $this->registry->find($key) ?? throw new RuntimeException("Unknown resource: {$key}");
    }
}
