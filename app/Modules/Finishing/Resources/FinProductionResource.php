<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Modules\DyeHouse\Support\DyeBatches;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * The shared ground of Finishing Line → DF Production's papers (AnnotechERP
 * 18/02): their own view and manage permission, a company row, the paper
 * number, and today on the floor's clock.
 */
abstract class FinProductionResource extends FinishingResource
{
    public function viewPermission(): string
    {
        return 'finishing.production.view';
    }

    public function managePermission(): string
    {
        return 'finishing.production.manage';
    }

    public function deletable(): bool
    {
        return true;
    }

    protected function own(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('company_id', $this->company());
    }

    protected function number(string $prefix, string $table, string $column, string $date): string
    {
        return DyeBatches::nextNumber($prefix, $table, $column, $date, 'Ym');
    }

    protected function today(): string
    {
        return CarbonImmutable::now('Asia/Dhaka')->toDateString();
    }

    protected function kg(mixed $v): ?float
    {
        return $v === null ? null : round((float) $v, 3);
    }
}
