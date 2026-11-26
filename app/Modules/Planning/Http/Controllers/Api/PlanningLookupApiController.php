<?php

declare(strict_types=1);

namespace App\Modules\Planning\Http\Controllers\Api;

use App\Modules\HrPayroll\Models\HrShift;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Planning\Support\PlanningLabels;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Support\Clock;
use Illuminate\Http\JsonResponse;

/**
 * What Capacity Planning's forms pick from (AnnotechERP 12/01): units
 * (factories), floors as "FAC-001>>FLR-001", the floor lines of each floor,
 * the planning lines and the buyers — for whoever may see planning, without
 * needing Company Management's or Merchandise Desk's own permissions.
 */
class PlanningLookupApiController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        $this->allow('planning.master.view');
        $company = $this->caller()->companyId;
        $factories = Factory::query()->where('company_id', $company)->orderBy('code')->get(['id', 'code', 'name']);
        $factoryCodes = $factories->pluck('code', 'id');
        $buildings = Building::query()->pluck('factory_id', 'id');

        return ApiResponse::ok([
            'factories' => $factories->map(fn (Factory $f): array => ['value' => $f->id, 'label' => "{$f->name} ({$f->code})"])->all(),
            'floors' => Floor::query()->orderBy('code')->get(['id', 'code', 'building_id'])->map(function (Floor $f) use ($buildings, $factoryCodes): array {
                $factory = $buildings[$f->building_id] ?? null;

                return ['value' => $f->id, 'label' => ($factory ? ($factoryCodes[$factory] ?? '?') : '?').'>>'.$f->code, 'factory_id' => $factory];
            })->all(),
            'floor_lines' => ProductionLine::query()->orderBy('code')->get(['id', 'code', 'name', 'floor_id'])
                ->map(fn (ProductionLine $l): array => ['value' => $l->id, 'label' => "{$l->code} — {$l->name}", 'floor_id' => $l->floor_id])->all(),
            'lines' => PlanningLine::query()->orderBy('display_order')->orderBy('code')->get(['id', 'code', 'name', 'active'])->map(function (PlanningLine $l): array {
                $labels = PlanningLabels::forLine($l->id);

                return ['value' => $l->id, 'label' => ($labels['floor'] ?? '?').'>>'.$l->code, 'active' => $l->active];
            })->all(),
            'buyers' => Buyer::query()->orderBy('short_name')->get(['id', 'code', 'short_name'])->map(fn (Buyer $b): array => ['value' => $b->id, 'label' => "{$b->short_name} ({$b->code})"])->all(),
            // A line's shifts are HR & Payroll's (Shift Master), the one source of shift times.
            'shifts' => HrShift::query()->where('active', true)->orderBy('start_time')->get(['id', 'code', 'name', 'start_time', 'end_time', 'break_min'])
                ->map(fn (HrShift $s): array => ['value' => $s->id, 'label' => "{$s->name} ({$s->code}) · ".Clock::range($s->start_time, $s->end_time)])->all(),
        ]);
    }
}
