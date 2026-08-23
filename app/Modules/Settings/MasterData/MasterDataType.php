<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData;

use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * One kind of reference data a company can maintain (SRS 6, Seed Catalog 1).
 *
 * These lists — asset types, failure codes, cost categories — are the
 * vocabulary the rest of the system is written in. They arrive two ways, and
 * both end up here: the platform seeds an industry starter set, and a company
 * adds its own. Import is a bulk path into the same rows, not a separate
 * store (ADR-066), which is what the question "do we import this or manage it"
 * comes down to: manage it here, import it when there are four hundred of them.
 *
 * A platform row has a null company_id and is shared by every tenant, so it is
 * readable by all and editable by none. Anything a company changes it owns.
 */
abstract class MasterDataType
{
    /** Stable url segment: 'asset-types'. */
    abstract public function key(): string;

    /** @return class-string<Model> */
    abstract public function model(): string;

    /** Which card on the index this list sits under. */
    abstract public function group(): string;

    /**
     * @return list<Field>
     */
    abstract public function fields(): array;

    /**
     * Where the human-readable name lives. Usually 'name'; an asset model
     * calls it 'model', because that is what it is called on the machine.
     */
    public function displayColumn(): string
    {
        return 'name';
    }

    /**
     * Tables that point at this one, as class-string => foreign key.
     *
     * Used to decide whether a row may be deleted. A category with four
     * hundred assets under it is not a mistake somebody is undoing; it is the
     * shape of their data.
     *
     * @return array<class-string<Model>, string>
     */
    public function usedBy(): array
    {
        return [];
    }

    public function title(): string
    {
        return __('masterdata.types.'.$this->key());
    }

    public function description(): string
    {
        return __('masterdata.descriptions.'.$this->key());
    }

    /**
     * Every row this company may see: the platform seed plus its own.
     *
     * @return Collection<int, Model>
     */
    public function rows(): Collection
    {
        return $this->query()
            ->orderBy($this->displayColumn())
            ->get();
    }

    /**
     * Whether the platform seeds rows of this kind that every tenant shares.
     *
     * False for a company's own organisation — nobody else's factory has this
     * company's buildings in it — and those types are simply tenant-scoped.
     */
    public function sharedWithPlatform(): bool
    {
        return true;
    }

    /**
     * Whether rows carry an active flag.
     *
     * The organisation tables do not: a building is demolished or it is not,
     * and the way out of one that should never have existed is to remove it.
     */
    public function supportsActive(): bool
    {
        return true;
    }

    public function query(): Builder
    {
        $model = $this->model();

        // Shared types carry platform rows with a null company_id and need the
        // scope that lets those through. Tenant-owned ones are already
        // constrained by the global scope, and asking them for availableTo()
        // would be asking for a method they have no reason to have.
        return $this->sharedWithPlatform()
            ? $model::query()->availableTo(app(TenantContext::class)->companyId())
            : $model::query();
    }

    /**
     * @return list<string>
     */
    public function columns(): array
    {
        $columns = [$this->displayColumn(), 'code'];

        foreach ($this->fields() as $field) {
            if ($field->inList && ! in_array($field->name, $columns, true)) {
                $columns[] = $field->name;
            }
        }

        return $columns;
    }

    /**
     * How the row reads in a list. Overridden where a bare column is not
     * enough — a reference field should show the parent's name, not its ulid.
     */
    public function display(Model $row, string $column): string
    {
        $value = $row->getAttribute($column);

        if (is_bool($value)) {
            return $value ? __('common.yes') : __('common.no');
        }

        if ($value === null) {
            return '—';
        }

        foreach ($this->fields() as $field) {
            if ($field->name !== $column) {
                continue;
            }

            if ($field->type === Field::REFERENCE) {
                return $this->referenceLabel((string) $field->reference, (string) $value);
            }

            if ($field->type === Field::BELONGS_TO) {
                $parent = $field->model::query()->find($value);

                return (string) ($parent?->name ?? $value);
            }
        }

        return (string) $value;
    }

    /**
     * A ulid in a table tells nobody anything, so the parent is looked up and
     * named. Cached per type for the life of the request: a list of forty
     * failure codes points at a handful of categories.
     */
    private function referenceLabel(string $typeKey, string $id): string
    {
        $parent = app(MasterDataRegistry::class)->find($typeKey);

        if ($parent === null) {
            return $id;
        }

        $this->labels[$typeKey] ??= $parent->query()
            ->pluck($parent->displayColumn(), 'id')
            ->all();

        return (string) ($this->labels[$typeKey][$id] ?? $id);
    }

    /** @var array<string, array<string, string>> */
    private array $labels = [];
}
