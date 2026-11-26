<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Support\DyeBatches;
use App\Modules\Erp\Support\ResourceDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * The shared ground of Dye House → Batch (Bulk)'s screen-kit lists
 * (AnnotechERP 17/03): one view and one manage permission, the company's own
 * rows to pick from, and the names their grids show, read once.
 */
abstract class DyeBatchResource extends ResourceDefinition
{
    /** @var array<string, array<string, mixed>> */
    private array $names = [];

    public function viewPermission(): string
    {
        return 'dyehouse.batch.view';
    }

    public function managePermission(): string
    {
        return 'dyehouse.batch.manage';
    }

    protected function company(): string
    {
        return DyeBatches::company();
    }

    protected function today(): string
    {
        return CarbonImmutable::now('Asia/Dhaka')->toDateString();
    }

    /** A row of this company's table. */
    protected function own(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('company_id', $this->company());
    }

    /** @return array<string, array<string, mixed>> */
    protected function names(): array
    {
        return $this->names ?: $this->names = DyeBatches::names();
    }

    /** "1111" and "LPP-2026-000007": an order's style and number. @return array{0: ?string, 1: ?string} */
    protected function styleAndOrder(?string $orderId): array
    {
        $o = $orderId ? ($this->names()['orders'][$orderId] ?? null) : null;

        return [$o ? ($o->work_style_no ?: $o->style_no) : null, $o?->order_no];
    }
}
