<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Resources;

use App\Modules\GeneralStore\Models\GsStore;
use App\Modules\GeneralStore\Support\GeneralStoreDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Modules\Inventory\Models\Store;

/**
 * Store / Location: the stores goods are kept in, where each is, who is in
 * charge, and the one entry forms open on. General Store → Master Data
 * (AnnotechERP 10/01).
 */
class StoreLocationResource extends StoreMasterResource
{
    public function key(): string
    {
        return 'store-locations';
    }

    public function model(): string
    {
        return GsStore::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'warehouse_id', 'store_type', 'location', 'in_charge', 'is_default', 'active'];
    }

    public function booleans(): array
    {
        return ['active', 'is_default'];
    }

    public function query(): Builder
    {
        GeneralStoreDefaults::stores($this->company());

        return parent::query();
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:32', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            // The one store list: a store sits in a warehouse (its factory) and has a type.
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('company_id', $this->company())],
            'store_type' => ['nullable', Rule::in(Store::STORE_TYPES)],
            'location' => ['nullable', 'string', 'max:255'],
            'in_charge' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $warehouse = DB::table('warehouses')->leftJoin('factories', 'factories.id', '=', 'warehouses.factory_id')
            ->where('warehouses.id', $row->getAttribute('warehouse_id'))->first(['warehouses.code', 'warehouses.name', 'factories.code as factory']);
        $out['warehouse'] = $warehouse ? trim(($warehouse->factory ? $warehouse->factory.' · ' : '').$warehouse->name) : null;

        return $out;
    }

    /** One default per company. */
    public function afterSave(Model $row, bool $created): void
    {
        if ($row->getAttribute('is_default')) {
            GsStore::query()->whereKeyNot($row->getKey())->where('is_default', true)->update(['is_default' => false]);
        }
    }
}
