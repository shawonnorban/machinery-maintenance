<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Resources;

use App\Modules\GeneralStore\Models\GsItem;
use App\Modules\GeneralStore\Models\GsUnit;
use App\Modules\GeneralStore\Support\GeneralStoreDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Unit Master: PCS, KG, LTR, Meter, Box, Carton, Packet, Pair, Dozen, Ream,
 * Roll, Set … with how many items count in each. General Store → Master Data
 * (AnnotechERP 10/01).
 */
class StoreUnitResource extends StoreMasterResource
{
    public function key(): string
    {
        return 'store-units';
    }

    public function model(): string
    {
        return GsUnit::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'active'];
    }

    public function query(): Builder
    {
        GeneralStoreDefaults::units($this->company());

        return parent::query();
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:16', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /** The platform's units are offered to every company; only the platform changes them. */
    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row instanceof GsUnit && $row->isPlatform()) {
            throw ValidationException::withMessages(['code' => __('store.master.platform_unit')]);
        }

        return parent::beforeSave($data, $row);
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['items'] = GsItem::query()->where('gs_unit_id', $row->getKey())->count();
        $out['platform'] = $row instanceof GsUnit && $row->isPlatform();

        return $out;
    }
}
