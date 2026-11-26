<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\Designation;

/**
 * Company Management's Designation tab (AnnotechERP 01/05). Department and
 * grade are optional pointers; the employee category is a plain list until
 * HR & Payroll brings its own master.
 */
class DesignationData extends MasterDataType
{
    public function key(): string
    {
        return 'designations';
    }

    public function model(): string
    {
        return Designation::class;
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
            Field::reference('department_id', 'departments', required: false),
            Field::enum('employee_category', Designation::EMPLOYEE_CATEGORIES, required: false),
            Field::reference('grade_id', 'grades', required: false),
            Field::status(),
        ];
    }

    public function autoCodePrefix(): ?string
    {
        return 'DSG';
    }
}
