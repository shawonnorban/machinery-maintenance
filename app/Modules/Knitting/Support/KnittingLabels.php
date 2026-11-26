<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support;

use App\Modules\HrPayroll\Models\HrShift;
use App\Modules\Knitting\Models\KnitMachine;
use App\Modules\Knitting\Models\KnitRefEntry;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use App\Shared\Support\Clock;

/**
 * The words Knit Production's grids show for the ids they hold — a floor as
 * "SWEING — FAC01", a schedule as "General (GS001) · 08:00–17:00", a machine
 * by its number — read once per request, not once per row.
 */
final class KnittingLabels
{
    /** @var array<string, array<string, string>> */
    private static array $cache = [];

    /** @return array<string, string> */
    public static function floors(): array
    {
        return self::$cache['floors'] ??= (function (): array {
            $buildings = Building::query()->pluck('factory_id', 'id');
            $factories = Factory::query()->pluck('code', 'id');

            return Floor::query()->orderBy('name')->get(['id', 'name', 'building_id'])
                ->mapWithKeys(fn ($f) => [$f->id => $f->name.' — '.($factories[$buildings[$f->building_id] ?? ''] ?? '?')])->all();
        })();
    }

    /** @return array<string, string> */
    public static function shifts(): array
    {
        return self::$cache['shifts'] ??= HrShift::query()->orderBy('name')->get(['id', 'name', 'code', 'start_time', 'end_time'])
            ->mapWithKeys(fn ($s) => [$s->id => "{$s->name} ({$s->code}) · ".Clock::range(substr((string) $s->start_time, 0, 5), substr((string) $s->end_time, 0, 5))])->all();
    }

    /** @return array<string, string> */
    public static function refs(): array
    {
        return self::$cache['refs'] ??= KnitRefEntry::query()->pluck('name', 'id')->all();
    }

    /** @return array<string, array{no: string, floor_id: string}> */
    public static function machines(): array
    {
        return self::$cache['machines'] ??= KnitMachine::query()->get(['id', 'machine_no', 'floor_id'])
            ->mapWithKeys(fn ($m) => [$m->id => ['no' => $m->machine_no, 'floor_id' => $m->floor_id]])->all();
    }

    public static function forget(): void
    {
        self::$cache = [];
    }
}
