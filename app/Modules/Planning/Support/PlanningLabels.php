<?php

declare(strict_types=1);

namespace App\Modules\Planning\Support;

use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\Tenancy\Models\ProductionLine;

/**
 * How a planning line is named on Capacity Planning's screens — its unit
 * (factory) code, floor code and floor line code — read once per request for
 * all lines, not once per row.
 */
final class PlanningLabels
{
    /** @var array<string, array{unit: ?string, floor: ?string, floor_line: ?string, line: ?string, factory_id: ?string}>|null */
    private static ?array $lines = null;

    /** @return array{unit: ?string, floor: ?string, floor_line: ?string, line: ?string, factory_id: ?string} */
    public static function forLine(?string $planningLineId): array
    {
        if (self::$lines === null || ($planningLineId && ! isset(self::$lines[$planningLineId]))) {
            self::$lines = self::load();
        }

        return self::$lines[$planningLineId] ?? ['unit' => null, 'floor' => null, 'floor_line' => null, 'line' => null, 'factory_id' => null];
    }

    /** @return array<string, array{unit: ?string, floor: ?string, floor_line: ?string, line: ?string, factory_id: ?string}> */
    private static function load(): array
    {
        $floors = Floor::query()->get(['id', 'code', 'building_id'])->keyBy('id');
        $buildings = Building::query()->pluck('factory_id', 'id');
        $factories = Factory::query()->get(['id', 'code'])->keyBy('id');
        $floorLines = ProductionLine::query()->pluck('code', 'id');
        $out = [];
        foreach (PlanningLine::query()->get(['id', 'floor_id', 'floor_line_id', 'code']) as $line) {
            $floor = $floors->get($line->floor_id);
            $factoryId = $floor ? ($buildings[$floor->building_id] ?? null) : null;
            $out[$line->id] = [
                'unit' => $factoryId ? $factories->get($factoryId)?->code : null,
                'floor' => $floor?->code,
                'floor_line' => $line->floor_line_id ? ($floorLines[$line->floor_line_id] ?? null) : null,
                'line' => $line->code,
                'factory_id' => $factoryId,
            ];
        }

        return $out;
    }

    /** Forget what was read, after a line is saved. */
    public static function forget(): void
    {
        self::$lines = null;
    }
}
