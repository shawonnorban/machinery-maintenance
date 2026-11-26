<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;

/** Colour Lab → Operations sample rows (AnnotechERP 16/01): the certificate types Brickland lists and the lab's usual equipment. */
final class ColourLabMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        $s->seed('lab-cert-types', array_map(fn (array $t) => ['code' => $t[0], 'name' => $t[1], 'issuing_body' => $t[2]], [
            ['OTS-100', 'OEKO-TEX Standard 100', 'OEKO-TEX'], ['GOTS', 'GOTS Scope Certificate', 'GOTS'], ['GRS', 'GRS Scope Certificate', 'GRS'],
            ['ZDHC', 'ZDHC MRSL Conformance', 'Higg / SAC'], ['BSCI', 'amfori BSCI Audit Report', 'BSCI'], ['CAL-SPEC', 'Spectrophotometer Calibration', 'SGS'],
            ['BCCL', 'Buyer Chemical Compliance Letter', 'Buyer'],
        ]), ['code']);
        $s->seed('lab-equipment', array_map(fn (array $e) => ['code' => $e[0], 'name' => $e[1], 'manufacturer' => $e[2], 'serial_no' => $e[3]], [
            ['SPEC-01', 'Spectrophotometer', 'Datacolor', 'DC650-22871'], ['LB-01', 'Light Box', 'Verivide', 'VV-CAC120-114'],
            ['CRK-01', 'Crockmeter', 'James Heal', 'JH-670-3381'], ['WSH-01', 'Washing Fastness Tester', 'SDL Atlas', 'SDL-LE-2210'],
        ]), ['code']);
    }
}
