<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Resources;

use App\Modules\GatePass\Models\GatePassPurpose;
use App\Modules\GatePass\Support\GatePassDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Purpose / Reason: why goods or people go out — embroidery, printing,
 * washing, repair, sample approval, buyer submission … Gate Pass → Master
 * Data (AnnotechERP 11/01).
 */
class GatePassPurposeResource extends GatePassMasterResource
{
    public function key(): string
    {
        return 'gatepass-purposes';
    }

    public function model(): string
    {
        return GatePassPurpose::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'description', 'active'];
    }

    public function query(): Builder
    {
        GatePassDefaults::purposes($this->company());

        return parent::query();
    }

    public function rules(?Model $row): array
    {
        return $this->basicRules($row);
    }
}
