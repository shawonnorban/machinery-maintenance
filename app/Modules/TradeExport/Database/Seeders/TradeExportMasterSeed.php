<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Database\Seeders;

use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;

/** Trade & Export → Master Data sample rows (AnnotechERP 22/01): forwarders, bank branches, and each buyer's notify party and LC applicant. Banks and the other reference lists ship as defaults. */
final class TradeExportMasterSeed extends MasterSeed
{
    public function seed(ResourceSeeder $s): void
    {
        $s->ensure('tx-banks');
        $s->seed('tx-reference-entries', array_map(fn (array $f, int $i) => ['list' => 'FORWARDER', 'name' => $f[0], 'short_name' => $f[1], 'display_order' => 20 + $i], [
            ['Kuehne + Nagel Bangladesh Ltd', 'KN'], ['DHL Global Forwarding Bangladesh', 'DHL-GF'], ['Maersk Logistics Bangladesh', 'MAERSK'], ['Expeditors Bangladesh Ltd', 'EXPD'],
        ], [0, 1, 2, 3]), ['list', 'short_name']);

        $bank = fn (ResourceSeeder $s, string $code) => $s->id('tx-banks', ['code' => $code]);
        $branches = [
            ['BNK-BRAC', 'BRAC-GUL', 'Gulshan Branch', '50 Gulshan Avenue, Gulshan 1, Dhaka 1212', 'BRAKBDDH', '060261726'],
            ['BNK-BRAC', 'BRAC-MTJ', 'Motijheel Corporate Branch', '1 Dilkusha C/A, Motijheel, Dhaka 1000', 'BRAKBDDH', '060271637'],
            ['DBBL', 'DBBL-FEX', 'Motijheel Foreign Exchange Branch', '49 Motijheel C/A, Dhaka 1000', 'DBBLBDDH', '090271638'],
            ['EBL', 'EBL-PRN', 'Principal Branch', '100 Gulshan Avenue, Dhaka 1212', 'EBLDBDDH', '095261725'],
            ['BNK-ASIA', 'BA-UTR', 'Uttara Branch', 'House 13, Sector 3, Uttara, Dhaka 1230', 'BALBBDDH', '070264563'],
        ];
        $s->seed('tx-bank-branches', array_map(fn (array $b) => fn (ResourceSeeder $s) => [
            'bank_id' => $bank($s, $b[0]), 'code' => $b[1], 'name' => $b[2], 'address' => $b[3], 'swift_code' => $b[4], 'routing_no' => $b[5],
        ], $branches), ['code']);

        $buyer = fn (ResourceSeeder $s, string $code) => $s->id('buyers', ['code' => $code]);
        $origin = fn (ResourceSeeder $s, string $code) => $s->id('origin-countries', ['code' => $code]);
        $parties = [
            ['LPP', 'LPP S.A. Logistics Centre', 'ul. Pomorska 1, 83-000 Pruszcz Gdański, Poland', '616', '83-000', '+48 58 769 6969', 'logistics@lpp.example'],
            ['HM', 'H&M Distribution Centre Hamburg', 'Billbrookdeich 210, 22113 Hamburg, Germany', '276', '22113', '+49 40 350 9500', 'dc.hamburg@hm.example'],
            ['ITX', 'Inditex Logistics Arteixo', 'Polígono Industrial de Sabón, 15142 Arteixo, Spain', '724', '15142', '+34 981 185 400', 'logistics@inditex.example'],
            ['PRM', 'Primark Distribution Thrapston', 'Islip Furnace, Thrapston NN14 4EX, United Kingdom', '826', 'NN14 4EX', '+44 1832 732 100', 'thrapston@primark.example'],
            ['CNA', 'C&A Distribution Mönchengladbach', 'Regioparkring 25, 41199 Mönchengladbach, Germany', '276', '41199', '+49 2166 977 0', 'logistics@canda.example'],
        ];
        $s->seed('tx-notify-parties', array_map(fn (array $p) => fn (ResourceSeeder $s) => [
            'buyer_id' => $buyer($s, $p[0]), 'name' => $p[1], 'address' => $p[2], 'country_id' => $origin($s, $p[3]), 'post_code' => $p[4], 'phone' => $p[5], 'email' => $p[6],
        ], $parties), ['buyer_id', 'name']);

        $applicants = [
            ['LPP', 'LPP Spolka Akcyjna', 'ul. Łąkowa 39/44, 80-769 Gdańsk, Poland'],
            ['HM', 'H & M Hennes & Mauritz GBC AB', 'Mäster Samuelsgatan 46A, 106 38 Stockholm, Sweden'],
            ['ITX', 'Industria de Diseño Textil S.A.', 'Avenida de la Diputación, 15143 Arteixo, A Coruña, Spain'],
            ['PRM', 'Primark Stores Ltd', 'Weston Centre, 10 Grosvenor Street, London W1K 4QY, United Kingdom'],
            ['KIA', 'Kiabi Europe SAS', '100 Rue du Calvaire, 59510 Hem, France'],
        ];
        $s->seed('tx-lc-applicants', array_map(fn (array $a) => fn (ResourceSeeder $s) => ['buyer_id' => $buyer($s, $a[0]), 'name' => $a[1], 'address' => $a[2]], $applicants), ['buyer_id', 'name']);
    }
}
