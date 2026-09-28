<?php

declare(strict_types=1);

namespace App\Modules\Metering\Database\Seeders;

use App\Modules\Metering\Models\MeterType;
use Illuminate\Database\Seeder;

/**
 * What gets counted on a machine (Seed Catalog 7).
 *
 * Usage-based maintenance has nothing to hang off until these exist: a plan
 * that says "service every 500 running hours" needs a meter type called
 * running hours before anybody can record one.
 *
 * All cumulative. A counter on a machine only ever goes up, so a reading lower
 * than the last one is a replaced counter rather than a typo — which is a
 * different correction, and the flag is what tells the two apart.
 */
class MeterTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['RUNNING_HOURS', 'Running hours', 'HOUR'],
            ['STITCH_COUNT', 'Stitch count', 'STITCH'],
            ['CYCLE_COUNT', 'Cycle count', 'CYCLE'],
            ['PIECE_COUNT', 'Piece count', 'PIECE'],
            ['FUEL_CONSUMED', 'Fuel consumed', 'LITRE'],
            ['ENERGY_CONSUMED', 'Energy consumed', 'KWH'],
            ['WATER_CONSUMED', 'Water consumed', 'CUBIC_METRE'],
            ['STEAM_CONSUMED', 'Steam consumed', 'KG'],
            ['DISTANCE', 'Distance', 'KM'],
            // A dye vessel's service interval is counted in batches, and a
            // knitting machine's in kilograms of fabric off the take-down —
            // neither of them in hours.
            ['BATCH_COUNT', 'Batch count', 'BATCH'],
            ['FABRIC_LENGTH', 'Fabric produced (length)', 'METRE'],
            ['FABRIC_WEIGHT', 'Fabric produced (weight)', 'KG'],
        ] as [$code, $name, $unit]) {
            MeterType::updateOrCreate(
                ['company_id' => null, 'code' => $code],
                [
                    'name' => $name,
                    'unit' => $unit,
                    'is_cumulative' => true,
                    'active' => true,
                ],
            );
        }
    }
}
