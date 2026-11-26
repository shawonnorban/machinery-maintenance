<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\Designation;
use App\Modules\Tenancy\Models\Grade;

/**
 * Company Management's Grade tab (AnnotechERP 01/05). Ranked by "Order", so
 * the list sorts by that rather than by name.
 */
class GradeData extends MasterDataType
{
    public function key(): string
    {
        return 'grades';
    }

    public function model(): string
    {
        return Grade::class;
    }

    public function group(): string
    {
        return 'organisation';
    }

    public function sharedWithPlatform(): bool
    {
        return false;
    }

    public function supportsActive(): bool
    {
        return false;
    }

    public function fields(): array
    {
        return [
            Field::code(auto: true),
            Field::text('name'),
            Field::number('sort_order', required: false),
            Field::status(),
        ];
    }

    public function autoCodePrefix(): ?string
    {
        return 'GRD';
    }

    public function usedBy(): array
    {
        return [
            Designation::class => 'grade_id',
        ];
    }
}
