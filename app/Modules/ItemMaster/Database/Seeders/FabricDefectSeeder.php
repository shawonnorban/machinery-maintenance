<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Database\Seeders;

use App\Modules\ItemMaster\Models\FabricDefect;
use Illuminate\Database\Seeder;

/**
 * The default Knit and Woven defect lists (Inventory spec §4.4). Platform
 * rows (null company_id) every company starts from; a company can add its
 * own via the ordinary Master Data screen — the Woven list in particular is
 * spec'd as "Slub, Missing End, Broken Pick, Oil ইত্যাদি" (etc.), an
 * explicitly non-exhaustive starter set rather than a closed catalogue.
 */
class FabricDefectSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string}> code => name, per family */
    public const KNIT = [
        ['HOLE_NEEDLE_LINE', 'Hole / Needle line'],
        ['DROP_STITCH', 'Drop stitch'],
        ['BARRE', 'Barré'],
        ['CONTAMINATION', 'Contamination'],
        ['OIL_SPOT', 'Oil spot'],
        ['UNEVEN_DYEING', 'Uneven dyeing'],
        ['WIDTH_VARIATION', 'Width variation'],
        ['GSM_VARIATION', 'GSM variation'],
        ['SNAGGING', 'Snagging'],
        ['OTHER', 'Other'],
    ];

    /**
     * Widened to the list a Bangladeshi woven inspection sheet actually
     * carries — the tally columns on the mill's own form, not a generic
     * textbook set. An inspector who cannot find the fault they are
     * looking at files it under Other, and Other tells nobody anything.
     */
    public const WOVEN = [
        ['BROKEN_END', 'Broken end'],
        ['BROKEN_PICK', 'Broken pick'],
        ['MISSING_END', 'Missing end'],
        ['MISSING_YARN', 'Missing yarn'],
        ['KNOT', 'Knot'],
        ['SLUB', 'Slub'],
        ['NAPS', 'Naps'],
        ['THICK_YARN', 'Thick yarn'],
        ['FOREIGN_FIBER', 'Foreign fibre'],
        ['DIRTY_SPOT', 'Dirty spot'],
        ['OIL_SPOT', 'Oil stain'],
        ['COLOR_OUT', 'Colour out'],
        ['HOLE', 'Hole'],
        ['WAVY_FAULT', 'Wavy fault'],
        ['CREASE_MARK', 'Crease mark'],
        ['STOP_MARK', 'Stop mark'],
        ['WIDTH_VARIATION', 'Width variation'],
        ['GSM_VARIATION', 'GSM variation'],
        ['SHADE_VARIATION', 'Shade variation'],
        ['OTHER', 'Other'],
    ];

    public function run(): void
    {
        foreach (['KNIT' => self::KNIT, 'WOVEN' => self::WOVEN] as $family => $defects) {
            foreach ($defects as [$code, $name]) {
                FabricDefect::updateOrCreate(
                    ['company_id' => null, 'fabric_family' => $family, 'code' => $code],
                    ['name' => $name, 'active' => true],
                );
            }
        }
    }
}
