<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Resources;

use App\Modules\GatePass\Models\GatePassLocation;
use App\Modules\GatePass\Models\GatePassLocationType;
use App\Modules\GatePass\Support\GatePassDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Location Type: embroidery, printing, washing, finishing, dyeing, a testing
 * laboratory, a buyer office, a supplier … with how many locations each has.
 * Gate Pass → Master Data (AnnotechERP 11/01).
 */
class GatePassLocationTypeResource extends GatePassMasterResource
{
    public function key(): string
    {
        return 'gatepass-location-types';
    }

    public function model(): string
    {
        return GatePassLocationType::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'description', 'active'];
    }

    public function query(): Builder
    {
        GatePassDefaults::locationTypes($this->company());

        return parent::query();
    }

    public function rules(?Model $row): array
    {
        return $this->basicRules($row);
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['locations'] = GatePassLocation::query()->where('location_type_id', $row->getKey())->count();

        return $out;
    }
}
