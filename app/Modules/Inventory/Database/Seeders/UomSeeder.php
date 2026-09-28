<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Seeders;

use App\Modules\Inventory\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

/**
 * The units a garment store counts in (Master Data spec §7).
 *
 * Platform rows: null company_id, visible to every tenant. This is the
 * source of truth for an item's stock unit — the list is every unit found
 * in a real 7,984-item factory library (yarn in cones and kilos, thread in
 * reels, paper in reams, pipe in running feet), not a tidy subset of it.
 */
class UomSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string}> code => name */
    public const UNITS = [
        ['PCS', 'Pieces'],
        ['SET', 'Set'],
        ['PAIR', 'Pair'],
        ['BOX', 'Box'],
        ['PAK', 'Packet'],
        ['BAG', 'Bag'],
        ['BTL', 'Bottle'],
        ['DRUM', 'Drum'],
        ['POT', 'Pot'],
        ['AMPL', 'Ampoule'],
        ['DZN', 'Dozen'],
        ['GRS', 'Gross'],
        ['BOOK', 'Book'],
        ['REAM', 'Ream'],
        ['MTR', 'Metre'],
        ['YDS', 'Yard'],
        ['FEET', 'Feet'],
        ['RFT', 'Running feet'],
        ['INCH', 'Inch'],
        ['SFT', 'Square feet'],
        ['CFT', 'Cubic feet'],
        ['M3', 'Cubic metre'],
        ['KG', 'Kilogram'],
        ['MG', 'Milligram'],
        ['LBS', 'Pound'],
        ['LTR', 'Litre'],
        ['ROLL', 'Roll'],
        ['REEL', 'Reel'],
        ['COIL', 'Coil'],
        ['CONE', 'Cone'],
        ['CONE2000', 'Cone (2000 m)'],
        ['CONE3000', 'Cone (3000 m)'],
        ['CONE4000', 'Cone (4000 m)'],
        ['CONE5000', 'Cone (5000 m)'],
    ];

    public function run(): void
    {
        foreach (self::UNITS as [$code, $name]) {
            UnitOfMeasure::updateOrCreate(
                ['company_id' => null, 'code' => $code],
                ['name' => $name, 'active' => true],
            );
        }
    }
}
